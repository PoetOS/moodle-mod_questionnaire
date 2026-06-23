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
 * Unit tests for mod_questionnaire\local\db\feedback_section_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

/**
 * Unit tests for mod_questionnaire\local\db\feedback_section_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\feedback_section_record
 */
final class feedback_section_record_test extends \advanced_testcase {
    /**
     * Insert a feedback-section row directly via $DB and return its id.
     *
     * @param int $surveyid
     * @param int $sectionnum
     * @return int
     */
    private function make_section(int $surveyid, int $sectionnum): int {
        global $DB;
        return $DB->insert_record('questionnaire_fb_sections', (object)[
            'surveyid' => $surveyid,
            'section' => $sectionnum,
            'scorecalculation' => '',
            'sectionlabel' => 'L' . $sectionnum,
            'sectionheading' => '',
            'sectionheadingformat' => FORMAT_HTML,
        ]);
    }

    /**
     * get_for_survey() returns every feedback section for the survey.
     */
    public function test_get_for_survey(): void {
        $this->resetAfterTest();
        $sid = 401;
        $this->make_section($sid, 1);
        $this->make_section($sid, 2);
        $this->make_section(402, 1); // Different survey.

        $this->assertCount(2, feedback_section_record::get_for_survey($sid));
        $this->assertCount(0, feedback_section_record::get_for_survey(999));
    }

    /**
     * get_for_survey() honors the sort + order arguments.
     */
    public function test_get_for_survey_sorted(): void {
        $this->resetAfterTest();
        $sid = 403;
        $third = $this->make_section($sid, 3);
        $first = $this->make_section($sid, 1);
        $second = $this->make_section($sid, 2);

        $sorted = array_values(feedback_section_record::get_for_survey($sid, 'section'));
        $this->assertCount(3, $sorted);
        $this->assertSame($first, $sorted[0]->get('id'));
        $this->assertSame($second, $sorted[1]->get('id'));
        $this->assertSame($third, $sorted[2]->get('id'));
    }

    /**
     * max_section_for_survey() returns the highest section number, or 0 if empty.
     */
    public function test_max_section_for_survey(): void {
        $this->resetAfterTest();
        $sid = 404;

        $this->assertSame(0, feedback_section_record::max_section_for_survey($sid));

        $this->make_section($sid, 1);
        $this->make_section($sid, 5);
        $this->make_section($sid, 3);

        $this->assertSame(5, feedback_section_record::max_section_for_survey($sid));
        // Other surveys are not considered.
        $this->assertSame(0, feedback_section_record::max_section_for_survey(999));
    }

    /**
     * delete_for_survey() removes only the survey's section rows.
     */
    public function test_delete_for_survey_scoped(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 405;
        $other = 406;
        $this->make_section($sid, 1);
        $this->make_section($sid, 2);
        $keep = $this->make_section($other, 1);

        $this->assertTrue(feedback_section_record::delete_for_survey($sid));

        $this->assertEquals(0, $DB->count_records('questionnaire_fb_sections', ['surveyid' => $sid]));
        $this->assertTrue($DB->record_exists('questionnaire_fb_sections', ['id' => $keep]));
    }

    /**
     * min_section_for_survey() returns the lowest section number, or 0 if empty.
     */
    public function test_min_section_for_survey(): void {
        $this->resetAfterTest();
        $sid = 407;

        $this->assertSame(0, feedback_section_record::min_section_for_survey($sid));

        $this->make_section($sid, 4);
        $this->make_section($sid, 2);
        $this->make_section($sid, 7);

        $this->assertSame(2, feedback_section_record::min_section_for_survey($sid));
        $this->assertSame(0, feedback_section_record::min_section_for_survey(999));
    }

    /**
     * get_numbered_section_records_for_survey() returns stdClass rows whose section
     * number is not null, scoped to the survey.
     */
    public function test_get_numbered_section_records_for_survey_filters_nulls(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 408;
        $this->make_section($sid, 1);
        $this->make_section($sid, 2);
        // Insert a section with null section number — should be excluded.
        $DB->insert_record('questionnaire_fb_sections', (object)[
            'surveyid' => $sid,
            'section' => null,
            'scorecalculation' => '',
            'sectionlabel' => 'orphan',
            'sectionheading' => '',
            'sectionheadingformat' => FORMAT_HTML,
        ]);
        // And one for a different survey, which should not appear.
        $this->make_section(409, 1);

        $rows = feedback_section_record::get_numbered_section_records_for_survey($sid);

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertInstanceOf(\stdClass::class, $row);
            $this->assertNotNull($row->section);
        }
    }
}
