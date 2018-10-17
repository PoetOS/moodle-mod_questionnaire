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
 * Class for date response types.
 *
 * @author Mike Churchward
 * @package responsetypes
 */

class date extends base {
    static public function response_table() {
        return 'questionnaire_response_date';
    }

    public function insert_response($rid, $val) {
        global $DB;
        $checkdateresult = questionnaire_check_date($val);
        $thisdate = $val;
        if (substr($checkdateresult, 0, 5) == 'wrong') {
            return false;
        }
        // Now use ISO date formatting.
        $checkdateresult = questionnaire_check_date($thisdate, true);
        $record = new \stdClass();
        $record->response_id = $rid;
        $record->question_id = $this->question->id;
        $record->response = $checkdateresult;
        return $DB->insert_record(self::response_table(), $record);
    }

    public function get_results($rids=false, $anonymous=false) {
        global $DB;

        $rsql = '';
        $params = array($this->question->id);
        if (!empty($rids)) {
            list($rsql, $rparams) = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND response_id ' . $rsql;
        }

        $sql = 'SELECT id, response ' .
               'FROM {'.self::response_table().'} ' .
               'WHERE question_id= ? ' . $rsql;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Provide a template for results screen if defined.
     * @return mixed The template string or false/
     */
    public function results_template() {
        return 'mod_questionnaire/results_date';
    }

    /**
     * @param bool $rids
     * @param string $sort
     * @param bool $anonymous
     * @return string
     * @throws \coding_exception
     */
    public function display_results($rids=false, $sort='', $anonymous=false) {
        $numresps = count($rids);
        if ($rows = $this->get_results($rids, $anonymous)) {
            $numrespondents = count($rows);
            foreach ($rows as $row) {
                // Count identical answers (case insensitive).
                $this->text = $row->response;
                if (!empty($this->text)) {
                    $dateparts = preg_split('/-/', $this->text);
                    $this->text = make_timestamp($dateparts[0], $dateparts[1], $dateparts[2]); // Unix timestamp.
                    $textidx = clean_text($this->text);
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $pagetags = $this->get_results_tags($this->counts, $numresps, $numrespondents);
        } else {
            $pagetags = new \stdClass();
        }
        return $pagetags;
    }

    /**
     * Override the results tags function for templates for questions with dates.
     *
     * @param $weights
     * @param $participants Number of questionnaire participants.
     * @param $respondents Number of question respondents.
     * @param $showtotals
     * @param string $sort
     * @return \stdClass
     * @throws \coding_exception
     */
    public function get_results_tags($weights, $participants, $respondents, $showtotals = 1, $sort = '') {
        $dateformat = get_string('strfdate', 'questionnaire');

        $pagetags = new \stdClass();
        if ($respondents == 0) {
            return $pagetags;
        }

        if (!empty($weights) && is_array($weights)) {
            $pagetags->responses = [];
            $numresps = 0;
            ksort ($weights); // Sort dates into chronological order.
            foreach ($weights as $content => $num) {
                $response = new \stdClass();
                $response->text = userdate($content, $dateformat, '', false);    // Change timestamp into readable dates.
                $numresps += $num;
                $response->total = $num;
                $pagetags->responses[] = (object)['response' => $response];
            }

            if ($showtotals == 1) {
                $pagetags->total = new \stdClass();
                $pagetags->total->total = "$numresps/$participants";
            }
        }

        return $pagetags;
    }

    /**
     * Return an array of answers by question/choice for the given response. Must be implemented by the subclass.
     *
     * @param int $rid The response id.
     * @param null $col Other data columns to return.
     * @param bool $csvexport Using for CSV export.
     * @param int $choicecodes CSV choicecodes are required.
     * @param int $choicetext CSV choicetext is required.
     * @return array
     */
    static public function response_select($rid, $col = null, $csvexport = false, $choicecodes = 0, $choicetext = 1) {
        global $DB;

        $values = [];
        $sql = 'SELECT q.id '.$col.', a.response as aresponse '.
            'FROM {'.self::response_table().'} a, {questionnaire_question} q '.
            'WHERE a.response_id=? AND a.question_id=q.id ';
        $records = $DB->get_records_sql($sql, [$rid]);
        $dateformat = get_string('strfdate', 'questionnaire');
        foreach ($records as $qid => $row) {
            unset ($row->id);
            $row = (array)$row;
            $newrow = array();
            foreach ($row as $key => $val) {
                if (!is_numeric($key)) {
                    $newrow[] = $val;
                    // Convert date from yyyy-mm-dd database format to actual questionnaire dateformat.
                    // does not work with dates prior to 1900 under Windows.
                    if (preg_match('/\d\d\d\d-\d\d-\d\d/', $val)) {
                        $dateparts = preg_split('/-/', $val);
                        $val = make_timestamp($dateparts[0], $dateparts[1], $dateparts[2]); // Unix timestamp.
                        $val = userdate ( $val, $dateformat);
                        $newrow[] = $val;
                    }
                }
            }
            $values["$qid"] = $newrow;
            $val = array_pop($values["$qid"]);
            array_push($values["$qid"], '', '', $val);
        }

        return $values;
    }

    /**
     * Configure bulk sql
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config(self::response_table(), 'qrd', false, true, false);
    }
}

