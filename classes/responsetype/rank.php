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

use Composer\Package\Package;
use mod_questionnaire\db\bulk_sql_config;

/**
 * Class for rank responses.
 *
 * @author Mike Churchward
 * @package responsetypes
 */

class rank extends responsetype {
    /**
     * @return string
     */
    static public function response_table() {
        return 'questionnaire_response_rank';
    }

    /**
     * Provide an array of answer objects from web form data for the question.
     *
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param \mod_questionnaire\question\question $question
     * @return array \mod_questionnaire\responsetype\answer\answer An array of answer objects.
     * @throws \coding_exception
     */
    static public function answers_from_webform($responsedata, $question) {
        $answers = [];
        foreach ($question->choices as $cid => $choice) {
            $other = isset($responsedata->{'q' . $question->id . '_' . $cid}) ?
                $responsedata->{'q' . $question->id . '_' . $cid} : null;
            // Choice not set or not answered.
            if (!isset($other) || $other == '') {
                continue;
            }
            if ($other == get_string('notapplicable', 'questionnaire')) {
                $rank = -1;
            } else {
                $rank = intval($other);
            }
            $record = new \stdClass();
            $record->responseid = $responsedata->rid;
            $record->questionid = $question->id;
            $record->choiceid = $cid;
            $record->value = $rank;
            $answers[$cid] = answer\answer::create_from_data($record);
        }
        return $answers;
    }

    /**
     * Provide an array of answer objects from mobile data for the question.
     *
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param \mod_questionnaire\question\question $question
     * @return array \mod_questionnaire\responsetype\answer\answer An array of answer objects.
     */
    static public function answers_from_appdata($responsedata, $question) {
        $answers = [];
        if (isset($responsedata->{'q'.$question->id}) && !empty($responsedata->{'q'.$question->id})) {
            foreach ($responsedata->{'q' . $question->id} as $choiceid => $choicevalue) {
                if (isset($question->choices[$choiceid])) {
                    $record = new \stdClass();
                    $record->responseid = $responsedata->rid;
                    $record->questionid = $question->id;
                    $record->choiceid = $choiceid;
                    if (!empty($question->nameddegrees)) {
                        // If using named degrees, the app returns the label string. Find the value.
                        $nameddegreevalue = array_search($choicevalue, $question->nameddegrees);
                        if ($nameddegreevalue !== false) {
                            $choicevalue = $nameddegreevalue;
                        }
                    }
                    $record->value = $choicevalue;
                    $answers[] = answer\answer::create_from_data($record);
                }
            }
        }
        return $answers;
    }

