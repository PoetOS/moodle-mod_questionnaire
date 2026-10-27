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
 * Persistent record for the questionnaire_dependency table.
 *
 * Each row defines one condition that must be satisfied for a question to be shown
 * (conditional branching / skip logic). Multiple rows for the same questionid are
 * combined using the dependandor ('and'/'or') logic.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dependency_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_dependency';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'questionid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'surveyid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'dependquestionid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'dependchoiceid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'dependlogic' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'dependandor' => [
                'type' => PARAM_ALPHA,
                'default' => '',
            ],
        ];
    }

    /**
     * Return all dependencies for a given question.
     *
     * @param int $questionid
     * @return dependency_record[]
     */
    public static function get_for_question(int $questionid): array {
        return static::get_records(['questionid' => $questionid]);
    }

    /**
     * Return all dependencies for all questions in a given survey.
     *
     * @param int $surveyid
     * @return dependency_record[]
     */
    public static function get_for_survey(int $surveyid): array {
        return static::get_records(['surveyid' => $surveyid]);
    }

    /**
     * Bulk delete every dependency row belonging to the survey.
     *
     * @param int $surveyid
     * @return bool
     */
    public static function delete_for_survey(int $surveyid): bool {
        global $DB;
        return $DB->delete_records(static::TABLE, ['surveyid' => $surveyid]);
    }

    /**
     * Bulk delete every dependency that references the given question — both as the
     * dependent question and as the question depended on.
     *
     * @param int $questionid
     * @return bool
     */
    public static function delete_for_question(int $questionid): bool {
        global $DB;
        $ok = $DB->delete_records(static::TABLE, ['questionid' => $questionid]);
        $ok = $DB->delete_records(static::TABLE, ['dependquestionid' => $questionid]) && $ok;
        return $ok;
    }
}
