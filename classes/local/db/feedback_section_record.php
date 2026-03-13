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
 * Persistent record for the questionnaire_fb_sections table.
 *
 * Defines a scored feedback section for a survey. Each section has a score
 * calculation expression and optional heading content displayed to the respondent.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_section_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_fb_sections';

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
            'section' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'scorecalculation' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'sectionlabel' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'sectionheading' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'sectionheadingformat' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => 1,
            ],
        ];
    }

    /**
     * Return all feedback sections for a given survey.
     *
     * @param int $surveyid
     * @return feedback_section_record[]
     */
    public static function get_for_survey(int $surveyid): array {
        return static::get_records(['surveyid' => $surveyid]);
    }
}
