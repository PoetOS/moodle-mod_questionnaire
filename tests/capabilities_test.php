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

    /**
     * Build a questionnaire with a file question and a student's uploaded file answer.
     *
     * Others may never view responses (the generator default), so only the response-anytime capability lets staff in.
     *
     * @return array [questionnaire, owner, response id, response file record id]
     */
    private function make_file_answer(): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESFILE,
            ['content' => 'Upload a file'],
            null
        );
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $questions = $questionnaire->questions();
        $question = reset($questions);

        $owner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($owner);
        $rid = \mod_questionnaire\local\response\response::create($questionnaire->id(), $owner->id)->id();
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'answer.pdf',
        ], 'answer');
        $recordid = $question->insert_response((object)['rid' => $rid, 'q' . $question->id() => $draftitemid]);
        return [$questionnaire, $owner, $rid, $recordid];
    }

    /**
     * Only the owner, once they have submitted, and staff who may read responses can view an uploaded response file.
     *
     * @covers \mod_questionnaire\capabilities::can_view_response_file
     */
    public function test_can_view_response_file(): void {
        $this->resetAfterTest();
        [$questionnaire, $owner, $rid, $recordid] = $this->make_file_answer();
        $course = $questionnaire->course();
        $classmate = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($owner);
        $this->assertFalse($questionnaire->capabilities()->can_view_response_file($recordid));
        $questionnaire->responses()->response_commit($rid);
        $this->assertTrue($questionnaire->capabilities()->can_view_response_file($recordid));

        $this->setUser($classmate);
        $this->assertFalse($questionnaire->capabilities()->can_view_response_file($recordid));

        $this->setUser($teacher);
        $this->assertTrue($questionnaire->capabilities()->can_view_response_file($recordid));
        $this->assertFalse($questionnaire->capabilities()->can_view_response_file($recordid + 1000));
    }

    /**
     * Another questionnaire's response file cannot be viewed through this questionnaire.
     *
     * @covers \mod_questionnaire\capabilities::can_view_response_file
     */
    public function test_can_view_response_file_refuses_other_questionnaires_file(): void {
        $this->resetAfterTest();
        [, , , $recordid] = $this->make_file_answer();
        $this->setAdminUser();
        $other = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $this->getDataGenerator()->create_course()->id]);
        $this->assertFalse($other->capabilities()->can_view_response_file($recordid));
    }

    /**
     * The pluginfile callback refuses another student's response file, even when the file name is known.
     *
     * @covers ::questionnaire_pluginfile
     */
    public function test_pluginfile_refuses_another_students_response_file(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/questionnaire/lib.php');
        $this->resetAfterTest();
        [$questionnaire, , , $recordid] = $this->make_file_answer();
        $this->setUser($this->getDataGenerator()->create_and_enrol($questionnaire->course(), 'student'));

        $this->assertFalse(questionnaire_pluginfile(
            $questionnaire->course(),
            $questionnaire->coursemodule(),
            $questionnaire->context(),
            'response_file',
            [$recordid, 'answer.pdf'],
            true,
            []
        ));
    }

    /**
     * Build a questionnaire in the given group mode with submitted responses from students in group A, group B
     * and no group.
     *
     * @param int $groupmode
     * @param int $respview
     * @return array [questionnaire, course, group A, response ids and students, each keyed 'a', 'b' and 'none']
     */
    private function make_group_responses(int $groupmode, int $respview = 0): array {
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $questionnaire = $dg->get_plugin_generator('mod_questionnaire')->create_instance([
            'course' => $course->id,
            'groupmode' => $groupmode,
            'respview' => $respview,
        ]);
        $groupa = $dg->create_group(['courseid' => $course->id]);
        $groupb = $dg->create_group(['courseid' => $course->id]);
        $rids = [];
        $students = [];
        foreach (['a' => $groupa, 'b' => $groupb, 'none' => null] as $key => $group) {
            $students[$key] = $dg->create_and_enrol($course, 'student');
            if ($group) {
                $dg->create_group_member(['groupid' => $group->id, 'userid' => $students[$key]->id]);
            }
            $rids[$key] = \mod_questionnaire\local\response\response::create($questionnaire->id(), $students[$key]->id)->id();
            $questionnaire->responses()->response_commit($rids[$key]);
        }
        return [questionnaire::from_instanceid($questionnaire->id()), $course, $groupa, $rids, $students];
    }

    /**
     * In separate groups, staff without access to all groups only see responses from members of their groups.
     *
     * @covers \mod_questionnaire\capabilities::can_view_response
     */
    public function test_can_view_response_separate_groups_staff(): void {
        $this->resetAfterTest();
        [$questionnaire, $course, $groupa, $rids] = $this->make_group_responses(SEPARATEGROUPS);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->setUser($teacher);
        $this->assertTrue($questionnaire->capabilities()->can_view_response($rids['a']));
        $this->assertFalse($questionnaire->capabilities()->can_view_response($rids['b']));
        $this->assertFalse($questionnaire->capabilities()->can_view_response($rids['none']));

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        foreach ($rids as $rid) {
            $this->assertTrue($questionnaire->capabilities()->can_view_response($rid));
        }
    }

    /**
     * In visible groups, group membership does not limit which responses staff can view.
     *
     * @covers \mod_questionnaire\capabilities::can_view_response
     */
    public function test_can_view_response_visible_groups_staff(): void {
        $this->resetAfterTest();
        [$questionnaire, $course, $groupa, $rids] = $this->make_group_responses(VISIBLEGROUPS);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->setUser($teacher);
        foreach ($rids as $rid) {
            $this->assertTrue($questionnaire->capabilities()->can_view_response($rid));
        }
    }

    /**
     * In separate groups, a student allowed to view all individual responses sees their own and their group mates' only.
     *
     * @covers \mod_questionnaire\capabilities::can_view_response
     */
    public function test_can_view_response_separate_groups_student(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $course, $groupa, $rids, $students] = $this->make_group_responses(
            SEPARATEGROUPS,
            questionnaire::RESPVIEW_ALWAYS
        );
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/questionnaire:viewsingleresponse', CAP_ALLOW, $studentrole, $questionnaire->context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $mate = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $mate->id]);
        $this->setUser($mate);
        $this->assertTrue($questionnaire->capabilities()->can_view_response($rids['a']));
        $this->assertFalse($questionnaire->capabilities()->can_view_response($rids['b']));
        $this->assertFalse($questionnaire->capabilities()->can_view_response($rids['none']));

        // A student with no group still sees their own response.
        $this->setUser($students['none']);
        $this->assertTrue($questionnaire->capabilities()->can_view_response($rids['none']));
        $this->assertFalse($questionnaire->capabilities()->can_view_response($rids['a']));
    }

    /**
     * A student allowed to view all responses sees summaries only: other students' individual responses need the
     * individual response capability, while their own stays visible.
     *
     * @covers \mod_questionnaire\capabilities::can_view_response
     */
    public function test_can_view_response_needs_viewsingleresponse_for_others(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $course, $groupa, $rids, $students] = $this->make_group_responses(
            NOGROUPS,
            questionnaire::RESPVIEW_ALWAYS
        );

        $this->setUser($students['a']);
        $this->assertTrue($questionnaire->capabilities()->can_view_all_responses());
        $this->assertTrue($questionnaire->capabilities()->can_view_response($rids['a']));
        $this->assertFalse($questionnaire->capabilities()->can_view_response($rids['b']));

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/questionnaire:viewsingleresponse', CAP_ALLOW, $studentrole, $questionnaire->context()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue($questionnaire->capabilities()->can_view_response($rids['b']));

        // Staff without the capability cannot open other users' responses either.
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('mod/questionnaire:viewsingleresponse', CAP_PROHIBIT, $teacherrole, $questionnaire->context()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertFalse($questionnaire->capabilities()->can_view_response($rids['a']));
    }
}
