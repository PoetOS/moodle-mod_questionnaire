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

/**
 * Unit tests for mod_questionnaire\output\question_delete_confirmation.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\local\db\dependency_record;
use mod_questionnaire\local\db\question_record;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\output\question_delete_confirmation.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\question_delete_confirmation
 */
final class question_delete_confirmation_test extends \advanced_testcase {
    /**
     * Fetch the plugin renderer.
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('mod_questionnaire');
    }

    /**
     * Build a questionnaire with yes/no questions Q1..Q3 at positions 1..3.
     *
     * @param bool $navigate Whether branching (skip logic) is enabled.
     * @return array [questionnaire, q1 id, q2 id, q3 id]
     */
    private function build_questionnaire(bool $navigate = true): array {
        global $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = $generator->create_instance(['course' => $course->id]);
        $DB->set_field('questionnaire', 'navigate', $navigate ? 1 : 0, ['id' => $instance->id()]);
        $questionnaire = questionnaire::from_instanceid($instance->id());

        $ids = [];
        foreach (['Q1', 'Q2', 'Q3'] as $index => $name) {
            $question = $generator->create_question($questionnaire, [
                'surveyid' => $questionnaire->surveyid(),
                'name' => $name,
                'typeid' => QUESYESNO,
                'content' => '<p>Question text ' . $name . '</p>',
            ]);
            question_record::update_position($question->id(), $index + 1);
            $ids[] = $question->id();
        }
        return array_merge([questionnaire::from_instanceid($instance->id())], $ids);
    }

    /**
     * Make $qid depend on $parentid being answered "Yes".
     *
     * @param questionnaire $questionnaire
     * @param int $qid
     * @param int $parentid
     * @param string $andor 'and' or 'or'.
     */
    private function add_dependency(questionnaire $questionnaire, int $qid, int $parentid, string $andor = 'and'): void {
        (new dependency_record(0, (object)[
            'questionid' => $qid,
            'surveyid' => $questionnaire->surveyid(),
            'dependquestionid' => $parentid,
            'dependchoiceid' => 0,
            'dependlogic' => 1,
            'dependandor' => $andor,
        ]))->create();
    }

    /**
     * Moving a question without dependants to the deletion area: one warning, its label and text, no dependency groups.
     */
    public function test_question_without_dependants(): void {
        $this->resetAfterTest();
        [$questionnaire, $q1] = $this->build_questionnaire();

        $data = (new question_delete_confirmation($questionnaire, $q1))->export_for_template($this->renderer());

        $this->assertEquals(
            [get_string('confirmdelquestion', 'questionnaire', '1 (Q1)')],
            $data->warnings
        );
        $this->assertEquals(get_string('position', 'questionnaire') . ' 1 (Q1)', $data->label);
        $this->assertStringContainsString('Question text Q1', $data->content);
        $this->assertEquals([], $data->dependencygroups);
    }

    /**
     * Existing responses to the question add a warning with their count.
     */
    public function test_responses_are_counted(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $q1] = $this->build_questionnaire();
        $DB->insert_record('questionnaire_response_bool', ['responseid' => 1, 'questionid' => $q1, 'choiceid' => 'y']);

        $data = (new question_delete_confirmation($questionnaire, $q1))->export_for_template($this->renderer());

        $this->assertCount(2, $data->warnings);
        $this->assertEquals(get_string('confirmdelquestionresps', 'questionnaire', 1), $data->warnings[1]);
    }

    /**
     * Direct and indirect dependants are listed in their own groups, with each dependency's parent, logic and and/or class.
     */
    public function test_direct_and_indirect_dependants(): void {
        $this->resetAfterTest();
        [$questionnaire, $q1, $q2, $q3] = $this->build_questionnaire();
        $this->add_dependency($questionnaire, $q2, $q1);
        $this->add_dependency($questionnaire, $q3, $q2, 'or');
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $data = (new question_delete_confirmation($questionnaire, $q1))->export_for_template($this->renderer());

        $this->assertCount(2, $data->dependencygroups);
        [$direct, $indirect] = $data->dependencygroups;
        $this->assertEquals(get_string('directwarnings', 'questionnaire'), $direct['heading']);
        $this->assertEquals(get_string('indirectwarnings', 'questionnaire'), $indirect['heading']);

        $this->assertCount(1, $direct['questions']);
        $this->assertEquals(get_string('position', 'questionnaire') . ' 2 (Q2)', $direct['questions'][0]['label']);
        $this->assertStringContainsString('Question text Q2', $direct['questions'][0]['content']);
        $this->assertEquals([[
            'class' => 'qdepend',
            'parentposition' => 1,
            'parent' => 'Q1->' . get_string('yes'),
            'logic' => get_string('set', 'questionnaire'),
        ]], $direct['questions'][0]['conditions']);

        $this->assertCount(1, $indirect['questions']);
        $this->assertEquals(get_string('position', 'questionnaire') . ' 3 (Q3)', $indirect['questions'][0]['label']);
        $this->assertEquals('qdepend-or', $indirect['questions'][0]['conditions'][0]['class']);
        $this->assertEquals('Q2->' . get_string('yes'), $indirect['questions'][0]['conditions'][0]['parent']);
    }

    /**
     * With branching off, dependencies are ignored, so no dependants are listed.
     */
    public function test_dependants_ignored_without_branching(): void {
        $this->resetAfterTest();
        [$questionnaire, $q1, $q2] = $this->build_questionnaire(false);
        $this->add_dependency($questionnaire, $q2, $q1);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $data = (new question_delete_confirmation($questionnaire, $q1))->export_for_template($this->renderer());

        $this->assertEquals([], $data->dependencygroups);
    }

    /**
     * Permanently deleting a question from the deletion area: its own warning, an NA label, and no dependency groups.
     */
    public function test_permanent_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $q1] = $this->build_questionnaire();
        $DB->set_field('questionnaire_question', 'deleted', time(), ['id' => $q1]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $data = (new question_delete_confirmation($questionnaire, $q1, true))->export_for_template($this->renderer());

        $this->assertEquals([get_string('confirmdelpermanentlyq', 'questionnaire')], $data->warnings);
        $this->assertEquals('NA (Q1)', $data->label);
        $this->assertStringContainsString('Question text Q1', $data->content);
        $this->assertEquals([], $data->dependencygroups);
    }

    /**
     * The rendered message nests each dependant's conditions inside its own container.
     */
    public function test_template_renders_dependants(): void {
        $this->resetAfterTest();
        [$questionnaire, $q1, $q2] = $this->build_questionnaire();
        $this->add_dependency($questionnaire, $q2, $q1);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $html = $this->renderer()->render(new question_delete_confirmation($questionnaire, $q1));

        $this->assertStringContainsString(get_string('directwarnings', 'questionnaire'), $html);
        $this->assertStringContainsString('<div class="qdepend">', $html);
        $this->assertStringContainsString('(' . get_string('position', 'questionnaire') . ' 1) : Q1->' . get_string('yes'), $html);
        $this->assertEquals(substr_count($html, '<div'), substr_count($html, '</div>'));
    }
}
