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
 * Unit tests for mod_questionnaire\local\db\dependency_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for mod_questionnaire\local\db\dependency_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\dependency_record
 */
final class dependency_record_test extends \advanced_testcase {
    /**
     * Insert a dependency row via $DB and return its id.
     *
     * @param int $surveyid
     * @param int $questionid
     * @param int $dependquestionid
     * @return int
     */
    private function make_dep(int $surveyid, int $questionid, int $dependquestionid): int {
        global $DB;
        return $DB->insert_record('questionnaire_dependency', (object)[
            'questionid' => $questionid,
            'surveyid' => $surveyid,
            'dependquestionid' => $dependquestionid,
            'dependchoiceid' => 0,
            'dependlogic' => 1,
            'dependandor' => 'and',
        ]);
    }

    /**
     * get_for_question() returns every dependency rooted at the given question.
     */
    public function test_get_for_question(): void {
        $this->resetAfterTest();
        $sid = 80;
        $qid = 81;
        $this->make_dep($sid, $qid, 90);
        $this->make_dep($sid, $qid, 91);
        $this->make_dep($sid, 999, 90); // Unrelated question.

        $records = dependency_record::get_for_question($qid);
        $this->assertCount(2, $records);
    }

    /**
     * get_for_survey() returns every dependency belonging to the survey.
     */
    public function test_get_for_survey(): void {
        $this->resetAfterTest();
        $sid = 82;
        $othersid = 83;
        $this->make_dep($sid, 100, 200);
        $this->make_dep($sid, 101, 200);
        $this->make_dep($othersid, 110, 210);

        $records = dependency_record::get_for_survey($sid);
        $this->assertCount(2, $records);
    }

    /**
     * delete_for_survey() removes only the survey's dependencies.
     */
    public function test_delete_for_survey_scoped(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 84;
        $othersid = 85;
        $this->make_dep($sid, 100, 200);
        $this->make_dep($sid, 101, 201);
        $keep = $this->make_dep($othersid, 110, 210);

        $this->assertTrue(dependency_record::delete_for_survey($sid));

        $this->assertEquals(0, $DB->count_records('questionnaire_dependency', ['surveyid' => $sid]));
        $this->assertTrue($DB->record_exists('questionnaire_dependency', ['id' => $keep]));
    }

    /**
     * delete_for_question() removes dependencies on both sides of the relationship —
     * those where the question depends on others, and those where others depend on it.
     */
    public function test_delete_for_question_removes_both_sides(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 86;
        $qid = 300;
        $other = 301;

        // qid depends on other.
        $this->make_dep($sid, $qid, $other);
        // other depends on qid (qid is the depend target).
        $this->make_dep($sid, $other, $qid);
        // unrelated.
        $kept = $this->make_dep($sid, 999, 998);

        $this->assertTrue(dependency_record::delete_for_question($qid));

        $this->assertEquals(0, $DB->count_records('questionnaire_dependency', ['questionid' => $qid]));
        $this->assertEquals(0, $DB->count_records('questionnaire_dependency', ['dependquestionid' => $qid]));
        $this->assertTrue($DB->record_exists('questionnaire_dependency', ['id' => $kept]));
    }
}
