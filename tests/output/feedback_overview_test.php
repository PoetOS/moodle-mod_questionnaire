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
 * Unit tests for mod_questionnaire\output\feedback_overview.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\local\feedback\section;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\output\feedback_overview.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\feedback_overview
 */
final class feedback_overview_test extends \advanced_testcase {
    /**
     * With no question that can be used for feedback, only the empty state is shown.
     */
    public function test_empty_state_without_eligible_questions(): void {
        $questionnaire = $this->create_questionnaire(false);

        $data = $this->export($questionnaire);

        $this->assertTrue($data->noeligible);
        $this->assertStringContainsString('Feedback options are available', $data->eligiblehelp);
        $this->assertStringContainsString('/mod/questionnaire/questions.php', $data->questionsurl);
        $this->assertFalse($data->showsections);
    }

    /**
     * With feedback turned off, no sections are listed.
     */
    public function test_no_sections_when_feedback_off(): void {
        $questionnaire = $this->create_questionnaire();

        $data = $this->export($questionnaire);

        $this->assertFalse($data->noeligible);
        $this->assertFalse($data->showsections);
    }

    /**
     * Sections mode lists every section with its counts, edit and delete links, and offers adding.
     */
    public function test_sections_mode_lists_sections(): void {
        $questionnaire = $this->create_questionnaire();
        $questionnaire->feedback()->update_settings(['feedbacksections' => 2]);
        $qid = array_key_first($questionnaire->questions());
        $first = section::new_section($questionnaire->surveyid(), 'First');
        $first->set_new_scorecalculation([$qid => 1]);
        $first->load_sectionfeedback((object)[
            'sectionid' => $first->id, 'feedbacklabel' => null, 'feedbacktext' => '<p>High</p>',
            'feedbacktextformat' => FORMAT_HTML, 'minscore' => 50, 'maxscore' => 101,
        ]);
        $first->load_sectionfeedback((object)[
            'sectionid' => $first->id, 'feedbacklabel' => null, 'feedbacktext' => '<p></p>',
            'feedbacktextformat' => FORMAT_HTML, 'minscore' => 0, 'maxscore' => 50,
        ]);
        section::new_section($questionnaire->surveyid(), 'Second');

        $data = $this->export(questionnaire::from_instanceid($questionnaire->id()));

        $this->assertTrue($data->showsections);
        $this->assertFalse($data->isglobal);
        $this->assertTrue($data->hassections);
        $this->assertTrue($data->canadd);
        $this->assertNull($data->unusednotice);
        $this->assertSame(['First', 'Second'], array_column($data->sections, 'label'));
        $this->assertSame(1, $data->sections[0]->questions);
        // The empty second message doesn't count.
        $this->assertSame(1, $data->sections[0]->messages);
        $this->assertStringContainsString('sectionid=' . $first->id, $data->sections[0]->editurl);
        $this->assertStringContainsString('action=confirmdeletesection', $data->sections[0]->deleteurl);
    }

    /**
     * Global mode lists only the first section, without delete or add, and notes sections kept from sections mode.
     */
    public function test_global_mode_lists_first_section_only(): void {
        $questionnaire = $this->create_questionnaire();
        $questionnaire->feedback()->update_settings(['feedbacksections' => 1]);
        section::new_section($questionnaire->surveyid(), 'Global');
        section::new_section($questionnaire->surveyid(), 'Leftover');

        $data = $this->export(questionnaire::from_instanceid($questionnaire->id()));

        $this->assertTrue($data->isglobal);
        $this->assertSame(['Global'], array_column($data->sections, 'label'));
        $this->assertSame(get_string('feedbackallquestions', 'questionnaire'), $data->sections[0]->questions);
        $this->assertNull($data->sections[0]->deleteurl);
        $this->assertFalse($data->canadd);
        $this->assertSame(get_string('feedbackunusedsections', 'questionnaire', 1), $data->unusednotice);
    }

    /**
     * Global mode with no section yet offers to add one, labelled for global feedback.
     */
    public function test_global_mode_without_section_offers_add(): void {
        $questionnaire = $this->create_questionnaire();
        $questionnaire->feedback()->update_settings(['feedbacksections' => 1]);

        $data = $this->export(questionnaire::from_instanceid($questionnaire->id()));

        $this->assertFalse($data->hassections);
        $this->assertTrue($data->canadd);
        $this->assertSame(get_string('feedbackglobal', 'questionnaire'), $data->defaultlabel);
    }

    /**
     * The template renders the section rows.
     */
    public function test_renders_section_rows(): void {
        global $PAGE;
        $questionnaire = $this->create_questionnaire();
        $questionnaire->feedback()->update_settings(['feedbacksections' => 2]);
        section::new_section($questionnaire->surveyid(), 'Extraversion');

        $html = $PAGE->get_renderer('mod_questionnaire')
            ->render(new feedback_overview(questionnaire::from_instanceid($questionnaire->id())));

        $this->assertStringContainsString('Extraversion', $html);
        $this->assertStringContainsString('name="action" value="addsection"', $html);
    }

    /**
     * Create a questionnaire, optionally with a question that can be used for feedback.
     *
     * @param bool $eligible Add a required yes/no question.
     * @return questionnaire
     */
    private function create_questionnaire(bool $eligible = true): questionnaire {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        if ($eligible) {
            $generator->create_question($questionnaire, [
                'surveyid' => $questionnaire->surveyid(),
                'name' => 'Q1',
                'typeid' => QUESYESNO,
                'content' => 'Y/N',
                'required' => 'y',
            ]);
        }
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $PAGE->set_context($questionnaire->context());
        return $questionnaire;
    }

    /**
     * Export the overview for a questionnaire.
     *
     * @param questionnaire $questionnaire
     * @return \stdClass
     */
    private function export(questionnaire $questionnaire): \stdClass {
        global $PAGE;
        return (new feedback_overview($questionnaire))->export_for_template($PAGE->get_renderer('mod_questionnaire'));
    }
}
