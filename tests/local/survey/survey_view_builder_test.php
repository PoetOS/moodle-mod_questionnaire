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
 * Unit tests for mod_questionnaire\local\survey\survey_view_builder.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\survey;

use mod_questionnaire\output\completepage;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\survey\survey_view_builder.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\survey\survey_view_builder
 */
final class survey_view_builder_test extends \advanced_testcase {
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
     * Expose the protected stdClass that backs the completepage so tests can read back
     * the keys the renderer added.
     *
     * @param completepage $page
     * @return \stdClass
     */
    private function page_data(completepage $page): \stdClass {
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
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->build_view($instance, $studentid);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('notifications', $data);
        $this->assertStringContainsString(
            get_string('templatenotviewable', 'questionnaire'),
            $data->notifications
        );
        $this->assertObjectNotHasProperty('form', $data);
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
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->build_view($instance, $studentid);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('form', $data);
        $this->assertObjectHasProperty('controlbuttons', $data);
    }

    /**
     * build_survey_form() returns null and populates the form (action and hidden fields) and its buttons
     * when questions exist; a one-page survey gets a submit button and no previous/next.
     */
    public function test_build_survey_form_with_questions(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();
        $result = (new survey_view_builder($renderer, $page))
            ->build_survey_form($instance, $studentid, $studentid);

        $this->assertNull($result);
        $data = $this->page_data($page);
        $this->assertStringContainsString(
            '/mod/questionnaire/complete.php?id=' . $instance->coursemodule()->id,
            $data->form['action']
        );
        $hidden = array_column($data->form['hidden'], 'value', 'name');
        $this->assertEqualsCanonicalizing(['referer', 'a', 'sid', 'rid', 'sec', 'end', 'sesskey'], array_keys($hidden));
        $this->assertEquals($instance->id(), $hidden['a']);
        $this->assertEquals(sesskey(), $hidden['sesskey']);
        $buttons = array_column($data->controlbuttons, 'type', 'name');
        $this->assertEquals(['submit' => 'submit', 'submittype' => 'hidden'], $buttons);
        $this->assertObjectNotHasProperty('noquestions', $data);
    }

    /**
     * The rendered completion page opens and closes the response form around the questions and buttons.
     */
    public function test_build_survey_form_renders_form(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->build_survey_form($instance, $studentid, $studentid);
        $html = $renderer->render($page);

        $this->assertStringContainsString('<form id="phpesp_response" method="post"', $html);
        $this->assertStringContainsString('</form>', $html);
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
        $submit = get_string('submitsurvey', 'questionnaire');
        $this->assertStringContainsString(
            'name="submit" class="btn btn-primary control-button-submit" value="' . $submit . '"',
            $html
        );
        $this->assertStringContainsString('Yes or no?', $html);
    }

