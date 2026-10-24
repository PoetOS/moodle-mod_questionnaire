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
 * Unit tests for the mod_questionnaire capabilities helper.
 *
 * @package    mod_questionnaire
 * @copyright  2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/lib.php');
require_once($CFG->dirroot . '/mod/questionnaire/tests/questionnaire_testable.php');

/**
 * Unit tests for {@see capabilities}.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\capabilities
 */
final class capabilities_test extends \advanced_testcase {
    /**
     * Build a questionnaire_record persistent populated from an in-memory object (no DB read).
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

    // Tests for user_time_for_new_attempt().

    /**
     * Asserts user_time_for_new_attempt() always returns true for unlimited questionnaires.
     */
    public function test_user_time_unlimited_always_allowed(): void {
        $q = $this->make_questionnaire(['qtype' => questionnaire::QTYPE_UNLIMITED]);
        $this->assertTrue($q->capabilities()->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns true when no prior responses exist.
     */
    public function test_user_time_with_no_previous_responses(): void {
        $this->resetAfterTest();
        $q = $this->make_questionnaire(['id' => 88881, 'qtype' => questionnaire::QTYPE_ONCE]);
        $this->assertTrue($q->capabilities()->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for ONCE when a prior response exists.
     */
    public function test_user_time_once_blocked_by_existing_response(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88882;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => questionnaire::QTYPE_ONCE]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time() - 3600, 'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertFalse($q->capabilities()->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for DAILY when a same-day response exists.
     */
    public function test_user_time_daily_blocked_same_day(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88883;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => questionnaire::QTYPE_DAILY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertFalse($q->capabilities()->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns true for DAILY when the prior response is from a previous day.
     */
    public function test_user_time_daily_allowed_previous_day(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88884;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => questionnaire::QTYPE_DAILY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => mktime(0, 0, 0, date('n'), date('j') - 1, date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertTrue($q->capabilities()->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for WEEKLY when a same-week response exists.
     */
    public function test_user_time_weekly_blocked_same_week(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88885;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => questionnaire::QTYPE_WEEKLY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => time() - DAYSECS, 'complete' => 'y', 'grade' => 0,
        ]);

        // Blocked because the attempt was in the same ISO week.
        if (date('W', time() - DAYSECS) === date('W', time())) {
            $this->assertFalse($q->capabilities()->user_time_for_new_attempt(1));
        } else {
            $this->markTestSkipped('Response crossed a week boundary during test execution.');
        }
    }

    /**
     * Asserts user_time_for_new_attempt() returns false for MONTHLY when a same-month response exists.
     */
    public function test_user_time_monthly_blocked_same_month(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88886;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => questionnaire::QTYPE_MONTHLY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => mktime(0, 0, 0, date('n'), 1, date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertFalse($q->capabilities()->user_time_for_new_attempt(1));
    }

    /**
     * Asserts user_time_for_new_attempt() returns true for MONTHLY when the prior response is from a previous month.
     */
    public function test_user_time_monthly_allowed_previous_month(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 88887;
        $q = $this->make_questionnaire(['id' => $qid, 'qtype' => questionnaire::QTYPE_MONTHLY]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => mktime(0, 0, 0, date('n') - 1, 1, date('Y')),
            'complete' => 'y', 'grade' => 0,
        ]);

        $this->assertTrue($q->capabilities()->user_time_for_new_attempt(1));
    }

    // Tests for capability methods (require a real course module and context).

    /**
     * Asserts can_manage_questionnaire() returns true for an editing teacher and false for a student.
     */
    public function test_can_manage_questionnaire_teacher_vs_student(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->assertTrue($instance->capabilities()->can_manage_questionnaire($teacher->id));
        $this->assertFalse($instance->capabilities()->can_manage_questionnaire($student->id));
    }

    /**
     * Asserts can_edit_questions() returns true for an editing teacher and false for a student.
     */
    public function test_can_edit_questions_teacher_vs_student(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->assertTrue($instance->capabilities()->can_edit_questions($teacher->id));
        $this->assertFalse($instance->capabilities()->can_edit_questions($student->id));
    }

    /**
     * Asserts can_view() returns true for the current user when set to a student.
     */
    public function test_can_view_returns_true_for_enrolled_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->setUser($student);
        $this->assertTrue($instance->capabilities()->can_view());
    }

    /**
     * Asserts can_preview() returns true for a teacher and false for a student.
     */
    public function test_can_preview_teacher_vs_student(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->setUser($teacher);
        $this->assertTrue($instance->capabilities()->can_preview());
        $this->setUser($student);
        $this->assertFalse($instance->capabilities()->can_preview());
    }

    /**
     * Asserts user_is_eligible() returns true for an enrolled student and false for a guest.
     */
    public function test_user_is_eligible_enrolled_vs_unenrolled(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $student = $generator->create_user();
        $guest = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->assertTrue($instance->capabilities()->user_is_eligible($student->id));
        $this->assertFalse($instance->capabilities()->user_is_eligible($guest->id));
    }

    /**
     * Asserts can_read_own_responses() returns true for a student (default role capability).
     */
    public function test_can_read_own_responses_for_enrolled_student(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->assertTrue($instance->capabilities()->can_read_own_responses($student->id));
    }

    /**
     * Asserts can_delete_responses() returns true for an editing teacher and false for a student.
     */
    public function test_can_delete_responses_teacher_vs_student(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->assertTrue($instance->capabilities()->can_delete_responses($teacher->id));
        $this->assertFalse($instance->capabilities()->can_delete_responses($student->id));
    }

    /**
     * Asserts can_download_responses() returns true for a teacher and false for a student.
     */
    public function test_can_download_responses_teacher_vs_student(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->assertTrue($instance->capabilities()->can_download_responses($teacher->id));
        $this->assertFalse($instance->capabilities()->can_download_responses($student->id));
    }

    /**
     * Asserts can_view_single_response() returns true for a teacher and false for a student.
     */
    public function test_can_view_single_response_teacher_vs_student(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')->create_instance(['course' => $course->id]);

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $this->assertTrue($instance->capabilities()->can_view_single_response($teacher->id));
        $this->assertFalse($instance->capabilities()->can_view_single_response($student->id));
    }
}
