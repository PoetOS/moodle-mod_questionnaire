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

declare(strict_types=1);

namespace mod_questionnaire\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_module;
use mod_questionnaire\questionnaire;

/**
 * External function for drag-and-drop reordering of questionnaire questions.
 *
 * @package     mod_questionnaire
 * @copyright   2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author      Mike Churchward
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_reorder extends external_api {
    /**
     * Describes the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Questionnaire course module id'),
            'itemorder' => new external_value(PARAM_SEQUENCE, 'New order, as a comma-separated sequence of question ids'),
        ]);
    }

    /**
     * Persist a new top-to-bottom ordering of a questionnaire's active questions.
     *
     * @param int $cmid
     * @param string $itemorder
     * @return array{warnings: string, reload: bool}
     */
    public static function execute(int $cmid, string $itemorder): array {
        [
            'cmid' => $cmid,
            'itemorder' => $itemorder,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'itemorder' => $itemorder,
        ]);

        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/questionnaire:editquestions', $context);

        $questionnaire = questionnaire::from_cmid($cmid);
        $orderedids = array_map('intval', explode(',', trim($itemorder, ',')));
        $result = $questionnaire->survey()->reorder_questions($orderedids);

        return [
            'warnings' => $result['message'],
            'reload' => $result['structurechanged'],
        ];
    }

    /**
     * Describes the data returned from execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'warnings' => new external_value(
                PARAM_RAW,
                'check_page_breaks() status message (only meaningful when reload is true)',
                VALUE_REQUIRED
            ),
            'reload' => new external_value(
                PARAM_BOOL,
                'True when check_page_breaks() added or removed a page break — the client\'s row list is stale and must reload',
                VALUE_REQUIRED
            ),
        ]);
    }
}
