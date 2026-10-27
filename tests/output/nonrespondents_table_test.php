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
 * Unit tests for mod_questionnaire\output\nonrespondents_table.
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
 * Unit tests for mod_questionnaire\output\nonrespondents_table.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\nonrespondents_table
 */
final class nonrespondents_table_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /**
     * Create a course and questionnaire; optionally enable save-and-resume.
     *
     * @param bool $resume
     * @return questionnaire
     */
    private function build_questionnaire(bool $resume = false): questionnaire {
        global $DB;
        $this->course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = $generator->create_instance(['course' => $this->course->id]);
        $DB->set_field('questionnaire', 'resume', $resume ? 1 : 0, ['id' => $instance->id()]);
        return questionnaire::from_instanceid($instance->id());
    }

    /**
     * Build a table for the questionnaire.
     *
     * @param questionnaire $questionnaire
     * @return nonrespondents_table
     */
    private function build_table(questionnaire $questionnaire): nonrespondents_table {
        global $PAGE;
        $PAGE->set_url('/mod/questionnaire/show_nonrespondents.php', ['id' => $questionnaire->coursemodule()->id]);
        $table = new nonrespondents_table($questionnaire, new moodle_url('/mod/questionnaire/show_nonrespondents.php'));
        $table->setup();
        return $table;
    }

    /**
     * A teacher who can message gets the select column; status appears only with save-and-resume.
     */
    public function test_columns_for_teacher_with_resume(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire(true);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));

        $table = $this->build_table($questionnaire);

        $this->assertEquals(
            ['userpic', 'fullname', 'email', 'city', 'country', 'lastaccess', 'status', 'select'],
            array_keys($table->columns)
        );
    }

    /**
     * Without save-and-resume there is no status column.
     */
    public function test_no_status_column_without_resume(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire(false);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));

        $table = $this->build_table($questionnaire);

        $this->assertArrayNotHasKey('status', $table->columns);
        $this->assertArrayHasKey('select', $table->columns);
    }

    /**
     * A non-editing teacher (no message capability) gets no select column.
     */
    public function test_no_select_column_without_message_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire(true);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'teacher'));
        $role = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('mod/questionnaire:message', CAP_PROHIBIT, $role, $questionnaire->context(), true);

        $table = $this->build_table($questionnaire);

        $this->assertArrayNotHasKey('select', $table->columns);
    }

    /**
     * add_user() renders the profile link, status and a labelled checkbox carrying data-started.
     */
    public function test_add_user_renders_row(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire(true);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['city' => 'Perth', 'country' => 'AU']);
        $table = $this->build_table($questionnaire);

        $table->add_user($student, true);
        $html = $table->render();

        $this->assertStringContainsString(fullname($student), $html);
        $this->assertStringContainsString('/user/view.php?id=' . $student->id, $html);
        $this->assertStringContainsString('Perth', $html);
        $this->assertStringContainsString(get_string_manager()->get_list_of_countries()['AU'], $html);
        $this->assertStringContainsString(get_string('started', 'questionnaire'), $html);
        $this->assertStringContainsString('name="messageuser[]"', $html);
        $this->assertStringContainsString('data-started="1"', $html);
        $this->assertStringContainsString('for="messageuser_' . $student->id . '"', $html);
        $this->assertStringContainsString(get_string('selectitem', 'moodle', fullname($student)), $html);
    }

    /**
     * A user who never logged in shows "Never" and a not-started checkbox.
     */
    public function test_add_user_never_accessed_not_started(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire(true);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['lastaccess' => 0]);
        $table = $this->build_table($questionnaire);

        $table->add_user($student, false);
        $html = $table->render();

        $this->assertStringContainsString(get_string('never'), $html);
        $this->assertStringContainsString(get_string('not_started', 'questionnaire'), $html);
        $this->assertStringContainsString('data-started="0"', $html);
    }

    /**
     * Adding rows prints nothing: the page adds them before its header, so any echo would land above it.
     */
    public function test_add_user_prints_nothing(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $table = $this->build_table($questionnaire);

        $this->expectOutputString('');
        $table->add_user($student, false);
    }
}
