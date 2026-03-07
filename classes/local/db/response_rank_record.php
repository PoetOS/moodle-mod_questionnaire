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
 * Persistent record for the questionnaire_response_rank table.
 *
 * Each row holds the rank value assigned to one choice in a rate/rank question.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_rank_record extends \core\persistent {

    /** @var string The table name. */
    public const TABLE = 'questionnaire_response_rank';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'responseid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'questionid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'choiceid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'rankvalue' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Return all rank answers for a given response.
     *
     * @param int $responseid
     * @return response_rank_record[]
     */
    public static function get_for_response(int $responseid): array {
        return static::get_records(['responseid' => $responseid]);
    }
}
