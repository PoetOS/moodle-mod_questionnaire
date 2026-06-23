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
 * Unit tests for mod_questionnaire\feedback_settings_controller.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\feedback_settings_controller.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\feedback_settings_controller
 */
final class feedback_settings_controller_test extends \advanced_testcase {
    /**
     * Build a course + questionnaire fixture and return its domain object.
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
     * Asserts save() writes feedbacksections / feedbackscores onto the survey row.
     */
    public function test_save_persists_feedbacksections_and_feedbackscores(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        $formdata = (object) [
            'feedbacksections' => 1,
            'feedbackscores'   => 1,
            'feedbacknotes'    => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => 'Note text'],
        ];

        $result = (new feedback_settings_controller($questionnaire))->save($formdata);

        $this->assertSame($questionnaire->surveyid(), $result);
        $row = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->surveyid()]);
        $this->assertEquals(1, $row->feedbacksections);
        $this->assertEquals(1, $row->feedbackscores);
        $this->assertStringContainsString('Note text', $row->feedbacknotes);
    }

    /**
     * Asserts save() resets feedbacksections to 0 when the form submits a zero value.
     */
    public function test_save_resets_feedbacksections_to_zero(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        // Seed feedbacksections = 2 first.
        $questionnaire->feedback()->update_settings(['feedbacksections' => 2]);

        $formdata = (object) [
            'feedbacksections' => 0,
            'feedbackscores'   => 0,
            'feedbacknotes'    => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => ''],
        ];
        (new feedback_settings_controller($questionnaire))->save($formdata);

        $this->assertEquals(0, $DB->get_field('questionnaire_survey', 'feedbacksections', ['id' => $questionnaire->surveyid()]));
    }

    /**
     * Asserts save() picks chart_type_global when sections=1 and usergraph is on.
     */
    public function test_save_picks_chart_type_global_when_one_section_with_usergraph(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        set_config('usergraph', 1, 'questionnaire');

        $formdata = (object) [
            'feedbacksections'       => 1,
            'feedbackscores'         => 1,
            'feedbacknotes'          => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => ''],
            'chart_type_global'      => 'bipolar',
            'chart_type_two_sections' => 'rose',
            'chart_type_sections'    => 'hbar',
        ];
        (new feedback_settings_controller($questionnaire))->save($formdata);

        $this->assertEquals('bipolar', $DB->get_field('questionnaire_survey', 'charttype', ['id' => $questionnaire->surveyid()]));
    }

    /**
     * Asserts save() picks chart_type_sections when sections > 2 and usergraph is on.
     */
    public function test_save_picks_chart_type_sections_when_many_with_usergraph(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        set_config('usergraph', 1, 'questionnaire');

        $formdata = (object) [
            'feedbacksections'       => 4,
            'feedbackscores'         => 1,
            'feedbacknotes'          => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => ''],
            'chart_type_global'      => 'bipolar',
            'chart_type_two_sections' => 'rose',
            'chart_type_sections'    => 'radar',
        ];
        (new feedback_settings_controller($questionnaire))->save($formdata);

        $this->assertEquals('radar', $DB->get_field('questionnaire_survey', 'charttype', ['id' => $questionnaire->surveyid()]));
    }

    /**
     * Asserts ensure_first_section() creates a section when none exist and feedback is enabled.
     */
    public function test_ensure_first_section_creates_section_when_missing(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        $questionnaire->feedback()->update_settings(['feedbacksections' => 1]);

        $surveyid = $questionnaire->surveyid();
        $this->assertEquals(0, $DB->count_records('questionnaire_fb_sections', ['surveyid' => $surveyid]));

        (new feedback_settings_controller($questionnaire))->ensure_first_section();

        $this->assertEquals(1, $DB->count_records('questionnaire_fb_sections', ['surveyid' => $surveyid]));
    }

    /**
     * Asserts ensure_first_section() does NOT create a section when feedbacksections == 0.
     */
    public function test_ensure_first_section_no_op_when_feedback_disabled(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        $surveyid = $questionnaire->surveyid();
        $this->assertEquals(0, $DB->count_records('questionnaire_fb_sections', ['surveyid' => $surveyid]));

        (new feedback_settings_controller($questionnaire))->ensure_first_section();

        $this->assertEquals(0, $DB->count_records('questionnaire_fb_sections', ['surveyid' => $surveyid]));
    }
}
