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
 * PHPUnit questionnaire generator tests
 *
 * @package    mod_questionnaire
 * @copyright  2022 Mike Churchward (mike@churchward.ca)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
require_once($CFG->dirroot . '/mod/questionnaire/classes/question/question.php');
require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

/**
 * Unit tests for questionnaire_deletion_question_testcase.
 *
 * @group mod_questionnaire
 */
final class deletion_question_test extends \advanced_testcase {
    public function setUp(): void {
        $this->create_question_by_type(
            QUESDATE,
            ['name' => 'DEMODATE1', 'content' => 'Demo date question 1', 'deleted' => time()]
        );
        $this->create_question_by_type(
            QUESDATE,
            ['name' => 'DEMODATE2', 'content' => 'Demo date question 2']
        );
        $this->create_question_by_type(
            QUESDATE,
            ['name' => 'DEMODATE3', 'content' => 'Demo date question 3', 'deleted' => time()]
        );
        $this->create_question_by_type(
            QUESTEXT,
            ['name' => 'DEMOTEXT4', 'content' => 'Demo text question 4']
        );

        parent::setUp();
    }

    /**
     * Test restore deleted question function.
     *
     * @return void
     *
     * @covers \questionnaire::restore_deleted_question
     */
    public function test_restore_deleted_question(): void {
        global $DB;
        $question = $DB->get_record_select('questionnaire_question', 'name = ?', ['DEMODATE1']);
        questionnaire_restore_deleted_question($question->id, $question->surveyid);
        $question = $DB->get_record('questionnaire_question', ['id' => $question->id]);
        $this->assertEquals($question->position, 1);
    }

    /**
     * Testing delete permanently question.
     *
     * @return void
     *
     * @covers \questionnaire::delete_permanently_questions
     */
    public function test_delete_permanently_question(): void {
        global $DB;
        $question = $DB->get_record_select('questionnaire_question', 'name = ?', ['DEMODATE1']);
        questionnaire_delete_permanently_questions($question->id, $question->surveyid);
        $question = $DB->get_record('questionnaire_question', ['id' => $question->id]);
        $this->assertEquals($question, false);
    }

    /**
     * Create a question by type of question.
     * @param mixed $qtype
     * @param mixed $qdata
     * @return void
     */
    public function create_question_by_type($qtype, $qdata): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $qdata['type_id'] = $qtype;
        $qdata['surveyid'] = $questionnaire->sid;
        $qdata['name'] = isset($qdata['name']) ? $qdata['name'] : 'Q1';
        $qdata['content'] = isset($qdata['content']) ? $qdata['content'] : 'Test content';
        $qdata['position'] = isset($qdata['position']) ? $qdata['position'] : 1;
        $question = $generator->create_question($questionnaire, $qdata, null);
    }
}
