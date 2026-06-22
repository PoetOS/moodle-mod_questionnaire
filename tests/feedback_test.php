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
 * Unit tests for mod_questionnaire\feedback.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\feedback.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\feedback
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

        $questionnaire->survey()->update_settings(['feedbacksections' => 1]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $this->assertSame(1, $questionnaire->feedback()->mode());
        $this->assertTrue($questionnaire->feedback()->enabled());

        $questionnaire->survey()->update_settings(['feedbacksections' => 5]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $this->assertSame(5, $questionnaire->feedback()->mode());
        $this->assertTrue($questionnaire->feedback()->enabled());
    }

    /**
     * show_scores() / notes() / chart_type() pass through the matching survey fields.
     */
    public function test_field_accessors_pass_through(): void {
        $questionnaire = $this->setup_questionnaire();
        $questionnaire->survey()->update_settings([
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
        $questionnaire->survey()->update_settings([
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
}
