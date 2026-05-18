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

namespace mod_questionnaire;

/**
 * Caches questionnaire completion state to avoid repeated DB queries.
 *
 * On first check for a questionnaire, bulk-loads all completed user IDs
 * for that questionnaire into memory. Subsequent checks for any user on
 * the same questionnaire are served from the cache with zero DB queries.
 *
 * Also caches questionnaire records so the same instance isn't loaded
 * repeatedly across different users.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Jamie Burgess, NSW Department of Education
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_cache {

    /** @var array Questionnaire records keyed by instance ID. */
    private static $questionnaires = [];

    /** @var array Completed user sets keyed by questionnaire ID. Each value is userid => true. */
    private static $completed = [];

    /**
     * Check whether a user has completed a questionnaire.
     *
     * @param \stdClass $cm Course module object.
     * @param int $userid User ID to check.
     * @param mixed $type Completion type (returned if completionsubmit is disabled).
     * @return bool|mixed True if completed, false if not, $type if completion not enabled.
     */
    public static function check($cm, int $userid, $type) {
        global $DB;

        $instanceid = $cm->instance;

        // Load questionnaire record from cache or DB.
        if (!isset(self::$questionnaires[$instanceid])) {
            self::$questionnaires[$instanceid] = $DB->get_record('questionnaire',
                ['id' => $instanceid], '*', MUST_EXIST);
        }
        $questionnaire = self::$questionnaires[$instanceid];

        if (!$questionnaire->completionsubmit) {
            return $type;
        }

        $qid = $questionnaire->id;

        // Lazy bulk preload: on first check for this questionnaire,
        // load ALL completed user IDs in a single query.
        if (!isset(self::$completed[$qid])) {
            self::$completed[$qid] = [];
            $records = $DB->get_records_sql(
                'SELECT DISTINCT userid FROM {questionnaire_response} WHERE questionnaireid = ? AND complete = ?',
                [$qid, 'y']);
            foreach ($records as $record) {
                self::$completed[$qid][$record->userid] = true;
            }
        }

        return isset(self::$completed[$qid][$userid]);
    }

    /**
     * Clear all caches.
     *
     * Call after any operation that changes questionnaire responses
     * (submit, delete) within the same request.
     */
    public static function clear(): void {
        self::$questionnaires = [];
        self::$completed = [];
    }

    /**
     * Invalidate the completion cache for a specific questionnaire.
     *
     * @param int $questionnaireid The questionnaire ID to invalidate.
     */
    public static function invalidate(int $questionnaireid): void {
        unset(self::$completed[$questionnaireid]);
    }
}
