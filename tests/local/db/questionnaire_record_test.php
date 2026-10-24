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
 * Unit tests for mod_questionnaire\local\db\questionnaire_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

/**
 * Unit tests for mod_questionnaire\local\db\questionnaire_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\questionnaire_record
 */
final class questionnaire_record_test extends \advanced_testcase {
    /**
     * Insert a questionnaire row via $DB and return its id.
     *
     * @param int $courseid
     * @param int $sid
     * @param string $name
     * @return int
     */
    private function make_questionnaire(int $courseid, int $sid, string $name = 'Q'): int {
        global $DB;
        return $DB->insert_record('questionnaire', (object)[
            'course' => $courseid,
            'name' => $name,
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'qtype' => 0,
            'respondenttype' => 'fullname',
            'respeligible' => 'all',
            'respview' => 0,
            'sid' => $sid,
            'timemodified' => time(),
        ]);
    }

    /**
     * get_for_survey() returns the first questionnaire pointing at the survey, or null.
     */
    public function test_get_for_survey(): void {
        $this->resetAfterTest();
        $sid = 7501;
        $this->assertNull(questionnaire_record::get_for_survey($sid));

        $qid = $this->make_questionnaire(1, $sid);
        $found = questionnaire_record::get_for_survey($sid);
        $this->assertNotNull($found);
        $this->assertSame($qid, $found->get('id'));
    }

    /**
     * get_for_survey_in_course() finds the questionnaire whose course owns the survey.
     */
    public function test_get_for_survey_in_course(): void {
        $this->resetAfterTest();
        $sid = 7502;
        $course1 = 11;
        $course2 = 22;

        $q1 = $this->make_questionnaire($course1, $sid, 'Original');
        $q2 = $this->make_questionnaire($course2, $sid, 'Copy');

        $found = questionnaire_record::get_for_survey_in_course($sid, $course1);
        $this->assertNotNull($found);
        $this->assertSame($q1, $found->get('id'));

        $found2 = questionnaire_record::get_for_survey_in_course($sid, $course2);
        $this->assertSame($q2, $found2->get('id'));

        $this->assertNull(questionnaire_record::get_for_survey_in_course($sid, 999));
    }

    /**
     * create_from_formdata() creates a new questionnaire row from a stdClass.
     */
    public function test_create_from_formdata(): void {
        global $DB;
        $this->resetAfterTest();

        $formdata = (object)[
            'course' => 5,
            'name' => 'Form created',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'qtype' => 0,
            'respondenttype' => 'fullname',
            'respeligible' => 'all',
            'respview' => 0,
            'sid' => 123,
            'timemodified' => time(),
        ];
        $record = questionnaire_record::create_from_formdata($formdata);

        $this->assertGreaterThan(0, $record->get('id'));
        $this->assertTrue($DB->record_exists('questionnaire', ['id' => $record->get('id')]));
        $this->assertSame('Form created', $record->get('name'));
    }
}
