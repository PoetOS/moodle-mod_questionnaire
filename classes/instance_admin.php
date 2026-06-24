<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_questionnaire;

use mod_questionnaire\local\db\dependency_record;
use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\survey\survey;
use stdClass;

/**
 * Module instance lifecycle hooks for the questionnaire activity.
 *
 * Hosts the lib.php callbacks: create / update / delete a questionnaire instance,
 * and reset user data on course reset. Also owns calendar event creation.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class instance_admin {
    /** @var int Maximum calendar event duration in seconds (5 days). */
    private const MAX_EVENT_LENGTH = 5 * 24 * 60 * 60;

    /**
     * Create a new questionnaire activity instance.
     *
     * Resolves the survey (new blank / copy / existing public), inserts the questionnaire
     * row, and fires calendar and completion events. Single point of delegation from
     * lib.php's questionnaire_add_instance().
     *
     * @param stdClass $formdata Form data from mod_form (coursemodule, course, name, …).
     * @return int|false The new questionnaire instance id, or false on failure.
     */
    public static function add_instance(stdClass $formdata): int|false {
        if (!empty($formdata->sid)) {
            // Survey already identified (e.g. restore or duplicate of a public survey).
            $sid = $formdata->sid;
        } else if ($formdata->create == 'new-0') {
            // Brand-new blank survey.
            $sdata = new stdClass();
            $sdata->name = $formdata->name;
            $sdata->realm = 'private';
            $sdata->title = $formdata->name;
            $sdata->subtitle = '';
            $sdata->info = '';
            $sdata->theme = ''; // Theme field is deprecated.
            $sdata->thankspage = '';
            $sdata->thankhead = '';
            $sdata->thankbody = '';
            $sdata->email = '';
            $sdata->feedbacknotes = '';
            $sdata->courseid = $formdata->course;
            $sid = survey::add_survey($sdata);
        } else {
            $parts = explode('-', $formdata->create);
            $copyrealm = $parts[0];
            $copyid = (int) $parts[1];

            if ($copyrealm == 'public') {
                // Reuse the existing public survey — no copy needed.
                $sid = $copyid;
            } else {
                // Copy the survey, its questions, choices, dependencies, and feedback.
                $surveyrecord = (new survey_record($copyid))->to_record();
                $questions = survey::load_questions_for_survey($copyid);
                $sid = survey::copy_survey($surveyrecord, $questions, $formdata->course);

                // All new questionnaires should be private, even copies of public/template surveys.
                $copied = new survey_record($sid);
                $copied->set('realm', 'private');
                $copied->update();

                // Signal post-actions hook to copy file areas from the original.
                $formdata->copyid = $copyid;
            }
        }

        // Enable navigation if the survey has dependency (skip-logic) data.
        if (dependency_record::count_records(['surveyid' => $sid]) > 0) {
            $formdata->navigate = 1;
        }
        $formdata->sid = $sid;

        $formdata->resume = ($formdata->resume == '1') ? 1 : 0;

        // Insert the questionnaire row.
        $formdata->id = (questionnaire_record::create_from_formdata($formdata))->get('id');

        self::set_events($formdata);

        $completiontimeexpected = !empty($formdata->completionexpected) ? $formdata->completionexpected : null;
        \core_completion\api::update_completion_date_event(
            $formdata->coursemodule,
            'questionnaire',
            $formdata->id,
            $completiontimeexpected
        );

        return $formdata->id;
    }

    /**
     * Update an existing questionnaire activity instance.
     *
     * Updates the survey realm when provided, updates the questionnaire row, and fires
     * calendar and completion events. Single point of delegation from lib.php's
     * questionnaire_update_instance().
     *
     * @param stdClass $questionnaire Form data from mod_form (instance, sid, realm, …).
     * @return bool True on success.
     */
    public static function update_instance(stdClass $questionnaire): bool {
        // Sync the survey realm when the form provides one.
        if (!empty($questionnaire->sid) && !empty($questionnaire->realm)) {
            $surveyrecord = new survey_record($questionnaire->sid);
            $surveyrecord->set('realm', $questionnaire->realm);
            $surveyrecord->update();
        }

        $questionnaire->id = $questionnaire->instance;
        $questionnaire->resume = ($questionnaire->resume == '1') ? 1 : 0;

        // Update the questionnaire row (before_update() sets timemodified automatically).
        $record = new questionnaire_record($questionnaire->id);
        foreach (
            ['name', 'intro', 'introformat', 'qtype', 'respondenttype',
            'respeligible', 'respview', 'notifications', 'opendate', 'closedate',
            'resume', 'navigate', 'grade', 'sid', 'completionsubmit',
            'autonum', 'progressbar', 'removeafter'] as $f
        ) {
            if (isset($questionnaire->$f)) {
                $record->set($f, $questionnaire->$f);
            }
        }
        $record->update();

        self::set_events($questionnaire);

        $completiontimeexpected = !empty($questionnaire->completionexpected) ? $questionnaire->completionexpected : null;
        \core_completion\api::update_completion_date_event(
            $questionnaire->coursemodule,
            'questionnaire',
            $questionnaire->id,
            $completiontimeexpected
        );

        return true;
    }

    /**
     * Delete a questionnaire instance and its survey data (if survey owned by this course).
     *
     * @param int $id Questionnaire instance id.
     * @return bool True on success.
     */
    public static function delete_instance(int $id): bool {
        global $DB;

        if (!$questionnaire = $DB->get_record('questionnaire', ['id' => $id])) {
            return false;
        }

        $result = true;

        if ($events = $DB->get_records('event', ['modulename' => 'questionnaire', 'instance' => $questionnaire->id])) {
            foreach ($events as $event) {
                $event = \calendar_event::load($event);
                $event->delete();
            }
        }

        // Delete responses owned by this questionnaire instance, then the row itself.
        self::delete_responses_for_instance($questionnaire->id);
        if (!$DB->delete_records('questionnaire', ['id' => $questionnaire->id])) {
            $result = false;
        }

        // If the survey is owned by this course, delete the survey content too.
        if ($surveyrow = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->sid])) {
            if ($surveyrow->courseid == $questionnaire->course) {
                $result = $result && survey::from_sid((int) $questionnaire->sid)->delete();
            }
        }

        return $result;
    }

    /**
     * Reset all user data for questionnaires in a course.
     *
     * @param object $data Data submitted from the reset course form.
     * @return array Status array.
     */
    public static function reset_userdata(object $data): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $componentstr = get_string('modulenameplural', 'questionnaire');
        $status = [];

        if (!empty($data->reset_questionnaire)) {
            $instances = $DB->get_records('questionnaire', ['course' => $data->courseid]);
            foreach ($instances as $instance) {
                $questionnaire = questionnaire::from_instanceid($instance->id);
                // Delete responses.
                $resps = $DB->get_records('questionnaire_response', ['questionnaireid' => $instance->id]);
                foreach ($resps as $response) {
                    $questionnaire->responses()->delete_response($response);
                }
                // Remove this questionnaire's grades (and feedback) from gradebook (if any).
                $select = "itemmodule = 'questionnaire' AND iteminstance = ?";
                if ($itemid = $DB->get_record_select('grade_items', $select, [$instance->id], 'id')) {
                    $DB->delete_records_select('grade_grades', 'itemid = ' . $itemid->id);
                }
            }
            $status[] = [
                'component' => $componentstr,
                'item' => get_string('deletedallresp', 'questionnaire'),
                'error' => false,
            ];

            $status[] = [
                'component' => $componentstr,
                'item' => get_string('gradesdeleted', 'questionnaire'),
                'error' => false,
            ];
        }
        return $status;
    }

    /**
     * Create or update calendar events for a questionnaire instance.
     *
     * Deletes any existing calendar events for the instance, then creates open and/or
     * close events based on the questionnaire's opendate and closedate.
     *
     * @param stdClass $questionnaire Questionnaire instance record (needs id, course, name, opendate, closedate).
     * @return void
     */
    public static function set_events(stdClass $questionnaire): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');

        if ($events = $DB->get_records('event', ['modulename' => 'questionnaire', 'instance' => $questionnaire->id])) {
            foreach ($events as $event) {
                $event = \calendar_event::load($event);
                $event->delete();
            }
        }

        // The open-event.
        $event = new stdClass();
        $event->description = $questionnaire->name;
        $event->courseid = $questionnaire->course;
        $event->groupid = 0;
        $event->userid = 0;
        $event->modulename = 'questionnaire';
        $event->instance = $questionnaire->id;
        $event->eventtype = 'open';
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->timestart = $questionnaire->opendate;
        $event->visible = instance_is_visible('questionnaire', $questionnaire);
        $event->timeduration = ($questionnaire->closedate - $questionnaire->opendate);

        if (
            $questionnaire->closedate && $questionnaire->opendate
            && ($event->timeduration <= self::MAX_EVENT_LENGTH)
        ) {
            // Single event for the whole questionnaire.
            $event->name = $questionnaire->name;
            $event->timesort = $questionnaire->opendate;
            \calendar_event::create($event);
        } else {
            // Separate start and end events.
            $event->timeduration = 0;
            if ($questionnaire->opendate) {
                $event->name = $questionnaire->name .
                    ' (' . get_string('questionnaireopens', 'questionnaire') . ')';
                $event->timesort = $questionnaire->opendate;
                \calendar_event::create($event);
                unset($event->id); // So we can use the same object for the close event.
            }
            if ($questionnaire->closedate) {
                $event->name = $questionnaire->name .
                    ' (' . get_string('questionnairecloses', 'questionnaire') . ')';
                $event->timestart = $questionnaire->closedate;
                $event->timesort = $questionnaire->closedate;
                $event->eventtype = 'close';
                \calendar_event::create($event);
            }
        }
    }

    /**
     * Delete all response records (and their per-question rows) for a questionnaire instance.
     *
     * @param int $questionnaireid Questionnaire instance id.
     * @return void
     */
    private static function delete_responses_for_instance(int $questionnaireid): void {
        global $DB;

        $rid = $DB->get_fieldset_select('questionnaire_response', 'id', 'questionnaireid = ?', [$questionnaireid]);
        if (!empty($rid)) {
            [$insql, $params] = $DB->get_in_or_equal($rid);
            $DB->delete_records_select('questionnaire_response_bool', "responseid $insql", $params);
            $DB->delete_records_select('questionnaire_response_date', "responseid $insql", $params);
            $DB->delete_records_select('questionnaire_resp_multiple', "responseid $insql", $params);
            $DB->delete_records_select('questionnaire_response_other', "responseid $insql", $params);
            $DB->delete_records_select('questionnaire_response_rank', "responseid $insql", $params);
            $DB->delete_records_select('questionnaire_resp_single', "responseid $insql", $params);
            $DB->delete_records_select('questionnaire_response_text', "responseid $insql", $params);
            $DB->delete_records_select('questionnaire_response_file', "responseid $insql", $params);
        }
        $DB->delete_records('questionnaire_response', ['questionnaireid' => $questionnaireid]);
    }
}
