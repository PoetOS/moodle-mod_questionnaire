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
 * Unit tests for mod_questionnaire\local\feedback\feedback_section_form.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\feedback\feedback_section_form.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\feedback_section_form
 */
final class feedback_section_form_test extends \advanced_testcase {
    /**
     * Global mode heads the form with the global heading and has no question fields.
     */
    public function test_global_mode_has_no_question_fields(): void {
        [$form, $questionnaire] = $this->create_form(1);
        $mform = $this->mform($form);

        $this->assertEquals($questionnaire->context(), $form->context);
        $this->assertEquals(get_string('feedbackglobalheading', 'questionnaire'), $mform->getElement('contenthdr')->_text);
        $this->assertFalse($mform->elementExists('questionshdr'));
        // Three empty ranges are offered to start with.
        $this->assertTrue($mform->elementExists('feedbackboundaries[2]'));
        $this->assertFalse($mform->elementExists('feedbackboundaries[3]'));
    }

    /**
     * Sections mode offers every eligible question, ticked with its weight when the section uses it.
     */
    public function test_sections_mode_lists_eligible_questions(): void {
        [$form, , , $qids] = $this->create_form(2, [0 => 0.5]);
        $mform = $this->mform($form);

        $used = $mform->getElement("questiongroup_{$qids[0]}")->getElements();
        $unused = $mform->getElement("questiongroup_{$qids[1]}")->getElements();
        $this->assertEquals(1, $used[0]->getValue());
        $this->assertEquals('0.5', $used[2]->getValue());
        $this->assertEquals(0, $unused[0]->getValue());
        // An unused question shows the weight scoring would give it.
        $this->assertEquals('1', $unused[2]->getValue());
    }

    /**
     * Existing ranges load their boundaries, with the bottom range's boundary left empty.
     */
    public function test_set_data_loads_ranges(): void {
        [, , $section] = $this->create_form(1);
        $this->add_range($section, 50, 101, '<p>High</p>');
        $this->add_range($section, 0, 50, '<p>Low</p>');
        [$form] = $this->rebuild_form($section);

        $form->set_data([]);
        $mform = $this->mform($form);

        $this->assertEquals('50', $mform->getElementValue('feedbackboundaries[0]'));
        $this->assertEquals('', $mform->getElementValue('feedbackboundaries[1]'));
        $this->assertStringContainsString('High', $mform->getElementValue('feedbacktext[0]')['text']);
    }

    /**
     * Each range starts where the one above it ends.
     */
    public function test_definition_after_data_sets_range_starts(): void {
        [, , $section] = $this->create_form(1);
        $this->add_range($section, 60, 101, '<p>High</p>');
        $this->add_range($section, 0, 60, '<p>Low</p>');
        [$form] = $this->rebuild_form($section);
        $form->set_data([]);

        $form->definition_after_data();
        $mform = $this->mform($form);

        $this->assertStringContainsString('>100%<', $mform->getElement('rangestart[0]')->_text);
        $this->assertStringContainsString('>60%<', $mform->getElement('rangestart[1]')->_text);
        $this->assertStringContainsString('>…<', $mform->getElement('rangestart[2]')->_text);
    }

    /**
     * Valid ranges step down from 100%; the last one may leave its boundary empty, and unused rows are ignored.
     */
    public function test_validation_accepts_descending_ranges(): void {
        [$form] = $this->create_form(1);

        $errors = $form->validation($this->range_data(['50%', '20', '', ''], ['A', 'B', 'C', '']), []);

        $this->assertSame([], $errors);
    }

    /**
     * Boundaries that aren't numbers, are out of range, out of order or missing are each reported on their row.
     */
    public function test_validation_rejects_bad_boundaries(): void {
        [$form] = $this->create_form(1);

        $errors = $form->validation(
            $this->range_data(['abc', '150', '', '30', '40', ''], ['A', 'B', 'C', 'D', 'E', 'F']),
            []
        );

        $this->assertSame(get_string('feedbackerrorboundaryformat', 'questionnaire'), $errors['feedbackboundaries[0]']);
        $this->assertSame(get_string('feedbackerrorboundaryoutofrange', 'questionnaire'), $errors['feedbackboundaries[1]']);
        $this->assertSame(get_string('feedbackerrorboundarymissing', 'questionnaire'), $errors['feedbackboundaries[2]']);
        $this->assertSame(get_string('feedbackerrororder', 'questionnaire'), $errors['feedbackboundaries[4]']);
        $this->assertArrayNotHasKey('feedbackboundaries[3]', $errors);
        $this->assertArrayNotHasKey('feedbackboundaries[5]', $errors);
    }

