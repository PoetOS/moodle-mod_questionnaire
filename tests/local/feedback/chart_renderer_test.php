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
 * Asserts the canvas HTML the renderer emits. The chart spec passed to the
 * mod_questionnaire/chart AMD module is serialised into the page's AMD
 * init bundle and not directly introspectable from PHP — behavioural
 * coverage of the in-browser chart rendering lives in the Behat feature
 * feedback_usergraph.feature.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\chart_renderer
 */
final class chart_renderer_test extends \advanced_testcase {
    /**
     * Render a 'global' feedbacktype + 'bipolar' chart with allscore set and
     * assert both canvas placeholders appear in the returned HTML.
     */
    public function test_render_global_bipolar_emits_both_canvases(): void {
        global $PAGE;
        $this->resetAfterTest();

        $html = chart_renderer::render(
            $PAGE,
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

        $this->assertStringContainsString('<canvas', $html);
        $this->assertStringContainsString('-primary', $html);
        $this->assertStringContainsString('-secondary', $html);
        $this->assertStringContainsString('[No canvas support]', $html);
    }

    /**
     * Render a 'sections' feedbacktype + 'radar' chart with allresponses=true so only
     * the secondary "group" canvas is emitted.
     */
    public function test_render_sections_radar_allresponses_emits_secondary_only(): void {
        global $PAGE;
        $this->resetAfterTest();

        $html = chart_renderer::render(
            $PAGE,
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
        $this->assertStringContainsString('-secondary', $html);
        $this->assertStringNotContainsString('-primary', $html);
    }

    /**
     * An unrecognised charttype short-circuits and returns an empty string.
     */
    public function test_render_unknown_charttype_returns_empty_string(): void {
        global $PAGE;
        $this->resetAfterTest();

        $html = chart_renderer::render(
            $PAGE,
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
