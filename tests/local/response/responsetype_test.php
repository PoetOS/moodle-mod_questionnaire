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
 * Unit tests for the responsetype hierarchy under classes/local/response/.
 *
 * Covers the pure / state methods on the abstract base and each concrete
 * subclass that can be exercised without a full questionnaire fixture.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\response;

/**
 * Unit tests for the responsetype hierarchy.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\response\responsetype
 * @covers \mod_questionnaire\local\response\boolean
 * @covers \mod_questionnaire\local\response\text
 * @covers \mod_questionnaire\local\response\date
 * @covers \mod_questionnaire\local\response\file
 * @covers \mod_questionnaire\local\response\single
 * @covers \mod_questionnaire\local\response\multiple
 * @covers \mod_questionnaire\local\response\rank
 * @covers \mod_questionnaire\local\response\slider
 * @covers \mod_questionnaire\local\response\numericaltext
 */
final class responsetype_test extends \advanced_testcase {
    /**
     * Build a minimal question stub with an id, suitable for instantiating a responsetype.
     *
     * @param int $id
     * @param array $choices
     * @return \mod_questionnaire\local\question\question
     */
    private function question_stub(int $id, array $choices = []): \mod_questionnaire\local\question\question {
        $stub = new class extends \mod_questionnaire\local\question\question {
            /** @var int Captured question id for the stub. */
            public $stubid = 0;

            /**
             * Bypass the real constructor — the stub does not need DB-backed state.
             */
            public function __construct() {
            }

            /**
             * Return the stub's captured id.
             *
             * @return int
             */
            public function id(): int {
                return $this->stubid;
            }

            /**
             * Help name placeholder.
             *
             * @return string
             */
            public function helpname(): string {
                return 'stub';
            }

            /**
             * Response table placeholder — not exercised by these tests.
             *
             * @return string
             */
            public function response_table() {
                return '';
            }

            /**
             * Responseclass placeholder — not exercised by these tests.
             *
             * @return string
             */
            protected function responseclass() {
                return '';
            }

            /**
             * Question display placeholder satisfying the abstract contract.
             *
             * @param mixed $response
             * @param mixed $descendantsdata
             * @param bool $blankquestionnaire
             * @return string
             */
            protected function question_survey_display($response, $descendantsdata, $blankquestionnaire = false) {
                return '';
            }

            /**
             * Response display placeholder satisfying the abstract contract.
             *
             * @param mixed $response
             * @return string
             */
            protected function response_survey_display($response) {
                return '';
            }
        };
        $stub->stubid = $id;
        $stub->choices = $choices;
        return $stub;
    }

    /**
     * all_response_tables() lists every per-type answer table the plugin owns.
     */
    public function test_all_response_tables(): void {
        $tables = responsetype::all_response_tables();
        $this->assertContains('questionnaire_response_bool', $tables);
        $this->assertContains('questionnaire_response_date', $tables);
        $this->assertContains('questionnaire_response_text', $tables);
        $this->assertContains('questionnaire_response_rank', $tables);
        $this->assertContains('questionnaire_response_other', $tables);
        $this->assertContains('questionnaire_resp_single', $tables);
        $this->assertContains('questionnaire_resp_multiple', $tables);
    }

    /**
     * Each subclass's static response_table() returns the expected table name.
     */
    public function test_response_table_per_subclass(): void {
        $this->assertSame('questionnaire_response_bool', boolean::response_table());
        $this->assertSame('questionnaire_response_text', text::response_table());
        $this->assertSame('questionnaire_response_date', date::response_table());
        $this->assertSame('questionnaire_response_file', file::response_table());
        $this->assertSame('questionnaire_resp_single', single::response_table());
        $this->assertSame('questionnaire_resp_multiple', multiple::response_table());
        $this->assertSame('questionnaire_response_rank', rank::response_table());
        // Slider and numericaltext inherit text's table.
        $this->assertSame('questionnaire_response_text', slider::response_table());
        $this->assertSame('questionnaire_response_text', numericaltext::response_table());
    }

