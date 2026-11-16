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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

/**
 * Tests that saving the question form only changes the questionnaire being edited.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class question_form_update_test extends \advanced_testcase {
    /** @var \questionnaire The questionnaire being edited. */
    private $questionnaire;

    /** @var \questionnaire A questionnaire in another course. */
    private $other;

    /**
     * Create the questionnaire being edited and one in another course, each with one Yes/No question.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questiondata = ['content' => 'Original', 'name' => 'Original'];
        $this->questionnaire = $generator->create_test_questionnaire(
            $this->getDataGenerator()->create_course(),
            QUESYESNO,
            $questiondata
        );
        $this->other = $generator->create_test_questionnaire(
            $this->getDataGenerator()->create_course(),
            QUESYESNO,
            $questiondata
        );
    }

    /**
     * Build the submitted question form data, with ids pointing at the other questionnaire.
     *
     * @param int $qid The posted question id.
     * @return \stdClass
     */
    private function form_data(int $qid): \stdClass {
        return (object)[
            'qid' => $qid,
            'sid' => $this->other->sid,
            'name' => 'Changed',
            'type_id' => QUESYESNO,
            'required' => 'n',
            'content' => ['text' => 'Changed', 'format' => FORMAT_HTML, 'itemid' => 0],
        ];
    }

    /**
     * Get the other questionnaire's question record.
     *
     * @return \stdClass
     */
    private function other_question(): \stdClass {
        global $DB;
        return $DB->get_record('questionnaire_question', ['surveyid' => $this->other->sid], '*', MUST_EXIST);
    }

    /**
     * A new question goes into the questionnaire being edited, whatever survey id is posted.
     *
     * @covers \mod_questionnaire\question\question::form_update
     */
    public function test_new_question_ignores_posted_survey(): void {
        global $DB;

        $question = \questionnaire_prep_for_questionform($this->questionnaire, 0, QUESYESNO);
        $question->form_update($this->form_data(0), $this->questionnaire);

        $this->assertTrue($DB->record_exists(
            'questionnaire_question',
            ['surveyid' => $this->questionnaire->sid, 'name' => 'Changed']
        ));
        $this->assertEquals(1, $DB->count_records('questionnaire_question', ['surveyid' => $this->other->sid]));
    }

    /**
     * A new question with a posted question id is still added as a new question.
     *
     * @covers \mod_questionnaire\question\question::form_update
     */
    public function test_new_question_ignores_posted_question_id(): void {
        global $DB;

        $question = \questionnaire_prep_for_questionform($this->questionnaire, 0, QUESYESNO);
        $question->form_update($this->form_data($this->other_question()->id), $this->questionnaire);

        $this->assertTrue($DB->record_exists(
            'questionnaire_question',
            ['surveyid' => $this->questionnaire->sid, 'name' => 'Changed']
        ));
        $this->assertEquals('Original', $this->other_question()->name);
    }

    /**
     * Editing a question only changes that question, whatever question id is posted.
     *
     * @covers \mod_questionnaire\question\question::form_update
     */
    public function test_edited_question_ignores_posted_question_id(): void {
        global $DB;

        $own = reset($this->questionnaire->questions);
        $question = \questionnaire_prep_for_questionform($this->questionnaire, $own->id, QUESYESNO);
        $question->form_update($this->form_data($this->other_question()->id), $this->questionnaire);

        $this->assertEquals('Changed', $DB->get_field('questionnaire_question', 'name', ['id' => $own->id]));
        $this->assertEquals('Original', $this->other_question()->name);
    }

    /**
     * A new condition belongs to the questionnaire being edited, whatever survey id is posted.
     *
     * @covers \mod_questionnaire\question\question::form_update
     */
    public function test_new_condition_ignores_posted_survey(): void {
        global $DB;

        $own = reset($this->questionnaire->questions);
        $formdata = $this->form_data(0);
        $formdata->dependquestions_and = [$own->id . ',0'];
        $formdata->dependlogic_and = [1];
        $question = \questionnaire_prep_for_questionform($this->questionnaire, 0, QUESYESNO);
        $question->form_update($formdata, $this->questionnaire);

        $dependency = $DB->get_record('questionnaire_dependency', ['dependquestionid' => $own->id], '*', MUST_EXIST);
        $this->assertEquals($this->questionnaire->sid, $dependency->surveyid);
    }
}
