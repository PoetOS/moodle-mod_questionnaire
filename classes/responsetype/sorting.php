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

namespace mod_questionnaire\responsetype;

use mod_questionnaire\db\bulk_sql_config;

/**
 * Class for sorting response types.
 *
 * @author The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questionnaire
 */
class sorting extends text {
    /**
     * Name of response table <questionnaire_response_sort>.
     *
     * @return string
     */
    public static function response_table() {
        return 'questionnaire_response_sort';
    }

    /**
     * Provide an array of answer objects from web form data for the question.
     *
     * @param array $responsedata All of the responsedata as an object.
     * @param \stdClass $question sorting type.
     * @return array An array of answer objects.
     */
    public static function answers_from_webform($responsedata, $question) {
        $answers = [];
        $values = [];
        foreach ($responsedata as $key => $data) {
            // Get value from input field by name is 'q1-1'.
            if (preg_match('/q' . $question->id . '\-\d/', $key)) {
                $values[] = $data;
            }
        }
        if (count($values) > 0) {
            $record = new \stdClass();
            $record->responseid = $responsedata->rid;
            $record->questionid = $question->id;
            $record->value = implode(',', $values);
            $answers[] = answer\answer::create_from_data($record);
        }
        return $answers;
    }

    /**
     * Provide an array of answer objects from mobile data for the question.
     *
     * @param \stdClass $responsedata All the responsedata as an object.
     * @param \stdClass $question sorting type.
     * @return array \mod_questionnaire\responsetype\answer\answer An array of answer objects.
     */
    public static function answers_from_appdata($responsedata, $question) {
        $answers = [];
        $qname = 'q' . $question->id;
        if (isset($responsedata->{$qname}[0]) && !empty($responsedata->{$qname}[0])) {
            $record = new \stdClass();
            $record->responseid = $responsedata->rid;
            $record->questionid = $question->id;
            $record->value = '';
            if (!empty($responsedata->{$qname}[0])) {
                $record->value = $responsedata->{$qname}[0];
            }
            $answers[] = answer\answer::create_from_data($record);
        }
        return $answers;
    }

    /**
     * Get response answer for sorting question.
     *
     * @param bool $rids response question
     * @param bool $anonymous user.
     * @return array result the answer.
     * @throws \dml_exception
     */
    public function get_results($rids = [], $anonymous = false) {
        global $DB;

        $rsql = "";
        if (!empty($rids)) {
            list($rsql, $params) = $DB->get_in_or_equal($rids);
            $rsql = " AND response_id " . $rsql;
        }
        $userfields = [];
        foreach (\core_user\fields::get_name_fields() as $field) {
            $userfields[] = "u.{$field}";
        }
        $sqluserfields = implode(', ', $userfields);
        if ($anonymous) {
            $sql = "SELECT t.id, t.response, r.submitted AS submitted, " .
                "r.questionnaireid, r.id AS rid " .
                "FROM {" . static::response_table() . "} t, " .
                "{questionnaire_response} r " .
                "WHERE question_id=" . $this->question->id . $rsql .
                " AND t.response_id = r.id " .
                "ORDER BY r.submitted DESC";
        } else {
            $sql = "SELECT t.id, t.response, r.submitted AS submitted,
                           r.userid,
                           u.id as usrid,
                           r.questionnaireid,
                           r.id AS rid,
                           q.extradata,
                           " . $sqluserfields . "
                      FROM {" . self::response_table() . "} t,
                           {questionnaire_response} r,
                           {questionnaire_question} q,
                           {user} u
                     WHERE t.response_id = r.id
                       AND q.id = t.question_id
                       AND t.question_id = ? " . $rsql . "
                       AND u.id = r.userid
                  ORDER BY u.lastname, u.firstname, r.submitted;";
        }
        $params = array_merge([$this->question->id], $params);
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Provide a template for results screen if defined.
     *
     * @param bool $pdf printing.
     * @return string The template string.
     */
    public function results_template($pdf = false) {
        if ($pdf) {
            return 'mod_questionnaire/resultspdf_sorting';
        }
        return 'mod_questionnaire/results_sorting';
    }

    /**
     * Display answer results.
     *
     * @param array $rids response ids of question.
     * @param string $sort
     * @param bool $anonymous
     * @return \stdClass result tags.
     * @throws \coding_exception
     */
    public function display_results($rids = [], $sort = '', $anonymous = false) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        $pagetags = new \stdClass();
        if ($rows = $this->get_results($rids, $anonymous)) {
            $numrespondents = count($rids);
            $numresponses = count($rows);
            $pagetags = $this->get_results_tags($rows, $numrespondents, $numresponses, $prtotal);
        }
        return $pagetags;
    }

