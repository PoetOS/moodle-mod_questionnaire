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

use cm_info;
use mod_questionnaire\local\db\dependency_record;
use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\question\question;
use mod_questionnaire\local\question_navigator;
use mod_questionnaire\local\response\questionnaire_responses;
use mod_questionnaire\reporter;
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
    public const QTYPE_UNLIMITED = 0;
    /** @var int Response frequency: one attempt only. */
    public const QTYPE_ONCE = 1;
    /** @var int Response frequency: once per day. */
    public const QTYPE_DAILY = 2;
    /** @var int Response frequency: once per week. */
    public const QTYPE_WEEKLY = 3;
    /** @var int Response frequency: once per month. */
    public const QTYPE_MONTHLY = 4;

    // Student response-view (respview field) constants.

    /** @var int Response visibility: students never see other responses. */
    public const RESPVIEW_NEVER = 0;
    /** @var int Response visibility: students see responses after they have answered. */
    public const RESPVIEW_WHENANSWERED = 1;
    /** @var int Response visibility: students see responses after the questionnaire closes. */
    public const RESPVIEW_WHENCLOSED = 2;
    /** @var int Response visibility: students always see other responses. */
    public const RESPVIEW_ALWAYS = 3;

    // Other internal constants.

    /** @var int Default number of rows per pagination page. */
    private const DEFAULT_PAGE_COUNT = 20;
    /** @var string URL parameter name for the permanent-delete confirmation action. */
    public const CONFIRM_DELETE_PERMANENTLY = 'confirmdelpermanentlyq';
    /** @var string URL parameter name for the question-restore action. */
    public const RESTORE_PARAM = 'restoreq';

    /** @var questionnaire_record|null The module record instance. */
    protected ?questionnaire_record $modulerecord = null;

    /** @var survey|null The survey domain object for this questionnaire. */
    protected ?survey $survey = null;

    /** @var cm_info|null The course module info object for this questionnaire. */
    protected ?cm_info $coursemodule = null;

    /** @var context_module|null The context for this questionnaire. */
    protected ?context_module $context = null;

    /** @var stdClass The course record for this questionnaire. */
    protected stdClass $course;

    // PROPERTIES TO BE REPLACED AND REFACTORED LATER.

    /** @var \plugin_renderer_base The module renderer, extended from core renderer. */
    public $renderer;

    /** @var \templatable The templatable page to render. */
    public $page;

    /** @var question_navigator|null Lazy-loaded navigator for page and dependency traversal. */
    private ?question_navigator $navigator = null;

    /** @var reporter|null Lazy-loaded reporter for CSV export and response analysis. */
    private ?reporter $reporter = null;

    /** @var questionnaire_responses|null Lazy-loaded responses handler; cached so in-memory state persists. */
    private ?questionnaire_responses $responseshandler = null;

    /** @var capabilities|null Lazy-loaded permission/eligibility helper. */
    private ?capabilities $capabilities = null;

    /** @var submission_controller|null Lazy-loaded controller for in-progress submission flow. */
    private ?submission_controller $submission = null;

    /** @var string Course-module idnumber, used by gradebook. Set by callers that need it. */
    public string $cmidnumber = '';

    /** @var int Course id, set by callers that need it directly on the object. */
    public int $courseid = 0;

    /**
     * Construct from a questionnaire instance id, optionally with a pre-loaded persistent and cm.
     *
     * Pass all arguments null to construct an "empty" instance for survey-only mode;
     * the from_survey() factory uses this path to populate a survey without a module
     * instance or course module (e.g. previewing template/public surveys before
     * attaching them to a course).
     *
     * @param int|null $mid Instance id (questionnaire.id).
     * @param int|null $cmid Course module id, if already known.
     * @param \cm_info|null $cm Optional pre-loaded cm_info object.
     */
    public function __construct(?int $mid = null, ?int $cmid = null, ?cm_info $cm = null) {
        if (empty($mid) && empty($cmid) && empty($cm)) {
            return;
        }

        if (!empty($mid)) {
            $this->modulerecord = new questionnaire_record($mid);
        }
        if (!empty($cm)) {
            $this->coursemodule = clone($cm);
        } else if (!empty($cmid)) {
            $this->coursemodule = cm_info::create(
                get_coursemodule_from_id('questionnaire', $cmid, 0, false, MUST_EXIST)
            );
        }

        if (empty($this->modulerecord)) {
            $this->modulerecord = new questionnaire_record($this->coursemodule->instance);
        }

        if (empty($this->coursemodule)) {
            $this->coursemodule = \cm_info::create(
                get_coursemodule_from_instance('questionnaire', $this->modulerecord->get('id'), 0, false, MUST_EXIST)
            );
        }

        $this->context = context_module::instance($this->coursemodule->id);
        $this->course = get_course($this->modulerecord->get('course'));
        $sid = $this->modulerecord->get('sid');
        $this->survey = survey::from_sid((int) $sid, $this->context);
    }

    /**
     * Return a questionnaire instance from an activity instance id.
     *
     * @param int $instanceid questionnaire.id
     * @return self
     */
    public static function from_instanceid(int $instanceid): self {
        return new self($instanceid);
    }

    /**
     * Return a questionnaire instance from a course module id.
     *
     * @param int $cmid course_modules.id
     * @return self
     */
    public static function from_cmid(int $cmid): self {
        return new self(null, $cmid);
    }

    /**
     * Return a questionnaire instance from a cm_info object.
     *
     * @param \cm_info $cm Course module info object.
     * @return self
     */
    public static function from_cm(\cm_info $cm): self {
        return new self(null, null, $cm);
    }

    /**
     * Return a survey-only instance: no module record, no course module, no module context.
     *
     * Used by preview.php to preview template or public surveys before they are
     * attached to a course module. The returned instance has id() == 0 and
     * coursemodule() == null; context() falls back to the course context.
     *
     * @param int $sid Survey id.
     * @param \stdClass $course Course record the survey is being previewed under.
     * @return self
     */
    public static function from_survey(int $sid, \stdClass $course): self {
        $instance = new self();
        $instance->course = $course;
        $instance->survey = survey::from_sid($sid);
        return $instance;
    }

    /**
     * Get the id of the questionnaire instance. Returns 0 in survey-only mode.
     *
     * @return int
     */
    public function id(): int {
        return $this->modulerecord?->get('id') ?? 0;
    }

    /**
     * Get the name of the questionnaire. Falls back to the survey title in survey-only mode.
     *
     * @return string
     */
    public function name(): string {
        return $this->modulerecord?->get('name') ?? ($this->survey?->title() ?? '');
    }

    /**
     * Get the intro text of the questionnaire.
     *
     * @return string
     */
    public function intro(): string {
        return $this->modulerecord?->get('intro') ?? '';
    }

    /**
     * Get the course id of the questionnaire.
     *
     * @return int
     */
    public function courseid(): int {
        return (int) ($this->modulerecord?->get('course') ?? $this->course->id);
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
     * Get the course module info object. Returns null in survey-only mode.
     *
     * @return \cm_info|null
     */
    public function coursemodule(): ?\cm_info {
        return $this->coursemodule;
    }

    /**
     * Get the context for this questionnaire. Falls back to the course context in survey-only mode.
     *
     * @return \context
     */
    public function context(): \context {
        return $this->context ?? \context_course::instance($this->course->id);
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
     * Get the response-frequency setting for this questionnaire.
     *
     * @return int One of the QTYPE_* constants.
     */
    public function qtype(): int {
        return (int) $this->modulerecord->get('qtype');
    }

    /**
     * Get the response-view setting for this questionnaire.
     *
     * @return int One of the RESPVIEW_* constants.
     */
    public function respview(): int {
        return (int) $this->modulerecord->get('respview');
    }

    /**
     * Get the respondent type setting for this questionnaire (e.g. 'fullname', 'anonymous').
     *
     * @return string
     */
    public function respondenttype(): string {
        return $this->modulerecord?->get('respondenttype') ?? 'fullname';
    }

    /**
     * Get the notification setting for this questionnaire (0 = none, 1 = simple, 2 = full).
     *
     * @return int
     */
    public function notifications(): int {
        return (int) $this->modulerecord->get('notifications');
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
     * Return the navigator for page traversal and dependency inspection.
     *
     * @return question_navigator
     */
    public function navigator(): question_navigator {
        $this->navigator ??= new question_navigator($this->survey, $this->navigate() > 0);
        return $this->navigator;
    }

    /**
     * Return the reporter for CSV export and response analysis.
     *
     * @return reporter
     */
    public function reporter(): reporter {
        $this->reporter ??= new reporter($this);
        return $this->reporter;
    }

    /**
     * True if skip-logic is enabled and at least one question has a dependency condition.
     *
     * Kept as a delegation method because question.php receives either the legacy or new
     * questionnaire class and calls this method on whichever it has.
     *
     * @return bool
     */
    public function has_dependencies(): bool {
        return $this->navigator()->has_dependencies();
    }

    /**
     * Load display info for each dependency of a question (parent name, choice label, etc.).
     *
     * Kept as a delegation method because question.php receives either the legacy or new
     * questionnaire class and calls this method on whichever it has.
     *
     * @param question $question
     * @return bool
     */
    public function load_parents(question $question): bool {
        return $this->navigator()->load_parents($question);
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
     * True if the user has an in-progress (incomplete) saved response.
     *
     * @param int $userid
     * @return bool
     */
    public function user_has_saved_response(int $userid): bool {
        return \mod_questionnaire\local\db\response_record::user_has_saved_response($this->id(), $userid);
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
        return $this->survey_is_public_master()
            ? \mod_questionnaire\local\db\response_record::count_complete_for_public_survey(
                $this->surveyid(),
                $userid,
                $groupid
            )
            : \mod_questionnaire\local\db\response_record::count_complete_for_questionnaire(
                $this->id(),
                $userid,
                $groupid
            );
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
            $msg = $this->capabilities()->can_manage_questionnaire() ? 'removenotinuse' : 'notavail';
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
        if (!$this->capabilities()->user_is_eligible($userid)) {
            return get_string('noteligible', 'questionnaire');
        }
        if (!$this->capabilities()->user_can_take($userid)) {
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
     * Create a new questionnaire survey record.
     *
     * @param stdClass $sdata  Survey data object (must include courseid).
     * @return int  The new survey id.
     */
    public static function add_survey(stdClass $sdata): int {
        return survey::add_survey($sdata);
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
        global $USER;

        $individualresponse = optional_param('individualresponse', false, PARAM_INT);
        $rid = optional_param('rid', false, PARAM_INT); // Response id.
        $currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

        $cm = $settings->get_page()->cm;
        $context = $cm->context;
        $cmid = $cm->id;
        $course = $settings->get_page()->course;
        $courseid = $course->id;

        // Treat an unlinked or orphaned survey row as "owned" — same fallback as the legacy code.
        $owner = ($this->survey()->id() == 0) ? true : $this->survey()->is_owned_by_course((int) $courseid);

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

        if ($this->capabilities()->user_can_take($USER->id)) {
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

        if ($this->capabilities()->can_read_own_responses() && ($usernumresp > 0)) {
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
                if ($this->capabilities()->can_download_responses()) {
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
        if ($this->capabilities()->can_view_all_responses($usernumresp)) {
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

            if ($this->capabilities()->can_view_single_response()) {
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

            if ($this->capabilities()->can_delete_responses()) {
                $summarynode->add(
                    get_string('deleteallresponses', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $this->id(), 'action' => 'delallresp', 'group' => $currentgroupid]
                    )
                );
            }

            if ($this->capabilities()->can_download_responses()) {
                $summarynode->add(
                    get_string('downloadtextformat', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $this->id(), 'action' => 'dwnpg', 'group' => $currentgroupid]
                    )
                );
            }
            if ($this->capabilities()->can_view_single_response()) {
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
        if ($this->capabilities()->can_view_single_response() && ($canviewallgroups || $canviewgroups)) {
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
        global $COURSE, $USER, $DB;

        if ($COURSE->id == $courseid) {
            $course = $COURSE;
        } else {
            $course = $DB->get_record('course', ['id' => $courseid]);
        }

        $modinfo = get_fast_modinfo($course);

        $cm = $modinfo->cms[$cmid];
        $questionnaire = self::from_cm($cm);

        $context = \context_module::instance($cm->id);
        $grader = has_capability('mod/questionnaire:viewsingleresponse', $context);

        // If this is a copy of a public questionnaire whose original is located in another course,
        // current user (teacher) cannot view responses.
        if ($grader) {
            // For a public questionnaire, look for the original public questionnaire that it is based on.
            if (!$questionnaire->survey_is_public_master()) {
                // For a public questionnaire, look for the original public questionnaire that it is based on.
                $surveycourseid = $questionnaire->survey()->owning_courseid();
                $originalquestionnaire = questionnaire_record::get_for_survey_in_course(
                    $questionnaire->surveyid(),
                    $surveycourseid
                );
                $cmoriginal = get_coursemodule_from_instance(
                    "questionnaire",
                    $originalquestionnaire->get('id'),
                    $surveycourseid
                );
                $contextoriginal = \context_course::instance($surveycourseid, MUST_EXIST);
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
        $params['questionnaireid'] = $questionnaire->id();

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
            if ($questionnaire->respondenttype() != 'anonymous') {
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
            if ($questionnaire->respondenttype() != 'anonymous') {
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
        if (!empty($data->copyid)) {
            $questionnaire = self::from_cmid((int)$data->coursemodule);
            $oldquestionnaireid = questionnaire_record::get_for_survey((int) $data->copyid)?->get('id');
            $oldcm = get_coursemodule_from_instance('questionnaire', $oldquestionnaireid);
            $oldquestionnaire = self::from_cmid((int)$oldcm->id);
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

    // Response-flow and utility methods.

    /**
     * Return the questionnaire_responses handler for this questionnaire.
     *
     * The same instance is returned on every call so that in-memory response state
     * (loaded via add_response / add_response_from_formdata) is visible to subsequent
     * get_response calls within the same request.
     *
     * @return questionnaire_responses
     */
    public function responses(): questionnaire_responses {
        $this->responseshandler ??= new questionnaire_responses($this);
        return $this->responseshandler;
    }

    /**
     * Return the capabilities helper for this questionnaire.
     *
     * @return capabilities
     */
    public function capabilities(): capabilities {
        $this->capabilities ??= new capabilities($this);
        return $this->capabilities;
    }

    /**
     * Return the submission controller for page navigation and section saves.
     *
     * @return submission_controller
     */
    public function submission(): submission_controller {
        $this->submission ??= new submission_controller($this);
        return $this->submission;
    }

    /**
     * Load a single response into the responses handler by response id.
     *
     * Convenience pass-through to questionnaire_responses::add_response().
     *
     * @param int $responseid
     */
    public function add_response(int $responseid): void {
        $this->responses()->add_response($responseid);
    }

    /**
     * Load all submitted responses for a user into the responses handler.
     *
     * Convenience pass-through to questionnaire_responses::add_user_responses().
     *
     * @param int|null $userid Defaults to current user when null.
     */
    public function add_user_responses(?int $userid = null): void {
        $this->responses()->add_user_responses($userid);
    }

    /**
     * Return a structured export of all answers for a given response id.
     *
     * Delegates to questionnaire_responses::get_structured_response(). Used by the
     * privacy provider to export user data.
     *
     * @param int $rid The response id.
     * @return array
     */
    public function get_structured_response(int $rid): array {
        return $this->responses()->get_structured_response($rid);
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
     * Process a mobile-app submission and return a result array.
     *
     * Handles next/previous page navigation and final submission from the mobile app.
     * Implementation delegates to responses() and submission() — this method itself
     * is the public surface that externallib.php and output/mobile.php call.
     *
     * NOTE: This is the mobile-API contract. Do not rename, relocate, or change the
     * signature without coordinating with the Moodle mobile app team and bumping the
     * web-service version. See externallib.php::save_mobile_data and
     * classes/output/mobile.php::mobile_view_activity_offline.
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
            $result = $this->submission()->next_page_action($response, $userid);
            if (is_string($result)) {
                $ret['warnings'] = $result;
            } else {
                $ret['nextpagenum'] = $result;
            }
        } else if ($action == 'previouspage') {
            $ret['nextpagenum'] = $this->submission()->previous_page_action($response, $userid);
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
     * Return an array describing all file areas used by this questionnaire's survey.
     *
     * @return array Keys are area names; values are either a single id or an array of ids.
     */
    public function get_all_file_areas(): array {
        return $this->survey->get_all_file_areas();
    }

    // Completion flow methods.
    // The print_survey() and survey_print_render() shims below delegate to the legacy class.
    // They will be removed once the rendering layer is refactored.

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
        (new \mod_questionnaire\output\survey_view_renderer($this->renderer, $this->page))
            ->build_view($this, $USER->id);
    }

    /**
     * Render the survey page(s) for completion.
     *
     * Processes form submissions for navigation and draft-saving, then renders
     * the current page of the survey.
     *
     * @param int $quser
     * @param int|false $userid
     * @return string|null Error message string, or null on success.
     */
    public function print_survey(int $quser, $userid = false): ?string {
        return (new \mod_questionnaire\output\survey_view_renderer($this->renderer, $this->page))
            ->build_survey_form($this, $quser, $userid ?: null);
    }

    /**
     * Render the survey for printing or preview display.
     *
     * @param int $courseid
     * @param string $message
     * @param string $referer
     * @param int $rid
     * @param bool $blankquestionnaire
     * @return false|void
     */
    public function survey_print_render(
        $courseid,
        $message = '',
        $referer = '',
        $rid = 0,
        $blankquestionnaire = false
    ) {
        return (new \mod_questionnaire\output\report_view_renderer($this->renderer, $this->page))
            ->build_print_view($this, $courseid, $message, $referer, $rid, $blankquestionnaire);
    }

}
