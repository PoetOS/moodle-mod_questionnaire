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

use stdClass;

/**
 * Main module instance class for Questionnaire.
 * Does all of the heavy lifting for course module code for the standard module callbacks in lib.php.
 *
 * @package mod_questionnaire
 * @copyright  2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class instance {
    /** @var int The module instance id. */
    protected $id;

    /** @var int The course id that the instance is in */
    protected $course;

    /** @var string Name */
    protected $name;

    /** @var string Introduction */
    protected $intro;

    /** @var int Introduction format */
    protected $introformat;

    /** @var int Questionnaire type */
    protected $qtype;

    /** @var string Respondent type */
    protected $respondenttype;

    /** @var string Respondent eligibility */
    protected $respeligible;

    /** @var int Respondent view */
    protected $respview;

    /** @var int Notifications flag */
    protected $notifications;

    /** @var int Opening date */
    protected $opendate;

    /** @var int Closing date */
    protected $closedate;

    /** @var int Save / resume flag */
    protected $resume;

    /** @var int Navigate flag */
    protected $navigate;

    /** @var int Grade setting */
    protected $grade;

    /** @var int Survey id */
    protected $sid;

    /** @var int Modified timestamp */
    protected $timemodified;

    /** @var int Submission flag */
    protected $completionsubmit;

    /** @var int Auto numbering flag */
    protected $autonum;

     /** @var int Progress bar flag */
    protected $progressbar;

    /** @var int Deletion duration */
    protected $removeafter;

    /**
     * The class constructor
     * @param stdClass|null $moddata The data from the module instance (from the course_modules table)
     */
    public function __construct(?stdClass $moddata = null) {
        if (is_null($moddata)) {
            $moddata = new stdClass();
        }
        $this->id = $moddata->instance ?? ($moddata->id ?? 0);
        $this->course = $moddata->course ?? 0;
        $this->name = $moddata->name ?? '';
        $this->intro = $moddata->intro ?? '';
        $this->introformat = $moddata->introformat ?? 0;
        $this->qtype = $moddata->qtype ?? '';
        $this->respondenttype = $moddata->respondenttype ?? '';
        $this->respeligible = $moddata->respeligible ?? '';
        $this->respview = $moddata->respview ?? 0;
        $this->notifications = $moddata->notifications ?? 0;
        $this->opendate = $moddata->opendate ?? 0;
        $this->closedate = $moddata->closedate ?? 0;
        $this->resume = $moddata->resume ?? 0;
        $this->navigate = $moddata->navigate ?? 0;
        $this->grade = $moddata->grade ?? 0;
        $this->sid = $moddata->sid ?? 0;
        $this->timemodified = $moddata->timemodified ?? 0;
        $this->completionsubmit = $moddata->completionsubmit ?? 0;
        $this->autonum = $moddata->autonum ?? 0;
        $this->progressbar = $moddata->progressbar ?? 0;
        $this->removeafter = $moddata->removeafter ?? 0;
    }
    /**
     * Implementation of add_instance.
     * @param stdClass $this
     * @return bool|int
     */
    public function add_instance() {
        // Given an object containing all the necessary data,
        // (defined by the form in mod.html) this function
        // will create a new instance and return the id number
        // of the new instance.
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

        $copyfiles = false;

        // Check the realm and set it to the survey if it's set.
        if (empty($this->sid)) {
            // Create a new survey.
            $course = get_course($this->course);
            $cm = new stdClass();
            $qobject = new questionnaire($course, $cm, 0, $this);

            if ($this->create == 'new-0') {
                $sdata = new stdClass();
                $sdata->name = $this->name;
                $sdata->realm = 'private';
                $sdata->title = $this->name;
                $sdata->subtitle = '';
                $sdata->info = '';
                $sdata->theme = ''; // Theme is deprecated.
                $sdata->thanks_page = '';
                $sdata->thank_head = '';
                $sdata->thank_body = '';
                $sdata->email = '';
                $sdata->feedbacknotes = '';
                $sdata->courseid = $course->id;
                if (!($sid = $qobject->survey_update($sdata))) {
                    throw new \moodle_exception('couldnotcreatenewsurvey', 'mod_questionnaire');
                }
            } else {
                $copyid = explode('-', $this->create);
                $copyrealm = $copyid[0];
                $copyid = $copyid[1];
                if (empty($qobject->survey)) {
                    $qobject->add_survey($copyid);
                    $qobject->add_questions($copyid);
                }
                // New questionnaires created as "use public" should not create a new survey instance.
                if ($copyrealm == 'public') {
                    $sid = $copyid;
                } else {
                    $sid = $qobject->sid = $qobject->survey_copy($course->id);
                    // All new questionnaires should be created as "private".
                    // Even if they are *copies* of public or template questionnaires.
                    $DB->set_field('questionnaire_survey', 'realm', 'private', ['id' => $sid]);

                    // Need to copy any files from the old questionnaire instance to the new one.
                    $this->copyid = $copyid;
                }
                // If the survey has dependency data, need to set the questionnaire to allow dependencies.
                if ($DB->count_records('questionnaire_dependency', ['surveyid' => $sid]) > 0) {
                    $this->navigate = 1;
                }
            }
            $this->sid = $sid;
        }

        $this->timemodified = time();

        if ($this->resume == '1') {
            $this->resume = 1;
        } else {
            $this->resume = 0;
        }

        if (!$this->id = $DB->insert_record("questionnaire", $this)) {
            return false;
        }

        questionnaire_set_events($this);

        $completiontimeexpected = !empty($this->completionexpected) ? $this->completionexpected : null;
        \core_completion\api::update_completion_date_event(
            $this->coursemodule,
            'questionnaire',
            $this->id,
            $completiontimeexpected
        );

        return $this->id;
    }
}
