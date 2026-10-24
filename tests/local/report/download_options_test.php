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
 * Unit tests for mod_questionnaire\local\report\download_options.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\report;

/**
 * Unit tests for mod_questionnaire\local\report\download_options.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\report\download_options
 */
final class download_options_test extends \advanced_testcase {
    /**
     * With nothing chosen, the export is a plain download with no extras.
     */
    public function test_defaults(): void {
        $options = new download_options();

        $this->assertSame('', $options->dataformat);
        $this->assertFalse($options->choicecodes);
        $this->assertFalse($options->choicetext);
        $this->assertFalse($options->showincompletes);
        $this->assertFalse($options->rankaverages);
        $this->assertFalse($options->emailreport);
        $this->assertFalse($options->emailroles);
        $this->assertSame('', $options->emailextra);
    }

    /**
     * Each form choice is kept, and checkbox values from the request become booleans.
     */
    public function test_holds_form_choices(): void {
        $options = new download_options('excel', 1, 1, 0, 1, true, 1, 'a@example.com,b@example.com');

        $this->assertSame('excel', $options->dataformat);
        $this->assertTrue($options->choicecodes);
        $this->assertTrue($options->choicetext);
        $this->assertFalse($options->showincompletes);
        $this->assertTrue($options->rankaverages);
        $this->assertTrue($options->emailreport);
        $this->assertTrue($options->emailroles);
        $this->assertSame('a@example.com,b@example.com', $options->emailextra);
    }
}
