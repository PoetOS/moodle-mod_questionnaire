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
 * Unit tests for mod_questionnaire\questionnaire.
 *
 * The testable subclass bypasses the constructor (which requires a real course module)
 * so that pure business logic can be tested without full Moodle fixture setup.
 *
 * Tests that require capabilities or a real context are marked TODO.
 *
 * @package    mod_questionnaire
 * @copyright  2025 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\response\questionnaire_responses;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/lib.php');
require_once($CFG->dirroot . '/mod/questionnaire/tests/questionnaire_testable.php');

/**
 * Unit tests for mod_questionnaire\questionnaire.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\questionnaire
 */
final class questionnaire_test extends \advanced_testcase {
    // Helpers.

    /**
     * Build a questionnaire_record persistent populated from an in-memory object
     * (no DB read). Pass $fields to override any default.
     *
     * @param array $fields
     * @return questionnaire_record
     */
    private function make_module_record(array $fields = []): questionnaire_record {
        return new questionnaire_record(0, (object)array_merge([
            'id'               => 1,
            'course'           => 1,
            'name'             => 'Test questionnaire',
            'intro'            => '',
            'introformat'      => FORMAT_HTML,
            'qtype'            => questionnaire::QTYPE_UNLIMITED,
            'respondenttype'   => 'fullname',
            'respeligible'     => 'all',
            'respview'         => questionnaire::RESPVIEW_NEVER,
            'notifications'    => 0,
            'opendate'         => 0,
            'closedate'        => 0,
            'resume'           => 0,
            'navigate'         => 0,
            'grade'            => 0,
            'sid'              => 1,
            'timemodified'     => 0,
            'completionsubmit' => 0,
            'autonum'          => 3,
            'progressbar'      => 0,
            'removeafter'      => 0,
        ], $fields));
    }

    /**
     * Build a survey_record persistent from an in-memory object (no DB read).
     *
     * @param array $fields
     * @return survey_record
     */
    private function make_survey_record(array $fields = []): survey_record {
        return new survey_record(0, (object)array_merge([
            'id'                  => 1,
            'name'                => 'Test survey',
            'courseid'            => 1,
            'realm'               => 'private',
            'status'              => 0,
            'title'               => '',
            'email'               => null,
            'subtitle'            => null,
            'info'                => null,
            'theme'               => null,
            'thankspage'          => null,
            'thankhead'           => null,
            'thankbody'           => null,
            'feedbacksections'    => 0,
            'feedbacknotes'       => null,
            'feedbackscores'      => 0,
            'charttype'           => null,
        ], $fields));
    }

    /**
     * Return a questionnaire_testable with optional field overrides.
     *
     * @param array $modulefields  Overrides for questionnaire_record.
     * @param array $surveyfields  Overrides for survey_record.
     * @return questionnaire_testable
     */
    private function make_questionnaire(array $modulefields = [], array $surveyfields = []): questionnaire_testable {
        if (!array_key_exists('id', $surveyfields)) {
            $surveyfields['id'] = $modulefields['sid'] ?? 1;
        }
        return new questionnaire_testable(
            $this->make_module_record($modulefields),
            $this->make_survey_record($surveyfields)
        );
    }

    // Tests for is_active().

    /**
     * Asserts is_active() returns true when a survey is linked.
     *
     * @covers \mod_questionnaire\questionnaire::is_active
     */
    public function test_is_active_when_survey_linked(): void {
        $this->assertTrue($this->make_questionnaire(['sid' => 5])->is_active());
    }

    /**
     * Asserts is_active() returns false when no survey is linked.
     *
     * @covers \mod_questionnaire\questionnaire::is_active
     */
    public function test_is_active_when_no_survey(): void {
        $this->assertFalse($this->make_questionnaire(['sid' => 0])->is_active());
    }

    // Tests for is_open().

    /**
     * Asserts is_open() returns true when no open date is set.
     *
     * @covers \mod_questionnaire\questionnaire::is_open
     */
    public function test_is_open_with_no_date(): void {
        $this->assertTrue($this->make_questionnaire(['opendate' => 0])->is_open());
    }

