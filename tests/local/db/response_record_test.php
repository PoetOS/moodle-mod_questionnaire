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
 * Unit tests for mod_questionnaire\local\db\response_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

/**
 * Unit tests for mod_questionnaire\local\db\response_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\response_record
 */
final class response_record_test extends \advanced_testcase {
    /**
     * Insert a questionnaire_response row via $DB and return its id.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @param string $complete 'y' or 'n'.
     * @param int|null $submitted
     * @return int
     */
    private function make_response(int $questionnaireid, int $userid, string $complete = 'y', ?int $submitted = null): int {
        global $DB;
        return $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaireid,
            'userid' => $userid,
            'submitted' => $submitted ?? time(),
            'complete' => $complete,
            'grade' => 0,
        ]);
    }

    /**
     * Insert a questionnaire row pointing at a specific survey.
     *
     * @param int $courseid
     * @param int $sid
     * @return int
     */
    private function make_questionnaire(int $courseid, int $sid): int {
        global $DB;
        return $DB->insert_record('questionnaire', (object)[
            'course' => $courseid,
            'name' => 'Q',
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
     * Insert a survey row.
     *
     * @param int $courseid
     * @param string $realm
     * @param string $name
     * @return int
     */
    private function make_survey(int $courseid, string $realm = 'private', string $name = 'S'): int {
        global $DB;
        return $DB->insert_record('questionnaire_survey', (object)[
            'name' => $name,
            'courseid' => $courseid,
            'realm' => $realm,
            'status' => 0,
            'title' => $name,
        ]);
    }

    /**
     * get_or_null() returns the record when it exists and null when it doesn't.
     */
    public function test_get_or_null(): void {
        $this->resetAfterTest();
        $rid = $this->make_response(8000, 1, 'y');

        $record = response_record::get_or_null($rid);
        $this->assertInstanceOf(response_record::class, $record);
        $this->assertEquals($rid, $record->get('id'));
        $this->assertEquals(8000, $record->get('questionnaireid'));

        $this->assertNull(response_record::get_or_null(999999));
        $this->assertNull(response_record::get_or_null(0));
    }

    /**
     * get_course_fullname() returns the owning course's fullname for a complete response.
     */
    public function test_get_course_fullname(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Shared course']);
        $sid = $this->make_survey((int)$course->id, 'public');
        $qid = $this->make_questionnaire((int)$course->id, $sid);
        $completerid = $this->make_response($qid, 1, 'y');
        $incompleterid = $this->make_response($qid, 1, 'n');

        $this->assertSame('Shared course', response_record::get_course_fullname($completerid));
        $this->assertNull(response_record::get_course_fullname($incompleterid));
        $this->assertNull(response_record::get_course_fullname(999999));
    }

    /**
     * get_for_questionnaire() returns every response for the questionnaire.
     */
    public function test_get_for_questionnaire(): void {
        $this->resetAfterTest();
        $qid = 9001;
        $this->make_response($qid, 1, 'y');
        $this->make_response($qid, 2, 'n');
        $this->make_response(9002, 1, 'y');

        $this->assertCount(2, response_record::get_for_questionnaire($qid));
        $this->assertCount(0, response_record::get_for_questionnaire(99999));
    }

    /**
     * get_complete_for_user() returns only the user's complete responses.
     */
    public function test_get_complete_for_user(): void {
        $this->resetAfterTest();
        $qid = 9003;
        $userid = 42;
        $this->make_response($qid, $userid, 'y');
        $this->make_response($qid, $userid, 'y');
        $this->make_response($qid, $userid, 'n');  // Incomplete — excluded.
        $this->make_response($qid, 43, 'y');       // Other user — excluded.

        $this->assertCount(2, response_record::get_complete_for_user($qid, $userid));
    }

    /**
     * user_has_complete_response() returns true only if a complete response exists.
     */
    public function test_user_has_complete_response(): void {
        $this->resetAfterTest();
        $qid = 9004;
        $userid = 50;

        $this->assertFalse(response_record::user_has_complete_response($qid, $userid));

        $this->make_response($qid, $userid, 'n');
        $this->assertFalse(response_record::user_has_complete_response($qid, $userid));

        $this->make_response($qid, $userid, 'y');
        $this->assertTrue(response_record::user_has_complete_response($qid, $userid));
    }

    /**
     * user_has_saved_response() returns true only if an incomplete response exists.
     */
    public function test_user_has_saved_response(): void {
        $this->resetAfterTest();
        $qid = 9005;
        $userid = 51;

        $this->assertFalse(response_record::user_has_saved_response($qid, $userid));

        $this->make_response($qid, $userid, 'y');
        $this->assertFalse(response_record::user_has_saved_response($qid, $userid));

        $this->make_response($qid, $userid, 'n');
        $this->assertTrue(response_record::user_has_saved_response($qid, $userid));
    }

    /**
     * get_latest_incomplete() returns the most recent incomplete response, or null.
     */
    public function test_get_latest_incomplete(): void {
        $this->resetAfterTest();
        $qid = 9006;
        $userid = 52;

        $this->assertNull(response_record::get_latest_incomplete($qid, $userid));

        $older = $this->make_response($qid, $userid, 'n', time() - 3600);
        $newer = $this->make_response($qid, $userid, 'n', time());
        $this->make_response($qid, $userid, 'y'); // Complete — excluded.

        $latest = response_record::get_latest_incomplete($qid, $userid);
        $this->assertNotNull($latest);
        $this->assertSame($newer, $latest->get('id'));
    }

    /**
     * count_complete_for_questionnaire() counts only complete responses scoped to the instance.
     */
    public function test_count_complete_for_questionnaire(): void {
        $this->resetAfterTest();
        $qid = 9007;
        $this->make_response($qid, 1, 'y');
        $this->make_response($qid, 2, 'y');
        $this->make_response($qid, 3, 'n'); // Incomplete — excluded.
        $this->make_response(9008, 1, 'y'); // Different questionnaire.

        $this->assertSame(2, response_record::count_complete_for_questionnaire($qid));
    }

    /**
     * count_complete_for_questionnaire() can restrict by user id.
     */
    public function test_count_complete_for_questionnaire_user_filter(): void {
        $this->resetAfterTest();
        $qid = 9009;
        $this->make_response($qid, 1, 'y');
        $this->make_response($qid, 1, 'y');
        $this->make_response($qid, 2, 'y');

        $this->assertSame(2, response_record::count_complete_for_questionnaire($qid, 1));
        $this->assertSame(1, response_record::count_complete_for_questionnaire($qid, 2));
        $this->assertSame(0, response_record::count_complete_for_questionnaire($qid, 99));
    }

    /**
     * count_complete_for_questionnaire() can restrict by group membership.
     */
    public function test_count_complete_for_questionnaire_group_filter(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 9010;
        $this->make_response($qid, 1, 'y');
        $this->make_response($qid, 2, 'y');
        $this->make_response($qid, 3, 'y');

        // Put users 1 and 2 in group 100 (raw insert — no need for a real course/group fixture).
        $gid = 100;
        $DB->insert_record('groups_members', (object)['groupid' => $gid, 'userid' => 1]);
        $DB->insert_record('groups_members', (object)['groupid' => $gid, 'userid' => 2]);

        $this->assertSame(2, response_record::count_complete_for_questionnaire($qid, false, $gid));
        $this->assertSame(0, response_record::count_complete_for_questionnaire($qid, false, 999));
    }

    /**
     * count_complete_for_public_survey() counts complete responses across every
     * questionnaire instance pointing at the shared survey.
     */
    public function test_count_complete_for_public_survey(): void {
        $this->resetAfterTest();
        $sid = $this->make_survey(1, 'public');
        $q1 = $this->make_questionnaire(11, $sid);
        $q2 = $this->make_questionnaire(22, $sid);
        $this->make_response($q1, 1, 'y');
        $this->make_response($q1, 2, 'y');
        $this->make_response($q2, 3, 'y');
        $this->make_response($q1, 4, 'n'); // Incomplete — excluded.

        $this->assertSame(3, response_record::count_complete_for_public_survey($sid));
    }

    /**
     * count_complete_for_public_survey() can restrict by user id.
     */
    public function test_count_complete_for_public_survey_user_filter(): void {
        $this->resetAfterTest();
        $sid = $this->make_survey(1, 'public');
        $q1 = $this->make_questionnaire(11, $sid);
        $q2 = $this->make_questionnaire(22, $sid);
        $this->make_response($q1, 1, 'y');
        $this->make_response($q2, 1, 'y');
        $this->make_response($q1, 2, 'y');

        $this->assertSame(2, response_record::count_complete_for_public_survey($sid, 1));
        $this->assertSame(1, response_record::count_complete_for_public_survey($sid, 2));
    }
}