    /**
     * An included question needs a weight above 0 and at most 1; excluded questions aren't checked.
     */
    public function test_validation_checks_included_weights(): void {
        [$form] = $this->create_form(2);
        $data = $this->range_data([''], ['A']);
        $data['include'] = [11 => 1, 12 => 1, 13 => 1, 14 => 0];
        $data['weight'] = [11 => '0', 12 => '1.5', 13 => '0.25', 14 => 'x'];

        $errors = $form->validation($data, []);

        $this->assertArrayHasKey('questiongroup_11', $errors);
        $this->assertArrayHasKey('questiongroup_12', $errors);
        $this->assertArrayNotHasKey('questiongroup_13', $errors);
        $this->assertArrayNotHasKey('questiongroup_14', $errors);
    }

    /**
     * Boundaries are read as numbers with an optional trailing '%'.
     */
    public function test_parse_boundary(): void {
        $this->assertSame(50.0, feedback_section_form::parse_boundary('50'));
        $this->assertSame(12.5, feedback_section_form::parse_boundary(' 12.5 % '));
        $this->assertNull(feedback_section_form::parse_boundary(''));
        $this->assertNull(feedback_section_form::parse_boundary('abc%'));
    }

    /**
     * Build a section form for a questionnaire with two eligible questions.
     *
     * @param int $mode The feedback mode (1 global, 2 sections).
     * @param array $weights Weights keyed by question position, for the section's scorecalculation.
     * @return array The form, questionnaire, section and question ids.
     */
    private function create_form(int $mode, array $weights = []): array {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        foreach (['Q1', 'Q2'] as $name) {
            $generator->create_question($questionnaire, [
                'surveyid' => $questionnaire->surveyid(),
                'name' => $name,
                'typeid' => QUESYESNO,
                'content' => $name,
                'required' => 'y',
            ]);
        }
        $questionnaire->feedback()->update_settings(['feedbacksections' => $mode]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $PAGE->set_context($questionnaire->context());

        $qids = array_keys($questionnaire->questions());
        $section = section::new_section($questionnaire->surveyid(), 'Section');
        if ($weights) {
            $calculation = [];
            foreach ($weights as $position => $weight) {
                $calculation[$qids[$position]] = $weight;
            }
            $section->set_new_scorecalculation($calculation);
        }
        [$form, $section] = $this->rebuild_form($section, $questionnaire);
        return [$form, $questionnaire, $section, $qids];
    }

    /**
     * Reload the section and build its form.
     *
     * @param section $section
     * @param questionnaire|null $questionnaire Defaults to the section's questionnaire.
     * @return array The form and reloaded section.
     */
    private function rebuild_form(section $section, ?questionnaire $questionnaire = null): array {
        global $DB;
        if ($questionnaire === null) {
            $instanceid = $DB->get_field('questionnaire', 'id', ['sid' => $section->surveyid]);
            $questionnaire = questionnaire::from_instanceid($instanceid);
        }
        $section = $questionnaire->feedback()->section($section->id);
        $form = new feedback_section_form('fbsections.php', ['questionnaire' => $questionnaire, 'section' => $section]);
        return [$form, $section];
    }

    /**
     * Add a range to a section.
     *
     * @param section $section
     * @param float $min
     * @param float $max
     * @param string $text
     */
    private function add_range(section $section, float $min, float $max, string $text): void {
        sectionfeedback::new_sectionfeedback((object)[
            'sectionid' => $section->id, 'feedbacklabel' => null, 'feedbacktext' => $text,
            'feedbacktextformat' => FORMAT_HTML, 'minscore' => $min, 'maxscore' => $max,
        ]);
    }

    /**
     * Form data for a list of ranges.
     *
     * @param string[] $boundaries
     * @param string[] $texts
     * @return array
     */
    private function range_data(array $boundaries, array $texts): array {
        return [
            'sectionlabel' => 'Section',
            'feedbackboundaries' => $boundaries,
            'feedbacktext' => array_map(fn($text) => ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => 0], $texts),
        ];
    }

    /**
     * The form's underlying quickform.
     *
     * @param feedback_section_form $form
     * @return \MoodleQuickForm
     */
    private function mform(feedback_section_form $form): \MoodleQuickForm {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }
}