    /**
     * Asserts is_open() returns true when the open date is in the past.
     *
     * @covers \mod_questionnaire\questionnaire::is_open
     */
    public function test_is_open_with_past_open_date(): void {
        $this->assertTrue($this->make_questionnaire(['opendate' => time() - 3600])->is_open());
    }

    /**
     * Asserts is_open() returns false when the open date is in the future.
     *
     * @covers \mod_questionnaire\questionnaire::is_open
     */
    public function test_is_not_open_with_future_open_date(): void {
        $this->assertFalse($this->make_questionnaire(['opendate' => time() + 3600])->is_open());
    }

    // Tests for is_closed().

    /**
     * Asserts is_closed() returns false when no close date is set.
     *
     * @covers \mod_questionnaire\questionnaire::is_closed
     */
    public function test_is_not_closed_with_no_date(): void {
        $this->assertFalse($this->make_questionnaire(['closedate' => 0])->is_closed());
    }

    /**
     * Asserts is_closed() returns true when the close date is in the past.
     *
     * @covers \mod_questionnaire\questionnaire::is_closed
     */
    public function test_is_closed_with_past_close_date(): void {
        $this->assertTrue($this->make_questionnaire(['closedate' => time() - 3600])->is_closed());
    }

    /**
     * Asserts is_closed() returns false when the close date is in the future.
     *
     * @covers \mod_questionnaire\questionnaire::is_closed
     */
    public function test_is_not_closed_with_future_close_date(): void {
        $this->assertFalse($this->make_questionnaire(['closedate' => time() + 3600])->is_closed());
    }

    // Tests for is_anonymous().

    /**
     * Asserts is_anonymous() returns true when respondent type is anonymous.
     *
     * @covers \mod_questionnaire\questionnaire::is_anonymous
     */
    public function test_is_anonymous(): void {
        $this->assertTrue($this->make_questionnaire(['respondenttype' => 'anonymous'])->is_anonymous());
    }

    /**
     * Asserts is_anonymous() returns false when respondent type is fullname.
     *
     * @covers \mod_questionnaire\questionnaire::is_anonymous
     */
    public function test_is_not_anonymous(): void {
        $this->assertFalse($this->make_questionnaire(['respondenttype' => 'fullname'])->is_anonymous());
    }

    // Tests for the survey_is_public and survey_is_template state methods.

    /**
     * Asserts survey_is_public() returns correct value based on realm.
     *
     * @covers \mod_questionnaire\questionnaire::survey_is_public
     */
    public function test_survey_is_public(): void {
        $this->assertTrue($this->make_questionnaire([], ['realm' => 'public'])->survey_is_public());
        $this->assertFalse($this->make_questionnaire([], ['realm' => 'private'])->survey_is_public());
        $this->assertFalse($this->make_questionnaire([], ['realm' => 'template'])->survey_is_public());
    }

    /**
     * Asserts survey_is_template() returns correct value based on realm.
     *
     * @covers \mod_questionnaire\questionnaire::survey_is_template
     */
    public function test_survey_is_template(): void {
        $this->assertTrue($this->make_questionnaire([], ['realm' => 'template'])->survey_is_template());
        $this->assertFalse($this->make_questionnaire([], ['realm' => 'public'])->survey_is_template());
        $this->assertFalse($this->make_questionnaire([], ['realm' => 'private'])->survey_is_template());
    }

    // Tests for the survey_is_public_master and is_survey_owner state methods.

    /**
     * Asserts survey_is_public_master() returns true when courses match.
     *
     * @covers \mod_questionnaire\questionnaire::survey_is_public_master
     */
    public function test_survey_is_public_master_when_owning_course(): void {
        $q = $this->make_questionnaire(['course' => 10], ['realm' => 'public', 'courseid' => 10]);
        $this->assertTrue($q->survey_is_public_master());
    }

    /**
     * Asserts survey_is_public_master() returns false when courses differ.
     *
     * @covers \mod_questionnaire\questionnaire::survey_is_public_master
     */
    public function test_survey_is_not_public_master_when_different_course(): void {
        $q = $this->make_questionnaire(['course' => 10], ['realm' => 'public', 'courseid' => 99]);
        $this->assertFalse($q->survey_is_public_master());
    }

