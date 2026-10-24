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
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

/**
 * Unit tests for questionnaire::user_time_for_new_attempt().
 *
 * @package    mod_questionnaire
 * @copyright  2026 taki-0105a
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 * @covers     \questionnaire::user_time_for_new_attempt
 */
final class user_time_for_new_attempt_test extends \advanced_testcase {
    /**
     * Data provider for test_weekly().
     *
     * @return array
     */
    public static function weekly_provider(): array {
        return [
            'Same ISO week, mid year' => ['2027-06-01 12:00:00', '2027-06-04 12:00:00', false],
            'Next ISO week, mid year' => ['2027-06-01 12:00:00', '2027-06-07 12:00:00', true],
            '1 Jan in ISO week 53 of previous year, same week' => ['2027-01-01 12:00:00', '2027-01-03 12:00:00', false],
            '1 Jan in ISO week 53 of previous year, next week' => ['2027-01-01 12:00:00', '2027-01-04 12:00:00', true],
            '1 Jan in ISO week 53 of previous year, later in year' => ['2027-01-01 12:00:00', '2027-06-01 12:00:00', true],
            '1 Jan in ISO week 52 of previous year, next week' => ['2022-01-01 12:00:00', '2022-01-03 12:00:00', true],
            'ISO week 53 spanning new year, same week' => ['2026-12-29 12:00:00', '2027-01-01 12:00:00', false],
            'ISO week 1 starting in December, same week' => ['2025-12-29 12:00:00', '2026-01-02 12:00:00', false],
            'ISO week 1 starting in December, next week' => ['2025-12-29 12:00:00', '2026-01-05 12:00:00', true],
        ];
    }

    /**
     * Test the "respond weekly" type around the turn of the year.
     *
     * @dataProvider weekly_provider
     * @param string $submitted Date and time (UTC) of the previous submission.
     * @param string $now Date and time (UTC) of the new attempt.
     * @param bool $expected Whether a new attempt is allowed.
     */
    public function test_weekly(string $submitted, string $now, bool $expected): void {
        global $DB;

        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');

        $utc = new \DateTimeZone('UTC');
        $submittedtime = (new \DateTimeImmutable($submitted, $utc))->getTimestamp();
        $nowtime = (new \DateTimeImmutable($now, $utc))->getTimestamp();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_questionnaire_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id, 'qtype' => QUESTIONNAIREWEEKLY]);

        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id,
            'userid' => $user->id,
            'submitted' => $submittedtime,
            'complete' => 'y',
        ]);

        $this->mock_clock_with_frozen($nowtime);

        $this->assertSame($expected, $questionnaire->user_time_for_new_attempt($user->id));
    }
}
