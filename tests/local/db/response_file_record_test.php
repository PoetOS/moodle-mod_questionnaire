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
 * Unit tests for mod_questionnaire\local\db\response_file_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

/**
 * Unit tests for mod_questionnaire\local\db\response_file_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\response_file_record
 */
final class response_file_record_test extends \advanced_testcase {
    /**
     * get_for_response() returns every file answer for the response.
     */
    public function test_get_for_response(): void {
        global $DB;
        $this->resetAfterTest();
        $rid = 13001;
        $DB->insert_record('questionnaire_response_file', (object)[
            'responseid' => $rid, 'questionid' => 1, 'fileid' => 100,
        ]);
        $DB->insert_record('questionnaire_response_file', (object)[
            'responseid' => $rid, 'questionid' => 1, 'fileid' => 101,
        ]);
        $DB->insert_record('questionnaire_response_file', (object)[
            'responseid' => 13002, 'questionid' => 1, 'fileid' => 102,
        ]);

        $this->assertCount(2, response_file_record::get_for_response($rid));
        $this->assertCount(0, response_file_record::get_for_response(99999));
    }
}
