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
 * Unit tests for mod_questionnaire\local\db\question_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

use mod_questionnaire\local\question_type;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for mod_questionnaire\local\db\question_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\question_record
 */
final class question_record_test extends \advanced_testcase {
    /**
     * Insert a question row via $DB and return its id.
     *
     * @param int $surveyid
     * @param int $position
     * @param int $typeid
     * @param int|null $deleted
     * @return int
     */
    private function make_question(int $surveyid, int $position, int $typeid = 1, ?int $deleted = null): int {
        global $DB;
        return $DB->insert_record('questionnaire_question', (object)[
            'surveyid' => $surveyid,
            'name' => 'Q' . $position,
            'typeid' => $typeid,
            'length' => 0,
            'precise' => 0,
            'position' => $position,
            'content' => 'content ' . $position,
            'required' => 'n',
            'deleted' => $deleted,
        ]);
    }

    /**
     * get_active_for_survey() returns only non-deleted questions, ordered by position, keyed by id.
     */
    public function test_get_active_for_survey(): void {
        $this->resetAfterTest();
        $sid = 8001;
        $third = $this->make_question($sid, 3);
        $first = $this->make_question($sid, 1);
        $second = $this->make_question($sid, 2);
        $this->make_question($sid, 4, 1, time()); // Soft-deleted — excluded.

        $records = question_record::get_active_for_survey($sid);
        $this->assertCount(3, $records);

        $ordered = array_values($records);
        $this->assertSame($first, $ordered[0]->get('id'));
        $this->assertSame($second, $ordered[1]->get('id'));
        $this->assertSame($third, $ordered[2]->get('id'));
    }

    /**
     * get_for_survey() returns every question for the survey, including soft-deleted ones.
     */
    public function test_get_for_survey_includes_deleted(): void {
        $this->resetAfterTest();
        $sid = 8002;
        $this->make_question($sid, 1);
        $this->make_question($sid, 2, 1, time()); // Soft-deleted.
        $this->make_question(8003, 1);            // Different survey.

        $this->assertCount(2, question_record::get_for_survey($sid));
        $this->assertCount(0, question_record::get_for_survey(99999));
    }

    /**
     * get_for_type() returns questions of the given type, optionally filtered by survey.
     */
    public function test_get_for_type(): void {
        $this->resetAfterTest();
        $sida = 8004;
        $sidb = 8005;
        $this->make_question($sida, 1, question_type::QUESRATE);
        $this->make_question($sida, 2, question_type::QUESRATE);
        $this->make_question($sida, 3, question_type::QUESTEXT);
        $this->make_question($sidb, 1, question_type::QUESRATE);

        $this->assertCount(3, question_record::get_for_type(question_type::QUESRATE));
        $this->assertCount(2, question_record::get_for_type(question_type::QUESRATE, $sida));
        $this->assertCount(1, question_record::get_for_type(question_type::QUESRATE, $sidb));
        $this->assertCount(0, question_record::get_for_type(question_type::QUESYESNO));
    }

    /**
     * get_deleted_for_survey() returns soft-deleted questions, excluding pagebreaks,
     * ordered by deletion time DESC.
     */
    public function test_get_deleted_for_survey(): void {
        $this->resetAfterTest();
        $sid = 8006;
        $this->make_question($sid, 1);                                      // Active — excluded.
        $older = $this->make_question($sid, 2, 1, time() - 3600);
        $newer = $this->make_question($sid, 3, 1, time());
        $this->make_question($sid, 4, question_type::QUESPAGEBREAK, time()); // Pagebreak — excluded.

        $records = question_record::get_deleted_for_survey($sid);
        $this->assertCount(2, $records);
        $ordered = array_values($records);
        $this->assertSame($newer, $ordered[0]->get('id'));
        $this->assertSame($older, $ordered[1]->get('id'));
    }

    /**
     * get_soft_deleted() returns the row only when it's actually soft-deleted, otherwise null.
     */
    public function test_get_soft_deleted(): void {
        $this->resetAfterTest();
        $sid = 8007;
        $active = $this->make_question($sid, 1);
        $deleted = $this->make_question($sid, 2, 1, time());

        $this->assertNull(question_record::get_soft_deleted($active, $sid));
        $this->assertNull(question_record::get_soft_deleted($deleted, 99999)); // Wrong survey.

        $found = question_record::get_soft_deleted($deleted, $sid);
        $this->assertNotNull($found);
        $this->assertSame($deleted, $found->get('id'));
    }

    /**
     * max_active_position_for_survey() returns the highest position of active questions, or 0.
     */
    public function test_max_active_position_for_survey(): void {
        $this->resetAfterTest();
        $sid = 8008;

        $this->assertSame(0, question_record::max_active_position_for_survey($sid));

        $this->make_question($sid, 1);
        $this->make_question($sid, 5);
        $this->make_question($sid, 3);
        $this->make_question($sid, 9, 1, time()); // Soft-deleted — excluded.

        $this->assertSame(5, question_record::max_active_position_for_survey($sid));
        $this->assertSame(0, question_record::max_active_position_for_survey(99999));
    }

