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
 * Unit tests for mod_questionnaire\output\survey_view_renderer.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\output\survey_view_renderer.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\survey_view_renderer
 */
final class survey_view_renderer_test extends \advanced_testcase {
    /**
     * Initialise the SESSION->questionnaire bucket that real callers (view.php, complete.php)
     * set up before invoking the renderer.
     */
    private function init_session(): void {
        global $SESSION;
        if (!isset($SESSION->questionnaire) || !is_object($SESSION->questionnaire)) {
            $SESSION->questionnaire = new \stdClass();
        }
        $SESSION->questionnaire->end = false;
        $SESSION->questionnaire->current_tab = '';
    }

    /**
     * Build a course + questionnaire with one yes/no question and return both the
     * domain object and the enrolled student id.
     *
     * @param string $realm Survey realm (defaults to 'private').
     * @return array [questionnaire, student id]
     */
    private function build_fixture(string $realm = 'private'): array {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $plugin = $generator->get_plugin_generator('mod_questionnaire');
        $instance = $plugin->create_test_questionnaire(
            $course,
            QUESYESNO,
            ['content' => 'Yes or no?'],
            []
        );

        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        return [$instance, (int) $student->id];
    }

    /**
     * Expose the protected stdClass that backs the viewpage so tests can read back
     * the keys the renderer added.
     *
     * @param viewpage $page
     * @return \stdClass
     */
    private function page_data(viewpage $page): \stdClass {
        global $PAGE;
        return $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
    }

    /**
     * build_view() writes an access-denied notification when access is blocked
     * (template realm, current user is not the survey owner).
     */
    public function test_build_view_writes_access_denied(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        // Flip the survey realm to template so user_access_messages reports "not viewable".
        $DB->set_field(
            'questionnaire_survey',
            'realm',
            'template',
            ['id' => $instance->surveyid()]
        );
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        (new survey_view_renderer($renderer, $page))->build_view($instance, $studentid);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('notifications', $data);
        $this->assertStringContainsString(
            get_string('templatenotviewable', 'questionnaire'),
            $data->notifications
        );
        $this->assertObjectNotHasProperty('formstart', $data);
    }

    /**
     * build_view() falls through to build_survey_form when access is allowed, populating
     * the standard form keys.
     */
    public function test_build_view_populates_survey_form_when_allowed(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        $this->init_session();
        (new survey_view_renderer($renderer, $page))->build_view($instance, $studentid);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('formstart', $data);
        $this->assertObjectHasProperty('controlbuttons', $data);
        $this->assertObjectHasProperty('formend', $data);
    }

    /**
     * build_survey_form() returns null and populates formstart/controlbuttons/formend
     * when questions exist.
     */
    public function test_build_survey_form_with_questions(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        $this->init_session();
        $result = (new survey_view_renderer($renderer, $page))
            ->build_survey_form($instance, $studentid, $studentid);

        $this->assertNull($result);
        $data = $this->page_data($page);
        $this->assertObjectHasProperty('formstart', $data);
        $this->assertObjectHasProperty('controlbuttons', $data);
        $this->assertObjectHasProperty('formend', $data);
    }

    /**
     * build_survey_form() with the survey title set writes it to the page.
     */
    public function test_build_survey_form_writes_title(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $DB->set_field(
            'questionnaire_survey',
            'title',
            'My Survey Title',
            ['id' => $instance->surveyid()]
        );
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        $this->init_session();
        (new survey_view_renderer($renderer, $page))
            ->build_survey_form($instance, $studentid, $studentid);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('title', $data);
        $this->assertStringContainsString('My Survey Title', $data->title);
    }

