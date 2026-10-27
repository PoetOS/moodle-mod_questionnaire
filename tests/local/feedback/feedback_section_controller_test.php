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
 * Unit tests for mod_questionnaire\local\feedback\feedback_section_controller.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

use mod_questionnaire\local\db\feedback_record;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\feedback\feedback_section_controller.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\feedback_section_controller
 */
final class feedback_section_controller_test extends \advanced_testcase {
    /**
     * Saving stores the label and turns the ranges into min/max score bands from the top down to 0%.
     */
    public function test_save_stores_label_and_ranges(): void {
        [$questionnaire, $section] = $this->setup_section(1);

        $this->save($questionnaire, $section, ['50%', '20', ''], ['<p>High</p>', '<p>Mid</p>', '<p>Low</p>']);

        $saved = $questionnaire->feedback()->section($section->id);
        $this->assertSame('New label', $saved->sectionlabel);
        $bands = array_map(
            fn($feedback) => [(float) $feedback->minscore, (float) $feedback->maxscore, $feedback->feedbacktext],
            array_values($saved->sectionfeedback)
        );
        $this->assertEquals([
            [50.0, (float) feedback_section_controller::TOP_BOUND, '<p>High</p>'],
            [20.0, 50.0, '<p>Mid</p>'],
            [0.0, 20.0, '<p>Low</p>'],
        ], $bands);
    }

    /**
     * Empty rows below the last used range are not stored.
     */
    public function test_save_drops_unused_rows(): void {
        [$questionnaire, $section] = $this->setup_section(1);

        $this->save($questionnaire, $section, ['40', '', ''], ['<p>Top</p>', '<p>Rest</p>', '']);

        $this->assertCount(2, feedback_record::get_for_section($section->id));
    }

    /**
     * Saving again updates the existing rows in place, keeping their ids, and deletes rows no longer needed.
     */
    public function test_save_updates_rows_in_place(): void {
        [$questionnaire, $section] = $this->setup_section(1);
        $this->save($questionnaire, $section, ['70', '30', ''], ['<p>A</p>', '<p>B</p>', '<p>C</p>']);
        $before = array_keys($questionnaire->feedback()->section($section->id)->sectionfeedback);

        $this->save(
            $questionnaire,
            $questionnaire->feedback()->section($section->id),
            ['60', ''],
            ['<p>A2</p>', '<p>B2</p>']
        );

        $after = $questionnaire->feedback()->section($section->id)->sectionfeedback;
        $this->assertSame(array_slice($before, 0, 2), array_keys($after));
        $this->assertSame(['<p>A2</p>', '<p>B2</p>'], array_column(array_values($after), 'feedbacktext'));
        $this->assertFalse(feedback_record::get_record(['id' => $before[2]]));
    }

    /**
     * In sections mode the included questions' weights are saved, in question order; excluded ones are dropped.
     */
    public function test_save_stores_weights_in_sections_mode(): void {
        [$questionnaire, $section, $qids] = $this->setup_section(2);

        $this->save($questionnaire, $section, [''], ['<p>All</p>'], [
            'include' => [$qids[0] => 1, $qids[1] => 0],
            'weight' => [$qids[0] => '0.25', $qids[1] => '1'],
        ]);

        $this->assertEquals([$qids[0] => 0.25], $questionnaire->feedback()->section($section->id)->scorecalculation);
    }

    /**
     * Global mode leaves the section's question weights alone.
     */
    public function test_save_keeps_weights_in_global_mode(): void {
        [$questionnaire, $section, $qids] = $this->setup_section(1);
        $section->set_new_scorecalculation([$qids[1] => 0.5]);

        $this->save($questionnaire, $section, [''], ['<p>All</p>'], ['include' => [$qids[0] => 1], 'weight' => [$qids[0] => '1']]);

        $this->assertEquals([$qids[1] => 0.5], $questionnaire->feedback()->section($section->id)->scorecalculation);
    }

    /**
     * Save the section with the given ranges.
     *
     * @param questionnaire $questionnaire
     * @param section $section
     * @param string[] $boundaries
     * @param string[] $texts
     * @param array $extra Further form fields.
     */
    private function save(
        questionnaire $questionnaire,
        section $section,
        array $boundaries,
        array $texts,
        array $extra = []
    ): void {
        $formdata = (object) array_merge([
            'sectionlabel' => 'New label',
            'feedbackboundaries' => $boundaries,
            'feedbacktext' => array_map(fn($text) => ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => 0], $texts),
        ], $extra);
        (new feedback_section_controller($questionnaire, $section))->save($formdata);
    }

    /**
     * A questionnaire with two eligible questions, in the given feedback mode, and one section.
     *
     * @param int $mode
     * @return array The questionnaire, section and question ids.
     */
    private function setup_section(int $mode): array {
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
        $section = section::new_section($questionnaire->surveyid(), 'Section');
        return [$questionnaire, $questionnaire->feedback()->section($section->id), array_keys($questionnaire->questions())];
    }
}
