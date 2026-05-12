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
 * Unit tests for mod_questionnaire\output\report_view_renderer.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\output\report_view_renderer.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\report_view_renderer
 */
final class report_view_renderer_test extends \advanced_testcase {
    /**
     * Initialise the SESSION->questionnaire bucket that real callers (preview.php, print.php)
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
     * Read the data backing a previewpage so tests can assert on the keys the renderer wrote.
     *
     * @param previewpage $page
     * @return \stdClass
     */
    private function page_data(previewpage $page): \stdClass {
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
        $page = new previewpage();
        $this->init_session();
        (new report_view_renderer($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, true);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('formstart', $data);
        $this->assertObjectHasProperty('questions', $data);
    }

    /**
     * build_print_view() in preview mode (not blank) appends the preview-submit form end.
     */
    public function test_build_print_view_preview_adds_formend(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$instance, ] = $this->build_fixture();

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new previewpage();
        $this->init_session();
        (new report_view_renderer($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, false);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('formend', $data);
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
        $page = new previewpage();
        $this->init_session();
        (new report_view_renderer($renderer, $page))
            ->build_print_view($instance, $instance->courseid(), '', 'preview', 0, true);

        $data = $this->page_data($page);
        $this->assertObjectHasProperty('title', $data);
        $this->assertStringContainsString('Print Preview Title', $data->title);
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
        $page = new previewpage();
        $this->init_session();
        (new report_view_renderer($renderer, $page))
            ->render_response($instance, $rid, 'print');

        // 'responses' key is only present when there is at least one renderable question;
        // even without a stored answer the YES/NO question renders an empty response row.
        $data = $this->page_data($page);
        $this->assertObjectHasProperty('responses', $data);
    }
}
