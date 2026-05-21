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
 * Persistent record for the questionnaire_response table.
 *
 * Each row represents a single attempt (complete or in-progress) by a user.
 * The 'complete' field uses 'y'/'n' rather than a boolean integer.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_record extends \core\persistent {
    /** @var string The table name. */
    public const TABLE = 'questionnaire_response';

    /**
     * Define the properties of this record.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'questionnaireid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'submitted' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'complete' => [
                'type' => PARAM_ALPHA,
                'default' => 'n',
            ],
            'grade' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'userid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Return all responses for a given questionnaire instance.
     *
     * @param int $questionnaireid
     * @return response_record[]
     */
    public static function get_for_questionnaire(int $questionnaireid): array {
        return static::get_records(['questionnaireid' => $questionnaireid]);
    }

    /**
     * Return the response with the given id, or null if it does not exist.
     *
     * Persistent's constructor throws on a missing id; callers that want to
     * branch on "exists or not" should use this finder instead.
     *
     * @param int $rid
     * @return response_record|null
     */
    public static function get_or_null(int $rid): ?self {
        if (empty($rid)) {
            return null;
        }
        return static::record_exists($rid) ? new self($rid) : null;
    }

    /**
     * Return all complete responses for a given questionnaire and user.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return response_record[]
     */
    public static function get_complete_for_user(int $questionnaireid, int $userid): array {
        return static::get_records(['questionnaireid' => $questionnaireid, 'userid' => $userid, 'complete' => 'y']);
    }

    /**
     * True if the given user has at least one complete response for the given questionnaire.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return bool
     */
    public static function user_has_complete_response(int $questionnaireid, int $userid): bool {
        return static::record_exists_select(
            'questionnaireid = :questionnaireid AND userid = :userid AND complete = :complete',
            ['questionnaireid' => $questionnaireid, 'userid' => $userid, 'complete' => 'y']
        );
    }

    /**
     * True if the given user has an in-progress (incomplete) saved response for the given questionnaire.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return bool
     */
    public static function user_has_saved_response(int $questionnaireid, int $userid): bool {
        return static::record_exists_select(
            'questionnaireid = :questionnaireid AND userid = :userid AND complete = :complete',
            ['questionnaireid' => $questionnaireid, 'userid' => $userid, 'complete' => 'n']
        );
    }

    /**
     * Count complete responses for one questionnaire instance.
     *
     * @param int $questionnaireid Restrict to this instance.
     * @param int|false $userid Restrict to a specific user, or false for all users.
     * @param int $groupid Restrict to members of this group, or 0 for no group filter.
     * @return int
     */
    public static function count_complete_for_questionnaire(
        int $questionnaireid,
        $userid = false,
        int $groupid = 0
    ): int {
        global $DB;

        $params = ['questionnaireid' => $questionnaireid, 'status' => 'y'];
        $sql = 'SELECT COUNT(r.id)
                  FROM {questionnaire_response} r ';
        if ($groupid != 0) {
            $sql .= 'INNER JOIN {groups_members} gm ON r.userid = gm.userid ';
        }
        $sql .= 'WHERE r.questionnaireid = :questionnaireid AND r.complete = :status';
        if ($groupid != 0) {
            $sql .= ' AND gm.groupid = :groupid';
            $params['groupid'] = $groupid;
        }
        if ($userid) {
            $sql .= ' AND r.userid = :userid';
            $params['userid'] = $userid;
        }
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Count complete responses across every questionnaire instance that points at the given (public) survey.
     *
     * @param int $surveyid The public-master survey id.
     * @param int|false $userid Restrict to a specific user, or false for all users.
     * @param int $groupid Restrict to members of this group, or 0 for no group filter.
     * @return int
     */
    public static function count_complete_for_public_survey(
        int $surveyid,
        $userid = false,
        int $groupid = 0
    ): int {
        global $DB;

        $params = ['surveyid' => $surveyid, 'status' => 'y'];
        $sql = 'SELECT COUNT(r.id)
                  FROM {questionnaire_response} r
                  INNER JOIN {questionnaire} q ON r.questionnaireid = q.id
                  INNER JOIN {questionnaire_survey} s ON q.sid = s.id ';
        if ($groupid != 0) {
            $sql .= 'INNER JOIN {groups_members} gm ON r.userid = gm.userid ';
        }
        $sql .= 'WHERE s.id = :surveyid AND r.complete = :status';
        if ($groupid != 0) {
            $sql .= ' AND gm.groupid = :groupid';
            $params['groupid'] = $groupid;
        }
        if ($userid) {
            $sql .= ' AND r.userid = :userid';
            $params['userid'] = $userid;
        }
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Return the most recent incomplete response record for the given questionnaire and user, or null.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return response_record|null
     */
    public static function get_latest_incomplete(int $questionnaireid, int $userid): ?self {
        $records = static::get_records(
            ['questionnaireid' => $questionnaireid, 'userid' => $userid, 'complete' => 'n'],
            'submitted',
            'DESC',
            0,
            1
        );
        return empty($records) ? null : reset($records);
    }
}
