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
 * Unit tests for mod_questionnaire\local\db\choice_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

/**
 * Unit tests for mod_questionnaire\local\db\choice_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\choice_record
 */
final class choice_record_test extends \advanced_testcase {
    /**
     * Insert a choice row directly via $DB and return the new id.
     *
     * @param int $questionid
     * @param string $content
     * @return int
     */
    private function make_choice(int $questionid, string $content = 'option'): int {
        global $DB;
        return $DB->insert_record('questionnaire_quest_choice', (object)[
            'questionid' => $questionid,
            'content' => $content,
            'value' => null,
        ]);
    }

    /**
     * get_for_question() returns every choice for a question.
     */
    public function test_get_for_question_returns_choices(): void {
        $this->resetAfterTest();
        $qid = 7001;
        $c1 = $this->make_choice($qid, 'a');
        $c2 = $this->make_choice($qid, 'b');

        $records = choice_record::get_for_question($qid);
        $this->assertCount(2, $records);
        $ids = array_map(fn($r) => $r->get('id'), $records);
        $this->assertContains($c1, $ids);
        $this->assertContains($c2, $ids);
    }

    /**
     * get_for_question() returns an empty array when the question has no choices.
     */
    public function test_get_for_question_empty(): void {
        $this->resetAfterTest();
        $this->assertSame([], choice_record::get_for_question(99999));
    }

    /**
     * delete_for_question() removes only rows belonging to the given question.
     */
    public function test_delete_for_question_scopes_to_question(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 7002;
        $otherqid = 7003;
        $this->make_choice($qid);
        $this->make_choice($qid);
        $this->make_choice($otherqid);

        $this->assertTrue(choice_record::delete_for_question($qid));

        $this->assertEquals(0, $DB->count_records('questionnaire_quest_choice', ['questionid' => $qid]));
        $this->assertEquals(1, $DB->count_records('questionnaire_quest_choice', ['questionid' => $otherqid]));
    }

    /**
     * delete_for_question() is a no-op when there are no choices for the question.
     */
    public function test_delete_for_question_no_op_when_empty(): void {
        $this->resetAfterTest();
        $this->assertTrue(choice_record::delete_for_question(99999));
    }
}
