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

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/drawchart.php');

/**
 * Tests the feedback chart output.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class drawchart_test extends \advanced_testcase {
    /**
     * Chart types, with the feedback type and labels each one uses.
     *
     * @return array
     */
    public static function chart_provider(): array {
        return [
            'bipolar' => ['bipolar', 'sections', ['Say "hi"|Say "bye"', 'Two|Three']],
            'hbar sections' => ['hbar', 'sections', ['Say "hi"', 'Two']],
            'hbar global' => ['hbar', 'global', ['Say "hi"|Say "bye"']],
            'radar' => ['radar', 'sections', ['Say "hi"', 'Two', 'Three']],
            'rose' => ['rose', 'sections', ['Say "hi"', 'Two', 'Three']],
            'vprogress' => ['vprogress', 'global', ['Say "hi"|Say "bye"']],
        ];
    }

    /**
     * Test that chart labels and titles are written as JavaScript strings.
     *
     * @dataProvider chart_provider
     * @covers ::draw_chart
     * @param string $charttype
     * @param string $feedbacktype
     * @param array $labels
     */
    public function test_draw_chart_encodes_text(string $charttype, string $feedbacktype, array $labels): void {
        $this->resetAfterTest();
        $scores = array_fill(0, count($labels), 50);
        $globallabel = reset($labels);

        $output = draw_chart($feedbacktype, $labels, 'Group "A"', false, $charttype, $scores, $scores, $globallabel);

        $this->assertStringContainsString('chart.Set("chart.title", "Group \"A\"")', $output);
        $this->assertStringContainsString('\"hi\"', $output);
        $this->assertStringNotContainsString('"hi"', $output);
        $this->assertStringNotContainsString('"A"', $output);
    }
}
