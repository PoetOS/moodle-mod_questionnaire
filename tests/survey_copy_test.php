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
require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

/**
 * Tests which surveys can be copied into a course from the "Add questionnaire" form.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class survey_copy_test extends \basic_testcase {
    /**
     * Surveys of each type, in the course being added to and in another course.
     *
     * @return array
     */
    public static function survey_provider(): array {
        return [
            'template from another course' => ['template', 2, true],
            'public from another course' => ['public', 2, true],
            'private from another course' => ['private', 2, false],
            'private from the same course' => ['private', 1, true],
            'template from the same course' => ['template', 1, true],
        ];
    }

    /**
     * Test survey_can_be_copied_into.
     *
     * @dataProvider survey_provider
     * @covers \questionnaire::survey_can_be_copied_into
     * @param string $realm The survey type.
     * @param int $surveycourseid The course that owns the survey.
     * @param bool $expected
     */
    public function test_survey_can_be_copied_into(string $realm, int $surveycourseid, bool $expected): void {
        $survey = (object)['realm' => $realm, 'courseid' => $surveycourseid];
        $this->assertSame($expected, \questionnaire::survey_can_be_copied_into($survey, 1));
    }
}
