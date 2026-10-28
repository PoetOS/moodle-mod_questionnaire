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
 * Unit tests for mod_questionnaire\local\report\report_view_builder.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\report;

use mod_questionnaire\output\page;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\report\report_view_builder.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\report\report_view_builder
 */
final class report_view_builder_test extends \advanced_testcase {
    /**
     * Build a course + questionnaire with one yes/no question.
     *
     * @return array [questionnaire, student id]
     */
    private function build_fixture(): array {
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
     * Read the data backing a page so tests can assert on the keys the renderer wrote.
     *
     * @param page $page
     * @return \stdClass
     */
    private function page_data(page $page): \stdClass {
        global $PAGE;
        return $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
    }

    /**
     * build_print_view() with rid = 0 populates the survey form skeleton on the page.
     */
    public function test_build_print_view_blank_renders_form(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = page::preview();
        (new report_view_builder($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, true);

        $data = $this->page_data($page);
        $this->assertStringContainsString(
            '/mod/questionnaire/preview.php?id=' . $instance->coursemodule()->id,
            $data->form['action']
        );
        $this->assertObjectHasProperty('questions', $data);
        $this->assertObjectNotHasProperty('previewbuttons', $data);

        // The form is closed even without the preview buttons.
        $html = $renderer->render($page);
        $this->assertStringContainsString('<form id="phpesp_response"', $html);
        $this->assertStringContainsString('</form>', $html);
    }

    /**
     * build_print_view() in preview mode (not blank) appends the preview-submit form end.
     */
    public function test_build_print_view_preview_adds_buttons(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = page::preview();
        (new report_view_builder($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, false);

        $data = $this->page_data($page);
        $this->assertEquals(get_string('submitpreview', 'questionnaire'), $data->previewbuttons['submitlabel']);
        $this->assertEquals($data->form['action'], $data->previewbuttons['reseturl']);
        $this->assertEquals(sesskey(), $data->previewbuttons['sesskey']);

        $html = $renderer->render($page);
        $this->assertStringContainsString('name="submit" value="' . get_string('submitpreview', 'questionnaire') . '"', $html);
    }

    /**
     * A previewer's own submission (with a valid session key) is checked and reported.
     */
    public function test_build_print_view_checks_previewers_submission(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$instance, ] = $this->build_fixture();

        $data = $this->preview_with_post($instance, ['sesskey' => sesskey()]);

        $this->assertObjectHasProperty('message', $data);
    }

    /**
     * A POST without a valid session key, such as one from another site, is ignored.
     */
    public function test_build_print_view_ignores_post_without_sesskey(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$instance, ] = $this->build_fixture();

        $data = $this->preview_with_post($instance, ['sesskey' => 'forged']);

        $this->assertObjectNotHasProperty('message', $data);
    }

    /**
     * A POST from a user without the preview capability is ignored.
     */
    public function test_build_print_view_ignores_post_without_preview_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$instance, $studentid] = $this->build_fixture();
        $this->setUser($studentid);

        $data = $this->preview_with_post($instance, ['sesskey' => sesskey()]);

        $this->assertObjectNotHasProperty('message', $data);
    }

    /**
     * Render the preview page for a POST of the preview form with the given fields.
     *
     * @param questionnaire $instance
     * @param array $post
     * @return \stdClass The page data.
     */
    private function preview_with_post(questionnaire $instance, array $post): \stdClass {
        global $PAGE;
        $_POST = array_merge(['submit' => get_string('submitpreview', 'questionnaire')], $post);
        $page = page::preview();
        (new report_view_builder($PAGE->get_renderer('mod_questionnaire'), $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, false);
        return $this->page_data($page);
    }

    /**
     * The preview page's survey header carries the print-blank link; the blank print view's header does not.
     *
     * The link must be in the header itself: the old page-level printblank key only showed through Mustache 2's
     * fallback to the parent context for a null header field, which Mustache 3 (Moodle 5.2) no longer does.
     */
    public function test_build_print_view_print_blank_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();
        $renderer = $PAGE->get_renderer('mod_questionnaire');

        $page = page::preview();
        (new report_view_builder($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, false);
        $data = $this->page_data($page);
        $this->assertStringContainsString('/mod/questionnaire/print.php', $data->surveyheader->printblank);
        $this->assertObjectNotHasProperty('printblank', $data);
        $this->assertStringContainsString(get_string('printblank', 'questionnaire'), $renderer->render($page));

        $page = page::preview();
        (new report_view_builder($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'print', 0, true);
        $this->assertNull($this->page_data($page)->surveyheader->printblank);
    }

    /**
     * build_print_view() writes the survey title via the shared header helper.
     */
    public function test_build_print_view_writes_title(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();
        $DB->set_field(
            'questionnaire_survey',
            'title',
            'Print Preview Title',
            ['id' => $instance->surveyid()]
        );
        $instance = questionnaire::from_instanceid($instance->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = page::preview();
        (new report_view_builder($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, true);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('surveyheader', $data);
        $this->assertStringContainsString('Print Preview Title', $data->surveyheader->title);
    }

    /**
     * render_response() populates the responses key for an existing response.
     */
    public function test_render_response_populates_responses(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, $studentid] = $this->build_fixture();
        $rid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $instance->id(),
            'userid' => $studentid,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = page::preview();
        (new report_view_builder($renderer, $page))
            ->render_response($instance, $rid, 'print');

        // The 'responses' key is only present when there is at least one renderable question;
        // even without a stored answer the YES/NO question renders an empty response row.
        $data = $this->page_data($page);
        $this->assertObjectHasProperty('responses', $data);
    }
}
