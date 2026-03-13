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
 * Persistent record for the questionnaire_question_type table.
 *
 * This is a static lookup table populated at install time. The typeid column
 * is the semantic identifier used throughout the plugin (not the auto-increment id).
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_type_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_question_type';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'typeid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'type' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'haschoices' => [
                'type' => PARAM_ALPHA,
                'default' => 'y',
            ],
            'responsetable' => [
                'type' => PARAM_ALPHANUMEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Return the question type record for a given typeid.
     *
     * @param int $typeid
     * @return question_type_record|false
     */
    public static function get_by_typeid(int $typeid) {
        $records = static::get_records(['typeid' => $typeid]);
        return !empty($records) ? reset($records) : false;
    }
}
