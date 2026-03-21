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

use mod_questionnaire\local\db\choice_record;
use mod_questionnaire\local\db\dependency_record;
use mod_questionnaire\local\db\feedback_record;
use mod_questionnaire\local\db\feedback_section_record;
use mod_questionnaire\local\db\question_record;
use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;
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

    /** @var string Course-module idnumber, used by gradebook. Set by callers that need it. */
    public string $cmidnumber = '';

    /** @var int Course id, set by callers that need it directly on the object. */
    public int $courseid = 0;

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
     * Get the intro text of the questionnaire.
     *
     * @return string
     */
    public function intro(): string {
        return $this->modulerecord->get('intro');
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
     * Get the grade value for this questionnaire.
     *
     * @return int
     */
    public function grade(): int {
        return $this->modulerecord->get('grade');
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
     * Reload questions from the database into this instance.
     *
     * @return void
     */
    public function add_questions(): void {
        $this->questions = [];
        $this->questionsbysec = [];
        $this->load_questions();
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
     * Create a new questionnaire survey record.
     *
     * @param stdClass $sdata  Survey data object (must include courseid).
     * @return int  The new survey id.
     */
    public static function add_survey(stdClass $sdata): int {
        return survey_record::create_from_sdata($sdata)->get('id');
    }

    /**
     * Update an existing questionnaire survey record.
     *
     * @param int $sid  Survey id.
     * @param stdClass $sdata  Survey data object.
     * @return int|false  The survey id on success, false on failure.
     */
    public static function update_survey(int $sid, stdClass $sdata): int|false {
        if (empty($sdata->name) || empty($sdata->title) || empty($sdata->realm)) {
            return false;
        }

        // Trying to change survey name.
        $existing = new survey_record($sid);
        if (trim($existing->get('name')) != trim(stripslashes($sdata->name))) {
            if (survey_record::count_records(['name' => $sdata->name]) != 0) {
                return false;
            }
        }

        return survey_record::update_from_sdata($sid, $sdata)->get('id');
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
        $oldsid = $survey->id;

        // Build the new survey name: truncate, append _copy, then resolve any conflicts.
        $basename = \core_text::substr($survey->name, 0, 64 - 10) . '_copy';
        $name = $basename;
        $i = 0;
        while (survey_record::count_records(['name' => $name]) > 0) {
            $name = $basename . (++$i);
        }

        // Create new survey record, carrying over all content fields.
        $newsurvey = new survey_record();
        $newsurvey->set('courseid', $owner);
        $newsurvey->set('name', $name);
        $newsurvey->set('status', 0);
        foreach (
            ['realm', 'title', 'email', 'subtitle', 'info', 'theme',
            'thankspage', 'thankhead', 'thankbody', 'feedbacksections',
            'feedbacknotes', 'feedbackscores', 'charttype'] as $f
        ) {
            if (isset($survey->$f)) {
                $newsurvey->set($f, $survey->$f);
            }
        }
        $newsurvey->create();
        $newsid = $newsurvey->get('id');

        // Make copies of all the questions.
        $pos = 1;
        // Skip logic: some changes needed here for dependencies down below.
        $qidarray = [];
        $cidarray = [];
        foreach ($questions as $question) {
            $oldid = $question->id;
            $newq = new question_record();
            $newq->set('surveyid', $newsid);
            $newq->set('position', $pos++);
            foreach (
                ['name', 'typeid', 'resultid', 'length', 'precise',
                'content', 'required', 'deleted', 'extradata'] as $f
            ) {
                if (isset($question->$f)) {
                    $newq->set($f, $question->$f);
                }
            }
            $newq->create();
            $newqid = $newq->get('id');
            $qidarray[$oldid] = $newqid;

            foreach ($question->choices as $oldcid => $choice) {
                $newchoice = new choice_record();
                $newchoice->set('questionid', $newqid);
                $newchoice->set('content', $choice->content);
                $newchoice->set('value', $choice->value);
                $newchoice->create();
                $cidarray[$oldcid] = $newchoice->get('id');
            }
        }

        // Replicate all dependency data.
        foreach (dependency_record::get_for_survey($oldsid) as $dep) {
            $newdep = new dependency_record();
            $newdep->set('questionid', $qidarray[$dep->get('questionid')]);
            $newdep->set('surveyid', $newsid);
            $newdep->set('dependquestionid', $qidarray[$dep->get('dependquestionid')]);
            // The response may not use choice id's (example boolean). If not, just copy the value.
            $responsetype = $questions[$dep->get('dependquestionid')]->responsetype;
            $depchoiceid = $dep->get('dependchoiceid');
            if ($responsetype->transform_choiceid($depchoiceid) == $depchoiceid) {
                $newdep->set('dependchoiceid', $cidarray[$depchoiceid]);
            } else {
                $newdep->set('dependchoiceid', $depchoiceid);
            }
            $newdep->set('dependlogic', $dep->get('dependlogic'));
            $newdep->set('dependandor', $dep->get('dependandor'));
            $newdep->create();
        }

        // Replicate any feedback data. Note: image attachments are not yet copied.
        foreach (feedback_section_record::get_for_survey($oldsid) as $oldfbs) {
            $scorecalculation = \mod_questionnaire\local\feedback\section::decode_scorecalculation(
                $oldfbs->get('scorecalculation')
            );
            $newscorecalculation = [];
            foreach ($scorecalculation as $qid => $val) {
                $newscorecalculation[$qidarray[$qid]] = $val;
            }
            $newfbs = new feedback_section_record();
            $newfbs->set('surveyid', $newsid);
            $newfbs->set('section', $oldfbs->get('section'));
            $newfbs->set('sectionlabel', $oldfbs->get('sectionlabel'));
            $newfbs->set('sectionheading', $oldfbs->get('sectionheading'));
            $newfbs->set('sectionheadingformat', $oldfbs->get('sectionheadingformat'));
            $newfbs->set('scorecalculation', serialize($newscorecalculation));
            $newfbs->create();
            $newfbsid = $newfbs->get('id');

            foreach (feedback_record::get_for_section($oldfbs->get('id')) as $oldfbr) {
                $newfbr = new feedback_record();
                $newfbr->set('sectionid', $newfbsid);
                $newfbr->set('feedbacklabel', $oldfbr->get('feedbacklabel'));
                $newfbr->set('feedbacktext', $oldfbr->get('feedbacktext'));
                $newfbr->set('feedbacktextformat', $oldfbr->get('feedbacktextformat'));
                $newfbr->set('minscore', $oldfbr->get('minscore'));
                $newfbr->set('maxscore', $oldfbr->get('maxscore'));
                $newfbr->create();
            }
        }

        return $newsid;
    }

    /**
     * Create a new questionnaire activity instance.
     *
     * Resolves the survey (new blank / copy / existing public), inserts the questionnaire
     * row, and fires calendar and completion events. Intended to be the single point of
     * delegation from lib.php's questionnaire_add_instance().
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
            $sid = self::add_survey($sdata);
        } else {
            $parts = explode('-', $formdata->create);
            $copyrealm = $parts[0];
            $copyid = (int) $parts[1];

            if ($copyrealm == 'public') {
                // Reuse the existing public survey — no copy needed.
                $sid = $copyid;
            } else {
                // Copy the survey, its questions, choices, dependencies, and feedback.
                $survey = (new survey_record($copyid))->to_record();
                $questions = self::load_questions_for_survey($copyid);
                $sid = self::copy_survey($survey, $questions, $formdata->course);

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
     * calendar and completion events. Intended to be the single point of delegation from
     * lib.php's questionnaire_update_instance().
     *
     * @param stdClass $questionnaire Form data from mod_form (instance, sid, realm, …).
     * @return bool True on success.
     */
    public static function update_instance(stdClass $questionnaire): bool {
        // Sync the survey realm when the form provides one.
        if (!empty($questionnaire->sid) && !empty($questionnaire->realm)) {
            $survey = new survey_record($questionnaire->sid);
            $survey->set('realm', $questionnaire->realm);
            $survey->update();
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
     * Create or update calendar events for a questionnaire instance.
     *
     * Deletes any existing calendar events for the instance, then creates open and/or
     * close events based on the questionnaire's opendate and closedate.
     *
     * @param stdClass $questionnaire Questionnaire instance record (needs id, course, name, opendate, closedate).
     * @return void
     */
    public static function set_events(stdClass $questionnaire): void {
        global $DB;

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
            && ($event->timeduration <= QUESTIONNAIRE_MAX_EVENT_LENGTH)
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

    // -------------------------------------------------------------------------
    // Gradebook methods (delegated from lib.php)
    // -------------------------------------------------------------------------

    /**
     * Return grade for given user or all users.
     *
     * @param object $questionnaire Questionnaire instance record or new questionnaire object.
     * @param int $userid Optional user id, 0 means all users.
     * @return array Array of grade records.
     */
    public static function get_user_grades(object $questionnaire, int $userid = 0): array {
        global $DB;
        $qid = ($questionnaire instanceof \mod_questionnaire\questionnaire) ? $questionnaire->id() : $questionnaire->id;
        $params = [];
        $usersql = '';
        if (!empty($userid)) {
            $usersql = "AND u.id = ?";
            $params[] = $userid;
        }

        $sql = "SELECT r.id, u.id AS userid, r.grade AS rawgrade, r.submitted AS dategraded, r.submitted AS datesubmitted
                FROM {user} u, {questionnaire_response} r
                WHERE u.id = r.userid AND r.questionnaireid = $qid AND r.complete = 'y' $usersql";
        return $DB->get_records_sql($sql, $params) ?? [];
    }

    /**
     * Create grade item for given questionnaire.
     *
     * @param object $questionnaire Object with extra cmidnumber.
     * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook.
     * @return int 0 if ok, error code otherwise.
     */
    public static function grade_item_update(object $questionnaire, mixed $grades = null): int {
        global $CFG;
        if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
            require_once($CFG->libdir . '/gradelib.php');
        }

        if ($questionnaire instanceof \mod_questionnaire\questionnaire) {
            $qid = $questionnaire->id();
            $qname = $questionnaire->name();
            $qgrade = $questionnaire->grade();
            $qcourseid = $questionnaire->courseid();
            $qcmidnumber = $questionnaire->cmidnumber;
        } else {
            $qid = $questionnaire->instance ?? $questionnaire->id;
            $qname = $questionnaire->name;
            $qgrade = $questionnaire->grade;
            $qcourseid = $questionnaire->courseid ?? $questionnaire->course;
            $qcmidnumber = $questionnaire->cmidnumber;
        }

        if ($qcmidnumber != '') {
            $params = ['itemname' => $qname, 'idnumber' => $qcmidnumber];
        } else {
            $params = ['itemname' => $qname];
        }

        if ($qgrade > 0) {
            $params['gradetype'] = GRADE_TYPE_VALUE;
            $params['grademax'] = $qgrade;
            $params['grademin'] = 0;
        } else if ($qgrade < 0) {
            $params['gradetype'] = GRADE_TYPE_SCALE;
            $params['scaleid'] = -$qgrade;
        } else if ($qgrade == 0) { // No Grade..be sure to delete the grade item if it exists.
            $grades = null;
            $params = ['deleted' => 1];
        } else {
            $params = null; // Allow text comments only.
        }

        if ($grades === 'reset') {
            $params['reset'] = true;
            $grades = null;
        }

        return grade_update(
            'mod/questionnaire',
            $qcourseid,
            'mod',
            'questionnaire',
            $qid,
            0,
            $grades,
            $params
        );
    }

    /**
     * Update grades by firing grade_updated event.
     *
     * @param object|null $questionnaire Questionnaire instance record, or null to update all.
     * @param int $userid Optional user id, 0 means all users.
     * @param bool $nullifnone Unused — API requires it.
     * @return void
     */
    public static function update_grades(?object $questionnaire = null, int $userid = 0, bool $nullifnone = true): void {
        global $CFG, $DB;

        if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
            require_once($CFG->libdir . '/gradelib.php');
        }

        if ($questionnaire != null) {
            if ($graderecs = self::get_user_grades($questionnaire, $userid)) {
                $grades = [];
                foreach ($graderecs as $v) {
                    if (!isset($grades[$v->userid])) {
                        $grades[$v->userid] = new stdClass();
                        if ($v->rawgrade == -1) {
                            $grades[$v->userid]->rawgrade = null;
                        } else {
                            $grades[$v->userid]->rawgrade = $v->rawgrade;
                        }
                        $grades[$v->userid]->userid = $v->userid;
                    } else if (isset($grades[$v->userid]) && ($v->rawgrade > $grades[$v->userid]->rawgrade)) {
                        $grades[$v->userid]->rawgrade = $v->rawgrade;
                    }
                }
                self::grade_item_update($questionnaire, $grades);
            } else {
                self::grade_item_update($questionnaire);
            }
        } else {
            $sql = "SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
                      FROM {questionnaire} q, {course_modules} cm, {modules} m
                     WHERE m.name='questionnaire' AND m.id=cm.module AND cm.instance=q.id";
            if ($rs = $DB->get_recordset_sql($sql)) {
                foreach ($rs as $questionnaire) {
                    if ($questionnaire->grade != 0) {
                        self::update_grades($questionnaire);
                    } else {
                        self::grade_item_update($questionnaire);
                    }
                }
                $rs->close();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load all active questions for a survey, built as full question objects.
     *
     * Used by copy_survey() which needs responsetype access for dependency replication.
     *
     * @param int $sid Survey id.
     * @return array Question objects indexed by question id.
     */
    private static function load_questions_for_survey(int $sid): array {
        $questions = [];
        foreach (question_record::get_active_for_survey($sid) as $qrec) {
            $questions[$qrec->get('id')] = question::question_builder($qrec->get('typeid'), $qrec->to_record());
        }
        return $questions;
    }
}
