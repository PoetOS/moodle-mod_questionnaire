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
 * Unit tests for mod_questionnaire\survey_settings_controller.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\survey_settings_controller.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\survey_settings_controller
 */
final class survey_settings_controller_test extends \advanced_testcase {
    /**
     * Build a course + questionnaire fixture.
     *
     * @return questionnaire
     */
    private function setup_questionnaire(): questionnaire {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        return $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
    }

    /**
     * Build the minimal formdata stdClass that the qsettings form returns from get_data().
     *
     * @param questionnaire $questionnaire
     * @param array $overrides
     * @return \stdClass
     */
    private function formdata(questionnaire $questionnaire, array $overrides = []): \stdClass {
        $defaults = [
            'name'       => $questionnaire->survey()->name(),
            'realm'      => $questionnaire->survey()->realm(),
            'title'      => $questionnaire->survey()->title(),
            'subtitle'   => 'Sub',
            'info'       => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => 'About this survey'],
            'thankspage' => 'https://example.org/done',
            'thankhead'  => 'Thanks!',
            'thankbody'  => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => 'Bye'],
            'email'      => 'alerts@example.org',
            'courseid'   => $questionnaire->course()->id,
        ];
        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Asserts save() persists each settings field onto the survey row.
     */
    public function test_save_persists_form_fields(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        $result = (new survey_settings_controller($questionnaire))->save($this->formdata($questionnaire, [
            'subtitle'  => 'New subtitle',
            'thankhead' => 'New thank head',
            'email'     => 'sup@example.org',
        ]));

        $this->assertSame($questionnaire->surveyid(), $result);
        $row = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->surveyid()]);
        $this->assertEquals('New subtitle', $row->subtitle);
        $this->assertEquals('New thank head', $row->thankhead);
        $this->assertEquals('sup@example.org', $row->email);
        $this->assertStringContainsString('About this survey', $row->info);
        $this->assertStringContainsString('Bye', $row->thankbody);
    }

    /**
     * Asserts save() throws when underlying update rejects (e.g. duplicate name).
     */
    public function test_save_throws_when_update_rejected(): void {
        $questionnaire = $this->setup_questionnaire();

        // Empty title is rejected by survey::update_settings().
        $this->expectException(\moodle_exception::class);
        (new survey_settings_controller($questionnaire))->save($this->formdata($questionnaire, [
            'title' => '',
        ]));
    }

    /**
     * Asserts save() leaves the survey row id untouched even when the form would otherwise
     * carry a misleading 'id' value. Hardened against the historical feedback.php bug.
     */
    public function test_save_does_not_corrupt_survey_id(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        $surveyid = $questionnaire->surveyid();

        $formdata = $this->formdata($questionnaire);
        // Smuggle a stray id like the legacy form payload used to. survey_settings_controller
        // builds an explicit allowlist, so this must be ignored.
        $formdata->id = 999999;

        (new survey_settings_controller($questionnaire))->save($formdata);

        $this->assertTrue($DB->record_exists('questionnaire_survey', ['id' => $surveyid]));
    }
}
