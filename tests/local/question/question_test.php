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
 * Unit tests for question::form_update.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\question;

/**
 * Unit tests for mod_questionnaire\local\question\question::form_update.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\question::form_update
 */
final class question_test extends \advanced_testcase {
    /**
     * A new question and its dependencies go into the questionnaire's own survey, whatever survey id was posted.
     */
    public function test_form_update_ignores_posted_survey_id(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $courses = [$this->getDataGenerator()->create_course(), $this->getDataGenerator()->create_course()];
        $mine = $generator->create_test_questionnaire($courses[0], QUESTEXT, ['content' => 'Mine']);
        $other = $generator->create_test_questionnaire($courses[1], QUESTEXT, ['content' => 'Other']);
        $existingqid = (int)array_key_first($mine->questions());

        [$question] = $mine->survey()->prep_question_for_form(0, QUESTEXT);
        $question->form_update((object)[
            'qid' => 0,
            'sid' => $other->surveyid(),
            'typeid' => QUESTEXT,
            'name' => 'Planted',
            'length' => 20,
            'precise' => 25,
            'required' => 'n',
            'content' => ['text' => '<p>Planted</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
            'dependquestions_and' => [$existingqid . ',0'],
            'dependlogic_and' => [1],
        ], $mine);

        $record = $DB->get_record('questionnaire_question', ['name' => 'Planted'], '*', MUST_EXIST);
        $this->assertEquals($mine->surveyid(), $record->surveyid);
        $dependency = $DB->get_record('questionnaire_dependency', ['questionid' => $record->id], '*', MUST_EXIST);
        $this->assertEquals($mine->surveyid(), $dependency->surveyid);
        $this->assertFalse($DB->record_exists('questionnaire_question', ['surveyid' => $other->surveyid(), 'name' => 'Planted']));
    }
}
