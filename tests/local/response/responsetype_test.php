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
     * Ensure question.php is loaded so its QUES* constants (QUESYESNO, QUESCHECK, …) are
     * available at test-argument evaluation time, even when a test runs in isolation.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        class_exists(\mod_questionnaire\local\question\question::class);
    }

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
     * @param array|null $choicedata Optional choice rows for choice-based question types.
     * @return array
     */
    private function build_data_fixture(int $qtype, ?array $choicedata = null): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_test_questionnaire(
            $course,
            $qtype,
            ['content' => 'data fixture'],
            $choicedata
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

    /**
     * date::insert_response() writes a YYYY-MM-DD value to questionnaire_response_date.
     */
    public function test_date_insert_response_writes_value(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESDATE);

        $rt = new date($question);
        $insertedid = $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => '2026-06-14',
        ]);

        $this->assertNotFalse($insertedid);
        $row = $DB->get_record('questionnaire_response_date', ['id' => $insertedid]);
        $this->assertEquals($rid, $row->responseid);
        $this->assertEquals($question->id(), $row->questionid);
        $this->assertSame('2026-06-14', $row->response);
    }

    /**
     * date::insert_response() rejects badly-formatted input (returns false, writes nothing).
     */
    public function test_date_insert_response_rejects_bad_format(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESDATE);

        $rt = new date($question);
        $result = $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => 'not-a-date',
        ]);

        $this->assertFalse($result);
        $this->assertSame(0, $DB->count_records('questionnaire_response_date', ['responseid' => $rid]));
    }

    /**
     * date::get_results() returns the stored date rows for the given response ids.
     */
    public function test_date_get_results_returns_dates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESDATE);
        $rt = new date($question);
        $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => '2026-06-14',
        ]);

        $results = $rt->get_results([$rid]);
        $this->assertCount(1, $results);
        $row = reset($results);
        $this->assertSame('2026-06-14', $row->response);
    }

    /**
     * single::insert_response() writes one questionnaire_resp_single row for the chosen choice id.
     */
    public function test_single_insert_response_writes_choice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'One', 'value' => 1],
            (object)['content' => 'Two', 'value' => 2],
            (object)['content' => 'Three', 'value' => 3],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESRADIO, $choices);

        // Pick the "Two" choice id from the question's loaded choices.
        $twoid = 0;
        foreach ($question->choices as $cid => $choice) {
            if ($choice->content === 'Two') {
                $twoid = $cid;
                break;
            }
        }
        $this->assertNotEmpty($twoid);

        $rt = new single($question);
        $insertedid = $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => $twoid,
        ]);

        $this->assertNotFalse($insertedid);
        $row = $DB->get_record('questionnaire_resp_single', ['id' => $insertedid]);
        $this->assertEquals($rid, $row->responseid);
        $this->assertEquals($question->id(), $row->questionid);
        $this->assertEquals($twoid, $row->choiceid);
    }

    /**
     * single::get_results() joins the response and choice rows so each result carries the choice content.
     */
    public function test_single_get_results_returns_choice_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'One', 'value' => 1],
            (object)['content' => 'Two', 'value' => 2],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESRADIO, $choices);

        $twoid = 0;
        foreach ($question->choices as $cid => $choice) {
            if ($choice->content === 'Two') {
                $twoid = $cid;
                break;
            }
        }

        $rt = new single($question);
        $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => $twoid,
        ]);

        $results = $rt->get_results([$rid]);
        $contents = array_map(fn($r) => $r->content ?? null, $results);
        $this->assertContains('Two', $contents);
    }

    /**
     * rank::insert_response() writes one questionnaire_response_rank row per ranked choice.
     */
    public function test_rank_insert_response_writes_rows_per_choice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'Speed', 'value' => null],
            (object)['content' => 'Quality', 'value' => null],
            (object)['content' => 'Price', 'value' => null],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESRATE, $choices);

        // Resolve choice ids by content so the form data uses real ids.
        $choiceidsbycontent = [];
        foreach ($question->choices as $cid => $choice) {
            $choiceidsbycontent[$choice->content] = $cid;
        }

        $rt = new rank($question);
        $data = (object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() . '_' . $choiceidsbycontent['Speed'] => '1',
            'q' . $question->id() . '_' . $choiceidsbycontent['Quality'] => '2',
            'q' . $question->id() . '_' . $choiceidsbycontent['Price'] => '3',
        ];
        $rt->insert_response($data);

        $rows = $DB->get_records('questionnaire_response_rank', [
            'responseid' => $rid,
            'questionid' => $question->id(),
        ]);
        $this->assertCount(3, $rows);
        $bychoice = [];
        foreach ($rows as $row) {
            $bychoice[(int)$row->choiceid] = (int)$row->rankvalue;
        }
        $this->assertSame(1, $bychoice[$choiceidsbycontent['Speed']]);
        $this->assertSame(2, $bychoice[$choiceidsbycontent['Quality']]);
        $this->assertSame(3, $bychoice[$choiceidsbycontent['Price']]);
    }

    /**
     * rank::insert_response() with "Not applicable" stores -1 (excluded from get_results averages).
     */
    public function test_rank_insert_response_marks_notapplicable_as_minus_one(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'Speed', 'value' => null],
            (object)['content' => 'Quality', 'value' => null],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESRATE, $choices);

        $choiceidsbycontent = [];
        foreach ($question->choices as $cid => $choice) {
            $choiceidsbycontent[$choice->content] = $cid;
        }

        $rt = new rank($question);
        $data = (object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() . '_' . $choiceidsbycontent['Speed'] => '2',
            'q' . $question->id() . '_' . $choiceidsbycontent['Quality'] => get_string('notapplicable', 'questionnaire'),
        ];
        $rt->insert_response($data);

        $qualityrow = $DB->get_record('questionnaire_response_rank', [
            'responseid' => $rid,
            'choiceid' => $choiceidsbycontent['Quality'],
        ]);
        $this->assertEquals(-1, $qualityrow->rankvalue);
    }

    /**
     * rank::get_results() returns one row per choice with its average rank value across responses.
     */
    public function test_rank_get_results_returns_averages_by_choice(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'Speed', 'value' => null],
            (object)['content' => 'Quality', 'value' => null],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESRATE, $choices);

        $choiceidsbycontent = [];
        foreach ($question->choices as $cid => $choice) {
            $choiceidsbycontent[$choice->content] = $cid;
        }
        $speedid = $choiceidsbycontent['Speed'];
        $qualityid = $choiceidsbycontent['Quality'];

        $rt = new rank($question);

        // Two responses: speed=1/quality=2 and speed=3/quality=4 → avg speed=2, avg quality=3.
        $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() . '_' . $speedid => '1',
            'q' . $question->id() . '_' . $qualityid => '2',
        ]);
        $rid2 = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(), 'userid' => $USER->id,
            'submitted' => time(), 'complete' => 'n', 'grade' => 0,
        ]);
        $rt->insert_response((object)[
            'rid' => $rid2,
            'a' => $questionnaire->id(),
            'q' . $question->id() . '_' . $speedid => '3',
            'q' . $question->id() . '_' . $qualityid => '4',
        ]);

        $results = $rt->get_results([$rid, $rid2]);
        // Results are reindexed by content.
        $this->assertArrayHasKey('Speed', $results);
        $this->assertArrayHasKey('Quality', $results);
        $this->assertEqualsWithDelta(2.0, (float)$results['Speed']->average, 0.0001);
        $this->assertEqualsWithDelta(3.0, (float)$results['Quality']->average, 0.0001);
        $this->assertSame(2, (int)$results['Speed']->num);
        $this->assertSame(2, (int)$results['Quality']->num);
    }

    /**
     * rank::display_results() returns a templatable stdClass once results exist.
     */
    public function test_rank_display_results_returns_tags(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'Speed', 'value' => null],
            (object)['content' => 'Quality', 'value' => null],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESRATE, $choices);

        $choiceidsbycontent = [];
        foreach ($question->choices as $cid => $choice) {
            $choiceidsbycontent[$choice->content] = $cid;
        }

        $rt = new rank($question);
        $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() . '_' . $choiceidsbycontent['Speed'] => '1',
            'q' . $question->id() . '_' . $choiceidsbycontent['Quality'] => '2',
        ]);

        $pagetags = $rt->display_results([$rid]);
        $this->assertIsObject($pagetags);
    }

    /**
     * multiple::insert_response() writes one questionnaire_resp_multiple row per checked choice.
     */
    public function test_multiple_insert_response_writes_rows_per_choice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'Red', 'value' => null],
            (object)['content' => 'Green', 'value' => null],
            (object)['content' => 'Blue', 'value' => null],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESCHECK, $choices);

        $choiceidsbycontent = [];
        foreach ($question->choices as $cid => $choice) {
            $choiceidsbycontent[$choice->content] = $cid;
        }
        $redid = $choiceidsbycontent['Red'];
        $blueid = $choiceidsbycontent['Blue'];

        $rt = new multiple($question);
        // Webform format: q{qid} is an array keyed by selected choice ids.
        $data = (object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => [$redid => $redid, $blueid => $blueid],
        ];
        $rt->insert_response($data);

        // Exactly two rows landed in the multi table; no rows for Green.
        $rows = $DB->get_records('questionnaire_resp_multiple', [
            'responseid' => $rid,
            'questionid' => $question->id(),
        ]);
        $this->assertCount(2, $rows);
        $chosen = array_map(fn($r) => (int)$r->choiceid, $rows);
        sort($chosen);
        $expected = [$redid, $blueid];
        sort($expected);
        $this->assertSame($expected, $chosen);

        // Sibling single table is empty — confirms make_primary_record routed to the right persistent.
        $this->assertSame(0, $DB->count_records('questionnaire_resp_single', [
            'responseid' => $rid,
            'questionid' => $question->id(),
        ]));
    }

    /**
     * multiple::get_results() joins the resp_multiple table so each result row carries the choice content.
     */
    public function test_multiple_get_results_returns_choice_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'Red', 'value' => null],
            (object)['content' => 'Green', 'value' => null],
            (object)['content' => 'Blue', 'value' => null],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESCHECK, $choices);

        $choiceidsbycontent = [];
        foreach ($question->choices as $cid => $choice) {
            $choiceidsbycontent[$choice->content] = $cid;
        }

        $rt = new multiple($question);
        $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => [
                $choiceidsbycontent['Red'] => $choiceidsbycontent['Red'],
                $choiceidsbycontent['Green'] => $choiceidsbycontent['Green'],
            ],
        ]);

        $results = $rt->get_results([$rid]);
        $contents = array_map(fn($r) => $r->content ?? null, $results);
        $this->assertContains('Red', $contents);
        $this->assertContains('Green', $contents);
        $this->assertNotContains('Blue', $contents);
    }

    /**
     * multiple::response_select() returns a per-question array keyed by the chosen choice ids.
     */
    public function test_multiple_response_select_returns_structured_array(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $choices = [
            (object)['content' => 'Red', 'value' => null],
            (object)['content' => 'Green', 'value' => null],
        ];
        [$questionnaire, $question, $rid] = $this->build_data_fixture(QUESCHECK, $choices);

        $choiceidsbycontent = [];
        foreach ($question->choices as $cid => $choice) {
            $choiceidsbycontent[$choice->content] = $cid;
        }
        $redid = $choiceidsbycontent['Red'];
        $greenid = $choiceidsbycontent['Green'];

        $rt = new multiple($question);
        $rt->insert_response((object)[
            'rid' => $rid,
            'a' => $questionnaire->id(),
            'q' . $question->id() => [$redid => $redid, $greenid => $greenid],
        ]);

        $values = multiple::response_select($rid);
        $this->assertArrayHasKey($question->id(), $values);
        $this->assertArrayHasKey('responses', $values[$question->id()]);
        $responses = $values[$question->id()]['responses'];
        $this->assertArrayHasKey($redid, $responses);
        $this->assertArrayHasKey($greenid, $responses);
    }
}
