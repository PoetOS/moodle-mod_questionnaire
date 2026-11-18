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
 * Tests that users only see the individual responses they are allowed to see.
 *
 * The plugin caches capabilities for the whole request, so each test runs on its own.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class response_view_test extends \advanced_testcase {
    /**
     * Create a questionnaire answered by a student and another user, as the given role.
     *
     * The user is set up before the questionnaire is created, because the plugin caches capabilities for the whole request.
     *
     * @param string $role The role of the user viewing the responses.
     * @return array [questionnaire, the viewing user's response id or 0, the other user's response id]
     */
    private function setup_answered_questionnaire(string $role): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user = $this->getDataGenerator()->create_and_enrol($course, $role);
        $coursecontext = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => $role]);
        $capabilities = ($role == 'student') ? ['submit', 'readownresponses'] : ['readallresponseanytime'];
        foreach ($capabilities as $capability) {
            assign_capability('mod/questionnaire:' . $capability, CAP_ALLOW, $roleid, $coursecontext->id);
        }
        $this->setUser($user);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questiondata = ['content' => 'Enter some text', 'length' => 0, 'precise' => 5];
        $questionnaire = $generator->create_test_questionnaire($course, QUESESSAY, $questiondata);
        $question = reset($questionnaire->questions);
        $ownrid = 0;
        if ($role == 'student') {
            $ownrid = $generator->create_question_response($questionnaire, $question, 'Mine.', $user->id)->id;
        }
        $otherrid = $generator->create_question_response($questionnaire, $question, 'Not yours.', $other->id)->id;

        return [$questionnaire, $ownrid, $otherrid];
    }

    /**
     * A student can view their own response, but not another user's or one from another questionnaire.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::can_view_response
     */
    public function test_can_view_response_as_student(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, $ownrid, $otherrid] = $this->setup_answered_questionnaire('student');

        $this->assertTrue($questionnaire->can_view_response($ownrid));
        $this->assertFalse($questionnaire->can_view_response($otherrid));
        $this->assertFalse($questionnaire->can_view_response($otherrid + 1000));
        $DB->set_field('questionnaire_response', 'questionnaireid', $questionnaire->id + 1, ['id' => $ownrid]);
        $this->assertFalse($questionnaire->can_view_response($ownrid));
    }

    /**
     * A teacher who can read all responses can view another user's response, but not one from another questionnaire.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::can_view_response
     */
    public function test_can_view_response_as_teacher(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, , $otherrid] = $this->setup_answered_questionnaire('editingteacher');

        $this->assertTrue($questionnaire->can_view_response($otherrid));
        $DB->set_field('questionnaire_response', 'questionnaireid', $questionnaire->id + 1, ['id' => $otherrid]);
        $this->assertFalse($questionnaire->can_view_response($otherrid));
    }

    /**
     * A public survey's master instance can view the responses of the instances that use its survey; other instances and
     * unrelated questionnaires stay apart.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::can_view_response
     */
    public function test_can_view_response_for_public_survey_instances(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, , $masterrid] = $this->setup_answered_questionnaire('editingteacher');
        $DB->set_field('questionnaire_survey', 'realm', 'public', ['id' => $questionnaire->sid]);
        $master = new \questionnaire($questionnaire->course, $questionnaire->cm, $questionnaire->id);
        $this->assertTrue($master->survey_is_public_master());

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $othercourse = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($othercourse, 'student');
        $created = $generator->create_instance(['course' => $othercourse->id]);
        $DB->set_field('questionnaire', 'sid', $questionnaire->sid, ['id' => $created->id]);
        $instance = new \questionnaire($othercourse, $created->cm, $created->id);
        $instancerid = $generator->create_question_response(
            $instance,
            reset($instance->questions),
            'From another course.',
            $student->id
        )->id;

        $questiondata = ['content' => 'Enter some text', 'length' => 0, 'precise' => 5];
        $unrelated = $generator->create_test_questionnaire($othercourse, QUESESSAY, $questiondata);
        $unrelatedrid = $generator->create_question_response(
            $unrelated,
            reset($unrelated->questions),
            'Unrelated.',
            $student->id
        )->id;

        $this->assertTrue($master->can_view_response($masterrid));
        $this->assertTrue($master->can_view_response($instancerid));
        $this->assertFalse($master->can_view_response($unrelatedrid));
        $this->assertTrue($instance->can_view_response($instancerid));
        $this->assertFalse($instance->can_view_response($masterrid));
    }

    /**
     * A student who cannot view all responses can print their own response, but cannot open anything else on the report.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::can_open_report
     */
    public function test_can_open_report_as_student(): void {
        $this->resetAfterTest();

        [$questionnaire, $ownrid, $otherrid] = $this->setup_answered_questionnaire('student');

        $this->assertTrue($questionnaire->can_open_report('vresp', 'print', $ownrid));
        $this->assertFalse($questionnaire->can_open_report('vresp', 'print', $otherrid));
        $this->assertFalse($questionnaire->can_open_report('vresp', 'print', false));
        $this->assertFalse($questionnaire->can_open_report('vresp', 'html', $ownrid));
        $this->assertFalse($questionnaire->can_open_report('vresp', 'pdf', $ownrid));
        $this->assertFalse($questionnaire->can_open_report('vall', 'print', $ownrid));
        $this->assertFalse($questionnaire->can_open_report('vall', 'html', false));
    }

    /**
     * A teacher who can read all responses can open the report, but not for a response from another questionnaire.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::can_open_report
     */
    public function test_can_open_report_as_teacher(): void {
        global $DB;
        $this->resetAfterTest();

        [$questionnaire, , $otherrid] = $this->setup_answered_questionnaire('editingteacher');

        $this->assertTrue($questionnaire->can_open_report('vall', 'html', false));
        $this->assertTrue($questionnaire->can_open_report('vresp', 'html', $otherrid));
        $this->assertTrue($questionnaire->can_open_report('vresp', 'print', $otherrid));
        $DB->set_field('questionnaire_response', 'questionnaireid', $questionnaire->id + 1, ['id' => $otherrid]);
        $this->assertFalse($questionnaire->can_open_report('vresp', 'html', $otherrid));
        $this->assertFalse($questionnaire->can_open_report('vresp', 'print', $otherrid));
    }

    /**
     * Showing another user's response to a student is refused.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::view_response
     */
    public function test_view_response_refuses_other_users_response(): void {
        global $PAGE, $SESSION;
        $this->resetAfterTest();

        [$questionnaire, , $otherrid] = $this->setup_answered_questionnaire('student');
        // The questions are drawn using the session's current tab and a global questionnaire, as on the report page.
        $SESSION->questionnaire = (object)['current_tab' => 'individualresp'];
        $GLOBALS['questionnaire'] = $questionnaire;
        $questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
        $questionnaire->add_page(new \mod_questionnaire\output\reportpage());

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopermissions', 'questionnaire'));
        $questionnaire->view_response($otherrid);
    }

    /**
     * The app will not show a student another user's submission.
     *
     * @runInSeparateProcess
     * @covers \mod_questionnaire\output\mobile::mobile_view_activity
     */
    public function test_mobile_review_refuses_other_users_response(): void {
        $this->resetAfterTest();

        [$questionnaire, , $otherrid] = $this->setup_answered_questionnaire('student');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopermissions', 'questionnaire'));
        \mod_questionnaire\output\mobile::mobile_view_activity([
            'cmid' => $questionnaire->cm->id,
            'action' => 'review',
            'appversioncode' => 44000,
            'submissionid' => $otherrid,
        ]);
    }
}
