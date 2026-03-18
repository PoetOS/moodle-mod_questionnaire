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

use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\db\question_record;
use mod_questionnaire\local\question\question;
use context_module;
use stdClass;

/**
 * The main class used to access and manage the questionnaire module.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class questionnaire {
    /** @var questionnaire_record The module record instance. */
    protected questionnaire_record $modulerecord;

    /** @var survey_record The survey record instance. */
    protected survey_record $surveyrecord;

    /** @var stdClass The course_modules record for this questionnaire. */
    protected stdClass $coursemodule;

    /** @var context_module The context for this questionnaire. */
    protected context_module $context;

    /** @var stdClass The course record for this questionnaire. */
    protected stdClass $course;

    /** @var question[] The list of question objects indexed by question id. */
    protected array $questions = [];

    /** @var question[][] Question objects organised by section number (1-based). */
    protected array $questionsbysec = [];

    // PROPERTIES TO BE REPLACED AND REFACTORED LATER.

    /** @var \plugin_renderer_base The module renderer, extended from core renderer. */
    public $renderer;

    /** @var \templatable The templatable page to render. */
    public $page;

    /**
     * Construct from a questionnaire instance id, optionally with a pre-loaded persistent and cm.
     *
     * @param int $mid Instance id (questionnaire.id).
     * @param questionnaire_record|null $modulerecord Pre-loaded persistent — omit to load from DB.
     * @param stdClass|null $coursemodule Pre-loaded course_modules row — omit to look up.
     */
    public function __construct(int $mid = 0, ?questionnaire_record $modulerecord = null, ?stdClass $coursemodule = null) {
        if (!empty($mid)) {
            $this->modulerecord = new questionnaire_record($mid);
        } else if (!empty($modulerecord)) {
            $this->modulerecord = $modulerecord;
        } else {
            throw new \coding_exception('Either mid or modulerecord must be provided to construct a questionnaire object.');
        }

        $sid = $this->modulerecord->get('sid');
        if (!empty($sid)) {
            $this->surveyrecord = new survey_record($sid);
        } else {
            $this->surveyrecord = new survey_record();
        }

        $this->coursemodule = $coursemodule ??
            get_coursemodule_from_instance('questionnaire', $this->modulerecord->get('id'), 0, false, MUST_EXIST);
        $this->context = context_module::instance($this->coursemodule->id);
        $this->course = get_course($this->modulerecord->get('course'));
        $this->load_questions();
    }

    /**
     * Return a questionnaire instance from an activity instance id.
     *
     * @param int $instanceid questionnaire.id
     * @param stdClass|null $cm Optional pre-loaded course_modules row.
     * @return self
     */
    public static function from_instanceid(int $instanceid, ?stdClass $cm = null): self {
        return new self($instanceid, null, $cm);
    }

    /**
     * Return a questionnaire instance from a course module id.
     *
     * @param int $cmid course_modules.id
     * @param stdClass|null $cm Optional pre-loaded course_modules row.
     * @return self
     */
    public static function from_cmid(int $cmid, ?stdClass $cm = null): self {
        $cm = $cm ?? get_coursemodule_from_id('questionnaire', $cmid, 0, false, MUST_EXIST);
        return new self($cm->instance, null, $cm);
    }

    /**
     * Return a questionnaire instance from a course_modules object.
     *
     * @param stdClass $cm course_modules row.
     * @return self
     */
    public static function from_cm(stdClass $cm): self {
        return new self($cm->instance, null, $cm);
    }

    /**
     * Load all active questions for this questionnaire, grouped by section.
     *
     * Questions returned by get_active_for_survey() are question_record persistents.
     * Convert each to a plain stdClass before passing to the question class constructor.
     *
     * @return void
     */
    protected function load_questions(): void {
        $sid = $this->surveyrecord->get('id');
        if (empty($sid)) {
            return;
        }

        $questionrecs = question_record::get_active_for_survey($sid);
        $sec = 1;
        $isbreak = false;

        foreach ($questionrecs as $questionrec) {
            $rec = $questionrec->to_record();

            $typeid = $questionrec->get('typeid');
            $this->questions[$questionrec->get('id')] = question::question_builder($typeid, $rec);

            if ($typeid != QUESPAGEBREAK) {
                // PHP assigns objects by reference, so this adds the same object to questionsbysec.
                $this->questionsbysec[$sec][] = $this->questions[$questionrec->get('id')];
                $isbreak = false;
            } else {
                // No section break as first position, no two consecutive breaks.
                if (($questionrec->get('position') != 1) && ($isbreak == false)) {
                    $sec++;
                    $isbreak = true;
                }
            }
        }
    }

    /**
     * Get the id of the questionnaire instance.
     *
     * @return int
     */
    public function id(): int {
        return $this->modulerecord->get('id');
    }

    /**
     * Get the name of the questionnaire.
     *
     * @return string
     */
    public function name(): string {
        return $this->modulerecord->get('name');
    }

    /**
     * Get the course id of the questionnaire.
     *
     * @return int
     */
    public function courseid(): int {
        return $this->modulerecord->get('course');
    }

    /**
     * Get the course record of the questionnaire.
     *
     * @return stdClass
     */
    public function course(): stdClass {
        return $this->course;
    }

    /**
     * Whether the questionnaire should show a progress bar.
     *
     * @return bool
     */
    public function use_progressbar(): bool {
        return $this->modulerecord->get('progressbar') == 1;
    }

    /**
     * Get the course module record.
     *
     * @return stdClass
     */
    public function coursemodule(): stdClass {
        return $this->coursemodule;
    }

    /**
     * Get the context for this questionnaire.
     *
     * @return context_module
     */
    public function context(): context_module {
        return $this->context;
    }

    /**
     * Get the survey id linked to this questionnaire.
     *
     * @return int
     */
    public function surveyid(): int {
        return $this->surveyrecord->get('id');
    }

    /**
     * Get the survey title.
     *
     * @return string
     */
    public function surveytitle(): string {
        return $this->surveyrecord->get('title') ?? '';
    }

    /**
     * Get the survey subtitle.
     *
     * @return string
     */
    public function surveysubtitle(): string {
        return $this->surveyrecord->get('subtitle') ?? '';
    }

    /**
     * Get the survey info/description.
     *
     * @return string
     */
    public function surveyinfo(): string {
        return $this->surveyrecord->get('info') ?? '';
    }

    /**
     * Get all question objects for this questionnaire.
     *
     * @return question[]
     */
    public function questions(): array {
        return $this->questions;
    }

    /**
     * Get question objects for a specific section.
     *
     * @param int $section 1-based section number.
     * @return question[]
     */
    public function questions_by_section(int $section = 1): array {
        return $this->questionsbysec[$section] ?? [];
    }

    /**
     * True if the questionnaire has an associated survey (is active).
     *
     * @return bool
     */
    public function is_active(): bool {
        return !empty($this->modulerecord->get('sid'));
    }

    /**
     * True if the questionnaire is currently open (past the open date, or no open date set).
     *
     * @return bool
     */
    public function is_open(): bool {
        $opendate = $this->modulerecord->get('opendate');
        return ($opendate > 0) ? ($opendate < time()) : true;
    }

    /**
     * True if the questionnaire is closed (past the close date).
     *
     * @return bool
     */
    public function is_closed(): bool {
        $closedate = $this->modulerecord->get('closedate');
        return ($closedate > 0) ? ($closedate < time()) : false;
    }

    /**
     * True if the questionnaire collects anonymous responses.
     *
     * @return bool
     */
    public function is_anonymous(): bool {
        return $this->modulerecord->get('respondenttype') == 'anonymous';
    }

    /**
     * True if the survey is marked as public (shareable across courses).
     *
     * @return bool
     */
    public function survey_is_public(): bool {
        return $this->surveyrecord->get('realm') == 'public';
    }

    /**
     * True if the survey is a template.
     *
     * @return bool
     */
    public function survey_is_template(): bool {
        return $this->surveyrecord->get('realm') == 'template';
    }

    /**
     * True if the survey is public and this questionnaire is in the owning course.
     *
     * @return bool
     */
    public function survey_is_public_master(): bool {
        return $this->survey_is_public() &&
            ($this->modulerecord->get('course') == $this->surveyrecord->get('courseid'));
    }

    /**
     * True if the current course owns this survey (as opposed to using a shared public survey).
     *
     * @return bool
     */
    public function is_survey_owner(): bool {
        return $this->courseid() == $this->surveyrecord->get('courseid');
    }

    /**
     * True if the specified user is eligible to view and submit this questionnaire.
     *
     * @param int|null $userid Defaults to current user.
     * @return bool
     */
    public function user_is_eligible(?int $userid = null): bool {
        return has_capability('mod/questionnaire:view', $this->context, $userid) &&
            has_capability('mod/questionnaire:submit', $this->context, $userid);
    }

    /**
     * True if the specified user can read their own responses.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_read_own_responses(?int $userid = null): bool {
        return has_capability('mod/questionnaire:readownresponses', $this->context, $userid);
    }

    /**
     * True if the specified user can view a single response.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_view_single_response(?int $userid = null): bool {
        return has_capability('mod/questionnaire:viewsingleresponse', $this->context, $userid);
    }

    /**
     * True if the specified user can delete responses.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_delete_responses(?int $userid = null): bool {
        return has_capability('mod/questionnaire:deleteresponses', $this->context, $userid);
    }

    /**
     * True if the specified user can download responses.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_download_responses(?int $userid = null): bool {
        return has_capability('mod/questionnaire:downloadresponses', $this->context, $userid);
    }

    /**
     * True if the specified user can manage this questionnaire.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_manage_questionnaire(?int $userid = null): bool {
        return has_capability('mod/questionnaire:manage', $this->context, $userid);
    }

    /**
     * True if the specified user can edit questions in this questionnaire.
     *
     * @param int|null $userid
     * @return bool
     */
    public function can_edit_questions(?int $userid = null): bool {
        return has_capability('mod/questionnaire:editquestions', $this->context, $userid);
    }

    /**
     * True if the specified user is allowed to take this questionnaire right now.
     *
     * @param int $userid
     * @return bool
     */
    public function user_can_take(int $userid): bool {
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
     * True if the timing rules allow the user to start a new attempt.
     *
     * @param int $userid
     * @return bool
     */
    public function user_time_for_new_attempt(int $userid): bool {
        global $DB;

        $params = ['questionnaireid' => $this->id(), 'userid' => $userid, 'complete' => 'y'];
        if (!($attempts = $DB->get_records('questionnaire_response', $params, 'submitted DESC'))) {
            return true;
        }

        $attempt = reset($attempts);
        $timenow = time();

        switch ($this->modulerecord->get('qtype')) {
            case QUESTIONNAIREUNLIMITED:
                return true;

            case QUESTIONNAIREONCE:
                return false;

            case QUESTIONNAIREDAILY:
                return (date('Y', $attempt->submitted) < date('Y', $timenow)) ||
                    (date('Yz', $attempt->submitted) < date('Yz', $timenow));

            case QUESTIONNAIREWEEKLY:
                return (date('Y', $attempt->submitted) < date('Y', $timenow)) ||
                    (date('YW', $attempt->submitted) < date('YW', $timenow));

            case QUESTIONNAIREMONTHLY:
                return (date('Y', $attempt->submitted) < date('Y', $timenow)) ||
                    (date('Yn', $attempt->submitted) < date('Yn', $timenow));

            default:
                return false;
        }
    }

    /**
     * True if the user has an in-progress (incomplete) saved response.
     *
     * @param int $userid
     * @return bool
     */
    public function user_has_saved_response(int $userid): bool {
        global $DB;
        return $DB->record_exists(
            'questionnaire_response',
            ['questionnaireid' => $this->id(), 'userid' => $userid, 'complete' => 'n']
        );
    }

    /**
     * Count the number of complete submissions for this questionnaire.
     *
     * @param int|false $userid Restrict to a specific user, or false for all users.
     * @param int $groupid Restrict to a specific group, or 0 for all groups.
     * @return int
     */
    public function count_submissions($userid = false, int $groupid = 0): int {
        global $DB;

        $params = [];
        $groupsql = '';
        $groupcnd = '';
        if ($groupid != 0) {
            $groupsql = 'INNER JOIN {groups_members} gm ON r.userid = gm.userid ';
            $groupcnd = ' AND gm.groupid = :groupid ';
            $params['groupid'] = $groupid;
        }

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
            $params['questionnaireid'] = $this->id();
            $params['status'] = 'y';
        }

        if ($userid) {
            $sql .= ' AND r.userid = :userid';
            $params['userid'] = $userid;
        }

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * True if the current user can view all responses (checking group membership and response counts).
     *
     * @param int|null $usernumresp Number of responses the user has made; null to calculate.
     * @param bool $isviewreport Whether the context is a view-report page.
     * @return bool
     */
    public function can_view_all_responses(?int $usernumresp = null, bool $isviewreport = false): bool {
        global $USER, $SESSION;

        $numresp = $this->count_submissions();
        if ($usernumresp === null) {
            $usernumresp = $this->count_submissions($USER->id);
        }

        $numselectedresps = $SESSION->questionnaire->numselectedresps ?? $numresp;

        $canviewallgroups = has_capability('moodle/site:accessallgroups', $this->context);
        $groupmode = groups_get_activity_groupmode($this->coursemodule, $this->modulerecord->get('course'));
        $canviewgroups = ($groupmode == 1)
            ? groups_has_membership($this->coursemodule, $USER->id)
            : true;

        $grouplogic = $canviewgroups || $canviewallgroups;
        $respslogic = ($numresp > 0 && $numselectedresps > 0) || $isviewreport;

        return $this->can_view_all_responses_anytime($grouplogic, $respslogic) ||
            $this->can_view_all_responses_with_restrictions($usernumresp, $grouplogic, $respslogic);
    }

    /**
     * True if the user can view all responses at any time (no submission requirement).
     *
     * @param bool $grouplogic
     * @param bool $respslogic
     * @return bool
     */
    public function can_view_all_responses_anytime(bool $grouplogic = true, bool $respslogic = true): bool {
        return $grouplogic && $respslogic && $this->is_survey_owner() &&
            has_capability('mod/questionnaire:readallresponseanytime', $this->context);
    }

    /**
     * True if the user can view all responses subject to the questionnaire's view restrictions.
     *
     * @param int|null $usernumresp
     * @param bool $grouplogic
     * @param bool $respslogic
     * @return bool
     */
    public function can_view_all_responses_with_restrictions(
        ?int $usernumresp,
        bool $grouplogic = true,
        bool $respslogic = true
    ): bool {
        $respview = $this->modulerecord->get('respview');
        return $grouplogic && $respslogic && $this->is_survey_owner() &&
            has_capability('moodle/site:readallresponses', $this->context) &&
            ($respview == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                ($respview == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $this->is_closed()) ||
                ($respview == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED && $usernumresp));
    }

    /**
     * Return information strings about how the questionnaire can be attempted.
     *
     * @return string[]
     */
    public function view_information(): array {
        $messages = [];

        switch ($this->modulerecord->get('qtype')) {
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
        $messages[] = get_string('attemptsallowed', 'questionnaire', $typestring);

        if ($this->is_open() && !$this->is_closed()) {
            if ($this->modulerecord->get('opendate') > 0) {
                $messages[] = get_string('openedat', 'questionnaire', userdate($this->modulerecord->get('opendate')));
            }
            if ($this->modulerecord->get('closedate') > 0) {
                $messages[] = get_string('closesat', 'questionnaire', userdate($this->modulerecord->get('closedate')));
            }
        }

        return $messages;
    }

    /**
     * Return a human-readable message explaining why the user cannot access this questionnaire,
     * or null if access is permitted.
     *
     * @param int $userid 0 for current user.
     * @param bool $asnotification Unused — retained for API compatibility.
     * @return string|null
     */
    public function user_access_messages(int $userid = 0, bool $asnotification = false): ?string {
        global $USER;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        if (!$this->is_active()) {
            $msg = $this->can_manage_questionnaire() ? 'removenotinuse' : 'notavail';
            return get_string($msg, 'questionnaire');
        }
        if ($this->survey_is_template()) {
            return get_string('templatenotviewable', 'questionnaire');
        }
        if (!$this->is_open()) {
            return get_string('notopen', 'questionnaire', userdate($this->modulerecord->get('opendate')));
        }
        if ($this->is_closed()) {
            return get_string('closed', 'questionnaire', userdate($this->modulerecord->get('closedate')));
        }
        if (!$this->user_is_eligible($userid)) {
            return get_string('noteligible', 'questionnaire');
        }
        if (!$this->user_can_take($userid)) {
            switch ($this->modulerecord->get('qtype')) {
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
            return get_string('alreadyfilled', 'questionnaire', $msgstring);
        }

        return null;
    }

    /**
     * Add the renderer to the questionnaire object.
     *
     * @param \plugin_renderer_base $renderer
     */
    public function add_renderer(\plugin_renderer_base $renderer): void {
        $this->renderer = $renderer;
    }

    /**
     * Add the templatable page to the questionnaire object.
     *
     * @param \templatable $page
     */
    public function add_page($page): void {
        $this->page = $page;
    }

    /**
     * Create or update a questionnaire survey record.
     *
     * If $sid is 0 (or empty), a new survey record is inserted and its id is returned.
     * If $sid is non-zero, the existing record is updated.
     *
     * @param int $sid  Survey id; 0 to create a new survey.
     * @param stdClass $sdata  Survey data object.
     * @return int|false  The survey id on success, false on failure.
     */
    public static function update_survey(int $sid, stdClass $sdata): int|false {
        global $DB;

        $fields = [
            'name',
            'realm',
            'title',
            'subtitle',
            'email',
            'theme',
            'thankspage',
            'thankhead',
            'thankbody',
            'feedbacknotes',
            'info',
            'feedbacksections',
            'feedbackscores',
            'charttype',
        ];

        if (empty($sid)) {
            // Create a new survey.
            // Theme field deprecated.
            $record = new stdClass();
            $record->id = 0;
            $record->courseid = $sdata->courseid;
            foreach ($fields as $f) {
                if (isset($sdata->$f)) {
                    $record->$f = $sdata->$f;
                }
            }
            $newsid = $DB->insert_record('questionnaire_survey', $record);
            if (!$newsid) {
                return false;
            }
            return $newsid;
        } else {
            if (empty($sdata->name) || empty($sdata->title) || empty($sdata->realm)) {
                return false;
            }
            if (!isset($sdata->charttype)) {
                $sdata->charttype = '';
            }

            $name = $DB->get_field('questionnaire_survey', 'name', ['id' => $sid]);

            // Trying to change survey name.
            if (trim($name) != trim(stripslashes($sdata->name))) {
                $count = $DB->count_records('questionnaire_survey', ['name' => $sdata->name]);
                if ($count != 0) {
                    return false;
                }
            }

            // UPDATE the row in the DB with current values.
            $surveyrecord = new stdClass();
            $surveyrecord->id = $sid;
            foreach ($fields as $f) {
                if (isset($sdata->{$f})) {
                    $surveyrecord->$f = trim($sdata->{$f});
                }
            }

            $result = $DB->update_record('questionnaire_survey', $surveyrecord);
            if (!$result) {
                return false;
            }
            return $sid;
        }
    }

    /**
     * Create an editable copy of a survey, including all questions, choices, dependencies,
     * and feedback sections.
     *
     * @param stdClass $survey     The survey record to copy (from questionnaire.class.php::$survey).
     * @param array    $questions  The questions array (from questionnaire.class.php::$questions).
     * @param int      $owner      The courseid that will own the new survey.
     * @return int|false  The new survey id on success, false on failure.
     */
    public static function copy_survey(stdClass $survey, array $questions, int $owner): int|false {
        global $DB;

        // Clear the sid, clear the creation date, change the name, and clear the status.
        $survey = clone $survey;

        $oldsid = $survey->id;
        unset($survey->id);
        $survey->courseid = $owner;
        // Make sure that the survey name is not larger than the field size (CONTRIB-2999). Leave room for extra chars.
        $survey->name = \core_text::substr($survey->name, 0, 64 - 10);

        $survey->name .= '_copy';
        $survey->status = 0;

        // Check for 'name' conflict, and resolve.
        $i = 0;
        $name = $survey->name;
        while ($DB->count_records('questionnaire_survey', ['name' => $name]) > 0) {
            $name = $survey->name . (++$i);
        }
        if ($i) {
            $survey->name .= $i;
        }

        // Create new survey.
        if (!($newsid = $DB->insert_record('questionnaire_survey', $survey))) {
            return false;
        }

        // Make copies of all the questions.
        $pos = 1;
        // Skip logic: some changes needed here for dependencies down below.
        $qidarray = [];
        $cidarray = [];
        foreach ($questions as $question) {
            // Fix some fields first.
            $oldid = $question->id;
            unset($question->id);
            $question->surveyid = $newsid;
            $question->position = $pos++;

            // Copy question to new survey.
            if (!($newqid = $DB->insert_record('questionnaire_question', $question))) {
                return false;
            }
            $qidarray[$oldid] = $newqid;
            foreach ($question->choices as $key => $choice) {
                $oldcid = $key;
                $newchoice = (object) [
                    'questionid' => $newqid,
                    'content' => $choice->content,
                    'value' => $choice->value,
                ];
                if (!$newcid = $DB->insert_record('questionnaire_quest_choice', $newchoice)) {
                    return false;
                }
                $cidarray[$oldcid] = $newcid;
            }
        }

        // Replicate all dependency data.
        if ($dependquestions = $DB->get_records('questionnaire_dependency', ['surveyid' => $oldsid], 'questionid')) {
            foreach ($dependquestions as $dquestion) {
                $record = new stdClass();
                $record->questionid = $qidarray[$dquestion->questionid];
                $record->surveyid = $newsid;
                $record->dependquestionid = $qidarray[$dquestion->dependquestionid];
                // The response may not use choice id's (example boolean). If not, just copy the value.
                $responsetype = $questions[$dquestion->dependquestionid]->responsetype;
                if ($responsetype->transform_choiceid($dquestion->dependchoiceid) == $dquestion->dependchoiceid) {
                    $record->dependchoiceid = $cidarray[$dquestion->dependchoiceid];
                } else {
                    $record->dependchoiceid = $dquestion->dependchoiceid;
                }
                $record->dependlogic = $dquestion->dependlogic;
                $record->dependandor = $dquestion->dependandor;
                $DB->insert_record('questionnaire_dependency', $record);
            }
        }

        // Replicate any feedback data.
        // TODO: Need to handle image attachments (same for other copies above).
        if ($fbsections = $DB->get_records('questionnaire_fb_sections', ['surveyid' => $oldsid], 'id')) {
            foreach ($fbsections as $fbsid => $fbsection) {
                $fbsection->surveyid = $newsid;
                $scorecalculation = \mod_questionnaire\local\feedback\section::decode_scorecalculation(
                    $fbsection->scorecalculation
                );
                $newscorecalculation = [];
                foreach ($scorecalculation as $qid => $val) {
                    $newscorecalculation[$qidarray[$qid]] = $val;
                }
                $fbsection->scorecalculation = serialize($newscorecalculation);
                unset($fbsection->id);
                $newfbsid = $DB->insert_record('questionnaire_fb_sections', $fbsection);
                if ($feedbackrecs = $DB->get_records('questionnaire_feedback', ['sectionid' => $fbsid], 'id')) {
                    foreach ($feedbackrecs as $feedbackrec) {
                        $feedbackrec->sectionid = $newfbsid;
                        unset($feedbackrec->id);
                        $DB->insert_record('questionnaire_feedback', $feedbackrec);
                    }
                }
            }
        }

        return $newsid;
    }
}
