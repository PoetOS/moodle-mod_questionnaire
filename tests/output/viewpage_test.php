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
 * Unit tests for mod_questionnaire\output\viewpage.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;
use moodle_url;

/**
 * Unit tests for mod_questionnaire\output\viewpage.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\viewpage
 */
final class viewpage_test extends \advanced_testcase {
    /** @var \stdClass The test course. */
    private \stdClass $course;

    /**
     * Fetch the plugin renderer.
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('mod_questionnaire');
    }

    /**
     * Create a course and a questionnaire in it, with one yes/no question unless told otherwise.
     *
     * @param bool $withquestion
     * @return questionnaire
     */
    private function build_questionnaire(bool $withquestion = true): questionnaire {
        $this->course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        if ($withquestion) {
            $instance = $generator->create_test_questionnaire($this->course, QUESYESNO, ['content' => 'Yes or no?'], []);
        } else {
            $instance = $generator->create_instance(['course' => $this->course->id]);
        }
        return questionnaire::from_instanceid($instance->id());
    }

    /**
     * Create a user enrolled in the test course with the given role, and make them current.
     *
     * @param string $role Role shortname.
     * @return int The user id.
     */
    private function login_as(string $role): int {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, $role);
        $this->setUser($user);
        return (int)$user->id;
    }

    /**
     * Add a response by the user to the questionnaire.
     *
     * @param questionnaire $questionnaire
     * @param int $userid
     * @param bool $complete False leaves a saved, unsubmitted response.
     */
    private function add_response(questionnaire $questionnaire, int $userid, bool $complete = true): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $generator->generate_response($questionnaire, $questionnaire->questions(), $userid, $complete);
    }

    /**
     * Set a column on the questionnaire instance and reload the domain object.
     *
     * @param questionnaire $questionnaire
     * @param string $field
     * @param mixed $value
     * @return questionnaire
     */
    private function set_instance_field(questionnaire $questionnaire, string $field, $value): questionnaire {
        global $DB;
        $DB->set_field('questionnaire', $field, $value, ['id' => $questionnaire->id()]);
        return questionnaire::from_instanceid($questionnaire->id());
    }

    /**
     * Export the page for the given viewer.
     *
     * @param questionnaire $questionnaire
     * @param int $userid
     * @param int $groupid
     * @param moodle_url|null $guestcancelurl
     * @return \stdClass
     */
    private function export(
        questionnaire $questionnaire,
        int $userid,
        int $groupid = 0,
        ?moodle_url $guestcancelurl = null
    ): \stdClass {
        return (new viewpage($questionnaire, $userid, $groupid, $guestcancelurl))->export_for_template($this->renderer());
    }

    /**
     * The labels of the exported action buttons, in order.
     *
     * @param \stdClass $data
     * @return string[]
     */
    private function labels(\stdClass $data): array {
        return array_column($data->actions, 'label');
    }

    /**
     * A student who can answer gets the answer button pointing at complete.php, no message,
     * and the information lines.
     */
    public function test_student_can_answer(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $userid = $this->login_as('student');

        $data = $this->export($questionnaire, $userid);

        $this->assertNull($data->message);
        $this->assertEquals([get_string('answerquestions', 'questionnaire')], $this->labels($data));
        $expected = new moodle_url('/mod/questionnaire/complete.php', ['id' => $questionnaire->coursemodule()->id]);
        $this->assertEquals($expected->out(false), $data->actions[0]['url']);
        $this->assertTrue($data->hasactions);
        $this->assertEquals($questionnaire->view_information(), $data->info);
        $this->assertTrue($data->hasinfo);
        $this->assertNull($data->guestconfirm);
    }

    /**
     * A student with a saved, unsubmitted response gets the resume button instead.
     */
    public function test_student_with_saved_response_can_resume(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $userid = $this->login_as('student');
        $this->add_response($questionnaire, $userid, false);

        $data = $this->export($questionnaire, $userid);

        $this->assertEquals([get_string('resumesurvey', 'questionnaire')], $this->labels($data));
        $this->assertStringContainsString('resume=1', $data->actions[0]['url']);
    }

    /**
     * A closed questionnaire shows its access message, no answer button and no information lines.
     */
    public function test_closed_questionnaire_shows_message_only(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $closedate = time() - DAYSECS;
        $questionnaire = $this->set_instance_field($questionnaire, 'closedate', $closedate);
        $userid = $this->login_as('student');

        $data = $this->export($questionnaire, $userid);

        $this->assertEquals(get_string('closed', 'questionnaire', userdate($closedate)), $data->message);
        $this->assertEmpty($data->actions);
        $this->assertFalse($data->hasactions);
        $this->assertEmpty($data->info);
        $this->assertFalse($data->hasinfo);
    }

    /**
     * A questionnaire that has not opened yet shows the not-open message and no information lines.
     */
    public function test_not_yet_open_questionnaire_shows_message_only(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $opendate = time() + DAYSECS;
        $questionnaire = $this->set_instance_field($questionnaire, 'opendate', $opendate);
        $userid = $this->login_as('student');

        $data = $this->export($questionnaire, $userid);

        $this->assertEquals(get_string('notopen', 'questionnaire', userdate($opendate)), $data->message);
        $this->assertEmpty($data->actions);
        $this->assertEmpty($data->info);
    }

    /**
     * A student viewing a questionnaire with no questions is told so and offered nothing.
     */
    public function test_student_sees_no_questions_message(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire(false);
        $userid = $this->login_as('student');

        $data = $this->export($questionnaire, $userid);

        $this->assertEquals(get_string('noneinuse', 'questionnaire'), $data->message);
        $this->assertEmpty($data->actions);
    }

    /**
     * An editing teacher viewing a questionnaire with no questions is offered "Add questions".
     */
    public function test_editor_offered_add_questions(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire(false);
        $userid = $this->login_as('editingteacher');

        $data = $this->export($questionnaire, $userid);

        $this->assertContains(get_string('addquestions', 'questionnaire'), $this->labels($data));
        $expected = new moodle_url('/mod/questionnaire/questions.php', ['id' => $questionnaire->coursemodule()->id]);
        $this->assertContains($expected->out(false), array_column($data->actions, 'url'));
    }

    /**
     * "Add questions" is not offered once the questionnaire has questions.
     */
    public function test_editor_not_offered_add_questions_when_questions_exist(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $userid = $this->login_as('editingteacher');

        $data = $this->export($questionnaire, $userid);

        $this->assertNotContains(get_string('addquestions', 'questionnaire'), $this->labels($data));
    }

    /**
     * After one submission the student gets "Your response", linking straight to that response.
     */
    public function test_one_submission_links_to_the_response(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $userid = $this->login_as('student');
        $this->add_response($questionnaire, $userid);

        $data = $this->export($questionnaire, $userid);

        $labels = $this->labels($data);
        $this->assertContains(get_string('yourresponse', 'questionnaire'), $labels);
        $url = $data->actions[array_search(get_string('yourresponse', 'questionnaire'), $labels)]['url'];
        $this->assertStringContainsString('/mod/questionnaire/myreport.php', $url);
        $this->assertStringContainsString('byresponse=1', $url);
        $this->assertStringContainsString('action=vresp', $url);
    }

    /**
     * After several submissions the student gets "View your responses (N)", linking to the list.
     */
    public function test_several_submissions_link_to_the_list(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $userid = $this->login_as('student');
        $this->add_response($questionnaire, $userid);
        $this->add_response($questionnaire, $userid);

        $data = $this->export($questionnaire, $userid);

        $labels = $this->labels($data);
        $label = get_string('viewyourresponses', 'questionnaire', 2);
        $this->assertContains($label, $labels);
        $url = $data->actions[array_search($label, $labels)]['url'];
        $this->assertStringNotContainsString('byresponse', $url);
    }

    /**
     * A teacher who can see all responses gets report.php, carrying the current group.
     */
    public function test_teacher_offered_all_responses_with_group(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $studentid = $this->login_as('student');
        $this->add_response($questionnaire, $studentid);
        $teacherid = $this->login_as('editingteacher');

        $data = $this->export($questionnaire, $teacherid, 5);

        $labels = $this->labels($data);
        $this->assertContains(get_string('viewallresponses', 'questionnaire'), $labels);
        $expected = new moodle_url('/mod/questionnaire/report.php', ['instance' => $questionnaire->id(), 'group' => 5]);
        $this->assertEquals($expected->out(false), $data->actions[array_search(
            get_string('viewallresponses', 'questionnaire'),
            $labels
        )]['url']);
    }

    /**
     * The log-in prompt is rendered only when a guest cancel URL is passed.
     */
    public function test_guest_confirm_only_with_cancel_url(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $userid = $this->login_as('student');

        $this->assertNull($this->export($questionnaire, $userid)->guestconfirm);

        $data = $this->export($questionnaire, $userid, 0, new moodle_url('/course/view.php', ['id' => $this->course->id]));
        $this->assertStringContainsString(get_string('liketologin'), $data->guestconfirm);
    }

    /**
     * The template renders each action as a button link and the information lines as a list.
     */
    public function test_template_renders_actions_and_info(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $userid = $this->login_as('student');

        $html = $this->renderer()->render(new viewpage($questionnaire, $userid, 0));

        $this->assertStringContainsString(
            'class="btn btn-primary">' . get_string('answerquestions', 'questionnaire') . '</a>',
            $html
        );
        $this->assertStringContainsString('<ul class="info list-unstyled">', $html);
    }
}
