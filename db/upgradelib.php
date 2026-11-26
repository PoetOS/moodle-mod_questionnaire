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

/**
 * Delete answer rows saved without a response, along with any files uploaded with them.
 *
 * Such rows cannot be traced back to the user who gave them, so they are removed.
 */
function questionnaire_upgrade_delete_unowned_answers(): void {
    global $DB;

    // Each file answer records the stored file it saved; only delete a file that points back at that answer.
    $fs = get_file_storage();
    $rows = $DB->get_recordset('questionnaire_response_file', ['responseid' => 0], '', 'id, fileid');
    foreach ($rows as $row) {
        $file = empty($row->fileid) ? false : $fs->get_file_by_id($row->fileid);
        if (
            $file && ($file->get_component() === 'mod_questionnaire') &&
            ($file->get_filearea() === 'response_file') && ((int)$file->get_itemid() === (int)$row->id)
        ) {
            $fs->delete_area_files($file->get_contextid(), 'mod_questionnaire', 'response_file', $row->id);
        }
    }
    $rows->close();

    $tables = [
        'questionnaire_response_bool',
        'questionnaire_response_date',
        'questionnaire_resp_multiple',
        'questionnaire_response_other',
        'questionnaire_response_rank',
        'questionnaire_resp_single',
        'questionnaire_response_text',
        'questionnaire_response_file',
    ];
    foreach ($tables as $table) {
        $DB->delete_records($table, ['responseid' => 0]);
    }
}
