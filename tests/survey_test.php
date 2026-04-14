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
 * Unit tests for mod_questionnaire\survey.
 *
 * Pure business-logic tests use survey_testable (no DB). Tests that require
 * a real survey record use the DB with resetAfterTest.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\db\survey_record;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
require_once($CFG->dirroot . '/mod/questionnaire/tests/survey_testable.php');

/**
 * Unit tests for mod_questionnaire\survey.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\survey
 */
final class survey_test extends \advanced_testcase {
    // Helpers.

    /**
     * Build an in-memory survey_record with sensible defaults, optionally overriding fields.
     *
     * @param array $fields
     * @return survey_record
     */
    private function make_survey_record(array $fields = []): survey_record {
        return new survey_record(0, (object) array_merge([
            'id'               => 1,
            'name'             => 'Test survey',
            'courseid'         => 1,
            'realm'            => 'private',
            'status'           => 0,
            'title'            => 'My title',
            'email'            => null,
            'subtitle'         => null,
            'info'             => null,
            'theme'            => null,
            'thankspage'       => null,
            'thankhead'        => null,
            'thankbody'        => null,
            'feedbacksections' => 0,
            'feedbacknotes'    => null,
            'feedbackscores'   => 0,
            'charttype'        => null,
        ], $fields));
    }

    /**
     * Build a survey_testable with optional field overrides.
     *
     * @param array $fields
     * @return survey_testable
     */
    private function make_survey(array $fields = []): survey_testable {
        return new survey_testable($this->make_survey_record($fields));
    }

    /**
     * Build a minimal stub question for position-helper tests.
     *
     * @param int   $id           Question id.
     * @param int   $position     Question position (1-based).
     * @param array $dependencies Array of dependency stdClass objects with dependquestionid.
     * @return object
     */
    private function make_stub_question(int $id, int $position, array $dependencies = []): object {
        return new class ($id, $position, $dependencies) {
            /** @var int */
            public int $qid;
            /** @var array */
            public array $dependencies;
            /** @var int */
            private int $pos;

            /**
             * Constructor.
             *
             * @param int   $id
             * @param int   $position
             * @param array $dependencies
             */
            public function __construct(int $id, int $position, array $dependencies) {
                $this->qid = $id;
                $this->pos = $position;
                $this->dependencies = $dependencies;
            }

            /**
             * Return the question id.
             *
             * @return int
             */
            public function id(): int {
                return $this->qid;
            }

            /**
             * Return the question position.
             *
             * @return int
             */
            public function position(): int {
                return $this->pos;
            }
        };
    }

    // Tests for accessor methods (no DB).

    /**
     * Asserts title() returns the value from the survey record.
     *
     * @covers \mod_questionnaire\survey::title
     */
    public function test_title_returns_value_from_record(): void {
        $this->assertEquals('My title', $this->make_survey(['title' => 'My title'])->title());
    }

    /**
     * Asserts subtitle() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\survey::subtitle
     */
    public function test_subtitle_returns_empty_string_when_null(): void {
        $this->assertSame('', $this->make_survey(['subtitle' => null])->subtitle());
    }

    /**
     * Asserts subtitle() returns the stored value when set.
     *
     * @covers \mod_questionnaire\survey::subtitle
     */
    public function test_subtitle_returns_value_when_set(): void {
        $this->assertEquals('Sub', $this->make_survey(['subtitle' => 'Sub'])->subtitle());
    }

    /**
     * Asserts info() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\survey::info
     */
    public function test_info_returns_empty_string_when_null(): void {
        $this->assertSame('', $this->make_survey(['info' => null])->info());
    }

    /**
     * Asserts realm() returns the stored realm string.
     *
     * @covers \mod_questionnaire\survey::realm
     */
    public function test_realm_returns_private_by_default(): void {
        $this->assertEquals('private', $this->make_survey()->realm());
    }

    /**
     * Asserts owning_courseid() returns the course id from the record.
     *
     * @covers \mod_questionnaire\survey::owning_courseid
     */
    public function test_owning_courseid_returns_course_id(): void {
        $this->assertEquals(42, $this->make_survey(['courseid' => 42])->owning_courseid());
    }

