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

/**
 * This file contains the parent class for questionnaire question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\response;
defined('MOODLE_INTERNAL') || die();

use mod_questionnaire\db\bulk_sql_config;

/**
 * Class for multiple response types.
 *
 * @author Mike Churchward
 * @package responsetypes
 */

class multiple extends single {
    /**
     * The only differences between multuple and single responses are the
     * response table and the insert logic.
     */
    public function response_table() {
        return 'questionnaire_resp_multiple';
    }

    public function insert_response($rid, $val) {
        global $DB;
        $resid = '';
        foreach ($this->question->choices as $cid => $choice) {
            if (strpos($choice->content, '!other') === 0) {
                $other = optional_param('q'.$this->question->id.'_'.$cid, '', PARAM_CLEAN);
                if (empty($other)) {
                    continue;
                }
                if (!isset($val)) {
                    $val = array($cid);
                } else {
                    array_push($val, $cid);
                }
                if (preg_match("/[^ \t\n]/", $other)) {
                    $record = new \stdClass();
                    $record->response_id = $rid;
                    $record->question_id = $this->question->id;
                    $record->choice_id = $cid;
                    $record->response = $other;
                    $resid = $DB->insert_record('questionnaire_response_other', $record);
                }
            }
        }

        if (!isset($val) || !is_array($val)) {
            return false;
        }

        foreach ($val as $cid) {
            $cid = clean_param($cid, PARAM_CLEAN);
            if ($cid != 0) { // Do not save response if choice is empty.
                if (preg_match("/other_q[0-9]+/", $cid)) {
                    continue;
                }
                $record = new \stdClass();
                $record->response_id = $rid;
                $record->question_id = $this->question->id;
                $record->choice_id = $cid;
                $resid = $DB->insert_record($this->response_table(), $record);
            }
        }
        return $resid;
    }

    /**
     * Return all the fields to be used for users in bulk questionnaire sql.
     *
     * @author: Guy Thomas
     * @return string
     */
    protected function user_fields_sql() {
        $userfieldsarr = get_all_user_name_fields();
        $userfieldsarr = array_merge($userfieldsarr, ['username', 'department', 'institution']);
        $userfields = '';
        foreach ($userfieldsarr as $field) {
            $userfields .= $userfields === '' ? '' : ', ';
            $userfields .= 'u.'.$field;
        }
        $userfields .= ', u.id as userid';
        return $userfields;
    }

    /**
     * Return sql and params for getting responses in bulk.
     * @author Guy Thomas
     * @param int $surveyid
     * @param bool|int $responseid
     * @param bool|int $userid
     * @return array
     */
    public function get_bulk_sql($surveyid, $responseid = false, $userid = false) {
        global $DB;

        $usernamesql = $DB->sql_cast_char2int('qr.username');

        $sql = $this->bulk_sql($surveyid, $responseid, $userid);
        $sql .= "
            AND qr.survey_id = ? AND qr.complete = ?
      LEFT JOIN {questionnaire_response_other} qro ON qro.response_id = qr.id AND qro.choice_id = qrm.choice_id
      LEFT JOIN {user} u ON u.id = $usernamesql
        ";
        $params = [$surveyid, 'y'];
        if ($responseid) {
            $sql .= " AND qr.id = ?";
            $params[] = $responseid;
        } else if ($userid) {
            $sql .= " AND qr.username = ?"; // Note: username is the userid.
            $params[] = $userid;
        }

        return [$sql, $params];
    }

    /**
     * Return sql for getting responses in bulk.
     * @author Guy Thomas
     * @return string
     */
    protected function bulk_sql() {
        global $DB;

        $userfields = $this->user_fields_sql();
        $extraselect = '';
        $extraselect .= 'qrm.choice_id, qro.response, 0 AS rank';
        $alias = 'qrm';

        return "
            SELECT " . $DB->sql_concat_join("'_'", ['qr.id', "'".$this->question->helpname()."'", $alias.'.id']) . " AS id,
                   qr.submitted, qr.complete, qr.grade, qr.username, $userfields, qr.id AS rid, $alias.question_id,
                   $extraselect
              FROM {questionnaire_response} qr
              JOIN {".$this->response_table()."} $alias ON $alias.response_id = qr.id
        ";
    }
}
