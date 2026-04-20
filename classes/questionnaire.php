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
use mod_questionnaire\local\question\question;
use mod_questionnaire\local\response\questionnaire_responses;
use mod_questionnaire\local\response\response;
use mod_questionnaire\survey;
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
    // Response-frequency (qtype field) constants.

    /** @var int Response frequency: unlimited attempts. */
    private const QTYPE_UNLIMITED = 0;
    /** @var int Response frequency: one attempt only. */
    private const QTYPE_ONCE = 1;
    /** @var int Response frequency: once per day. */
    private const QTYPE_DAILY = 2;
    /** @var int Response frequency: once per week. */
    private const QTYPE_WEEKLY = 3;
    /** @var int Response frequency: once per month. */
    private const QTYPE_MONTHLY = 4;

    // Student response-view (respview field) constants.

    /** @var int Response visibility: students never see other responses. */
    private const RESPVIEW_NEVER = 0;
    /** @var int Response visibility: students see responses after they have answered. */
    private const RESPVIEW_WHENANSWERED = 1;
    /** @var int Response visibility: students see responses after the questionnaire closes. */
    private const RESPVIEW_WHENCLOSED = 2;
    /** @var int Response visibility: students always see other responses. */
    private const RESPVIEW_ALWAYS = 3;

    // Other internal constants.

    /** @var int Maximum calendar event duration in seconds (5 days). */
    private const MAX_EVENT_LENGTH = 5 * 24 * 60 * 60;
    /** @var int Default number of rows per pagination page. */
    private const DEFAULT_PAGE_COUNT = 20;
    /** @var string URL parameter name for the permanent-delete confirmation action. */
    private const CONFIRM_DELETE_PERMANENTLY = 'confirmdelpermanentlyq';
    /** @var string URL parameter name for the question-restore action. */
    private const RESTORE_PARAM = 'restoreq';

    /** @var questionnaire_record The module record instance. */
    protected questionnaire_record $modulerecord;

    /** @var survey The survey domain object for this questionnaire. */
    protected survey $survey;

    /** @var stdClass|\cm_info The course_modules record for this questionnaire. */
    protected stdClass|\cm_info $coursemodule;

    /** @var context_module The context for this questionnaire. */
    protected context_module $context;

    /** @var stdClass The course record for this questionnaire. */
    protected stdClass $course;

    // PROPERTIES TO BE REPLACED AND REFACTORED LATER.

    /** @var \plugin_renderer_base The module renderer, extended from core renderer. */
    public $renderer;

    /** @var \templatable The templatable page to render. */
    public $page;

    /** @var \questionnaire|null Lazy-loaded legacy instance used by rendering shims. */
    private $legacyinstance = null;

    /** @var string Course-module idnumber, used by gradebook. Set by callers that need it. */
    public string $cmidnumber = '';

    /** @var int Course id, set by callers that need it directly on the object. */
    public int $courseid = 0;

    /**
     * Construct from a questionnaire instance id, optionally with a pre-loaded persistent and cm.
     *
     * @param int $mid Instance id (questionnaire.id).
     * @param questionnaire_record|null $modulerecord Pre-loaded persistent — omit to load from DB.
     * @param stdClass|\cm_info|null $coursemodule Pre-loaded course_modules row — omit to look up.
     */
    public function __construct(
        int $mid = 0,
        ?questionnaire_record $modulerecord = null,
        stdClass|\cm_info|null $coursemodule = null
    ) {
        if (!empty($mid)) {
            $this->modulerecord = new questionnaire_record($mid);
        } else if (!empty($modulerecord)) {
            $this->modulerecord = $modulerecord;
        } else {
            throw new \coding_exception('Either mid or modulerecord must be provided to construct a questionnaire object.');
        }

        $this->coursemodule = $coursemodule ??
            get_coursemodule_from_instance('questionnaire', $this->modulerecord->get('id'), 0, false, MUST_EXIST);
        $this->context = context_module::instance($this->coursemodule->id);
        $this->course = get_course($this->modulerecord->get('course'));
        $sid = $this->modulerecord->get('sid');
        $this->survey = survey::from_sid((int) $sid, $this->context);
    }

    /**
     * Return a questionnaire instance from an activity instance id.
     *
     * @param int $instanceid questionnaire.id
     * @param stdClass|\cm_info|null $cm Optional pre-loaded course_modules row.
     * @return self
     */
    public static function from_instanceid(int $instanceid, stdClass|\cm_info|null $cm = null): self {
        return new self($instanceid, null, $cm);
    }

    /**
     * Return a questionnaire instance from a course module id.
     *
     * @param int $cmid course_modules.id
     * @param stdClass|\cm_info|null $cm Optional pre-loaded course_modules row.
     * @return self
     */
    public static function from_cmid(int $cmid, stdClass|\cm_info|null $cm = null): self {
        $cm = $cm ?? get_coursemodule_from_id('questionnaire', $cmid, 0, false, MUST_EXIST);
        return new self($cm->instance, null, $cm);
    }

    /**
     * Return a questionnaire instance from a course_modules object.
     *
     * @param stdClass|\cm_info $cm course_modules row or cm_info object.
     * @return self
     */
    public static function from_cm(stdClass|\cm_info $cm): self {
        return new self($cm->instance, null, $cm);
    }

    /**
     * Return a lightweight questionnaire instance built from already-loaded persistent records.
     *
     * Skips all database lookups for course, course_module, and context. Only
     * modulerecord and survey are set. This factory is intended for low-overhead
     * contexts such as building select lists in mod_form, where CM and capability
     * checks are not needed.
     *
     * Callers must not invoke methods that require coursemodule(), context(), or course().
     *
     * @param questionnaire_record $qrec Already-loaded questionnaire persistent record.
     * @param local\db\survey_record $srec Already-loaded survey persistent record.
     * @return self
     */
    public static function from_records(
        questionnaire_record $qrec,
        local\db\survey_record $srec
    ): self {
        // Bypass the normal constructor to avoid the CM and course DB lookups.
        $instance = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->modulerecord = $qrec;
        $instance->survey = survey::from_record_shallow($srec);
        return $instance;
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
     * Get the survey domain object for this questionnaire.
     *
     * @return survey
     */
    public function survey(): survey {
        return $this->survey;
    }

    /**
     * Get the survey id linked to this questionnaire.
     *
     * @return int
     */
    public function surveyid(): int {
        return $this->survey->id();
    }

    /**
     * Get the survey title.
     *
     * @return string
     */
    public function surveytitle(): string {
        return $this->survey->title();
    }

    /**
     * Get the survey subtitle.
     *
     * @return string
     */
    public function surveysubtitle(): string {
        return $this->survey->subtitle();
    }

    /**
     * Get the survey info/description.
     *
     * @return string
     */
    public function surveyinfo(): string {
        return $this->survey->info();
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
     * Get the respondent type setting for this questionnaire (e.g. 'fullname', 'anonymous').
     *
     * @return string
     */
    public function respondenttype(): string {
        return $this->modulerecord->get('respondenttype') ?? 'fullname';
    }

    /**
     * Return true if this questionnaire allows resuming a saved response.
     *
     * @return bool
     */
    public function resume(): bool {
        return (bool) $this->modulerecord->get('resume');
    }

    /**
     * Get all question objects for this questionnaire.
     *
     * @return question[]
     */
    public function questions(): array {
        return $this->survey->questions();
    }

    /**
     * Return the soft-deleted questions for this questionnaire's survey.
     *
     * @return question[] Keyed by question id.
     */
    public function get_delete_questions(): array {
        global $DB;
        $sql = "SELECT *
                  FROM {questionnaire_question}
                 WHERE deleted IS NOT NULL
                   AND surveyid = ? AND typeid != ?
              ORDER BY deleted DESC";
        $deletequestions = [];
        if ($records = $DB->get_records_sql($sql, [$this->surveyid(), QUESPAGEBREAK])) {
            foreach ($records as $record) {
                $deletequestions[$record->id] = \mod_questionnaire\local\question\question::question_builder(
                    $record->typeid,
                    $record,
                    $this->context
                );
            }
        }
        return $deletequestions;
    }

    /**
     * Move a question to a new position, re-numbering surrounding questions.
     *
     * Shim — delegates to the legacy questionnaire class until question ordering
     * is refactored onto the new domain objects.
     *
     * @param int $moveqid    Id of the question to move.
     * @param int $movetopos  Target position (1-based).
     * @return bool
     */
    public function move_question(int $moveqid, int $movetopos): bool {
        return $this->legacy()->move_question($moveqid, $movetopos);
    }

    /**
     * Validate that page breaks are correctly placed for dependent questions.
     *
     * Shim — delegates to the legacy questionnaire class until page-break logic
     * is refactored.
     *
     * @return false|string Status message, or false on failure.
     */
    public function check_page_breaks() {
        return $this->legacy()->check_page_breaks();
    }

    /**
     * Reload questions from the database into this instance.
     *
     * @return void
     */
    public function add_questions(): void {
        $this->survey->add_questions();
    }

    /**
     * Get question objects for a specific section.
     *
     * @param int $section 1-based section number.
     * @return question[]
     */
    public function questions_by_section(int $section = 1): array {
        return $this->survey->questions_by_section($section);
    }

    /**
     * Get all question arrays organised by section (keyed by 1-based section number).
     *
     * @return array
     */
    public function questions_by_section_all(): array {
        return $this->survey->questions_by_section_all();
    }

    /**
     * True if questions should be automatically numbered.
     *
     * @return bool
     */
    public function questions_autonumbered(): bool {
        $autonum = $this->modulerecord->get('autonum');
        return !empty($autonum) && ($autonum == 1 || $autonum == 3);
    }

    /**
     * Whether navigation (skip-logic) is enabled for this questionnaire.
     *
     * @return int 0 = disabled, 1 = enabled.
     */
    public function navigate(): int {
        return (int) $this->modulerecord->get('navigate');
    }

    /**
     * True if pages should be automatically numbered.
     *
     * @return bool
     */
    public function pages_autonumbered(): bool {
        $autonum = $this->modulerecord->get('autonum');
        return !empty($autonum) && ($autonum == 2 || $autonum == 3);
    }

    /**
     * True if any question in this questionnaire has dependency (skip-logic) conditions.
     *
     * @return bool
     */
    public function has_dependencies(): bool {
        return $this->navigate() > 0 && $this->survey->has_questions_with_dependencies();
    }

    /**
     * Get the IDs of all questions that depend on the given question.
     *
     * @param int $questionid
     * @return array
     */
    public function get_dependants(int $questionid): array {
        return $this->survey->get_dependants($questionid);
    }

    /**
     * Get all direct and indirect dependants of a question.
     *
     * @param int $questionid
     * @return stdClass Object with ->directs and ->indirects arrays.
     */
    public function get_all_dependants(int $questionid): stdClass {
        return $this->survey->get_all_dependants($questionid);
    }

    /**
     * Get all descendants and their choice conditions, keyed by parent question id.
     *
     * @return array
     */
    public function get_dependants_and_choices(): array {
        return $this->survey->get_dependants_and_choices();
    }

    /**
     * Load display info for each dependency of a question (parent name, choice label, etc.).
     *
     * @param question $question
     * @return bool
     */
    public function load_parents(question $question): bool {
        return $this->survey->load_parents($question);
    }

    /**
     * True if there are any eligible (dependency-satisfied) questions on the given section.
     *
     * @param int $secnum 1-based section number.
     * @param int $rid Response id (0 if no response yet).
     * @return bool
     */
    public function eligible_questions_on_page(int $secnum, int $rid): bool {
        return $this->survey->eligible_questions_on_page($secnum, $rid);
    }

    /**
     * Return the next valid section number after $secnum, or false if none.
     *
     * @param int $secnum Current section number.
     * @param int $rid Response id.
     * @return int|bool
     */
    public function next_page(int $secnum, int $rid): int|bool {
        return $this->survey->next_page($secnum, $rid, $this->has_dependencies());
    }

    /**
     * Return the previous valid section number before $secnum, or false if none.
     *
     * @param int $secnum Current section number.
     * @param int $rid Response id.
     * @return int|bool
     */
    public function prev_page(int $secnum, int $rid): int|bool {
        return $this->survey->prev_page($secnum, $rid, $this->has_dependencies());
    }

    /**
     * True if the questionnaire has an associated survey (is active).
     *
     * @return bool
     */
    public function is_active(): bool {
        return $this->survey->id() > 0;
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
     * True if the questionnaire is configured to require submission for completion.
     *
     * @return bool
     */
    public function completionsubmit(): bool {
        return (bool) $this->modulerecord->get('completionsubmit');
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
        return $this->survey->is_public();
    }

    /**
     * True if the survey is a template.
     *
     * @return bool
     */
    public function survey_is_template(): bool {
        return $this->survey->is_template();
    }

    /**
     * True if the survey is public and this questionnaire is in the owning course.
     *
     * @return bool
     */
    public function survey_is_public_master(): bool {
        return $this->survey->is_public_master($this->courseid());
    }

    /**
     * True if the current course owns this survey (as opposed to using a shared public survey).
     *
     * @return bool
     */
    public function is_survey_owner(): bool {
        return $this->survey->is_owned_by_course($this->courseid());
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
     * True if the current user can view this questionnaire.
     *
     * @return bool
     */
    public function can_view(): bool {
        return has_capability('mod/questionnaire:view', $this->context);
    }

    /**
     * True if the current user can preview this questionnaire.
     *
     * @return bool
     */
    public function can_preview(): bool {
        return has_capability('mod/questionnaire:preview', $this->context);
    }

    /**
     * True if the current user can print a blank copy of this questionnaire.
     *
     * @return bool
     */
    public function can_print_blank(): bool {
        return has_capability('mod/questionnaire:printblank', $this->context);
    }

    /**
     * True if the current user can create template surveys.
     *
     * @return bool
     */
    public function can_create_templates(): bool {
        return has_capability('mod/questionnaire:createtemplates', $this->context);
    }

    /**
     * True if the current user can create public surveys.
     *
     * @return bool
     */
    public function can_create_public(): bool {
        return has_capability('mod/questionnaire:createpublic', $this->context);
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
        } else if ($this->modulerecord->get('qtype') == self::QTYPE_UNLIMITED) {
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
            case self::QTYPE_UNLIMITED:
                return true;

            case self::QTYPE_ONCE:
                return false;

            case self::QTYPE_DAILY:
                return (date('Y', $attempt->submitted) < date('Y', $timenow)) ||
                    (date('Yz', $attempt->submitted) < date('Yz', $timenow));

            case self::QTYPE_WEEKLY:
                return (date('Y', $attempt->submitted) < date('Y', $timenow)) ||
                    (date('YW', $attempt->submitted) < date('YW', $timenow));

            case self::QTYPE_MONTHLY:
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
     * True if the given user has at least one complete (submitted) response for this questionnaire.
     *
     * @param int $userid
     * @return bool
     */
    public function user_has_submitted(int $userid): bool {
        return (new questionnaire_responses($this))->response_exists($userid);
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
            $params['surveyid'] = $this->survey->id();
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
            has_capability('mod/questionnaire:readallresponses', $this->context) &&
            ($respview == self::RESPVIEW_ALWAYS ||
                ($respview == self::RESPVIEW_WHENCLOSED && $this->is_closed()) ||
                ($respview == self::RESPVIEW_WHENANSWERED && $usernumresp));
    }

    /**
     * True if the current user can view the specified response (or any response if $rid is 0).
     *
     * @param int $rid Response id to check, or 0 to check general viewing rights.
     * @return bool
     */
    public function can_view_response(int $rid = 0): bool {
        global $USER, $DB;

        $respview = $this->modulerecord->get('respview');

        if (!empty($rid)) {
            $response = $DB->get_record('questionnaire_response', ['id' => $rid]);

            // Response not found or belongs to a different questionnaire.
            if (empty($response) || $response->questionnaireid != $this->id()) {
                return false;
            }

            // Can always view if you have the unrestricted capability.
            if (has_capability('mod/questionnaire:readallresponseanytime', $this->context)) {
                return true;
            }

            // Can view other users' responses if capability is set and view conditions are met.
            if (
                has_capability('mod/questionnaire:readallresponses', $this->context) &&
                ($respview == self::RESPVIEW_ALWAYS ||
                 ($respview == self::RESPVIEW_WHENCLOSED && $this->is_closed()) ||
                 ($respview == self::RESPVIEW_WHENANSWERED && !$this->user_can_take($USER->id)))
            ) {
                return true;
            }

            // Can view own response.
            if (
                $response->userid == $USER->id &&
                has_capability('mod/questionnaire:readownresponses', $this->context) &&
                $this->count_submissions($USER->id) > 0
            ) {
                return true;
            }
        } else {
            // No specific response — check general viewing rights.
            if (has_capability('mod/questionnaire:readallresponseanytime', $this->context)) {
                return true;
            }

            if (
                has_capability('mod/questionnaire:readallresponses', $this->context) &&
                ($respview == self::RESPVIEW_ALWAYS ||
                 ($respview == self::RESPVIEW_WHENCLOSED && $this->is_closed()) ||
                 ($respview == self::RESPVIEW_WHENANSWERED && !$this->user_can_take($USER->id)))
            ) {
                return true;
            }

            if (
                has_capability('mod/questionnaire:readownresponses', $this->context) &&
                $this->count_submissions($USER->id) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return information strings about how the questionnaire can be attempted.
     *
     * @return string[]
     */
    public function view_information(): array {
        $messages = [];

        switch ($this->modulerecord->get('qtype')) {
            case self::QTYPE_UNLIMITED:
                $typestring = get_string('unlimited', 'questionnaire');
                break;
            case self::QTYPE_ONCE:
                $typestring = get_string('once', 'questionnaire');
                break;
            case self::QTYPE_DAILY:
                $typestring = get_string('daily', 'questionnaire');
                break;
            case self::QTYPE_WEEKLY:
                $typestring = get_string('weekly', 'questionnaire');
                break;
            case self::QTYPE_MONTHLY:
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
     * Render an array of info messages as HTML paragraphs.
     *
     * @param array $messages
     * @return string
     */
    public function access_messages(array $messages): string {
        $output = '';
        foreach ($messages as $message) {
            $output .= \html_writer::tag('p', $message) . "\n";
        }
        return $output;
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
                case self::QTYPE_DAILY:
                    $msgstring = ' ' . get_string('today', 'questionnaire');
                    break;
                case self::QTYPE_WEEKLY:
                    $msgstring = ' ' . get_string('thisweek', 'questionnaire');
                    break;
                case self::QTYPE_MONTHLY:
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

    // Behavior methods — expose internal constants as a public API.

    /**
     * Return a localised label => value map of response-frequency options for form selects.
     *
     * @return array  Keys are the qtype integer values; values are localised display strings.
     */
    public static function response_frequency_options(): array {
        return [
            self::QTYPE_UNLIMITED => get_string('qtypeunlimited', 'questionnaire'),
            self::QTYPE_ONCE      => get_string('qtypeonce', 'questionnaire'),
            self::QTYPE_DAILY     => get_string('qtypedaily', 'questionnaire'),
            self::QTYPE_WEEKLY    => get_string('qtypeweekly', 'questionnaire'),
            self::QTYPE_MONTHLY   => get_string('qtypemonthly', 'questionnaire'),
        ];
    }

    /**
     * Return a localised label => value map of student-response-viewer options for form selects.
     *
     * @return array  Keys are the respview integer values; values are localised display strings.
     */
    public static function response_viewer_options(): array {
        return [
            self::RESPVIEW_WHENANSWERED => get_string('responseviewstudentswhenanswered', 'questionnaire'),
            self::RESPVIEW_WHENCLOSED   => get_string('responseviewstudentswhenclosed', 'questionnaire'),
            self::RESPVIEW_ALWAYS       => get_string('responseviewstudentsalways', 'questionnaire'),
            self::RESPVIEW_NEVER        => get_string('responseviewstudentsnever', 'questionnaire'),
        ];
    }

    /**
     * Return the default number of rows shown per pagination page.
     *
     * @return int
     */
    public static function default_page_count(): int {
        return self::DEFAULT_PAGE_COUNT;
    }

    /**
     * Return the URL parameter name used to confirm permanent question deletion.
     *
     * @return string
     */
    public static function confirm_delete_param(): string {
        return self::CONFIRM_DELETE_PERMANENTLY;
    }

    /**
     * Return the URL parameter name used to restore a soft-deleted question.
     *
     * @return string
     */
    public static function restore_param(): string {
        return self::RESTORE_PARAM;
    }

    /**
     * Return the HTML-editor options array for use with editors outside Moodle forms.
     *
     * @param \context $context The module context.
     * @return array
     */
    public static function editor_options(\context $context): array {
        return [
            'subdirs'   => 0,
            'maxbytes'  => 0,
            'maxfiles'  => -1,
            'context'   => $context,
            'noclean'   => 0,
            'trusttext' => 0,
        ];
    }

    /**
     * Return a localised label => value map of response-removal duration options for admin form selects.
     *
     * @return array  Keys are seconds (0 = never); values are localised display strings.
     */
    public static function response_removal_options(): array {
        $options = [0 => get_string('removeoldresponsesdefault', 'questionnaire')];
        for ($i = 1; $i <= 36; $i++) {
            $options[$i * 2592000] = $i > 1
                ? get_string('nummonths', 'moodle', $i)
                : get_string('onemonth', 'questionnaire');
        }
        return $options;
    }

    /**
     * Prepare a question object for display in the question editing form.
     *
     * Populates draft file areas and dependency arrays expected by questions_form.
     *
     * @param int $qid   0 when creating a new question; the existing question id when editing.
     * @param int $qtype Question type id (used only when $qid is 0).
     * @return \mod_questionnaire\local\question\question
     */
    public function prep_question_for_form(int $qid, int $qtype): local\question\question {
        $cmid = $this->coursemodule()->id;
        $context = context_module::instance($cmid);
        if ($qid != 0) {
            $questions = $this->questions();
            $question = clone($questions[$qid]);
            $question->qid = $question->id();
            $question->sid = $this->surveyid();
            $question->set_id($cmid);
            $draftideditor = file_get_submitted_draft_itemid('question');
            $content = file_prepare_draft_area(
                $draftideditor,
                $context->id,
                'mod_questionnaire',
                'question',
                $qid,
                ['subdirs' => true],
                $question->content()
            );
            $question->set_content(['text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor]);
            if (isset($question->dependencies)) {
                foreach ($question->dependencies as $dependencies) {
                    if ($dependencies->dependandor === "and") {
                        $question->dependquestionsand[] =
                            $dependencies->dependquestionid . ',' . $dependencies->dependchoiceid;
                        $question->dependlogicand[] = $dependencies->dependlogic;
                    } else if ($dependencies->dependandor === "or") {
                        $question->dependquestionsor[] =
                            $dependencies->dependquestionid . ',' . $dependencies->dependchoiceid;
                        $question->dependlogicor[] = $dependencies->dependlogic;
                    }
                }
            }
        } else {
            $question = local\question\question::question_builder($qtype);
            $question->sid = $this->surveyid();
            $question->set_id($cmid);
            $question->set_typeid($qtype);
            $draftideditor = file_get_submitted_draft_itemid('question');
            $content = file_prepare_draft_area(
                $draftideditor,
                $context->id,
                'mod_questionnaire',
                'question',
                0,
                ['subdirs' => true],
                ''
            );
            $question->set_content(['text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor]);
        }
        return $question;
    }

    /**
     * Return private questionnaires belonging to the given course as a labelled select array.
     *
     * Keys are "private-{survey_id}"; values are popup-preview action-link strings.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_private_questionnaires(int $courseid): array {
        return self::build_survey_select_list(self::get_survey_list('private', $courseid), 'private', 0);
    }

    /**
     * Return public questionnaires from other courses as a labelled select array.
     *
     * Surveys whose courseid matches $courseid are excluded (they are the current course's
     * own public surveys, which would create a circular reference).
     * Keys are "public-{survey_id}"; values are popup-preview action-link strings.
     *
     * @param int $courseid The current course id; questionnaires from this course are excluded.
     * @return array
     */
    public static function get_public_questionnaires(int $courseid): array {
        return self::build_survey_select_list(self::get_survey_list('public', 0), 'public', $courseid);
    }

    /**
     * Return template questionnaires from any course as a labelled select array.
     *
     * Keys are "template-{survey_id}"; values are popup-preview action-link strings.
     *
     * @param int $courseid Passed for consistency; not used for filtering.
     * @return array
     */
    public static function get_template_questionnaires(int $courseid): array {
        return self::build_survey_select_list(self::get_survey_list('template', 0), 'template', 0);
    }

    /**
     * Fetch surveys of the given realm and return them as lightweight questionnaire objects.
     *
     * Uses the survey domain class finders and questionnaire_record persistents — no direct
     * $DB access. Surveys with no linked questionnaire instance are excluded.
     *
     * Returned questionnaire objects are built via from_records() and only expose
     * id(), name(), surveyid(), and survey() accessors safely. Callers must not
     * call coursemodule(), context(), or course() on them.
     *
     * @param string $realm 'private', 'public', or 'template'.
     * @param int $courseid Scope to this course when realm is 'private'; ignored otherwise.
     * @return self[]
     */
    private static function get_survey_list(string $realm, int $courseid): array {
        if ($realm === 'private' && $courseid > 0) {
            $surveys = survey::get_private_for_course($courseid);
        } else {
            $surveys = survey::get_by_realm($realm);
        }

        $items = [];
        foreach ($surveys as $surveyobj) {
            $qrec = local\db\questionnaire_record::get_for_survey($surveyobj->id());
            if ($qrec === null) {
                continue;
            }
            $items[] = self::from_records($qrec, $surveyobj->survey_record());
        }
        return $items;
    }

    /**
     * Format a list of lightweight questionnaire objects as a labelled popup-preview select array.
     *
     * @param self[] $items From get_survey_list() — lightweight from_records() instances.
     * @param string $realm Used as the key prefix (e.g. 'private-42').
     * @param int $excludecourseid Skip items whose survey owning_courseid matches this value
     *     (pass 0 to skip no items). Used by get_public_questionnaires() to omit the current
     *     course's own public surveys.
     * @return array
     */
    private static function build_survey_select_list(array $items, string $realm, int $excludecourseid): array {
        global $OUTPUT, $DB;

        $surveylist = [];
        $strpreview = get_string('preview_questionnaire', 'questionnaire');
        foreach ($items as $questionnaire) {
            $owningcourseid = $questionnaire->survey()->owning_courseid();
            if ($excludecourseid > 0 && $owningcourseid == $excludecourseid) {
                continue;
            }
            $originalcourse = $DB->get_record('course', ['id' => $owningcourseid]);
            if (!$originalcourse) {
                continue;
            }
            $sid = $questionnaire->surveyid();
            $qid = $questionnaire->id();
            $args = "sid={$sid}&popup=1&qid={$qid}";
            $link = new \moodle_url("/mod/questionnaire/preview.php?{$args}");
            $action = new \popup_action('click', $link);
            $label = $OUTPUT->action_link(
                $link,
                $questionnaire->name() . ' [' . $originalcourse->fullname . ']',
                $action,
                ['title' => $strpreview]
            );
            $surveylist[$realm . '-' . $sid] = $label;
        }
        return $surveylist;
    }

    /**
     * Return the configured duration (in seconds) before soft-deleted questions are permanently removed.
     *
     * The value comes from the questionnaire_questiondeletion plugin config. Callers should not need
     * to know the config key — use this method instead.
     *
     * @return string|false  Duration string, or false if not configured.
     */
    public static function question_deletion_duration() {
        return get_config('questionnaire_questiondeletion', 'duration');
    }

    /**
     * Permanently delete a soft-deleted question and all its associated response data.
     *
     * @param int $qid  Question id.
     * @param int $sid  Survey id.
     */
    public static function delete_question_permanently(int $qid, int $sid): void {
        global $DB;
        $select = 'id = :id AND surveyid = :sid AND deleted IS NOT NULL';
        $DB->delete_records_select('questionnaire_question', $select, ['id' => $qid, 'sid' => $sid]);
        $DB->delete_records('questionnaire_response', ['questionnaireid' => $qid]);
        local\response\questionnaire_responses::delete_responses_for_question($qid);
        $DB->delete_records('questionnaire_dependency', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_dependency', ['dependquestionid' => $qid]);
    }

    /**
     * Trigger the question_deleted event for a specific course module context.
     *
     * @param int    $cmid         Course-module id.
     * @param string $questiontype Short type name of the deleted question.
     * @param int    $courseid     Course id.
     */
    public static function trigger_question_deleted_event(int $cmid, string $questiontype, int $courseid): void {
        $context = context_module::instance($cmid);
        $event = \mod_questionnaire\event\question_deleted::create([
            'context'  => $context,
            'courseid' => $courseid,
            'other'    => ['questiontype' => $questiontype],
        ]);
        $event->trigger();
    }

    /**
     * Restore a soft-deleted question, placing it at the end of the survey's question list.
     *
     * @param int $qid  Question id.
     * @param int $sid  Survey id.
     */
    public static function restore_deleted_question(int $qid, int $sid): void {
        global $DB;
        $sql = "SELECT *, (
                        SELECT position + 1
                          FROM {questionnaire_question}
                         WHERE surveyid = ?
                           AND deleted IS NULL
                      ORDER BY position DESC
                         LIMIT 1) as lastposition
                  FROM {questionnaire_question}
                 WHERE id = ?
                   AND surveyid = ?
                   AND deleted IS NOT NULL";
        $question = $DB->get_record_sql($sql, [$sid, $qid, $sid]);
        if ($question) {
            $question->deleted = null;
            $question->position = $question->lastposition ?? 1;
            $DB->update_record('questionnaire_question', $question);
        }
    }

    /**
     * Create a new questionnaire survey record.
     *
     * @param stdClass $sdata  Survey data object (must include courseid).
     * @return int  The new survey id.
     */
    public static function add_survey(stdClass $sdata): int {
        return survey::add_survey($sdata);
    }

    /**
     * Update an existing questionnaire survey record.
     *
     * @param int $sid  Survey id.
     * @param stdClass $sdata  Survey data object.
     * @return int|false  The survey id on success, false on failure.
     */
    public static function update_survey(int $sid, stdClass $sdata): int|false {
        return survey::update_survey($sid, $sdata);
    }

    /**
     * Create an editable copy of a survey, including all questions, choices, dependencies,
     * and feedback sections.
     *
     * @param stdClass $surveydata  The survey record to copy.
     * @param array    $questions   The questions array.
     * @param int      $owner       The courseid that will own the new survey.
     * @return int|false  The new survey id on success, false on failure.
     */
    public static function copy_survey(stdClass $surveydata, array $questions, int $owner): int|false {
        return survey::copy_survey($surveydata, $questions, $owner);
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
                $questions = survey::load_questions_for_survey($copyid);
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

    // Navigation / display / hook methods (delegated from lib.php).

    /**
     * Adds module specific settings to the settings block.
     *
     * @param \settings_navigation $settings The settings navigation object.
     * @param \navigation_node $questionnairenode The node to add module settings to.
     * @return void
     */
    public function extend_settings_navigation(
        \settings_navigation $settings,
        \navigation_node $questionnairenode
    ): void {
        global $DB, $USER, $CFG;

        $individualresponse = optional_param('individualresponse', false, PARAM_INT);
        $rid = optional_param('rid', false, PARAM_INT); // Response id.
        $currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

        $cm = $settings->get_page()->cm;
        $context = $cm->context;
        $cmid = $cm->id;
        $course = $settings->get_page()->course;
        $courseid = $course->id;

        if ($owner = $DB->get_field('questionnaire_survey', 'courseid', ['id' => $this->surveyid()])) {
            $owner = (trim($owner) == trim($courseid));
        } else {
            $owner = true;
        }

        // On view page, currentgroupid is not yet sent as an optional_param, so get it.
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode > 0 && $currentgroupid == 0) {
            $currentgroupid = groups_get_activity_group($this->coursemodule());
            if (!groups_is_member($currentgroupid, $USER->id)) {
                $currentgroupid = 0;
            }
        }

        // We want to add these new nodes after the Edit settings node, and before the
        // Locally assigned roles node. Of course, both of those are controlled by capabilities.
        $keys = $questionnairenode->get_children_key_list();
        $beforekey = null;
        $i = array_search('modedit', $keys);
        if (($i === false) && array_key_exists(0, $keys)) {
            $beforekey = $keys[0];
        } else if (array_key_exists($i + 1, $keys)) {
            $beforekey = $keys[$i + 1];
        }

        if (has_capability('mod/questionnaire:manage', $context) && $owner) {
            $url = '/mod/questionnaire/qsettings.php';
            $node = \navigation_node::create(
                get_string('advancedsettings'),
                new \moodle_url($url, ['id' => $cmid]),
                \navigation_node::TYPE_SETTING,
                null,
                'advancedsettings',
                new \pix_icon('t/edit', '')
            );
            $questionnairenode->add_node($node, $beforekey);
        }

        if (has_capability('mod/questionnaire:editquestions', $context) && $owner) {
            $url = '/mod/questionnaire/questions.php';
            $node = \navigation_node::create(
                get_string('questions', 'questionnaire'),
                new \moodle_url($url, ['id' => $cmid]),
                \navigation_node::TYPE_SETTING,
                null,
                'questions',
                new \pix_icon('t/edit', '')
            );
            $questionnairenode->add_node($node, $beforekey);
        }

        if (has_capability('mod/questionnaire:editquestions', $context) && $owner) {
            $url = '/mod/questionnaire/feedback.php';
            $node = \navigation_node::create(
                get_string('feedback', 'questionnaire'),
                new \moodle_url($url, ['id' => $cmid]),
                \navigation_node::TYPE_SETTING,
                null,
                'feedback',
                new \pix_icon('t/edit', '')
            );
            $questionnairenode->add_node($node, $beforekey);
        }

        if (has_capability('mod/questionnaire:preview', $context)) {
            $url = '/mod/questionnaire/preview.php';
            $node = \navigation_node::create(
                get_string('preview_label', 'questionnaire'),
                new \moodle_url($url, ['id' => $cmid]),
                \navigation_node::TYPE_SETTING,
                null,
                'preview',
                new \pix_icon('t/preview', '')
            );
            $questionnairenode->add_node($node, $beforekey);
        }

        if ($this->user_can_take($USER->id)) {
            $url = '/mod/questionnaire/complete.php';
            if ($this->user_has_saved_response($USER->id)) {
                $args = ['id' => $cmid, 'resume' => 1];
                $text = get_string('resumesurvey', 'questionnaire');
            } else {
                $args = ['id' => $cmid];
                $text = get_string('answerquestions', 'questionnaire');
            }
            $node = \navigation_node::create(
                $text,
                new \moodle_url($url, $args),
                \navigation_node::TYPE_SETTING,
                null,
                '',
                new \pix_icon('i/info', 'answerquestions')
            );
            $questionnairenode->add_node($node, $beforekey);
        }
        $usernumresp = $this->count_submissions($USER->id);

        if ($this->can_read_own_responses() && ($usernumresp > 0)) {
            $url = '/mod/questionnaire/myreport.php';

            if ($usernumresp > 1) {
                $urlargs = [
                    'instance' => $this->id(),
                    'userid' => $USER->id,
                    'byresponse' => 0,
                    'action' => 'summary',
                    'group' => $currentgroupid,
                ];
                $node = \navigation_node::create(
                    get_string('yourresponses', 'questionnaire'),
                    new \moodle_url($url, $urlargs),
                    \navigation_node::TYPE_SETTING,
                    null,
                    'yourresponses'
                );
                $myreportnode = $questionnairenode->add_node($node, $beforekey);

                $urlargs = [
                    'instance' => $this->id(),
                    'userid' => $USER->id,
                    'byresponse' => 0,
                    'action' => 'summary',
                    'group' => $currentgroupid,
                ];
                $myreportnode->add(get_string('summary', 'questionnaire'), new \moodle_url($url, $urlargs));

                $urlargs = [
                    'instance' => $this->id(),
                    'userid' => $USER->id,
                    'byresponse' => 1,
                    'action' => 'vresp',
                    'group' => $currentgroupid,
                ];
                $byresponsenode = $myreportnode->add(
                    get_string('viewindividualresponse', 'questionnaire'),
                    new \moodle_url($url, $urlargs)
                );

                $urlargs = [
                    'instance' => $this->id(),
                    'userid' => $USER->id,
                    'byresponse' => 0,
                    'action' => 'vall',
                    'group' => $currentgroupid,
                ];
                $myreportnode->add(get_string('myresponses', 'questionnaire'), new \moodle_url($url, $urlargs));
                if ($this->can_download_responses()) {
                    $urlargs = [
                        'instance' => $this->id(),
                        'user' => $USER->id,
                        'action' => 'dwnpg',
                        'group' => $currentgroupid,
                    ];
                    $myreportnode->add(
                        get_string('downloadtextformat', 'questionnaire'),
                        new \moodle_url(
                            '/mod/questionnaire/report.php',
                            $urlargs
                        )
                    );
                }
            } else {
                $urlargs = [
                    'instance' => $this->id(),
                    'userid' => $USER->id,
                    'byresponse' => 1,
                    'action' => 'vresp',
                    'group' => $currentgroupid,
                ];
                $node = \navigation_node::create(
                    get_string('yourresponse', 'questionnaire'),
                    new \moodle_url(
                        $url,
                        $urlargs
                    ),
                    \navigation_node::TYPE_SETTING,
                    null,
                    'yourresponse'
                );
                $myreportnode = $questionnairenode->add_node($node, $beforekey);
            }
        }

        // If questionnaire is set to separate groups, prevent user who is not member of any group
        // and is not a non-editing teacher to view All responses.
        if ($this->can_view_all_responses($usernumresp)) {
            $url = '/mod/questionnaire/report.php';
            $node = \navigation_node::create(
                get_string('viewallresponses', 'questionnaire'),
                new \moodle_url(
                    $url,
                    ['instance' => $this->id(), 'action' => 'vall']
                ),
                \navigation_node::TYPE_SETTING,
                null,
                'vall'
            );
            $reportnode = $questionnairenode->add_node($node, $beforekey);

            if ($this->can_view_single_response()) {
                $summarynode = $reportnode->add(
                    get_string('summary', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $this->id(), 'action' => 'vall']
                    )
                );
            } else {
                $summarynode = $reportnode;
            }
            $summarynode->add(
                get_string('order_default', 'questionnaire'),
                new \moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $this->id(), 'action' => 'vall', 'group' => $currentgroupid]
                )
            );
            $summarynode->add(
                get_string('order_ascending', 'questionnaire'),
                new \moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $this->id(), 'action' => 'vallasort', 'group' => $currentgroupid]
                )
            );
            $summarynode->add(
                get_string('order_descending', 'questionnaire'),
                new \moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $this->id(), 'action' => 'vallarsort', 'group' => $currentgroupid]
                )
            );

            if ($this->can_delete_responses()) {
                $summarynode->add(
                    get_string('deleteallresponses', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $this->id(), 'action' => 'delallresp', 'group' => $currentgroupid]
                    )
                );
            }

            if ($this->can_download_responses()) {
                $summarynode->add(
                    get_string('downloadtextformat', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $this->id(), 'action' => 'dwnpg', 'group' => $currentgroupid]
                    )
                );
            }
            if ($this->can_view_single_response()) {
                $byresponsenode = $reportnode->add(
                    get_string('viewbyresponse', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $this->id(), 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid]
                    )
                );

                $byresponsenode->add(
                    get_string('view', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $this->id(), 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid]
                    )
                );

                if ($individualresponse) {
                    $byresponsenode->add(
                        get_string('deleteresp', 'questionnaire'),
                        new \moodle_url(
                            '/mod/questionnaire/report.php',
                            [
                                'instance' => $this->id(),
                                'action' => 'dresp',
                                'byresponse' => 1,
                                'rid' => $rid,
                                'group' => $currentgroupid,
                                'individualresponse' => 1,
                            ]
                        )
                    );
                }
            }
        }

        $canviewgroups = true;
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == 1) {
            $canviewgroups = groups_has_membership($cm, $USER->id);
        }
        $canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
        if ($this->can_view_single_response() && ($canviewallgroups || $canviewgroups)) {
            $url = '/mod/questionnaire/show_nonrespondents.php';
            $node = \navigation_node::create(
                get_string('show_nonrespondents', 'questionnaire'),
                new \moodle_url($url, ['id' => $cmid]),
                \navigation_node::TYPE_SETTING,
                null,
                'nonrespondents'
            );
            $questionnairenode->add_node($node, $beforekey);
        }
    }

    /**
     * Collects questionnaire activity since $timestart for the recent activity block.
     *
     * @param array $activities Accumulated list of activities; new ones are appended.
     * @param int $index Current array index; incremented for each activity appended.
     * @param int $timestart Unix timestamp — only activity after this is included.
     * @param int $courseid Course to search within.
     * @param int $cmid Course-module id of the questionnaire.
     * @param int $userid Restrict to a single user (0 = all users).
     * @param int $groupid Restrict to a group (0 = all groups).
     * @return void
     */
    public static function get_recent_mod_activity(
        array &$activities,
        int &$index,
        int $timestart,
        int $courseid,
        int $cmid,
        int $userid = 0,
        int $groupid = 0
    ): void {
        global $CFG, $COURSE, $USER, $DB;
        require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
        require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

        if ($COURSE->id == $courseid) {
            $course = $COURSE;
        } else {
            $course = $DB->get_record('course', ['id' => $courseid]);
        }

        $modinfo = get_fast_modinfo($course);

        $cm = $modinfo->cms[$cmid];
        $questionnaire = $DB->get_record('questionnaire', ['id' => $cm->instance]);
        $questionnaire = new \questionnaire($course, $cm, 0, $questionnaire);

        $context = \context_module::instance($cm->id);
        $grader = has_capability('mod/questionnaire:viewsingleresponse', $context);

        // If this is a copy of a public questionnaire whose original is located in another course,
        // current user (teacher) cannot view responses.
        if ($grader) {
            // For a public questionnaire, look for the original public questionnaire that it is based on.
            if (!$questionnaire->survey_is_public_master()) {
                // For a public questionnaire, look for the original public questionnaire that it is based on.
                $originalquestionnaire = $DB->get_record(
                    'questionnaire',
                    ['sid' => $questionnaire->survey->id, 'course' => $questionnaire->survey->courseid]
                );
                $cmoriginal = get_coursemodule_from_instance(
                    "questionnaire",
                    $originalquestionnaire->id,
                    $questionnaire->survey->courseid
                );
                $contextoriginal = \context_course::instance($questionnaire->survey->courseid, MUST_EXIST);
                if (!has_capability('mod/questionnaire:viewsingleresponse', $contextoriginal)) {
                    $tmpactivity = new stdClass();
                    $tmpactivity->type = 'questionnaire';
                    $tmpactivity->cmid = $cm->id;
                    $tmpactivity->cannotview = true;
                    $tmpactivity->anonymous = false;
                    $activities[$index++] = $tmpactivity;
                    return;
                }
            }
        }

        $params = [];
        if ($userid) {
            $userselect = "AND u.id = :userid";
            $params['userid'] = $userid;
        } else {
            $userselect = '';
        }

        if ($groupid) {
            $groupselect = 'AND gm.groupid = :groupid';
            $groupjoin = 'JOIN {groups_members} gm ON  gm.userid=u.id';
            $params['groupid'] = $groupid;
        } else {
            $groupselect = '';
            $groupjoin = '';
        }

        $params['timestart'] = $timestart;
        $params['questionnaireid'] = $questionnaire->id;

        $userfieldsapi = \core_user\fields::for_userpic();
        $ufields = $userfieldsapi->get_sql('u', false, '', 'useridagain', false)->selects;
        if (
            !$attempts = $DB->get_records_sql(
                "
                        SELECT qr.*,
                        {$ufields}
                        FROM {questionnaire_response} qr
                        JOIN {user} u ON u.id = qr.userid
                        $groupjoin
                        WHERE qr.submitted > :timestart
                        AND qr.questionnaireid = :questionnaireid
                        $userselect
                        $groupselect
                        ORDER BY qr.submitted ASC",
                $params
            )
        ) {
            return;
        }

        $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
        $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
        $groupmode = groups_get_activity_groupmode($cm, $course);

        $usersgroups = null;
        $aname = format_string($cm->name, true);
        $userattempts = [];
        foreach ($attempts as $attempt) {
            if ($questionnaire->respondenttype != 'anonymous') {
                if (!isset($userattempts[$attempt->lastname])) {
                    $userattempts[$attempt->lastname] = 1;
                } else {
                    $userattempts[$attempt->lastname]++;
                }
            }
            if ($attempt->userid != $USER->id) {
                if (!$grader) {
                    // View complete individual responses permission required.
                    continue;
                }

                if (($groupmode == SEPARATEGROUPS) && !$accessallgroups) {
                    if ($usersgroups === null) {
                        $usersgroups = groups_get_all_groups(
                            $course->id,
                            $attempt->userid,
                            $cm->groupingid
                        );
                        if (is_array($usersgroups)) {
                            $usersgroups = array_keys($usersgroups);
                        } else {
                             $usersgroups = [];
                        }
                    }
                    if (!array_intersect($usersgroups, $modinfo->groups[$cm->id])) {
                        continue;
                    }
                }
            }

            $tmpactivity = new stdClass();

            $tmpactivity->type = 'questionnaire';
            $tmpactivity->cmid = $cm->id;
            $tmpactivity->cminstance = $cm->instance;
            // Current user is admin - or teacher enrolled in original public course.
            if (isset($cmoriginal)) {
                $tmpactivity->cminstance = $cmoriginal->instance;
            }
            $tmpactivity->cannotview = false;
            $tmpactivity->anonymous = false;
            $tmpactivity->name = $aname;
            $tmpactivity->sectionnum = $cm->sectionnum;
            $tmpactivity->timestamp = $attempt->submitted;
            $tmpactivity->groupid = $groupid;
            if (isset($userattempts[$attempt->lastname])) {
                $tmpactivity->nbattempts = $userattempts[$attempt->lastname];
            }

            $tmpactivity->content = new stdClass();
            $tmpactivity->content->attemptid = $attempt->id;

            $userfieldsapi = \core_user\fields::for_userpic();
            $allnamefields = $userfieldsapi->get_sql('', false, '', '', false)->selects;
            $selects = str_replace(', ', ',', $allnamefields);
            $userfields = explode(',', $selects);
            $tmpactivity->user = new stdClass();
            foreach ($userfields as $userfield) {
                if ($userfield == 'id') {
                    $tmpactivity->user->{$userfield} = $attempt->userid;
                } else {
                    if (!empty($attempt->{$userfield})) {
                        $tmpactivity->user->{$userfield} = $attempt->{$userfield};
                    } else {
                        $tmpactivity->user->{$userfield} = null;
                    }
                }
            }
            if ($questionnaire->respondenttype != 'anonymous') {
                $tmpactivity->user->fullname = fullname($attempt, $viewfullnames);
            } else {
                $tmpactivity->user = '';
                unset($tmpactivity->user);
                $tmpactivity->anonymous = true;
            }
            $activities[$index++] = $tmpactivity;
        }
    }

    /**
     * Prints all users who have completed a specified questionnaire since a given time.
     *
     * @param object $activity Activity object from get_recent_mod_activity.
     * @param int $courseid Course id.
     * @param string $detail Not used but needed for compatibility.
     * @param array $modnames Module names array.
     * @return void Output is echoed.
     */
    public static function print_recent_mod_activity(
        object $activity,
        int $courseid,
        string $detail,
        array $modnames
    ): void {
        global $OUTPUT;

        // If the questionnaire is "anonymous", then $activity->user won't have been set, so do not display respondent info.
        if ($activity->anonymous) {
            $stranonymous = ' (' . get_string('anonymous', 'questionnaire') . ')';
            $activity->nbattempts = '';
        } else {
            $stranonymous = '';
        }
        // Current user cannot view responses to public questionnaire.
        if ($activity->cannotview) {
            $strcannotview = get_string('cannotviewpublicresponses', 'questionnaire');
        }
        echo \html_writer::start_tag('div');
        echo \html_writer::start_tag(
            'span',
            [
                'class' => 'clearfix',
                'style' => 'margin-top:0px; background-color: white; display: inline-block;',
            ]
        );

        if (!$activity->anonymous && !$activity->cannotview) {
            echo \html_writer::tag(
                'div',
                $OUTPUT->user_picture($activity->user, ['courseid' => $courseid]),
                ['style' => 'float: left; padding-right: 10px;']
            );
        }
        if (!$activity->cannotview) {
            echo \html_writer::start_tag('div');
            echo \html_writer::start_tag('div');

            $urlparams = [
                'action' => 'vresp',
                'instance' => $activity->cminstance,
                'group' => $activity->groupid,
                'rid' => $activity->content->attemptid,
                'individualresponse' => 1,
            ];

            $context = \context_module::instance($activity->cmid);
            if (has_capability('mod/questionnaire:viewsingleresponse', $context)) {
                $report = 'report.php';
            } else {
                $report = 'myreport.php';
            }
            echo \html_writer::tag(
                'a',
                get_string('response', 'questionnaire') .  ' ' . $activity->nbattempts . $stranonymous,
                ['href' => new \moodle_url('/mod/questionnaire/' . $report, $urlparams)]
            );
            echo \html_writer::end_tag('div');
        } else {
            echo \html_writer::start_tag('div');
            echo \html_writer::start_tag('div');
            echo \html_writer::tag('div', $strcannotview);
            echo \html_writer::end_tag('div');
        }
        if (!$activity->anonymous  && !$activity->cannotview) {
            $url = new \moodle_url('/user/view.php', ['course' => $courseid, 'id' => $activity->user->id]);
            $name = $activity->user->fullname;
            $link = \html_writer::link($url, $name);
            echo \html_writer::start_tag('div', ['class' => 'user']);
            echo $link . ' - ' . userdate($activity->timestamp);
            echo \html_writer::end_tag('div');
        }

        echo \html_writer::end_tag('div');
        echo \html_writer::end_tag('span');
        echo \html_writer::end_tag('div');
    }

    /**
     * Called after the activity and module have been created. Copies file areas if the questionnaire
     * was created from another questionnaire survey.
     *
     * @param object $data Form data including coursemodule and optional copyid.
     * @param object $course Course record.
     * @return object The data object (unchanged).
     */
    public static function coursemodule_edit_post_actions(object $data, object $course): object {
        global $DB;
        require_once(dirname(__DIR__) . '/questionnaire.class.php');

        if (!empty($data->copyid)) {
            $cm = (object)['id' => $data->coursemodule];
            $questionnaire = new \questionnaire($course, $cm, 0, $data);
            $oldquestionnaireid = $DB->get_field('questionnaire', 'id', ['sid' => $data->copyid]);
            $oldcm = get_coursemodule_from_instance('questionnaire', $oldquestionnaireid);
            $oldquestionnaire = new \questionnaire($course, $oldcm, $oldquestionnaireid, null);
            $oldcontext = \context_module::instance($oldcm->id);
            $newcontext = \context_module::instance($data->coursemodule);
            $areas = $questionnaire->get_all_file_areas();
            $oldareas = $oldquestionnaire->get_all_file_areas();
            $fs = new \mod_questionnaire\file_storage();
            foreach ($areas as $area => $ids) {
                if (is_array($ids)) {
                    $oldid = current($oldareas[$area]);
                    foreach ($ids as $id) {
                        $fs->copy_area_files_to_new_context(
                            $oldcontext->id,
                            $newcontext->id,
                            'mod_questionnaire',
                            $area,
                            $oldid,
                            $id
                        );
                        $oldid = next($oldareas[$area]);
                    }
                } else {
                    $fs->copy_area_files_to_new_context(
                        $oldcontext->id,
                        $newcontext->id,
                        'mod_questionnaire',
                        $area,
                        $oldareas[$area],
                        $ids
                    );
                }
            }
        }

        return $data;
    }

    // Instance lifecycle methods (delegated from lib.php).

    /**
     * Delete a questionnaire instance and its survey data (if survey owned by this course).
     *
     * @param int $id Questionnaire instance id.
     * @return bool True on success.
     */
    public static function delete_instance(int $id): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
        require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

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

        if (!$DB->delete_records('questionnaire', ['id' => $questionnaire->id])) {
            $result = false;
        }

        if ($survey = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->sid])) {
            // If this survey is owned by this course, delete all of the survey records and responses.
            if ($survey->courseid == $questionnaire->course) {
                $result = $result && \questionnaire::delete_survey($questionnaire->sid, $questionnaire->id);
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
        require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
        require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

        $componentstr = get_string('modulenameplural', 'questionnaire');
        $status = [];

        if (!empty($data->reset_questionnaire)) {
            $surveys = \questionnaire::get_survey_list($data->courseid, '');

            // Delete responses.
            foreach ($surveys as $survey) {
                // Get all responses for this questionnaire.
                $sql = "SELECT qr.id, qr.questionnaireid, qr.submitted, qr.userid, q.sid
                     FROM {questionnaire} q
                     INNER JOIN {questionnaire_response} qr ON q.id = qr.questionnaireid
                     WHERE q.sid = ?
                     ORDER BY qr.id";
                $resps = $DB->get_records_sql($sql, [$survey->id]);
                if (!empty($resps)) {
                    $questrecord = $DB->get_record(
                        "questionnaire",
                        ["sid" => $survey->id, "course" => $survey->courseid]
                    );
                    $questcourse = $DB->get_record("course", ["id" => $questrecord->course]);
                    $questcm = get_coursemodule_from_instance("questionnaire", $questrecord->id, $questcourse->id);
                    $questobj = new \questionnaire($questcourse, $questcm, 0, $questrecord);
                    foreach ($resps as $response) {
                        $questobj->responses()->delete_response($response);
                    }
                }
                // Remove this questionnaire's grades (and feedback) from gradebook (if any).
                $select = "itemmodule = 'questionnaire' AND iteminstance = " . $survey->qid;
                $fields = 'id';
                if ($itemid = $DB->get_record_select('grade_items', $select, null, $fields)) {
                    $itemid = $itemid->id;
                    $DB->delete_records_select('grade_grades', 'itemid = ' . $itemid);
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

    // Gradebook methods (delegated from lib.php).

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

    // Response-flow and utility methods.

    /**
     * Return a new questionnaire_responses handler for this questionnaire.
     *
     * @return questionnaire_responses
     */
    public function responses(): questionnaire_responses {
        return new questionnaire_responses($this);
    }

    /**
     * Return all responses for this questionnaire, optionally filtered by user or group.
     *
     * @param int|false $userid  Limit to this user, or false for all users.
     * @param int $groupid       Limit to this group (0 = no filter).
     * @return array
     */
    public function get_responses($userid = false, int $groupid = 0): array {
        return $this->responses()->get_responses($userid, $groupid);
    }

    /**
     * Return the most recent incomplete response id for the given user, or 0 if none.
     *
     * @param int $userid
     * @return int
     */
    public function get_latest_responseid(int $userid): int {
        return response::latest_incomplete($this->id(), $userid)?->id() ?? 0;
    }

    /**
     * Delete the current response section and insert a fresh one; return the new response id.
     *
     * @param stdClass $response Response data object (must have ->rid and ->sec).
     * @param int $userid
     * @return int New response id.
     */
    public function existing_response_action($response, int $userid): int {
        $responses = $this->responses();
        $responses->response_delete($response->rid, $response->sec);
        return $responses->response_insert($response, $userid);
    }

    /**
     * Validate and save the current page, then return the next page number (or an error string).
     *
     * @param stdClass $response Response data object.
     * @param int $userid
     * @return int|bool|string Next section number, false if no next page, or error message string.
     */
    public function next_page_action($response, int $userid): int|bool|string {
        $msg = $this->responses()->response_check_format($response->sec, $response, true, true, $this->survey->questions());
        if (empty($msg)) {
            $response->rid = $this->existing_response_action($response, $userid);
            return $this->next_page($response->sec, $response->rid);
        }
        return $msg;
    }

    /**
     * Save the current page and return the previous page number.
     *
     * @param stdClass $response Response data object.
     * @param int $userid
     * @return int|bool Previous section number, or false if none.
     */
    public function previous_page_action($response, int $userid): int|bool {
        $response->rid = $this->existing_response_action($response, $userid);
        return $this->prev_page($response->sec, $response->rid);
    }

    /**
     * Process a mobile-app submission and return a result array.
     *
     * Handles next/previous page navigation and final submission from the mobile app.
     * All response persistence is delegated to the questionnaire_responses handler.
     *
     * @param int $userid
     * @param int $sec Section (page) number.
     * @param int $completed Whether the questionnaire has already been completed (1/0).
     * @param int $rid Existing in-progress response id (0 if none).
     * @param int $submit Whether the user is submitting (1/0).
     * @param string $action 'nextpage', 'previouspage', or empty for a plain save.
     * @param array $responses Key/value response data from the app.
     * @return array Result array; may contain 'warnings', 'nextpagenum', or 'response' keys.
     */
    public function save_mobile_data(
        int $userid,
        int $sec,
        int $completed,
        int $rid,
        int $submit,
        string $action,
        array $responses
    ): array {
        $ret = [];
        $response = $this->responses()->build_response_from_appdata((object)$responses, $sec, $rid);
        $response->sec = $sec;
        $response->rid = $rid;

        if ($action == 'nextpage') {
            $result = $this->next_page_action($response, $userid);
            if (is_string($result)) {
                $ret['warnings'] = $result;
            } else {
                $ret['nextpagenum'] = $result;
            }
        } else if ($action == 'previouspage') {
            $ret['nextpagenum'] = $this->previous_page_action($response, $userid);
        } else if (!$completed) {
            // If reviewing a completed questionnaire, don't insert a response.
            $msg = $this->responses()->response_check_format(
                $response->sec,
                $response,
                true,
                true,
                $this->survey->questions()
            );
            if (empty($msg)) {
                $rid = $this->responses()->response_insert($response, $userid);
            } else {
                $ret['warnings'] = $msg;
                $ret['response'] = $response;
            }
        }

        if ($submit && (!isset($ret['warnings']) || empty($ret['warnings']))) {
            $this->responses()->commit_submission_response($rid, $userid);
        }
        return $ret;
    }

    /**
     * Return users who should be notified of a new submission by $userid.
     *
     * In SEPARATEGROUPS mode only users who share a group with $userid are notified.
     * Users with no group are notified only if the submitter also has no group.
     *
     * @param int $userid Submitting user id.
     * @return array Indexed by user id.
     */
    public function get_notifiable_users(int $userid): array {
        // Potential users should be active users only.
        $potentialusers = get_enrolled_users(
            $this->context,
            'mod/questionnaire:submissionnotification',
            null,
            'u.*',
            null,
            null,
            null,
            true
        );

        $notifiableusers = [];
        if (groups_get_activity_groupmode($this->coursemodule) == SEPARATEGROUPS) {
            if ($groups = groups_get_all_groups($this->course()->id, $userid, $this->coursemodule->groupingid)) {
                foreach ($groups as $group) {
                    foreach ($potentialusers as $potentialuser) {
                        if ($potentialuser->id == $userid) {
                            // Do not send self.
                            continue;
                        }
                        if (groups_is_member($group->id, $potentialuser->id)) {
                            $notifiableusers[$potentialuser->id] = $potentialuser;
                        }
                    }
                }
            } else {
                // User not in group, try to find graders without group.
                foreach ($potentialusers as $potentialuser) {
                    if ($potentialuser->id == $userid) {
                        // Do not send self.
                        continue;
                    }
                    if (!groups_has_membership($this->coursemodule, $potentialuser->id)) {
                        $notifiableusers[$potentialuser->id] = $potentialuser;
                    }
                }
            }
        } else {
            foreach ($potentialusers as $potentialuser) {
                if ($potentialuser->id == $userid) {
                    // Do not send self.
                    continue;
                }
                $notifiableusers[$potentialuser->id] = $potentialuser;
            }
        }
        return $notifiableusers;
    }

    /**
     * Return an array describing all file areas used by this questionnaire's survey.
     *
     * @return array Keys are area names; values are either a single id or an array of ids.
     */
    public function get_all_file_areas(): array {
        return $this->survey->get_all_file_areas();
    }

    // Completion flow methods.
    // TODO: The print_survey(), submission_notify(), response_goto_thankyou(), and legacy() methods
    // below are temporary shims that delegate to the legacy questionnaire class. They exist only
    // until print_survey() and the surrounding rendering layer are refactored as part of the
    // rendering overhaul. Once that work is done, the legacy() helper and all three shims are
    // removed and questionnaire.class.php is no longer instantiated from this class.

    /**
     * Render the questionnaire completion page and handle form submission.
     *
     * Displays the survey form via the legacy print_survey() shim, then on a valid
     * "Submit Survey" POST commits the response, notifies subscribers, and redirects
     * to the thank-you screen.
     *
     * @return void
     */
    public function view(): void {
        global $USER;

        $message = $this->user_access_messages($USER->id, true);
        if ($message !== null) {
            $this->page->add_to_page('notifications', $message);
        } else {
            $quser = $USER->id;
            $msg = $this->print_survey($quser, $USER->id);

            $viewform = data_submitted();
            if (
                $viewform && confirm_sesskey() &&
                isset($viewform->submit) && isset($viewform->submittype) &&
                ($viewform->submittype == "Submit Survey") && empty($msg)
            ) {
                if (!empty($viewform->rid)) {
                    $viewform->rid = (int)$viewform->rid;
                }
                if (!empty($viewform->sec)) {
                    $viewform->sec = (int)$viewform->sec;
                }
                $rid = $this->existing_response_action($viewform, $quser);
                $this->responses()->commit_submission_response($rid, $quser);
                $this->submission_notify($rid);
                $this->response_goto_thankyou();
            }
        }
    }

    /**
     * Render the survey page(s) for completion.
     *
     * Shim — delegates to the legacy questionnaire class until print_survey() is
     * refactored as part of the rendering overhaul.
     *
     * @param int $quser
     * @param int|false $userid
     * @return string Error message string, or empty string on success.
     */
    public function print_survey(int $quser, $userid = false): ?string {
        return $this->legacy()->print_survey($quser, $userid);
    }

    /**
     * Render the survey for printing or preview display.
     *
     * Shim — delegates to the legacy questionnaire class until the print/preview
     * rendering is refactored.
     *
     * @param int $courseid
     * @param string $message
     * @param string $referer
     * @param int $rid
     * @param bool $blankquestionnaire
     * @return false|void
     */
    public function survey_print_render($courseid, $message = '', $referer = '', $rid = 0, $blankquestionnaire = false) {
        $this->legacy()->page = $this->page;
        return $this->legacy()->survey_print_render($courseid, $message, $referer, $rid, $blankquestionnaire);
    }

    /**
     * Notify subscribers that a response has been submitted.
     *
     * Shim — delegates to the legacy questionnaire class until submission
     * notifications are refactored.
     *
     * @param int $rid The response id.
     * @return bool
     */
    public function submission_notify(int $rid): bool {
        return $this->legacy()->submission_notify($rid);
    }

    /**
     * Redirect or render the thank-you screen after a submission.
     *
     * Shim — delegates to the legacy questionnaire class until the rendering
     * overhaul covers the thank-you flow.
     *
     * @return void
     */
    public function response_goto_thankyou(): void {
        $this->legacy()->page = $this->page;
        $this->legacy()->response_goto_thankyou();
    }

    /**
     * Return true if the current user has site-level access to all groups.
     *
     * @return bool
     */
    public function can_view_all_groups(): bool {
        return has_capability('moodle/site:accessallgroups', $this->context);
    }

    /**
     * Render the aggregate survey results for a set of response ids.
     *
     * Shim — delegates to the legacy questionnaire class until the results
     * rendering is refactored.
     *
     * @param array|string $rids Response ids to summarise.
     * @param int|false $uid  Restrict to this user id, or false for all.
     * @param bool $pdf       True if rendering for PDF output.
     * @param string $currentgroupid  Current group id string.
     * @param string $sort    Sort order string.
     * @return void
     */
    public function survey_results(
        $rids = '',
        $uid = false,
        bool $pdf = false,
        string $currentgroupid = '',
        string $sort = ''
    ): void {
        global $questionnaire;
        $legacy = $this->legacy();
        $legacy->page = $this->page;
        $prev = $questionnaire;
        $questionnaire = $legacy;
        try {
            $legacy->survey_results($rids, $uid, $pdf, $currentgroupid, $sort);
        } finally {
            $questionnaire = $prev;
        }
    }

    /**
     * Load all responses for the given user into the legacy instance's response store.
     *
     * Shim — delegates to the legacy questionnaire class until response loading
     * is refactored onto the new domain objects.
     *
     * @param int|null $userid Load responses for this user, or null for all users.
     * @return void
     */
    public function add_user_responses(?int $userid = null): void {
        $this->legacy()->add_user_responses($userid);
    }

    /**
     * Render all loaded responses for display.
     *
     * Shim — delegates to the legacy questionnaire class until the results
     * rendering is refactored.
     *
     * @return void
     */
    public function view_all_responses(): void {
        $legacy = $this->legacy();
        $legacy->page = $this->page;
        $legacy->view_all_responses();
    }

    /**
     * Render the student response navigation bar for myreport/report pages.
     *
     * Shim — delegates to the legacy questionnaire class until the navigation
     * rendering is refactored.
     *
     * @param int $currrid      Currently displayed response id.
     * @param int $userid       User whose responses are being navigated.
     * @param int $instance     Questionnaire instance id (for URL construction).
     * @param array $resps      All responses to navigate across.
     * @param string $reporttype 'myreport' or 'report'.
     * @param string $sid       Survey id (used in report mode URLs).
     * @return void
     */
    public function survey_results_navbar_student(
        int $currrid,
        int $userid,
        int $instance,
        array $resps,
        string $reporttype = 'myreport',
        string $sid = ''
    ): void {
        $legacy = $this->legacy();
        $legacy->page = $this->page;
        $legacy->survey_results_navbar_student($currrid, $userid, $instance, $resps, $reporttype, $sid);
    }

    /**
     * Render a single saved response, optionally with feedback/comparison data.
     *
     * Shim — delegates to the legacy questionnaire class until the response
     * display is refactored.
     *
     * @param int $rid             Response id to display.
     * @param string $referer      'print' suppresses feedback rendering.
     * @param array|string $resps  Responses used for comparison/feedback.
     * @param bool $compare        True if showing group-comparison feedback.
     * @param bool $isgroupmember  True if the viewer is in the comparison group.
     * @param bool $allresponses   True when all responses are included.
     * @param int $currentgroupid  Active group id (0 = all participants).
     * @param string $outputtarget 'html' or 'pdf'.
     * @return void
     */
    public function view_response(
        int $rid,
        string $referer = '',
        $resps = '',
        bool $compare = false,
        bool $isgroupmember = false,
        bool $allresponses = false,
        int $currentgroupid = 0,
        string $outputtarget = 'html'
    ): void {
        $legacy = $this->legacy();
        $legacy->page = $this->page;
        $legacy->view_response($rid, $referer, $resps, $compare, $isgroupmember, $allresponses, $currentgroupid, $outputtarget);
    }

    /**
     * Render the alphabetical response navigation bar for the report page.
     *
     * Shim — delegates to the legacy questionnaire class until the navigation
     * rendering is refactored.
     *
     * @param int $currrid       Currently displayed response id.
     * @param int $currentgroupid Active group id.
     * @param stdClass $cm       Course module object.
     * @param bool $byresponse   True when navigating by individual response.
     * @return void
     */
    public function survey_results_navbar_alpha(int $currrid, int $currentgroupid, stdClass $cm, bool $byresponse): void {
        $legacy = $this->legacy();
        $legacy->page = $this->page;
        $legacy->survey_results_navbar_alpha($currrid, $currentgroupid, $cm, $byresponse);
    }

    /**
     * Analyse responses and return any feedback messages.
     *
     * Shim — delegates to the legacy questionnaire class until response analysis
     * is refactored.
     *
     * @param int $rid            Response id (0 for all).
     * @param array|string $resps Responses to analyse.
     * @param bool $compare       True when comparing with group.
     * @param bool $isgroupmember True when viewer is a group member.
     * @param bool $allresponses  True when all responses are included.
     * @param int $currentgroupid Active group id.
     * @return array Feedback message strings.
     */
    public function response_analysis(
        int $rid,
        $resps,
        bool $compare,
        bool $isgroupmember,
        bool $allresponses,
        int $currentgroupid
    ) {
        return $this->legacy()->response_analysis($rid, $resps, $compare, $isgroupmember, $allresponses, $currentgroupid);
    }

    /**
     * Generate CSV export data for all (or filtered) responses.
     *
     * Shim — delegates to the legacy questionnaire class until CSV generation
     * is refactored.
     *
     * @param int $currentgroupid Group id filter (0 = all).
     * @param string $rid         Response id filter ('' = all).
     * @param int|string $userid  User id filter ('' = all).
     * @param int|null $choicecodes Include choice codes column.
     * @param int $choicetext     Include choice text column.
     * @param int $showincompletes Include incomplete responses.
     * @param int $rankaverages   Include rank averages.
     * @return array Rows of CSV data (row 0 = column headers).
     */
    public function generate_csv(
        int $currentgroupid = 0,
        string $rid = '',
        $userid = '',
        ?int $choicecodes = null,
        int $choicetext = 1,
        int $showincompletes = 0,
        int $rankaverages = 0
    ): array {
        return $this->legacy()->generate_csv(
            $currentgroupid,
            $rid,
            $userid,
            $choicecodes,
            $choicetext,
            $showincompletes,
            $rankaverages
        );
    }

    /**
     * Return a lazy-loaded legacy questionnaire instance sharing this object's renderer and page.
     *
     * Used only by rendering shims until the legacy class is fully replaced.
     *
     * @return \questionnaire
     */
    private function legacy(): \questionnaire {
        global $CFG, $DB;
        if (!isset($this->legacyinstance)) {
            require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');
            $record = $DB->get_record('questionnaire', ['id' => $this->id()], '*', MUST_EXIST);
            $course = $this->course();
            $cm = $this->coursemodule();
            $this->legacyinstance = new \questionnaire($course, $cm, 0, $record);
            $this->legacyinstance->renderer = $this->renderer;
            $this->legacyinstance->page = $this->page;
        }
        return $this->legacyinstance;
    }
}
