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
 * Feedback domain object.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\feedback\section;

/**
 * Feedback domain object.
 *
 * Wraps the feedback-related state that lives on the `questionnaire_survey`
 * table (feedbacknotes, feedbacksections, feedbackscores, charttype) plus
 * the `questionnaire_fb_sections` collection. The `update_settings` write
 * path owns its own allowlist; the `response_analysis` extraction is
 * deferred to a later phase.
 *
 * @see project-feedback-domain-class-plan
 */
class feedback {
    /** @var string[] Survey persistent fields owned by the feedback concern. */
    public const UPDATABLE_FIELDS = [
        'feedbacknotes', 'feedbacksections', 'feedbackscores', 'charttype',
    ];

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The owning questionnaire.
     */
    public function __construct(
        /** @var questionnaire The owning questionnaire. */
        private readonly questionnaire $questionnaire,
    ) {
    }

    /**
     * True if feedback is enabled (any sections configured).
     *
     * @return bool
     */
    public function enabled(): bool {
        return $this->mode() > 0;
    }

    /**
     * Number of feedback sections — 0 off, 1 single global, N sectioned.
     *
     * Stored under feedbacksections on questionnaire_survey.
     *
     * @return int
     */
    public function mode(): int {
        return $this->questionnaire->survey()->feedbacksections();
    }

    /**
     * True if the per-section feedback score table should be shown.
     *
     * Stored under feedbackscores on questionnaire_survey.
     *
     * @return bool
     */
    public function show_scores(): bool {
        return $this->questionnaire->survey()->feedbackscores();
    }

    /**
     * Raw HTML for the feedback notes editor.
     *
     * @return string
     */
    public function notes(): string {
        return $this->questionnaire->survey()->feedbacknotes();
    }

    /**
     * Feedback notes with pluginfile URLs rewritten for display.
     *
     * @return string Empty string when notes() is empty; the rewritten HTML otherwise.
     */
    public function rendered_notes(): string {
        $notes = $this->notes();
        if ($notes === '') {
            return '';
        }
        return file_rewrite_pluginfile_urls(
            $notes,
            'pluginfile.php',
            $this->questionnaire->context()->id,
            'mod_questionnaire',
            'feedbacknotes',
            $this->questionnaire->surveyid()
        );
    }

    /**
     * Chart type for per-section feedback rendering — '' when off.
     *
     * Stored under charttype on questionnaire_survey. Empty string when
     * the user disabled the chart or when the site usergraph setting is
     * off.
     *
     * @return string
     */
    public function chart_type(): string {
        return $this->questionnaire->survey()->charttype();
    }

    /**
     * True if any question on the survey is a valid feedback question
     * (rate, yesno, slider — anything where valid_feedback() returns true).
     *
     * @return bool
     */
    public function has_any_feedback_questions(): bool {
        foreach ($this->questionnaire->questions() as $question) {
            if ($question->valid_feedback()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update feedback-domain fields on the survey row.
     *
     * Only keys in self::UPDATABLE_FIELDS are accepted; passing anything else
     * throws a coding_exception so form metadata cannot be smuggled onto the
     * persistent. The actual write goes through {@see survey::update_settings()}
     * with the feedback allowlist as the package-private override.
     *
     * @param array $fields Map of feedback field name => value.
     * @return int|false Survey id on success, false on validation failure.
     */
    public function update_settings(array $fields): int|false {
        foreach (array_keys($fields) as $key) {
            if (!in_array($key, self::UPDATABLE_FIELDS, true)) {
                throw new \coding_exception("feedback::update_settings: field '{$key}' is not updatable");
            }
        }
        return $this->questionnaire->survey()->update_settings($fields, self::UPDATABLE_FIELDS);
    }

    /**
     * Ensure a first feedback section exists for the survey.
     *
     * Used by the "Save settings and edit Feedback Sections" path so the
     * user always lands on a real section editor. Returns the id of the
     * section to redirect to (existing first section, or newly created one).
     *
     * @return int Section id (0 if no sections exist and no feedback is configured).
     */
    public function ensure_first_section(): int {
        global $DB;
        $surveyid = $this->questionnaire->surveyid();
        $firstsection = (int) ($DB->get_field(
            'questionnaire_fb_sections',
            'MIN(section)',
            ['surveyid' => $surveyid]
        ) ?: 0);

        $mode = $this->mode();
        if ($mode > 0 && $firstsection === 0) {
            $label = ($mode === 1)
                ? get_string('feedbackglobal', 'questionnaire')
                : get_string('feedbackdefaultlabel', 'questionnaire');
            section::new_section($surveyid, $label);
        }
        return $firstsection;
    }
}
