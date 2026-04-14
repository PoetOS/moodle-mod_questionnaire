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
 * Unit tests for mod_questionnaire\local\question_type.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\question_type;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

/**
 * Unit tests for mod_questionnaire\local\question_type.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question_type
 */
final class question_type_test extends \advanced_testcase {
    // Tests for qtypename().

    /**
     * Asserts qtypename() returns the correct short name for the yes/no type.
     *
     * @covers \mod_questionnaire\local\question_type::qtypename
     */
    public function test_qtypename_returns_yesno_for_type_1(): void {
        $this->assertEquals('yesno', question_type::qtypename(question_type::QUESYESNO));
    }

    /**
     * Asserts qtypename() returns the correct short name for the essay type.
     *
     * @covers \mod_questionnaire\local\question_type::qtypename
     */
    public function test_qtypename_returns_essay_for_essay_type(): void {
        $this->assertEquals('essay', question_type::qtypename(question_type::QUESESSAY));
    }

    /**
     * Asserts qtypename() returns an empty string for an unknown type id.
     *
     * @covers \mod_questionnaire\local\question_type::qtypename
     */
    public function test_qtypename_returns_empty_string_for_unknown_type(): void {
        $this->assertSame('', question_type::qtypename(999));
    }

    /**
     * Asserts qtypename() returns an empty string for QUESCHOOSE which is a UI placeholder.
     *
     * @covers \mod_questionnaire\local\question_type::qtypename
     */
    public function test_qtypename_returns_empty_string_for_choose_type(): void {
        $this->assertSame('', question_type::qtypename(question_type::QUESCHOOSE));
    }

    // Tests for qtypenames().

    /**
     * Asserts qtypenames() returns an array.
     *
     * @covers \mod_questionnaire\local\question_type::qtypenames
     */
    public function test_qtypenames_returns_array(): void {
        $this->assertIsArray(question_type::qtypenames());
    }

    /**
     * Asserts qtypenames() contains entries for all standard question types.
     *
     * @covers \mod_questionnaire\local\question_type::qtypenames
     */
    public function test_qtypenames_contains_all_standard_types(): void {
        $names = question_type::qtypenames();
        $this->assertArrayHasKey(question_type::QUESYESNO, $names);
        $this->assertArrayHasKey(question_type::QUESTEXT, $names);
        $this->assertArrayHasKey(question_type::QUESESSAY, $names);
        $this->assertArrayHasKey(question_type::QUESRADIO, $names);
        $this->assertArrayHasKey(question_type::QUESCHECK, $names);
        $this->assertArrayHasKey(question_type::QUESDROP, $names);
        $this->assertArrayHasKey(question_type::QUESRATE, $names);
        $this->assertArrayHasKey(question_type::QUESDATE, $names);
        $this->assertArrayHasKey(question_type::QUESNUMERIC, $names);
        $this->assertArrayHasKey(question_type::QUESSLIDER, $names);
        $this->assertArrayHasKey(question_type::QUESFILE, $names);
        $this->assertArrayHasKey(question_type::QUESPAGEBREAK, $names);
        $this->assertArrayHasKey(question_type::QUESSECTIONTEXT, $names);
    }

    /**
     * Asserts qtypenames() does not include QUESCHOOSE which is a UI placeholder with no stored type.
     *
     * @covers \mod_questionnaire\local\question_type::qtypenames
     */
    public function test_qtypenames_does_not_include_choose_type(): void {
        $this->assertArrayNotHasKey(question_type::QUESCHOOSE, question_type::qtypenames());
    }

    // Tests for display_name().

    /**
     * Asserts display_name() returns a non-empty string for every known question type.
     *
     * @covers \mod_questionnaire\local\question_type::display_name
     */
    public function test_display_name_returns_non_empty_string_for_each_known_type(): void {
        $this->resetAfterTest();
        $types = [
            question_type::QUESYESNO,
            question_type::QUESTEXT,
            question_type::QUESESSAY,
            question_type::QUESRADIO,
            question_type::QUESCHECK,
            question_type::QUESDROP,
            question_type::QUESRATE,
            question_type::QUESDATE,
            question_type::QUESNUMERIC,
            question_type::QUESSLIDER,
            question_type::QUESFILE,
            question_type::QUESSECTIONTEXT,
            question_type::QUESPAGEBREAK,
        ];
        foreach ($types as $typeid) {
            $name = question_type::display_name($typeid);
            $this->assertNotEmpty($name, "display_name($typeid) should not be empty");
            $this->assertIsString($name);
        }
    }

    /**
     * Asserts display_name() returns an empty string for an unknown type id.
     *
     * @covers \mod_questionnaire\local\question_type::display_name
     */
    public function test_display_name_returns_empty_string_for_unknown_type(): void {
        $this->assertSame('', question_type::display_name(999));
    }

    /**
     * Asserts display_name() returns an empty string for QUESCHOOSE which has no display name.
     *
     * @covers \mod_questionnaire\local\question_type::display_name
     */
    public function test_display_name_returns_empty_string_for_choose_type(): void {
        $this->assertSame('', question_type::display_name(question_type::QUESCHOOSE));
    }

    // Tests for from_typeid().

    /**
     * Asserts from_typeid() returns a question_type instance for a known type.
     *
     * @covers \mod_questionnaire\local\question_type::from_typeid
     */
    public function test_from_typeid_returns_question_type_for_yesno(): void {
        $this->resetAfterTest();
        $qt = question_type::from_typeid(question_type::QUESYESNO);
        $this->assertNotNull($qt);
        $this->assertInstanceOf(question_type::class, $qt);
    }

    /**
     * Asserts from_typeid() returns null when the type id does not exist in the database.
     *
     * @covers \mod_questionnaire\local\question_type::from_typeid
     */
    public function test_from_typeid_returns_null_for_invalid_typeid(): void {
        $this->resetAfterTest();
        $this->assertNull(question_type::from_typeid(999));
    }

    /**
     * Asserts from_typeid() returns the same instance on repeated calls due to per-request caching.
     *
     * @covers \mod_questionnaire\local\question_type::from_typeid
     */
    public function test_from_typeid_returns_same_instance_on_repeat_call(): void {
        $this->resetAfterTest();
        $first = question_type::from_typeid(question_type::QUESTEXT);
        $second = question_type::from_typeid(question_type::QUESTEXT);
        $this->assertSame($first, $second);
    }
}
