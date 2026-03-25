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

    /**
     * True if at least one question in this survey has dependency (skip-logic) conditions.
     *
     * @return bool
     */
    public function has_questions_with_dependencies(): bool {
        foreach ($this->questions as $question) {
            if ($question->has_dependencies()) {
                return true;
            }
        }
        return false;
    }

    // Dependency inspection.

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

    // Page navigation.

    /**
     * True if there are any eligible (dependency-satisfied) questions on the given section.
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
     * @param bool $hasdependencies Whether skip-logic is active for this questionnaire.
     * @return int|bool
     */
    public function next_page(int $secnum, int $rid, bool $hasdependencies = false): int|bool {
        $secnum++;
        $numsections = !empty($this->questionsbysec) ? count($this->questionsbysec) : 0;
        if ($hasdependencies) {
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
     * @param bool $hasdependencies Whether skip-logic is active for this questionnaire.
     * @return int|bool
     */
    public function prev_page(int $secnum, int $rid, bool $hasdependencies = false): int|bool {
        $secnum--;
        if ($hasdependencies) {
            while (($secnum > 0) && !$this->eligible_questions_on_page($secnum, $rid)) {
                $secnum--;
            }
        }
        if ($secnum === 0) {
            $secnum = false;
        }
        return $secnum;
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

    // Survey CRUD (static).

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
