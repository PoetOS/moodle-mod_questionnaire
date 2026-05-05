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

use stdClass;

/**
 * Moodle gradebook integration for the questionnaire module.
 *
 * Pure static glue between mod_questionnaire and core grade_update().
 * Accepts either a questionnaire domain object or a stdClass record from lib.php.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class gradebook {
    /**
     * Return grade rows for the given questionnaire and (optional) user.
     *
     * @param object $questionnaire Either a questionnaire domain object or a stdClass record.
     * @param int $userid Optional user id; 0 means all users.
     * @return array
     */
    public static function get_user_grades(object $questionnaire, int $userid = 0): array {
        global $DB;
        $qid = ($questionnaire instanceof questionnaire) ? $questionnaire->id() : $questionnaire->id;
        $params = [];
        $usersql = '';
        if (!empty($userid)) {
            $usersql = "AND u.id = ?";
            $params[] = $userid;
        }

        $sql = "SELECT r.id, u.id AS userid, r.grade AS rawgrade, r.submitted AS dategraded, r.submitted AS datesubmitted
                FROM {user} u, {questionnaire_response} r
                WHERE u.id = r.userid AND r.questionnaireid = $qid AND r.complete = 'y' $usersql";
        return $DB->get_records_sql($sql, $params) ?? [];
    }

    /**
     * Create or update the grade item for the given questionnaire.
     *
     * @param object $questionnaire Either a questionnaire domain object or a stdClass record (with cmidnumber).
     * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook.
     * @return int 0 if ok, error code otherwise.
     */
    public static function grade_item_update(object $questionnaire, mixed $grades = null): int {
        global $CFG;
        if (!function_exists('grade_update')) {
            require_once($CFG->libdir . '/gradelib.php');
        }

        if ($questionnaire instanceof questionnaire) {
            $qid = $questionnaire->id();
            $qname = $questionnaire->name();
            $qgrade = $questionnaire->grade();
            $qcourseid = $questionnaire->courseid();
            $qcmidnumber = $questionnaire->cmidnumber;
        } else {
            $qid = $questionnaire->instance ?? $questionnaire->id;
            $qname = $questionnaire->name;
            $qgrade = $questionnaire->grade;
            $qcourseid = $questionnaire->courseid ?? $questionnaire->course;
            $qcmidnumber = $questionnaire->cmidnumber;
        }

        if ($qcmidnumber != '') {
            $params = ['itemname' => $qname, 'idnumber' => $qcmidnumber];
        } else {
            $params = ['itemname' => $qname];
        }

        if ($qgrade > 0) {
            $params['gradetype'] = GRADE_TYPE_VALUE;
            $params['grademax'] = $qgrade;
            $params['grademin'] = 0;
        } else if ($qgrade < 0) {
            $params['gradetype'] = GRADE_TYPE_SCALE;
            $params['scaleid'] = -$qgrade;
        } else if ($qgrade == 0) {
            $grades = null;
            $params = ['deleted' => 1];
        } else {
            $params = null;
        }

        if ($grades === 'reset') {
            $params['reset'] = true;
            $grades = null;
        }

        return grade_update(
            'mod/questionnaire',
            $qcourseid,
            'mod',
            'questionnaire',
            $qid,
            0,
            $grades,
            $params
        );
    }

    /**
     * Update grades by firing grade_updated event.
     *
     * @param object|null $questionnaire Questionnaire instance, or null to update all.
     * @param int $userid Optional user id; 0 means all users.
     * @param bool $nullifnone Unused; the Moodle API requires it.
     * @return void
     */
    public static function update_grades(?object $questionnaire = null, int $userid = 0, bool $nullifnone = true): void {
        global $CFG, $DB;

        if (!function_exists('grade_update')) {
            require_once($CFG->libdir . '/gradelib.php');
        }

        if ($questionnaire != null) {
            if ($graderecs = self::get_user_grades($questionnaire, $userid)) {
                $grades = [];
                foreach ($graderecs as $v) {
                    if (!isset($grades[$v->userid])) {
                        $grades[$v->userid] = new stdClass();
                        if ($v->rawgrade == -1) {
                            $grades[$v->userid]->rawgrade = null;
                        } else {
                            $grades[$v->userid]->rawgrade = $v->rawgrade;
                        }
                        $grades[$v->userid]->userid = $v->userid;
                    } else if (isset($grades[$v->userid]) && ($v->rawgrade > $grades[$v->userid]->rawgrade)) {
                        $grades[$v->userid]->rawgrade = $v->rawgrade;
                    }
                }
                self::grade_item_update($questionnaire, $grades);
            } else {
                self::grade_item_update($questionnaire);
            }
        } else {
            $sql = "SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
                      FROM {questionnaire} q, {course_modules} cm, {modules} m
                     WHERE m.name='questionnaire' AND m.id=cm.module AND cm.instance=q.id";
            if ($rs = $DB->get_recordset_sql($sql)) {
                foreach ($rs as $questionnaire) {
                    if ($questionnaire->grade != 0) {
                        self::update_grades($questionnaire);
                    } else {
                        self::grade_item_update($questionnaire);
                    }
                }
                $rs->close();
            }
        }
    }
}
