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

namespace mod_questionnaire\local\db;

/**
 * Persistent record for the questionnaire_response table.
 *
 * Each row represents a single attempt (complete or in-progress) by a user.
 * The 'complete' field uses 'y'/'n' rather than a boolean integer.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_response';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'questionnaireid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'submitted' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'complete' => [
                'type' => PARAM_ALPHA,
                'default' => 'n',
            ],
            'grade' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'userid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Return all responses for a given questionnaire instance.
     *
     * @param int $questionnaireid
     * @return response_record[]
     */
    public static function get_for_questionnaire(int $questionnaireid): array {
        return static::get_records(['questionnaireid' => $questionnaireid]);
    }

    /**
     * Return all complete responses for a given questionnaire and user.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return response_record[]
     */
    public static function get_complete_for_user(int $questionnaireid, int $userid): array {
        return static::get_records(['questionnaireid' => $questionnaireid, 'userid' => $userid, 'complete' => 'y']);
    }
}
