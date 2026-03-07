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
 * Persistent record for the questionnaire_question table.
 *
 * The column 'precise' holds the precision/max value for applicable types.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_record extends \core\persistent {

    /** @var string The table name. */
    public const TABLE = 'questionnaire_question';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'surveyid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'typeid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'resultid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'length' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'precise' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'position' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'content' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'required' => [
                'type' => PARAM_ALPHA,
                'default' => 'n',
            ],
            'deleted' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'extradata' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Return all non-deleted questions for a given survey, ordered by position.
     *
     * Uses a raw SQL query because Moodle's get_records() cannot express IS NULL conditions.
     *
     * @param int $surveyid
     * @return question_record[]
     */
    public static function get_active_for_survey(int $surveyid): array {
        global $DB;
        $records = $DB->get_records_select(
            static::TABLE,
            'surveyid = :surveyid AND deleted IS NULL',
            ['surveyid' => $surveyid],
            'position ASC'
        );
        return array_map(fn($r) => new static(0, $r), $records);
    }
}