    /**
     * The base transform_choiceid() is an identity pass-through.
     */
    public function test_transform_choiceid_default_is_identity(): void {
        $rt = new text($this->question_stub(1));
        $this->assertSame(42, $rt->transform_choiceid(42));
        $this->assertSame('foo', $rt->transform_choiceid('foo'));
    }

    /**
     * boolean::transform_choiceid() maps 0 -> 'y' and any other value -> 'n'.
     */
    public function test_transform_choiceid_boolean_override(): void {
        $rt = new boolean($this->question_stub(1));
        $this->assertSame('y', $rt->transform_choiceid(0));
        $this->assertSame('n', $rt->transform_choiceid(1));
        $this->assertSame('n', $rt->transform_choiceid(99));
    }

    /**
     * results_template() returns false on the base; subclasses that supply one return a string.
     */
    public function test_results_template(): void {
        $base = new text($this->question_stub(1));
        // The base default is false, but text overrides — verify each concrete returns expected.
        $this->assertNotEmpty($base->results_template());

        $bool = new boolean($this->question_stub(1));
        $this->assertNotEmpty($bool->results_template());
        $this->assertNotEmpty($bool->results_template(true));

        $rank = new rank($this->question_stub(1));
        $this->assertNotEmpty($rank->results_template());

        $single = new single($this->question_stub(1));
        $this->assertNotEmpty($single->results_template());
    }

    /**
     * set_display_context() sets the protected state used by render-time methods.
     */
    public function test_set_display_context_state(): void {
        $rt = new boolean($this->question_stub(1));
        // Access via reflection — the fields are protected.
        $rt->set_display_context(true, 'anonymous', 42);
        $reflection = new \ReflectionObject($rt);

        $canview = $reflection->getProperty('canviewsingleresponse');
        $canview->setAccessible(true);
        $this->assertTrue($canview->getValue($rt));

        $type = $reflection->getProperty('displayrespondenttype');
        $type->setAccessible(true);
        $this->assertSame('anonymous', $type->getValue($rt));

        $sid = $reflection->getProperty('displaysurveysid');
        $sid->setAccessible(true);
        $this->assertSame(42, $sid->getValue($rt));
    }

    /**
     * boolean::answers_from_webform() turns 'y' / 'n' form input into one answer per question.
     */
    public function test_answers_from_webform_boolean(): void {
        $question = $this->question_stub(7);
        $data = (object)['rid' => 99, 'q7' => 'y'];
        $answers = boolean::answers_from_webform($data, $question);
        $this->assertCount(1, $answers);
        $a = reset($answers);
        $this->assertEquals(99, $a->responseid);
        $this->assertEquals(7, $a->questionid);
        $this->assertSame('y', $a->choiceid);
    }

    /**
     * boolean::answers_from_webform() returns an empty array when the user did not answer.
     */
    public function test_answers_from_webform_boolean_empty(): void {
        $question = $this->question_stub(7);
        $data = (object)['rid' => 99]; // No q7 property.
        $this->assertSame([], boolean::answers_from_webform($data, $question));
    }

    /**
     * text::answers_from_webform() captures the free-form value when present.
     */
    public function test_answers_from_webform_text(): void {
        $question = $this->question_stub(11);
        $data = (object)['rid' => 50, 'q11' => 'a written answer'];
        $answers = text::answers_from_webform($data, $question);
        $this->assertCount(1, $answers);
        $a = reset($answers);
        $this->assertEquals(50, $a->responseid);
        $this->assertEquals(11, $a->questionid);
        $this->assertSame('a written answer', $a->value);
    }

    /**
     * text::answers_from_webform() returns nothing for an empty string.
     */
    public function test_answers_from_webform_text_empty(): void {
        $question = $this->question_stub(11);
        $this->assertSame([], text::answers_from_webform((object)['rid' => 50, 'q11' => ''], $question));
    }

