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
 * Unit tests for mod_questionnaire\local\question\slider.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\question;

/**
 * Unit tests for mod_questionnaire\local\question\slider.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\slider::is_valid_value
 */
final class slider_test extends \advanced_testcase {
    /**
     * Only values inside the range and on a step from the minimum are valid.
     */
    public function test_is_valid_value_checks_range_and_step(): void {
        $this->resetAfterTest();
        $question = $this->build_slider(['minrange' => 1, 'maxrange' => 10, 'startingvalue' => 5, 'stepvalue' => 3]);

        foreach ([1, '4', 7, 10] as $value) {
            $this->assertTrue($question->is_valid_value($value), var_export($value, true));
        }
        foreach ([0, 2, 11, 100000, -1, 'abc', ''] as $value) {
            $this->assertFalse($question->is_valid_value($value), var_export($value, true));
        }
    }

    /**
     * A slider saved without a range accepts any number, but nothing else.
     */
    public function test_is_valid_value_without_range(): void {
        $this->resetAfterTest();
        $question = $this->build_slider(null);

        $this->assertTrue($question->is_valid_value(100000));
        $this->assertTrue($question->is_valid_value('-3'));
        $this->assertFalse($question->is_valid_value('abc'));
    }

    /**
     * Build a slider question with the given range settings.
     *
     * @param array|null $ranges The extradata settings, or null for none.
     * @return slider
     */
    private function build_slider(?array $ranges): slider {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questiondata = ['content' => 'How much?'];
        if ($ranges !== null) {
            $questiondata['extradata'] = json_encode($ranges);
        }
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $generator->create_test_questionnaire($course, QUESSLIDER, $questiondata);
        $questions = $questionnaire->questions();
        return reset($questions);
    }
}
