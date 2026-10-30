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
 * Unit tests for pagebreak::question_survey_display.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\question;

/**
 * Unit tests for mod_questionnaire\local\question\pagebreak rendering.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\pagebreak::question_survey_display
 */
final class pagebreak_test extends \advanced_testcase {
    /**
     * A page break renders nothing, and the blank-questionnaire argument may be left out.
     */
    public function test_question_survey_display_without_blank_argument(): void {
        $question = question::question_builder(QUESPAGEBREAK, 0, (object)['typeid' => QUESPAGEBREAK, 'name' => 'pb']);
        $method = new \ReflectionMethod($question, 'question_survey_display');
        $this->assertSame('', $method->invoke($question, null, null));
    }
}
