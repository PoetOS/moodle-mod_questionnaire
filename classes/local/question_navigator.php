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

use mod_questionnaire\survey;
use mod_questionnaire\local\question\question;
use stdClass;

/**
 * Stateless navigator for moving through a survey's pages and questions.
 *
 * Encapsulates page-traversal and dependency-inspection logic that previously
 * lived on the survey and questionnaire classes. Accepts the survey (for
 * question/page structure) and a skip-logic flag (from the questionnaire module
 * record) at construction time, then reads question state fresh on each call.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class question_navigator {

    /**
     * Construct a navigator for the given survey.
     *
     * @param survey $survey The survey whose pages and questions are navigated.
     * @param bool $skiplogicenabled True when the questionnaire module has navigate=1.
     */
    public function __construct(
        private survey $survey,
        private bool $skiplogicenabled
    ) {
    }

    // Dependency inspection.

    /**
     * True if skip-logic is enabled and at least one question carries a dependency condition.
     *
     * @return bool
     */
    public function has_dependencies(): bool {
        if (!$this->skiplogicenabled) {
            return false;
        }
        foreach ($this->survey->questions() as $question) {
            if ($question->has_dependencies()) {
                return true;
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
        foreach ($this->survey->questions() as $question) {
            if ($question->has_dependencies()) {
                foreach ($question->dependencies as $dependency) {
                    if (($dependency->dependquestionid == $questionid) && !in_array($question->id(), $qu)) {
                        $qu[] = $question->id();
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
        $questions = $this->survey->questions();
        $directids = $this->get_dependants($questionid);
        $directs = [];
        $indirects = [];
        foreach ($directids as $directid) {
            $this->load_parents($questions[$directid]);
            $indirectids = $this->get_dependants($directid);
            foreach ($questions[$directid]->dependencies as $dep) {
                if ($dep->dependquestionid == $questionid) {
                    $directs[$directid][] = $dep;
                }
            }
            foreach ($indirectids as $indirectid) {
                $this->load_parents($questions[$indirectid]);
                foreach ($questions[$indirectid]->dependencies as $dep) {
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
        $questions = array_reverse($this->survey->questions(), true);
        $parents = [];
        foreach ($questions as $question) {
            foreach ($question->dependencies as $dependency) {
                $child = new stdClass();
                $child->choiceid = $dependency->dependchoiceid;
                $child->logic = $dependency->dependlogic;
                $child->andor = $dependency->dependandor;
                $parents[$dependency->dependquestionid][$question->id()][] = $child;
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
        $questions = $this->survey->questions();
        foreach ($question->dependencies as $did => $dependency) {
            $dependquestion = $questions[$dependency->dependquestionid];
            $qdependchoice = '';
            switch ($dependquestion->typeid()) {
                case QUESRADIO:
                case QUESDROP:
                case QUESCHECK:
                    $qdependchoice = $dependency->dependchoiceid;
                    $dependchoice = $dependquestion->choices[$dependency->dependchoiceid]->content;
                    $contents = question::parse_choice_content($dependchoice);
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
            $question->dependencies[$did]->qdependquestion = 'q' . $dependquestion->id();
            $question->dependencies[$did]->qdependchoice = $qdependchoice;
            $question->dependencies[$did]->parenttype = $dependquestion->typeid();
            $question->dependencies[$did]->position = $question->position();
            $question->dependencies[$did]->name = $question->name();
            $question->dependencies[$did]->content = $question->content();
            $question->dependencies[$did]->parentposition = $dependquestion->position();
            $question->dependencies[$did]->parent =
                format_string($dependquestion->name()) . '->' . format_string($dependchoice);
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
        $questions = $this->survey->questions();
        foreach ($this->survey->questions_by_section($secnum) as $question) {
            if ($question->dependency_fulfilled($rid, $questions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the next valid section number after $secnum, or false if none.
     *
     * When skip-logic is active, skips pages whose questions are all dependency-blocked.
     *
     * @param int $secnum Current section number.
     * @param int $rid Response id.
     * @return int|bool
     */
    public function next_page(int $secnum, int $rid): int|bool {
        $secnum++;
        $questionsbysec = $this->survey->questions_by_section_all();
        $numsections = !empty($questionsbysec) ? count($questionsbysec) : 0;
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
     * When skip-logic is active, skips pages whose questions are all dependency-blocked.
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
}
