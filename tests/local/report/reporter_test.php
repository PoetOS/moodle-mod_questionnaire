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
 * Unit tests for mod_questionnaire\local\report\reporter.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\report;

use mod_questionnaire\output\page;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\report\reporter.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\report\reporter::add_user_responses
 * @covers \mod_questionnaire\local\report\reporter::view_all_responses
 * @covers \mod_questionnaire\local\report\reporter::view_response
 * @covers \mod_questionnaire\local\report\reporter::survey_results
 * @covers \mod_questionnaire\local\report\reporter::survey_results_navbar_alpha
 * @covers \mod_questionnaire\local\report\reporter::survey_results_navbar_student
 * @covers \mod_questionnaire\local\report\reporter::response_analysis
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
     * @return array [reporter, page]
     */
    private function build_reporter(questionnaire $questionnaire): array {
        global $PAGE;
        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = page::report();
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
     * view_response() lets a student view their own response without any
     * view-all-responses rights (the myreport.php path).
     */
    public function test_view_response_allows_own_response(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $rid = $this->seed_complete_response($questionnaire, $studentid);

        $this->setUser($studentid);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);

        $reporter->view_response($rid, '', [$rid => null]);

        global $PAGE;
        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertNotEmpty($data->responses);
    }

    /**
     * view_response() throws nopermissions when a student asks for another
     * student's response.
     */
    public function test_view_response_rejects_other_users_response(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $otherrid = $this->seed_complete_response($questionnaire, $studentid);

        $snoop = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($snoop->id, $questionnaire->courseid(), 'student');
        $this->seed_complete_response($questionnaire, (int) $snoop->id);

        $this->setUser($snoop);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter] = $this->build_reporter($questionnaire);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/permission|nopermissions/i');
        $reporter->view_response($otherrid, '', [$otherrid => null]);
    }

    /**
     * view_response() throws nopermissions for a response that belongs to a
     * different questionnaire, even for a user who can read all responses.
     */
    public function test_view_response_rejects_response_from_other_questionnaire(): void {
        [$questionnaire, $studentid] = $this->setup_fixture();
        $plugin = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $otherquestionnaire = $plugin->create_test_questionnaire(
            $questionnaire->course(),
            QUESYESNO,
            ['content' => 'Another yes or no?'],
            []
        );
        $foreignrid = $this->seed_complete_response($otherquestionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter] = $this->build_reporter($questionnaire);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/permission|nopermissions/i');
        $reporter->view_response($foreignrid, '', [$foreignrid => null]);
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
     * survey_results() over all responses counts them, broken down into submitted and in progress.
     */
    public function test_survey_results_counts_responses(): void {
        global $PAGE;
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);
        $other = $this->getDataGenerator()->create_and_enrol($questionnaire->course(), 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')
            ->generate_response($questionnaire, $questionnaire->questions(), $other->id, false);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);
        $reporter->survey_results();

        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $breakdown = get_string('responses_breakdown', 'questionnaire', ['responses' => 1, 'incomplete' => 1]);
        $this->assertEquals(
            ['label' => get_string('responses', 'questionnaire'), 'count' => '2 ' . $breakdown],
            $data->responsecount
        );
        $this->assertObjectNotHasProperty('respondentinfo', $data);
    }

    /**
     * survey_results() filtered to submissions labels the count as submissions, without a breakdown.
     */
    public function test_survey_results_counts_submissions(): void {
        global $PAGE;
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);
        $reporter->survey_results('', false, false, '0', '', 'y');

        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertEquals(['label' => get_string('submissions', 'questionnaire'), 'count' => '1'], $data->responsecount);
    }

    /**
     * survey_results() adds one entry per question with its number, formatted text and results.
     */
    public function test_survey_results_adds_question_results(): void {
        global $PAGE;
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);
        $reporter->survey_results();

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $data = $page->export_for_template($renderer);
        $this->assertCount(1, $data->questionresults);
        $this->assertEquals(1, $data->questionresults[0]['qnum']);
        $this->assertStringContainsString('Yes or no?', $data->questionresults[0]['qcontent']);
        $this->assertIsString($data->questionresults[0]['results']);
        $this->assertObjectHasProperty('surveyheader', $data);

        $html = $renderer->render($page);
        $this->assertStringContainsString('<h2 class="qn-number">1</h2>', $html);
        $this->assertStringContainsString('<div class="qn-question">Yes or no?</div>', $html);
        $this->assertStringContainsString(
            get_string('responses', 'questionnaire') . ': <strong>1 ',
            $html
        );
    }

    /**
     * The PDF report template lays out the same response count and question results.
     */
    public function test_survey_results_pdf(): void {
        global $PAGE;
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $page = page::report_pdf();
        (new reporter($questionnaire, $renderer, $page))->survey_results('', false, true, '0');

        $html = $renderer->render($page);
        $this->assertStringContainsString(get_string('responses', 'questionnaire') . ': <strong>1 ', $html);
        $this->assertStringContainsString('font-size: large;">1</span>', $html);
        $this->assertStringContainsString('Yes or no?', $html);
    }

    /**
     * Without question autonumbering, the results entries carry no number.
     */
    public function test_survey_results_without_autonumbering(): void {
        global $DB, $PAGE;
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);
        $DB->set_field('questionnaire', 'autonum', 0, ['id' => $questionnaire->id()]);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        [$reporter, $page] = $this->build_reporter($questionnaire);
        $reporter->survey_results();

        $data = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->assertNull($data->questionresults[0]['qnum']);
    }

    /**
     * Build a questionnaire with two scored feedback sections: "Alpha" scores 100% and "Beta" 50% for one response.
     *
     * @return array [questionnaire, array responses keyed by id]
     */
    private function setup_scored_sections(): array {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $plugin = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = $plugin->create_instance(['course' => $course->id]);
        $questionnaire = questionnaire::from_instanceid($instance->id());
        $DB->update_record('questionnaire_survey', (object) [
            'id' => $questionnaire->surveyid(), 'feedbacksections' => 2, 'feedbackscores' => 1,
        ]);

        // The generated response picks each question's last choice: the top score for Q1, half of it for Q2.
        $choices = ['Q1' => [[1, 'Low'], [2, 'High']], 'Q2' => [[2, 'High'], [1, 'Low']]];
        $qids = [];
        foreach ($choices as $name => $values) {
            $question = $plugin->create_question(
                $questionnaire,
                ['surveyid' => $questionnaire->surveyid(), 'name' => $name, 'typeid' => QUESRADIO, 'required' => 'y'],
                array_map(fn($choice) => (object) ['value' => $choice[0], 'content' => $choice[1]], $values)
            );
            $qids[$name] = $question->id();
        }
        foreach (['Alpha' => 'Q1', 'Beta' => 'Q2'] as $label => $name) {
            (new \mod_questionnaire\local\db\feedback_section_record(0, (object) [
                'surveyid' => $questionnaire->surveyid(),
                'section' => $label == 'Alpha' ? 1 : 2,
                'scorecalculation' => serialize([$qids[$name] => 1]),
                'sectionlabel' => $label,
                'sectionheading' => $label,
            ]))->create();
        }

        $questionnaire = questionnaire::from_instanceid($instance->id());
        $student = $this->getDataGenerator()->create_user();
        $this->seed_complete_response($questionnaire, (int) $student->id);
        $PAGE->set_context($questionnaire->context());

        $questionnaire = questionnaire::from_instanceid($instance->id());
        return [$questionnaire, $questionnaire->get_responses()];
    }

    /**
     * Feedback scores follow section order by default; the vallasort / vallarsort actions sort them by score.
     */
    public function test_response_analysis_sorts_scores(): void {
        [$questionnaire, $resps] = $this->setup_scored_sections();
        $order = function (string $sortaction) use ($questionnaire, $resps): array {
            global $PAGE;
            $page = page::report();
            (new reporter($questionnaire, $PAGE->get_renderer('mod_questionnaire'), $page))
                ->response_analysis(0, $resps, false, false, true, 0, null, $sortaction);
            $scores = $page->export_for_template($PAGE->get_renderer('mod_questionnaire'))->feedbackscores;
            return [strpos($scores, 'Alpha'), strpos($scores, 'Beta')];
        };

        [$alpha, $beta] = $order('');
        $this->assertLessThan($beta, $alpha);
        [$alpha, $beta] = $order('vallasort');
        $this->assertLessThan($alpha, $beta);
        [$alpha, $beta] = $order('vallarsort');
        $this->assertLessThan($beta, $alpha);
    }

    /**
     * Without feedback sections there is nothing to analyse.
     */
    public function test_response_analysis_without_feedback(): void {
        global $PAGE;
        [$questionnaire, $studentid] = $this->setup_fixture();
        $this->seed_complete_response($questionnaire, $studentid);
        $page = page::report();

        $result = (new reporter($questionnaire, $PAGE->get_renderer('mod_questionnaire'), $page))
            ->response_analysis(0, $questionnaire->get_responses(), false, false, true, 0, null, 'vallasort');

        $this->assertSame('', $result);
        $this->assertObjectNotHasProperty('feedbackscores', $page->export_for_template($PAGE->get_renderer('mod_questionnaire')));
    }
}
