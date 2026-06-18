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
}