    /**
     * build_survey_form() on a questionnaire without questions shows the none-in-use message instead of buttons.
     */
    public function test_build_survey_form_without_questions(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = questionnaire::from_instanceid($generator->create_instance(['course' => $course->id])->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->build_survey_form($instance, 0);

        $data = $this->page_data($page);
        $this->assertEquals(get_string('noneinuse', 'questionnaire'), $data->noquestions);
        $this->assertObjectNotHasProperty('controlbuttons', $data);
        $this->assertObjectHasProperty('form', $data);
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
        $page = new completepage();
        (new survey_view_builder($renderer, $page))
            ->build_survey_form($instance, $studentid, $studentid);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('surveyheader', $data);
        $this->assertStringContainsString('My Survey Title', $data->surveyheader->title);
    }

    /**
     * handle_submit_action() short-circuits (returns null) when the user has already
     * walked past the final page (formdata->end == 1).
     */
    public function test_handle_submit_action_short_circuits_when_past_end(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();

        $svr = new survey_view_builder($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0, 'end' => 1];
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
        $page = new completepage();

        $svr = new survey_view_builder($renderer, $page);
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
        $page = new completepage();

        $svr = new survey_view_builder($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0, 'next' => 'Next'];
        $method = new \ReflectionMethod($svr, 'handle_next_action');
        $method->setAccessible(true);
        $msg = $method->invoke($svr, $instance, $formdata, (int)$studentid, 1);

        $this->assertSame('', $msg);
        $this->assertSame(2, $formdata->sec);
    }

    /**
     * handle_submit_action() returns the validation error and refreshes formdata->rid
     * when the user tries to submit a page with a required question left blank.
     */
    public function test_handle_submit_action_returns_error_on_missing_required(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        // Mark the only question on the survey as required so check_format flags it as missing.
        $qid = $DB->get_field('questionnaire_question', 'id', ['surveyid' => $instance->surveyid()]);
        $DB->set_field('questionnaire_question', 'required', 'y', ['id' => $qid]);
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();

        $svr = new survey_view_builder($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0, 'submit' => 'Submit Survey'];
        $method = new \ReflectionMethod($svr, 'handle_submit_action');
        $method->setAccessible(true);
        $msg = $method->invoke($svr, $instance, $formdata, (int)$studentid);

        $this->assertNotEmpty($msg);
        $this->assertNotEmpty($formdata->rid);
    }

    /**
     * handle_next_action() returns the validation error, clears formdata->next, and
     * refreshes formdata->rid when the user tries to advance past a required question.
     */
    public function test_handle_next_action_returns_error_on_missing_required(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $qid = $DB->get_field('questionnaire_question', 'id', ['surveyid' => $instance->surveyid()]);
        $DB->set_field('questionnaire_question', 'required', 'y', ['id' => $qid]);
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();

        $svr = new survey_view_builder($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0, 'next' => 'Next'];
        $method = new \ReflectionMethod($svr, 'handle_next_action');
        $method->setAccessible(true);
        $msg = $method->invoke($svr, $instance, $formdata, (int)$studentid, 1);

        $this->assertNotEmpty($msg);
        $this->assertSame('', $formdata->next);
        $this->assertNotEmpty($formdata->rid);
    }

    /**
     * handle_prev_action() returns a wrong-format error, clears formdata->prev,
     * and refreshes formdata->rid when the current page contains an invalid date.
     *
     * Prev only checks format (not missing-required), so we use a date question
     * with a non-date value to trigger response_valid() == false.
     */
    public function test_handle_prev_action_returns_error_on_invalid_format(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $plugin = $generator->get_plugin_generator('mod_questionnaire');
        $instance = $plugin->create_test_questionnaire(
            $course,
            QUESDATE,
            ['content' => 'When?'],
            []
        );
        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student->id, $course->id, $studentrole->id);
        $this->setUser($student->id);
        $instance = questionnaire::from_instanceid($instance->id());

        $qid = $DB->get_field('questionnaire_question', 'id', ['surveyid' => $instance->surveyid()]);

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();

        $svr = new survey_view_builder($renderer, $page);
        $formdata = (object)['sec' => 1, 'rid' => 0, 'prev' => 'Prev', 'q' . $qid => 'not-a-date'];
        $method = new \ReflectionMethod($svr, 'handle_prev_action');
        $method->setAccessible(true);
        $msg = $method->invoke($svr, $instance, $formdata, (int)$student->id);

        $this->assertNotEmpty($msg);
        $this->assertSame('', $formdata->prev);
        $this->assertNotEmpty($formdata->rid);
    }

    /**
     * handle_prev_action() walks back from the past-end summary by decrementing
     * sec and clearing SESSION->end before validating the current page.
     */
    public function test_handle_prev_action_walks_back_from_past_end(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();

        $svr = new survey_view_builder($renderer, $page);
        // After the user walked past the last section (sec=2 + end=1), pressing Prev should land them on sec=1
        // with the end flag cleared.
        $formdata = (object)['sec' => 2, 'rid' => 0, 'prev' => 'Prev', 'end' => 1];
        $method = new \ReflectionMethod($svr, 'handle_prev_action');
        $method->setAccessible(true);
        $method->invoke($svr, $instance, $formdata, (int)$studentid);

        $this->assertSame(0, $formdata->end);
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
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_end($instance, 1, 3);

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
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_end($instance, 1, 1);

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
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_end($instance, 1, 5);

        $data = $this->page_data($page);
        $this->assertObjectNotHasProperty('pageinfo', $data);
    }

    /**
     * goto_saved() adds a save-progress notification and a back-to-course link.
     */
    public function test_goto_saved_writes_savedprogress_notification(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->goto_saved($instance);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('notifications', $data);
        $this->assertStringContainsString('progress has been saved', $data->notifications);
        $this->assertCount(1, $data->links);
        $this->assertEquals(
            (new \moodle_url('/course/view.php', ['id' => $instance->courseid()]))->out(false),
            $data->links[0]['url']
        );
        $this->assertEquals(get_string('backto', 'moodle', $instance->course()->fullname), $data->links[0]['label']);
    }

    /**
     * goto_saved() adds a resume link when the user has a saved response to come back to.
     */
    public function test_goto_saved_adds_resume_link(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $DB->set_field('questionnaire', 'resume', 1, ['id' => $instance->id()]);
        $instance = questionnaire::from_instanceid($instance->id());
        $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')
            ->generate_response($instance, $instance->questions(), $studentid, false);
        $this->setUser($studentid);
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->goto_saved($instance);

        $data = $this->page_data($page);
        $this->assertCount(2, $data->links);
        $this->assertEquals(
            (new \moodle_url('/mod/questionnaire/complete.php', ['id' => $instance->coursemodule()->id, 'resume' => 1]))
                ->out(false),
            $data->links[1]['url']
        );
        $this->assertEquals(get_string('resumesurvey', 'questionnaire'), $data->links[1]['label']);
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
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->goto_thankyou($instance);

        $data = $this->page_data($page);
        $thankhead = $instance->survey()->thankhead() ?: get_string('thank_head', 'questionnaire');
        $this->assertEquals(format_string($thankhead), $data->surveyheader->title);
        $this->assertObjectHasProperty('continue', $data);
    }

