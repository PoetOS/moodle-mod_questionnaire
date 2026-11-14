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

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

/**
 * Tests that responses are only saved into the current user's own responses.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class response_ownership_test extends \advanced_testcase {
    /**
     * Create a questionnaire answered by another user, as a student who can answer it.
     *
     * The plugin caches capabilities for the whole request, so the student is set up before the questionnaire is created.
     *
     * @return array [questionnaire, the other user's response, the student]
     */
    private function setup_answered_questionnaire(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/questionnaire:submit', CAP_ALLOW, $studentrole, \context_course::instance($course->id)->id);
        $this->setUser($student);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questiondata = ['content' => 'Enter some text', 'length' => 0, 'precise' => 5];
        $questionnaire = $generator->create_test_questionnaire($course, QUESESSAY, $questiondata);
        $question = reset($questionnaire->questions);
        $response = $generator->create_question_response($questionnaire, $question, 'Not yours.', $other->id);

        return [$questionnaire, $response, $student];
    }

    /**
     * Submit through the web service as the current user. Only call this from a test that runs on its own.
     *
     * @param \questionnaire $questionnaire
     * @param int $userid
     * @param int $rid
     */
    private function submit_by_web_service(\questionnaire $questionnaire, int $userid, int $rid): void {
        global $CFG;
        // Core only allows its old external library to load in a test that runs on its own.
        require_once($CFG->dirroot . '/mod/questionnaire/externallib.php');
        \mod_questionnaire\external::submit_questionnaire_response(
            $questionnaire->id,
            $questionnaire->sid,
            $userid,
            $questionnaire->cm->id,
            1,
            0,
            $rid,
            1,
            '',
            []
        );
    }

    /**
     * Only an empty id or the user's own unfinished response to this questionnaire is accepted.
     *
     * @covers \questionnaire::require_own_incomplete_response
     */
    public function test_require_own_incomplete_response(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, $response, $student] = $this->setup_answered_questionnaire();
        $DB->set_field('questionnaire_response', 'complete', 'n', ['id' => $response->id]);

        // Nothing to check for a new response, or for the owner's unfinished one.
        $questionnaire->require_own_incomplete_response(0, $student->id);
        $questionnaire->require_own_incomplete_response($response->id, $response->userid);

        $rejected = [
            'another user' => fn() => $questionnaire->require_own_incomplete_response($response->id, $student->id),
            'finished' => function () use ($DB, $questionnaire, $response) {
                $DB->set_field('questionnaire_response', 'complete', 'y', ['id' => $response->id]);
                $questionnaire->require_own_incomplete_response($response->id, $response->userid);
            },
            'another questionnaire' => function () use ($DB, $questionnaire, $response) {
                $DB->set_field('questionnaire_response', 'complete', 'n', ['id' => $response->id]);
                $DB->set_field('questionnaire_response', 'questionnaireid', $questionnaire->id + 1, ['id' => $response->id]);
                $questionnaire->require_own_incomplete_response($response->id, $response->userid);
            },
        ];
        foreach ($rejected as $case => $call) {
            try {
                $call();
                $this->fail("Accepted a response from $case.");
            } catch (\moodle_exception $e) {
                $this->assertEquals('invalidresponse', $e->errorcode, $case);
            }
        }
    }

    /**
     * The web service saves for the current user, whatever user id it is given.
     *
     * @runInSeparateProcess
     * @covers \mod_questionnaire\external::submit_questionnaire_response
     */
    public function test_web_service_ignores_userid(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, $response, $student] = $this->setup_answered_questionnaire();

        $this->submit_by_web_service($questionnaire, $response->userid, 0);

        $this->assertEquals(1, $DB->count_records('questionnaire_response', ['userid' => $response->userid]));
        $own = ['questionnaireid' => $questionnaire->id, 'userid' => $student->id, 'complete' => 'y'];
        $this->assertTrue($DB->record_exists('questionnaire_response', $own));
    }

    /**
     * The web service will not save into another user's response.
     *
     * @runInSeparateProcess
     * @covers \mod_questionnaire\external::submit_questionnaire_response
     */
    public function test_web_service_rejects_other_users_rid(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, $response, $student] = $this->setup_answered_questionnaire();
        $DB->set_field('questionnaire_response', 'complete', 'n', ['id' => $response->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidresponse', 'questionnaire'));
        $this->submit_by_web_service($questionnaire, $student->id, $response->id);
    }

    /**
     * The web service will not save when the user cannot answer the questionnaire.
     *
     * @runInSeparateProcess
     * @covers \mod_questionnaire\external::submit_questionnaire_response
     */
    public function test_web_service_rejects_closed_questionnaire(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, , $student] = $this->setup_answered_questionnaire();
        $DB->set_field('questionnaire', 'closedate', time() - HOURSECS, ['id' => $questionnaire->id]);

        try {
            $this->submit_by_web_service($questionnaire, $student->id, 0);
            $this->fail('Saved a response to a closed questionnaire.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('questionnaire_response', ['userid' => $student->id]));
    }
}
