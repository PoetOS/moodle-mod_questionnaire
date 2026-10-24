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
 * Unit tests for mod_questionnaire\settings_form.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\settings_form.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\settings_form
 */
final class settings_form_test extends \advanced_testcase {
    /**
     * Asserts definition() reads the questionnaire from customdata and seeds the field defaults from it.
     */
    public function test_definition_uses_customdata_questionnaire(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());

        $form = new settings_form('qsettings.php', ['questionnaire' => $questionnaire]);
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);

        $this->assertEquals($questionnaire->surveytitle(), $mform->getElement('title')->getValue());
        // Admin can create templates and public questionnaires, so realm is a select rather than hidden.
        $this->assertEquals('select', $mform->getElementType('realm'));
        $this->assertEquals([$questionnaire->survey()->realm()], $mform->getElement('realm')->getValue());
    }
}
