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
 * Unit tests for mod_questionnaire\local\survey\survey.
 *
 * Pure business-logic tests use survey_testable (no DB). Tests that require
 * a real survey record use the DB with resetAfterTest.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\survey;

use mod_questionnaire\local\db\survey_record;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/tests/local/survey/survey_testable.php');

/**
 * Unit tests for mod_questionnaire\local\survey\survey.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\survey\survey
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
     * Build a minimal stub question with a required() accessor for has_required() tests.
     *
     * @param bool $required
     * @return object
     */
    private function make_required_stub(bool $required): object {
        return new class ($required) {
            /** @var bool */
            private bool $req;
            /**
             * Constructor.
             *
             * @param bool $required
             */
            public function __construct(bool $required) {
                $this->req = $required;
            }
            /**
             * Required flag accessor.
             *
             * @return bool
             */
            public function required(): bool {
                return $this->req;
            }
        };
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
     * @covers \mod_questionnaire\local\survey\survey::title
     */
    public function test_title_returns_value_from_record(): void {
        $this->assertEquals('My title', $this->make_survey(['title' => 'My title'])->title());
    }

    /**
     * Asserts subtitle() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\local\survey\survey::subtitle
     */
    public function test_subtitle_returns_empty_string_when_null(): void {
        $this->assertSame('', $this->make_survey(['subtitle' => null])->subtitle());
    }

    /**
     * Asserts subtitle() returns the stored value when set.
     *
     * @covers \mod_questionnaire\local\survey\survey::subtitle
     */
    public function test_subtitle_returns_value_when_set(): void {
        $this->assertEquals('Sub', $this->make_survey(['subtitle' => 'Sub'])->subtitle());
    }

    /**
     * Asserts info() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\local\survey\survey::info
     */
    public function test_info_returns_empty_string_when_null(): void {
        $this->assertSame('', $this->make_survey(['info' => null])->info());
    }

    /**
     * Asserts realm() returns the stored realm string.
     *
     * @covers \mod_questionnaire\local\survey\survey::realm
     */
    public function test_realm_returns_private_by_default(): void {
        $this->assertEquals('private', $this->make_survey()->realm());
    }

    /**
     * Asserts owning_courseid() returns the course id from the record.
     *
     * @covers \mod_questionnaire\local\survey\survey::owning_courseid
     */
    public function test_owning_courseid_returns_course_id(): void {
        $this->assertEquals(42, $this->make_survey(['courseid' => 42])->owning_courseid());
    }

    /**
     * Asserts thankspage() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\local\survey\survey::thankspage
     */
    public function test_thankspage_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['thankspage' => null])->thankspage());
    }

    /**
     * Asserts thankhead() returns the stored value when set.
     *
     * @covers \mod_questionnaire\local\survey\survey::thankhead
     */
    public function test_thankhead_returns_value_when_set(): void {
        $this->assertEquals('Thank you!', $this->make_survey(['thankhead' => 'Thank you!'])->thankhead());
    }

    /**
     * Asserts thankbody() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\local\survey\survey::thankbody
     */
    public function test_thankbody_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['thankbody' => null])->thankbody());
    }

    /**
     * Asserts email() returns an empty string when the record value is null.
     *
     * @covers \mod_questionnaire\local\survey\survey::email
     */
    public function test_email_returns_empty_when_null(): void {
        $this->assertSame('', $this->make_survey(['email' => null])->email());
    }

    /**
     * Asserts email() returns the stored address when set.
     *
     * @covers \mod_questionnaire\local\survey\survey::email
     */
    public function test_email_returns_value_when_set(): void {
        $this->assertEquals('a@b.com', $this->make_survey(['email' => 'a@b.com'])->email());
    }

    /**
     * Asserts raw_field() returns the persistent value for the named column
     * (the package-private hook used by the feedback domain class).
     *
     * @covers \mod_questionnaire\local\survey\survey::raw_field
     */
    public function test_raw_field_returns_persistent_value(): void {
        $survey = $this->make_survey(['feedbacksections' => 3, 'charttype' => 'radar']);
        $this->assertSame(3, (int) $survey->raw_field('feedbacksections'));
        $this->assertSame('radar', $survey->raw_field('charttype'));
        $this->assertNull($survey->raw_field('feedbacknotes'));
    }

    // Tests for boolean state methods.

    /**
     * Asserts is_public() returns false for a private-realm survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_public
     */
    public function test_is_public_false_when_private(): void {
        $this->assertFalse($this->make_survey(['realm' => 'private'])->is_public());
    }

    /**
     * Asserts is_public() returns true for a public-realm survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_public
     */
    public function test_is_public_true_when_public(): void {
        $this->assertTrue($this->make_survey(['realm' => 'public'])->is_public());
    }

    /**
     * Asserts is_template() returns false for a private-realm survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_template
     */
    public function test_is_template_false_when_private(): void {
        $this->assertFalse($this->make_survey(['realm' => 'private'])->is_template());
    }

    /**
     * Asserts is_template() returns true for a template-realm survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_template
     */
    public function test_is_template_true_when_template(): void {
        $this->assertTrue($this->make_survey(['realm' => 'template'])->is_template());
    }

    /**
     * Asserts is_public_master() returns true when public and the course id matches.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_public_master
     */
    public function test_is_public_master_true_when_public_and_owning_course(): void {
        $survey = $this->make_survey(['realm' => 'public', 'courseid' => 5]);
        $this->assertTrue($survey->is_public_master(5));
    }

    /**
     * Asserts is_public_master() returns false when the survey is private.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_public_master
     */
    public function test_is_public_master_false_when_private(): void {
        $survey = $this->make_survey(['realm' => 'private', 'courseid' => 5]);
        $this->assertFalse($survey->is_public_master(5));
    }

    /**
     * Asserts is_public_master() returns false when the course id does not match.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_public_master
     */
    public function test_is_public_master_false_when_different_course(): void {
        $survey = $this->make_survey(['realm' => 'public', 'courseid' => 5]);
        $this->assertFalse($survey->is_public_master(99));
    }

    /**
     * Asserts is_owned_by_course() returns true when the course id matches.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_owned_by_course
     */
    public function test_is_owned_by_course_true_when_same(): void {
        $this->assertTrue($this->make_survey(['courseid' => 7])->is_owned_by_course(7));
    }

    /**
     * Asserts is_owned_by_course() returns false when the course id does not match.
     *
     * @covers \mod_questionnaire\local\survey\survey::is_owned_by_course
     */
    public function test_is_owned_by_course_false_when_different(): void {
        $this->assertFalse($this->make_survey(['courseid' => 7])->is_owned_by_course(8));
    }

    // Tests for to_stdclass().

    /**
     * Asserts to_stdclass() returns a stdClass with the expected field values.
     *
     * @covers \mod_questionnaire\local\survey\survey::to_stdclass
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
     * @covers \mod_questionnaire\local\survey\survey::get_parent_positions
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
     * @covers \mod_questionnaire\local\survey\survey::get_parent_positions
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
     * @covers \mod_questionnaire\local\survey\survey::get_parent_positions
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
     * @covers \mod_questionnaire\local\survey\survey::get_child_positions
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
     * @covers \mod_questionnaire\local\survey\survey::get_child_positions
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
     * @covers \mod_questionnaire\local\survey\survey::get_child_positions
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
     * @covers \mod_questionnaire\local\survey\survey::from_sid
     */
    public function test_from_sid_returns_empty_survey_for_nonexistent_sid(): void {
        $survey = survey::from_sid(0);
        $this->assertEquals(0, $survey->id());
    }

    /**
     * Asserts from_sid() returns a populated survey for a real survey id.
     *
     * @covers \mod_questionnaire\local\survey\survey::from_sid
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
     * @covers \mod_questionnaire\local\survey\survey::delete_pagebreaks
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

    // Tests for survey listing methods.

    /**
     * Asserts get_private_questionnaires() returns an entry keyed "private-{sid}" for a private survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::get_private_questionnaires
     */
    public function test_get_private_questionnaires_returns_entry_for_course_survey(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $sid = $questionnaire->surveyid();

        $result = survey::get_private_questionnaires($course->id);

        $this->assertArrayHasKey('private-' . $sid, $result);
        $this->assertNotEmpty($result['private-' . $sid]);
    }

    /**
     * Asserts get_public_questionnaires() includes other-course public surveys and excludes the current course's own.
     *
     * @covers \mod_questionnaire\local\survey\survey::get_public_questionnaires
     */
    public function test_get_public_questionnaires_excludes_current_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');

        $q1 = $generator->create_instance(['course' => $course1->id]);
        $generator->create_content($q1, ['realm' => 'public']);
        $sid1 = $q1->surveyid();

        $q2 = $generator->create_instance(['course' => $course2->id]);
        $generator->create_content($q2, ['realm' => 'public']);
        $sid2 = $q2->surveyid();

        $result = survey::get_public_questionnaires($course1->id);

        $this->assertArrayNotHasKey('public-' . $sid1, $result);
        $this->assertArrayHasKey('public-' . $sid2, $result);
    }

    /**
     * Asserts get_template_questionnaires() returns an entry keyed "template-{sid}" for a template survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::get_template_questionnaires
     */
    public function test_get_template_questionnaires_returns_template_survey(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');

        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $generator->create_content($questionnaire, ['realm' => 'template']);
        $sid = $questionnaire->surveyid();

        $result = survey::get_template_questionnaires($course->id);

        $this->assertArrayHasKey('template-' . $sid, $result);
        $this->assertNotEmpty($result['template-' . $sid]);
    }

    // Tests covering the update_settings allowlist persistence path.

    /**
     * Asserts update_settings() persists each allowlisted field onto the survey row.
     *
     * @covers \mod_questionnaire\local\survey\survey::update_settings
     */
    public function test_update_settings_persists_allowlisted_fields(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $sid = $questionnaire->surveyid();

        $survey = survey::from_sid($sid);
        $result = $survey->update_settings([
            'subtitle'  => 'New subtitle',
            'thankhead' => 'New thank head',
            'email'     => 'sup@example.org',
        ]);

        $this->assertSame($sid, $result);
        $row = $DB->get_record('questionnaire_survey', ['id' => $sid]);
        $this->assertEquals('New subtitle', $row->subtitle);
        $this->assertEquals('New thank head', $row->thankhead);
        $this->assertEquals('sup@example.org', $row->email);
    }

    /**
     * Asserts update_settings() rejects unknown / non-allowlisted fields.
     *
     * @covers \mod_questionnaire\local\survey\survey::update_settings
     */
    public function test_update_settings_rejects_unknown_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);

        $survey = survey::from_sid($questionnaire->surveyid());

        $this->expectException(\coding_exception::class);
        $survey->update_settings(['id' => 999]);
    }

    /**
     * Asserts update_settings() rejects a field that is not in the persistent's properties
     * (e.g. someone passing a form-only key like 'sid').
     *
     * @covers \mod_questionnaire\local\survey\survey::update_settings
     */
    public function test_update_settings_rejects_form_metadata_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);

        $survey = survey::from_sid($questionnaire->surveyid());

        $this->expectException(\coding_exception::class);
        $survey->update_settings(['sid' => $questionnaire->surveyid()]);
    }

    /**
     * Asserts update_settings() returns false when a present 'name' / 'title' / 'realm' is empty.
     *
     * @covers \mod_questionnaire\local\survey\survey::update_settings
     */
    public function test_update_settings_returns_false_for_empty_required_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);

        $survey = survey::from_sid($questionnaire->surveyid());
        $this->assertFalse($survey->update_settings(['name' => '']));
        $this->assertFalse($survey->update_settings(['title' => '']));
        $this->assertFalse($survey->update_settings(['realm' => '']));
    }

    /**
     * Asserts update_settings() returns false when renaming to a value that collides with another survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::update_settings
     */
    public function test_update_settings_returns_false_for_duplicate_name(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $q1 = $generator->create_instance(['course' => $course->id]);
        $q2 = $generator->create_instance(['course' => $course->id]);

        $survey1 = survey::from_sid($q1->surveyid());
        $survey2 = survey::from_sid($q2->surveyid());

        $survey1->update_settings(['name' => 'shared-name']);
        $this->assertFalse($survey2->update_settings(['name' => 'shared-name']));
    }

    /**
     * Asserts update_settings() does NOT silently mutate fields that were not supplied
     * (only the named keys are written).
     *
     * @covers \mod_questionnaire\local\survey\survey::update_settings
     */
    public function test_update_settings_does_not_touch_unsupplied_fields(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $sid = $questionnaire->surveyid();
        $originaltitle = $DB->get_field('questionnaire_survey', 'title', ['id' => $sid]);

        survey::from_sid($sid)->update_settings(['subtitle' => 'New subtitle']);

        $this->assertEquals($originaltitle, $DB->get_field('questionnaire_survey', 'title', ['id' => $sid]));
        $this->assertEquals('New subtitle', $DB->get_field('questionnaire_survey', 'subtitle', ['id' => $sid]));
    }

    /**
     * Asserts update_settings() rejects feedback-domain fields: they live on
     * {@see feedback::UPDATABLE_FIELDS} and must go through feedback::update_settings().
     *
     * @covers \mod_questionnaire\local\survey\survey::update_settings
     */
    public function test_update_settings_rejects_feedback_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);

        $survey = survey::from_sid($questionnaire->surveyid());

        $this->expectException(\coding_exception::class);
        $survey->update_settings(['feedbacksections' => 3]);
    }

    // Tests for has_required() (pure, no DB).

    /**
     * Asserts has_required() returns false when there are no questions.
     *
     * @covers \mod_questionnaire\local\survey\survey::has_required
     */
    public function test_has_required_false_when_no_questions(): void {
        $this->assertFalse($this->make_survey()->has_required());
        $this->assertFalse($this->make_survey()->has_required(2));
    }

    /**
     * Asserts has_required() returns true across all sections when any question is required.
     *
     * @covers \mod_questionnaire\local\survey\survey::has_required
     */
    public function test_has_required_true_across_all_sections(): void {
        $survey = $this->make_survey();
        $survey->set_questions([
            1 => $this->make_required_stub(false),
            2 => $this->make_required_stub(true),
        ]);
        $this->assertTrue($survey->has_required());
    }

    /**
     * Asserts has_required() returns false across all sections when no question is required.
     *
     * @covers \mod_questionnaire\local\survey\survey::has_required
     */
    public function test_has_required_false_across_all_sections(): void {
        $survey = $this->make_survey();
        $survey->set_questions([
            1 => $this->make_required_stub(false),
            2 => $this->make_required_stub(false),
        ]);
        $this->assertFalse($survey->has_required());
    }

    /**
     * Asserts has_required() scoped to a section only inspects that section.
     *
     * @covers \mod_questionnaire\local\survey\survey::has_required
     */
    public function test_has_required_per_section(): void {
        $survey = $this->make_survey();
        $survey->set_questions_by_sec([
            1 => [$this->make_required_stub(false)],
            2 => [$this->make_required_stub(true)],
        ]);
        $survey->set_questions([
            1 => $this->make_required_stub(false),
            2 => $this->make_required_stub(true),
        ]);
        $this->assertFalse($survey->has_required(1));
        $this->assertTrue($survey->has_required(2));
        $this->assertFalse($survey->has_required(99));
    }

    // Tests for get_all_file_areas().

    /**
     * Build a minimal question stub that supports id().
     *
     * @param int $id
     * @return object
     */
    private function make_question_stub(int $id): object {
        return new class ($id) {
            /** @var int */
            public int $id;
            /**
             * Constructor.
             * @param int $id
             */
            public function __construct(int $id) {
                $this->id = $id;
            }
            /**
             * Return the question id.
             * @return int
             */
            public function id(): int {
                return $this->id;
            }
        };
    }

    /**
     * Asserts get_all_file_areas() returns the basic area keys when no feedback sections exist.
     *
     * @covers \mod_questionnaire\local\survey\survey::get_all_file_areas
     */
    public function test_get_all_file_areas_basic(): void {
        $this->resetAfterTest();
        $sid = 1;
        $survey = $this->make_survey(['id' => $sid]);
        $survey->set_questions([10 => $this->make_question_stub(10)]);

        $areas = $survey->get_all_file_areas();
        $this->assertSame($sid, $areas['info']);
        $this->assertSame($sid, $areas['thankbody']);
        $this->assertSame($sid, $areas['feedbacknotes']);
        $this->assertContains(10, $areas['question']);
        $this->assertArrayNotHasKey('sectionheading', $areas);
        $this->assertArrayNotHasKey('feedback', $areas);
    }

    /**
     * Asserts get_all_file_areas() includes sectionheading and feedback areas when fb_sections exist.
     *
     * @covers \mod_questionnaire\local\survey\survey::get_all_file_areas
     */
    public function test_get_all_file_areas_with_feedback_sections(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 12345;
        $survey = $this->make_survey(['id' => $sid]);
        $survey->set_questions([10 => $this->make_question_stub(10)]);

        $sectionid = $DB->insert_record('questionnaire_fb_sections', (object)[
            'surveyid' => $sid, 'section' => 1, 'scorecalculation' => null,
            'sectionlabel' => 'S1', 'sectionheading' => '', 'sectionheadingformat' => FORMAT_HTML,
        ]);
        $feedbackid = $DB->insert_record('questionnaire_feedback', (object)[
            'sectionid' => $sectionid, 'feedbacklabel' => '', 'feedbacktext' => '',
            'feedbacktextformat' => FORMAT_HTML, 'minscore' => 0, 'maxscore' => 100,
        ]);

        $areas = $survey->get_all_file_areas();
        $this->assertArrayHasKey('sectionheading', $areas);
        $this->assertContains((int)$sectionid, $areas['sectionheading']);
        $this->assertArrayHasKey('feedback', $areas);
        $this->assertContains((int)$feedbackid, $areas['feedback']);
    }

    // Tests for soft_delete_question() and reorder_questions() (manage-questions rebuild).

    /**
     * Build a real questionnaire (course + instance) for the soft_delete_question /
     * reorder_questions tests, which need genuine DB-backed questions and dependencies.
     *
     * @return \mod_questionnaire\questionnaire
     */
    private function make_real_questionnaire(): \mod_questionnaire\questionnaire {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        return \mod_questionnaire\questionnaire::from_instanceid($questionnaire->id());
    }

    /**
     * Add a real question to a real questionnaire at an explicit position.
     *
     * The generator's own position assignment (count of already-loaded questions) starts
     * at 0 for the first question added to an empty questionnaire, which does not match
     * this suite's 1-based position assertions — so the position is pinned explicitly via
     * question_record::update_position() straight after creation, decoupling these tests
     * from that generator quirk.
     *
     * @param \mod_questionnaire\questionnaire $questionnaire
     * @param string $name
     * @param int $position 1-based position to pin the question at.
     * @param int $typeid Question type; defaults to a plain yes/no question.
     * @return int The new question id.
     */
    private function add_real_question(
        \mod_questionnaire\questionnaire $questionnaire,
        string $name,
        int $position,
        int $typeid = QUESYESNO
    ): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $record = [
            'surveyid' => $questionnaire->surveyid(),
            'name' => $name,
            'typeid' => $typeid,
        ];
        if ($typeid == QUESPAGEBREAK) {
            $record['content'] = 'break';
        }
        $question = $generator->create_question($questionnaire, $record);
        \mod_questionnaire\local\db\question_record::update_position($question->id(), $position);
        return $question->id();
    }

    /**
     * soft_delete_question() marks a regular question deleted, closes the position gap,
     * removes its dependency records, deletes its response data, and fires question_deleted.
     *
     * @covers \mod_questionnaire\local\survey\survey::soft_delete_question
     */
    public function test_soft_delete_question_marks_deleted_and_shifts_positions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $q1 = $this->add_real_question($questionnaire, 'Q1', 1);
        $q2 = $this->add_real_question($questionnaire, 'Q2', 2);
        $q3 = $this->add_real_question($questionnaire, 'Q3', 3);

        $sink = $this->redirectEvents();
        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $removed = $survey->soft_delete_question($q2, $questionnaire->id());

        $this->assertTrue($removed);
        $this->assertNotNull($DB->get_field('questionnaire_question', 'deleted', ['id' => $q2]));
        $this->assertEquals(1, $DB->get_field('questionnaire_question', 'position', ['id' => $q1]));
        $this->assertEquals(2, $DB->get_field('questionnaire_question', 'position', ['id' => $q3]));

        $events = array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof \mod_questionnaire\event\question_deleted
        );
        $this->assertCount(1, $events);
    }

    /**
     * soft_delete_question() hard-deletes a page break (it carries no responses) rather
     * than soft-deleting it.
     *
     * @covers \mod_questionnaire\local\survey\survey::soft_delete_question
     */
    public function test_soft_delete_question_hard_deletes_pagebreak(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $q1 = $this->add_real_question($questionnaire, 'Q1', 1);
        $pb = $this->add_real_question($questionnaire, 'break', 2, QUESPAGEBREAK);
        $q2 = $this->add_real_question($questionnaire, 'Q2', 3);

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $this->assertTrue($survey->soft_delete_question($pb, $questionnaire->id()));

        $this->assertFalse($DB->record_exists('questionnaire_question', ['id' => $pb]));
        $this->assertEquals(1, $DB->get_field('questionnaire_question', 'position', ['id' => $q1]));
        $this->assertEquals(2, $DB->get_field('questionnaire_question', 'position', ['id' => $q2]));
    }

    /**
     * soft_delete_question() purges every response for the questionnaire once the survey's
     * last active question is removed — pins the bug fix (the legacy questions.php code
     * purged by question id instead of questionnaire id, so this never actually fired).
     *
     * @covers \mod_questionnaire\local\survey\survey::soft_delete_question
     */
    public function test_soft_delete_question_purges_responses_when_survey_empties(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $q1 = $this->add_real_question($questionnaire, 'Q1', 1);

        $rid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $survey->soft_delete_question($q1, $questionnaire->id());

        $this->assertFalse($DB->record_exists('questionnaire_response', ['id' => $rid]));
    }

    /**
     * soft_delete_question() returns false and makes no changes for an id that is not an
     * active question in the survey.
     *
     * @covers \mod_questionnaire\local\survey\survey::soft_delete_question
     */
    public function test_soft_delete_question_returns_false_for_unknown_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $this->add_real_question($questionnaire, 'Q1', 1);

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $this->assertFalse($survey->soft_delete_question(999999, $questionnaire->id()));
    }

    /**
     * reorder_questions() rewrites positions to match the submitted order.
     *
     * @covers \mod_questionnaire\local\survey\survey::reorder_questions
     */
    public function test_reorder_questions_rewrites_positions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $q1 = $this->add_real_question($questionnaire, 'Q1', 1);
        $q2 = $this->add_real_question($questionnaire, 'Q2', 2);
        $q3 = $this->add_real_question($questionnaire, 'Q3', 3);

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $msg = $survey->reorder_questions([$q3, $q1, $q2]);

        $this->assertIsString($msg);
        $this->assertEquals(1, $DB->get_field('questionnaire_question', 'position', ['id' => $q3]));
        $this->assertEquals(2, $DB->get_field('questionnaire_question', 'position', ['id' => $q1]));
        $this->assertEquals(3, $DB->get_field('questionnaire_question', 'position', ['id' => $q2]));
    }

    /**
     * reorder_questions() rejects an id list that does not match the survey's active
     * question set (a dropped or extra id) without changing any positions.
     *
     * @covers \mod_questionnaire\local\survey\survey::reorder_questions
     */
    public function test_reorder_questions_rejects_wrong_set(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $q1 = $this->add_real_question($questionnaire, 'Q1', 1);
        $q2 = $this->add_real_question($questionnaire, 'Q2', 2);

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $this->expectException(\moodle_exception::class);
        try {
            $survey->reorder_questions([$q1]);
        } finally {
            $this->assertEquals(1, $DB->get_field('questionnaire_question', 'position', ['id' => $q1]));
            $this->assertEquals(2, $DB->get_field('questionnaire_question', 'position', ['id' => $q2]));
        }
    }

    /**
     * reorder_questions() rejects an order that would place a question at or before a
     * question it depends on.
     *
     * @covers \mod_questionnaire\local\survey\survey::reorder_questions
     */
    public function test_reorder_questions_rejects_parent_after_child(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $parent = $this->add_real_question($questionnaire, 'Parent', 1);
        $child = $this->add_real_question($questionnaire, 'Child', 2);

        $dependency = new \mod_questionnaire\local\db\dependency_record(0, (object)[
            'questionid' => $child,
            'surveyid' => $questionnaire->surveyid(),
            'dependquestionid' => $parent,
        ]);
        $dependency->create();

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $this->expectException(\moodle_exception::class);
        $survey->reorder_questions([$child, $parent]);
    }

    /**
     * reorder_questions() rejects an order with a page break in the first position.
     *
     * @covers \mod_questionnaire\local\survey\survey::reorder_questions
     */
    public function test_reorder_questions_rejects_leading_pagebreak(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $q1 = $this->add_real_question($questionnaire, 'Q1', 1);
        $pb = $this->add_real_question($questionnaire, 'break', 2, QUESPAGEBREAK);

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        $this->expectException(\moodle_exception::class);
        $survey->reorder_questions([$pb, $q1]);
    }

    /**
     * reorder_questions() runs check_page_breaks() after persisting the new order and
     * surfaces its message, so a caller-visible pagebreak repair is never silent.
     *
     * @covers \mod_questionnaire\local\survey\survey::reorder_questions
     */
    public function test_reorder_questions_runs_check_page_breaks(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->make_real_questionnaire();
        $q1 = $this->add_real_question($questionnaire, 'Q1', 1);
        $child = $this->add_real_question($questionnaire, 'Child', 2);
        $q3 = $this->add_real_question($questionnaire, 'Q3', 3);

        $dependency = new \mod_questionnaire\local\db\dependency_record(0, (object)[
            'questionid' => $child,
            'surveyid' => $questionnaire->surveyid(),
            'dependquestionid' => $q1,
        ]);
        $dependency->create();

        $survey = survey::from_sid($questionnaire->surveyid(), $questionnaire->context());
        // Valid order (parent still precedes child): no dependency violation, but the new
        // adjacency may prompt check_page_breaks() to adjust breaks — either way it must run.
        $msg = $survey->reorder_questions([$q1, $child, $q3]);
        $this->assertIsString($msg);
    }
}