    /**
     * @param \mod_questionnaire\responsetype\response\response|\stdClass $responsedata
     * @return bool|int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function insert_response($responsedata) {
        global $DB;

        if (!$responsedata instanceof \mod_questionnaire\responsetype\response\response) {
            $response = \mod_questionnaire\responsetype\response\response::response_from_webform($responsedata, [$this->question]);
        } else {
            $response = $responsedata;
        }

        $resid = false;

        if (isset($response->answers[$this->question->id])) {
            foreach ($response->answers[$this->question->id] as $answer) {
                // Record the choice selection.
                $record = new \stdClass();
                $record->response_id = $response->id;
                $record->question_id = $this->question->id;
                $record->choice_id = $answer->choiceid;
                $record->rankvalue = $answer->value;
                $resid = $DB->insert_record(static::response_table(), $record);
            }
        }
        return $resid;
    }

    /**
     * @param bool $rids
     * @param bool $anonymous
     * @return array
     *
     * TODO - This works differently than all other get_results methods. This needs to be refactored.
     */
    public function get_results($rids=false, $anonymous=false) {
        global $DB;

        $rsql = '';
        if (!empty($rids)) {
            list($rsql, $params) = $DB->get_in_or_equal($rids);
            $rsql = ' AND response_id ' . $rsql;
        }

        $select = 'question_id=' . $this->question->id . ' AND content NOT LIKE \'!other%\' ORDER BY id ASC';
        if ($rows = $DB->get_records_select('questionnaire_quest_choice', $select)) {
            foreach ($rows as $row) {
                $this->counts[$row->content] = new \stdClass();
                $nbna = $DB->count_records(static::response_table(), array('question_id' => $this->question->id,
                                'choice_id' => $row->id, 'rankvalue' => '-1'));
                $this->counts[$row->content]->nbna = $nbna;
            }
        }

        // For nameddegrees, need an array by degree value of positions (zero indexed).
        $rankvalue = [];
        if (!empty($this->question->nameddegrees)) {
            $rankvalue = array_flip(array_keys($this->question->nameddegrees));
        }

        $isrestricted = ($this->question->length < count($this->question->choices)) && $this->question->no_duplicate_choices();
        // Usual case.
        if (!$isrestricted) {
            if (!empty ($rankvalue)) {
                $sql = "SELECT r.id, c.content, r.rankvalue, c.id AS choiceid
                FROM {questionnaire_quest_choice} c, {".static::response_table()."} r
                WHERE r.choice_id = c.id
                AND c.question_id = " . $this->question->id . "
                AND r.rankvalue >= 0{$rsql}
                ORDER BY choiceid";
                $results = $DB->get_records_sql($sql, $params);
                $value = [];
                foreach ($results as $result) {
                    if (isset($rankvalue[$result->rankvalue])) {
                        if (isset ($value[$result->choiceid])) {
                            $value[$result->choiceid] += $rankvalue[$result->rankvalue] + 1;
                        } else {
                            $value[$result->choiceid] = $rankvalue[$result->rankvalue] + 1;
                        }
                    }
                }
            }

            $sql = "SELECT c.id, c.content, a.average, a.num
                    FROM {questionnaire_quest_choice} c
                    INNER JOIN
                         (SELECT c2.id, AVG(a2.rankvalue) AS average, COUNT(a2.response_id) AS num
                          FROM {questionnaire_quest_choice} c2, {".static::response_table()."} a2
                          WHERE c2.question_id = ? AND a2.question_id = ? AND a2.choice_id = c2.id AND a2.rankvalue >= 0{$rsql}
                          GROUP BY c2.id) a ON a.id = c.id
                          order by c.id";
            $results = $DB->get_records_sql($sql, array_merge(array($this->question->id, $this->question->id), $params));
            if (!empty ($rankvalue)) {
                foreach ($results as $key => $result) {
                    if (isset($value[$key])) {
                        $result->averagevalue = $value[$key] / $result->num;
                    }
                }
            }
            // Reindex by 'content'. Can't do this from the query as it won't work with MS-SQL.
            foreach ($results as $key => $result) {
                $results[$result->content] = $result;
                unset($results[$key]);
            }
            return $results;
            // Case where scaleitems is less than possible choices.
        } else {
            $sql = "SELECT c.id, c.content, a.sum, a.num
                    FROM {questionnaire_quest_choice} c
                    INNER JOIN
                         (SELECT c2.id, SUM(a2.rankvalue) AS sum, COUNT(a2.response_id) AS num
                          FROM {questionnaire_quest_choice} c2, {".static::response_table()."} a2
                          WHERE c2.question_id = ? AND a2.question_id = ? AND a2.choice_id = c2.id AND a2.rankvalue >= 0{$rsql}
                          GROUP BY c2.id) a ON a.id = c.id";
            $results = $DB->get_records_sql($sql, array_merge(array($this->question->id, $this->question->id), $params));
            // Formula to calculate the best ranking order.
            $nbresponses = count($rids);
            foreach ($results as $key => $result) {
                $result->average = ($result->sum + ($nbresponses - $result->num) * ($this->length + 1)) / $nbresponses;
                $results[$result->content] = $result;
                unset($results[$key]);
            }
            return $results;
        }
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

        $sql = 'SELECT r.id, r.response_id as rid, r.question_id AS qid, r.choice_id AS cid, r.rankvalue ' .
            'FROM {'.$this->response_table().'} r ' .
            'INNER JOIN {questionnaire_quest_choice} c ON r.choice_id = c.id ' .
            'WHERE r.question_id= ? ' . $rsql . ' ' .
            'ORDER BY rid,cid ASC';
        $responses = $DB->get_recordset_sql($sql, $params);

        $rid = 0;
        $feedbackscores = [];
        foreach ($responses as $response) {
            if ($rid != $response->rid) {
                $rid = $response->rid;
                $feedbackscores[$rid] = new \stdClass();
                $feedbackscores[$rid]->rid = $rid;
                $feedbackscores[$rid]->score = 0;
            }
            // Only count scores that are currently defined (in case old responses are using older data).
            $feedbackscores[$rid]->score += isset($this->question->nameddegrees[$response->rankvalue]) ? $response->rankvalue : 0;
        }

        return (!empty($feedbackscores) ? $feedbackscores : false);
    }

