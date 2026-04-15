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
 * Unit tests for mod_questionnaire\local\response\questionnaire_responses static methods.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\question_type;
use mod_questionnaire\local\response\questionnaire_responses;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

/**
 * Unit tests for mod_questionnaire\local\response\questionnaire_responses static methods.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\response\questionnaire_responses
 */
final class questionnaire_responses_test extends \advanced_testcase {
    // Tests for count_for_question().

    /**
     * Asserts count_for_question() returns zero for the QUESSECTIONTEXT type without any DB query.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::count_for_question
     */
    public function test_count_for_question_returns_zero_for_sectiontext(): void {
        $this->assertEquals(0, questionnaire_responses::count_for_question(99, question_type::QUESSECTIONTEXT));
    }

    /**
     * Asserts count_for_question() returns zero when no response records exist for the question.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::count_for_question
     */
    public function test_count_for_question_returns_zero_when_no_records(): void {
        $this->resetAfterTest();
        // Use a question id that has never had responses.
        $this->assertEquals(0, questionnaire_responses::count_for_question(99999, question_type::QUESYESNO));
    }

    /**
     * Asserts count_for_question() returns the correct count after inserting response records.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::count_for_question
     */
    public function test_count_for_question_returns_correct_count(): void {
        global $DB;
        $this->resetAfterTest();

        // Insert two yes/no response records for the same question id.
        $qid = 12301;
        $DB->insert_record('questionnaire_response_bool', (object)['questionid' => $qid, 'responseid' => 1, 'choice' => 'y']);
        $DB->insert_record('questionnaire_response_bool', (object)['questionid' => $qid, 'responseid' => 2, 'choice' => 'n']);

        $this->assertEquals(2, questionnaire_responses::count_for_question($qid, question_type::QUESYESNO));
    }

    /**
     * Asserts count_for_question() only counts records for the specified question id.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::count_for_question
     */
    public function test_count_for_question_counts_only_matching_questionid(): void {
        global $DB;
        $this->resetAfterTest();

        $qid = 12302;
        $otherid = 12303;
        $DB->insert_record('questionnaire_response_bool', (object)['questionid' => $qid, 'responseid' => 1, 'choice' => 'y']);
        $DB->insert_record('questionnaire_response_bool', (object)['questionid' => $otherid, 'responseid' => 2, 'choice' => 'n']);

        $this->assertEquals(1, questionnaire_responses::count_for_question($qid, question_type::QUESYESNO));
    }

    /**
     * Asserts count_for_question() returns zero for an unknown type id with no response table.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::count_for_question
     */
    public function test_count_for_question_returns_zero_for_unknown_type(): void {
        $this->resetAfterTest();
        $this->assertEquals(0, questionnaire_responses::count_for_question(1, 999));
    }

    // Tests for delete_old_responses().

    /**
     * Asserts delete_old_responses() deletes responses older than the questionnaire's retention period.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::delete_old_responses
     */
    public function test_delete_old_responses_removes_expired_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        // Set removeafter to 1 hour.
        $onehoursecs = HOURSECS;
        $DB->set_field('questionnaire', 'removeafter', $onehoursecs, ['id' => $questionnaire->id()]);

