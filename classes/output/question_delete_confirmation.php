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

namespace mod_questionnaire\output;

use mod_questionnaire\local\question\question;
use mod_questionnaire\local\response\questionnaire_responses;
use mod_questionnaire\questionnaire;
use renderer_base;
use stdClass;

/**
 * The message shown when confirming a question deletion (questions.php).
 *
 * Moving a question to the deletion area also lists the questions whose dependencies the move removes; permanently
 * deleting a question from the deletion area does not, as its dependencies are already gone.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_delete_confirmation implements \renderable, \templatable {
    /** @var questionnaire */
    protected questionnaire $questionnaire;

    /** @var question The question being deleted. */
    protected question $question;

    /** @var bool True when permanently deleting from the deletion area. */
    protected bool $permanent;

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire
     * @param int $qid The question being deleted.
     * @param bool $permanent True when permanently deleting a question from the deletion area.
     */
    public function __construct(questionnaire $questionnaire, int $qid, bool $permanent = false) {
        $this->questionnaire = $questionnaire;
        $this->permanent = $permanent;
        $questions = $permanent ? $questionnaire->survey()->get_delete_questions() : $questionnaire->questions();
        $this->question = $questions[$qid];
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $question = $this->question;
        $responses = questionnaire_responses::count_for_question($question->id(), $question->typeid());

        $data = new stdClass();
        $data->warnings = [];
        if ($this->permanent) {
            $data->warnings[] = get_string('confirmdelpermanentlyq', 'questionnaire');
            $data->label = 'NA' . $this->name_suffix($question);
        } else {
            $data->warnings[] = get_string('confirmdelquestion', 'questionnaire', $question->position() .
                $this->name_suffix($question));
            $data->label = $this->position_label($question);
        }
        if ($responses !== 0) {
            $data->warnings[] = get_string('confirmdelquestionresps', 'questionnaire', $responses);
        }
        $data->content = $this->content($question);
        $data->dependencygroups = $this->permanent ? [] : $this->dependency_groups();
        return $data;
    }

    /**
     * The direct and indirect dependants that lose a dependency when the question is moved to the deletion area.
     *
     * @return array[] Each with a heading and its dependant questions; empty groups are left out.
     */
    private function dependency_groups(): array {
        if (!$this->questionnaire->navigator()->has_dependencies()) {
            return [];
        }
        $dependants = $this->questionnaire->navigator()->get_all_dependants($this->question->id());
        $groups = [];
        foreach (['directwarnings' => $dependants->directs, 'indirectwarnings' => $dependants->indirects] as $heading => $list) {
            if (!empty($list)) {
                $groups[] = [
                    'heading' => get_string($heading, 'questionnaire'),
                    'questions' => $this->dependant_questions($list),
                ];
            }
        }
        return $groups;
    }

    /**
     * One entry per dependant question, listing the dependencies the template shows for it.
     *
     * @param array $dependants Dependency records keyed by dependant question id, from question_navigator::load_parents().
     * @return array[]
     */
    private function dependant_questions(array $dependants): array {
        $questions = $this->questionnaire->questions();
        $entries = [];
        foreach ($dependants as $qid => $dependencies) {
            $entries[] = [
                'label' => $this->position_label($questions[$qid]),
                'content' => $this->content($questions[$qid]),
                'conditions' => array_map(fn($dependency) => [
                    'class' => ($dependency->dependandor === 'or') ? 'qdepend-or' : 'qdepend',
                    'parentposition' => $dependency->parentposition,
                    'parent' => $dependency->parent,
                    'logic' => $this->logic_label((int)$dependency->dependlogic),
                ], array_values($dependencies)),
            ];
        }
        return $entries;
    }

    /**
     * "Position N (name)" for a question.
     *
     * @param question $question
     * @return string
     */
    private function position_label(question $question): string {
        return get_string('position', 'questionnaire') . ' ' . $question->position() . $this->name_suffix($question);
    }

    /**
     * " (name)" when the question has a name, else ''.
     *
     * @param question $question
     * @return string
     */
    private function name_suffix(question $question): string {
        return $question->name() ? ' (' . format_string($question->name()) . ')' : '';
    }

    /**
     * The question text, formatted for display; a blank question (two non-breaking spaces) shows nothing.
     *
     * @param question $question
     * @return string
     */
    private function content(question $question): string {
        $content = $question->content();
        if ($content == '<p>  </p>') {
            return '';
        }
        $content = file_rewrite_pluginfile_urls(
            $content,
            'pluginfile.php',
            $question->context->id,
            'mod_questionnaire',
            'question',
            $question->id()
        );
        return format_text($content, FORMAT_HTML, ['noclean' => true]);
    }

    /**
     * The label for a dependency's logic: set, not set, or nothing.
     *
     * @param int $logic
     * @return string
     */
    private function logic_label(int $logic): string {
        switch ($logic) {
            case 0:
                return get_string('notset', 'questionnaire');
            case 1:
                return get_string('set', 'questionnaire');
            default:
                return '';
        }
    }
}