    /**
     * handle_submit_action() short-circuits (returns null) when the user has already
     * walked past the final page (SESSION->questionnaire->end == true).
     */
    public function test_handle_submit_action_short_circuits_when_past_end(): void {
        global $PAGE, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        $this->init_session();
        $SESSION->questionnaire->end = true;

        $svr = new survey_view_renderer($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0];
        $method = new \ReflectionMethod($svr, 'handle_submit_action');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($svr, $instance, $formdata, (int)$studentid));
    }

    /**
     * handle_resume_action() persists the in-progress response and posts a
     * "progress saved" notification to the page (delegating to goto_saved).
     */
    public function test_handle_resume_action_writes_savedprogress(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        $this->init_session();

        $svr = new survey_view_renderer($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0];
        $method = new \ReflectionMethod($svr, 'handle_resume_action');
        $method->setAccessible(true);
        $method->invoke($svr, $instance, $formdata, (int)$studentid);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('notifications', $data);
        $this->assertStringContainsString('progress has been saved', $data->notifications);
        $this->assertNotEmpty($formdata->rid);
    }

    /**
     * handle_next_action() advances formdata->sec to the next section on success.
     */
    public function test_handle_next_action_advances_to_next_section(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        $this->init_session();

        $svr = new survey_view_renderer($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0, 'next' => 'Next'];
        $method = new \ReflectionMethod($svr, 'handle_next_action');
        $method->setAccessible(true);
        $msg = $method->invoke($svr, $instance, $formdata, (int)$studentid, 1);

        $this->assertSame('', $msg);
        $this->assertSame(2, $formdata->sec);
    }

    /**
     * handle_prev_action() walks back from the past-end summary by decrementing
     * sec and clearing SESSION->end before validating the current page.
     */
    public function test_handle_prev_action_walks_back_from_past_end(): void {
        global $PAGE, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        $this->init_session();
        $SESSION->questionnaire->end = true;

        $svr = new survey_view_renderer($renderer, $page);
        // After the user walked past the last section (sec=2), pressing Prev should land them on sec=1.
        $formdata = (object)['sec' => 2, 'rid' => 0, 'prev' => 'Prev'];
        $method = new \ReflectionMethod($svr, 'handle_prev_action');
        $method->setAccessible(true);
        $method->invoke($svr, $instance, $formdata, (int)$studentid);

        $this->assertFalse($SESSION->questionnaire->end);
    }

    /**
     * print_survey_end() writes a "page X of Y" footer when auto-numbering is on and
     * there is more than one section.
     */
    public function test_print_survey_end_writes_pageinfo(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();
        // The autonum bit 2 (decimal 2 or 3) turns on page auto-numbering.
        $DB->set_field('questionnaire', 'autonum', 3, ['id' => $instance->id()]);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        (new survey_view_renderer($renderer, $page))->print_survey_end($instance, 1, 3);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('pageinfo', $data);
        $this->assertStringContainsString('1', $data->pageinfo);
    }

    /**
     * print_survey_end() is a no-op when there is only one section.
     */
    public function test_print_survey_end_skips_single_section(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();
        $DB->set_field('questionnaire', 'autonum', 3, ['id' => $instance->id()]);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        (new survey_view_renderer($renderer, $page))->print_survey_end($instance, 1, 1);

        $data = $this->page_data($page);
        $this->assertObjectNotHasProperty('pageinfo', $data);
    }

    /**
     * print_survey_end() is a no-op when auto-numbering is disabled.
     */
    public function test_print_survey_end_skips_when_autonum_off(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();
        $DB->set_field('questionnaire', 'autonum', 0, ['id' => $instance->id()]);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        (new survey_view_renderer($renderer, $page))->print_survey_end($instance, 1, 5);

        $data = $this->page_data($page);
        $this->assertObjectNotHasProperty('pageinfo', $data);
    }

    /**
     * goto_saved() adds a save-progress notification and a back-to-course homelink.
     */
    public function test_goto_saved_writes_savedprogress_notification(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        (new survey_view_renderer($renderer, $page))->goto_saved($instance);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('notifications', $data);
        $this->assertStringContainsString('progress has been saved', $data->notifications);
        $this->assertObjectHasProperty('respondentinfo', $data);
    }

    /**
     * goto_thankyou() writes the thank-you title and a continue button when no thanks URL is set.
     */
    public function test_goto_thankyou_writes_title_and_continue(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new viewpage();
        (new survey_view_renderer($renderer, $page))->goto_thankyou($instance);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('title', $data);
        $this->assertObjectHasProperty('continue', $data);
    }
}
