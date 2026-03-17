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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/lib.php');
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
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
            'qtype'            => QUESTIONNAIREUNLIMITED,
            'respondenttype'   => 'fullname',
            'respeligible'     => 'all',
            'respview'         => QUESTIONNAIRE_STUDENTVIEWRESPONSES_NEVER,
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

    // Tests for survey_is_public() and survey_is_template().

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

    // Tests for survey_is_public_master() and is_survey_owner().

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

    // Tests for user_time_for_new_attempt().

    /**
     * Asserts user_time_for_new_attempt() always returns true for unlimited questionnaires.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_unlimited_always_allowed(): void {
        $q = $this->make_questionnaire(['qtype' => QUESTIONNAIREUNLIMITED]);
        // UNLIMITED always returns true regardless of existing responses.
        $this->assertTrue($q->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns true when no prior responses exist.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_with_no_previous_responses(): void {
        $this->resetAfterTest();
        // Any qtype returns true when the user has no prior responses.
        $q = $this->make_questionnaire(['id' => 88881, 'qtype' => QUESTIONNAIREONCE]);
        $this->assertTrue($q->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for ONCE when a prior response exists.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_once_blocked_by_existing_response(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88882;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => QUESTIONNAIREONCE]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time() - 3600, 'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertFalse($q->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for DAILY when a same-day response exists.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_daily_blocked_same_day(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88883;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => QUESTIONNAIREDAILY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertFalse($q->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns true for DAILY when the prior response is from a previous day.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_daily_allowed_previous_day(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88884;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => QUESTIONNAIREDAILY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => mktime(0, 0, 0, date('n'), date('j') - 1, date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertTrue($q->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for WEEKLY when a same-week response exists.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_weekly_blocked_same_week(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88885;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => QUESTIONNAIREWEEKLY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time() - DAYSECS, 'complete' => 'y', 'grade' => 0,
        ]);

        // Blocked because the attempt was in the same ISO week.
        if (date('W', time() - DAYSECS) === date('W', time())) {
            $this->assertFalse($q->user_time_for_new_attempt(1));
        } else {
            // Test ran across a week boundary — skip rather than fail spuriously.
            $this->markTestSkipped('Response crossed a week boundary during test execution.');
        }
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for MONTHLY when a same-month response exists.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_monthly_blocked_same_month(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88886;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => QUESTIONNAIREMONTHLY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            // First of this month.
            'submitted' => mktime(0, 0, 0, date('n'), 1, date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertFalse($q->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns true for MONTHLY when the prior response is from a previous month.
     *
     * @covers \mod_questionnaire\questionnaire::user_time_for_new_attempt
     */
    public function test_user_time_monthly_allowed_previous_month(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88887;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => QUESTIONNAIREMONTHLY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            // First of last month.
            'submitted' => mktime(0, 0, 0, date('n') - 1, 1, date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertTrue($q->user_time_for_new_attempt(1));
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

    /*
     * TODO: tests requiring a real course module and context.
     *
     * The following methods need has_capability() and therefore a real CM:
     * user_is_eligible(), user_can_take(), can_read_own_responses(),
     * can_view_single_response(), can_delete_responses(), can_download_responses(),
     * can_manage_questionnaire(), can_edit_questions(),
     * can_view_all_responses(), can_view_all_responses_anytime(),
     * can_view_all_responses_with_restrictions(),
     * user_access_messages() when questionnaire is inactive (uses can_manage_questionnaire).
     *
     * These tests should be written once the generator is updated to use the
     * current DB column names (respeligible, respview, etc.) and the add_instance
     * pathway is working correctly with the refactored schema.
     */
}
