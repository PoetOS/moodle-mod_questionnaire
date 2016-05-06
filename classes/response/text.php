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
 * Class for text response types.
 *
 * @author Mike Churchward
 * @package responsetypes
 */

class text extends base {
    public function response_table() {
        return 'questionnaire_response_text';
    }

    public function insert_response($rid, $val) {
        global $DB;
        // Only insert if non-empty content.
        if ($this->question->type_id == QUESNUMERIC) {
            $val = preg_replace("/[^0-9.\-]*(-?[0-9]*\.?[0-9]*).*/", '\1', $val);
        }

        if (preg_match("/[^ \t\n]/", $val)) {
            $record = new \stdClass();
            $record->response_id = $rid;
            $record->question_id = $this->question->id;
            $record->response = $val;
            return $DB->insert_record($this->response_table(), $record);
        } else {
            return false;
        }
    }

    protected function get_results($rids=false) {
        global $DB;

        $rsql = '';
        if (!empty($rids)) {
            list($rsql, $params) = $DB->get_in_or_equal($rids);
            $rsql = ' AND response_id ' . $rsql;
        }

        $sql = 'SELECT t.id, t.response, r.submitted AS submitted, r.username, u.username AS username, ' .
                'u.id as userid, ' .
                'r.survey_id, r.id AS rid ' .
                'FROM {'.$this->response_table().'} t, ' .
                '{questionnaire_response} r, ' .
                '{user} u ' .
                'WHERE question_id=' . $this->question->id . $rsql .
                ' AND t.response_id = r.id' .
                ' AND u.id = ' . $DB->sql_cast_char2int('r.username') .
                'ORDER BY u.lastname, u.firstname, r.submitted';
        return $DB->get_records_sql($sql, $params);
    }

    public function display_results($rids=false, $sort='') {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_results($rids)) {
            // Count identical answers (numeric questions only).
            foreach ($rows as $row) {
                if (!empty($row->response) || $row->response === "0") {
                    $this->text = $row->response;
                    $textidx = clean_text($this->text);
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                    $this->userid[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $isnumeric = $this->question->type_id == QUESNUMERIC;
            if ($isnumeric) {
                \mod_questionnaire\response\display_support::mkreslistnumeric($this->counts, count($rids), $this->question->precise);
            } else {
                \mod_questionnaire\response\display_support::mkreslisttext($rows);
            }
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    /**
     * Configure bulk sql
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config($this->response_table(), 'qrt', false, true, false);
    }
}

