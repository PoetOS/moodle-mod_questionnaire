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
 * Unit tests for mod_questionnaire\local\feedback\feedback_section_form.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\feedback\feedback_section_form.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\feedback_section_form
 */
final class feedback_section_form_test extends \advanced_testcase {
    /**
     * Asserts definition() reads the questionnaire from customdata for the context and feedback mode.
     */
    public function test_definition_uses_customdata_questionnaire(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $instance->feedback()->update_settings(['feedbacksections' => 1]);
        $questionnaire = questionnaire::from_instanceid($instance->id());
        $PAGE->set_context($questionnaire->context());

        $customdata = new \stdClass();
        $customdata->questionnaire = $questionnaire;
        $customdata->feedbacksection = section::new_section($questionnaire->surveyid(), 'Global');
        $customdata->validquestions = [];
        $customdata->survey = $questionnaire->survey()->to_stdclass();
        $customdata->sectionselect = [];

        $form = new feedback_section_form('fbsections.php', $customdata, $PAGE->get_renderer('mod_questionnaire'));
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);

        $this->assertEquals($questionnaire->context(), $form->context);
        // Mode 1 (global feedback) labels the content header with the global heading.
        $this->assertEquals(
            get_string('feedbackglobalheading', 'questionnaire'),
            $mform->getElement('contenthdr')->_text
        );
    }
}
