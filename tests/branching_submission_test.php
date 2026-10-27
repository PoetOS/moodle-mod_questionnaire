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
 * Submission tests for branching questionnaires where a parent answer changes mid-attempt.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Submission tests for branching questionnaires where a parent answer changes mid-attempt.
 *
 * Each test drives the same page-by-page path the web form uses (next_page_action,
 * previous_page_action, existing_response_action, commit_submission_response) against a
 * real questionnaire, so the stored answers are exactly what a participant would leave.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\submission_controller
 * @covers \mod_questionnaire\local\response\questionnaire_responses::commit_submission_response
 */
final class branching_submission_test extends \advanced_testcase {
    /** @var string The stale answer typed into the dependent question. */
    private const STALE_ANSWER = 'Bug-Blue';

    /**
     * Build a branching questionnaire: Q1 (yes/no) | Q2 (text, shown when Q1 = Yes) [| Q3 (yes/no)].
     *
     * Each question sits on its own page.
     *
     * @param bool $withfollowingpage True to add Q3 on a third page after the dependent question.
     * @return array [questionnaire, array of question ids keyed q1/q2/q3, int student id]
     */
    private function setup_branching_questionnaire(bool $withfollowingpage): array {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id, 'navigate' => 1]);

        $qids = [];
        $position = 1;
        $qids['q1'] = $this->add_question($questionnaire, 'Q1', QUESYESNO, $position++);
        $this->add_question($questionnaire, 'break1', QUESPAGEBREAK, $position++);
        $qids['q2'] = $this->add_question($questionnaire, 'Q2', QUESTEXT, $position++);
        if ($withfollowingpage) {
            $this->add_question($questionnaire, 'break2', QUESPAGEBREAK, $position++);
            $qids['q3'] = $this->add_question($questionnaire, 'Q3', QUESYESNO, $position++);
        }

        // Q2 is shown only when Q1 = Yes (yes/no choice 0 is 'y'; dependlogic 1 is "this answer given").
        (new \mod_questionnaire\local\db\dependency_record(0, (object)[
            'questionid' => $qids['q2'],
            'surveyid' => $questionnaire->surveyid(),
            'dependquestionid' => $qids['q1'],
            'dependchoiceid' => 0,
            'dependlogic' => 1,
            'dependandor' => 'and',
        ]))->create();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        return [questionnaire::from_instanceid($questionnaire->id()), $qids, (int)$student->id];
    }

    /**
     * Add a question at an explicit 1-based position.
     *
     * @param questionnaire $questionnaire
     * @param string $name
     * @param int $typeid
     * @param int $position
     * @return int The new question id.
     */
    private function add_question(questionnaire $questionnaire, string $name, int $typeid, int $position): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $record = ['surveyid' => $questionnaire->surveyid(), 'name' => $name, 'typeid' => $typeid];
        if ($typeid == QUESPAGEBREAK) {
            $record['content'] = 'break';
        }
        $question = $generator->create_question($questionnaire, $record);
        \mod_questionnaire\local\db\question_record::update_position($question->id(), $position);
        return $question->id();
    }

    /**
     * Build the form data a page submission posts.
     *
     * @param int $rid
     * @param int $sec
     * @param array $answers Map of question id => submitted value.
     * @return \stdClass
     */
    private function page(int $rid, int $sec, array $answers = []): \stdClass {
        $formdata = (object)['rid' => $rid, 'sec' => $sec];
        foreach ($answers as $qid => $value) {
            $formdata->{'q' . $qid} = $value;
        }
        return $formdata;
    }

    /**
     * Fetch the stored text answers for a question in a response.
     *
     * @param int $rid
     * @param int $qid
     * @return array
     */
    private function text_answers(int $rid, int $qid): array {
        global $DB;
        return $DB->get_fieldset_select(
            'questionnaire_response_text',
            'response',
            'responseid = ? AND questionid = ?',
            [$rid, $qid]
        );
    }

    /**
     * Answer Q1 = Yes and Q2, go back to Q1, change it to No, then walk forward to the end
     * page and submit — the path in the bug report.
     *
     * @param questionnaire $questionnaire
     * @param array $qids
     * @param int $userid
     * @return int The submitted response id.
     */
    private function answer_yes_then_change_to_no(questionnaire $questionnaire, array $qids, int $userid): int {
        $submission = $questionnaire->submission();
        $haspage3 = isset($qids['q3']);

        // Page 1: Q1 = Yes; Q2's page is eligible.
        $form = $this->page(0, 1, [$qids['q1'] => 'y']);
        $this->assertSame(2, $submission->next_page_action($form, $userid));
        $rid = (int)$form->rid;

        // Page 2: Q2 = Bug-Blue.
        $form = $this->page($rid, 2, [$qids['q2'] => self::STALE_ANSWER]);
        $next = $submission->next_page_action($form, $userid);

        if ($haspage3) {
            $this->assertSame(3, $next);
            // Page 3 -> back to page 2 (Q3 not yet answered).
            $this->assertSame(2, $submission->previous_page_action($this->page($rid, 3), $userid));
        } else {
            $this->assertFalse($next);
        }
        // Page 2 -> back to page 1, Q2 still holding the answer.
        $form = $this->page($rid, 2, [$qids['q2'] => self::STALE_ANSWER]);
        $this->assertSame(1, $submission->previous_page_action($form, $userid));
        $this->assertSame([self::STALE_ANSWER], $this->text_answers($rid, $qids['q2']));

        // Page 1: change Q1 to No; Q2's page must now be skipped.
        $form = $this->page($rid, 1, [$qids['q1'] => 'n']);
        $next = $submission->next_page_action($form, $userid);

        if ($haspage3) {
            $this->assertSame(3, $next, 'Q2\'s page should be skipped once Q1 = No.');
            $final = $this->page($rid, 3, [$qids['q3'] => 'y']);
        } else {
            $this->assertFalse($next, 'Q2\'s page should be skipped once Q1 = No.');
            // The web form submits from the past-the-end page (numsections + 1).
            $final = $this->page($rid, 3);
        }

        // Submit, exactly as survey_view_builder::build_view() does.
        $rid = $submission->existing_response_action($final, $userid);
        $questionnaire->responses()->commit_submission_response($rid, $userid);
        return $rid;
    }

    /**
     * Changing Q1 from Yes to No must drop Q2's earlier answer from the submitted response
     * when another page (Q3) follows Q2. This is the reported bug.
     */
    public function test_stale_dependent_answer_removed_when_page_follows(): void {
        global $DB;
        [$questionnaire, $qids, $userid] = $this->setup_branching_questionnaire(true);

        $rid = $this->answer_yes_then_change_to_no($questionnaire, $qids, $userid);

        $this->assertEquals('y', $DB->get_field('questionnaire_response', 'complete', ['id' => $rid]));
        $this->assertEquals(
            'n',
            $DB->get_field('questionnaire_response_bool', 'choiceid', ['responseid' => $rid, 'questionid' => $qids['q1']])
        );
        $this->assertSame(
            [],
            $this->text_answers($rid, $qids['q2']),
            'Q2 depends on Q1 = Yes, so its answer must not survive Q1 being changed to No.'
        );
    }

    /**
     * Same path without Q3: the stale answer is already removed today, because the final
     * save clamps the past-the-end section to Q2's page and clears it. This is the
     * "only Q1 and Q2" observation in the bug report.
     */
    public function test_stale_dependent_answer_removed_when_dependent_is_last_page(): void {
        [$questionnaire, $qids, $userid] = $this->setup_branching_questionnaire(false);

        $rid = $this->answer_yes_then_change_to_no($questionnaire, $qids, $userid);

        $this->assertSame([], $this->text_answers($rid, $qids['q2']));
    }

    /**
     * Stale answers are only removed at submission: a dependent answer hidden by changing
     * Q1 to No is still there, ready to be redisplayed, if Q1 is changed back to Yes, and
     * it survives submission because the dependency holds again.
     */
    public function test_dependent_answer_restored_when_parent_answer_changed_back(): void {
        [$questionnaire, $qids, $userid] = $this->setup_branching_questionnaire(true);
        $submission = $questionnaire->submission();

        // Q1 = Yes, Q2 = Bug-Blue, on to Q3.
        $form = $this->page(0, 1, [$qids['q1'] => 'y']);
        $this->assertSame(2, $submission->next_page_action($form, $userid));
        $rid = (int)$form->rid;
        $form = $this->page($rid, 2, [$qids['q2'] => self::STALE_ANSWER]);
        $this->assertSame(3, $submission->next_page_action($form, $userid));

        // Back to Q1 and change it to No; Q2's page is skipped on the way forward.
        $this->assertSame(2, $submission->previous_page_action($this->page($rid, 3), $userid));
        $form = $this->page($rid, 2, [$qids['q2'] => self::STALE_ANSWER]);
        $this->assertSame(1, $submission->previous_page_action($form, $userid));
        $this->assertSame(3, $submission->next_page_action($this->page($rid, 1, [$qids['q1'] => 'n']), $userid));

        // Mid-attempt the hidden answer is kept, not cleared.
        $this->assertSame([self::STALE_ANSWER], $this->text_answers($rid, $qids['q2']));

        // Back to Q1 (skipping Q2's page) and change it back to Yes; Q2's page returns.
        $this->assertSame(1, $submission->previous_page_action($this->page($rid, 3), $userid));
        $this->assertSame(2, $submission->next_page_action($this->page($rid, 1, [$qids['q1'] => 'y']), $userid));

        // The page form is filled from the stored answers, so Q2 shows the earlier answer again.
        $this->assertSame([self::STALE_ANSWER], $this->text_answers($rid, $qids['q2']));

        // Submit with Q2 left as it was: the dependency holds, so the answer is kept.
        $form = $this->page($rid, 2, [$qids['q2'] => self::STALE_ANSWER]);
        $this->assertSame(3, $submission->next_page_action($form, $userid));
        $rid = $submission->existing_response_action($this->page($rid, 3, [$qids['q3'] => 'y']), $userid);
        $questionnaire->responses()->commit_submission_response($rid, $userid);

        $this->assertSame([self::STALE_ANSWER], $this->text_answers($rid, $qids['q2']));
    }

    /**
     * When the dependency still holds, the dependent answer is kept.
     */
    public function test_fulfilled_dependent_answer_is_kept(): void {
        [$questionnaire, $qids, $userid] = $this->setup_branching_questionnaire(true);
        $submission = $questionnaire->submission();

        $form = $this->page(0, 1, [$qids['q1'] => 'y']);
        $this->assertSame(2, $submission->next_page_action($form, $userid));
        $rid = (int)$form->rid;

        $form = $this->page($rid, 2, [$qids['q2'] => 'Red']);
        $this->assertSame(3, $submission->next_page_action($form, $userid));

        $rid = $submission->existing_response_action($this->page($rid, 3, [$qids['q3'] => 'y']), $userid);
        $questionnaire->responses()->commit_submission_response($rid, $userid);

        $this->assertSame(['Red'], $this->text_answers($rid, $qids['q2']));
    }
}
