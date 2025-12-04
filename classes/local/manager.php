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

namespace mod_questionnaire\local;

use cm_info;
use context_module;
use mod_questionnaire\local\db\module_record;
use stdClass;

/**
 * Class manager for questionnaire activity
 *
 * @package    mod_questionnaire
 * @copyright  2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** Module name. */
    public const MODULE = 'questionnaire';

    /** @var context_module the current context. */
    private $context;

    /** @var stdClass $course record. */
    private $course;

    /** @var \moodle_database the database instance. */
    private \moodle_database $db;

    /**
     * Class constructor.
     *
     * @param cm_info $cm course module info object
     * @param stdClass $instance activity instance object.
     */
    public function __construct(
        /** @var cm_info $cm the given course module info */
        private cm_info $cm,
        /** @var stdClass $instance activity instance object */
        private stdClass $instance
    ) {
        $this->context = context_module::instance($cm->id);
        $this->db = \core\di::get(\moodle_database::class);
        $this->course = $cm->get_course();
    }

    /**
     * Create a manager instance from an instance record.
     *
     * @param stdClass $instance an activity record
     * @return manager
     */
    public static function create_from_instance(stdClass $instance): self {
        $cm = get_coursemodule_from_instance(self::MODULE, $instance->id);
        // Ensure that $this->cm is a cm_info object.
        $cm = cm_info::create($cm);
        return new self($cm, $instance);
    }

    /**
     * Create a manager instance from a course_modules record.
     *
     * @param stdClass|cm_info $cm an activity record
     * @return manager
     */
    public static function create_from_coursemodule(stdClass|cm_info $cm): self {
        // Ensure that $this->cm is a cm_info object.
        $cm = cm_info::create($cm);
        $db = \core\di::get(\moodle_database::class);
        $instance = $db->get_record(self::MODULE, ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($cm, $instance);
    }

    /**
     * Return the current context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Return the current instance.
     *
     * @return stdClass the instance record
     */
    public function get_instance(): stdClass {
        return $this->instance;
    }

    /**
     * Return the current cm_info.
     *
     * @return cm_info the course module
     */
    public function get_coursemodule(): cm_info {
        return $this->cm;
    }

    /**
     * Return the current count of users who have answered this questionnaire module, that the current user can see.
     *
     * @param int[] $groupids the group identifiers to filter by, empty array means no filtering
     * @param int|null $optionid the option ID to filter by, or null to count all answers
     * @return int the number of answers that the user can see
     */
    public function count_all_users_answered(
        array $groupids = [],
        ?int $optionid = null,
    ): int {
        if (!has_capability('mod/questionnaire:viewsingleresponse', $this->context)) {
            return 0;
        }

        $tableprefix = empty($groupids) ? '' : 'qr.';
        $select = $tableprefix . 'questionnaireid = :questionnaireid';
        $params = [
            'questionnaireid' => $this->instance->id,
        ];
        if ($optionid) {
            $select .= ' AND ' . $tableprefix . 'optionid = :optionid ';
            $params['optionid'] = $optionid;
        }

        if (empty($groupids)) {
            // No groups filtering, count all users answered.
            return $this->db->count_records_select('questionnaire_response', $select, $params, 'COUNT(DISTINCT userid)');
        }

        // Groups filtering is applied.
        [$gsql, $gparams] = $this->db->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
        $query = "SELECT COUNT(DISTINCT qr.userid)
                FROM {questionnaire_response} qr, {groups_members} gm
               WHERE $select
                     AND (gm.groupid $gsql OR gm.groupid = 0)
                     AND qr.userid = gm.userid";
        return $this->db->count_records_sql($query, $params + $gparams);
    }

    /**
     * Check if the current user has answered the questionnaire.
     *
     * Note: this will count all answers, regardless of grouping.
     *
     * @return bool true if the user has answered, false otherwise
     */
    public function has_answered(): bool {
        global $USER;
        $conditions = ['questionnaireid' => $this->instance->id, 'userid' => $USER->id];
        return $this->db->record_exists('questionnaire_response', $conditions);
    }

    /**
     * Get the options for this questionnaire activity.
     *
     * @return array of questionnaire options
     */
    public function get_options(): array {
        return $this->db->get_records(
            'questionnaire_options',
            ['questionnaireid' => $this->instance->id],
            'id ASC',
        );
    }

    /**
     * Given an object containing all the necessary data, (defined by the form in mod.html) this function will create and return
     * the new instance id.
     * @param stdClass $formdata
     * @throws \moodle_exception
     * @return int The id of the newly created instance.
     */
    public static function create_module_instance(stdClass $formdata): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

        $copyfiles = false;

        // Check the realm and set it to the survey if it's set.
        if (empty($formdata->sid)) {
            // Create a new survey.
            $course = get_course($formdata->course);
            $cm = new stdClass();
            $qobject = new questionnaire($course, $cm, 0, $formdata);

            if ($formdata->create == 'new-0') {
                $sdata = new stdClass();
                $sdata->name = $formdata->name;
                $sdata->realm = 'private';
                $sdata->title = $formdata->name;
                $sdata->subtitle = '';
                $sdata->info = '';
                $sdata->theme = ''; // Theme is deprecated.
                $sdata->thankspage = '';
                $sdata->thankhead = '';
                $sdata->thankbody = '';
                $sdata->email = '';
                $sdata->feedbacknotes = '';
                $sdata->courseid = $course->id;
                if (!($sid = $qobject->survey_update($sdata))) {
                    throw new \moodle_exception('couldnotcreatenewsurvey', 'mod_questionnaire');
                }
            } else {
                $copyid = explode('-', $formdata->create);
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
                    $formdata->copyid = $copyid;
                }
                // If the survey has dependency data, need to set the questionnaire to allow dependencies.
                if ($DB->count_records('questionnaire_dependency', ['surveyid' => $sid]) > 0) {
                    $formdata->navigate = 1;
                }
            }
            $formdata->sid = $sid;
        }

        $formdata->timemodified = time();

        if ($formdata->resume == '1') {
            $formdata->resume = 1;
        } else {
            $formdata->resume = 0;
        }

        $instanceid = (module_record::create_from_formdata($formdata))->get('id');
        $formdata->id = $instanceid;

        questionnaire_set_events($formdata);

        $completiontimeexpected = !empty($formdata->completionexpected) ? $formdata->completionexpected : null;
        \core_completion\api::update_completion_date_event(
            $formdata->coursemodule,
            'questionnaire',
            $instanceid,
            $completiontimeexpected
        );

        return $instanceid;
    }
}
