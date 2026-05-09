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
 * Persistent record for the questionnaire_feedback table.
 *
 * Each row defines a score band within a feedback section. When a respondent's
 * score falls between minscore and maxscore, feedbacktext is shown.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_feedback';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'sectionid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'feedbacklabel' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'feedbacktext' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'feedbacktextformat' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => 1,
            ],
            'minscore' => [
                'type' => PARAM_FLOAT,
                'null' => NULL_ALLOWED,
                'default' => 0,
            ],
            'maxscore' => [
                'type' => PARAM_FLOAT,
                'null' => NULL_ALLOWED,
                'default' => 101,
            ],
        ];
    }

    /**
     * Return all feedback bands for a given section.
     *
     * @param int $sectionid
     * @return feedback_record[]
     */
    public static function get_for_section(int $sectionid, string $sort = ''): array {
        return static::get_records(['sectionid' => $sectionid], $sort);
    }

    /**
     * Bulk delete every feedback-message row belonging to the given section.
     *
     * @param int $sectionid
     * @return bool
     */
    public static function delete_for_section(int $sectionid): bool {
        global $DB;
        return $DB->delete_records(static::TABLE, ['sectionid' => $sectionid]);
    }
}
