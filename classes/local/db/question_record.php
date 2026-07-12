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

    /**
     * Count the non-deleted questions for a given survey.
     *
     * @param int $surveyid
     * @return int
     */
    public static function count_active_for_survey(int $surveyid): int {
        global $DB;
        return $DB->count_records_select(
            static::TABLE,
            'surveyid = :surveyid AND deleted IS NULL',
            ['surveyid' => $surveyid]
        );
    }

    /**
     * Return every question record for the given survey, including soft-deleted rows.
     *
     * @param int $surveyid
     * @return question_record[]
     */
    public static function get_for_survey(int $surveyid): array {
        return static::get_records(['surveyid' => $surveyid]);
    }

    /**
     * Return question records of the given type, optionally restricted to a single survey.
     *
     * @param int $typeid
     * @param int|null $surveyid Restrict to this survey, or null for all surveys.
     * @return question_record[]
     */
    public static function get_for_type(int $typeid, ?int $surveyid = null): array {
        $filters = ['typeid' => $typeid];
        if ($surveyid !== null) {
            $filters['surveyid'] = $surveyid;
        }
        return static::get_records($filters);
    }

    /**
     * Return every soft-deleted question record for the given survey, excluding page breaks,
     * ordered by deletion time (most recent first).
     *
     * @param int $surveyid
     * @return question_record[]
     */
    public static function get_deleted_for_survey(int $surveyid): array {
        global $DB;
        $records = $DB->get_records_select(
            static::TABLE,
            'deleted IS NOT NULL AND surveyid = :surveyid AND typeid != :pagebreak',
            [
                'surveyid' => $surveyid,
                'pagebreak' => \mod_questionnaire\local\question_type::QUESPAGEBREAK,
            ],
            'deleted DESC'
        );
        return array_map(fn($r) => new static(0, $r), $records);
    }

    /**
     * Return a soft-deleted question identified by id and survey, or null if none matches.
     *
     * @param int $questionid
     * @param int $surveyid
     * @return self|null
     */
    public static function get_soft_deleted(int $questionid, int $surveyid): ?self {
        global $DB;
        $record = $DB->get_record_select(
            static::TABLE,
            'id = :id AND surveyid = :surveyid AND deleted IS NOT NULL',
            ['id' => $questionid, 'surveyid' => $surveyid]
        );
        return $record ? new static(0, $record) : null;
    }

    /**
     * Return the highest position among active (non-deleted) questions in the survey,
     * or 0 if there are none.
     *
     * @param int $surveyid
     * @return int
     */
    public static function max_active_position_for_survey(int $surveyid): int {
        global $DB;
        $max = $DB->get_field_select(
            static::TABLE,
            'MAX(position)',
            'surveyid = :surveyid AND deleted IS NULL',
            ['surveyid' => $surveyid]
        );
        return (int) ($max ?: 0);
    }

    /**
     * Count active questions of a given type whose position is below the given value.
     *
     * @param int $surveyid
     * @param int $typeid
     * @param int $position
     * @return int
     */
    public static function count_active_before_position(int $surveyid, int $typeid, int $position): int {
        global $DB;
        return $DB->count_records_select(
            static::TABLE,
            'surveyid = :surveyid AND typeid = :typeid AND position < :position AND deleted IS NULL',
            ['surveyid' => $surveyid, 'typeid' => $typeid, 'position' => $position]
        );
    }

    /**
     * Update only the position field of a question row.
     *
     * @param int $questionid
     * @param int $position
     * @return bool
     */
    public static function update_position(int $questionid, int $position): bool {
        global $DB;
        return $DB->set_field(static::TABLE, 'position', $position, ['id' => $questionid]);
    }

    /**
     * Mark a question as soft-deleted by stamping its deleted column with the current time.
     *
     * @param int $questionid
     * @return bool
     */
    public static function soft_delete(int $questionid): bool {
        global $DB;
        return $DB->set_field(static::TABLE, 'deleted', time(), ['id' => $questionid]);
    }

    /**
     * Return active questions in the survey whose position is greater than the given one,
     * ordered by position ascending.
     *
     * @param int $surveyid
     * @param int $position
     * @return question_record[]
     */
    public static function get_active_after_position(int $surveyid, int $position): array {
        global $DB;
        $records = $DB->get_records_select(
            static::TABLE,
            'surveyid = :surveyid AND deleted IS NULL AND position > :pos',
            ['surveyid' => $surveyid, 'pos' => $position],
            'position ASC'
        );
        return array_map(fn($r) => new static(0, $r), $records);
    }

    /**
     * Insert a new page-break question row at the given position. Returns the created record.
     *
     * @param int $surveyid
     * @param int $position
     * @return self
     */
    public static function create_pagebreak(int $surveyid, int $position): self {
        $record = new static(0, (object)[
            'surveyid' => $surveyid,
            'typeid' => \mod_questionnaire\local\question_type::QUESPAGEBREAK,
            'position' => $position,
            'content' => 'break',
        ]);
        $record->create();
        return $record;
    }

    /**
     * Bulk delete every question row belonging to the survey, regardless of deleted status.
     *
     * @param int $surveyid
     * @return bool
     */
    public static function delete_for_survey(int $surveyid): bool {
        global $DB;
        return $DB->delete_records(static::TABLE, ['surveyid' => $surveyid]);
    }

    /**
     * Permanently delete a soft-deleted question row identified by id and survey.
     *
     * No-op when the row is not currently soft-deleted, so callers can use this safely
     * without first checking the deleted flag.
     *
     * @param int $questionid
     * @param int $surveyid
     * @return bool
     */
    public static function delete_soft_deleted(int $questionid, int $surveyid): bool {
        global $DB;
        return $DB->delete_records_select(
            static::TABLE,
            'id = :id AND surveyid = :surveyid AND deleted IS NOT NULL',
            ['id' => $questionid, 'surveyid' => $surveyid]
        );
    }

    /**
     * Permanently delete every soft-deleted page-break question for the survey.
     *
     * @param int $surveyid
     * @return bool
     */
    public static function delete_soft_deleted_pagebreaks_for_survey(int $surveyid): bool {
        global $DB;
        return $DB->delete_records_select(
            static::TABLE,
            'surveyid = :surveyid AND deleted IS NOT NULL AND typeid = :typeid',
            ['surveyid' => $surveyid, 'typeid' => \mod_questionnaire\local\question_type::QUESPAGEBREAK]
        );
    }
}
