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

namespace mod_questionnaire\local\survey;

use mod_questionnaire\local\db\choice_record;
use mod_questionnaire\local\db\dependency_record;
use mod_questionnaire\local\db\feedback_record;
use mod_questionnaire\local\db\feedback_section_record;
use mod_questionnaire\local\db\question_record;
use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\question\question;
use mod_questionnaire\local\question_type;
use mod_questionnaire\local\response\questionnaire_responses;
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
    /** @var string[] Survey persistent fields that may be updated via update_settings(). */
    private const UPDATABLE_FIELDS = [
        'name', 'realm', 'title', 'subtitle', 'info', 'theme',
        'thankspage', 'thankhead', 'thankbody', 'email', 'courseid',
    ];

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

    // Survey finders.

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
            self::get_survey_list('private', $courseid),
            'private',
            0
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
            self::get_survey_list('public', 0),
            'public',
            $courseid
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
            self::get_survey_list('template', 0),
            'template',
            0
        );
    }

    // Survey record accessors.

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
     * Read a raw value from the survey persistent record.
     *
     * Package-private: reserved for sibling domain classes (currently feedback)
     * that need to read survey-stored state without exposing the persistent
     * record. External callers must use the typed accessor methods on survey,
     * feedback, or another domain class.
     *
     * @param string $field
     * @return mixed
     */
    public function raw_field(string $field): mixed {
        return $this->surveyrecord->get($field);
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

    /**
     * Return true if any question in the given section (or in any section) is required.
     *
     * @param int $section 0 to check all sections, otherwise the 1-based section number.
     * @return bool
     */
    public function has_required(int $section = 0): bool {
        if (empty($this->questions)) {
            return false;
        }
        if ($section <= 0) {
            foreach ($this->questions as $question) {
                if ($question->required()) {
                    return true;
                }
            }
            return false;
        }
        if (!array_key_exists($section, $this->questionsbysec)) {
            return false;
        }
        foreach ($this->questionsbysec[$section] as $question) {
            if ($question->required()) {
                return true;
            }
        }
        return false;
    }

    // Question administration.

    /**
     * Return the soft-deleted questions for this survey.
     *
     * @return question[] Keyed by question id.
     */
    public function get_delete_questions(): array {
        $deletequestions = [];
        foreach (question_record::get_deleted_for_survey($this->id()) as $qrec) {
            $deletequestions[$qrec->get('id')] = question::question_builder(
                $qrec->get('typeid'),
                $qrec->to_record(),
                $this->context
            );
        }
        return $deletequestions;
    }

    /**
     * Move a question to a new position, re-numbering surrounding questions.
     *
     * @param int $moveqid    Id of the question to move.
     * @param int $movetopos  Target position (1-based).
     * @return bool
     */
    public function move_question(int $moveqid, int $movetopos): bool {
        $questions = $this->questions();
        if (!is_array($questions) || !isset($questions[$moveqid])) {
            return false;
        }
        $movequestion = $questions[$moveqid];
        $index = 1;
        foreach ($questions as $question) {
            if ($index == $movetopos) {
                $index++;
            }
            if ($question->id() == $movequestion->id()) {
                question_record::update_position($movequestion->id(), $movetopos);
                continue;
            }
            question_record::update_position($question->id(), $index);
            $index++;
        }
        return true;
    }

    /**
     * Validate that page breaks are correctly placed for dependent questions.
     *
     * Removes redundant consecutive page breaks and inserts missing page breaks
     * between questions that have different dependency chains.
     *
     * @return false|string Status message, or false on failure.
     */
    public function check_page_breaks() {
        $msg = '';
        $newpbids = [];
        $delpb = 0;
        $sid = $this->id();

        // Snapshot active questions and their dependencies as plain arrays so the
        // sliding-window comparison below can read positional state without re-querying.
        $positions = [];
        foreach (question_record::get_active_for_survey($sid) as $key => $qrec) {
            $deps = array_map(
                fn($d) => $d->to_record(),
                dependency_record::get_for_question($key)
            );
            $positions[] = [
                'questionid' => $key,
                'typeid' => $qrec->get('typeid'),
                'qname' => $qrec->get('name'),
                'qpos' => $qrec->get('position'),
                'dependencies' => $deps,
            ];
        }
        $count = count($positions);

        for ($i = $count - 1; $i >= 0; $i--) {
            $qu = $positions[$i];
            $prevqu = null;
            $prevtypeid = null;
            if ($i > 0) {
                $prevqu = $positions[$i - 1];
                $prevtypeid = $prevqu['typeid'];
            }
            if ($qu['typeid'] == QUESPAGEBREAK) {
                if ($prevtypeid == QUESPAGEBREAK || $i == $count - 1 || $qu['qpos'] == 1) {
                    $qid = $qu['questionid'];
                    $delpb++;
                    $msg .= get_string('checkbreaksremoved', 'questionnaire', $delpb) . '<br />';
                    // Re-load to capture position shifts caused by earlier iterations of this loop.
                    $current = question_record::get_active_for_survey($sid);
                    if (isset($current[$qid])) {
                        $deletedpos = $current[$qid]->get('position');
                        question_record::soft_delete($qid);
                        foreach (question_record::get_active_after_position($sid, $deletedpos) as $shifted) {
                            question_record::update_position($shifted->get('id'), $shifted->get('position') - 1);
                        }
                    }
                }
            }
            if ($qu['typeid'] != QUESPAGEBREAK) {
                if ($prevqu) {
                    $prevdependencies = $prevqu['dependencies'];
                    $outerdependencies = count($qu['dependencies']) >= count($prevdependencies) ?
                        $qu['dependencies'] : $prevdependencies;
                    $innerdependencies = count($qu['dependencies']) < count($prevdependencies) ?
                        $qu['dependencies'] : $prevdependencies;

                    $okeys = [];
                    $ikeys = [];
                    foreach ($outerdependencies as $okey => $outerdependency) {
                        foreach ($innerdependencies as $ikey => $innerdependency) {
                            if (
                                $outerdependency->dependquestionid === $innerdependency->dependquestionid &&
                                $outerdependency->dependchoiceid === $innerdependency->dependchoiceid &&
                                $outerdependency->dependlogic === $innerdependency->dependlogic
                            ) {
                                $okeys[] = $okey;
                                $ikeys[] = $ikey;
                            }
                        }
                    }

                    foreach ($okeys as $key) {
                        if (key_exists($key, $outerdependencies)) {
                            unset($outerdependencies[$key]);
                        }
                    }
                    foreach ($ikeys as $key) {
                        if (key_exists($key, $innerdependencies)) {
                            unset($innerdependencies[$key]);
                        }
                    }

                    $diffdependencies = count($outerdependencies) + count($innerdependencies);

                    if (
                        ($prevtypeid != QUESPAGEBREAK && $diffdependencies != 0) ||
                        (!isset($qu['dependencies']) && isset($prevdependencies))
                    ) {
                        $newpos = question_record::max_active_position_for_survey($sid) + 1;
                        $newpbids[] = question_record::create_pagebreak($sid, $newpos)->get('id');
                        $this->add_questions();
                        $this->move_question(end($newpbids), $qu['qpos']);
                    }
                }
            }
        }
        if (empty($newpbids) && !$msg) {
            $msg = get_string('checkbreaksok', 'questionnaire');
        } else if ($newpbids) {
            $msg .= get_string('checkbreaksadded', 'questionnaire') . '&nbsp;';
            $newpbids = array_reverse($newpbids);
            $this->add_questions();
            foreach ($newpbids as $newpbid) {
                $msg .= $this->questions()[$newpbid]->position() . '&nbsp;';
            }
        }
        return $msg;
    }

    /**
     * Prepare a question object for display in the question editing form.
     *
     * Populates draft file areas and dependency arrays expected by questions_form.
     *
     * @param int $qid   0 when creating a new question; the existing question id when editing.
     * @param int $qtype Question type id (used only when $qid is 0).
     * @return question
     */
    public function prep_question_for_form(int $qid, int $qtype): question {
        $cmid = $this->context->instanceid;
        if ($qid != 0) {
            $questions = $this->questions();
            $question = clone($questions[$qid]);
            $question->qid = $question->id();
            $question->sid = $this->id();
            $question->set_id($cmid);
            $draftideditor = file_get_submitted_draft_itemid('question');
            $content = file_prepare_draft_area(
                $draftideditor,
                $this->context->id,
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
            $question = question::question_builder($qtype);
            $question->sid = $this->id();
            $question->set_id($cmid);
            $question->set_typeid($qtype);
            $draftideditor = file_get_submitted_draft_itemid('question');
            $content = file_prepare_draft_area(
                $draftideditor,
                $this->context->id,
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

    // File areas.

    /**
     * Return an array describing all file areas used by this survey.
     *
     * @return array Keys are area names; values are either a single id or an array of ids.
     */
    public function get_all_file_areas(): array {
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
        $fbsections = feedback_section_record::get_for_survey($sid);
        if (!empty($fbsections)) {
            $areas['sectionheading'] = [];
            foreach ($fbsections as $section) {
                $areas['sectionheading'][] = $section->get('id');
                $feedbacks = feedback_record::get_for_section($section->get('id'));
                if (!empty($feedbacks)) {
                    $areas['feedback'] = [];
                    foreach ($feedbacks as $feedback) {
                        $areas['feedback'][] = $feedback->get('id');
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
        $sid = $this->id();
        $status = true;

        // Delete all question data for the survey.
        $questions = question_record::get_for_survey($sid);
        foreach ($questions as $question) {
            $qid = $question->get('id');
            choice_record::delete_for_question($qid);
            dependency_record::delete_for_question($qid);
        }
        if (!empty($questions)) {
            $status = $status && question_record::delete_for_survey($sid);
            $status = $status && dependency_record::delete_for_survey($sid);
        }

        // Delete all feedback sections and feedback messages for the survey.
        $fbsections = feedback_section_record::get_for_survey($sid);
        foreach ($fbsections as $fbsection) {
            feedback_record::delete_for_section($fbsection->get('id'));
        }
        if (!empty($fbsections)) {
            $status = $status && feedback_section_record::delete_for_survey($sid);
        }

        if ($this->surveyrecord->get('id')) {
            $this->surveyrecord->delete();
        }

        return $status;
    }

    /**
     * Delete all orphaned surveys — surveys that have no linked questionnaire instance.
     *
     * Intended for use by the scheduled cleanup task.
     */
    public static function cleanup_orphans(): void {
        foreach (survey_record::get_orphaned() as $orphan) {
            self::from_sid((int) $orphan->get('id'))->delete();
        }
    }

    /**
     * Return the configured duration (in seconds) before soft-deleted questions are permanently removed.
     *
     * The value comes from the questionnaire_questiondeletion plugin config. Callers should not need
     * to know the config key — use this method instead.
     *
     * @return string|false Duration string, or false if not configured.
     */
    public static function question_deletion_duration() {
        return get_config('questionnaire_questiondeletion', 'duration');
    }

    /**
     * Permanently delete a soft-deleted question and all its associated response data.
     *
     * @param int $qid Question id.
     * @param int $sid Survey id.
     */
    public static function delete_question_permanently(int $qid, int $sid): void {
        question_record::delete_soft_deleted($qid, $sid);
        questionnaire_responses::delete_responses_for_question($qid);
        dependency_record::delete_for_question($qid);
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
     * @param int $qid Question id.
     * @param int $sid Survey id.
     */
    public static function restore_deleted_question(int $qid, int $sid): void {
        $record = question_record::get_soft_deleted($qid, $sid);
        if ($record === null) {
            return;
        }
        $record->set('deleted', null);
        $record->set('position', question_record::max_active_position_for_survey($sid) + 1);
        $record->update();
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
     * Update this survey from an explicit array of field => value pairs.
     *
     * Only keys in self::UPDATABLE_FIELDS are accepted; passing anything else throws
     * a coding_exception (so callers cannot accidentally smuggle id, timecreated or
     * unrelated form metadata onto the persistent). When 'name', 'title' or 'realm'
     * are present they are required to be non-empty; a name change also has to be
     * unique across all surveys.
     *
     * Feedback-domain fields (feedbacknotes, feedbacksections, feedbackscores,
     * charttype) intentionally live on a separate allowlist owned by
     * {@see feedback::UPDATABLE_FIELDS}. Callers wanting to update those fields
     * must go through {@see feedback::update_settings()}.
     *
     * The $allowlist parameter is package-private: it is the override hook used
     * by sibling domain classes (e.g. feedback) that own a subset of survey
     * persistent fields. External callers must omit it.
     *
     * @param array $fields Map of survey field name => value.
     * @param array|null $allowlist Override for the allowlist of writable fields.
     *  Reserved for sibling domain classes (e.g. feedback) that own a subset of
     *  survey persistent fields. External callers must omit this argument.
     * @return int|false The survey id on success, false on validation failure.
     */
    public function update_settings(array $fields, ?array $allowlist = null): int|false {
        $allowlist ??= self::UPDATABLE_FIELDS;
        foreach (array_keys($fields) as $key) {
            if (!in_array($key, $allowlist, true)) {
                throw new \coding_exception("survey::update_settings: field '{$key}' is not updatable");
            }
        }

        foreach (['name', 'title', 'realm'] as $required) {
            if (array_key_exists($required, $fields) && empty($fields[$required])) {
                return false;
            }
        }

        if (array_key_exists('name', $fields)) {
            $newname = $fields['name'];
            if (trim($this->surveyrecord->get('name')) != trim(stripslashes($newname))) {
                if (survey_record::count_records(['name' => $newname]) != 0) {
                    return false;
                }
            }
        }

        foreach ($fields as $key => $value) {
            $this->surveyrecord->set($key, $value);
        }
        $this->surveyrecord->update();
        return (int) $this->surveyrecord->get('id');
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
        question_record::delete_soft_deleted_pagebreaks_for_survey($sid);
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

    // Protected helpers.

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

    // Private helpers.

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
    private static function from_record_shallow(survey_record $surveyrecord): self {
        $instance = new self(new survey_record());
        $instance->surveyrecord = $surveyrecord;
        return $instance;
    }

    /**
     * Return all private surveys belonging to the given course (shallow — no questions loaded).
     *
     * @param int $courseid
     * @return self[]
     */
    private static function get_private_for_course(int $courseid): array {
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
    private static function get_by_realm(string $realm): array {
        return array_map(
            fn($rec) => self::from_record_shallow($rec),
            survey_record::get_by_realm($realm)
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
            $qrec = questionnaire_record::get_for_survey($survey->id());
            if ($qrec === null) {
                continue;
            }
            $originalcourse = $DB->get_record('course', ['id' => $owningcourseid]);
            if (!$originalcourse) {
                continue;
            }
            $sid = $survey->id();
            $args = "sid={$sid}&popup=1&qid={$qrec->get('id')}";
            $link = new \moodle_url("/mod/questionnaire/preview.php?{$args}");
            $action = new \popup_action('click', $link);
            $label = $OUTPUT->action_link(
                $link,
                $qrec->get('name') . ' [' . $originalcourse->fullname . ']',
                $action,
                ['title' => $strpreview]
            );
            $surveylist[$realm . '-' . $sid] = $label;
        }
        return $surveylist;
    }
}
