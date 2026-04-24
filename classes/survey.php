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
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\question\question;
use mod_questionnaire\local\question_type;
use context_module;
use stdClass;

/**
 * The survey domain object — the content container for a questionnaire.
 *
 * Owns the survey record, its questions, page structure, dependency logic,
 * and survey-level CRUD operations. Independent of any specific questionnaire
 * module instance; a survey can be shared across multiple instances when public.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class survey {
    /** @var survey_record The survey record instance. */
    protected survey_record $surveyrecord;

    /** @var context_module|null The module context, used when building question objects. */
    protected ?context_module $context;

    /** @var question[] Question objects indexed by question id. */
    protected array $questions = [];

    /** @var question[][] Question objects organised by section number (1-based). */
    protected array $questionsbysec = [];

    /**
     * Construct from a survey_record, optionally with a module context.
     *
     * @param survey_record $surveyrecord
     * @param context_module|null $context Required for full question object construction.
     */
    public function __construct(survey_record $surveyrecord, ?context_module $context = null) {
        $this->surveyrecord = $surveyrecord;
        $this->context = $context;
        if (!empty($surveyrecord->get('id'))) {
            $this->load_questions();
        }
    }

    // Factories.

    /**
     * Return a survey loaded from the given survey id, or an empty survey if the record does not exist.
     *
     * @param int $sid
     * @param context_module|null $context
     * @return self
     */
    public static function from_sid(int $sid, ?context_module $context = null): self {
        if (!empty($sid) && survey_record::record_exists($sid)) {
            return new self(new survey_record($sid), $context);
        }
        return new self(new survey_record(), $context);
    }

    /**
     * Return a survey built from an already-loaded survey_record, without hitting the DB again.
     *
     * @param survey_record $surveyrecord
     * @param context_module|null $context
     * @return self
     */
    public static function from_record(survey_record $surveyrecord, ?context_module $context = null): self {
        return new self($surveyrecord, $context);
    }

    /**
     * Return a shallow survey built from an already-loaded survey_record without loading questions.
     *
     * Useful for lightweight contexts such as select-list building where only survey
     * metadata (id, name, realm, courseid) is needed and the question-loading DB query
     * would be wasteful.
     *
     * @param survey_record $surveyrecord
     * @return self
     */
    public static function from_record_shallow(survey_record $surveyrecord): self {
        $instance = new self(new survey_record());
        $instance->surveyrecord = $surveyrecord;
        return $instance;
    }

    // Survey finders.

    /**
     * Return all private surveys belonging to the given course (shallow — no questions loaded).
     *
     * @param int $courseid
     * @return self[]
     */
    public static function get_private_for_course(int $courseid): array {
        return array_map(
            fn($rec) => self::from_record_shallow($rec),
            survey_record::get_by_realm_in_course('private', $courseid)
        );
    }

    /**
     * Return all surveys with the given realm across all courses (shallow — no questions loaded).
     *
     * @param string $realm 'public' or 'template'.
     * @return self[]
     */
    public static function get_by_realm(string $realm): array {
        return array_map(
            fn($rec) => self::from_record_shallow($rec),
            survey_record::get_by_realm($realm)
        );
    }

    /**
     * Return private questionnaires for the given course as a labelled popup-preview select array.
     *
     * Keys are "private-{survey_id}"; values are popup-preview action-link strings.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_private_questionnaires(int $courseid): array {
        return self::build_survey_select_list(
            self::get_survey_list('private', $courseid), 'private', 0
        );
    }

    /**
     * Return public questionnaires from other courses as a labelled popup-preview select array.
     *
     * Surveys whose courseid matches $courseid are excluded (they are the current course's
     * own public surveys, which would create a circular reference).
     * Keys are "public-{survey_id}"; values are popup-preview action-link strings.
     *
     * @param int $courseid The current course id; questionnaires from this course are excluded.
     * @return array
     */
    public static function get_public_questionnaires(int $courseid): array {
        return self::build_survey_select_list(
            self::get_survey_list('public', 0), 'public', $courseid
        );
    }

    /**
     * Return template questionnaires from any course as a labelled popup-preview select array.
     *
     * Keys are "template-{survey_id}"; values are popup-preview action-link strings.
     *
     * @param int $courseid Passed for consistency; not used for filtering.
     * @return array
     */
    public static function get_template_questionnaires(int $courseid): array {
        return self::build_survey_select_list(
            self::get_survey_list('template', 0), 'template', 0
        );
    }

    /**
     * Return surveys of the given realm, scoped to a course when realm is 'private'.
     *
     * @param string $realm 'private', 'public', or 'template'.
     * @param int $courseid Scope to this course when realm is 'private'; ignored otherwise.
     * @return self[]
     */
    private static function get_survey_list(string $realm, int $courseid): array {
        return ($realm === 'private' && $courseid > 0)
            ? self::get_private_for_course($courseid)
            : self::get_by_realm($realm);
    }

    /**
     * Format a list of surveys as a labelled popup-preview select array for form radio buttons.
     *
     * Surveys with no linked questionnaire instance are skipped.
     *
     * @param self[] $surveys From get_survey_list().
     * @param string $realm Key prefix (e.g. 'private-42').
     * @param int $excludecourseid Skip items whose owning_courseid matches this value (0 = skip none).
     * @return array
     */
    private static function build_survey_select_list(array $surveys, string $realm, int $excludecourseid): array {
        global $OUTPUT, $DB;

        $surveylist = [];
        $strpreview = get_string('preview_questionnaire', 'questionnaire');
        foreach ($surveys as $survey) {
            $owningcourseid = $survey->owning_courseid();
            if ($excludecourseid > 0 && $owningcourseid == $excludecourseid) {
                continue;
            }
            $qrecs = $DB->get_records('questionnaire', ['sid' => $survey->id()], '', 'id, name', 0, 1);
            $qrec = reset($qrecs);
            if (!$qrec) {
                continue;
            }
            $originalcourse = $DB->get_record('course', ['id' => $owningcourseid]);
            if (!$originalcourse) {
                continue;
            }
            $sid = $survey->id();
            $args = "sid={$sid}&popup=1&qid={$qrec->id}";
            $link = new \moodle_url("/mod/questionnaire/preview.php?{$args}");
            $action = new \popup_action('click', $link);
            $label = $OUTPUT->action_link(
                $link,
                $qrec->name . ' [' . $originalcourse->fullname . ']',
                $action,
                ['title' => $strpreview]
            );
            $surveylist[$realm . '-' . $sid] = $label;
        }
        return $surveylist;
    }

    // Survey record accessors.

    /**
     * Return the underlying survey_record persistent object.
     *
     * @return survey_record
     */
    public function survey_record(): survey_record {
        return $this->surveyrecord;
    }

    /**
     * Get the survey id.
     *
     * @return int
     */
    public function id(): int {
        return (int) $this->surveyrecord->get('id');
    }

    /**
     * Get the survey name (internal identifier).
     *
     * @return string
     */
    public function name(): string {
        return $this->surveyrecord->get('name') ?? '';
    }

    /**
     * Get the survey display title.
     *
     * @return string
     */
    public function title(): string {
        return $this->surveyrecord->get('title') ?? '';
    }

    /**
     * Get the survey subtitle.
     *
     * @return string
     */
    public function subtitle(): string {
        return $this->surveyrecord->get('subtitle') ?? '';
    }

    /**
     * Get the survey info/description text.
     *
     * @return string
     */
    public function info(): string {
        return $this->surveyrecord->get('info') ?? '';
    }

    /**
     * Get the thank-you redirect URL for this survey.
     *
     * @return string
     */
    public function thankspage(): string {
        return $this->surveyrecord->get('thankspage') ?? '';
    }

    /**
     * Get the thank-you heading text for this survey.
     *
     * @return string
     */
    public function thankhead(): string {
        return $this->surveyrecord->get('thankhead') ?? '';
    }

    /**
     * Get the thank-you body text for this survey.
     *
     * @return string
     */
    public function thankbody(): string {
        return $this->surveyrecord->get('thankbody') ?? '';
    }

    /**
     * Get the notification email address for this survey.
     *
     * @return string
     */
    public function email(): string {
        return $this->surveyrecord->get('email') ?? '';
    }

    /**
     * Get the feedback notes for this survey.
     *
     * @return string
     */
    public function feedbacknotes(): string {
        return $this->surveyrecord->get('feedbacknotes') ?? '';
    }

    /**
     * Get the number of feedback sections configured for this survey.
     *
     * @return int
     */
    public function feedbacksections(): int {
        return (int) $this->surveyrecord->get('feedbacksections');
    }

    /**
     * True if this survey is configured to display numeric feedback scores.
     *
     * @return bool
     */
    public function feedbackscores(): bool {
        return (bool) $this->surveyrecord->get('feedbackscores');
    }

    /**
     * Get the chart type configured for feedback display.
     *
     * @return string
     */
    public function charttype(): string {
        return $this->surveyrecord->get('charttype') ?? '';
    }

    /**
     * Return the survey record as a plain stdClass object.
     *
     * Useful when legacy APIs or forms expect a raw object rather than the domain class.
     *
     * @return stdClass
     */
    public function to_stdclass(): stdClass {
        return $this->surveyrecord->to_record();
    }

    /**
     * Get the survey realm (private / public / template).
     *
     * @return string
     */
    public function realm(): string {
        return $this->surveyrecord->get('realm') ?? '';
    }

    /**
     * Get the course id that owns this survey.
     *
     * @return int
     */
    public function owning_courseid(): int {
        return (int) $this->surveyrecord->get('courseid');
    }

    /**
     * True if the survey is marked as public (shareable across courses).
     *
     * @return bool
     */
    public function is_public(): bool {
        return $this->realm() === 'public';
    }

    /**
     * True if the survey is a template.
     *
     * @return bool
     */
    public function is_template(): bool {
        return $this->realm() === 'template';
    }

    /**
     * True if the survey is public and the given course id is the owning course.
     *
     * @param int $questionnairecouseid The course id of the questionnaire instance.
     * @return bool
     */
    public function is_public_master(int $questionnairecouseid): bool {
        return $this->is_public() && ($this->owning_courseid() == $questionnairecouseid);
    }

    /**
     * True if the given course id owns this survey.
     *
     * @param int $courseid
     * @return bool
     */
    public function is_owned_by_course(int $courseid): bool {
        return $this->owning_courseid() == $courseid;
    }

    // Question loading and access.

    /**
     * Load all active questions for this survey, grouped by section.
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
     * Get all question objects, indexed by question id.
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
     * Get all question arrays organised by section (keyed by 1-based section number).
     *
     * @return array
     */
    public function questions_by_section_all(): array {
        return $this->questionsbysec;
    }

    // File areas.

    /**
     * Return an array describing all file areas used by this survey.
     *
     * @return array Keys are area names; values are either a single id or an array of ids.
     */
    public function get_all_file_areas(): array {
        global $DB;
        $sid = $this->id();
        $areas = [];
        $areas['info'] = $sid;
        $areas['thankbody'] = $sid;
        if (empty($this->questions)) {
            $this->add_questions();
        }
        $areas['question'] = [];
        foreach ($this->questions as $question) {
            $areas['question'][] = $question->id();
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

    // Survey CRUD (static).

    /**
     * Delete this survey and all its associated content.
     *
     * Removes questions (with their choices and dependencies), feedback sections,
     * feedback messages, and the survey record itself. Does NOT delete questionnaire
     * response data — callers that need to purge responses must do so beforehand.
     *
     * @return bool True on success.
     */
    public function delete(): bool {
        global $DB;
        $sid = $this->id();
        $status = true;

        // Delete all question data for the survey.
        if ($questions = $DB->get_records('questionnaire_question', ['surveyid' => $sid], 'id')) {
            foreach ($questions as $question) {
                $DB->delete_records('questionnaire_quest_choice', ['questionid' => $question->id]);
                $DB->delete_records('questionnaire_dependency', ['questionid' => $question->id]);
                $DB->delete_records('questionnaire_dependency', ['dependquestionid' => $question->id]);
            }
            $status = $status && $DB->delete_records('questionnaire_question', ['surveyid' => $sid]);
            $status = $status && $DB->delete_records('questionnaire_dependency', ['surveyid' => $sid]);
        }

        // Delete all feedback sections and feedback messages for the survey.
        if ($fbsections = $DB->get_records('questionnaire_fb_sections', ['surveyid' => $sid], 'id')) {
            foreach ($fbsections as $fbsection) {
                $DB->delete_records('questionnaire_feedback', ['sectionid' => $fbsection->id]);
            }
            $status = $status && $DB->delete_records('questionnaire_fb_sections', ['surveyid' => $sid]);
        }

        $status = $status && $DB->delete_records('questionnaire_survey', ['id' => $sid]);

        return $status;
    }

    /**
     * Delete all orphaned surveys — surveys that have no linked questionnaire instance.
     *
     * Intended for use by the scheduled cleanup task.
     */
    public static function cleanup_orphans(): void {
        global $DB;

        $sql = 'SELECT qs.* FROM {questionnaire_survey} qs
                LEFT JOIN {questionnaire} q ON q.sid = qs.id
                WHERE q.sid IS NULL';

        if ($surveys = $DB->get_records_sql($sql)) {
            foreach ($surveys as $surveyrow) {
                $survey = self::from_sid((int) $surveyrow->id);
                $survey->delete();
            }
        }
    }

    /**
     * Create a new survey record.
     *
     * @param stdClass $sdata Survey data object (must include courseid).
     * @return int The new survey id.
     */
    public static function add_survey(stdClass $sdata): int {
        return survey_record::create_from_sdata($sdata)->get('id');
    }

    /**
     * Update an existing survey record.
     *
     * @param int $sid Survey id.
     * @param stdClass $sdata Survey data object.
     * @return int|false The survey id on success, false on failure.
     */
    public static function update_survey(int $sid, stdClass $sdata): int|false {
        if (empty($sdata->name) || empty($sdata->title) || empty($sdata->realm)) {
            return false;
        }

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
     * @param stdClass $survey    The survey record to copy.
     * @param array    $questions The questions array.
     * @param int      $owner     The courseid that will own the new survey.
     * @return int|false The new survey id on success, false on failure.
     */
    public static function copy_survey(stdClass $survey, array $questions, int $owner): int|false {
        $oldsid = $survey->id;

        $basename = \core_text::substr($survey->name, 0, 64 - 10) . '_copy';
        $name = $basename;
        $i = 0;
        while (survey_record::count_records(['name' => $name]) > 0) {
            $name = $basename . (++$i);
        }

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

        $pos = 1;
        $qidarray = [];
        $cidarray = [];
        foreach ($questions as $question) {
            $oldid = $question->id();
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

        foreach (dependency_record::get_for_survey($oldsid) as $dep) {
            $newdep = new dependency_record();
            $newdep->set('questionid', $qidarray[$dep->get('questionid')]);
            $newdep->set('surveyid', $newsid);
            $newdep->set('dependquestionid', $qidarray[$dep->get('dependquestionid')]);
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
     * Delete all soft-deleted page-break questions for a survey.
     *
     * Called when a permanent question delete is triggered so stale page breaks are
     * not left behind in the survey structure.
     *
     * @param int $sid Survey id.
     */
    public static function delete_pagebreaks(int $sid): void {
        global $DB;
        $DB->delete_records_select(
            'questionnaire_question',
            'surveyid = :sid AND deleted IS NOT NULL AND typeid = :typeid',
            ['sid' => $sid, 'typeid' => question_type::QUESPAGEBREAK]
        );
    }

    /**
     * Return a map of child-question-id => parent-question-position for all dependent questions.
     *
     * When a question has multiple parents, the one with the highest position wins (last dependency
     * encountered when iterating the collection).
     *
     * @param array $questions Questions indexed by question id.
     * @return array  child question id => parent question position
     */
    public static function get_parent_positions(array $questions): array {
        $parentpositions = [];
        foreach ($questions as $question) {
            foreach ($question->dependencies as $dependency) {
                $dependquestion = $dependency->dependquestionid;
                if (isset($dependquestion) && $dependquestion != 0) {
                    $childid = $question->id();
                    $parentpos = $questions[$dependquestion]->position();
                    if (!isset($parentpositions[$childid]) || $parentpos > $parentpositions[$childid]) {
                        $parentpositions[$childid] = $parentpos;
                    }
                }
            }
        }
        return $parentpositions;
    }

    /**
     * Return a map of parent-question-id => child-question-position for all dependent questions.
     *
     * When a question has multiple children, the one with the lowest position wins.
     *
     * @param array $questions Questions indexed by question id.
     * @return array  parent question id => child question position
     */
    public static function get_child_positions(array $questions): array {
        $childpositions = [];
        foreach ($questions as $question) {
            foreach ($question->dependencies as $dependency) {
                $dependquestion = $dependency->dependquestionid;
                if (isset($dependquestion) && $dependquestion != 0) {
                    $parentid = $questions[$dependquestion]->id();
                    $childpos = $question->position();
                    if (!isset($childpositions[$parentid]) || $childpos < $childpositions[$parentid]) {
                        $childpositions[$parentid] = $childpos;
                    }
                }
            }
        }
        return $childpositions;
    }

    // Private helpers.

    /**
     * Load all active questions for a survey, built as full question objects.
     *
     * Used by copy_survey() which needs responsetype access for dependency replication.
     *
     * @param int $sid Survey id.
     * @return array Question objects indexed by question id.
     */
    public static function load_questions_for_survey(int $sid): array {
        $questions = [];
        foreach (question_record::get_active_for_survey($sid) as $qrec) {
            $questions[$qrec->get('id')] = question::question_builder($qrec->get('typeid'), $qrec->to_record());
        }
        return $questions;
    }
}
