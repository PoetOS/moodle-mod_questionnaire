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
 * Unit tests for mod_questionnaire\submission_controller.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/tests/questionnaire_testable.php');

/**
 * Unit tests for mod_questionnaire\submission_controller.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\submission_controller
 */
final class submission_controller_test extends \advanced_testcase {
    /**
     * Build a questionnaire_record persistent populated from an in-memory object.
     *
     * @param array $fields
     * @return questionnaire_record
     */
    private function make_module_record(array $fields = []): questionnaire_record {
        return new questionnaire_record(0, (object)array_merge([
            'id'               => 1,
            'course'           => 1,
            'name'             => 'Test questionnaire',
            'intro'            => '',
            'introformat'      => FORMAT_HTML,
            'qtype'            => questionnaire::QTYPE_UNLIMITED,
            'respondenttype'   => 'fullname',
            'respeligible'     => 'all',
            'respview'         => questionnaire::RESPVIEW_NEVER,
            'notifications'    => 0,
            'opendate'         => 0,
            'closedate'        => 0,
            'resume'           => 0,
            'navigate'         => 0,
            'grade'            => 0,
            'sid'              => 1,
            'timemodified'     => 0,
            'completionsubmit' => 0,
            'autonum'          => 3,
            'progressbar'      => 0,
            'removeafter'      => 0,
        ], $fields));
    }

    /**
     * Build a survey_record persistent from an in-memory object.
     *
     * @param array $fields
     * @return survey_record
     */
    private function make_survey_record(array $fields = []): survey_record {
        return new survey_record(0, (object)array_merge([
            'id'                  => 1,
            'name'                => 'Test survey',
            'courseid'            => 1,
            'realm'               => 'private',
            'status'              => 0,
            'title'               => 'Title',
            'email'               => null,
            'subtitle'            => null,
            'info'                => null,
            'theme'               => null,
            'thankspage'          => null,
            'thankhead'           => null,
            'thankbody'           => null,
            'feedbacksections'    => 0,
            'feedbacknotes'       => null,
            'feedbackscores'      => 0,
            'charttype'           => null,
        ], $fields));
    }

    /**
     * Build a questionnaire_testable wrapping fresh module/survey records.
     *
     * @param array $modulefields
     * @param array $surveyfields
     * @return questionnaire_testable
     */
    private function make_questionnaire(array $modulefields = [], array $surveyfields = []): questionnaire_testable {
        if (!array_key_exists('id', $surveyfields)) {
            $surveyfields['id'] = $modulefields['sid'] ?? 1;
        }
        return new questionnaire_testable(
            $this->make_module_record($modulefields),
            $this->make_survey_record($surveyfields)
        );
    }

    // Tests for existing_response_action().

    /**
     * Asserts existing_response_action() inserts a new DB record when rid is 0.
     */
    public function test_existing_response_action_inserts_when_rid_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 55561;
        $q = $this->make_questionnaire(['id' => $qid]);
        $q->set_questions_by_sec([1 => []]);
        $newrid = $q->submission()->existing_response_action((object)['rid' => 0, 'sec' => 1], 1);
        $this->assertGreaterThan(0, $newrid);
        $this->assertTrue($DB->record_exists('questionnaire_response', ['id' => $newrid, 'questionnaireid' => $qid]));
    }

    /**
     * Asserts existing_response_action() updates the submitted time on an existing record.
     */
    public function test_existing_response_action_updates_existing_record(): void {
        global $DB;
        $this->resetAfterTest();
        $qid = 55562;
        $q = $this->make_questionnaire(['id' => $qid]);
        $q->set_questions_by_sec([1 => []]);
        $originaltime = time() - 3600;
        $rid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $qid, 'userid' => 1,
            'submitted' => $originaltime, 'complete' => 'n', 'grade' => 0,
        ]);
        $returnedrid = $q->submission()->existing_response_action((object)['rid' => $rid, 'sec' => 1], 1);
        $this->assertSame((int)$rid, (int)$returnedrid);
        $this->assertGreaterThan($originaltime, $DB->get_field('questionnaire_response', 'submitted', ['id' => $rid]));
    }

    // Tests for next_page_action().

    /**
     * Asserts next_page_action() returns the next section number when the response is valid.
     */
    public function test_next_page_action_returns_next_section_when_valid(): void {
        $this->resetAfterTest();
        // With no questions, response_check_format returns '' so the action proceeds.
        $q = $this->make_questionnaire(['id' => 44451, 'navigate' => 0]);
        $q->set_questions_by_sec([1 => []]);
        $result = $q->submission()->next_page_action((object)['rid' => 0, 'sec' => 1], 1);
        // With no dependencies, next_page(1, 0) increments to 2.
        $this->assertSame(2, $result);
    }

    // Tests for previous_page_action().

    /**
     * Asserts previous_page_action() saves the response and returns the previous section number.
     */
    public function test_previous_page_action_returns_previous_section(): void {
        $this->resetAfterTest();
        $q = $this->make_questionnaire(['id' => 44452, 'navigate' => 0]);
        $q->set_questions_by_sec([1 => [], 2 => []]);
        $result = $q->submission()->previous_page_action((object)['rid' => 0, 'sec' => 2], 1);
        // With no dependencies, prev_page(2, 0) returns 1.
        $this->assertSame(1, $result);
    }

    /**
     * Asserts previous_page_action() returns false when already on the first section.
     */
    public function test_previous_page_action_returns_false_on_first_section(): void {
        $this->resetAfterTest();
        $q = $this->make_questionnaire(['id' => 44453, 'navigate' => 0]);
        $q->set_questions_by_sec([1 => []]);
        $result = $q->submission()->previous_page_action((object)['rid' => 0, 'sec' => 1], 1);
        // Prev_page(1, 0) decrements to 0, which returns false.
        $this->assertFalse($result);
    }
}
