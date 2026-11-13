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
 * Tests for the Moodle App handler and the response paths it uses.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class mobile_test extends \advanced_testcase {
    /** @var string A response id that is not a number. */
    const BAD_RID = 'x1';

    /**
     * Create a two-page questionnaire whose first page is answered by another user, as a student who can answer it.
     *
     * The plugin caches capabilities for the whole request, so the student is set up before the questionnaire is created.
     *
     * @return array [questionnaire, the other user's response id, the student]
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
        $generator->create_question($questionnaire, ['surveyid' => $questionnaire->sid, 'name' => 'B', 'type_id' => QUESPAGEBREAK]);
        $generator->create_question($questionnaire, ['surveyid' => $questionnaire->sid, 'name' => 'Q2', 'type_id' => QUESESSAY] +
            $questiondata);
        // The generator numbers positions from 0, but a page break at position 1 is ignored, so number them as the editor does.
        $DB->execute('UPDATE {questionnaire_question} SET position = position + 1 WHERE surveyid = ?', [$questionnaire->sid]);
        $response = $generator->create_question_response($questionnaire, $question, 'Not yours.', $other->id);

        return [$questionnaire, $response->id, $student];
    }

    /**
     * A response id from the app that is not a number is cleaned.
     *
     * Runs on its own because the plugin caches capabilities for the whole request.
     *
     * @runInSeparateProcess
     * @covers \mod_questionnaire\output\mobile::mobile_view_activity
     */
    public function test_mobile_view_activity_cleans_rid(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, $otherrid, $student] = $this->setup_answered_questionnaire();

        \mod_questionnaire\output\mobile::mobile_view_activity([
            'cmid' => $questionnaire->cm->id,
            'action' => 'nextpage',
            'pagenum' => 1,
            'appversioncode' => 44000,
            'rid' => self::BAD_RID,
        ]);

        $own = ['questionnaireid' => $questionnaire->id, 'userid' => $student->id];
        $this->assertTrue($DB->record_exists('questionnaire_response', $own));
        $this->assertTrue($DB->record_exists('questionnaire_response_text', ['response_id' => $otherrid]));
    }

    /**
     * Deleting a page of answers with a response id that is not a number leaves other answers alone.
     *
     * @covers \questionnaire::response_delete
     */
    public function test_response_delete_cleans_rid(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, $otherrid] = $this->setup_answered_questionnaire();

        $method = new \ReflectionMethod($questionnaire, 'response_delete');
        $method->setAccessible(true);
        $method->invoke($questionnaire, self::BAD_RID, 1);

        $this->assertTrue($DB->record_exists('questionnaire_response_text', ['response_id' => $otherrid]));
    }
}
