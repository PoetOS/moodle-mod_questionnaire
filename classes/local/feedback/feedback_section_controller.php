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

namespace mod_questionnaire\local\feedback;

use mod_questionnaire\local\db\feedback_record;
use mod_questionnaire\questionnaire;

/**
 * Saves the feedback section editor (fbsections.php).
 *
 * Reads an explicit set of fields from the validated form data: the label and heading, the weights of
 * the included questions (sections mode only), and the score ranges. Existing message rows are updated
 * in place, in order, so their message file areas keep their ids; surplus rows are deleted.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_section_controller {
    /** Upper bound stored on the top range, so a score of exactly 100% falls inside it (minscore <= score < maxscore). */
    public const TOP_BOUND = 101;

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The active questionnaire.
     * @param section $section The section being edited.
     */
    public function __construct(
        /** @var questionnaire The active questionnaire. */
        private readonly questionnaire $questionnaire,
        /** @var section The section being edited. */
        private readonly section $section,
    ) {
    }

    /**
     * Persist the section from validated form data.
     *
     * @param \stdClass $formdata Data returned by feedback_section_form::get_data().
     */
    public function save(\stdClass $formdata): void {
        $section = $this->section;
        $section->sectionlabel = $formdata->sectionlabel;
        if (isset($formdata->sectionheading) && is_array($formdata->sectionheading)) {
            $section->sectionheading = file_save_draft_area_files(
                (int) $formdata->sectionheading['itemid'],
                $this->questionnaire->context()->id,
                'mod_questionnaire',
                'sectionheading',
                $section->id,
                ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
                $formdata->sectionheading['text']
            );
            $section->sectionheadingformat = $formdata->sectionheading['format'];
        }
        if ($this->questionnaire->feedback()->mode() > 1) {
            $section->scorecalculation = $this->weights($formdata);
        }
        $this->save_ranges($formdata);
        $section->update();
    }

    /**
     * The weight of each included question, in question order.
     *
     * @param \stdClass $formdata
     * @return array Weights keyed by question id.
     */
    private function weights(\stdClass $formdata): array {
        $weights = [];
        foreach ($this->questionnaire->questions() as $question) {
            $qid = $question->id();
            if ($question->valid_feedback() && !empty($formdata->include[$qid])) {
                $weights[$qid] = (float) unformat_float($formdata->weight[$qid] ?? '1');
            }
        }
        return $weights;
    }

    /**
     * Save the ranges: each one runs from the previous boundary (or the top) down to its own boundary
     * (or 0% for the last one). Unused ranges at the bottom are dropped.
     *
     * @param \stdClass $formdata
     */
    private function save_ranges(\stdClass $formdata): void {
        $rows = [];
        foreach ($formdata->feedbacktext ?? [] as $i => $editor) {
            $boundary = feedback_section_form::parse_boundary((string) ($formdata->feedbackboundaries[$i] ?? ''));
            $rows[] = ['editor' => $editor, 'boundary' => $boundary];
        }
        while (!empty($rows)) {
            $last = end($rows);
            if ($last['boundary'] !== null || trim(strip_tags($last['editor']['text'] ?? '')) !== '') {
                break;
            }
            array_pop($rows);
        }

        $existing = array_values($this->section->sectionfeedback);
        $saved = [];
        $max = self::TOP_BOUND;
        foreach ($rows as $k => $row) {
            $min = ($k === array_key_last($rows) || $row['boundary'] === null) ? 0 : $row['boundary'];
            $feedback = $existing[$k] ?? sectionfeedback::new_sectionfeedback((object)[
                'sectionid' => $this->section->id,
                'feedbacklabel' => null,
                'feedbacktext' => '',
                'feedbacktextformat' => FORMAT_HTML,
                'minscore' => $min,
                'maxscore' => $max,
            ]);
            $feedback->minscore = $min;
            $feedback->maxscore = $max;
            $feedback->feedbacktextformat = $row['editor']['format'] ?? FORMAT_HTML;
            $feedback->feedbacktext = file_save_draft_area_files(
                (int) ($row['editor']['itemid'] ?? 0),
                $this->questionnaire->context()->id,
                'mod_questionnaire',
                'feedback',
                $feedback->id,
                ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
                $row['editor']['text'] ?? ''
            );
            $saved[$feedback->id] = $feedback;
            $max = $min;
        }

        $fs = get_file_storage();
        foreach (array_slice($existing, count($rows)) as $surplus) {
            $fs->delete_area_files($this->questionnaire->context()->id, 'mod_questionnaire', 'feedback', $surplus->id);
            (new feedback_record($surplus->id))->delete();
        }
        $this->section->sectionfeedback = $saved;
    }
}
