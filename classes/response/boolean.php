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
 * Class for boolean response types.
 *
 * @author Mike Churchward
 * @package response
 */

class boolean extends base {

    public function response_table() {
        return 'questionnaire_response_bool';
    }

    public function insert_response($rid, $val) {
        global $DB;
        if (!empty($val)) { // If "no answer" then choice is empty (CONTRIB-846).
            $record = new \stdClass();
            $record->response_id = $rid;
            $record->question_id = $this->question->id;
            $record->choice_id = $val;
            return $DB->insert_record($this->response_table(), $record);
        } else {
            return false;
        }
    }

    protected function get_results($rids=false) {
        global $DB;

        $rsql = '';
        $params = array($this->question->id);
        if (!empty($rids)) {
            list($rsql, $rparams) = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND response_id ' . $rsql;
        }
        $params[] = '';

        $sql = 'SELECT choice_id, COUNT(response_id) AS num ' .
               'FROM {'.$this->response_table().'} ' .
               'WHERE question_id= ? ' . $rsql . ' AND choice_id != ? ' .
               'GROUP BY choice_id';
        return $DB->get_records_sql($sql, $params);
    }

    public function display_results($rids=false, $sort='') {
        if (empty($this->stryes)) {
            $this->stryes = get_string('yes');
            $this->strno = get_string('no');
        }

        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }

         $this->counts = array($this->stryes => 0, $this->strno => 0);
        if ($rows = $this->get_results($rids)) {
            foreach ($rows as $row) {
                $this->choice = $row->choice_id;
                $count = $row->num;
                if ($this->choice == 'y') {
                    $this->choice = $this->stryes;
                } else {
                    $this->choice = $this->strno;
                }
                $this->counts[$this->choice] = intval($count);
            }
            \mod_questionnaire\response\display_support::mkrespercent($this->counts, count($rids),
                $this->question->precise, $prtotal, $sort = '');
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    /**
     * Configure bulk sql
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config($this->response_table(), 'qrb', true, false, false);
    }

    /**
     * Return sql for getting responses in bulk.
     * @author Guy Thomas
     * @author Mike Churchward
     * @return string
     */
    protected function bulk_sql() {
        global $DB;

        $userfields = $this->user_fields_sql();
        // Postgres requires all fields to be the same type. Boolean type returns a character value as "choice_id",
        // while all others are an integer. So put the boolean response in "response" field instead (CONTRIB-6436).
        // NOTE - the actual use of "boolean" should probably change to not use "choice_id" at all, or use it as
        // numeric zero and one instead.
        $extraselect = '0 AS choice_id, qrb.choice_id AS response, 0 AS rank';
        $alias = 'qrb';

        return "
            SELECT " . $DB->sql_concat_join("'_'", ['qr.id', "'".$this->question->helpname()."'", $alias.'.id']) . " AS id,
                   qr.submitted, qr.complete, qr.grade, qr.username, $userfields, qr.id AS rid, $alias.question_id,
                   $extraselect
              FROM {questionnaire_response} qr
              JOIN {".$this->response_table()."} $alias ON $alias.response_id = qr.id
        ";
    }
}