    /**
     * Build a real course + questionnaire with one question of the given type, plus an empty
     * parent response row. Returns [questionnaire, question, parent response id].
     *
     * @param int $qtype Question type constant (QUESYESNO, QUESTEXT, etc).
     * @return array
     */
    private function build_data_fixture(int $qtype): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_test_questionnaire(
            $course,
            $qtype,
            ['content' => 'data fixture']
        );
        $question = reset($questionnaire->questions());

        // Use $USER->id so text::get_results' user join finds a row in its non-anonymous branch.
        $rid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => $USER->id,
            'submitted' => time(),
            'complete' => 'n',
            'grade' => 0,
        ]);
        return [$questionnaire, $question, (int)$rid];
    }

    /**
     * boolean::insert_response() writes a 'y' answer to questionnaire_response_bool.
     */
    public function test_boolean_insert_response_writes_record(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESYESNO);

        $rt = new boolean($question);
        $data = (object)['rid' => $rid, 'a' => $questionnaire->id(), 'q' . $question->id() => 'y'];
        $insertedid = $rt->insert_response($data);

        $this->assertNotFalse($insertedid);
        $row = $DB->get_record('questionnaire_response_bool', ['id' => $insertedid]);
        $this->assertEquals($rid, $row->responseid);
        $this->assertEquals($question->id(), $row->questionid);
        $this->assertSame('y', $row->choiceid);
    }

    /**
     * boolean::get_results() returns one count per choice id.
     */
    public function test_boolean_get_results_counts_choices(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESYESNO);
        $rt = new boolean($question);

        // Three responses: two 'y', one 'n', each in its own parent response row.
        $rt->insert_response((object)['rid' => $rid, 'a' => $questionnaire->id(), 'q' . $question->id() => 'y']);
        $rid2 = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(), 'userid' => $USER->id,
            'submitted' => time(), 'complete' => 'n', 'grade' => 0,
        ]);
        $rt->insert_response((object)['rid' => $rid2, 'a' => $questionnaire->id(), 'q' . $question->id() => 'y']);
        $rid3 = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(), 'userid' => $USER->id,
            'submitted' => time(), 'complete' => 'n', 'grade' => 0,
        ]);
        $rt->insert_response((object)['rid' => $rid3, 'a' => $questionnaire->id(), 'q' . $question->id() => 'n']);

        $results = $rt->get_results([$rid, $rid2, $rid3]);
        $bychoice = [];
        foreach ($results as $row) {
            $bychoice[$row->choiceid] = (int)$row->num;
        }
        $this->assertSame(2, $bychoice['y'] ?? 0);
        $this->assertSame(1, $bychoice['n'] ?? 0);
    }

    /**
     * boolean::display_results() returns a templatable object derived from the counts.
     */
    public function test_boolean_display_results_returns_tags(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESYESNO);
        $rt = new boolean($question);
        $rt->insert_response((object)['rid' => $rid, 'a' => $questionnaire->id(), 'q' . $question->id() => 'y']);

        $tags = $rt->display_results([$rid], '', false);
        $this->assertIsObject($tags);
        // Get_results_tags fills counts via $pagetags->counts; verify the structure renders something.
        $this->assertNotEmpty(get_object_vars($tags));
    }

    /**
     * text::insert_response() writes the clean text value to questionnaire_response_text.
     */
    public function test_text_insert_response_writes_value(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESTEXT);

        $rt = new text($question);
        $data = (object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => 'free-form answer',
        ];
        $insertedid = $rt->insert_response($data);

        $this->assertNotFalse($insertedid);
        $row = $DB->get_record('questionnaire_response_text', ['id' => $insertedid]);
        $this->assertEquals($rid, $row->responseid);
        $this->assertEquals($question->id(), $row->questionid);
        $this->assertSame('free-form answer', $row->response);
    }

    /**
     * text::get_results() returns the inserted text answers joined with the parent response.
     */
    public function test_text_get_results_returns_value(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESTEXT);
        $rt = new text($question);
        $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => 'hello world',
        ]);

        $results = $rt->get_results([$rid]);
        $this->assertCount(1, $results);
        $row = reset($results);
        $this->assertSame('hello world', $row->response);
    }
}
