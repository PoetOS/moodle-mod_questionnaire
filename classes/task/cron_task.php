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

namespace mod_questionnaire\task;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');
/**
 * A schedule task for mod_questionnaire cron.
 *
 * @package   mod_questionnaire
 * @copyright 2022 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanrecylebin', 'mod_questionnaire');
    }

    /**
     * Run mod_questionnaire cron.
     */
    public function execute() {
        global $DB;
        $rangetimecrontask = questionnaire_get_range_time_permanently();
        $sql = "SELECT *
                  FROM {questionnaire_question}
                 WHERE deleted IS NOT NULL
                   AND deleted < ?";
        if ($deletequestions = $DB->get_records_sql($sql, [time() - $rangetimecrontask])) {
            foreach ($deletequestions as $question) {
                questionnaire_delete_permanently_questions($question->id, $question->surveyid);
            }
        }
    }
}