    /**
     * Asserts survey_is_public_master() returns false when survey is private.
     *
     * @covers \mod_questionnaire\questionnaire::survey_is_public_master
     */
    public function test_survey_is_not_public_master_when_private(): void {
        $q = $this->make_questionnaire(['course' => 10], ['realm' => 'private', 'courseid' => 10]);
        $this->assertFalse($q->survey_is_public_master());
    }

    /**
     * Asserts is_survey_owner() returns true when questionnaire and survey share course.
     *
     * @covers \mod_questionnaire\questionnaire::is_survey_owner
     */
    public function test_is_survey_owner_when_same_course(): void {
        $q = $this->make_questionnaire(['course' => 10], ['courseid' => 10]);
        $this->assertTrue($q->is_survey_owner());
    }

    /**
     * Asserts is_survey_owner() returns false when questionnaire and survey are in different courses.
     *
     * @covers \mod_questionnaire\questionnaire::is_survey_owner
     */
    public function test_is_not_survey_owner_when_different_course(): void {
        $q = $this->make_questionnaire(['course' => 10], ['courseid' => 99]);
        $this->assertFalse($q->is_survey_owner());
    }

    // Tests for user_has_saved_response().

    /**
     * Asserts user_has_saved_response() returns false when no saved response exists.
     *
     * @covers \mod_questionnaire\questionnaire::user_has_saved_response
     */
    public function test_user_has_no_saved_response(): void {
        $this->resetAfterTest();
        $q = $this->make_questionnaire(['id' => 99991]);
        $this->assertFalse($q->user_has_saved_response(1));
    }

    /**
     * Asserts user_has_saved_response() returns true when an incomplete response exists.
     *
     * @covers \mod_questionnaire\questionnaire::user_has_saved_response
     */
    public function test_user_has_saved_response(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 99992;
        $q = $this->make_questionnaire(['id' => $qid]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time(), 'complete' => 'n', 'grade' => 0,
        ]);

