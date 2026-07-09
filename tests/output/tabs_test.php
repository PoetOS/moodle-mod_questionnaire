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
 * Unit tests for mod_questionnaire\output\tabs.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\output\tabs.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\tabs
 */
final class tabs_test extends \advanced_testcase {
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
     * Build a course + questionnaire with NO questions attached.
     *
     * @return questionnaire
     */
    private function build_questionnaire_no_questions(): questionnaire {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        return $generator->get_plugin_generator('mod_questionnaire')->create_test_questionnaire($course);
    }

    /**
     * Build a course + questionnaire configured for unlimited-per-user responses.
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
        return [\mod_questionnaire\questionnaire::from_instanceid($questionnaire->id()), $course];
    }

    /**
     * Seed a complete student response so the staff "All responses" tab path is reachable.
     *
     * @param questionnaire $questionnaire
     * @return void
     */
    private function seed_complete_response(questionnaire $questionnaire): void {
        $generator = $this->getDataGenerator();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $generator->get_plugin_generator('mod_questionnaire')->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            (int) $student->id,
            true
        );
        $this->setAdminUser();
    }

    /**
     * Render the tabs into a fresh feedbackpage and return its tabsarea HTML.
     *
     * @param questionnaire $questionnaire
     * @param string $currenttab
     * @param int|null $currentgroupid
     * @param int|null $rid
     * @return string
     */
    private function render_tabsarea(
        questionnaire $questionnaire,
        string $currenttab,
        ?int $currentgroupid = null,
        ?int $rid = null,
    ): string {
        global $PAGE;
        $page = new feedbackpage();
        (new tabs($questionnaire, $currenttab, $currentgroupid, $rid))->render($page);
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        return (string) ($data->tabsarea ?? '');
    }

    /**
     * Owner with full caps sees the settings + questions + feedback + preview row.
     * Uses an inactive currenttab so every management tab renders with a clickable href.
     */
    public function test_owner_admin_sees_management_tabs(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $html = $this->render_tabsarea($questionnaire, 'inactive-tab');

        $this->assertStringContainsString('qsettings.php', $html);
        $this->assertStringContainsString('questions.php', $html);
        $this->assertStringContainsString('feedback.php', $html);
        $this->assertStringContainsString('preview.php', $html);
    }

    /**
     * On the 'vall' tab with a complete response present, the all-responses sub-row appears.
     * The vrespsummary sub-tab is clickable on this currenttab and includes byresponse=1.
     */
    public function test_vall_subrow_visible_when_responses_present(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();
        $this->seed_complete_response($questionnaire);

        $html = $this->render_tabsarea($questionnaire, 'vall');

        // The vall sub-row introduces the "View by response" sub-tab (vrespsummary).
        $this->assertStringContainsString('byresponse=1', $html);
        $this->assertStringContainsString('action=vresp', $html);
    }

    /**
     * On the 'individualresp' tab the deleteresp sub-tab becomes a clickable link
     * carrying the supplied rid.
     */
    public function test_deleteresp_tab_includes_rid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();
        $this->seed_complete_response($questionnaire);

        $html = $this->render_tabsarea($questionnaire, 'individualresp', 0, 4242);

        $this->assertStringContainsString('rid=4242', $html);
    }

    /**
     * The currentgroupid parameter feeds into the All-responses sub-row href group= param.
     */
    public function test_currentgroupid_threads_into_subrow_links(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();
        $this->seed_complete_response($questionnaire);

        $html = $this->render_tabsarea($questionnaire, 'valldefault', 77);

        $this->assertStringContainsString('group=77', $html);
    }

    /**
     * Unknown tab name still renders the management row without errors.
     */
    public function test_unknown_tab_renders_main_row_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $html = $this->render_tabsarea($questionnaire, 'no-such-tab');

        // No sub-row indicators (order_default appears only on the vall sub-row).
        $this->assertStringNotContainsString('order_default', $html);
        // Main row still renders something for settings.
        $this->assertStringContainsString('qsettings.php', $html);
    }

    /**
     * Student without manage caps does NOT see the staff management row.
     */
    public function test_student_without_responses_sees_no_tabsarea(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $this->setUser($student);

        $html = $this->render_tabsarea($questionnaire, 'view');

        // No row to print (count(row) <= 1 short-circuits).
        $this->assertSame('', $html);
    }

    /**
     * Preview tab does not appear when the survey has zero questions.
     */
    public function test_preview_tab_hidden_when_no_questions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire_no_questions();

        $html = $this->render_tabsarea($questionnaire, 'inactive-tab');

        // Management row still shows settings, but preview.php is skipped when there are no questions.
        $this->assertStringContainsString('qsettings.php', $html);
        $this->assertStringNotContainsString('preview.php', $html);
    }

    /**
     * Admin on the management row sees the non-respondents tab.
     */
    public function test_nonrespondents_tab_present_for_admin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $html = $this->render_tabsarea($questionnaire, 'inactive-tab');

        $this->assertStringContainsString('show_nonrespondents.php', $html);
    }

    /**
     * On a vall-sort tab the row3 sub-row emits order_default / order_ascending / order_descending.
     */
    public function test_vall_sort_subrow_emits_order_tabs(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();
        $this->seed_complete_response($questionnaire);

        $html = $this->render_tabsarea($questionnaire, 'valldefault');

        // Order-tab labels only appear from the row3 build.
        $this->assertStringContainsString(get_string('order_default', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('order_ascending', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('order_descending', 'questionnaire'), $html);
        // downloadcsv and deleteall variants of the sort-subrow.
        $this->assertStringContainsString('action=dwnpg', $html);
        $this->assertStringContainsString('action=delallresp', $html);
    }

    /**
     * Student with 2+ own responses on a myreport sub-tab sees the myreport sub-row.
     */
    public function test_myreport_subrow_visible_for_student_with_multiple_responses(): void {
        $this->resetAfterTest();
        [$questionnaire, $course] = $this->build_questionnaire_with_settings();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $plugingen = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        // Two completed responses for the same student — the sub-row only renders when usernumresp > 1.
        $plugingen->generate_response($questionnaire, $questionnaire->questions(), (int) $student->id, true);
        $plugingen->generate_response($questionnaire, $questionnaire->questions(), (int) $student->id, true);
        $this->setUser($student);

        $html = $this->render_tabsarea($questionnaire, 'myvall');

        // Sub-row labels: summary / view-by-response / my-responses.
        $this->assertStringContainsString(get_string('summary', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('viewindividualresponse', 'questionnaire'), $html);
        $this->assertStringContainsString(get_string('myresponses', 'questionnaire'), $html);
        // Sub-row hrefs point at myreport.php (not report.php).
        $this->assertStringContainsString('myreport.php', $html);
        $this->assertStringContainsString('action=summary', $html);
    }

    /**
     * Student with responses on a restricted questionnaire (RESPVIEW_ALWAYS, no anytime cap)
     * hits the restricted allreport branch — distinguished by the sid= querystring arg.
     */
    public function test_restricted_allreport_branch_emits_sid_arg(): void {
        $this->resetAfterTest();
        [$questionnaire, $course] = $this->build_questionnaire_with_settings(
            \mod_questionnaire\questionnaire::RESPVIEW_ALWAYS
        );

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            (int) $student->id,
            true
        );
        $this->setUser($student);

        $html = $this->render_tabsarea($questionnaire, 'valldefault');

        // The restricted branch is the only path that includes sid= in the allreport link.
        $this->assertStringContainsString('sid=' . $questionnaire->surveyid(), $html);
        // And the order sub-tabs still appear (restricted branch builds a row3).
        $this->assertStringContainsString(get_string('order_default', 'questionnaire'), $html);
    }
}
