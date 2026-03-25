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
use mod_questionnaire\local\response\questionnaire_responses;
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
            $this->questions[$questionrec->get('id')] = question::question_builder($typeid, $rec, $this->context);

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
     * Get all question arrays organised by section (keyed by 1-based section number).
     *
     * @return array
     */
    public function questions_by_section_all(): array {
        return $this->questionsbysec;
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
        if ($this->navigate() > 0 && !empty($this->questions)) {
            foreach ($this->questions as $question) {
                if ($question->has_dependencies()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get the IDs of all questions that depend on the given question.
     *
     * @param int $questionid
     * @return array
     */
    public function get_dependants(int $questionid): array {
        $qu = [];
        foreach ($this->questions as $question) {
            if ($question->has_dependencies()) {
                foreach ($question->dependencies as $dependency) {
                    if (($dependency->dependquestionid == $questionid) && !in_array($question->id, $qu)) {
                        $qu[] = $question->id;
                    }
                }
            }
        }
        return $qu;
    }

    /**
     * Get all direct and indirect dependants of a question.
     *
     * @param int $questionid
     * @return stdClass Object with ->directs and ->indirects arrays.
     */
    public function get_all_dependants(int $questionid): stdClass {
        $directids = $this->get_dependants($questionid);
        $directs = [];
        $indirects = [];
        foreach ($directids as $directid) {
            $this->load_parents($this->questions[$directid]);
            $indirectids = $this->get_dependants($directid);
            foreach ($this->questions[$directid]->dependencies as $dep) {
                if ($dep->dependquestionid == $questionid) {
                    $directs[$directid][] = $dep;
                }
            }
            foreach ($indirectids as $indirectid) {
                $this->load_parents($this->questions[$indirectid]);
                foreach ($this->questions[$indirectid]->dependencies as $dep) {
                    if ($dep->dependquestionid != $questionid) {
                        $indirects[$indirectid][] = $dep;
                    }
                }
            }
        }
        $alldependants = new stdClass();
        $alldependants->directs = $directs;
        $alldependants->indirects = $indirects;
        return $alldependants;
    }

    /**
     * Get all descendants and their choice conditions, keyed by parent question id.
     *
     * @return array
     */
    public function get_dependants_and_choices(): array {
        $questions = array_reverse($this->questions, true);
        $parents = [];
        foreach ($questions as $question) {
            foreach ($question->dependencies as $dependency) {
                $child = new stdClass();
                $child->choiceid = $dependency->dependchoiceid;
                $child->logic = $dependency->dependlogic;
                $child->andor = $dependency->dependandor;
                $parents[$dependency->dependquestionid][$question->id][] = $child;
            }
        }
        return $parents;
    }

    /**
     * Load display info for each dependency of a question (parent name, choice label, etc.).
     *
     * @param question $question
     * @return bool
     */
    public function load_parents(question $question): bool {
        foreach ($question->dependencies as $did => $dependency) {
            $dependquestion = $this->questions[$dependency->dependquestionid];
            $qdependchoice = '';
            switch ($dependquestion->typeid) {
                case QUESRADIO:
                case QUESDROP:
                case QUESCHECK:
                    $qdependchoice = $dependency->dependchoiceid;
                    $dependchoice = $dependquestion->choices[$dependency->dependchoiceid]->content;
                    $contents = \questionnaire_choice_values($dependchoice);
                    if ($contents->modname) {
                        $dependchoice = $contents->modname;
                    }
                    break;
                case QUESYESNO:
                    switch ($dependency->dependchoiceid) {
                        case 0:
                            $dependchoice = get_string('yes');
                            $qdependchoice = 'y';
                            break;
                        case 1:
                            $dependchoice = get_string('no');
                            $qdependchoice = 'n';
                            break;
                        default:
                            $dependchoice = '';
                    }
                    break;
                default:
                    $dependchoice = '';
            }
            $question->dependencies[$did]->qdependquestion = 'q' . $dependquestion->id;
            $question->dependencies[$did]->qdependchoice = $qdependchoice;
            $question->dependencies[$did]->parenttype = $dependquestion->typeid;
            $question->dependencies[$did]->position = $question->position;
            $question->dependencies[$did]->name = $question->name;
            $question->dependencies[$did]->content = $question->content;
            $question->dependencies[$did]->parentposition = $dependquestion->position;
            $question->dependencies[$did]->parent = format_string($dependquestion->name) . '->' . format_string($dependchoice);
        }
        return true;
    }

    /**
     * True if there are any eligible (dependency-satisfied) questions on the given section.
     *
     * Note: $this->questionsbysec stores question objects (not IDs), unlike the legacy class.
     *
     * @param int $secnum 1-based section number.
     * @param int $rid Response id (0 if no response yet).
     * @return bool
     */
    public function eligible_questions_on_page(int $secnum, int $rid): bool {
        foreach ($this->questionsbysec[$secnum] as $question) {
            if ($question->dependency_fulfilled($rid, $this->questions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the next valid section number after $secnum, or false if none.
     *
     * @param int $secnum Current section number.
     * @param int $rid Response id.
     * @return int|bool
     */
    public function next_page(int $secnum, int $rid): int|bool {
        $secnum++;
        $numsections = !empty($this->questionsbysec) ? count($this->questionsbysec) : 0;
        if ($this->has_dependencies()) {
            while (!$this->eligible_questions_on_page($secnum, $rid)) {
                $secnum++;
                if ($secnum > $numsections) {
                    $secnum = false;
                    break;
                }
            }
        }
        return $secnum;
    }

    /**
     * Return the previous valid section number before $secnum, or false if none.
     *
     * @param int $secnum Current section number.
     * @param int $rid Response id.
     * @return int|bool
     */
    public function prev_page(int $secnum, int $rid): int|bool {
        $secnum--;
        if ($this->has_dependencies()) {
            while (($secnum > 0) && !$this->eligible_questions_on_page($secnum, $rid)) {
                $secnum--;
            }
        }
        if ($secnum === 0) {
            $secnum = false;
        }
        return $secnum;
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
    // Navigation / display / hook methods (delegated from lib.php).
    // -------------------------------------------------------------------------

    /**
     * Adds module specific settings to the settings block.
     *
     * @param \settings_navigation $settings The settings navigation object.
     * @param \navigation_node $questionnairenode The node to add module settings to.
     * @return void
     */
    public static function extend_settings_navigation(
        \settings_navigation $settings,
        \navigation_node $questionnairenode
    ): void {
        global $DB, $USER, $CFG;

        $individualresponse = optional_param('individualresponse', false, PARAM_INT);
        $rid = optional_param('rid', false, PARAM_INT); // Response id.
        $currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

        require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

        $cm = $settings->get_page()->cm;
        $context = $cm->context;
        $cmid = $cm->id;
        $course = $settings->get_page()->course;

        if (!$questionnaire = $DB->get_record("questionnaire", ["id" => $cm->instance])) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questionnaire');
        }

        $courseid = $course->id;
        $questionnaire = new \questionnaire($course, $cm, 0, $questionnaire);

        if ($owner = $DB->get_field('questionnaire_survey', 'courseid', ['id' => $questionnaire->sid])) {
            $owner = (trim($owner) == trim($courseid));
        } else {
            $owner = true;
        }

        // On view page, currentgroupid is not yet sent as an optional_param, so get it.
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode > 0 && $currentgroupid == 0) {
            $currentgroupid = groups_get_activity_group($questionnaire->cm);
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

        if ($questionnaire->user_can_take($USER->id)) {
            $url = '/mod/questionnaire/complete.php';
            if ($questionnaire->user_has_saved_response($USER->id)) {
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
        $usernumresp = $questionnaire->count_submissions($USER->id);

        if ($questionnaire->capabilities->readownresponses && ($usernumresp > 0)) {
            $url = '/mod/questionnaire/myreport.php';

            if ($usernumresp > 1) {
                $urlargs = [
                    'instance' => $questionnaire->id,
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
                    'instance' => $questionnaire->id,
                    'userid' => $USER->id,
                    'byresponse' => 0,
                    'action' => 'summary',
                    'group' => $currentgroupid,
                ];
                $myreportnode->add(get_string('summary', 'questionnaire'), new \moodle_url($url, $urlargs));

                $urlargs = [
                    'instance' => $questionnaire->id,
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
                    'instance' => $questionnaire->id,
                    'userid' => $USER->id,
                    'byresponse' => 0,
                    'action' => 'vall',
                    'group' => $currentgroupid,
                ];
                $myreportnode->add(get_string('myresponses', 'questionnaire'), new \moodle_url($url, $urlargs));
                if ($questionnaire->capabilities->downloadresponses) {
                    $urlargs = [
                        'instance' => $questionnaire->id,
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
                    'instance' => $questionnaire->id,
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
        if ($questionnaire->can_view_all_responses($usernumresp)) {
            $url = '/mod/questionnaire/report.php';
            $node = \navigation_node::create(
                get_string('viewallresponses', 'questionnaire'),
                new \moodle_url(
                    $url,
                    ['instance' => $questionnaire->id, 'action' => 'vall']
                ),
                \navigation_node::TYPE_SETTING,
                null,
                'vall'
            );
            $reportnode = $questionnairenode->add_node($node, $beforekey);

            if ($questionnaire->capabilities->viewsingleresponse) {
                $summarynode = $reportnode->add(
                    get_string('summary', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $questionnaire->id, 'action' => 'vall']
                    )
                );
            } else {
                $summarynode = $reportnode;
            }
            $summarynode->add(
                get_string('order_default', 'questionnaire'),
                new \moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'vall', 'group' => $currentgroupid]
                )
            );
            $summarynode->add(
                get_string('order_ascending', 'questionnaire'),
                new \moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'vallasort', 'group' => $currentgroupid]
                )
            );
            $summarynode->add(
                get_string('order_descending', 'questionnaire'),
                new \moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'vallarsort', 'group' => $currentgroupid]
                )
            );

            if ($questionnaire->capabilities->deleteresponses) {
                $summarynode->add(
                    get_string('deleteallresponses', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $questionnaire->id, 'action' => 'delallresp', 'group' => $currentgroupid]
                    )
                );
            }

            if ($questionnaire->capabilities->downloadresponses) {
                $summarynode->add(
                    get_string('downloadtextformat', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $questionnaire->id, 'action' => 'dwnpg', 'group' => $currentgroupid]
                    )
                );
            }
            if ($questionnaire->capabilities->viewsingleresponse) {
                $byresponsenode = $reportnode->add(
                    get_string('viewbyresponse', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $questionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid]
                    )
                );

                $byresponsenode->add(
                    get_string('view', 'questionnaire'),
                    new \moodle_url(
                        '/mod/questionnaire/report.php',
                        ['instance' => $questionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid]
                    )
                );

                if ($individualresponse) {
                    $byresponsenode->add(
                        get_string('deleteresp', 'questionnaire'),
                        new \moodle_url(
                            '/mod/questionnaire/report.php',
                            [
                                'instance' => $questionnaire->id,
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
        if ($questionnaire->capabilities->viewsingleresponse && ($canviewallgroups || $canviewgroups)) {
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

    // -------------------------------------------------------------------------
    // Instance lifecycle methods (delegated from lib.php).
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Gradebook methods (delegated from lib.php).
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
    // Response-flow and utility methods.
    // -------------------------------------------------------------------------

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
        global $DB;
        $params = ['questionnaireid' => $this->id(), 'userid' => $userid, 'complete' => 'n'];
        if ($records = $DB->get_records('questionnaire_response', $params, 'submitted DESC', 'id,questionnaireid', 0, 1)) {
            return (int) reset($records)->id;
        }
        return 0;
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
        $msg = $this->responses()->response_check_format($response->sec, $response, true, true, $this->questions);
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
            null, 'u.*', null, null, null, true
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
        global $DB;
        $sid = $this->surveyid();
        $areas = [];
        $areas['info'] = $sid;
        $areas['thankbody'] = $sid;
        if (empty($this->questions)) {
            $this->add_questions();
        }
        $areas['question'] = [];
        foreach ($this->questions as $question) {
            $areas['question'][] = $question->id;
        }
        $areas['feedbacknotes'] = $sid;
        $fbsections = $DB->get_records('questionnaire_fb_sections', ['surveyid' => $sid]);
        if (!empty($fbsections)) {
            $areas['sectionheading'] = [];
            foreach ($fbsections as $section) {
                $areas['sectionheading'][] = $section->id;
                $feedbacks = $DB->get_records('questionnaire_feedback', ['sectionid' => $section->id]);
                if (!empty($feedbacks)) {
                    $areas['feedback'] = [];
                    foreach ($feedbacks as $feedback) {
                        $areas['feedback'][] = $feedback->id;
                    }
                }
            }
        }
        return $areas;
    }

    // -------------------------------------------------------------------------
    // Private helpers.
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
