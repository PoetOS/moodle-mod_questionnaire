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
 * Persistent record for the questionnaire_response_file table.
 *
 * Links a response/question pair to an uploaded file stored in the Moodle
 * files table. The fileid is a foreign key into {files}.id.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_file_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_response_file';

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
            'fileid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Return all file answers for a given response.
     *
     * @param int $responseid
     * @return response_file_record[]
     */
    public static function get_for_response(int $responseid): array {
        return static::get_records(['responseid' => $responseid]);
    }
}