    /**
     * Override the results tags function for templates for questions with dates.
     *
     * @param array $rows
     * @param int $participants Number of questionnaire participants.
     * @param int $respondents Number of question respondents.
     * @param int $showtotals
     * @param string $sort
     * @return \stdClass
     * @throws \coding_exception
     */
    public function get_results_tags($rows, $participants, $respondents, $showtotals = 1, $sort = '') {
        $pagetags = new \stdClass();
        if ($respondents == 0) {
            return $pagetags;
        }

        if (is_object(reset($rows))) {
            global $SESSION, $questionnaire;
            $viewsingleresponse = $questionnaire->capabilities->viewsingleresponse;
            $nonanonymous = $questionnaire->respondenttype != 'anonymous';
            $uri = '/mod/questionnaire/report.php';
            $urlparams = [];
            if ($viewsingleresponse && $nonanonymous) {
                $currentgroupid = '';
                if (isset($SESSION->questionnaire->currentgroupid)) {
                    $currentgroupid = $SESSION->questionnaire->currentgroupid;
                }
                $urlparams['action'] = 'vresp';
                $urlparams['sid'] = $questionnaire->survey->id;
                $urlparams['currentgroupid'] = $currentgroupid;
                $url = new \moodle_url($uri, $urlparams);
            }
            $evencolor = false;
            foreach ($rows as $row) {
                $response = new \stdClass();
                $response->respondent = '';
                $response->sorting = format_text($row->response, FORMAT_HTML);
                if ($viewsingleresponse && $nonanonymous) {
                    $urlparams['rid'] = $row->rid;
                    $urlparams['individualresponse'] = 1;
                    $url = new \moodle_url($uri, $urlparams);
                    $response->respondent = \html_writer::link($url, fullname($row), ['title' => userdate($row->submitted)]);
                }
                // The 'evencolor' attribute is used by the PDF template.
                $response->evencolor = $evencolor;

                // Preparing data to display anwser.
                $extradata = !empty($row->extradata) ? json_decode($row->extradata) : '';
                $response->qelements = new \stdClass();
                $response->qelements->sortinglist = $this->prepare_answers($extradata->answers, $row->response);
                $response->qelements->qid = 'q' . $row->rid;
                $response->qelements->isresponse = true;
                $layoutdirection = $extradata->sortingdirection ?? QUESTIONNAIRE_LAYOUT_VERTICAL;
                $layout = $this->questionnaire_sort_type_layout()[$layoutdirection];
                $response->qelements->sortingdirection = strtolower($layout);
                $pagetags->responses[] = (object)['response' => $response];
                $evencolor = !$evencolor;
            }

            if ($showtotals == 1) {
                $pagetags->total = new \stdClass();
                $pagetags->total->total = "$respondents/$participants";
            }
        }
        return $pagetags;
    }

    /**
     * Return an array of answers by question/choice for the given response. Must be implemented by the subclass.
     *
     * @param int $rid The response id.
     * @return array response values.
     * @throws \dml_exception
     */
    public static function response_select($rid) {
        global $DB;

        $values = [];
        $sql = "SELECT q.id, q.content, a.response as aresponse " .
               "FROM {" . static::response_table() . "} a, {questionnaire_question} q " .
               "WHERE a.response_id=? AND a.question_id=q.id ";
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $qid => $row) {
            unset($row->id);
            $row = (array)$row;
            $newrow = [];
            foreach ($row as $key => $val) {
                if (!is_numeric($key)) {
                    $newrow[] = $val;
                }
            }
            $values[$qid] = $newrow;
            $val = array_pop($values[$qid]);
            array_push($values[$qid], $val, $val);
        }

        return $values;
    }

    /**
     * Return an array of answer objects by question for the given response id.
     * THIS SHOULD REPLACE response_select.
     *
     * @param int $rid The response id.
     * @return array array answer.
     * @throws \dml_exception
     */
    public static function response_answers_by_question($rid) {
        global $DB;

        $answers = [];
        $sql = "SELECT id, response_id as responseid, question_id as questionid, 0 as choiceid, response as value " .
               "FROM {" . static::response_table() ."} " .
               "WHERE response_id = ? ";
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $record) {
            $answers[$record->questionid][] = answer\answer::create_from_data($record);
        }

        return $answers;
    }

    /**
     * Configure bulk sql.
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config(static::response_table(), 'qrt', false, true, false);
    }

    /**
     * Preparing sorting answers.
     *
     * @param array|null $answers of questions. [array of \stdClass with properties: index, text].
     * @param string|null $responses join with (,) character. Example: 0,1,2.
     * @param bool $istext
     * @return array|string data sorting question.
     */
    public function prepare_answers(array $answers,
            ?string $responses = null, bool $istext = false) {
        $sortinglist = [];
        if (!empty($responses)) {
            foreach (explode(',', $responses) as $index) {
                if (isset($answers[$index])) {
                    $sortinglist[] = [
                            'index' => $index,
                            'text' => format_text($answers[$index]->text, $answers[$index]->format),
                            'title' => strip_tags($answers[$index]->text)
                    ];
                }
            };
        } else {
            foreach ($answers as $index => $answer) {
                $sortinglist[] = [
                        'index' => $index,
                        'text' => format_text($answer->text, $answer->format),
                        'title' => strip_tags($answers[$index]->text)
                ];
            };
        }

        if ($istext) {
            $newsortinglist = array_map(function($item) {
                return strip_tags($item['text']);
            }, $sortinglist);
            $sortinglist = join(' ', $newsortinglist);
        }
        return $sortinglist;
    }

    /**
     * Get key and value of type question layout.
     *
     * @return array
     */
    public function questionnaire_sort_type_layout(): array {
        return [
                QUESTIONNAIRE_LAYOUT_VERTICAL => get_string(QUESTIONNAIRE_LAYOUT_VERTICAL_VALUE, 'mod_questionnaire'),
                QUESTIONNAIRE_LAYOUT_HORIZONTAL => get_string(QUESTIONNAIRE_LAYOUT_HORIZONTAL_VALUE, 'mod_questionnaire'),
        ];
    }
}
