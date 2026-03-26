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

use advanced_testcase;
use mod_questionnaire\local\db\response_record;

/**
 * Unit tests for mod_questionnaire\response.
 *
 * @package    mod_questionnaire
 * @copyright  2025 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_questionnaire\response
 */
class response_test extends advanced_testcase {

    // -----------------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------------

    /**
     * Insert a raw questionnaire_response row and return its id.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @param string $complete 'y' or 'n'
     * @param int|null $submitted Unix timestamp; defaults to time().
     * @return int
     */
    private function insert_response(
        int $questionnaireid,
        int $userid,
        string $complete = 'n',
        ?int $submitted = null
    ): int {
        global $DB;
        return (int) $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaireid,
            'userid' => $userid,
            'submitted' => $submitted ?? time(),
            'complete' => $complete,
            'grade' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // response::create() tests.
    // -----------------------------------------------------------------------

    /**
     * Test that create() inserts a header row and returns a response with the correct ids.
     */
    public function test_create_inserts_header_row(): void {
        global $DB;
        $this->resetAfterTest();

        $r = response::create(101, 42);

        $this->assertGreaterThan(0, $r->id());
        $this->assertSame(101, $r->questionnaireid());
        $this->assertSame(42, $r->userid());
        $this->assertFalse($r->is_complete());
        $this->assertTrue($DB->record_exists('questionnaire_response', ['id' => $r->id()]));
    }

    /**
     * Test that create() sets submitted to approximately now.
     */
    public function test_create_sets_submitted_to_now(): void {
        $this->resetAfterTest();

        $before = time();
        $r = response::create(101, 42);
        $after = time();

        $this->assertGreaterThanOrEqual($before, $r->submitted_at());
        $this->assertLessThanOrEqual($after, $r->submitted_at());
    }

    // -----------------------------------------------------------------------
    // response::latest_incomplete() tests.
    // -----------------------------------------------------------------------

    /**
     * Test that latest_incomplete() returns null when no incomplete response exists.
     */
    public function test_latest_incomplete_returns_null_when_none(): void {
        $this->resetAfterTest();

        $this->assertNull(response::latest_incomplete(999, 1));
    }

    /**
     * Test that latest_incomplete() ignores complete responses.
     */
    public function test_latest_incomplete_ignores_complete_responses(): void {
        $this->resetAfterTest();

        $this->insert_response(202, 7, 'y');

        $this->assertNull(response::latest_incomplete(202, 7));
    }

    /**
     * Test that latest_incomplete() returns the incomplete response id.
     */
    public function test_latest_incomplete_returns_incomplete_response(): void {
        $this->resetAfterTest();

        $rid = $this->insert_response(303, 8, 'n');

        $r = response::latest_incomplete(303, 8);

        $this->assertNotNull($r);
        $this->assertSame($rid, $r->id());
        $this->assertSame(303, $r->questionnaireid());
        $this->assertSame(8, $r->userid());
    }

    /**
     * Test that latest_incomplete() returns the most recent when multiple exist.
     */
    public function test_latest_incomplete_returns_most_recent(): void {
        $this->resetAfterTest();

        $this->insert_response(404, 9, 'n', time() - 100);
        $newer = $this->insert_response(404, 9, 'n', time() - 10);

        $r = response::latest_incomplete(404, 9);

        $this->assertSame($newer, $r->id());
    }

    /**
     * Test that latest_incomplete() is scoped to the given questionnaire.
     */
    public function test_latest_incomplete_scoped_to_questionnaire(): void {
        $this->resetAfterTest();

        $this->insert_response(501, 10, 'n');

        $this->assertNull(response::latest_incomplete(502, 10));
    }

    // -----------------------------------------------------------------------
    // Accessor tests.
    // -----------------------------------------------------------------------

    /**
     * Test that is_complete() returns false for an incomplete response.
     */
    public function test_is_complete_false_for_incomplete(): void {
        $this->resetAfterTest();

        $rid = $this->insert_response(601, 11, 'n');
        $r = new response(new response_record($rid));

        $this->assertFalse($r->is_complete());
    }

    /**
     * Test that is_complete() returns true for a complete response.
     */
    public function test_is_complete_true_for_complete(): void {
        $this->resetAfterTest();

        $rid = $this->insert_response(601, 11, 'y');
        $r = new response(new response_record($rid));

        $this->assertTrue($r->is_complete());
    }

    // -----------------------------------------------------------------------
    // touch() tests.
    // -----------------------------------------------------------------------

    /**
     * Test that touch() updates the submitted timestamp in the database.
     */
    public function test_touch_updates_submitted_timestamp(): void {
        global $DB;
        $this->resetAfterTest();

        $rid = $this->insert_response(701, 12, 'n', time() - 3600);
        $r = new response(new response_record($rid));

        $before = time();
        $r->touch();
        $after = time();

        $stored = (int) $DB->get_field('questionnaire_response', 'submitted', ['id' => $rid]);
        $this->assertGreaterThanOrEqual($before, $stored);
        $this->assertLessThanOrEqual($after, $stored);
    }

    // -----------------------------------------------------------------------
    // commit() tests.
    // -----------------------------------------------------------------------

    /**
     * Test that commit() marks the response as complete in the database.
     */
    public function test_commit_marks_complete(): void {
        global $DB;
        $this->resetAfterTest();

        $rid = $this->insert_response(801, 13, 'n');
        $r = new response(new response_record($rid));
        $r->commit();

        $row = $DB->get_record('questionnaire_response', ['id' => $rid]);
        $this->assertSame('y', $row->complete);
    }

    /**
     * Test that commit() stores the provided grade.
     */
    public function test_commit_stores_grade(): void {
        global $DB;
        $this->resetAfterTest();

        $rid = $this->insert_response(801, 13, 'n');
        $r = new response(new response_record($rid));
        $r->commit(5);

        $this->assertSame('5', $DB->get_field('questionnaire_response', 'grade', ['id' => $rid]));
    }

    /**
     * Test that commit() updates submitted to approximately now.
     */
    public function test_commit_updates_submitted(): void {
        global $DB;
        $this->resetAfterTest();

        $rid = $this->insert_response(801, 13, 'n', time() - 3600);
        $r = new response(new response_record($rid));

        $before = time();
        $r->commit();
        $after = time();

        $stored = (int) $DB->get_field('questionnaire_response', 'submitted', ['id' => $rid]);
        $this->assertGreaterThanOrEqual($before, $stored);
        $this->assertLessThanOrEqual($after, $stored);
    }

    /**
     * Test that after commit() is_complete() returns true.
     */
    public function test_is_complete_true_after_commit(): void {
        $this->resetAfterTest();

        $rid = $this->insert_response(901, 14, 'n');
        $r = new response(new response_record($rid));
        $r->commit();

        $this->assertTrue($r->is_complete());
    }
}
