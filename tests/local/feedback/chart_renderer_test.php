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
 * Asserts the canvas HTML the renderer emits and the RGraph scripts its spec asks
 * the mod_questionnaire/chart AMD module to load. Behavioural coverage of the
 * in-browser chart rendering lives in the Behat feature feedback_usergraph.feature.
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
        $this->assertStringContainsString(get_string('nocanvassupport', 'questionnaire'), $html);
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

    /**
     * Each chart type needs the RGraph core script followed by its own chart script.
     *
     * @dataProvider rgraph_scripts_provider
     * @param string $charttype
     * @param string $script
     */
    public function test_rgraph_scripts_lists_core_then_chart_script(string $charttype, string $script): void {
        global $CFG;
        $scripts = chart_renderer::rgraph_scripts($charttype);
        $this->assertSame(['RGraph.common.core.js', $script], $scripts);
        foreach ($scripts as $name) {
            $this->assertFileExists($CFG->dirroot . '/mod/questionnaire/javascript/RGraph/' . $name);
        }
    }

    /**
     * Chart types and the RGraph script that defines each.
     *
     * @return array
     */
    public static function rgraph_scripts_provider(): array {
        return [
            'bipolar' => ['bipolar', 'RGraph.bipolar.js'],
            'hbar' => ['hbar', 'RGraph.hbar.js'],
            'radar' => ['radar', 'RGraph.radar.js'],
            'rose' => ['rose', 'RGraph.rose.js'],
            'vprogress' => ['vprogress', 'RGraph.vprogress.js'],
        ];
    }

    /**
     * An unknown chart type needs no RGraph scripts.
     */
    public function test_rgraph_scripts_unknown_charttype_returns_empty(): void {
        $this->assertSame([], chart_renderer::rgraph_scripts('not-a-real-charttype'));
    }

    /**
     * The canvases carry the chart spec, RGraph scripts included, and the chart module gets only their wrapper's id.
     */
    public function test_render_puts_spec_on_wrapper_for_chart_module(): void {
        global $PAGE;
        $this->resetAfterTest();

        $html = chart_renderer::render($PAGE, 'global', [], 'Group A', false, 'rose', [70], [55], 'Speed', 'Your response');

        $this->assertMatchesRegularExpression('/^<span id="(questionnaire-chart-\\d+)" data-chart-spec="/', $html);
        preg_match('/^<span id="([^"]+)" data-chart-spec="([^"]*)"/', $html, $matches);
        $spec = json_decode(html_entity_decode($matches[2], ENT_QUOTES), true);
        $this->assertSame(['RGraph.common.core.js', 'RGraph.rose.js'], $spec['rgraph']);
        $this->assertSame('Rose', $spec['charts'][0]['type']);
        $this->assertStringContainsString('<canvas id="' . $matches[1] . '-primary"', $html);

        $amdjscode = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $amd = implode("\n", $amdjscode->getValue($PAGE->requires));
        $this->assertStringContainsString('amd.init("' . $matches[1] . '");', $amd);

        $jsincludes = new \ReflectionProperty($PAGE->requires, 'jsincludes');
        $this->assertStringNotContainsString('RGraph', json_encode(array_map(
            fn(array $urls) => array_keys($urls),
            $jsincludes->getValue($PAGE->requires)
        )));
    }
}
