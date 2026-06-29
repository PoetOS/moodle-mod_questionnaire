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

namespace mod_questionnaire\local\response;

use mod_questionnaire\db\bulk_sql_config;
use mod_questionnaire\local\question\question;

/**
 * Class for rank responses.
 *
 * @author Mike Churchward
 * @copyright 2016 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questionnaire
 */
class rank extends responsetype {
    /**
     * @var \stdClass $counts Range counts.
     */
    public $counts;

    /**
     * Provide the necessary response data table name. Should probably always be used with late static binding 'static::' form
     * rather than 'self::' form to allow for class extending.
     *
     * @return string response table name.
     */
    public static function response_table() {
        return 'questionnaire_response_rank';
    }

    /**
     * Provide an array of answer objects from web form data for the question.
     *
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param \mod_questionnaire\local\question\question $question
     * @return array \mod_questionnaire\local\response\answer\answer An array of answer objects.
     * @throws \coding_exception
     */
    public static function answers_from_webform($responsedata, $question) {
        $answers = [];
        foreach ($question->choices as $cid => $choice) {
            $other = isset($responsedata->{'q' . $question->id() . '_' . $cid}) ?
                $responsedata->{'q' . $question->id() . '_' . $cid} : null;
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
            $record->questionid = $question->id();
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
     * @param \mod_questionnaire\local\question\question $question
     * @return array \mod_questionnaire\local\response\answer\answer An array of answer objects.
     */
    public static function answers_from_appdata($responsedata, $question) {
        $answers = [];
        if (isset($responsedata->{'q' . $question->id()}) && !empty($responsedata->{'q' . $question->id()})) {
            foreach ($responsedata->{'q' . $question->id()} as $choiceid => $choicevalue) {
                if (isset($question->choices[$choiceid])) {
                    $record = new \stdClass();
                    $record->responseid = $responsedata->rid;
                    $record->questionid = $question->id();
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
     * Insert a provided response to the question.
     *
     * @param object $responsedata All of the responsedata as an object.
     * @return int|bool - on error the subtype should call set_error and return false.
     */
    public function insert_response($responsedata) {
        if (!$responsedata instanceof \mod_questionnaire\local\response\response) {
            $response = \mod_questionnaire\local\response\response::response_from_webform(
                $responsedata,
                [$this->question]
            );
        } else {
            $response = $responsedata;
        }

        $resid = false;

        if (isset($response->answers[$this->question->id()])) {
            foreach ($response->answers[$this->question->id()] as $answer) {
                // Record the choice selection.
                $rec = new \mod_questionnaire\local\db\response_rank_record();
                $rec->set('responseid', $response->id());
                $rec->set('questionid', $this->question->id());
                $rec->set('choiceid', $answer->choiceid);
                $rec->set('rankvalue', $answer->value);
                $rec->create();
                $resid = $rec->get('id');
                // The "other" text is read directly from $responsedata here (not from the
                // parsed $response->answers) — pre-existing inconsistency, not fixed in Phase 23.
                if (isset($responsedata->{$answer->choiceid . '_qother'})) {
                    $otherrec = new \mod_questionnaire\local\db\response_other_record();
                    $otherrec->set('responseid', $response->id());
                    $otherrec->set('questionid', $this->question->id());
                    $otherrec->set('choiceid', $answer->choiceid);
                    $otherrec->set('response', $responsedata->{$answer->choiceid . '_qother'});
                    $otherrec->create();
                }
            }
        }
        return $resid;
    }

    /**
     * Provide the result information for the specified result records.
     *
     * @param bool $rids
     * @param bool $anonymous
     * @return array
     *
     * TODO - This works differently than all other get_results methods. This needs to be refactored.
     */
    public function get_results($rids = false, $anonymous = false) {
        global $DB;

        $rsql = '';
        $params = [];
        if (!empty($rids)) {
            [$rsql, $params] = $DB->get_in_or_equal($rids);
            $rsql = ' AND responseid ' . $rsql;
        }
        // Get other choices.
        $otherrecs = $this->get_other_choice($rsql, $params);

        $select = 'questionid=' . $this->question->id() . ' ORDER BY id ASC';
        if ($rows = $DB->get_records_select('questionnaire_quest_choice', $select)) {
            foreach ($rows as $row) {
                $nbna = $DB->count_records(
                    static::response_table(),
                    [
                        'questionid' => $this->question->id(),
                        'choiceid' => $row->id,
                        'rankvalue' => '-1',
                    ]
                );
                if (\mod_questionnaire\local\question\choice::content_is_other_choice($row->content) && !empty($otherrecs)) {
                    foreach (array_keys($otherrecs) as $key) {
                        $this->counts[$key] = new \stdClass();
                        $this->counts[$key]->nbna = $nbna;
                        $this->counts[$key]->content = $row->content;
                    }
                } else {
                    $this->counts[$row->content] = new \stdClass();
                    $this->counts[$row->content]->nbna = $nbna;
                }
            }
        }

        // For nameddegrees, need an array by degree value of positions (zero indexed).
        $rankvalue = [];
        if (!empty($this->question->nameddegrees)) {
            $rankvalue = array_flip(array_keys($this->question->nameddegrees));
        }

        $isrestricted = ($this->question->length() < count($this->question->choices)) && $this->question->no_duplicate_choices();
        // Usual case.
        if (!$isrestricted) {
            if (!empty($rankvalue)) {
                $sql = "SELECT r.id, c.content, r.rankvalue, c.id AS choiceid
                FROM {questionnaire_quest_choice} c, {" . static::response_table() . "} r
                WHERE r.choiceid = c.id
                AND c.questionid = " . $this->question->id() . "
                AND r.rankvalue >= 0{$rsql}
                ORDER BY choiceid";
                $results = $DB->get_records_sql($sql, $params);
                $value = [];
                foreach ($results as $result) {
                    if (isset($rankvalue[$result->rankvalue])) {
                        if (isset($value[$result->choiceid])) {
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
                         (SELECT c2.id, AVG(a2.rankvalue) AS average, COUNT(a2.responseid) AS num
                            FROM {questionnaire_quest_choice} c2, {" . static::response_table() . "} a2
                           WHERE c2.questionid = ? AND a2.questionid = ? AND a2.choiceid = c2.id
                                 AND a2.rankvalue >= 0 AND c2.content NOT LIKE '!other%'{$rsql}
                        GROUP BY c2.id) a ON a.id = c.id
                  ORDER BY c.id";
            $results = $DB->get_records_sql($sql, array_merge([$this->question->id(), $this->question->id()], $params));

            // Handle 'other...'.
            if ($otherrecs) {
                $i = 1;
                foreach ($otherrecs as $rec) {
                    $results['other' . $i] = new \stdClass();
                    $results['other' . $i]->id = $rec->cid;
                    $results['other' . $i]->content = $rec->response;
                    $results['other' . $i]->average = $rec->average;
                    $results['other' . $i]->num = $rec->num;
                    $results['other' . $i]->isother = true;
                    $i++;
                }
            }

            if (!empty($rankvalue)) {
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
                         (SELECT c2.id, SUM(a2.rankvalue) AS sum, COUNT(a2.responseid) AS num
                            FROM {questionnaire_quest_choice} c2, {" . static::response_table() . "} a2
                           WHERE c2.questionid = ? AND a2.questionid = ? AND a2.choiceid = c2.id
                                 AND a2.rankvalue >= 0 AND c2.content NOT LIKE '!other%'{$rsql}
                  GROUP BY c2.id) a ON a.id = c.id";

            $results = $DB->get_records_sql($sql, array_merge([$this->question->id(), $this->question->id()], $params));

            if ($otherrecs) {
                $i = 1;
                foreach ($otherrecs as $rec) {
                    $results['other' . $i] = new \stdClass();
                    $results['other' . $i]->id = $rec->cid;
                    $results['other' . $i]->content = $rec->response;
                    $results['other' . $i]->sum = $rec->average;
                    $results['other' . $i]->num = $rec->num;
                    $results['other' . $i]->isother = true;
                    $i++;
                }
            }
            // Formula to calculate the best ranking order.
            $nbresponses = count($rids);
            foreach ($results as $key => $result) {
                if ($this->question->length() > 0) {
                    $result->average = ($result->sum + ($nbresponses - $result->num) *
                        ($this->question->length() + 1)) / $nbresponses;
                } else {
                    $result->average = ($result->sum + ($nbresponses - $result->num) * 1 ) / $nbresponses;
                }
                $results[$result->content] = $result;
                unset($results[$key]);
            }
            return $results;
        }
    }

    /**
     * Get a list of other available choices.
     *
     * @param string $rsql
     * @param array $params
     * @return array
     */
    public function get_other_choice(string $rsql, array $params): array {
        global $DB;
        $osql = "SELECT ro.response, AVG(a.rankvalue) AS average, COUNT(a.responseid) AS num, c.id as cid
                   FROM {questionnaire_quest_choice} c
             INNER JOIN {" . static::response_table() . "} a ON a.choiceid = c.id
                        AND a.rankvalue >= 0
                        AND a.questionid = c.questionid{$rsql}
             INNER JOIN {questionnaire_response_other} ro  ON ro.choiceid = c.id
                        AND ro.responseid = a.responseid
                        AND ro.questionid = c.questionid
                  WHERE c.questionid = ?
                        AND c.content = '!other'
                        AND ro.response <> ''
               GROUP BY ro.response, c.id
               ORDER BY c.id";
        return $DB->get_records_sql($osql, array_merge($params, [$this->question->id()]));
    }

    /**
     * Provide the feedback scores for all requested response id's. This should be provided only by questions that provide feedback.
     * @param array $rids
     * @return array | boolean
     */
    public function get_feedback_scores(array $rids) {
        global $DB;

        $rsql = '';
        $params = [$this->question->id()];
        if (!empty($rids)) {
            [$rsql, $rparams] = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND responseid ' . $rsql;
        }
        $params[] = 'y';

        $sql = 'SELECT r.id, r.responseid as rid, r.questionid AS qid, r.choiceid AS cid, r.rankvalue ' .
            'FROM {' . $this->response_table() . '} r ' .
            'INNER JOIN {questionnaire_quest_choice} c ON r.choiceid = c.id ' .
            'WHERE r.questionid= ? ' . $rsql . ' ' .
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
     * Provide a template for results screen if defined.
     * @param bool $pdf
     * @return mixed The template string or false/
     */
    public function results_template($pdf = false) {
        if ($pdf) {
            return 'mod_questionnaire/resultspdf_rate';
        } else {
            return 'mod_questionnaire/results_rate';
        }
    }

    /**
     * Provide the result information for the specified result records.
     *
     * @param int|array $rids - A single response id, or array.
     * @param string $sort - Optional display sort.
     * @param boolean $anonymous - Whether or not responses are anonymous.
     * @param int|null $currentgroupid Active group filter id (unused; signature must match parent).
     * @return string - Display output.
     */
    public function display_results($rids = false, $sort = '', $anonymous = false, ?int $currentgroupid = null) {
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
                            $stravgvalue = ' (' . get_string('andaveragevalues', 'questionnaire') . ')';
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
            $output1 = $this->mkresavg($sort, $stravgvalue);
            $output2 = $this->mkrescount($rids, $rows, $sort);
            $output = (object)array_merge((array)$output1, (array)$output2);
        } else {
            $output = (object)['noresponses' => true];
        }
        return $output;
    }

    /**
     * Return an array of answers by question/choice for the given response. Must be implemented by the subclass.
     *
     * @param int $rid The response id.
     * @return array
     */
    public static function response_select($rid) {
        global $DB;

        $values = [];
        $sql = 'SELECT a.id as aid, q.id AS qid, q.precise AS precise, c.id AS cid, q.content, c.content as ccontent,
                                a.rankvalue as arank ' .
            'FROM {' . static::response_table() . '} a, {questionnaire_question} q, {questionnaire_quest_choice} c ' .
            'WHERE a.responseid= ? AND a.questionid=q.id AND a.choiceid=c.id ' .
            'ORDER BY aid, a.questionid, c.id';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $row) {
            // Next two are 'qid' and 'cid', each with numeric and hash keys.
            $osgood = false;
            if (\mod_questionnaire\local\question\rate::type_is_osgood_rate_scale($row->precise)) {
                $osgood = true;
            }
            $qid = $row->qid . '_' . $row->cid;
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
                            [$contentleft, $contentright] = array_merge(preg_split('/[|]/', $val), [' ']);
                            $contents = question::parse_choice_content($contentleft);
                            if ($contents->title) {
                                $contentleft = $contents->title;
                            }
                            $contents = question::parse_choice_content($contentright);
                            if ($contents->title) {
                                $contentright = $contents->title;
                            }
                            $val = strip_tags($contentleft . '|' . $contentright);
                            $val = preg_replace("/[\r\n\t]/", ' ', $val);
                        } else {
                            $contents = question::parse_choice_content($val);
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
    public static function response_answers_by_question($rid) {
        global $DB;

        $answers = [];
        $sql = 'SELECT r.id,
                       r.responseid AS responseid,
                       r.questionid AS questionid,
                       r.choiceid AS choiceid,
                       r.rankvalue AS value,
                       rt.response AS otheresponse
                  FROM {' . static::response_table() . '} r
             LEFT JOIN {questionnaire_response_other} rt ON rt.choiceid = r.choiceid
                       AND r.questionid = rt.questionid
                       AND r.responseid = rt.responseid
                 WHERE r.responseid = ?';
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

    /**
     * Return a structure for averages.
     * @param string $sort
     * @param string $stravgvalue
     * @return \stdClass
     */
    private function mkresavg($sort, $stravgvalue = '') {
        global $CFG;

        $osgood = ($this->question->precise() == 3);
        $isna = ($this->question->precise() == 1);
        $isrestricted = ($this->question->length() < count($this->counts)) && $this->question->precise() == 2;

        $headers = $this->build_averages_headers($stravgvalue);

        $pagetags = new \stdClass();
        $pagetags->averages = new \stdClass();
        $pagetags->averages->headers = $headers;
        $rankcols = $this->build_rank_columns($headers[1], $isrestricted);
        $pagetags->averages->choicelabelrow = self::build_averages_label_row($headers, $rankcols);

        self::sort_counts_by_avg($this->counts, $sort);

        if (!empty($this->counts) && is_array($this->counts)) {
            $imageurl = $CFG->wwwroot . '/mod/questionnaire/images/hbar.gif';
            $spacerimage = $CFG->wwwroot . '/mod/questionnaire/images/hbartransp.gif';
            $width = 100 / ($this->effective_length($isrestricted));
            $innertablewidth = $pagetags->averages->choicelabelrow->innertablewidth;
            $pagetags->averages->choiceaverages = [];
            foreach ($this->counts as $content => $contentobj) {
                // Eliminate potential named degrees on Likert scale.
                if (preg_match("/^[0-9]{1,3}=/", $content)) {
                    continue;
                }
                $row = $this->build_averages_choice_row(
                    $content,
                    $contentobj,
                    $headers,
                    $osgood,
                    $isna,
                    $isrestricted,
                    $width,
                    $innertablewidth,
                    $imageurl,
                    $spacerimage,
                    $stravgvalue
                );
                if ($row !== null) {
                    $pagetags->averages->choiceaverages[] = $row;
                }
            }
        } else {
            $pagetags->averages->nodata = self::build_averages_nodata($headers);
        }
        return $pagetags;
    }

    /**
     * Build the [label, avg-chart, arrow, optional N/A] header objects with widths and pdfwidths.
     *
     * Shape (isna / osgood / default) is derived from $this->question->precise().
     *
     * @param string $stravgvalue Optional "(and average values)" suffix shown next to the chart title.
     * @return \stdClass[] Indexed from 1.
     */
    private function build_averages_headers(string $stravgvalue): array {
        $isna = ($this->question->precise() == 1);
        $osgood = ($this->question->precise() == 3);
        $stravgrank = $osgood
            ? get_string('averageposition', 'questionnaire')
            : get_string('averagerank', 'questionnaire');

        // PDF columns are based on a 11.69in x 8.27in page. Margins are 15mm each side, or 1.1811 in total.
        $pdfwidth = 11.69 - 1.1811;
        $stravg = '<div style="text-align:right">' . $stravgrank . $stravgvalue . '</div>';
        if ($isna) {
            $widths = ['55%', '35%', '5%', '5%'];
            $ratios = [.55, .35, .05, .05];
            $isnahead = get_string('notapplicable', 'questionnaire');
            $headers = [
                1 => (object)['text' => '', 'align' => ''],
                2 => (object)['text' => $stravg, 'align' => ''],
                3 => (object)['text' => '&dArr;', 'align' => 'center'],
                4 => (object)['text' => $isnahead, 'align' => 'right'],
            ];
        } else if ($osgood) {
            $widths = ['25%', '50%', '25%'];
            $ratios = [.25, .5, .25];
            $stravg = '<div style="text-align:center">' . $stravgrank . '</div>';
            $headers = [
                1 => (object)['text' => '', 'align' => ''],
                2 => (object)['text' => $stravg, 'align' => ''],
                3 => (object)['text' => '', 'align' => 'center'],
            ];
        } else {
            $widths = ['60%', '35%', '5%'];
            $ratios = [.6, .35, .05];
            $headers = [
                1 => (object)['text' => '', 'align' => ''],
                2 => (object)['text' => $stravg, 'align' => ''],
                3 => (object)['text' => '&dArr;', 'align' => 'center'],
            ];
        }
        $i = 0;
        foreach ($headers as $h) {
            $h->width = $widths[$i];
            $h->pdfwidth = $pdfwidth * $ratios[$i];
            $i++;
        }
        return $headers;
    }

    /**
     * Effective rank-axis length (number of rank columns), accounting for the
     * +1 added when the question is "restricted" (length < #choices).
     *
     * @param bool $isrestricted
     * @return int
     */
    private function effective_length(bool $isrestricted): int {
        $llength = $this->question->length() ?: 5;
        return $llength + (int)$isrestricted;
    }

    /**
     * Build the per-rank column objects shown above the average chart.
     *
     * @param \stdClass $chartheader The middle (avg-chart) header used for pdfwidth scaling.
     * @param bool $isrestricted
     * @return \stdClass[]
     */
    private function build_rank_columns(\stdClass $chartheader, bool $isrestricted): array {
        $llength = $this->effective_length($isrestricted);
        $width = 100 / $llength;
        $names = array_values($this->question->nameddegrees);
        $pdfwidth = $chartheader->pdfwidth / (100 / $width);
        $rankcols = [];
        for ($i = 0; $i < $llength; $i++) {
            if ($isrestricted && $i == $llength - 1) {
                $text = '...';
            } else if (isset($names[$i])) {
                $text = $names[$i];
            } else {
                $text = $i + 1;
            }
            $rankcols[] = (object)['width' => $width . '%', 'text' => $text, 'pdfwidth' => $pdfwidth];
        }
        return $rankcols;
    }

    /**
     * Assemble the row that labels the rank columns and slots in the headers' widths.
     *
     * @param array $headers
     * @param array $rankcols
     * @return \stdClass
     */
    private static function build_averages_label_row(array $headers, array $rankcols): \stdClass {
        $row = new \stdClass();
        $row->innertablewidth = $headers[2]->pdfwidth;
        $row->column1 = (object)[
            'width' => $headers[1]->width, 'align' => $headers[1]->align,
            'text' => '', 'pdfwidth' => $headers[1]->pdfwidth,
        ];
        $row->column2 = (object)[
            'width' => $headers[2]->width, 'align' => $headers[2]->align,
            'ranks' => $rankcols, 'pdfwidth' => $headers[2]->pdfwidth,
        ];
        $row->column3 = (object)[
            'width' => $headers[3]->width, 'align' => $headers[3]->align,
            'text' => '', 'pdfwidth' => $headers[3]->pdfwidth,
        ];
        if (isset($headers[4])) {
            $row->column4 = (object)[
                'width' => $headers[4]->width, 'align' => $headers[4]->align,
                'text' => '', 'pdfwidth' => $headers[4]->pdfwidth,
            ];
        }
        return $row;
    }

    /**
     * Sort $this->counts in place by the avg field, depending on $sort.
     *
     * @param array $counts
     * @param string $sort 'ascending', 'descending', or anything else (no-op).
     */
    private static function sort_counts_by_avg(array &$counts, string $sort): void {
        if ($sort === 'ascending') {
            uasort($counts, self::class . '::sortavgasc');
        } else if ($sort === 'descending') {
            uasort($counts, self::class . '::sortavgdesc');
        }
        reset($counts);
    }

    /**
     * Build one choice-average row, or return null when the row should be skipped
     * (osgood charts skip zero-avg rows; normal charts skip rows with no avg and zero N/As).
     *
     * @param string $content
     * @param \stdClass $contentobj
     * @param array $headers
     * @param bool $osgood
     * @param bool $isna
     * @param bool $isrestricted
     * @param float $width
     * @param float $innertablewidth
     * @param string $imageurl
     * @param string $spacerimage
     * @param string $stravgvalue
     * @return \stdClass|null
     */
    private function build_averages_choice_row(
        string $content,
        \stdClass $contentobj,
        array $headers,
        bool $osgood,
        bool $isna,
        bool $isrestricted,
        float $width,
        float $innertablewidth,
        string $imageurl,
        string $spacerimage,
        string $stravgvalue
    ): ?\stdClass {
        // Resolve avg / avgvalue / nbna for this content.
        $avg = '';
        $avgvalue = '';
        if (isset($contentobj->avg)) {
            $avg = $contentobj->avg;
            if (isset($contentobj->avgvalue)) {
                $avgvalue = $contentobj->avg;
                $avg = $contentobj->avgvalue;
            }
        }
        $nbna = $contentobj->nbna;

        // Compute the chart-bar margin for the average position.
        $margin = '';
        $marginpdf = 0;
        if ($avg) {
            $marginposition = ($avg - 0.5) / ($this->question->length() + (int)$isrestricted);
            if (!right_to_left()) {
                $margin = 'margin-left:' . $marginposition * 100 . '%';
                $marginpdf = $marginposition * $innertablewidth;
            } else {
                $margin = 'margin-right:' . $marginposition * 100 . '%';
                $marginpdf = $innertablewidth - ($marginposition * $innertablewidth);
            }
        }

        // Split osgood "left|right" pairs; otherwise parse for embedded mod names.
        $contentright = ' ';
        if ($osgood) {
            [$content, $contentright] = array_merge(preg_split('/[|]/', $content), [' ']);
        } else {
            $parsed = question::parse_choice_content($content);
            if ($parsed->modname) {
                $content = $parsed->text;
            }
        }
        if (
            isset($contentobj->content) &&
            \mod_questionnaire\local\question\choice::content_other_choice_display($contentobj->content)
        ) {
            $othertext = \mod_questionnaire\local\question\choice::content_other_choice_display($contentobj->content);
            $content = $othertext . ' ' . clean_text($content);
        }

        $chartcol = self::make_chart_column($headers[2], $imageurl, $spacerimage, $margin, $marginpdf);

        if ($osgood) {
            return (object)[
                'column1' => self::make_text_column(
                    $headers[1],
                    '<div class="mdl-right">' . format_text($content, FORMAT_HTML, ['noclean' => true]) . '</div>'
                ),
                'column2' => $chartcol,
                'column3' => self::make_text_column(
                    $headers[3],
                    '<div class="mdl-left">' . format_text($contentright, FORMAT_HTML, ['noclean' => true]) . '</div>'
                ),
            ];
        }

        // Non-osgood: skip rows that have neither an avg nor any N/A responses.
        if (!$avg && ($nbna == 0)) {
            return null;
        }

        $stravgval = '';
        if ($avg) {
            $stravgval = sprintf('%.1f', $avg) . '&nbsp;';
            if ($stravgvalue) {
                $stravgval .= '(' . sprintf('%.1f', $avgvalue) . ')';
            }
        }

        $row = (object)[
            'column1' => self::make_text_column($headers[1], format_text($content, FORMAT_HTML, ['noclean' => true])),
            'column2' => $chartcol,
            'column3' => self::make_text_column($headers[3], $stravgval),
        ];
        if ($isna) {
            // Always emit column4 for isna; the value is nbna whether or not an avg exists.
            $row->column4 = self::make_text_column($headers[4], $nbna);
        }
        return $row;
    }

    /**
     * Build a simple text-bearing column object from a header template.
     *
     * @param \stdClass $header
     * @param string $text
     * @return \stdClass
     */
    private static function make_text_column(\stdClass $header, $text): \stdClass {
        return (object)[
            'width'    => $header->width,
            'pdfwidth' => $header->pdfwidth,
            'align'    => $header->align,
            'text'     => $text,
        ];
    }

    /**
     * Build the chart-bar column with image + margin metadata.
     *
     * @param \stdClass $header
     * @param string $imageurl
     * @param string $spacerimage
     * @param string $margin
     * @param float $marginpdf
     * @return \stdClass
     */
    private static function make_chart_column(
        \stdClass $header,
        string $imageurl,
        string $spacerimage,
        string $margin,
        float $marginpdf
    ): \stdClass {
        return (object)[
            'width'       => $header->width,
            'pdfwidth'    => $header->pdfwidth,
            'align'       => $header->align,
            'imageurl'    => $imageurl,
            'spacerimage' => $spacerimage,
            'margin'      => $margin,
            'marginpdf'   => $marginpdf,
        ];
    }

    /**
     * Build the "no responses" placeholder row for the averages table.
     *
     * @param array $headers
     * @return \stdClass[]
     */
    private static function build_averages_nodata(array $headers): array {
        $nodata = [];
        foreach ($headers as $i => $header) {
            $nodata[] = (object)[
                'width' => $header->width,
                'align' => $header->align,
                'text'  => ($i === 2) ? get_string('noresponsedata', 'mod_questionnaire') : '',
            ];
        }
        return $nodata;
    }

    /**
     * Return a structure for counts.
     * @param array $rids
     * @param array $rows
     * @param string $sort
     * @return \stdClass
     */
    private function mkrescount($rids, $rows, $sort) {
        $nbresponses = count($rids);
        $isrestricted = ($this->question->length() < count($this->question->choices)) && $this->question->precise() == 2;
        $isna = ($this->question->precise() == 1);
        $osgood = ($this->question->precise() == 3);

        $choices = $this->fetch_count_choices($rids);
        self::sort_rows_by_average($rows, $sort);
        $ranks = $this->tally_ranks($choices, $rows);

        // Strip embedded mod-name annotations from each persisted choice content.
        foreach ($this->question->choices as $choice) {
            $parsed = question::parse_choice_content($choice->content);
            if ($parsed->modname) {
                $choice->content = $parsed->text;
            }
        }

        $pagetags = new \stdClass();
        $pagetags->totals = new \stdClass();
        $pagetags->totals->headers = $this->build_totals_headers($osgood, $isna, $isrestricted);
        $pagetags->totals->choices = [];
        foreach ($ranks as $content => $rank) {
            // Eliminate potential named degrees on Likert scale.
            if (preg_match("/^[0-9]{1,3}=/", $content)) {
                continue;
            }
            $totalcols = $this->build_totals_choice_row(
                $content,
                $rank,
                $rows,
                $pagetags->totals->headers,
                $osgood,
                $isna,
                $isrestricted,
                $nbresponses
            );
            $pagetags->totals->choices[] = (object)['totalcols' => $totalcols];
        }
        return $pagetags;
    }

    /**
     * Fetch the per-choice rank rows for this question, joined to "!other" override text.
     *
     * @param array $rids
     * @return array
     */
    private function fetch_count_choices(array $rids): array {
        global $DB;
        $rsql = '';
        $params = [];
        if (!empty($rids)) {
            [$rsql, $params] = $DB->get_in_or_equal($rids);
            $rsql = ' AND responseid ' . $rsql;
        }
        array_push($params, $this->question->id());
        $sql = "SELECT r.id,
                       CASE
                            WHEN c.content = '!other' THEN o.response
                            ELSE c.content
                       END as content, r.rankvalue, c.id AS choiceid
                  FROM {questionnaire_quest_choice} c
            INNER JOIN {" . static::response_table() . "} r ON r.questionid = c.questionid
                       AND r.choiceid = c.id{$rsql}
             LEFT JOIN {questionnaire_response_other} o ON o.choiceid = c.id
                       AND o.responseid = r.responseid
                       AND o.questionid = c.questionid
                 WHERE c.questionid = ? AND (c.content != '!other' OR (o.response IS NOT NULL AND o.response <> ''))
              ORDER BY choiceid, rankvalue ASC";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Sort the get_results() rows by their average field in place.
     *
     * @param array $rows
     * @param string $sort 'ascending', 'descending', or anything else (no-op).
     */
    private static function sort_rows_by_average(array &$rows, string $sort): void {
        if ($sort === 'default') {
            return;
        }
        $sortarray = [];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                $sortarray[$key][] = $value;
            }
        }
        if (!isset($sortarray['average'])) {
            return;
        }
        if ($sort === 'ascending') {
            array_multisort($sortarray['average'], SORT_ASC, $rows);
        } else if ($sort === 'descending') {
            array_multisort($sortarray['average'], SORT_DESC, $rows);
        }
    }

    /**
     * Tally how many responses fell into each rank position, per choice content.
     *
     * Returns a nested array: [content => [rankposition(1-based) => count, ...], ...].
     *
     * @param array $choices Result of {@see fetch_count_choices()}.
     * @param array $rows Result of {@see get_results()}.
     * @return array
     */
    private function tally_ranks(array $choices, array $rows): array {
        $nbranks = $this->question->length();
        $rankvalue = [];
        if (!empty($this->question->nameddegrees)) {
            $rankvalue = array_flip(array_keys($this->question->nameddegrees));
        }
        $ranks = [];
        foreach ($rows as $key => $row) {
            $choiceid = $row->id;
            foreach ($choices as $choice) {
                if ($choice->choiceid != $choiceid || $choice->content !== $key) {
                    continue;
                }
                for ($i = 1; $i <= $nbranks; $i++) {
                    $hit = (isset($rankvalue[$choice->rankvalue]) && ($rankvalue[$choice->rankvalue] == ($i - 1)))
                        || (empty($rankvalue) && ($choice->rankvalue == $i));
                    if (!isset($ranks[$choice->content][$i])) {
                        $ranks[$choice->content][$i] = 0;
                    }
                    if ($hit) {
                        $ranks[$choice->content][$i]++;
                    }
                }
            }
        }
        return $ranks;
    }

    /**
     * Build the header row of the counts table.
     *
     * @param bool $osgood
     * @param bool $isna
     * @param bool $isrestricted
     * @return \stdClass[]
     */
    private function build_totals_headers(bool $osgood, bool $isna, bool $isrestricted): array {
        $headers = [];
        $headers[] = (object)[
            'align' => $osgood ? 'right' : 'left',
            'text'  => '<span class="smalltext">' . get_string('responses', 'questionnaire') . '</span>',
        ];

        $names = [];
        foreach (array_values($this->question->nameddegrees) as $i => $degree) {
            $names[$i] = format_text($degree, FORMAT_HTML, ['noclean' => true]);
        }
        for ($j = 0; $j < $this->question->length(); $j++) {
            $label = $names[$j] ?? ($j + 1);
            $headers[] = (object)['align' => 'center', 'text' => '<span class="smalltext">' . $label . '</span>'];
        }
        if ($osgood) {
            $headers[] = (object)['align' => 'left', 'text' => ''];
        }
        $headers[] = (object)[
            'align' => 'center',
            'text'  => '<strong>' . get_string('total', 'questionnaire') . '</strong>',
        ];
        if ($isrestricted) {
            $headers[] = (object)['align' => 'center', 'text' => get_string('notapplicable', 'questionnaire')];
        }
        if ($isna) {
            $headers[] = (object)['align' => 'center', 'text' => get_string('notapplicable', 'questionnaire')];
        }
        return $headers;
    }

    /**
     * Build the columns of one choice row in the counts table.
     *
     * The headers array is iterated via reset()/next() to consume one header per emitted column.
     *
     * @param string $content
     * @param array $rank
     * @param array $rows
     * @param array $headers
     * @param bool $osgood
     * @param bool $isna
     * @param bool $isrestricted
     * @param int $nbresponses
     * @return \stdClass[]
     */
    private function build_totals_choice_row(
        string $content,
        array $rank,
        array $rows,
        array $headers,
        bool $osgood,
        bool $isna,
        bool $isrestricted,
        int $nbresponses
    ): array {
        $nbna = $this->counts[$content]->nbna;
        $total = $this->counts[$content]->num;
        $nbresp = '<strong>(' . $total . ')</strong>';

        $contentright = '';
        if ($osgood) {
            [$content, $contentright] = array_merge(preg_split('/[|]/', $content), [' ']);
        } else {
            $parsed = question::parse_choice_content($content);
            if ($parsed->modname) {
                $content = $parsed->text;
            }
        }
        if (isset($rows[$content]) && isset($rows[$content]->isother) && $rows[$content]->isother) {
            $content = get_string('other', 'questionnaire') . ' ' . $content;
        }

        $cols = [];
        $header = reset($headers);
        $cols[] = (object)[
            'align' => $header->align,
            'text'  => format_text($content, FORMAT_HTML, ['noclean' => true, 'filter' => false]),
        ];

        // Rank/rate numbers.
        $maxrank = max($rank);
        for ($i = 1; $i <= $this->question->length(); $i++) {
            $str = $rank[$i] ?? 0;
            $percent = '';
            if ($total !== 0 && $str !== 0) {
                $percent = ' (<span class="percent">' . number_format(($str * 100) / $total) . '%</span>)';
            }
            if ($str == $maxrank) {
                $str = '<strong>' . $str . '</strong>';
            }
            $header = next($headers);
            $cols[] = (object)['align' => $header->align, 'text' => $str . $percent];
        }

        if ($osgood) {
            $header = next($headers);
            $cols[] = (object)[
                'align' => $header->align,
                'text'  => format_text($contentright, FORMAT_HTML, ['noclean' => true]),
            ];
        }

        $header = next($headers);
        $cols[] = (object)['align' => $header->align, 'text' => $nbresp];

        if ($isrestricted) {
            $header = next($headers);
            $cols[] = (object)['align' => $header->align, 'text' => $nbresponses - $total];
        }

        if (!$osgood && $isna) {
            $header = next($headers);
            $cols[] = (object)['align' => $header->align, 'text' => $nbna];
        }

        return $cols;
    }

    /**
     * Sorting function for ascending.
     * @param \stdClass $a
     * @param \stdClass $b
     * @return int
     */
    private static function sortavgasc($a, $b) {
        if (isset($a->avg) && isset($b->avg)) {
            if ($a->avg < $b->avg) {
                return -1;
            } else if ($a->avg > $b->avg) {
                return 1;
            } else {
                return 0;
            }
        }
    }

    /**
     * Sorting function for descending.
     * @param \stdClass $a
     * @param \stdClass $b
     * @return int
     */
    private static function sortavgdesc($a, $b) {
        if (isset($a->avg) && isset($b->avg)) {
            if ($a->avg > $b->avg) {
                return -1;
            } else if ($a->avg < $b->avg) {
                return 1;
            } else {
                return 0;
            }
        }
    }
}
