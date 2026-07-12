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
 * Unit tests for the questionnaire secondary-navigation view and the
 * settings-navigation gating rules it depends on.
 *
 * These carry the navigation-visibility rules that used to be pinned by
 * tabs_test.php: management-node capability gating, the preview owner/questions
 * gates, non-respondents gating, and the leaf shape of the report nodes after
 * the destructive/duplicate children were pruned.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\navigation;

use mod_questionnaire\navigation\views\secondary;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire secondary/settings navigation.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\navigation\views\secondary
 * @covers \mod_questionnaire\questionnaire::extend_settings_navigation
 */
final class secondary_test extends \advanced_testcase {
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
     * Build a moodle_page for the questionnaire's view page.
     *
     * The cm/course are re-resolved fresh: the questionnaire object's cached snapshots
     * predate any seeded responses, and on Moodle 5.1+ a stale course record makes the
     * resolved cm_info report not-user-visible, which empties the module settings branch.
     *
     * @param questionnaire $questionnaire
     * @return \moodle_page
     */
    private function build_page(questionnaire $questionnaire): \moodle_page {
        $course = get_course($questionnaire->course()->id);
        $cminfo = get_fast_modinfo($course)->get_cm($questionnaire->coursemodule()->id);
        $page = new \moodle_page();
        $page->set_cm($cminfo, $course);
        $page->set_url('/mod/questionnaire/view.php', ['id' => $cminfo->id]);
        return $page;
    }

    /**
     * Build an initialised settings navigation for the questionnaire's view page.
     *
     * @param questionnaire $questionnaire
     * @return \settings_navigation
     */
    private function build_settings_nav(questionnaire $questionnaire): \settings_navigation {
        global $PAGE;
        // Nav hooks from other components (e.g. usertours) read the global page URL.
        $PAGE->set_url('/mod/questionnaire/view.php', ['id' => $questionnaire->coursemodule()->id]);
        $page = $this->build_page($questionnaire);
        $settingsnav = new \settings_navigation($page);
        $settingsnav->initialise();
        return $settingsnav;
    }

    /**
     * The custom secondary view maps the plugin's node keys so Questions, Preview and
     * Responses order into the visible bar and the rest overflow into More.
     */
    public function test_secondary_mapping_orders_plugin_nodes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $secondary = new secondary($this->build_page($questionnaire));
        $method = new \ReflectionMethod($secondary, 'get_default_module_mapping');
        $method->setAccessible(true);
        $mapping = $method->invoke($secondary)[\navigation_node::TYPE_SETTING];

        $this->assertSame(2, $mapping['questions']);
        $this->assertSame(3, $mapping['preview']);
        $this->assertSame(4, $mapping['vall']);
        $this->assertGreaterThan($mapping['vall'], $mapping['nonrespondents']);
        $this->assertGreaterThan($mapping['vall'], $mapping['feedback']);
        $this->assertGreaterThan($mapping['vall'], $mapping['advancedsettings']);
        // The core keys survive the merge.
        $this->assertSame(1, $mapping['modedit']);
    }

    /**
     * Admin/owner sees the management nodes: advanced settings, questions, feedback,
     * preview and non-respondents.
     */
    public function test_settings_nav_admin_sees_management_nodes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $settingsnav = $this->build_settings_nav($questionnaire);

        foreach (['advancedsettings', 'questions', 'feedback', 'preview', 'nonrespondents'] as $key) {
            $this->assertNotFalse($settingsnav->find($key, \navigation_node::TYPE_SETTING), "Missing node: {$key}");
        }
    }

    /**
     * The preview node is hidden when the survey has no questions (the gate the old
     * preview tab enforced).
     */
    public function test_settings_nav_preview_hidden_without_questions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire_no_questions();

        $settingsnav = $this->build_settings_nav($questionnaire);

        $this->assertFalse($settingsnav->find('preview', \navigation_node::TYPE_SETTING));
        // The rest of the management row is unaffected.
        $this->assertNotFalse($settingsnav->find('questions', \navigation_node::TYPE_SETTING));
    }

    /**
     * A student sees neither the management nodes nor non-respondents.
     */
    public function test_settings_nav_student_sees_no_management_nodes(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $this->setUser($student);

        $settingsnav = $this->build_settings_nav($questionnaire);

        foreach (['advancedsettings', 'questions', 'feedback', 'nonrespondents'] as $key) {
            $this->assertFalse($settingsnav->find($key, \navigation_node::TYPE_SETTING), "Unexpected node: {$key}");
        }
    }

    /**
     * The report node (vall) is a leaf: the order / delete-all / download / by-response
     * children moved into the in-page action bar and must not resurface in navigation.
     */
    public function test_settings_nav_vall_is_a_leaf(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        // The vall node only appears once at least one response exists.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            (int) $student->id,
            true
        );
        $this->setAdminUser();

        $settingsnav = $this->build_settings_nav($questionnaire);
        $vall = $settingsnav->find('vall', \navigation_node::TYPE_SETTING);

        $this->assertNotFalse($vall);
        $this->assertFalse($vall->has_children());
    }

    /**
     * The yourresponses node is a leaf for a user with multiple responses: its sub-views
     * moved into the myreport action bar.
     */
    public function test_settings_nav_yourresponses_is_a_leaf(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $plugingen = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $plugingen->generate_response($questionnaire, $questionnaire->questions(), (int) $student->id, true);
        $plugingen->generate_response($questionnaire, $questionnaire->questions(), (int) $student->id, true);
        $this->setUser($student);

        $settingsnav = $this->build_settings_nav($questionnaire);
        $yourresponses = $settingsnav->find('yourresponses', \navigation_node::TYPE_SETTING);

        $this->assertNotFalse($yourresponses);
        $this->assertFalse($yourresponses->has_children());
    }
}
