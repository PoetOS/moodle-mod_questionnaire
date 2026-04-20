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
 * Unit tests for questionnaire::save_mobile_data().
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

/**
 * Tests for questionnaire::save_mobile_data().
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\questionnaire::save_mobile_data
 */
final class save_mobile_data_test extends \advanced_testcase {
    // Helpers.

    /**
     * Create a course, questionnaire instance, and one optional-text question.
     * Returns [$questionnaire, $userid].
     *
     * The pagebreak requires at least two questions before it (position >= 2) so that
     * load_questions() does not treat it as a leading break and skip it. We therefore
     * add Q1 + Q2 on page 1, then a pagebreak, then Q3 on page 2.
     *
     * @param bool $addpagebreak Also add a page break + a third text question (for nav tests).
     * @param bool $required Make the first text question required.
     * @return array [$questionnaire, $userid]
     */
    private function make_fixture(bool $addpagebreak = false, bool $required = false): array {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $qgen = $generator->get_plugin_generator('mod_questionnaire');
        $questionnaire = $qgen->create_instance(['course' => $course->id]);

        $surveyid = $questionnaire->surveyid();
        $qgen->create_question($questionnaire, [
            'surveyid' => $surveyid,
            'name'     => 'Q1',
            'typeid'   => QUESTEXT,
            'content'  => 'Enter some text',
            'required' => $required ? 'y' : 'n',
        ]);

        if ($addpagebreak) {
            // Two questions on page 1 push the pagebreak to position >= 2, which is
            // required for load_questions() to recognise it as a real section break.
            $qgen->create_question($questionnaire, [
                'surveyid' => $surveyid,
                'name'     => 'Q2',
                'typeid'   => QUESTEXT,
                'content'  => 'Enter more text',
                'required' => 'n',
            ]);
            $qgen->create_question($questionnaire, [
                'surveyid' => $surveyid,
                'name'     => 'break1',
                'typeid'   => QUESPAGEBREAK,
                'content'  => '',
            ]);
            $qgen->create_question($questionnaire, [
                'surveyid' => $surveyid,
                'name'     => 'Q3',
                'typeid'   => QUESTEXT,
                'content'  => 'Enter final text',
                'required' => 'n',
            ]);
        }

        $user = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentrole->id);

        // Reload so questions_by_section is populated.
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        return [$questionnaire, $user->id];
    }

    // Tests for action = '' (plain save).

    /**
     * A plain save with no action inserts a response record and returns an empty array.
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_plain_save_inserts_response(): void {
        global $DB;
        [$questionnaire, $userid] = $this->make_fixture();

        $result = $questionnaire->save_mobile_data($userid, 1, 0, 0, 0, '', []);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('warnings', $result);
        $this->assertTrue(
            $DB->record_exists('questionnaire_response', ['questionnaireid' => $questionnaire->id(), 'userid' => $userid])
        );
    }

    /**
     * When completed=1 (reviewing), a plain save must NOT insert a new response.
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_plain_save_skips_insert_when_completed(): void {
        global $DB;
        [$questionnaire, $userid] = $this->make_fixture();

        $result = $questionnaire->save_mobile_data($userid, 1, 1, 0, 0, '', []);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('warnings', $result);
        $this->assertFalse(
            $DB->record_exists('questionnaire_response', ['questionnaireid' => $questionnaire->id(), 'userid' => $userid])
        );
    }

    /**
     * A required question with no answer returns warnings and a response object; no DB record is created.
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_plain_save_returns_warnings_for_missing_required(): void {
        global $DB;
        [$questionnaire, $userid] = $this->make_fixture(false, true); // Required question.

        $result = $questionnaire->save_mobile_data($userid, 1, 0, 0, 0, '', []);

        $this->assertArrayHasKey('warnings', $result);
        $this->assertNotEmpty($result['warnings']);
        $this->assertArrayHasKey('response', $result);
        $this->assertFalse(
            $DB->record_exists('questionnaire_response', ['questionnaireid' => $questionnaire->id(), 'userid' => $userid])
        );
    }

    // Tests for submit=1.

    /**
     * When submit=1 and there are no warnings, the response is committed (complete='y').
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_submit_commits_response(): void {
        global $DB;
        [$questionnaire, $userid] = $this->make_fixture();

        $questionnaire->save_mobile_data($userid, 1, 0, 0, 1, '', []);

        $record = $DB->get_record(
            'questionnaire_response',
            ['questionnaireid' => $questionnaire->id(), 'userid' => $userid],
            'complete',
            MUST_EXIST
        );
        $this->assertEquals('y', $record->complete);
    }

    /**
     * When submit=1 but there are warnings (required question unanswered), the response is NOT committed.
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_submit_skipped_when_warnings_present(): void {
        global $DB;
        [$questionnaire, $userid] = $this->make_fixture(false, true); // Required question.

        $questionnaire->save_mobile_data($userid, 1, 0, 0, 1, '', []);

        $this->assertFalse(
            $DB->record_exists('questionnaire_response', ['questionnaireid' => $questionnaire->id(), 'userid' => $userid])
        );
    }

    // Tests for action = 'nextpage'.

    /**
     * nextpage action on a two-page questionnaire returns nextpagenum = 2.
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_nextpage_returns_next_section_number(): void {
        [$questionnaire, $userid] = $this->make_fixture(true); // Two-page questionnaire.

        $result = $questionnaire->save_mobile_data($userid, 1, 0, 0, 0, 'nextpage', []);

        $this->assertArrayHasKey('nextpagenum', $result);
        $this->assertEquals(2, $result['nextpagenum']);
    }

    // Tests for action = 'previouspage'.

    /**
     * previouspage action from page 2 returns nextpagenum = 1.
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_previouspage_returns_previous_section_number(): void {
        [$questionnaire, $userid] = $this->make_fixture(true); // Two-page questionnaire.

        $result = $questionnaire->save_mobile_data($userid, 2, 0, 0, 0, 'previouspage', []);

        $this->assertArrayHasKey('nextpagenum', $result);
        $this->assertEquals(1, $result['nextpagenum']);
    }

    /**
     * previouspage action from page 1 returns nextpagenum = false (no previous page).
     *
     * @covers \mod_questionnaire\questionnaire::save_mobile_data
     */
    public function test_previouspage_returns_false_on_first_page(): void {
        [$questionnaire, $userid] = $this->make_fixture(true);

        $result = $questionnaire->save_mobile_data($userid, 1, 0, 0, 0, 'previouspage', []);

        $this->assertArrayHasKey('nextpagenum', $result);
        $this->assertFalse($result['nextpagenum']);
    }
}
