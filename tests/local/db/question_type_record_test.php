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
 * Unit tests for mod_questionnaire\local\db\question_type_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

use mod_questionnaire\local\question_type;

/**
 * Unit tests for mod_questionnaire\local\db\question_type_record.
 *
 * The questionnaire_question_type table is populated at install time,
 * so the test relies on the pre-seeded entries rather than inserting new ones.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\question_type_record
 */
final class question_type_record_test extends \advanced_testcase {
    /**
     * get_by_typeid() returns the installed record for a known typeid.
     */
    public function test_get_by_typeid_returns_known_type(): void {
        $this->resetAfterTest();
        $record = question_type_record::get_by_typeid(question_type::QUESTEXT);

        $this->assertNotFalse($record);
        $this->assertSame(question_type::QUESTEXT, (int) $record->get('typeid'));
    }

    /**
     * get_by_typeid() returns false for an unknown typeid.
     */
    public function test_get_by_typeid_returns_false_for_unknown(): void {
        $this->resetAfterTest();
        $this->assertFalse(question_type_record::get_by_typeid(9999));
    }

    /**
     * from_typeid() returns the installed record for a known typeid.
     */
    public function test_from_typeid_returns_known_type(): void {
        $this->resetAfterTest();
        $record = question_type_record::from_typeid(question_type::QUESYESNO);

        $this->assertNotNull($record);
        $this->assertSame(question_type::QUESYESNO, (int) $record->get('typeid'));
    }

    /**
     * from_typeid() returns null for an unknown typeid.
     */
    public function test_from_typeid_returns_null_for_unknown(): void {
        $this->resetAfterTest();
        $this->assertNull(question_type_record::from_typeid(9999));
    }
}
