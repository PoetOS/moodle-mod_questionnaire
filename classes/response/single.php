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

/**
 * Class for single response types.
 *
 * @author Mike Churchward
 * @package responsetypes
 */

class single extends base {
    /**
     * @return string
     */
    static public function response_table() {
        return 'questionnaire_resp_single';
    }

    /**
     * @param int|object $responsedata
     * @return bool|int
     * @throws \dml_exception
     */
    public function insert_response($responsedata) {
        global $DB;

        $cid = isset($responsedata->{'q'.$this->question->id}) ? $responsedata->{'q'.$this->question->id} : null;
        $resid = '';

        if ($cid === null) {
            return false;
        }

        $cid = clean_param($cid, PARAM_CLEAN);
        if (isset($this->question->choices[$cid])) {
            // If this choice is an "other" choice, look for the added input.
            if (\mod_questionnaire\question\base::other_choice($this->question->choices[$cid])) {
                $cname = 'q' . $this->question->id . \mod_questionnaire\question\base::other_choice_name($cid);
                $other = isset($responsedata->{$cname}) ? $responsedata->{$cname} : '';

                // If no input specified, ignore this choice.
                if (!empty($other) && !preg_match("/[ \t\n]/", $other)) {
                    $record = new \stdClass();
                    $record->response_id = $responsedata->rid;
                    $record->question_id = $this->question->id;
                    $record->choice_id = $cid;
                    $record->response = $other;
                    $DB->insert_record('questionnaire_response_other', $record);
                }
            }

            // Record the choice selection.
            $record = new \stdClass();
            $record->response_id = $responsedata->rid;
            $record->question_id = $this->question->id;
            $record->choice_id = $cid;
            $resid = $DB->insert_record(self::response_table(), $record);
        }
        return $resid;
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

        // Added qc.id to preserve original choices ordering.
        $sql = 'SELECT rt.id, qc.id as cid, qc.content ' .
               'FROM {questionnaire_quest_choice} qc, ' .
               '{'.static::response_table().'} rt ' .
               'WHERE qc.question_id= ? AND qc.content NOT LIKE \'!other%\' AND ' .
                     'rt.question_id=qc.question_id AND rt.choice_id=qc.id' . $rsql . ' ' .
               'ORDER BY qc.id';

        $rows = $DB->get_records_sql($sql, $params);

        // Handle 'other...'.
        $sql = 'SELECT rt.id, rt.response, qc.content ' .
               'FROM {questionnaire_response_other} rt, ' .
                    '{questionnaire_quest_choice} qc ' .
               'WHERE rt.question_id= ? AND rt.choice_id=qc.id' . $rsql . ' ' .
               'ORDER BY qc.id';

        if ($recs = $DB->get_records_sql($sql, $params)) {
            $i = 1;
            foreach ($recs as $rec) {
                $rows['other'.$i] = new \stdClass();
                $rows['other'.$i]->content = $rec->content;
                $rows['other'.$i]->response = $rec->response;
                $i++;
            }
        }

        return $rows;
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

        $sql = 'SELECT response_id as rid, c.value AS score ' .
            'FROM {'.$this->response_table().'} r ' .
            'INNER JOIN {questionnaire_quest_choice} c ON r.choice_id = c.id ' .
            'WHERE r.question_id= ? ' . $rsql . ' ' .
            'ORDER BY response_id ASC';
        return $DB->get_records_sql($sql, $params);
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
        global $DB;

        $rows = $this->get_results($rids, $anonymous);
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        $numresps = count($rids);

        $responsecountsql = 'SELECT COUNT(DISTINCT r.response_id) ' .
            'FROM {' . $this->response_table() . '} r ' .
            'WHERE r.question_id = ? ';
        $numrespondents = $DB->count_records_sql($responsecountsql, [$this->question->id]);

        if ($rows) {
            foreach ($rows as $idx => $row) {
                if (strpos($idx, 'other') === 0) {
                    $answer = $row->response;
                    $ccontent = $row->content;
                    $content = \mod_questionnaire\question\base::other_choice_display($ccontent);
                    $content .= ' ' . clean_text($answer);
                    $textidx = $content;
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                } else {
                    $contents = questionnaire_choice_values($row->content);
                    $this->choice = $contents->text.$contents->image;
                    $textidx = $this->choice;
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $pagetags = $this->get_results_tags($this->counts, $numresps, $numrespondents, $prtotal, $sort);
        } else {
            $pagetags = new \stdClass();
        }
        return $pagetags;
    }

    /**
     * Return an array of answers by question/choice for the given response. Must be implemented by the subclass.
     * Array is indexed by question, and contains an array by choice code of selected choices.
     *
     * @param int $rid The response id.
     * @return array
     */
    static public function response_select($rid) {
        global $DB;

        $values = [];
        $sql = 'SELECT a.id, q.id as qid, q.content, c.content as ccontent, c.id as cid, o.response ' .
            'FROM {'.static::response_table().'} a ' .
            'INNER JOIN {questionnaire_question} q ON a.question_id = q.id ' .
            'INNER JOIN {questionnaire_quest_choice} c ON a.choice_id = c.id ' .
            'LEFT JOIN {questionnaire_response_other} o ON a.response_id = o.response_id AND c.id = o.choice_id ' .
            'WHERE a.response_id = ? ';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $row) {
            $newrow['content'] = $row->content;
            $newrow['ccontent'] = $row->ccontent;
            $newrow['responses'] = [];
            $newrow['responses'][$row->cid] = $row->cid;
            if (\mod_questionnaire\question\base::other_choice($row->ccontent)) {
                $newrow['responses'][\mod_questionnaire\question\base::other_choice_name($row->cid)] = $row->response;
            }
            $values[$row->qid] = $newrow;
        }

        return $values;
    }

    /**
     * Return sql and params for getting responses in bulk.
     * @author Guy Thomas
     * @param int|array $questionnaireids One id, or an array of ids.
     * @param bool|int $responseid
     * @param bool|int $userid
     * @return array
     */
    public function get_bulk_sql($questionnaireids, $responseid = false, $userid = false, $groupid = false, $showincompletes = 0) {
        global $DB;

        $sql = $this->bulk_sql();
        if (($groupid !== false) && ($groupid > 0)) {
            $groupsql = ' INNER JOIN {groups_members} gm ON gm.groupid = ? AND gm.userid = qr.userid ';
            $gparams = [$groupid];
        } else {
            $groupsql = '';
            $gparams = [];
        }

        if (is_array($questionnaireids)) {
            list($qsql, $params) = $DB->get_in_or_equal($questionnaireids);
        } else {
            $qsql = ' = ? ';
            $params = [$questionnaireids];
        }
        if ($showincompletes == 1) {
            $showcompleteonly = '';
        } else {
            $showcompleteonly = 'AND qr.complete = ? ';
            $params[] = 'y';
        }

        $sql .= "
            AND qr.questionnaireid $qsql $showcompleteonly
      LEFT JOIN {questionnaire_response_other} qro ON qro.response_id = qr.id AND qro.choice_id = qrs.choice_id
      LEFT JOIN {user} u ON u.id = qr.userid
      $groupsql
        ";
        $params = array_merge($params, $gparams);

        if ($responseid) {
            $sql .= " WHERE qr.id = ?";
            $params[] = $responseid;
        } else if ($userid) {
            $sql .= " WHERE qr.userid = ?";
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
        $alias = 'qrs';
        $extraselect = 'qrs.choice_id, ' . $DB->sql_order_by_text('qro.response', 1000) . ' AS response, 0 AS rankvalue';

        return "
            SELECT " . $DB->sql_concat_join("'_'", ['qr.id', "'".$this->question->helpname()."'", $alias.'.id']) . " AS id,
                   qr.submitted, qr.complete, qr.grade, qr.userid, $userfields, qr.id AS rid, $alias.question_id,
                   $extraselect
              FROM {questionnaire_response} qr
              JOIN {".static::response_table()."} $alias ON $alias.response_id = qr.id
        ";
    }
}
