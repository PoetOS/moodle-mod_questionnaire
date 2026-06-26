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
 * Unit tests for mod_questionnaire\local\feedback\chart_renderer.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

/**
 * Unit tests for mod_questionnaire\local\feedback\chart_renderer.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\chart_renderer
 */
final class chart_renderer_test extends \advanced_testcase {
    /**
     * Render a 'global' feedbacktype + 'bipolar' chart with allscore set so both the
     * 'your response' and the group-name titles are emitted.
     */
    public function test_render_global_bipolar_emits_script_and_titles(): void {
        // Set allresponses=false so the "your response" chart renders, allscore set so the
        // group-comparison chart renders too — both titles should appear.
        $html = chart_renderer::render(
            'global',
            [],
            'Group A',
            false,
            'bipolar',
            [70, 30],
            [55, 45],
            'Speed | Quality',
            'Your response'
        );

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('Speed', $html);
        $this->assertStringContainsString('Group A', $html);
        $this->assertStringContainsString('Your response', $html);
    }

    /**
     * Render a 'sections' feedbacktype + 'radar' chart against two sections.
     */
    public function test_render_sections_radar_uses_section_labels(): void {
        $html = chart_renderer::render(
            'sections',
            ['Section 1', 'Section 2'],
            'All participants',
            true,
            'radar',
            [40, 60],
            [55, 45],
            null,
            'This response'
        );

        $this->assertStringContainsString('<canvas', $html);
        $this->assertStringContainsString('Section 1', $html);
        $this->assertStringContainsString('Section 2', $html);
        $this->assertStringContainsString('All participants', $html);
    }

    /**
     * An unrecognised charttype falls through the switch and returns an empty string.
     */
    public function test_render_unknown_charttype_returns_empty_string(): void {
        $html = chart_renderer::render(
            'global',
            [],
            'Group A',
            false,
            'not-a-real-charttype',
            [50, 50],
            null,
            'X',
            'Your response'
        );

        $this->assertSame('', $html);
    }
}
