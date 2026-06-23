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
 * Unit tests for mod_questionnaire\reporter.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\output\reportpage;

/**
 * Unit tests for mod_questionnaire\reporter.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\reporter::add_user_responses
 * @covers \mod_questionnaire\reporter::view_all_responses
 * @covers \mod_questionnaire\reporter::view_response
 * @covers \mod_questionnaire\reporter::survey_results
 * @covers \mod_questionnaire\reporter::survey_results_navbar_alpha
 * @covers \mod_questionnaire\reporter::survey_results_navbar_student
 */
final class reporter_test extends \advanced_testcase {
    /**
     * Build a course + questionnaire with one yes/no question and one enrolled
     * student, and return the questionnaire + student id.
     *
     * @return array [questionnaire, int studentid]
     */
    private function setup_fixture(): array {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $plugin = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $plugin->create_test_questionnaire(
            $course,
            QUESYESNO,
            ['content' => 'Yes or no?'],
            []
        );

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $PAGE->set_context($questionnaire->context());

        return [$questionnaire, (int) $student->id];
    }

    /**
     * Seed a complete response from the given user on the given questionnaire.
     *
     * @param questionnaire $questionnaire
     * @param int $userid
     * @return int Response id.
     */
    private function seed_complete_response(questionnaire $questionnaire, int $userid): int {
        $plugin = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $resp = $plugin->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            $userid,
            true
        );
        // The generate_response helper returns the array form of the response row.
        return (int) $resp['id'];
    }

    /**
     * Build a reporter with a renderer + page wired up for the page-side methods.
     *
     * @param questionnaire $questionnaire
     * @return array [reporter, reportpage]
     */
    private function build_reporter(questionnaire $questionnaire): array {
        global $PAGE;
        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = new reportpage();
        return [new reporter($questionnaire, $renderer, $page), $page];
    }

    /**
     * add_user_responses() loads the user's responses into the shared collection.
     */
    public function test_add_user_responses_loads_user_responses(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $rid = $this->seed_complete_response($questionnaire, $studentid);

        // Reload questionnaire — the responses collection caches per-instance state.
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter] = $this->build_reporter($questionnaire);

        $this->assertSame([], $questionnaire->responses()->get_loaded_responses());
        $reporter->add_user_responses($studentid);
        $loaded = $questionnaire->responses()->get_loaded_responses();
        $this->assertArrayHasKey($rid, $loaded);
    }

    /**
     * view_all_responses() renders the seeded response data into the responses slot.
     */
    public function test_view_all_responses_renders_loaded_responses(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);
        $reporter->add_user_responses($studentid);
        $reporter->view_all_responses();

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('responses', $data);
        $this->assertNotEmpty($data->responses);
    }

    /**
     * view_all_responses() emits the 'noresponses' placeholder when nothing was loaded.
     */
    public function test_view_all_responses_renders_placeholder_when_empty(): void {
        [$questionnaire] = $this->setup_fixture();
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->view_all_responses();

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('responses', $data);
        $this->assertStringContainsString(
            get_string('noresponses', 'questionnaire'),
            implode('', (array) $data->responses)
        );
    }

    /**
     * view_response() renders the single response into the responses slot.
     */
    public function test_view_response_renders_single_response(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $rid = $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->view_response($rid, '', [$rid => null]);

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('responses', $data);
        $this->assertNotEmpty($data->responses);
    }

    /**
     * survey_results() short-circuits silently when the survey has no questions.
     */
    public function test_survey_results_no_questions_short_circuits(): void {
        [$questionnaire] = $this->setup_fixture();
        // Delete the single question so questions() returns empty.
        global $DB;
        $DB->delete_records('questionnaire_question', ['surveyid' => $questionnaire->surveyid()]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->survey_results();

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        // No respondentinfo notification produced when there are no questions to report on.
        $this->assertObjectNotHasProperty('respondentinfo', $data);
    }

    /**
     * survey_results() emits the 'noresponses' notification when responses are absent.
     */
    public function test_survey_results_no_responses_emits_notification(): void {
        [$questionnaire] = $this->setup_fixture();
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->survey_results();

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('respondentinfo', $data);
        $this->assertStringContainsString(
            get_string('noresponses', 'questionnaire'),
            $data->respondentinfo
        );
    }

    /**
     * survey_results_navbar_alpha() short-circuits silently when no responses exist.
     */
    public function test_survey_results_navbar_alpha_short_circuits_without_responses(): void {
        [$questionnaire] = $this->setup_fixture();
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->survey_results_navbar_alpha(0, 0, false);

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectNotHasProperty('navigationbar', $data);
        $this->assertObjectNotHasProperty('responses', $data);
    }

    /**
     * survey_results_navbar_alpha() with byresponse=false adds a prev/next navigation bar
     * to the page when multiple responses exist.
     */
    public function test_survey_results_navbar_alpha_writes_navigationbar(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        // Two extra students so there's a prev and next to navigate.
        $extras = [];
        $extras[] = $studentid;
        $extras[] = (int) $this->getDataGenerator()->create_user()->id;
        $extras[] = (int) $this->getDataGenerator()->create_user()->id;
        foreach ([$extras[1], $extras[2]] as $uid) {
            $this->getDataGenerator()->enrol_user($uid, $questionnaire->course()->id, 'student');
        }
        $rids = [];
        foreach ($extras as $uid) {
            $rids[] = $this->seed_complete_response($questionnaire, $uid);
        }

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        // Navigate from the middle response.
        $reporter->survey_results_navbar_alpha($rids[1], 0, false);

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('navigationbar', $data);
    }

    /**
     * survey_results_navbar_alpha() with byresponse=true emits a respondents-list rather
     * than a navigation bar.
     */
    public function test_survey_results_navbar_alpha_byresponse_emits_respondents(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $rid = $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->survey_results_navbar_alpha($rid, 0, true);

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('responses', $data);
        $this->assertObjectNotHasProperty('navigationbar', $data);
    }

    /**
     * survey_results_navbar_student() writes the nav into both navigationbar slots
     * when the student has multiple responses.
     */
    public function test_survey_results_navbar_student_writes_both_navigationbars(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        // Multiple responses by the same student.
        $rid1 = $this->seed_complete_response($questionnaire, $studentid);
        $rid2 = $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $resps = $questionnaire->get_responses($studentid);
        $reporter->survey_results_navbar_student(
            $rid1,
            $studentid,
            $questionnaire->id(),
            $resps,
            'myreport'
        );

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('navigationbar', $data);
        $this->assertObjectHasProperty('bottomnavigationbar', $data);
    }

    /**
     * survey_results_navbar_student() in report mode includes the survey id in nav URLs.
     */
    public function test_survey_results_navbar_student_report_mode_includes_sid(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $rid1 = $this->seed_complete_response($questionnaire, $studentid);
        $rid2 = $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $resps = $questionnaire->get_responses($studentid);
        $reporter->survey_results_navbar_student(
            $rid1,
            $studentid,
            $questionnaire->id(),
            $resps,
            'report',
            (string) $questionnaire->surveyid()
        );

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertStringContainsString('sid=' . $questionnaire->surveyid(), $data->navigationbar);
    }

    /**
     * survey_results() with completed responses populates a respondent-count header.
     */
    public function test_survey_results_with_responses_renders_header(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->survey_results();

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertObjectHasProperty('respondentinfo', $data);
        $this->assertStringContainsString(
            get_string('responses', 'questionnaire'),
            $data->respondentinfo
        );
    }
}