    /**
     * @param bool $rids
     * @param string $sort
     * @param bool $anonymous
     * @return string
     */
    public function display_results($rids=false, $sort='', $anonymous=false) {
        $output = '';

        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }

        if ($rows = $this->get_results($rids, $sort, $anonymous)) {
            $stravgvalue = ''; // For printing table heading.
            foreach ($this->counts as $key => $value) {
                $ccontent = $key;
                $avgvalue = '';
                if (array_key_exists($ccontent, $rows)) {
                    $avg = $rows[$ccontent]->average;
                    $this->counts[$ccontent]->num = $rows[$ccontent]->num;
                    if (isset($rows[$ccontent]->averagevalue)) {
                        $avgvalue = $rows[$ccontent]->averagevalue;
                        $osgood = false;
                        if ($this->question->osgood_rate_scale()) { // Osgood's semantic differential.
                            $osgood = true;
                        }
                        if ($stravgvalue == '' && !$osgood) {
                            $stravgvalue = ' ('.get_string('andaveragevalues', 'questionnaire').')';
                        }
                    } else {
                        $avgvalue = null;
                    }
                } else {
                    $avg = 0;
                }
                $this->counts[$ccontent]->avg = $avg;
                $this->counts[$ccontent]->avgvalue = $avgvalue;
            }
            $output .= \mod_questionnaire\responsetype\display_support::mkresavg($this->counts, count($rids),
                $this->question, $prtotal, $sort, $stravgvalue);

            $output .= \mod_questionnaire\responsetype\display_support::mkrescount($this->counts, $rids, $rows, $this->question,
                $sort);
        } else {
            $output .= '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
        return $output;
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
        $sql = 'SELECT a.id as aid, q.id AS qid, q.precise AS precise, c.id AS cid, q.content, c.content as ccontent,
                                a.rankvalue as arank '.
            'FROM {'.static::response_table().'} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
            'WHERE a.response_id= ? AND a.question_id=q.id AND a.choice_id=c.id '.
            'ORDER BY aid, a.question_id, c.id';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $row) {
            // Next two are 'qid' and 'cid', each with numeric and hash keys.
            $osgood = false;
            if (\mod_questionnaire\question\rate::type_is_osgood_rate_scale($row->precise)) {
                $osgood = true;
            }
            $qid = $row->qid.'_'.$row->cid;
            unset($row->aid); // Get rid of the answer id.
            unset($row->qid);
            unset($row->cid);
            unset($row->precise);
            $row = (array)$row;
            $newrow = [];
            foreach ($row as $key => $val) {
                if ($key != 'content') { // No need to keep question text - ony keep choice text and rank.
                    if ($key == 'ccontent') {
                        if ($osgood) {
                            list($contentleft, $contentright) = array_merge(preg_split('/[|]/', $val), [' ']);
                            $contents = questionnaire_choice_values($contentleft);
                            if ($contents->title) {
                                $contentleft = $contents->title;
                            }
                            $contents = questionnaire_choice_values($contentright);
                            if ($contents->title) {
                                $contentright = $contents->title;
                            }
                            $val = strip_tags($contentleft.'|'.$contentright);
                            $val = preg_replace("/[\r\n\t]/", ' ', $val);
                        } else {
                            $contents = questionnaire_choice_values($val);
                            if ($contents->modname) {
                                $val = $contents->modname;
                            } else if ($contents->title) {
                                $val = $contents->title;
                            } else if ($contents->text) {
                                $val = strip_tags($contents->text);
                                $val = preg_replace("/[\r\n\t]/", ' ', $val);
                            }
                        }
                    }
                    $newrow[] = $val;
                }
            }
            $values[$qid] = $newrow;
        }

        return $values;
    }

    /**
     * Return an array of answer objects by question for the given response id.
     * THIS SHOULD REPLACE response_select.
     *
     * @param int $rid The response id.
     * @return array array answer
     * @throws \dml_exception
     */
    static public function response_answers_by_question($rid) {
        global $DB;

        $answers = [];
        $sql = 'SELECT id, response_id as responseid, question_id as questionid, choice_id as choiceid, rankvalue as value ' .
            'FROM {' . static::response_table() .'} ' .
            'WHERE response_id = ? ';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $record) {
            $answers[$record->questionid][$record->choiceid] = answer\answer::create_from_data($record);
        }

        return $answers;
    }

    /**
     * Configure bulk sql
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config(static::response_table(), 'qrr', true, false, true);
    }

}