        $this->assertTrue($q->user_has_saved_response(1));
    }

    /**
     * Complete responses do not count as "saved" (incomplete) responses.
     * @covers \mod_questionnaire\questionnaire::user_has_saved_response
     */
    public function test_complete_response_is_not_saved_response(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 99993;
        $q = $this->make_questionnaire(['id' => $qid]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time(), 'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertFalse($q->user_has_saved_response(1));
    }

    // Tests for user_access_messages() — cases that don't reach capability checks.

    /**
     * Asserts user_access_messages() returns the template-not-viewable string for template surveys.
     *
     * @covers \mod_questionnaire\questionnaire::user_access_messages
     */
    public function test_user_access_messages_template_not_viewable(): void {
        $q = $this->make_questionnaire(['sid' => 5], ['realm' => 'template']);
        $this->assertStringContainsString(
            get_string('templatenotviewable', 'questionnaire'),
            $q->user_access_messages()
        );
    }

    /**
     * Asserts user_access_messages() includes the open date when the questionnaire is not yet open.
     *
     * @covers \mod_questionnaire\questionnaire::user_access_messages
     */
    public function test_user_access_messages_not_open_yet(): void {
        $opendate = time() + 7200;
        $q = $this->make_questionnaire(['sid' => 5, 'opendate' => $opendate]);
        $msg = $q->user_access_messages();
        $this->assertNotNull($msg);
        $this->assertStringContainsString(userdate($opendate), $msg);
    }

    /**
     * Asserts user_access_messages() includes the close date when the questionnaire is closed.
     *
     * @covers \mod_questionnaire\questionnaire::user_access_messages
     */
    public function test_user_access_messages_closed(): void {
        $closedate = time() - 3600;
        $q = $this->make_questionnaire(['sid' => 5, 'closedate' => $closedate]);
        $msg = $q->user_access_messages();
        $this->assertNotNull($msg);
        $this->assertStringContainsString(userdate($closedate), $msg);
    }

    // Tests for the navigate and pages_autonumbered accessors.

    /**
     * Asserts navigate() returns the raw integer from the module record.
     *
     * @covers \mod_questionnaire\questionnaire::navigate
     */
    public function test_navigate_returns_field_value(): void {
        $this->assertEquals(0, $this->make_questionnaire(['navigate' => 0])->navigate());
        $this->assertEquals(1, $this->make_questionnaire(['navigate' => 1])->navigate());
    }

    /**
     * Asserts pages_autonumbered() returns true only for autonum values 2 and 3.
     *
     * @covers \mod_questionnaire\questionnaire::pages_autonumbered
     */
    public function test_pages_autonumbered(): void {
        $this->assertFalse($this->make_questionnaire(['autonum' => 0])->pages_autonumbered());
        $this->assertFalse($this->make_questionnaire(['autonum' => 1])->pages_autonumbered());
        $this->assertTrue($this->make_questionnaire(['autonum' => 2])->pages_autonumbered());
        $this->assertTrue($this->make_questionnaire(['autonum' => 3])->pages_autonumbered());
    }

    // Tests for responses().

    /**
     * Asserts responses() returns a questionnaire_responses instance.
     *
     * @covers \mod_questionnaire\questionnaire::responses
     */
    public function test_responses_returns_correct_type(): void {
        $this->assertInstanceOf(questionnaire_responses::class, $this->make_questionnaire()->responses());
    }

    // Tests for get_responses().

    /**
     * Asserts get_responses() returns an empty array when no responses exist.
     *
     * @covers \mod_questionnaire\questionnaire::get_responses
     */
    public function test_get_responses_empty_when_none(): void {
        $this->resetAfterTest();
        $this->assertSame([], $this->make_questionnaire(['id' => 77771])->get_responses());
    }

    /**
     * Asserts get_responses() returns all responses for the questionnaire.
     *
     * @covers \mod_questionnaire\questionnaire::get_responses
     */
    public function test_get_responses_returns_records(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 77772;
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time(), 'complete' => 'y', 'grade' => 0,
        ]);
        $this->assertCount(1, $this->make_questionnaire(['id' => $qid])->get_responses());
    }

    /**
     * Asserts get_responses() filters by userid when one is specified.
     *
     * @covers \mod_questionnaire\questionnaire::get_responses
     */
    public function test_get_responses_filtered_by_userid(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 77773;
        $q = $this->make_questionnaire(['id' => $qid]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time(), 'complete' => 'y', 'grade' => 0,
        ]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 2,
            'submitted' => time(), 'complete' => 'y', 'grade' => 0,
        ]);
        $this->assertCount(1, $q->get_responses(1));
        $this->assertCount(1, $q->get_responses(2));
        $this->assertCount(2, $q->get_responses(false));
    }

    // Tests for get_latest_responseid().

    /**
     * Asserts get_latest_responseid() returns 0 when no responses exist.
     *
     * @covers \mod_questionnaire\questionnaire::get_latest_responseid
     */
    public function test_get_latest_responseid_returns_zero_when_none(): void {
        $this->resetAfterTest();
        $this->assertSame(0, $this->make_questionnaire(['id' => 66661])->get_latest_responseid(1));
    }

    /**
     * Asserts get_latest_responseid() returns the id of the user's incomplete response.
     *
     * @covers \mod_questionnaire\questionnaire::get_latest_responseid
     */
    public function test_get_latest_responseid_returns_id_of_incomplete(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 66662;
        $rid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time(), 'complete' => 'n', 'grade' => 0,
        ]);
        $this->assertSame((int)$rid, $this->make_questionnaire(['id' => $qid])->get_latest_responseid(1));
    }

    /**
     * Asserts get_latest_responseid() returns 0 when only complete responses exist.
     *
     * @covers \mod_questionnaire\questionnaire::get_latest_responseid
     */
    public function test_get_latest_responseid_ignores_complete_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 66663;
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time(), 'complete' => 'y', 'grade' => 0,
        ]);
        $this->assertSame(0, $this->make_questionnaire(['id' => $qid])->get_latest_responseid(1));
    }

    /**
     * Asserts get_latest_responseid() returns the most recently submitted incomplete response.
     *
     * @covers \mod_questionnaire\questionnaire::get_latest_responseid
     */
    public function test_get_latest_responseid_returns_most_recent(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 66664;
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time() - 7200, 'complete' => 'n', 'grade' => 0,
        ]);
        $latestid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time() - 3600, 'complete' => 'n', 'grade' => 0,
        ]);
        $this->assertSame((int)$latestid, $this->make_questionnaire(['id' => $qid])->get_latest_responseid(1));
    }

    // Tests for existing_response_action().

    /**
     * Asserts existing_response_action() inserts a new DB record when rid is 0.
     *
     * @covers \mod_questionnaire\questionnaire::existing_response_action
     */
    public function test_existing_response_action_inserts_when_rid_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 55551;
        $q = $this->make_questionnaire(['id' => $qid]);
        $q->set_questions_by_sec([1 => []]);
        $newrid = $q->existing_response_action((object)['rid' => 0, 'sec' => 1], 1);
        $this->assertGreaterThan(0, $newrid);
        $this->assertTrue($DB->record_exists('questionnaire_response', ['id' => $newrid, 'questionnaireid' => $qid]));
    }

    /**
     * Asserts existing_response_action() updates the submitted time on an existing record.
     *
     * @covers \mod_questionnaire\questionnaire::existing_response_action
     */
    public function test_existing_response_action_updates_existing_record(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 55552;
        $q = $this->make_questionnaire(['id' => $qid]);
        $q->set_questions_by_sec([1 => []]);
        $originaltime = time() - 3600;
        $rid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => $originaltime, 'complete' => 'n', 'grade' => 0,
        ]);
        $returnedrid = $q->existing_response_action((object)['rid' => $rid, 'sec' => 1], 1);
        $this->assertSame((int)$rid, (int)$returnedrid);
        $this->assertGreaterThan($originaltime, $DB->get_field('questionnaire_response', 'submitted', ['id' => $rid]));
    }

    // Tests for next_page_action().

    /**
     * Asserts next_page_action() returns the next section number when the response is valid.
     *
     * @covers \mod_questionnaire\questionnaire::next_page_action
     */
    public function test_next_page_action_returns_next_section_when_valid(): void {
        $this->resetAfterTest();
        // With no questions, response_check_format returns '' so the action proceeds.
        $q = $this->make_questionnaire(['id' => 44441, 'navigate' => 0]);
        $q->set_questions_by_sec([1 => []]);
        $result = $q->next_page_action((object)['rid' => 0, 'sec' => 1], 1);
        // With no dependencies, next_page(1, 0) increments to 2.
        $this->assertSame(2, $result);
    }

    // Tests for previous_page_action().

    /**
     * Asserts previous_page_action() saves the response and returns the previous section number.
     *
     * @covers \mod_questionnaire\questionnaire::previous_page_action
     */
    public function test_previous_page_action_returns_previous_section(): void {
        $this->resetAfterTest();
        $q = $this->make_questionnaire(['id' => 44442, 'navigate' => 0]);
        $q->set_questions_by_sec([1 => [], 2 => []]);
        $result = $q->previous_page_action((object)['rid' => 0, 'sec' => 2], 1);
        // With no dependencies, prev_page(2, 0) returns 1.
        $this->assertSame(1, $result);
    }

    /**
     * Asserts previous_page_action() returns false when already on the first section.
     *
     * @covers \mod_questionnaire\questionnaire::previous_page_action
     */
    public function test_previous_page_action_returns_false_on_first_section(): void {
        $this->resetAfterTest();
        $q = $this->make_questionnaire(['id' => 44443, 'navigate' => 0]);
        $q->set_questions_by_sec([1 => []]);
        $result = $q->previous_page_action((object)['rid' => 0, 'sec' => 1], 1);
        // Prev_page(1, 0) decrements to 0, which returns false.
        $this->assertFalse($result);
    }

    /*
     * TODO: tests for get_notifiable_users().
     *
     * get_notifiable_users() requires get_enrolled_users() and groups_get_activity_groupmode(),
     * which need a real course module and context. These tests should be added once the
     * generator creates instances through the full add_instance pathway.
     */

    // Tests for get_all_file_areas().

    /**
     * Asserts get_all_file_areas() returns the basic area keys when no feedback sections exist.
     *
     * @covers \mod_questionnaire\questionnaire::get_all_file_areas
     */
    public function test_get_all_file_areas_basic(): void {
        $this->resetAfterTest();
        $sid = 1;
        $q = $this->make_questionnaire([], ['id' => $sid]);
        $stub = $this->make_stub_question(10, []);
        $q->set_questions([10 => $stub]);
        $areas = $q->get_all_file_areas();
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
     * @covers \mod_questionnaire\questionnaire::get_all_file_areas
     */
    public function test_get_all_file_areas_with_feedback_sections(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 12345;
        $q = $this->make_questionnaire([], ['id' => $sid]);
        $stub = $this->make_stub_question(10, []);
        $q->set_questions([10 => $stub]);

        $sectionid = $DB->insert_record('questionnaire_fb_sections', (object)[
            'surveyid' => $sid, 'section' => 1, 'scorecalculation' => null,
            'sectionlabel' => 'S1', 'sectionheading' => '', 'sectionheadingformat' => FORMAT_HTML,
        ]);
        $feedbackid = $DB->insert_record('questionnaire_feedback', (object)[
            'sectionid' => $sectionid, 'feedbacklabel' => '', 'feedbacktext' => '',
            'feedbacktextformat' => FORMAT_HTML, 'minscore' => 0, 'maxscore' => 100,
        ]);

        $areas = $q->get_all_file_areas();
        $this->assertArrayHasKey('sectionheading', $areas);
        $this->assertContains((int)$sectionid, $areas['sectionheading']);
        $this->assertArrayHasKey('feedback', $areas);
        $this->assertContains((int)$feedbackid, $areas['feedback']);
    }

    // Helpers for dependency tests.

    /**
     * Create a minimal question stub with the given id and dependencies array.
     *
     * Uses an anonymous class to avoid instantiating the real question hierarchy
     * (which requires a DB-backed record).
     *
     * @param int $id
     * @param array $dependencies Keyed dependency stdClass objects (dependquestionid etc.)
     * @return object
     */
    private function make_stub_question(int $id, array $dependencies): object {
        return new class ($id, $dependencies) {
            /** @var int */
            public int $id;
            /** @var array */
            public array $dependencies;
            /** @var int */
            public int $position = 0;
            /** @var string */
            public string $name = '';
            /** @var string */
            public string $content = '';
            /** @var int */
            public int $typeid = 0;

            /**
             * Constructor.
             * @param int $id
             * @param array $dependencies
             */
            public function __construct(int $id, array $dependencies) {
                $this->id = $id;
                $this->dependencies = $dependencies;
            }

            /**
             * True if dependencies are non-empty.
             * @return bool
             */
            public function has_dependencies(): bool {
                return !empty($this->dependencies);
            }

            /**
             * Return the question id.
             * @return int
             */
            public function id(): int {
                return $this->id;
            }

            /**
             * Always fulfilled when no dependencies are set (used in navigation tests).
             * @param mixed $rid
             * @param mixed $questions
             * @return bool
             */
            public function dependency_fulfilled($rid, $questions): bool {
                return !$this->has_dependencies();
            }
        };
    }

    // Tests for the default_page_count, confirm_delete_param and restore_param behavior helpers.

    /**
     * Asserts default_page_count() returns the expected default value of 20.
     *
     * @covers \mod_questionnaire\questionnaire::default_page_count
     */
    public function test_default_page_count_returns_twenty(): void {
        $this->assertEquals(20, questionnaire::default_page_count());
    }

    /**
     * Asserts confirm_delete_param() returns the expected URL parameter string.
     *
     * @covers \mod_questionnaire\questionnaire::confirm_delete_param
     */
    public function test_confirm_delete_param_returns_expected_string(): void {
        $this->assertEquals(questionnaire::CONFIRM_DELETE_PERMANENTLY, questionnaire::confirm_delete_param());
    }

    /**
     * Asserts restore_param() returns the expected URL parameter string.
     *
     * @covers \mod_questionnaire\questionnaire::restore_param
     */
    public function test_restore_param_returns_expected_string(): void {
        $this->assertEquals(questionnaire::RESTORE_PARAM, questionnaire::restore_param());
    }

    // Tests for question_deletion_duration().

    /**
     * Asserts question_deletion_duration() returns the value from plugin config.
     *
     * @covers \mod_questionnaire\questionnaire::question_deletion_duration
     */
    public function test_question_deletion_duration_returns_configured_value(): void {
        $this->resetAfterTest();
        set_config('duration', 7 * DAYSECS, 'questionnaire_questiondeletion');
        $this->assertEquals(7 * DAYSECS, survey::question_deletion_duration());
    }
}