    /**
     * update_position() sets the position field on the specified row.
     */
    public function test_update_position(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 8009;
        $qid = $this->make_question($sid, 1);

        $this->assertTrue(question_record::update_position($qid, 7));

        $this->assertSame(7, (int) $DB->get_field('questionnaire_question', 'position', ['id' => $qid]));
    }

    /**
     * soft_delete() stamps the deleted field with a current timestamp.
     */
    public function test_soft_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 8010;
        $qid = $this->make_question($sid, 1);

        $before = time();
        $this->assertTrue(question_record::soft_delete($qid));
        $deleted = (int) $DB->get_field('questionnaire_question', 'deleted', ['id' => $qid]);

        $this->assertGreaterThanOrEqual($before, $deleted);
    }

    /**
     * get_active_after_position() returns active questions with greater positions, ordered ascending.
     */
    public function test_get_active_after_position(): void {
        $this->resetAfterTest();
        $sid = 8011;
        $p1 = $this->make_question($sid, 1);
        $p2 = $this->make_question($sid, 2);
        $p3 = $this->make_question($sid, 3);
        $this->make_question($sid, 4, 1, time()); // Soft-deleted — excluded.

        $records = array_values(question_record::get_active_after_position($sid, 1));
        $this->assertCount(2, $records);
        $this->assertSame($p2, $records[0]->get('id'));
        $this->assertSame($p3, $records[1]->get('id'));

        $this->assertCount(0, question_record::get_active_after_position($sid, 10));
    }

    /**
     * create_pagebreak() inserts a pagebreak row at the given position.
     */
    public function test_create_pagebreak(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 8012;
        $rec = question_record::create_pagebreak($sid, 5);

        $this->assertGreaterThan(0, $rec->get('id'));
        $row = $DB->get_record('questionnaire_question', ['id' => $rec->get('id')]);
        $this->assertEquals($sid, $row->surveyid);
        $this->assertEquals(question_type::QUESPAGEBREAK, $row->typeid);
        $this->assertEquals(5, $row->position);
        $this->assertEquals('break', $row->content);
    }

    /**
     * delete_for_survey() removes only the given survey's question rows.
     */
    public function test_delete_for_survey_scoped(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 8013;
        $other = 8014;
        $this->make_question($sid, 1);
        $this->make_question($sid, 2);
        $keep = $this->make_question($other, 1);

        $this->assertTrue(question_record::delete_for_survey($sid));

        $this->assertEquals(0, $DB->count_records('questionnaire_question', ['surveyid' => $sid]));
        $this->assertTrue($DB->record_exists('questionnaire_question', ['id' => $keep]));
    }

    /**
     * delete_soft_deleted() permanently removes a soft-deleted row, but only if it's soft-deleted.
     */
    public function test_delete_soft_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 8015;
        $active = $this->make_question($sid, 1);
        $deleted = $this->make_question($sid, 2, 1, time());

        // Active rows are not removed.
        $this->assertTrue(question_record::delete_soft_deleted($active, $sid));
        $this->assertTrue($DB->record_exists('questionnaire_question', ['id' => $active]));

        // Wrong survey: not removed.
        $this->assertTrue(question_record::delete_soft_deleted($deleted, 99999));
        $this->assertTrue($DB->record_exists('questionnaire_question', ['id' => $deleted]));

        // Correctly scoped soft-deleted row is removed.
        $this->assertTrue(question_record::delete_soft_deleted($deleted, $sid));
        $this->assertFalse($DB->record_exists('questionnaire_question', ['id' => $deleted]));
    }

    /**
     * delete_soft_deleted_pagebreaks_for_survey() removes only soft-deleted pagebreak rows.
     */
    public function test_delete_soft_deleted_pagebreaks_for_survey(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 8016;
        $other = 8017;
        $activepb = $this->make_question($sid, 1, question_type::QUESPAGEBREAK);
        $deletedpb = $this->make_question($sid, 2, question_type::QUESPAGEBREAK, time());
        $deletedquestion = $this->make_question($sid, 3, 1, time()); // Soft-deleted but not pagebreak.
        $otherdeleted = $this->make_question($other, 1, question_type::QUESPAGEBREAK, time());

        $this->assertTrue(question_record::delete_soft_deleted_pagebreaks_for_survey($sid));

        $this->assertTrue($DB->record_exists('questionnaire_question', ['id' => $activepb]));
        $this->assertFalse($DB->record_exists('questionnaire_question', ['id' => $deletedpb]));
        $this->assertTrue($DB->record_exists('questionnaire_question', ['id' => $deletedquestion]));
        $this->assertTrue($DB->record_exists('questionnaire_question', ['id' => $otherdeleted]));
    }
}
