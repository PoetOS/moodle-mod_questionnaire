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
 * Unit tests for mod_questionnaire\local\db\feedback_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

/**
 * Unit tests for mod_questionnaire\local\db\feedback_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\feedback_record
 */
final class feedback_record_test extends \advanced_testcase {
    /**
     * Insert a feedback row directly via $DB and return its id.
     *
     * @param int $sectionid
     * @param float $minscore
     * @param float $maxscore
     * @return int
     */
    private function make_feedback(int $sectionid, float $minscore = 0.0, float $maxscore = 100.0): int {
        global $DB;
        return $DB->insert_record('questionnaire_feedback', (object)[
            'sectionid' => $sectionid,
            'feedbacklabel' => '',
            'feedbacktext' => '',
            'feedbacktextformat' => FORMAT_HTML,
            'minscore' => $minscore,
            'maxscore' => $maxscore,
        ]);
    }

    /**
     * get_for_section() returns every feedback row for the section.
     */
    public function test_get_for_section(): void {
        $this->resetAfterTest();
        $sid = 901;
        $this->make_feedback($sid, 0, 50);
        $this->make_feedback($sid, 50, 100);
        $this->make_feedback(902, 0, 100); // Different section.

        $this->assertCount(2, feedback_record::get_for_section($sid));
        $this->assertCount(0, feedback_record::get_for_section(999));
    }

    /**
     * get_for_section() honors the sort + order arguments.
     */
    public function test_get_for_section_sorted(): void {
        $this->resetAfterTest();
        $sid = 903;
        $low = $this->make_feedback($sid, 0, 50);
        $high = $this->make_feedback($sid, 75, 100);
        $mid = $this->make_feedback($sid, 50, 75);

        $sorted = array_values(feedback_record::get_for_section($sid, 'minscore', 'DESC'));
        $this->assertCount(3, $sorted);
        $this->assertSame($high, $sorted[0]->get('id'));
        $this->assertSame($mid, $sorted[1]->get('id'));
        $this->assertSame($low, $sorted[2]->get('id'));
    }

    /**
     * delete_for_section() removes only feedback rows for the given section.
     */
    public function test_delete_for_section_scoped(): void {
        global $DB;
        $this->resetAfterTest();
        $target = 904;
        $other = 905;
        $this->make_feedback($target);
        $this->make_feedback($target);
        $keep = $this->make_feedback($other);

        $this->assertTrue(feedback_record::delete_for_section($target));

        $this->assertEquals(0, $DB->count_records('questionnaire_feedback', ['sectionid' => $target]));
        $this->assertTrue($DB->record_exists('questionnaire_feedback', ['id' => $keep]));
    }
}