    /**
     * Asserts thankspage() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\survey::thankspage
     */
    public function test_thankspage_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['thankspage' => null])->thankspage());
    }

    /**
     * Asserts thankhead() returns the stored value when set.
     *
     * @covers \mod_questionnaire\survey::thankhead
     */
    public function test_thankhead_returns_value_when_set(): void {
        $this->assertEquals('Thank you!', $this->make_survey(['thankhead' => 'Thank you!'])->thankhead());
    }

    /**
     * Asserts thankbody() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\survey::thankbody
     */
    public function test_thankbody_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['thankbody' => null])->thankbody());
    }

    /**
     * Asserts email() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\survey::email
     */
    public function test_email_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['email' => null])->email());
    }

    /**
     * Asserts email() returns the stored address when set.
     *
     * @covers \mod_questionnaire\survey::email
     */
    public function test_email_returns_value_when_set(): void {
        $this->assertEquals('a@b.com', $this->make_survey(['email' => 'a@b.com'])->email());
    }

    /**
     * Asserts feedbacknotes() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\survey::feedbacknotes
     */
    public function test_feedbacknotes_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['feedbacknotes' => null])->feedbacknotes());
    }

    /**
     * Asserts feedbacksections() returns 0 when no feedback sections are configured.
     *
     * @covers \mod_questionnaire\survey::feedbacksections
     */
    public function test_feedbacksections_returns_zero_by_default(): void {
        $this->assertSame(0, $this->make_survey()->feedbacksections());
    }

    /**
     * Asserts feedbacksections() returns the configured count.
     *
     * @covers \mod_questionnaire\survey::feedbacksections
     */
    public function test_feedbacksections_returns_configured_value(): void {
        $this->assertEquals(3, $this->make_survey(['feedbacksections' => 3])->feedbacksections());
    }

    /**
     * Asserts charttype() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\survey::charttype
     */
    public function test_charttype_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['charttype' => null])->charttype());
    }

    // Tests for boolean state methods.

    /**
     * Asserts is_public() returns false for a private-realm survey.
     *
     * @covers \mod_questionnaire\survey::is_public
     */
    public function test_is_public_false_when_private(): void {
        $this->assertFalse($this->make_survey(['realm' => 'private'])->is_public());
    }

    /**
     * Asserts is_public() returns true for a public-realm survey.
     *
     * @covers \mod_questionnaire\survey::is_public
     */
    public function test_is_public_true_when_public(): void {
        $this->assertTrue($this->make_survey(['realm' => 'public'])->is_public());
    }

    /**
     * Asserts is_template() returns false for a private-realm survey.
     *
     * @covers \mod_questionnaire\survey::is_template
     */
    public function test_is_template_false_when_private(): void {
        $this->assertFalse($this->make_survey(['realm' => 'private'])->is_template());
    }

    /**
     * Asserts is_template() returns true for a template-realm survey.
     *
     * @covers \mod_questionnaire\survey::is_template
     */
    public function test_is_template_true_when_template(): void {
        $this->assertTrue($this->make_survey(['realm' => 'template'])->is_template());
    }

    /**
     * Asserts is_public_master() returns true when public and the course id matches.
     *
     * @covers \mod_questionnaire\survey::is_public_master
     */
    public function test_is_public_master_true_when_public_and_owning_course(): void {
        $survey = $this->make_survey(['realm' => 'public', 'courseid' => 5]);
        $this->assertTrue($survey->is_public_master(5));
    }

    /**
     * Asserts is_public_master() returns false when the survey is private.
     *
     * @covers \mod_questionnaire\survey::is_public_master
     */
    public function test_is_public_master_false_when_private(): void {
        $survey = $this->make_survey(['realm' => 'private', 'courseid' => 5]);
        $this->assertFalse($survey->is_public_master(5));
    }

    /**
     * Asserts is_public_master() returns false when the course id does not match.
     *
     * @covers \mod_questionnaire\survey::is_public_master
     */
    public function test_is_public_master_false_when_different_course(): void {
        $survey = $this->make_survey(['realm' => 'public', 'courseid' => 5]);
        $this->assertFalse($survey->is_public_master(99));
    }

    /**
     * Asserts is_owned_by_course() returns true when the course id matches.
     *
     * @covers \mod_questionnaire\survey::is_owned_by_course
     */
    public function test_is_owned_by_course_true_when_same(): void {
        $this->assertTrue($this->make_survey(['courseid' => 7])->is_owned_by_course(7));
    }

    /**
     * Asserts is_owned_by_course() returns false when the course id does not match.
     *
     * @covers \mod_questionnaire\survey::is_owned_by_course
     */
    public function test_is_owned_by_course_false_when_different(): void {
        $this->assertFalse($this->make_survey(['courseid' => 7])->is_owned_by_course(8));
    }

    // Tests for to_stdclass().

    /**
     * Asserts to_stdclass() returns a stdClass with the expected field values.
     *
     * @covers \mod_questionnaire\survey::to_stdclass
     */
    public function test_to_stdclass_contains_expected_fields(): void {
        $obj = $this->make_survey(['title' => 'T', 'realm' => 'public'])->to_stdclass();
        $this->assertInstanceOf(\stdClass::class, $obj);
        $this->assertEquals('T', $obj->title);
        $this->assertEquals('public', $obj->realm);
    }

    // Tests for static position helpers (pure, no DB).

    /**
     * Asserts get_parent_positions() returns an empty array when no questions have dependencies.
     *
     * @covers \mod_questionnaire\survey::get_parent_positions
     */
    public function test_get_parent_positions_empty_when_no_deps(): void {
        $questions = [
            1 => $this->make_stub_question(1, 1),
            2 => $this->make_stub_question(2, 2),
        ];
        $this->assertSame([], survey::get_parent_positions($questions));
    }

    /**
     * Asserts get_parent_positions() maps child id to parent position.
     *
     * @covers \mod_questionnaire\survey::get_parent_positions
     */
    public function test_get_parent_positions_returns_parent_position(): void {
        $dep = (object)['dependquestionid' => 1, 'dependchoiceid' => 1, 'dependlogic' => 1];
        $questions = [
            1 => $this->make_stub_question(1, 5),
            2 => $this->make_stub_question(2, 10, [$dep]),
        ];
        $result = survey::get_parent_positions($questions);
        $this->assertArrayHasKey(2, $result);
        $this->assertEquals(5, $result[2]);
    }

    /**
     * Asserts get_parent_positions() uses the highest parent position when a child has multiple parents.
     *
     * @covers \mod_questionnaire\survey::get_parent_positions
     */
    public function test_get_parent_positions_highest_parent_wins(): void {
        $dep1 = (object)['dependquestionid' => 1, 'dependchoiceid' => 1, 'dependlogic' => 1];
        $dep2 = (object)['dependquestionid' => 2, 'dependchoiceid' => 1, 'dependlogic' => 1];
        $questions = [
            1 => $this->make_stub_question(1, 3),
            2 => $this->make_stub_question(2, 7),
            3 => $this->make_stub_question(3, 10, [$dep1, $dep2]),
        ];
        $result = survey::get_parent_positions($questions);
        $this->assertEquals(7, $result[3]);
    }

    /**
     * Asserts get_child_positions() returns an empty array when no questions have dependencies.
     *
     * @covers \mod_questionnaire\survey::get_child_positions
     */
    public function test_get_child_positions_empty_when_no_deps(): void {
        $questions = [
            1 => $this->make_stub_question(1, 1),
            2 => $this->make_stub_question(2, 2),
        ];
        $this->assertSame([], survey::get_child_positions($questions));
    }

    /**
     * Asserts get_child_positions() maps parent id to child position.
     *
     * @covers \mod_questionnaire\survey::get_child_positions
     */
    public function test_get_child_positions_returns_child_position(): void {
        $dep = (object)['dependquestionid' => 1, 'dependchoiceid' => 1, 'dependlogic' => 1];
        $questions = [
            1 => $this->make_stub_question(1, 5),
            2 => $this->make_stub_question(2, 10, [$dep]),
        ];
        $result = survey::get_child_positions($questions);
        $this->assertArrayHasKey(1, $result);
        $this->assertEquals(10, $result[1]);
    }

    /**
     * Asserts get_child_positions() uses the lowest child position when a parent has multiple children.
     *
     * @covers \mod_questionnaire\survey::get_child_positions
     */
    public function test_get_child_positions_lowest_child_wins(): void {
        $dep = (object)['dependquestionid' => 1, 'dependchoiceid' => 1, 'dependlogic' => 1];
        $questions = [
            1 => $this->make_stub_question(1, 2),
            2 => $this->make_stub_question(2, 5, [$dep]),
            3 => $this->make_stub_question(3, 3, [$dep]),
        ];
        $result = survey::get_child_positions($questions);
        $this->assertEquals(3, $result[1]);
    }

    // DB-backed tests.

    /**
     * Asserts from_sid() returns a zero-id survey when the given sid does not exist.
     *
     * @covers \mod_questionnaire\survey::from_sid
     */
    public function test_from_sid_returns_empty_survey_for_nonexistent_sid(): void {
        $survey = survey::from_sid(0);
        $this->assertEquals(0, $survey->id());
    }

    /**
     * Asserts from_sid() returns a populated survey for a real survey id.
     *
     * @covers \mod_questionnaire\survey::from_sid
     */
    public function test_from_sid_returns_survey_for_real_sid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);

        $sid = $questionnaire->surveyid();
        $this->assertGreaterThan(0, $sid);

        $survey = survey::from_sid($sid);
        $this->assertEquals($sid, $survey->id());
    }

    /**
     * Asserts delete_pagebreaks() removes soft-deleted pagebreak rows while leaving other rows intact.
     *
     * @covers \mod_questionnaire\survey::delete_pagebreaks
     */
    public function test_delete_pagebreaks_removes_deleted_pagebreak_questions(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $sid = $questionnaire->surveyid();

        // Insert a soft-deleted pagebreak question.
        $pbid = $DB->insert_record('questionnaire_question', [
            'surveyid' => $sid,
            'typeid'   => QUESPAGEBREAK,
            'position' => 1,
            'deleted'  => time(),
            'content'  => '',
        ]);

        // Insert a non-deleted regular question that must not be removed.
        $regularid = $DB->insert_record('questionnaire_question', [
            'surveyid' => $sid,
            'typeid'   => QUESYESNO,
            'position' => 2,
            'deleted'  => null,
            'content'  => 'Q?',
        ]);

        survey::delete_pagebreaks($sid);

        $this->assertFalse($DB->record_exists('questionnaire_question', ['id' => $pbid]));
        $this->assertTrue($DB->record_exists('questionnaire_question', ['id' => $regularid]));
    }
}
