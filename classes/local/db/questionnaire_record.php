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
 * Persistent record for the questionnaire table (the activity instance).
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_record extends \core\persistent {

    /** @var string The table name. */
    public const TABLE = 'questionnaire';

    /**
     * Define the properties of this record.
     *
     * Property names map directly to DB column names. Types use Moodle PARAM_* constants.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'course' => [
                'type' => PARAM_INT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'intro' => [
                'type' => PARAM_RAW,
            ],
            'introformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_HTML,
            ],
            'qtype' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'respondenttype' => [
                'type' => PARAM_ALPHA,
                'default' => 'fullname',
            ],
            'respeligible' => [
                'type' => PARAM_ALPHA,
                'default' => 'all',
            ],
            'respview' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'notifications' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'opendate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'closedate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'resume' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'navigate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'grade' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'sid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'timemodified' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'completionsubmit' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'autonum' => [
                'type' => PARAM_INT,
                'default' => 3,
            ],
            'progressbar' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'removeafter' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Update timemodified before saving changes.
     */
    protected function before_update(): void {
        $this->raw_set('timemodified', time());
    }
}
