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
 * Unit tests for mod_questionnaire\submission_notifier.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\submission_notifier.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\submission_notifier
 */
final class submission_notifier_test extends \advanced_testcase {
    /**
     * Create a course, a questionnaire instance, and an enrolled student. Return both the
     * questionnaire domain object and the student so callers can wire up further state.
     *
     * @param array $instanceopts Extra options to pass to create_instance().
     * @return array [questionnaire, student]
     */
    private function setup_with_student(array $instanceopts = []): array {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->get_plugin_generator('mod_questionnaire')
            ->create_instance(array_merge(['course' => $course->id], $instanceopts));

        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        return [$instance, $student];
    }

    /**
     * Insert a complete response and return its id.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return int
     */
    private function insert_response(int $questionnaireid, int $userid): int {
        global $DB;
        return $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaireid,
            'userid' => $userid,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);
    }

    /**
     * get_notifiable_users() returns every enrolled user with the capability except the submitter.
     */
    public function test_get_notifiable_users_excludes_submitter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        [$questionnaire, $student] = $this->setup_with_student();
        // Enrol a teacher who will receive notifications.
        $teacher = $generator->create_user();
        $teacherrole = $generator->create_role();
        assign_capability(
            'mod/questionnaire:submissionnotification',
            CAP_ALLOW,
            $teacherrole,
            $questionnaire->context()->id
        );
        $generator->role_assign($teacherrole, $teacher->id, $questionnaire->context()->id);
        $generator->enrol_user($teacher->id, $questionnaire->courseid());

        $notifier = new submission_notifier($questionnaire);
        $users = $notifier->get_notifiable_users($student->id);

        $this->assertArrayHasKey($teacher->id, $users);
        $this->assertArrayNotHasKey($student->id, $users);
    }

    /**
     * get_notifiable_users() returns an empty array when nobody has the notification capability.
     */
    public function test_get_notifiable_users_empty_when_nobody_capable(): void {
        $this->resetAfterTest();
        [$questionnaire, $student] = $this->setup_with_student();
        // The student does not have mod/questionnaire:submissionnotification by default.

        $notifier = new submission_notifier($questionnaire);
        $this->assertSame([], $notifier->get_notifiable_users($student->id));
    }

    /**
     * notify() does nothing when the survey has no email recipient and notifications are off.
     */
    public function test_notify_noop_with_no_recipients(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $student] = $this->setup_with_student();
        $rid = $this->insert_response($questionnaire->id(), $student->id);

        $emailsink = $this->redirectEmails();
        $messagesink = $this->redirectMessages();

        $notifier = new submission_notifier($questionnaire);
        $this->assertTrue($notifier->notify($rid));

        $this->assertCount(0, $emailsink->get_messages());
        $this->assertCount(0, $messagesink->get_messages());
        $emailsink->close();
        $messagesink->close();
    }

    /**
     * notify() sends an email to every address in the survey's configured recipient list.
     */
    public function test_notify_sends_email_to_survey_recipients(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $student] = $this->setup_with_student();
        // Add an email recipient list to the survey row.
        $DB->set_field(
            'questionnaire_survey',
            'email',
            'first@example.com,second@example.com',
            ['id' => $questionnaire->surveyid()]
        );

        $rid = $this->insert_response($questionnaire->id(), $student->id);

        $emailsink = $this->redirectEmails();

        // Reload the questionnaire so the survey object picks up the email.
        $reloaded = questionnaire::from_instanceid($questionnaire->id());
        (new submission_notifier($reloaded))->notify($rid);

        $emails = $emailsink->get_messages();
        $this->assertCount(2, $emails);
        $addresses = array_map(fn($m) => $m->to, $emails);
        $this->assertContains('first@example.com', $addresses);
        $this->assertContains('second@example.com', $addresses);
        $emailsink->close();
    }

    /**
     * notify() dispatches a Moodle message to capable users when notifications are enabled.
     */
    public function test_notify_sends_messages_when_notifications_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        [$questionnaire, $student] = $this->setup_with_student();
        // Enable notifications mode 1 (subject-only message).
        $DB->set_field('questionnaire', 'notifications', 1, ['id' => $questionnaire->id()]);

        // Enrol a teacher with the notification capability.
        $teacher = $generator->create_user();
        $teacherrole = $generator->create_role();
        assign_capability(
            'mod/questionnaire:submissionnotification',
            CAP_ALLOW,
            $teacherrole,
            $questionnaire->context()->id
        );
        $generator->role_assign($teacherrole, $teacher->id, $questionnaire->context()->id);
        $generator->enrol_user($teacher->id, $questionnaire->courseid());

        $rid = $this->insert_response($questionnaire->id(), $student->id);

        // The notifier reads $USER->id as the submitter.
        $this->setUser($student);
        $messagesink = $this->redirectMessages();

        $reloaded = questionnaire::from_instanceid($questionnaire->id());
        (new submission_notifier($reloaded))->notify($rid);

        $messages = $messagesink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($teacher->id, $messages[0]->useridto);
        $messagesink->close();
    }
}
