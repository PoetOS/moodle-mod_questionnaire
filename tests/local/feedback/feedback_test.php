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
 * Unit tests for mod_questionnaire\local\feedback\feedback.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\feedback\feedback.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\feedback
 */
final class feedback_test extends \advanced_testcase {
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
     * mode() / enabled() reflect the feedbacksections column.
     */
    public function test_mode_and_enabled_reflect_feedbacksections(): void {
        $questionnaire = $this->setup_questionnaire();

        $this->assertSame(0, $questionnaire->feedback()->mode());
        $this->assertFalse($questionnaire->feedback()->enabled());

        $questionnaire->feedback()->update_settings(['feedbacksections' => 1]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $this->assertSame(1, $questionnaire->feedback()->mode());
        $this->assertTrue($questionnaire->feedback()->enabled());

        $questionnaire->feedback()->update_settings(['feedbacksections' => 5]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $this->assertSame(5, $questionnaire->feedback()->mode());
        $this->assertTrue($questionnaire->feedback()->enabled());
    }

    /**
     * show_scores() / notes() / chart_type() pass through the matching survey fields.
     */
    public function test_field_accessors_pass_through(): void {
        $questionnaire = $this->setup_questionnaire();
        $questionnaire->feedback()->update_settings([
            'feedbackscores' => 1,
            'feedbacknotes'  => '<p>Hello</p>',
            'charttype'      => 'radar',
        ]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $this->assertTrue($questionnaire->feedback()->show_scores());
        $this->assertSame('<p>Hello</p>', $questionnaire->feedback()->notes());
        $this->assertSame('radar', $questionnaire->feedback()->chart_type());
    }

    /**
     * rendered_notes() returns '' when notes is empty without calling file_rewrite_pluginfile_urls.
     */
    public function test_rendered_notes_empty_short_circuits(): void {
        $questionnaire = $this->setup_questionnaire();
        $this->assertSame('', $questionnaire->feedback()->rendered_notes());
    }

    /**
     * rendered_notes() returns the pluginfile-rewritten notes when set.
     */
    public function test_rendered_notes_rewrites_pluginfile_urls(): void {
        $questionnaire = $this->setup_questionnaire();
        $questionnaire->feedback()->update_settings([
            'feedbacknotes' => '<p>Plain text</p>',
        ]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $rendered = $questionnaire->feedback()->rendered_notes();
        $this->assertStringContainsString('Plain text', $rendered);
    }

    /**
     * has_any_feedback_questions() is false on an empty questionnaire and true once a valid-feedback
     * question (rate, yesno, slider) is added.
     */
    public function test_has_any_feedback_questions(): void {
        $questionnaire = $this->setup_questionnaire();
        $this->assertFalse($questionnaire->feedback()->has_any_feedback_questions());

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $generator->create_question(
            $questionnaire,
            [
                'surveyid' => $questionnaire->surveyid(),
                'name'     => 'Q1',
                'typeid'   => QUESYESNO,
                'content'  => 'Y/N',
                'required' => 'y',
            ]
        );
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $this->assertTrue($questionnaire->feedback()->has_any_feedback_questions());
    }

    /**
     * $q->feedback() returns the same instance on repeated calls (cached lazy accessor).
     */
    public function test_feedback_accessor_is_cached(): void {
        $questionnaire = $this->setup_questionnaire();
        $this->assertSame($questionnaire->feedback(), $questionnaire->feedback());
    }

    /**
     * update_settings() persists allowlisted feedback fields onto the survey row.
     */
    public function test_update_settings_persists_allowlisted_fields(): void {
        global $DB;
        $questionnaire = $this->setup_questionnaire();

        $result = $questionnaire->feedback()->update_settings([
            'feedbacksections' => 3,
            'feedbackscores'   => 1,
            'feedbacknotes'    => '<p>Notes</p>',
            'charttype'        => 'bipolar',
        ]);

        $this->assertSame($questionnaire->surveyid(), $result);
        $row = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->surveyid()]);
        $this->assertEquals(3, $row->feedbacksections);
        $this->assertEquals(1, $row->feedbackscores);
        $this->assertSame('<p>Notes</p>', $row->feedbacknotes);
        $this->assertSame('bipolar', $row->charttype);
    }

    /**
     * update_settings() rejects fields outside the feedback allowlist via coding_exception.
     */
    public function test_update_settings_rejects_unknown_field(): void {
        $questionnaire = $this->setup_questionnaire();

        $this->expectException(\coding_exception::class);
        $questionnaire->feedback()->update_settings(['title' => 'spoof']);
    }

    /**
     * update_settings() also rejects survey-form metadata such as 'id' or 'sid'.
     */
    public function test_update_settings_rejects_survey_metadata(): void {
        $questionnaire = $this->setup_questionnaire();

        $this->expectException(\coding_exception::class);
        $questionnaire->feedback()->update_settings(['id' => 999]);
    }

    /**
     * ensure_first_section() creates a section when feedback is enabled and none exist.
     */
    public function test_ensure_first_section_creates_when_missing(): void {
        global $DB;
        $questionnaire = $this->setup_questionnaire();
        $questionnaire->feedback()->update_settings(['feedbacksections' => 1]);
        $surveyid = $questionnaire->surveyid();
        $this->assertSame(0, $DB->count_records('questionnaire_fb_sections', ['surveyid' => $surveyid]));

        $result = $questionnaire->feedback()->ensure_first_section();

        $this->assertSame(0, $result); // Returns 0 because no section existed before this call.
        $this->assertSame(1, $DB->count_records('questionnaire_fb_sections', ['surveyid' => $surveyid]));
    }

    /**
     * ensure_first_section() is a no-op when feedback is disabled.
     */
    public function test_ensure_first_section_no_op_when_disabled(): void {
        global $DB;
        $questionnaire = $this->setup_questionnaire();

        $result = $questionnaire->feedback()->ensure_first_section();

        $this->assertSame(0, $result);
        $this->assertSame(0, $DB->count_records(
            'questionnaire_fb_sections',
            ['surveyid' => $questionnaire->surveyid()]
        ));
    }
}
