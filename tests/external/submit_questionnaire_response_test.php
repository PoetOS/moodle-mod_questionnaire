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
 * Unit tests for mod_questionnaire\external\submit_questionnaire_response.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\external;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\external\submit_questionnaire_response.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\external\submit_questionnaire_response
 */
final class submit_questionnaire_response_test extends \advanced_testcase {
    /**
     * Submitting saves a completed response for the current user.
     */
    public function test_submit_saves_response_for_current_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $student] = $this->questionnaire_with_students(1);
        $this->setUser($student);

        $result = $this->submit($questionnaire, (int)$student->id, 0);

        $this->assertTrue($result['submitted']);
        $this->assertTrue($DB->record_exists('questionnaire_response', ['userid' => $student->id, 'complete' => 'y']));
    }

    /**
     * The userid parameter is ignored: naming another user still saves for the current user.
     */
    public function test_submit_ignores_userid_parameter(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $student, $victim] = $this->questionnaire_with_students(2);
        $this->setUser($student);

        $this->submit($questionnaire, (int)$victim->id, 0);

        $this->assertFalse($DB->record_exists('questionnaire_response', ['userid' => $victim->id]));
        $this->assertTrue($DB->record_exists('questionnaire_response', ['userid' => $student->id]));
    }

    /**
     * Continuing another user's in-progress response is refused and leaves it untouched.
     */
    public function test_submit_rejects_another_users_response(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $student, $victim] = $this->questionnaire_with_students(2);
        $victimrid = $this->make_response($questionnaire, $victim, false);
        $this->setUser($student);

        try {
            $this->submit($questionnaire, (int)$victim->id, $victimrid);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidresponse', $e->errorcode);
        }
        $this->assertSame('n', $DB->get_field('questionnaire_response', 'complete', ['id' => $victimrid]));
    }

    /**
     * Submitting to a closed questionnaire is refused.
     */
    public function test_submit_rejects_closed_questionnaire(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $student] = $this->questionnaire_with_students(1);
        $DB->set_field('questionnaire', 'closedate', time() - HOURSECS, ['id' => $questionnaire->id()]);
        $this->setUser($student);

        try {
            $this->submit($questionnaire, (int)$student->id, 0);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('questionnaire_response', []));
    }

    /**
     * Call the web service to submit an empty response.
     *
     * @param questionnaire $questionnaire
     * @param int $userid The userid parameter to send.
     * @param int $rid
     * @return array
     */
    private function submit(questionnaire $questionnaire, int $userid, int $rid): array {
        return submit_questionnaire_response::execute(
            $questionnaire->id(),
            $questionnaire->surveyid(),
            $userid,
            $questionnaire->coursemodule()->id,
            1,
            0,
            $rid,
            1,
            '',
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
            $students[] = $this->getDataGenerator()->create_and_enrol($course, 'student');
        }
        return array_merge([$questionnaire], $students);
    }

    /**
     * Generate a response by the given user, answering every question.
     *
     * @param questionnaire $questionnaire
     * @param \stdClass $user
     * @param bool $complete
     * @return int The response id.
     */
    private function make_response(questionnaire $questionnaire, \stdClass $user, bool $complete): int {
        $response = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            (int)$user->id,
            $complete
        );
        return (int)$response['id'];
    }
}