    /**
     * print_survey_start() puts the survey title, subtitle and information in the header on section 1 only.
     */
    public function test_print_survey_start_header_on_first_section_only(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance] = $this->build_fixture();
        $DB->update_record('questionnaire_survey', (object)[
            'id' => $instance->surveyid(),
            'title' => 'Header title',
            'subtitle' => 'Header subtitle',
            'info' => '<p>Header info</p>',
        ]);
        $instance = questionnaire::from_instanceid($instance->id());
        $renderer = $PAGE->get_renderer('mod_questionnaire');

        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_start($instance, '', 1);
        $header = $this->page_data($page)->surveyheader;
        $this->assertEquals('Header title', $header->title);
        $this->assertEquals('Header subtitle', $header->subtitle);
        $this->assertStringContainsString('Header info', $header->info);
        $this->assertNull($header->respondent);
        $this->assertNull($header->printblank);

        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_start($instance, '', 2);
        $header = $this->page_data($page)->surveyheader;
        $this->assertNull($header->title);
        $this->assertNull($header->info);
    }

    /**
     * print_survey_start() adds an error message as a notification.
     */
    public function test_print_survey_start_writes_message(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance] = $this->build_fixture();
        $page = new completepage();
        (new survey_view_builder($PAGE->get_renderer('mod_questionnaire'), $page))
            ->print_survey_start($instance, 'Something went wrong', 1);

        $this->assertStringContainsString('Something went wrong', $this->page_data($page)->message);
    }

    /**
     * print_survey_start() offers the print-blank link only for a blank questionnaire on section 1.
     */
    public function test_print_survey_start_print_blank_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance] = $this->build_fixture();
        $renderer = $PAGE->get_renderer('mod_questionnaire');

        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_start($instance, '', 1, '', true);
        $printblank = $this->page_data($page)->surveyheader->printblank;
        $this->assertStringContainsString('/mod/questionnaire/print.php', $printblank);
        $this->assertStringContainsString(get_string('printblank', 'questionnaire'), $printblank);

        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_start($instance, '', 1, '', false);
        $this->assertNull($this->page_data($page)->surveyheader->printblank);
    }

    /**
     * Viewing a named response shows the respondent, a print icon on HTML pages, and logs response_viewed.
     */
    public function test_print_survey_start_named_respondent(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $response = $generator->generate_response($instance, $instance->questions(), $studentid);
        $renderer = $PAGE->get_renderer('mod_questionnaire');

        $sink = $this->redirectEvents();
        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_start($instance, '', 1, $response['id']);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_questionnaire\event\response_viewed);
        $sink->close();

        $respondent = $this->page_data($page)->surveyheader->respondent;
        $this->assertEquals(fullname(\core_user::get_user($studentid)), $respondent->name);
        $this->assertNotNull($respondent->submitted);
        $this->assertStringContainsString('target=print', $respondent->printlink);
        $this->assertCount(1, $events);
        $this->assertEquals($studentid, reset($events)->relateduserid);

        $page = new completepage();
        (new survey_view_builder($renderer, $page))->print_survey_start($instance, '', 1, $response['id'], false, 'pdf');
        $this->assertNull($this->page_data($page)->surveyheader->respondent->printlink);
    }

    /**
     * An anonymous questionnaire shows the anonymous marker, no submission time, and logs nothing.
     */
    public function test_print_survey_start_anonymous_respondent(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $response = $generator->generate_response($instance, $instance->questions(), $studentid);
        $DB->set_field('questionnaire', 'respondenttype', 'anonymous', ['id' => $instance->id()]);
        $instance = questionnaire::from_instanceid($instance->id());

        $sink = $this->redirectEvents();
        $page = new completepage();
        (new survey_view_builder($PAGE->get_renderer('mod_questionnaire'), $page))
            ->print_survey_start($instance, '', 1, $response['id']);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_questionnaire\event\response_viewed);
        $sink->close();

        $respondent = $this->page_data($page)->surveyheader->respondent;
        $this->assertEquals('- ' . get_string('anonymous', 'questionnaire') . ' -', $respondent->name);
        $this->assertNull($respondent->submitted);
        $this->assertEmpty($events);
    }

    /**
     * A blank questionnaire never shows the respondent line, even with a response id.
     */
    public function test_print_survey_start_blank_hides_respondent(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $response = $generator->generate_response($instance, $instance->questions(), $studentid);

        $page = new completepage();
        (new survey_view_builder($PAGE->get_renderer('mod_questionnaire'), $page))
            ->print_survey_start($instance, '', 1, $response['id'], true);

        $this->assertNull($this->page_data($page)->surveyheader->respondent);
    }
}
