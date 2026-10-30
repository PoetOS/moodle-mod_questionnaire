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
 * Helper functions for the questionnaire upgrade steps.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Move the question recycle-bin duration from 'questionnaire_questiondeletion/duration' to
 * 'questionnaire/questiondeletionduration', so it is stored and removed with the plugin's own settings.
 */
function questionnaire_upgrade_move_question_deletion_duration(): void {
    $duration = get_config('questionnaire_questiondeletion', 'duration');
    if ($duration !== false) {
        set_config('questiondeletionduration', $duration, 'questionnaire');
        unset_config('duration', 'questionnaire_questiondeletion');
    }
}

/**
 * The text fields that carry a trust flag, as table => [text field => trust field].
 *
 * @return array
 */
function questionnaire_upgrade_trust_fields(): array {
    return [
        'questionnaire_survey' => ['info' => 'infotrust', 'thankbody' => 'thankbodytrust', 'feedbacknotes' => 'feedbacknotestrust'],
        'questionnaire_question' => ['content' => 'contenttrust'],
        'questionnaire_fb_sections' => ['sectionheading' => 'sectionheadingtrust'],
        'questionnaire_feedback' => ['feedbacktext' => 'feedbacktexttrust'],
    ];
}

/**
 * Mark all existing staff-authored text as trusted.
 *
 * Before trust flags existed this text was always shown unfiltered, and only users with RISK_XSS capabilities could
 * write it. Marking it trusted keeps it unfiltered on sites that enable trusted content; elsewhere it is now cleaned.
 */
function questionnaire_upgrade_trust_existing_content(): void {
    global $DB;
    foreach (questionnaire_upgrade_trust_fields() as $table => $fields) {
        foreach ($fields as $trustfield) {
            $DB->set_field($table, $trustfield, 1);
        }
    }
}
