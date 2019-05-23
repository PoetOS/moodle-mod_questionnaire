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

namespace mod_questionnaire\responsetype;
defined('MOODLE_INTERNAL') || die();

use mod_questionnaire\db\bulk_sql_config;

/**
 * Class for boolean response types.
 *
 * @author Mike Churchward
 * @package response
 */

class boolean extends responsetype {

    /**
     * @return string
     */
    static public function response_table() {
        return 'questionnaire_response_bool';
    }

    /**
     * @param int|object $responsedata
     * @return bool|int
     * @throws \dml_exception
     */
    public function insert_response($responsedata) {
        global $DB;

        $val = isset($responsedata->{'q'.$this->question->id}) ? $responsedata->{'q'.$this->question->id} : '';
        if (!empty($val)) { // If "no answer" then choice is empty (CONTRIB-846).
            $record = new \stdClass();
            $record->response_id = $responsedata->rid;
            $record->question_id = $this->question->id;
            $record->choice_id = $val;
            return $DB->insert_record(self::response_table(), $record);
        } else {
            return false;
        }
    }

    /**
     * @param bool $rids
     * @param bool $anonymous
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_results($rids=false, $anonymous=false) {
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
               'FROM {'.self::response_table().'} ' .
               'WHERE question_id= ? ' . $rsql . ' AND choice_id != ? ' .
               'GROUP BY choice_id';
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * If the choice id needs to be transformed into a different value, override this in the child class.
     * @param $choiceid
     * @return mixed
     */
    public function transform_choiceid($choiceid) {
        if ($choiceid == 0) {
            $choice = 'y';
        } else {
            $choice = 'n';
        }
        return $choice;
    }

    /**
     * Provide the feedback scores for all requested response id's. This should be provided only by questions that provide feedback.
     * @param array $rids
     * @return array | boolean
     */
    public function get_feedback_scores(array $rids) {
        global $DB;

        $rsql = '';
        $params = [$this->question->id];
        if (!empty($rids)) {
            list($rsql, $rparams) = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND response_id ' . $rsql;
        }
        $params[] = 'y';

        $feedbackscores = false;
        $sql = 'SELECT response_id, choice_id ' .
            'FROM {'.$this->response_table().'} ' .
            'WHERE question_id= ? ' . $rsql . ' ' .
            'ORDER BY response_id ASC';
        if ($responses = $DB->get_recordset_sql($sql, $params)) {
            $feedbackscores = [];
            foreach ($responses as $rid => $response) {
                $feedbackscores[$rid] = new \stdClass();
                $feedbackscores[$rid]->rid = $rid;
                $feedbackscores[$rid]->score = ($response->choice_id == 'y') ? 1 : 0;
            }
        }
        return $feedbackscores;
    }

    /**
     * Provide a template for results screen if defined.
     * @return mixed The template string or false/
     */
    public function results_template() {
        return 'mod_questionnaire/results_choice';
    }

    /**
     * Return the JSON structure required for the template.
     *
     * @param bool $rids
     * @param string $sort
     * @param bool $anonymous
     * @return string
     */
    public function display_results($rids=false, $sort='', $anonymous=false) {
        $stryes = get_string('yes');
        $strno = get_string('no');

        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        $numresps = count($rids);

        $counts = [$stryes => 0, $strno => 0];
        $numrespondents = 0;
        if ($rows = $this->get_results($rids, $anonymous)) {
            foreach ($rows as $row) {
                $choice = $row->choice_id;
                $count = $row->num;
                if ($choice == 'y') {
                    $choice = $stryes;
                } else {
                    $choice = $strno;
                }
                $counts[$choice] = intval($count);
                $numrespondents += $counts[$choice];
            }
            $pagetags = $this->get_results_tags($counts, $numresps, $numrespondents, $prtotal, '');
        } else {
            $pagetags = new \stdClass();
        }
        return $pagetags;
    }

    /**
     * Load the requested response into the object. Must be implemented by the subclass.
     *
     * @param int $rid The response id.
     */
    public function load_response($rid) {
        global $DB;

        $sql = 'SELECT a.id, c.id as cid, o.response ' .
            'FROM {'.static::response_table().'} a ' .
            'INNER JOIN {questionnaire_quest_choice} c ON a.choice_id = c.id ' .
            'LEFT JOIN {questionnaire_response_other} o ON a.response_id = o.response_id AND c.id = o.choice_id ' .
            'WHERE a.response_id = ? ';
        $record = $DB->get_record_sql($sql, [$rid]);
        if ($record) {
            $this->responseid = $rid;
            $this->choices[$record->cid] = new choice($record->cid, $record->response);
        }
    }

    /**
     * Return an array of answers by question/choice for the given response. Must be implemented by the subclass.
     *
     * @param int $rid The response id.
     * @return array
     */
    static public function response_select($rid) {
        global $DB;

        $values = [];
        $sql = 'SELECT q.id, q.content, a.choice_id '.
            'FROM {'.self::response_table().'} a, {questionnaire_question} q '.
            'WHERE a.response_id= ? AND a.question_id=q.id ';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $qid => $row) {
            $choice = $row->choice_id;
            unset ($row->id);
            unset ($row->choice_id);
            $row = (array)$row;
            $newrow = [];
            foreach ($row as $key => $val) {
                if (!is_numeric($key)) {
                    $newrow[] = $val;
                }
            }
            $values[$qid] = $newrow;
            array_push($values[$qid], ($choice == 'y') ? '1' : '0');
            array_push($values[$qid], $choice); // DEV still needed for responses display.
        }

        return $values;
    }

    /**
     * Configure bulk sql
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config(self::response_table(), 'qrb', true, false, false);
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
        $alias = 'qrb';
        $extraselect = '0 AS choice_id, ' . $DB->sql_order_by_text('qrb.choice_id', 1000) . ' AS response, 0 AS rankvalue';

        return "
            SELECT " . $DB->sql_concat_join("'_'", ['qr.id', "'".$this->question->helpname()."'", $alias.'.id']) . " AS id,
                   qr.submitted, qr.complete, qr.grade, qr.userid, $userfields, qr.id AS rid, $alias.question_id,
                   $extraselect
              FROM {questionnaire_response} qr
              JOIN {".self::response_table()."} $alias ON $alias.response_id = qr.id
        ";
    }
}

