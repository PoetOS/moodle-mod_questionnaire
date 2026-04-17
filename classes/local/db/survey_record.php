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
 * Persistent record for the questionnaire_survey table.
 *
 * The survey is the content container for a questionnaire. A survey can be
 * shared across multiple questionnaire activity instances (public/template surveys).
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class survey_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_survey';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'courseid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'realm' => [
                'type' => PARAM_ALPHA,
                'default' => '',
            ],
            'status' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'title' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'email' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'subtitle' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'info' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'theme' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'thankspage' => [
                'type' => PARAM_URL,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'thankhead' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'thankbody' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'feedbacksections' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => 0,
            ],
            'feedbacknotes' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'feedbackscores' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => 0,
            ],
            'charttype' => [
                'type' => PARAM_ALPHA,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Return true if a survey record with the given id exists in the database.
     *
     * @param int $id
     * @return bool
     */
    public static function record_exists($id): bool {
        return static::record_exists_select('id = ?', [$id]);
    }

    /**
     * Return all surveys with the given realm belonging to a specific course.
     *
     * @param string $realm
     * @param int $courseid
     * @return self[]
     */
    public static function get_by_realm_in_course(string $realm, int $courseid): array {
        return static::get_records(['realm' => $realm, 'courseid' => $courseid], 'name');
    }

    /**
     * Return all surveys with the given realm across all courses.
     *
     * @param string $realm
     * @return self[]
     */
    public static function get_by_realm(string $realm): array {
        return static::get_records(['realm' => $realm], 'name');
    }

    /**
     * Create a new survey record from survey data.
     *
     * @param stdClass $sdata Survey data object.
     * @return self The created record.
     */
    public static function create_from_sdata(\stdClass $sdata): self {
        return (new self(0, $sdata))->create();
    }

    /**
     * Update an existing survey record from survey data.
     *
     * @param int $sid Survey id.
     * @param stdClass $sdata Survey data object.
     * @return self The updated record.
     */
    public static function update_from_sdata(int $sid, \stdClass $sdata): self {
        $record = new self($sid, $sdata);
        $record->update();
        return $record;
    }
}
