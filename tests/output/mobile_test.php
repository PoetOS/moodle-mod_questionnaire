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
 * Unit tests for the mobile WS response builder.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

/**
 * Tests for mod_questionnaire\output\mobile::mobile_view_activity().
 *
 * Asserts the shape of the WS response payload (templates / javascript /
 * otherdata / files). Acts as a guard against accidental changes to the
 * payload contract that the Moodle Mobile app consumes.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\mobile::mobile_view_activity
 */
final class mobile_test extends \advanced_testcase {
    /**
     * Build a questionnaire with a single yes/no question owned by the current user.
     *
     * @return \mod_questionnaire\questionnaire
     */
    private function build_questionnaire(): \mod_questionnaire\questionnaire {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        return $generator->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESYESNO,
            ['content' => 'Yes or no?'],
            []
        );
    }

    /**
     * Build a questionnaire (as admin) with one text question and the given number of enrolled students.
     *
     * @param int $count
     * @return array The questionnaire followed by the student records.
     */
    private function questionnaire_with_students(int $count): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESTEXT,
            ['content' => 'Your answer?'],
            []
        );
        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $students[] = $this->getDataGenerator()->create_and_enrol($questionnaire->course(), 'student');
        }
        return array_merge([$questionnaire], $students);
    }

    /**
     * Generate a response by the given user, answering every question.
     *
     * @param \mod_questionnaire\questionnaire $questionnaire
     * @param \stdClass $user
     * @param bool $complete
     * @return int The response id.
     */
    private function make_response(\mod_questionnaire\questionnaire $questionnaire, \stdClass $user, bool $complete): int {
        $response = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            (int)$user->id,
            $complete
        );
        return (int)$response['id'];
    }

    /**
     * Call mobile_view_activity() for the questionnaire with the given extra args.
     *
     * @param \mod_questionnaire\questionnaire $questionnaire
     * @param array $args
     * @return array
     */
    private function view(\mod_questionnaire\questionnaire $questionnaire, array $args): array {
        return mobile::mobile_view_activity(array_merge([
            'cmid' => $questionnaire->coursemodule()->id,
            'appversioncode' => 44000,
        ], $args));
    }

    /**
     * Assert the invariant payload shape emitted by mobile_view_activity().
     *
     * @param array $result
     * @return void
     */
    private function assert_payload_shape(array $result): void {
        $this->assertArrayHasKey('templates', $result);
        $this->assertArrayHasKey('javascript', $result);
        $this->assertArrayHasKey('otherdata', $result);
        $this->assertArrayHasKey('files', $result);
        $this->assertIsArray($result['templates']);
        $this->assertCount(1, $result['templates']);
        $this->assertSame('main', $result['templates'][0]['id']);
        $this->assertIsString($result['templates'][0]['html']);
        $this->assertIsString($result['javascript']);
        $this->assertIsArray($result['otherdata']);
        $this->assertNull($result['files']);
    }

    /**
     * The default 'index' action returns a payload with the documented shape.
     */
    public function test_mobile_view_activity_index_returns_expected_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $generator->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'index',
            'appversioncode' => 44000,
        ]);

        $this->assertIsArray($result);
        $this->assert_payload_shape($result);
    }

    /**
     * The 'respond' action returns the same payload shape when there is no in-progress response.
     */
    public function test_mobile_view_activity_respond_returns_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'respond',
            'pagenum'        => 1,
            'appversioncode' => 44000,
        ]);

        $this->assert_payload_shape($result);
    }

    /**
     * The 'review' action for a submitted response returns the expected payload shape.
     */
    public function test_mobile_view_activity_review_returns_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $submission = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            (int) $student->id,
            true
        );
        $this->setUser($student);

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'review',
            'submissionid'   => $submission['id'],
            'appversioncode' => 44000,
        ]);

        $this->assert_payload_shape($result);
    }

    /**
     * Reviewing returns the answers of the student's own submission.
     *
     * @covers \mod_questionnaire\output\mobile::handle_review_action
     */
    public function test_review_returns_own_answers(): void {
        $this->resetAfterTest();
        [$questionnaire, $student] = $this->questionnaire_with_students(1);
        $own = $this->make_response($questionnaire, $student, true);
        $this->setUser($student);

        $result = $this->view($questionnaire, ['action' => 'review', 'submissionid' => $own]);

        $this->assertNotEmpty($result['otherdata']);
    }

    /**
     * Reviewing another student's submission returns none of their answers.
     *
     * @covers \mod_questionnaire\output\mobile::handle_review_action
     */
    public function test_review_rejects_another_students_submission(): void {
        $this->resetAfterTest();
        [$questionnaire, $student, $victim] = $this->questionnaire_with_students(2);
        $this->make_response($questionnaire, $student, true);
        $victimrid = $this->make_response($questionnaire, $victim, true);
        $this->setUser($student);

        $result = $this->view($questionnaire, ['action' => 'review', 'submissionid' => $victimrid]);

        $this->assertSame([], $result['otherdata']);
    }

    /**
     * Continuing another student's in-progress response is refused.
     *
     * @covers \mod_questionnaire\output\mobile::handle_page_action
     */
    public function test_page_action_rejects_another_students_response(): void {
        $this->resetAfterTest();
        [$questionnaire, $student, $victim] = $this->questionnaire_with_students(2);
        $victimrid = $this->make_response($questionnaire, $victim, false);
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        $this->view($questionnaire, ['action' => 'submit', 'submit' => 1, 'rid' => $victimrid]);
    }

    /**
     * A userid in the args is ignored: the submission is saved for the current user.
     *
     * @covers \mod_questionnaire\output\mobile::mobile_view_activity
     */
    public function test_submit_ignores_userid_arg(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $student, $victim] = $this->questionnaire_with_students(2);
        $this->setUser($student);

        $this->view($questionnaire, ['action' => 'submit', 'submit' => 1, 'rid' => 0, 'userid' => $victim->id]);

        $this->assertFalse($DB->record_exists('questionnaire_response', ['userid' => $victim->id]));
        $this->assertTrue($DB->record_exists('questionnaire_response', ['userid' => $student->id, 'complete' => 'y']));
    }

    /**
     * A user who can view but not submit cannot save a response through the app, even by naming a user who can.
     *
     * @covers \mod_questionnaire\output\mobile::handle_page_action
     */
    public function test_submit_needs_submit_capability(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $viewer, $victim] = $this->questionnaire_with_students(2);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'qviewer']);
        assign_capability('mod/questionnaire:submit', CAP_PROHIBIT, $roleid, $questionnaire->context()->id);
        role_assign($roleid, $viewer->id, $questionnaire->context()->id);
        $this->setUser($viewer);

        $this->view($questionnaire, ['action' => 'submit', 'submit' => 1, 'rid' => 0, 'userid' => $victim->id]);

        $this->assertFalse($DB->record_exists('questionnaire_response', []));
    }

    /**
     * An unknown action still returns the invariant payload shape (falls through the switch).
     */
    public function test_mobile_view_activity_unknown_action_returns_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'no-such-action',
            'appversioncode' => 44000,
        ]);

        $this->assert_payload_shape($result);
    }
}
