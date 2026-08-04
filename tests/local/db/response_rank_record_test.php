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
 * Unit tests for mod_questionnaire\local\db\response_rank_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

/**
 * Unit tests for mod_questionnaire\local\db\response_rank_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\response_rank_record
 */
final class response_rank_record_test extends \advanced_testcase {
    /**
     * Insert a rank row via $DB and return its id.
     *
     * @param int $responseid
     * @param int $questionid
     * @param int $choiceid
     * @param int $rankvalue
     * @return int
     */
    private function make_rank(int $responseid, int $questionid, int $choiceid, int $rankvalue): int {
        global $DB;
        return $DB->insert_record('questionnaire_response_rank', (object)[
            'responseid' => $responseid,
            'questionid' => $questionid,
            'choiceid' => $choiceid,
            'rankvalue' => $rankvalue,
        ]);
    }

    /**
     * get_for_response() returns every rank row for the response.
     */
    public function test_get_for_response(): void {
        $this->resetAfterTest();
        $rid = 501;
        $this->make_rank($rid, 1, 10, 2);
        $this->make_rank($rid, 1, 11, 3);
        $this->make_rank(502, 1, 10, 1); // Different response.

        $this->assertCount(2, response_rank_record::get_for_response($rid));
    }

    /**
     * get_for_question() returns every rank row for the question.
     */
    public function test_get_for_question(): void {
        $this->resetAfterTest();
        $qid = 600;
        $this->make_rank(1, $qid, 1, 0);
        $this->make_rank(2, $qid, 1, 1);
        $this->make_rank(1, 601, 1, 0); // Different question.

        $this->assertCount(2, response_rank_record::get_for_question($qid));
        $this->assertCount(0, response_rank_record::get_for_question(99999));
    }

    /**
     * increment_rankvalues() bumps every non-negative rankvalue by one across all rows.
     */
    public function test_increment_rankvalues_all_rows(): void {
        global $DB;
        $this->resetAfterTest();

        $a = $this->make_rank(1, 1, 1, 0);
        $b = $this->make_rank(1, 1, 2, 3);
        $c = $this->make_rank(1, 1, 3, -1); // Sentinel; should not increment.

        $this->assertTrue(response_rank_record::increment_rankvalues());

        $this->assertSame(1, (int) $DB->get_field('questionnaire_response_rank', 'rankvalue', ['id' => $a]));
        $this->assertSame(4, (int) $DB->get_field('questionnaire_response_rank', 'rankvalue', ['id' => $b]));
        $this->assertSame(-1, (int) $DB->get_field('questionnaire_response_rank', 'rankvalue', ['id' => $c]));
    }

    /**
     * increment_rankvalues() with a question-id filter only touches matching rows.
     */
    public function test_increment_rankvalues_filtered_by_question(): void {
        global $DB;
        $this->resetAfterTest();

        $target = 700;
        $other = 701;
        $a = $this->make_rank(1, $target, 1, 2);
        $b = $this->make_rank(2, $other, 1, 2);

        $this->assertTrue(response_rank_record::increment_rankvalues([$target]));

        $this->assertSame(3, (int) $DB->get_field('questionnaire_response_rank', 'rankvalue', ['id' => $a]));
        $this->assertSame(2, (int) $DB->get_field('questionnaire_response_rank', 'rankvalue', ['id' => $b]));
    }

    /**
     * increment_rankvalues() with an empty filter is a no-op and returns true.
     */
    public function test_increment_rankvalues_empty_filter_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->make_rank(1, 1, 1, 5);

        $this->assertTrue(response_rank_record::increment_rankvalues([]));

        $this->assertSame(5, (int) $DB->get_field('questionnaire_response_rank', 'rankvalue', ['id' => $a]));
    }
}
