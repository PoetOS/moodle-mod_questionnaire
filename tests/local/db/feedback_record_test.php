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

    /**
     * get_records_for_section() returns stdClass rows scoped to the section
     * (matches the legacy $DB->get_records shape used by the scoreboard math).
     */
    public function test_get_records_for_section_returns_stdclass(): void {
        $this->resetAfterTest();
        $target = 906;
        $this->make_feedback($target, 0, 50);
        $this->make_feedback($target, 50, 100);
        $this->make_feedback(907, 0, 100);

        $rows = feedback_record::get_records_for_section($target);

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertInstanceOf(\stdClass::class, $row);
            $this->assertSame($target, (int) $row->sectionid);
        }
    }

    /**
     * find_for_score() returns the band whose minscore ≤ score < maxscore.
     */
    public function test_find_for_score_returns_matching_band(): void {
        $this->resetAfterTest();
        $sid = 908;
        $low  = $this->make_feedback($sid, 0, 33);
        $mid  = $this->make_feedback($sid, 33, 66);
        $high = $this->make_feedback($sid, 66, 100);

        $match = feedback_record::find_for_score($sid, 50.0);
        $this->assertNotNull($match);
        $this->assertSame($mid, (int) $match->id);

        // Boundary: minscore is inclusive, maxscore is exclusive.
        $atboundary = feedback_record::find_for_score($sid, 33.0);
        $this->assertSame($mid, (int) $atboundary->id);

        // Low-end inclusive.
        $atzero = feedback_record::find_for_score($sid, 0.0);
        $this->assertSame($low, (int) $atzero->id);
        $this->assertNotSame($high, (int) $atzero->id);
    }

    /**
     * find_for_score() returns null when no band covers the score.
     */
    public function test_find_for_score_returns_null_when_no_band(): void {
        $this->resetAfterTest();
        $sid = 909;
        $this->make_feedback($sid, 0, 50);

        $this->assertNull(feedback_record::find_for_score($sid, 75.0));
    }

    /**
     * find_for_score() honors the optional field list parameter.
     */
    public function test_find_for_score_with_field_subset(): void {
        $this->resetAfterTest();
        $sid = 910;
        $this->make_feedback($sid, 0, 100);

        $row = feedback_record::find_for_score($sid, 50.0, 'id,minscore');

        $this->assertNotNull($row);
        $this->assertObjectHasProperty('id', $row);
        $this->assertObjectHasProperty('minscore', $row);
        $this->assertObjectNotHasProperty('feedbacktext', $row);
    }
}
