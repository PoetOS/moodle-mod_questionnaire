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
 * Submit a questionnaire response (external function).
 *
 * @package    mod_questionnaire
 * @category   external
 * @copyright  2018 Igor Sazonov <sovletig@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use mod_questionnaire\questionnaire;

/**
 * External function: submit a questionnaire response (mobile API endpoint).
 */
class submit_questionnaire_response extends external_api {
    /**
     * Describes the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionnaireid' => new external_value(PARAM_INT, 'Questionnaire instance id'),
            'surveyid'        => new external_value(PARAM_INT, 'Survey id'),
            'userid'          => new external_value(PARAM_INT, 'User id'),
            'cmid'            => new external_value(PARAM_INT, 'Course module id'),
            'sec'             => new external_value(PARAM_INT, 'Section number'),
            'completed'       => new external_value(PARAM_INT, 'Completed survey or not'),
            'rid'             => new external_value(PARAM_INT, 'Existing response id'),
            'submit'          => new external_value(PARAM_INT, 'Submit survey or not'),
            'action'          => new external_value(PARAM_ALPHA, 'Page action'),
            'responses'       => new external_multiple_structure(
                new external_single_structure([
                    'name'  => new external_value(PARAM_RAW, 'data key'),
                    'value' => new external_value(PARAM_RAW, 'data value'),
                ]),
                'The data to be saved',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Submit questionnaire responses.
     *
     * @param int $questionnaireid The questionnaire instance id.
     * @param int $surveyid Survey id.
     * @param int $userid User id.
     * @param int $cmid Course module id.
     * @param int $sec Section number.
     * @param int $completed Completed survey 1/0.
     * @param int $rid Already in progress response id.
     * @param int $submit Submit survey?
     * @param string $action
     * @param array $responses The response ids.
     * @return array Answers information and warnings.
     */
    public static function execute(
        int $questionnaireid,
        int $surveyid,
        int $userid,
        int $cmid,
        int $sec,
        int $completed,
        int $rid,
        int $submit,
        string $action,
        array $responses
    ): array {
        self::validate_parameters(
            self::execute_parameters(),
            [
                'questionnaireid' => $questionnaireid,
                'surveyid'        => $surveyid,
                'userid'          => $userid,
                'cmid'            => $cmid,
                'sec'             => $sec,
                'completed'       => $completed,
                'rid'             => $rid,
                'submit'          => $submit,
                'action'          => $action,
                'responses'       => $responses,
            ]
        );

        $questionnaire = questionnaire::from_cmid($cmid);

        self::validate_context($questionnaire->context());

        require_capability('mod/questionnaire:submit', $questionnaire->context());

        $result = $questionnaire->save_mobile_data($userid, $sec, $completed, $rid, $submit, $action, $responses);
        $result['submitted'] = true;
        if (isset($result['warnings']) && !empty($result['warnings'])) {
            unset($result['responses']);
            $result['submitted'] = false;
        }
        $result['warnings'] = [];
        return $result;
    }

    /**
     * Describes the execute() return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'submitted' => new external_value(PARAM_BOOL, 'submitted', VALUE_REQUIRED, false, false),
            'warnings'  => new external_warnings(),
        ]);
    }
}
