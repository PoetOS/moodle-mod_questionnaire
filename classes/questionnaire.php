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

use mod_questionnaire\local\db\module_record;
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\question\question;
use context_module;
use stdClass;
use html_writer;

/**
 * The main class used to access and manage the questionnaire module.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class questionnaire {
    /** @var \mod_questionnaire\local\db\module_record The module record instance. */
    protected $modulerecord;

    /** @var \mod_questionnaire\local\db\survey_record The survey record instance. */
    protected $surveyrecord;

    /** @var \stdClass The course_modules record for this questionnaire. */
    protected $coursemodule;

    /** @var \context_module The context for this questionnaire. */
    protected $context;

    /** @var \stdClass The course record for this questionnaire. */
    protected $course;

    /** @var \mod_questionnaire\local\question\question[] The list of question objects. */
    protected $questions = [];

    // PROPERTIES TO BE REPLACED AND REFACTORED LATER.

    /** @var \plugin_renderer_base The module renderer, extended from core renderer. */
    public $renderer;

    /** @var \templatable The templatable page to render. */
    public $page;

    /**
     * Summary of __construct
     * @param int $mid
     * @param module_record|null $modulerecord
     * @param stdClass|null $coursemodule
     */
    public function __construct(int $mid = 0, ?module_record $modulerecord = null, ?stdClass $coursemodule = null) {
        if (!empty($mid)) {
            $this->modulerecord = new module_record($mid);
        } else if (!empty($modulerecord)) {
            $this->modulerecord = $modulerecord;
        } else {
            throw new \coding_exception('Either mid or modulerecord must be provided to construct a questionnaire object.');
        }

        try {
            $this->surveyrecord = new survey_record($this->modulerecord->get('sid'));
        } catch (\dml_missing_record_exception $e) {
            $this->surveyrecord = new survey_record();
        }
        $this->coursemodule = $coursemodule ??
            get_coursemodule_from_instance('questionnaire', $this->modulerecord->get('id'), 0, false, MUST_EXIST);
        $this->context = context_module::instance($this->coursemodule->id);
        $this->course = get_course($this->modulerecord->get('course'));
        $this->load_questions();
    }

    /**
     * Return a questionnaire instance from an instance id.
     * @param int $instanceid
     * @param stdClass|null $cm
     * @return self
     * @throws \dml_exception
     */
    public static function from_instanceid(int $instanceid, ?stdClass $cm = null) {
        return new self($instanceid, null, $cm);
    }

    /**
     * Return a questionnaire instance from a course module id.
     * @param int $cmid
     * @param stdClass|null $cm
     * @return self
     * @throws \dml_exception
     */
    public static function from_cmid(int $cmid, ?stdClass $cm = null) {
        return new self(0, module_record::from_cmid($cmid), $cm);
    }

    /**
     * Return a questionnaire instance from a course module object.
     * @param stdClass $cm
     * @return self
     * @throws \dml_exception
     */
    public static function from_cm(stdClass $cm) {
        return new self(0, new module_record($cm->instance), $cm);
    }

    /**
     * Load all questions for this questionnaire into the questions array.
     * @return void
     */
    protected function load_questions() {
        $sid = $this->surveyrecord->get('id');
        if (!empty($sid)) {
            $questionrecs = \mod_questionnaire\local\db\question_record::questions_for_survey($sid);
            foreach ($questionrecs as $questionrec) {
                $this->questions[$questionrec->get('id')] = question::question_builder($questionrec->get('typeid'), $questionrec);
            }
        }
    }

    /**
     * Get the id of the questionnaire.
     * @return int
     */
    public function id() {
        return $this->modulerecord->get('id');
    }

    /**
     * Get the name of the questionnaire.
     * @return string
     */
    public function name() {
        return $this->modulerecord->get('name');
    }

    /**
     * Get the course id of the questionnaire.
     * @return int
     */
    public function courseid() {
        return $this->modulerecord->get('course');
    }

    /**
     * Get the course record of the questionnaire.
     * @return stdClass
     */
    public function course() {
        return $this->course;
    }

    /**
     * Get the course module record of the questionnaire.
     * @return stdClass
     */
    public function coursemodule() {
        return $this->coursemodule;
    }

    /**
     * Get the course module record of the questionnaire.
     * @return stdClass
     */
    public function context() {
        return $this->context;
    }

    /**
     * Get the list of question objects for this questionnaire.
     * @return \mod_questionnaire\local\question\question[]
     */
    public function questions() {
        return $this->questions;
    }

    /**
     * True if the questionnaire is active.
     * @return bool
     */
    public function is_active() {
        return (!empty($this->modulerecord->get('sid')));
    }

    /**
     * True if the questionnaire is open.
     * @return bool
     */
    public function is_open() {
        return ($this->modulerecord->get('opendate') > 0) ? ($this->modulerecord->get('opendate') < time()) : true;
    }

    /**
     * True if the questionnaire is closed.
     * @return bool
     */
    public function is_closed() {
        return ($this->modulerecord->get('closedate') > 0) ? ($this->modulerecord->get('closedate') < time()) : false;
    }

    /**
     * Return true if the questionnaire is anonymous.
     *
     * @return boolean
     */
    public function is_anonymous(): bool {
        return $this->modulerecord->get('respondenttype') == 'anonymous';
    }

    /**
     * Return true if the survey is a 'public' one.
     *
     * @return boolean
     */
    public function survey_is_public() {
        return $this->surveyrecord->get('realm') == 'public';
    }

    /**
     * Return true if the survey is a 'public' one and this is the master instance.
     *
     * @return boolean
     */
    public function survey_is_public_master() {
        return $this->survey_is_public() &&
            ($this->modulerecord->get('course') == $this->surveyrecord->get('courseid'));
    }

    /**
     * True if the accessing course contains the actual questionnaire, as opposed to an instance of a public questionnaire.
     * @return bool
     */
    public function is_survey_owner() {
        return $this->courseid() == $this->surveyrecord->get('courseid');
    }

    /**
     * True if the specified user is eligible to complete this questionnaire.
     * @param int|null $userid
     * @return bool
     */
    public function user_is_eligible(?int $userid = null) {
        return has_capability('mod/questionnaire:view', $this->context, $userid) &&
            has_capability('mod/questionnaire:submit', $this->context, $userid);
    }

    /**
     * True if the specified user can read their own responses to this questionnaire.
     * @param int|null $userid
     * @return bool
     */
    public function can_read_own_responses(?int $userid = null) {
        return has_capability(
            'mod/questionnaire:readownresponses',
            $this->context,
            $userid
        );
    }

    /**
     * Summary of can_view_single_response
     * @param int|null $userid
     * @return bool
     */
    public function can_view_single_response(?int $userid = null) {
        return has_capability(
            'mod/questionnaire:viewsingleresponse',
            $this->context,
            $userid
        );
    }

    /**
     * True if the specified user can delete responses to this questionnaire.
     * @param int|null $userid
     * @return bool
     */
    public function can_delete_responses(?int $userid = null) {
        return has_capability(
            'mod/questionnaire:deleteresponses',
            $this->context,
            $userid
        );
    }

    /**
     * True if the specified user can download responses to this questionnaire.
     * @param int|null $userid
     * @return bool
     */
    public function can_download_responses(?int $userid = null) {
        return has_capability(
            'mod/questionnaire:downloadresponses',
            $this->context,
            $userid
        );
    }

    /**
     * Summary of can_manage_questionnaire
     * @param int|null $userid
     * @return bool
     */
    public function can_manage_questionnaire(?int $userid = null) {
        return has_capability(
            'mod/questionnaire:manage',
            $this->context,
            $userid
        );
    }

    /**
     * True if the specified user can edit questions in this questionnaire.
     * @param int|null $userid
     * @return bool
     */
    public function can_edit_questions(?int $userid = null) {
        return has_capability(
            'mod/questionnaire:editquestions',
            $this->context,
            $userid
        );
    }

    /**
     * True if the specified user can complete this questionnaire.
     * @param int $userid
     * @return bool
     */
    public function user_can_take($userid) {

        if (!$this->is_active() || !$this->user_is_eligible($userid)) {
            return false;
        } else if ($this->modulerecord->get('qtype') == QUESTIONNAIREUNLIMITED) {
            return true;
        } else if ($userid > 0) {
            return $this->user_time_for_new_attempt($userid);
        } else {
            return false;
        }
    }

    /**
     * True if the specified user can complete this questionnaire at this time.
     * @param int $userid
     * @return bool
     */
    public function user_time_for_new_attempt($userid) {
        global $DB;

        // TODO: Refactor to use response classes.
        $params = ['questionnaireid' => $this->id, 'userid' => $userid, 'complete' => 'y'];
        if (!($attempts = $DB->get_records('questionnaire_response', $params, 'submitted DESC'))) {
            return true;
        }

        $attempt = reset($attempts);
        $timenow = time();

        switch ($this->qtype) {
            case QUESTIONNAIREUNLIMITED:
                $cantake = true;
                break;

            case QUESTIONNAIREONCE:
                $cantake = false;
                break;

            case QUESTIONNAIREDAILY:
                $attemptyear = date('Y', $attempt->submitted);
                $currentyear = date('Y', $timenow);
                $attemptdayofyear = date('z', $attempt->submitted);
                $currentdayofyear = date('z', $timenow);
                $cantake = (($attemptyear < $currentyear) ||
                    (($attemptyear == $currentyear) && ($attemptdayofyear < $currentdayofyear)));
                break;

            case QUESTIONNAIREWEEKLY:
                $attemptyear = date('Y', $attempt->submitted);
                $currentyear = date('Y', $timenow);
                $attemptweekofyear = date('W', $attempt->submitted);
                $currentweekofyear = date('W', $timenow);
                $cantake = (($attemptyear < $currentyear) ||
                    (($attemptyear == $currentyear) && ($attemptweekofyear < $currentweekofyear)));
                break;

            case QUESTIONNAIREMONTHLY:
                $attemptyear = date('Y', $attempt->submitted);
                $currentyear = date('Y', $timenow);
                $attemptmonthofyear = date('n', $attempt->submitted);
                $currentmonthofyear = date('n', $timenow);
                $cantake = (($attemptyear < $currentyear) ||
                    (($attemptyear == $currentyear) && ($attemptmonthofyear < $currentmonthofyear)));
                break;

            default:
                $cantake = false;
                break;
        }

        return $cantake;
    }

    /**
     * True if the specified user has a saved response for this questionnaire.
     * TODO: Refactor to use response classes.
     * @param int $userid
     * @return bool
     */
    public function user_has_saved_response($userid) {
        global $DB;

        return $DB->record_exists(
            'questionnaire_response',
            ['questionnaireid' => $this->modulerecord->get('id'), 'userid' => $userid, 'complete' => 'n']
        );
    }

    /**
     * Return the number of submissions for this questionnaire.
     * TODO: Refactor to use response classes.
     * @param bool $userid
     * @param int $groupid
     * @return int
     */
    public function count_submissions($userid = false, $groupid = 0) {
        global $DB;

        $params = [];
        $groupsql = '';
        $groupcnd = '';
        if ($groupid != 0) {
            $groupsql = 'INNER JOIN {groups_members} gm ON r.userid = gm.userid ';
            $groupcnd = ' AND gm.groupid = :groupid ';
            $params['groupid'] = $groupid;
        }

        // Since submission can be across questionnaires in the case of public questionnaires, need to check the realm.
        // Public questionnaires can have responses to multiple questionnaire instances.
        if ($this->survey_is_public_master()) {
            $sql = 'SELECT COUNT(r.id) ' .
                'FROM {questionnaire_response} r ' .
                'INNER JOIN {questionnaire} q ON r.questionnaireid = q.id ' .
                'INNER JOIN {questionnaire_survey} s ON q.sid = s.id ' .
                $groupsql .
                'WHERE s.id = :surveyid AND r.complete = :status' . $groupcnd;
            $params['surveyid'] = $this->surveyrecord->get('id');
            $params['status'] = 'y';
        } else {
            $sql = 'SELECT COUNT(r.id) ' .
                'FROM {questionnaire_response} r ' .
                $groupsql .
                'WHERE r.questionnaireid = :questionnaireid AND r.complete = :status' . $groupcnd;
            $params['questionnaireid'] = $this->modulerecord->get('id');
            $params['status'] = 'y';
        }
        if ($userid) {
            $sql .= ' AND r.userid = :userid';
            $params['userid'] = $userid;
        }
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * True if the user can view the responses to this questionnaire, and there are valid responses.
     *
     * @param null|int $usernumresp
     * @param bool $isviewreport
     * @return bool
     */
    public function can_view_all_responses($usernumresp = null, $isviewreport = false) {
        global $USER, $SESSION;

        $owner = $this->is_survey_owner();
        $numresp = $this->count_submissions();
        if ($usernumresp === null) {
            $usernumresp = $this->count_submissions($USER->id);
        }

        // Number of Responses in currently selected group (or all participants etc.).
        if (isset($SESSION->questionnaire->numselectedresps)) {
            $numselectedresps = $SESSION->questionnaire->numselectedresps;
        } else {
            $numselectedresps = $numresp;
        }

        // If questionnaire is set to separate groups, prevent user who is not member of any group
        // to view All responses.
        $canviewgroups = true;
        $canviewallgroups = has_capability('moodle/site:accessallgroups', context: $this->context);
        $groupmode = groups_get_activity_groupmode($this->coursemodule, $this->modulerecord->get('course'));
        if ($groupmode == 1) {
            $canviewgroups = groups_has_membership($this->coursemodule, $USER->id);
        }

        $grouplogic = $canviewgroups || $canviewallgroups;
        $respslogic = ($numresp > 0) && ($numselectedresps > 0) || $isviewreport;
        return $this->can_view_all_responses_anytime($grouplogic, $respslogic) ||
            $this->can_view_all_responses_with_restrictions($usernumresp, $grouplogic, $respslogic);
    }

    /**
     * True if the user can view all of the responses to this questionnaire any time, and there are valid responses.
     * @param bool $grouplogic
     * @param bool $respslogic
     * @return bool
     */
    public function can_view_all_responses_anytime($grouplogic = true, $respslogic = true) {
        // Can view if you are a valid group user, this is the owning course, and there are responses, and you have no
        // response view restrictions.
        has_capability('mod/questionnaire:readallresponseanytime', $this->context);
        return $grouplogic &&
            $respslogic &&
            $this->is_survey_owner() &&
            has_capability(
                'mod/questionnaire:readallresponseanytime',
                $this->context
            );
    }

    /**
     * True if the user can view all of the responses to this questionnaire any time, and there are valid responses.
     * @param null|int $usernumresp
     * @param bool $grouplogic
     * @param bool $respslogic
     * @return bool
     */
    public function can_view_all_responses_with_restrictions($usernumresp, $grouplogic = true, $respslogic = true) {
        // Can view if you are a valid group user, this is the owning course, and there are responses, and you can view
        // subject to viewing settings..
        return $grouplogic && $respslogic && $this->is_survey_owner() &&
            (has_capability('moodle/site:readallresponses', context: $this->context) &&
                ($this->modulerecord->get('respview') == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                    ($this->modulerecord->get('respview') == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $this->is_closed()) ||
                    ($this->modulerecord->get('respview') == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED && $usernumresp)));
    }

    // METHODS TO BE POSSIBLY REPLACED AND REFACTORED LATER.

    /**
     * Add the renderer to the questionnaire object.
     * @param plugin_renderer_base $renderer The module renderer, extended from core renderer.
     */
    public function add_renderer(\plugin_renderer_base $renderer) {
        $this->renderer = $renderer;
    }

    /**
     * Add the templatable page to the questionnaire object.
     * @param templatable $page The page to render, implementing core classes.
     */
    public function add_page($page) {
        $this->page = $page;
    }

    /**
     * Return any message if the user cannot complete this questionnaire, explaining why.
     * @param int $userid
     * @param bool $asnotification Return as a rendered notification.
     * @return bool|string
     */
    public function user_access_messages($userid = 0, $asnotification = false) {
        global $USER;

        if ($userid == 0) {
            $userid = $USER->id;
        }
        $message = false;

        if (!$this->is_active()) {
            if ($this->can_manage_questionnaire()) {
                $msg = 'removenotinuse';
            } else {
                $msg = 'notavail';
            }
            $message = get_string($msg, 'questionnaire');
        } else if ($this->surveyrecord->get('realm') == 'template') {
            $message = get_string('templatenotviewable', 'questionnaire');
        } else if (!$this->is_open()) {
            $message = get_string('notopen', 'questionnaire', userdate($this->modulerecord->get('opendate')));
        } else if ($this->is_closed()) {
            $message = get_string('closed', 'questionnaire', userdate($this->modulerecord->get('closedate')));
        } else if (!$this->user_is_eligible($userid)) {
            $message = get_string('noteligible', 'questionnaire');
        } else if (!$this->user_can_take($userid)) {
            switch ($this->qtype) {
                case QUESTIONNAIREDAILY:
                    $msgstring = ' ' . get_string('today', 'questionnaire');
                    break;
                case QUESTIONNAIREWEEKLY:
                    $msgstring = ' ' . get_string('thisweek', 'questionnaire');
                    break;
                case QUESTIONNAIREMONTHLY:
                    $msgstring = ' ' . get_string('thismonth', 'questionnaire');
                    break;
                default:
                    $msgstring = '';
                    break;
            }
            $message = get_string("alreadyfilled", "questionnaire", $msgstring);
        }

        if (($message !== false) && $asnotification) {
            $message = $this->renderer->notification($message, \core\output\notification::NOTIFY_ERROR);
        }

        return $message;
    }

    /**
     * Output the questionnaire information.
     *
     * @return string
     */
    public function view_information() {
        $messages = [];

        if (isset($this->qtype)) {
            switch ($this->qtype) {
                case QUESTIONNAIREUNLIMITED:
                    $typestring = get_string('unlimited', 'questionnaire');
                    break;
                case QUESTIONNAIREONCE:
                    $typestring = get_string('once', 'questionnaire');
                    break;
                case QUESTIONNAIREDAILY:
                    $typestring = get_string('daily', 'questionnaire');
                    break;
                case QUESTIONNAIREWEEKLY:
                    $typestring = get_string('weekly', 'questionnaire');
                    break;
                case QUESTIONNAIREMONTHLY:
                    $typestring = get_string('monthly', 'questionnaire');
                    break;
                default:
                    $typestring = '';
                    break;
            }
            array_push($messages, get_string('attemptsallowed', 'questionnaire', $typestring));
        }

        if ($this->is_open() && !$this->is_closed()) {
            if ($this->modulerecord->get('opendate') > 0) {
                array_push($messages, get_string('openedat', 'questionnaire', userdate($this->modulerecord->get('opendate'))));
            }
            if ($this->modulerecord->get('closedate') > 0) {
                array_push($messages, get_string('closesat', 'questionnaire', userdate($this->modulerecord->get('closedate'))));
            }
        }

        return $messages;
    }

    /**
     * Print each message in an array, surrounded by &lt;p>, &lt;/p> tags.
     *
     * @param array $messages the array of message strings.
     * @return string HTML to output.
     */
    public function access_messages($messages) {
        $output = '';
        foreach ($messages as $message) {
            $output .= html_writer::tag('p', $message) . "\n";
        }
        return $output;
    }

    /**
     * The main module view function.
     */
    public function view() {
        global $CFG, $USER, $PAGE;

        $PAGE->set_title(format_string($this->name));
        $PAGE->set_heading(format_string($this->course->fullname));
        $message = $this->user_access_messages($USER->id, true);
        if ($message !== false) {
            $this->page->add_to_page('notifications', $message);
        } else {
            // Handle the main questionnaire completion page.
            $quser = $USER->id;

            $msg = $this->print_survey($quser, $USER->id);

            // If Questionnaire was submitted with all required fields completed ($msg is empty),
            // then record the submittal.
            $viewform = data_submitted($CFG->wwwroot . "/mod/questionnaire/complete.php");
            if (
                $viewform && confirm_sesskey() && isset($viewform->submit) && isset($viewform->submittype) &&
                ($viewform->submittype == "Submit Survey") && empty($msg)
            ) {
                if (!empty($viewform->rid)) {
                    $viewform->rid = (int)$viewform->rid;
                }
                if (!empty($viewform->sec)) {
                    $viewform->sec = (int)$viewform->sec;
                }
                $this->response_delete($viewform->rid, $viewform->sec);
                $this->rid = $this->response_insert($viewform, $quser);
                $this->response_commit($this->rid);

                $this->update_grades($quser);

                // Update completion state.
                $completion = new \completion_info($this->course);
                if ($completion->is_enabled($this->cm) && $this->completionsubmit) {
                    $completion->update_state($this->cm, COMPLETION_COMPLETE);
                }

                // Log this submitted response. Note this removes the anonymity in the logged event.
                $context = context_module::instance($this->cm->id);
                $anonymous = $this->respondenttype == 'anonymous';
                $params = [
                    'context' => $context,
                    'courseid' => $this->course->id,
                    'relateduserid' => $USER->id,
                    'anonymous' => $anonymous,
                    'other' => ['questionnaireid' => $this->id],
                ];
                $event = \mod_questionnaire\event\attempt_submitted::create($params);
                $event->trigger();

                $this->submission_notify($this->rid);
                $this->response_goto_thankyou();
            }
        }
    }
}
