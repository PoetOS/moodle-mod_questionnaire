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
 * Unit tests for mod_questionnaire\edit_question_form.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/lib.php');

/**
 * Unit tests for mod_questionnaire\edit_question_form.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\edit_question_form
 */
final class edit_question_form_test extends \advanced_testcase {
    /**
     * Build a course + questionnaire fixture with one text question, and point $PAGE at it.
     *
     * @return questionnaire
     */
    private function setup_questionnaire(): questionnaire {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_test_questionnaire($course, QUESTEXT, ['name' => 'Q1']);
        $PAGE->set_context($questionnaire->context());
        return $questionnaire;
    }

    /**
     * Asserts definition() builds the edit form for an existing question passed in customdata.
     */
    public function test_definition_uses_customdata_question(): void {
        $questionnaire = $this->setup_questionnaire();
        $qid = array_key_first($questionnaire->questions());
        [$question] = $questionnaire->survey()->prep_question_for_form($qid, QUESTEXT);

        $form = new edit_question_form('questions.php', ['questionnaire' => $questionnaire, 'question' => $question]);
        $form->set_data($question->form_data());

        $this->assertTrue($form->_form->elementExists('name'));
        $this->assertEquals('Q1', $form->_form->getElement('name')->getValue());
        $this->assertSame($question, $form->_customdata['question']);
    }

    /**
     * Asserts definition() builds the add form for a new question passed in customdata.
     */
    public function test_definition_new_question(): void {
        $questionnaire = $this->setup_questionnaire();
        [$question] = $questionnaire->survey()->prep_question_for_form(0, QUESESSAY);

        $form = new edit_question_form('questions.php', ['questionnaire' => $questionnaire, 'question' => $question]);

        $this->assertTrue($form->_form->elementExists('name'));
        $this->assertSame($questionnaire, $form->_customdata['questionnaire']);
    }
}
