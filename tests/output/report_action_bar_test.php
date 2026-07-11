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
 * Unit tests for mod_questionnaire\output\report_action_bar.
 *
 * These carry the visibility/threading rules that used to be pinned by tabs_test.php:
 * capability gating of the list/download/delete controls, group and rid threading,
 * the restricted-respview branch, and the single-response myreport short-circuit.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\output\report_action_bar.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\report_action_bar
 */
final class report_action_bar_test extends \advanced_testcase {
    /**
     * Build a course + questionnaire with one yes/no question.
     *
     * @return questionnaire
     */
    private function build_questionnaire(): questionnaire {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        return $generator->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESYESNO,
            ['content' => 'Yes or no?'],
            []
        );
    }

    /**
     * Build a questionnaire configured for unlimited responses with a given respview.
     *
     * @param int $respview Value for questionnaire->respview.
     * @return array{0: questionnaire, 1: \stdClass} [questionnaire, course]
     */
    private function build_questionnaire_with_settings(int $respview = 0): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $plugingen = $generator->get_plugin_generator('mod_questionnaire');
        $questionnaire = $plugingen->create_instance([
            'course' => $course->id,
            'qtype' => 5,
            'respview' => $respview,
        ]);
        $plugingen->create_question(
            $questionnaire,
            ['typeid' => QUESYESNO, 'surveyid' => $questionnaire->surveyid(), 'name' => 'Q1', 'content' => 'Yes or no?']
        );
        return [questionnaire::from_instanceid($questionnaire->id()), $course];
    }

    /**
     * Render the bar to HTML through the plugin renderer + mustache template.
     *
     * @param report_action_bar $bar
     * @return string
     */
    private function render_bar(report_action_bar $bar): string {
        return $this->render_bar_renderer()->render($bar);
    }

    /**
     * Fetch the plugin renderer (for render and export-level assertions).
     *
     * @return \renderer_base
     */
    private function render_bar_renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('mod_questionnaire');
    }

    /**
     * Admin on the summary view gets the full toolbar: list link, three order options,
     * download link and the delete-all danger action.
     */
    public function test_for_summary_admin_has_full_toolbar(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $bar = report_action_bar::for_summary($questionnaire, 0, 'vall');
        $html = $this->render_bar($bar);

        $this->assertTrue($bar->has_content());
        $this->assertStringContainsString('byresponse=1', $html);
        $this->assertStringContainsString('action=vresp', $html);
        $this->assertStringContainsString(get_string('order_default', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('order_ascending', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('order_descending', 'questionnaire'), $html);
        $this->assertStringContainsString('action=dwnpg', $html);
        $this->assertStringContainsString('action=delallresp', $html);
        $this->assertStringContainsString('btn-outline-danger', $html);
    }

    /**
     * The currentgroupid threads into every control URL: view links, order options,
     * download and delete-all.
     */
    public function test_for_summary_threads_group_into_all_urls(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $bar = report_action_bar::for_summary($questionnaire, 77, 'vall');
        $data = $bar->export_for_template($this->render_bar_renderer());

        foreach ($data->left as $item) {
            if (isset($item['actionlink'])) {
                $this->assertStringContainsString('group=77', $item['actionlink']->url);
            }
            if (isset($item['urlselect'])) {
                foreach ($item['urlselect']->options as $option) {
                    $this->assertStringContainsString('group=77', $option['value']);
                }
            }
        }
        $this->assertStringContainsString('group=77', $data->danger->url);
    }

    /**
     * A student on a restricted-view questionnaire (RESPVIEW_ALWAYS, no staff caps) keeps the
     * order select but gets no list link, no download and no delete — the old restricted branch.
     */
    public function test_for_summary_restricted_student_gets_order_only(): void {
        $this->resetAfterTest();
        [$questionnaire, $course] = $this->build_questionnaire_with_settings(questionnaire::RESPVIEW_ALWAYS);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $bar = report_action_bar::for_summary($questionnaire, 0, 'vall');
        $html = $this->render_bar($bar);

        $this->assertStringContainsString(get_string('order_default', 'questionnaire'), $html);
        $this->assertStringNotContainsString(get_string('viewbyresponse', 'questionnaire'), $html);
        $this->assertStringNotContainsString('action=dwnpg', $html);
        $this->assertStringNotContainsString('action=delallresp', $html);
    }

    /**
     * The individual-response bar carries the rid into the danger delete link.
     */
    public function test_for_individual_response_includes_rid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $bar = report_action_bar::for_individual_response($questionnaire, 0, 4242);
        $html = $this->render_bar($bar);

        $this->assertStringContainsString('action=dresp', $html);
        $this->assertStringContainsString('rid=4242', $html);
        $this->assertStringContainsString('individualresponse=1', $html);
        $this->assertStringContainsString('btn-outline-danger', $html);
        $this->assertStringContainsString(get_string('deleteresp', 'questionnaire'), $html);
    }

    /**
     * Without delete capability the individual-response bar has no danger link.
     */
    public function test_for_individual_response_without_cap_has_no_delete(): void {
        $this->resetAfterTest();
        [$questionnaire, $course] = $this->build_questionnaire_with_settings(questionnaire::RESPVIEW_ALWAYS);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $bar = report_action_bar::for_individual_response($questionnaire, 0, 4242);
        $html = $this->render_bar($bar);

        $this->assertStringNotContainsString('action=dresp', $html);
        $this->assertStringNotContainsString('btn-outline-danger', $html);
    }

    /**
     * The list view bar is a switcher only: no order select, no danger link.
     */
    public function test_for_response_list_is_switcher_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $bar = report_action_bar::for_response_list($questionnaire, 0);
        $html = $this->render_bar($bar);

        $this->assertStringContainsString(get_string('summary', 'questionnaire'), $html);
        $this->assertStringNotContainsString(get_string('order_default', 'questionnaire'), $html);
        $this->assertStringNotContainsString('btn-outline-danger', $html);
    }

    /**
     * The download page bar offers the way back to both report views.
     */
    public function test_for_download_offers_view_links(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $bar = report_action_bar::for_download($questionnaire, 0);
        $html = $this->render_bar($bar);

        $this->assertStringContainsString('action=vall', $html);
        $this->assertStringContainsString('byresponse=1', $html);
    }

    /**
     * A user with a single response gets an empty myreport bar (nothing to switch between).
     */
    public function test_for_myreport_single_response_has_no_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $bar = report_action_bar::for_myreport($questionnaire, 2, 0, 1, 'summary');

        $this->assertFalse($bar->has_content());
    }

    /**
     * A student with multiple responses gets the three myreport views; no download link
     * without the download capability.
     */
    public function test_for_myreport_multiple_responses_lists_views(): void {
        $this->resetAfterTest();
        [$questionnaire, $course] = $this->build_questionnaire_with_settings();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $bar = report_action_bar::for_myreport($questionnaire, (int) $student->id, 0, 2, 'vall');
        $html = $this->render_bar($bar);

        $this->assertTrue($bar->has_content());
        $this->assertStringContainsString('myreport.php', $html);
        $this->assertStringContainsString(get_string('summary', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('viewindividualresponse', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('myresponses', 'questionnaire'), $html);
        $this->assertStringContainsString('action=summary', $html);
        $this->assertStringNotContainsString('action=dwnpg', $html);
    }
}
