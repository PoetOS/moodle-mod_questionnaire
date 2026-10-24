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

use mod_questionnaire\questionnaire;

/**
 * Controller for the feedback.php save / redirect flow.
 *
 * Owns the conversion from feedback form data into an explicit allowlist of
 * feedback settings, the saving of the feedbacknotes editor draft area, and
 * the redirect to the section editor. Domain validation of the field set
 * lives on {@see feedback::update_settings()}; section bootstrap lives on
 * {@see feedback::ensure_first_section()}.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_settings_controller {
    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The active questionnaire.
     */
    public function __construct(
        /** @var questionnaire The active questionnaire. */
        private readonly questionnaire $questionnaire,
    ) {
    }

    /**
     * Persist feedback settings from parsed form data.
     *
     * Writes the feedbacknotes draft area and applies the field set via
     * {@see feedback::update_settings()}. Throws if the underlying update
     * rejects the changes.
     *
     * @param \stdClass $formdata Validated form data returned by feedback_form::get_data().
     * @return int Survey id on success.
     */
    public function save(\stdClass $formdata): int {
        $fields = [
            'feedbackscores'   => isset($formdata->feedbackscores) ? (int) $formdata->feedbackscores : 0,
            'feedbacknotes'    => $this->save_feedbacknotes($formdata),
            'feedbacksections' => isset($formdata->feedbacksections) ? (int) $formdata->feedbacksections : 0,
        ];

        if ($fields['feedbacksections'] > 0 && get_config('questionnaire', 'usergraph')) {
            $charttype = $this->extract_charttype($formdata, $fields['feedbacksections']);
            if ($charttype !== null) {
                $fields['charttype'] = $charttype;
            }
        }

        $result = $this->questionnaire->feedback()->update_settings($fields);
        if ($result === false) {
            throw new \moodle_exception('couldnotcreatenewsurvey', 'mod_questionnaire');
        }
        return $result;
    }

    /**
     * Forward to {@see feedback::ensure_first_section()} for use by the
     * "Save settings and edit Feedback Sections" path in feedback.php.
     *
     * @return int Section id (0 if no sections exist and no feedback is configured).
     */
    public function ensure_first_section(): int {
        return $this->questionnaire->feedback()->ensure_first_section();
    }

    /**
     * Save the feedbacknotes editor draft area and return the processed HTML body.
     *
     * Empty / missing editor data resolves to an empty string so the survey field
     * is positively cleared instead of being left stale.
     *
     * @param \stdClass $formdata
     * @return string
     */
    private function save_feedbacknotes(\stdClass $formdata): string {
        if (!isset($formdata->feedbacknotes) || !is_array($formdata->feedbacknotes)) {
            return '';
        }
        return file_save_draft_area_files(
            (int) $formdata->feedbacknotes['itemid'],
            $this->questionnaire->context()->id,
            'mod_questionnaire',
            'feedbacknotes',
            $this->questionnaire->surveyid(),
            ['subdirs' => true],
            $formdata->feedbacknotes['text']
        );
    }

    /**
     * Resolve which charttype the form picked given the number of sections.
     *
     * @param \stdClass $formdata
     * @param int $sections
     * @return string|null
     */
    private function extract_charttype(\stdClass $formdata, int $sections): ?string {
        return match (true) {
            $sections === 1 => $formdata->chart_type_global ?? null,
            $sections === 2 => $formdata->chart_type_two_sections ?? null,
            $sections > 2 => $formdata->chart_type_sections ?? null,
            default => null,
        };
    }
}