        // Insert a response submitted 2 hours ago (expired).
        $expiredid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time() - (2 * HOURSECS),
            'complete' => 'y',
            'grade' => 0,
        ]);

        questionnaire_responses::delete_old_responses();

        $this->assertFalse($DB->record_exists('questionnaire_response', ['id' => $expiredid]));
    }

    /**
     * Asserts delete_old_responses() leaves responses that are still within the retention period.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::delete_old_responses
     */
    public function test_delete_old_responses_keeps_recent_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        // Set removeafter to 1 day.
        $DB->set_field('questionnaire', 'removeafter', DAYSECS, ['id' => $questionnaire->id()]);

        // Insert a response submitted 1 hour ago (still within 1 day retention).
        $recentid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time() - HOURSECS,
            'complete' => 'y',
            'grade' => 0,
        ]);

        questionnaire_responses::delete_old_responses();

        $this->assertTrue($DB->record_exists('questionnaire_response', ['id' => $recentid]));
    }

    /**
     * Asserts delete_old_responses() leaves responses when removeafter is zero (disabled).
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::delete_old_responses
     */
    public function test_delete_old_responses_ignores_when_removeafter_is_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        // Explicitly set removeafter to 0 (disabled).
        $DB->set_field('questionnaire', 'removeafter', 0, ['id' => $questionnaire->id()]);

        // Insert a very old response.
        $oldid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time() - (365 * DAYSECS),
            'complete' => 'y',
            'grade' => 0,
        ]);

        questionnaire_responses::delete_old_responses();

        // With removeafter=0 the WHERE clause filters it out; the response must survive.
        $this->assertTrue($DB->record_exists('questionnaire_response', ['id' => $oldid]));
    }

    /**
     * Asserts delete_old_responses() also removes answer rows from all response detail tables.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::delete_old_responses
     */
    public function test_delete_old_responses_removes_answer_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $DB->set_field('questionnaire', 'removeafter', HOURSECS, ['id' => $questionnaire->id()]);

        $expiredrid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time() - (2 * HOURSECS),
            'complete' => 'y',
            'grade' => 0,
        ]);

        // Insert an answer row in questionnaire_response_bool referencing the expired response.
        $DB->insert_record('questionnaire_response_bool', (object)[
            'questionid' => 1, 'responseid' => $expiredrid, 'choice' => 'y',
        ]);

        questionnaire_responses::delete_old_responses();

        $this->assertFalse(
            $DB->record_exists('questionnaire_response_bool', ['responseid' => $expiredrid]),
            'Answer rows in response_bool should be deleted with the expired response.'
        );
    }

    // Tests for get_incomplete_users().

    /**
     * Asserts get_incomplete_users() returns false when no users are enrolled.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::get_incomplete_users
     */
    public function test_get_incomplete_users_returns_false_when_no_enrolled_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id(), $course->id, false, MUST_EXIST);

        // No students enrolled; get_enrolled_users() returns empty → false.
        $result = questionnaire_responses::get_incomplete_users($cm, $questionnaire->surveyid());
        $this->assertFalse($result);
    }

    /**
     * Asserts get_incomplete_users() returns enrolled users who have not completed the questionnaire.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::get_incomplete_users
     */
    public function test_get_incomplete_users_returns_users_without_completion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $generator
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student1->id, $course->id, $studentrole->id);
        $generator->enrol_user($student2->id, $course->id, $studentrole->id);

        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id(), $course->id, false, MUST_EXIST);
        $result = questionnaire_responses::get_incomplete_users($cm, $questionnaire->surveyid());

        // Normalise to integers to avoid driver-specific string/int key differences.
        $intresult = is_array($result) ? array_map('intval', $result) : [];
        $this->assertNotEmpty($intresult, 'Enrolled students should appear as incomplete users.');
        $this->assertContains((int)$student1->id, $intresult);
        $this->assertContains((int)$student2->id, $intresult);
    }

    /**
     * Asserts get_incomplete_users() excludes users who have submitted a complete response.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::get_incomplete_users
     */
    public function test_get_incomplete_users_excludes_completers(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $generator
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($student1->id, $course->id, $studentrole->id);
        $generator->enrol_user($student2->id, $course->id, $studentrole->id);

        // Student1 submits a complete response.
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => $student1->id,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id(), $course->id, false, MUST_EXIST);
        $result = questionnaire_responses::get_incomplete_users($cm, $questionnaire->surveyid());

        $intresult = is_array($result) ? array_map('intval', $result) : [];
        $this->assertNotContains((int)$student1->id, $intresult);
        $this->assertContains((int)$student2->id, $intresult);
    }

    /**
     * Asserts get_incomplete_users() supports paging via startpage and pagecount parameters.
     *
     * The internal early-return path (no completions yet) skips paging, so a completed
     * response must exist before the paging slice can be exercised.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::get_incomplete_users
     */
    public function test_get_incomplete_users_supports_paging(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $generator
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $students = [];
        for ($i = 0; $i < 5; $i++) {
            $student = $generator->create_user();
            $generator->enrol_user($student->id, $course->id, $studentrole->id);
            $students[] = $student;
        }

        // One student completes — triggers the completion-check path that applies paging.
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => $students[0]->id,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        // 4 remaining students are incomplete. Page by 2 gives two non-overlapping pages.
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id(), $course->id, false, MUST_EXIST);
        $page0 = questionnaire_responses::get_incomplete_users($cm, $questionnaire->surveyid(), false, '', 0, 2);
        $page1 = questionnaire_responses::get_incomplete_users($cm, $questionnaire->surveyid(), false, '', 2, 2);

        $this->assertIsArray($page0);
        $this->assertCount(2, $page0);
        $this->assertIsArray($page1);
        $this->assertCount(2, $page1);
        // No overlap between pages.
        $this->assertEmpty(array_intersect($page0, $page1));
    }
}
