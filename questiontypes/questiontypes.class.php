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

/**
 * Class for describing a question
 *
 * @author Mike Churchward
 * @package questiontypes
 */

 // Constants.
define('QUESCHOOSE', 0);
define('QUESYESNO', 1);
define('QUESTEXT', 2);
define('QUESESSAY', 3);
define('QUESRADIO', 4);
define('QUESCHECK', 5);
define('QUESDROP', 6);
define('QUESRATE', 8);
define('QUESDATE', 9);
define('QUESNUMERIC', 10);
define('QUESPAGEBREAK', 99);
define('QUESSECTIONTEXT', 100);

GLOBAL $qtypenames;
$qtypenames = array(
        QUESYESNO => 'yesno',
        QUESTEXT => 'text',
        QUESESSAY => 'essay',
        QUESRADIO => 'radio',
        QUESCHECK => 'check',
        QUESDROP => 'drop',
        QUESRATE => 'rate',
        QUESDATE => 'date',
        QUESNUMERIC => 'numeric',
        QUESPAGEBREAK => 'pagebreak',
        QUESSECTIONTEXT => 'sectiontext'
        );
GLOBAL $idcounter, $CFG;
$idcounter = 0;

require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

class questionnaire_question {

    // Class Properties.
    /*
     * The database id of this question.
     * @var int $id
     */
     public $id          = 0;

    /**
     * The database id of the survey this question belongs to.
     * @var int $survey_id
     */
     public $surveyid   = 0;

    /**
     * The name of this question.
     * @var string $name
     */
     public $name        = '';

    /**
     * The alias of the number of this question.
     * @var string $numberalias
     */

    /**
     * The name of the question type.
     * @var string $type
     */
     public $type        = '';

    /**
     * Array holding any choices for this question.
     * @var array $choices
     */
     public $choices     = array();

    /**
     * The table name for responses.
     * @var string $response_table
     */
     public $responsetable = '';

    /**
     * The length field.
     * @var int $length
     */
     public $length      = 0;

    /**
     * The precision field.
     * @var int $precise
     */
     public $precise     = 0;

    /**
     * Position in the questionnaire
     * @var int $position
     */
     public $position    = 0;

    /**
     * The question's content.
     * @var string $content
     */
     public $content     = '';

    /**
     * The list of all question's choices.
     * @var string $allchoices
     */
     public $allchoices  = '';

    /**
     * The required flag.
     * @var boolean $required
     */
     public $required    = 'n';

    /**
     * The deleted flag.
     * @var boolean $deleted
     */
     public $deleted     = 'n';

    // Class Methods.

    /**
     * The class constructor
     *
     */
    public function __construct($id = 0, $question = null, $context = null) {
        global $DB;
        static $qtypes = null;

        if (is_null($qtypes)) {
            $qtypes = $DB->get_records('questionnaire_question_type', array(), 'typeid',
                                       'typeid, type, has_choices, response_table');
        }

        if ($id) {
            $question = $DB->get_record('questionnaire_question', array('id' => $id));
        }

        if (is_object($question)) {
            $this->id = $question->id;
            $this->survey_id = $question->survey_id;
            $this->name = $question->name;
            // Added for skip feature.
            $this->dependquestion = $question->dependquestion;
            $this->dependchoice = $question->dependchoice;
            $this->length = $question->length;
            $this->precise = $question->precise;
            $this->position = $question->position;
            $this->content = $question->content;
            $this->required = $question->required;
            $this->deleted = $question->deleted;

            $this->type_id = $question->type_id;
            $this->type = $qtypes[$this->type_id]->type;
            $this->response_table = $qtypes[$this->type_id]->response_table;
            if ($qtypes[$this->type_id]->has_choices == 'y') {
                $this->get_choices();
            }
        }
        $this->context = $context;
    }

    private function get_choices() {
        global $DB;

        if ($choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $this->id), 'id ASC')) {
            foreach ($choices as $choice) {
                $this->choices[$choice->id] = new stdClass();
                $this->choices[$choice->id]->content = $choice->content;
                $this->choices[$choice->id]->value = $choice->value;
            }
        } else {
            $this->choices = array();
        }
    }

    // Storage Methods.
    // The following methods are defined by the tables they use. Questions should call the
    // appropriate function based on its table.

    public function insert_response($rid) {
        $method = 'insert_'.$this->response_table;
        if (method_exists($this, $method)) {
            return $this->$method($rid);
        } else {
            return false;
        }
    }

    private function insert_response_bool($rid) {
        global $DB;
        $val = optional_param('q'.$this->id, '', PARAM_ALPHANUMEXT);
        if (!empty($val)) { // If "no answer" then choice is empty (CONTRIB-846).
            $record = new Object();
            $record->response_id = $rid;
            $record->question_id = $this->id;
            $record->choice_id = $val;
            return $DB->insert_record('questionnaire_'.$this->response_table, $record);
        } else {
            return false;
        }
    }

    private function insert_response_text($rid) {
        global $DB;
        $val = optional_param('q'.$this->id, '', PARAM_CLEAN);
        // Only insert if non-empty content.
        if ($this->type_id == QUESNUMERIC) {
            $val = preg_replace("/[^0-9.\-]*(-?[0-9]*\.?[0-9]*).*/", '\1', $val);
        }

        if (preg_match("/[^ \t\n]/", $val)) {
            $record = new Object();
            $record->response_id = $rid;
            $record->question_id = $this->id;
            $record->response = $val;
            return $DB->insert_record('questionnaire_'.$this->response_table, $record);
        } else {
            return false;
        }
    }

    private function insert_response_date($rid) {
        global $DB;
        $val = optional_param('q'.$this->id, '', PARAM_CLEAN);
        $checkdateresult = questionnaire_check_date($val);
        $thisdate = $val;
        if (substr($checkdateresult, 0, 5) == 'wrong') {
            return false;
        }
        // Now use ISO date formatting.
        $checkdateresult = questionnaire_check_date($thisdate, $insert = true);
        $record = new Object();
        $record->response_id = $rid;
        $record->question_id = $this->id;
        $record->response = $checkdateresult;
        return $DB->insert_record('questionnaire_'.$this->response_table, $record);
    }

    private function insert_resp_single($rid) {
        global $DB;
        $val = optional_param('q'.$this->id, null, PARAM_CLEAN);
        if (!empty($val)) {
            foreach ($this->choices as $cid => $choice) {
                if (strpos($choice->content, '!other') === 0) {
                    $other = optional_param('q'.$this->id.'_'.$cid, null, PARAM_CLEAN);
                    if (!isset($other)) {
                        continue;
                    }
                    if (preg_match("/[^ \t\n]/", $other)) {
                        $record = new Object();
                        $record->response_id = $rid;
                        $record->question_id = $this->id;
                        $record->choice_id = $cid;
                        $record->response = $other;
                        $resid = $DB->insert_record('questionnaire_response_other', $record);
                        $val = $cid;
                        break;
                    }
                }
            }
        }
        if (preg_match("/other_q([0-9]+)/", (isset($val) ? $val : ''), $regs)) {
            $cid = $regs[1];
            if (!isset($other)) {
                break; // Out of the case.
                $other = optional_param('q'.$this->id.'_'.$cid, null, PARAM_CLEAN);
            }
            if (preg_match("/[^ \t\n]/", $other)) {
                $record = new object;
                $record->response_id = $rid;
                $record->question_id = $this->id;
                $record->choice_id = $cid;
                $record->response = $other;
                $resid = $DB->insert_record('questionnaire_response_other', $record);
                $val = $cid;
            }
        }
        $record = new Object();
        $record->response_id = $rid;
        $record->question_id = $this->id;
        $record->choice_id = isset($val) ? $val : 0;
        if ($record->choice_id) {// If "no answer" then choice_id is empty (CONTRIB-846).
            return $DB->insert_record('questionnaire_'.$this->response_table, $record);
        } else {
            return false;
        }
    }

    private function insert_resp_multiple($rid) {
        global $DB;
        $resid = '';
        $val = optional_param_array('q'.$this->id, null, PARAM_CLEAN);
        foreach ($this->choices as $cid => $choice) {
            if (strpos($choice->content, '!other') === 0) {
                $other = optional_param('q'.$this->id.'_'.$cid, '', PARAM_CLEAN);
                if (empty($other)) {
                    continue;
                }
                if (!isset($val)) {
                    $val = array($cid);
                } else {
                    array_push($val, $cid);
                }
                if (preg_match("/[^ \t\n]/", $other)) {
                    $record = new Object();
                    $record->response_id = $rid;
                    $record->question_id = $this->id;
                    $record->choice_id = $cid;
                    $record->response = $other;
                    $resid = $DB->insert_record('questionnaire_response_other', $record);
                }
            }
        }

        if (!isset($val) || count($val) < 1) {
            return false;
        }

        foreach ($val as $cid) {
            $cid = clean_param($cid, PARAM_CLEAN);
            if ($cid != 0) { // Do not save response if choice is empty.
                if (preg_match("/other_q[0-9]+/", $cid)) {
                    continue;
                }
                $record = new Object();
                $record->response_id = $rid;
                $record->question_id = $this->id;
                $record->choice_id = $cid;
                $resid = $DB->insert_record('questionnaire_'.$this->response_table, $record);
            }
        }
        return $resid;
    }

    private function insert_response_rank($rid) {
        global $DB;
        $val = optional_param('q'.$this->id, null, PARAM_CLEAN);
        if ($this->type_id == QUESRATE) {
            $resid = false;
            foreach ($this->choices as $cid => $choice) {
                $other = optional_param('q'.$this->id.'_'.$cid, null, PARAM_CLEAN);
                // Choice not set or not answered.
                if (!isset($other) || $other == '') {
                    continue;
                }
                if ($other == get_string('notapplicable', 'questionnaire')) {
                    $rank = -1;
                } else {
                    $rank = intval($other);
                }
                $record = new Object();
                $record->response_id = $rid;
                $record->question_id = $this->id;
                $record->choice_id = $cid;
                $record->rank = $rank;
                $resid = $DB->insert_record('questionnaire_'.$this->response_table, $record);
            }
            return $resid;
        } else { // THIS SHOULD NEVER HAPPEN.
            $r = $val;
            if ($val == get_string('notapplicable', 'questionnaire')) {
                $rank = -1;
            } else {
                $rank = intval($val);
            }
            $record = new Object();
            $record->response_id = $rid;
            $record->question_id = $this->id;
            $record->rank = $rank;
            return $DB->insert_record('questionnaire_'.$this->response_table, $record);
        }
    }


    // Results Methods.
    // The following methods are defined by the tables they use. Questions should call the
    // appropriate function based on its table.

    private function get_results($rids=false) {

        $method = 'get_'.$this->response_table.'_results';
        if (method_exists($this, $method)) {
            return $this->$method($rids);
        } else {
            return false;
        }
    }

    private function get_response_bool_results($rids=false) {
        global $DB;
        global $CFG;

        $rsql = '';
        $params = array($this->id);
        if (!empty($rids)) {
            list($rsql, $rparams) = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND response_id ' . $rsql;
        }
        $params[] = '';

        $sql = 'SELECT choice_id, COUNT(response_id) AS num ' .
               'FROM {questionnaire_' . $this->response_table . '} ' .
               'WHERE question_id= ? ' . $rsql . ' AND choice_id != ? ' .
               'GROUP BY choice_id';
        return $DB->get_records_sql($sql, $params);
    }

    private function get_response_text_results($rids = false) {
        global $DB;

        $rsql = '';
        if (!empty($rids)) {
            list($rsql, $params) = $DB->get_in_or_equal($rids);
            $rsql = ' AND response_id ' . $rsql;
        }

        $sql = 'SELECT T.id, T.response, R.submitted AS submitted, R.username, U.username AS username, ' .
                'U.id as userid, ' .
                'R.survey_id, R.id AS rid ' .
                'FROM {questionnaire_'. $this->response_table.'} T, ' .
                '{questionnaire_response} R, ' .
                '{user} U ' .
                'WHERE question_id=' . $this->id . $rsql .
                ' AND T.response_id = R.id' .
                ' AND U.id = ' . $DB->sql_cast_char2int('R.username') .
                'ORDER BY U.lastname, U.firstname, R.submitted';
        return $DB->get_records_sql($sql, $params);
    }

    private function get_response_date_results($rids = false) {
        global $DB;

        $rsql = '';
        $params = array($this->id);
        if (!empty($rids)) {
            list($rsql, $rparams) = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND response_id ' . $rsql;
        }

        $sql = 'SELECT id, response ' .
               'FROM {questionnaire_' . $this->response_table . '} ' .
               'WHERE question_id= ? ' . $rsql;

        return $DB->get_records_sql($sql, $params);
    }

    private function get_response_single_results($rids=false) {
        global $CFG;
        global $DB;

        $rsql = '';
        $params = array($this->id);
        if (!empty($rids)) {
            list($rsql, $rparams) = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND response_id ' . $rsql;
        }
        // Added qc.id to preserve original choices ordering.
        $sql = 'SELECT rt.id, qc.id as cid, qc.content ' .
               'FROM {questionnaire_quest_choice} qc, ' .
               '{questionnaire_' . $this->response_table . '} rt ' .
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
                $rows['other'.$i] = new stdClass();
                $rows['other'.$i]->content = $rec->content;
                $rows['other'.$i]->response = $rec->response;
                $i++;
            }
        }

        return $rows;
    }

    private function get_response_multiple_results($rids) {
        return $this->get_response_single_results($rids); // Both functions are equivalent.
    }

    private function get_response_rank_results($rids=false) {
        global $CFG;
        global $DB;

        $rsql = '';
        if (!empty($rids)) {
            list($rsql, $params) = $DB->get_in_or_equal($rids);
            $rsql = ' AND response_id ' . $rsql;
        }

        if ($this->type_id == QUESRATE) {
            // JR there can't be an !other field in rating questions ???
            $rankvalue = array();
            $select = 'question_id=' . $this->id . ' AND content NOT LIKE \'!other%\' ORDER BY id ASC';
            if ($rows = $DB->get_records_select('questionnaire_quest_choice', $select)) {
                foreach ($rows as $row) {
                    $this->counts[$row->content] = new stdClass();
                    $nbna = $DB->count_records('questionnaire_response_rank', array('question_id' => $this->id,
                                    'choice_id' => $row->id, 'rank' => '-1'));
                    $this->counts[$row->content]->nbna = $nbna;
                    // The $row->value may be null (i.e. empty) or have a 'NULL' value.
                    if ($row->value !== null && $row->value !== 'NULL') {
                        $rankvalue[] = $row->value;
                    }
                }
            }

            $isrestricted = ($this->length < count($this->choices)) && $this->precise == 2;
            // Usual case.
            if (!$isrestricted) {
                if (!empty ($rankvalue)) {
                    $sql = "SELECT r.id, c.content, r.rank, c.id AS choiceid
                    FROM {$CFG->prefix}questionnaire_quest_choice c, {$CFG->prefix}questionnaire_{$this->response_table} r
                    WHERE r.choice_id = c.id
                    AND c.question_id = " . $this->id . "
                    AND r.rank >= 0{$rsql}
                    ORDER BY choiceid";
                    $results = $DB->get_records_sql($sql, $params);
                    $value = array();
                    foreach ($results as $result) {
                        if (isset ($value[$result->choiceid])) {
                            $value[$result->choiceid] += $rankvalue[$result->rank];
                        } else {
                            $value[$result->choiceid] = $rankvalue[$result->rank];
                        }
                    }
                }

                $sql = "SELECT c.id, c.content, a.average, a.num
                        FROM {questionnaire_quest_choice} c
                        INNER JOIN
                             (SELECT c2.id, AVG(a2.rank+1) AS average, COUNT(a2.response_id) AS num
                              FROM {questionnaire_quest_choice} c2, {$CFG->prefix}questionnaire_{$this->response_table} a2
                              WHERE c2.question_id = ? AND a2.question_id = ? AND a2.choice_id = c2.id AND a2.rank >= 0{$rsql}
                              GROUP BY c2.id) a ON a.id = c.id
                              order by c.id";
                $results = $DB->get_records_sql($sql, array_merge(array($this->id, $this->id), $params));
                if (!empty ($rankvalue)) {
                    foreach ($results as $key => $result) {
                        $result->averagevalue = $value[$key] / $result->num;
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
                             (SELECT c2.id, SUM(a2.rank+1) AS sum, COUNT(a2.response_id) AS num
                              FROM {questionnaire_quest_choice} c2, {$CFG->prefix}questionnaire_{$this->response_table} a2
                              WHERE c2.question_id = ? AND a2.question_id = ? AND a2.choice_id = c2.id AND a2.rank >= 0{$rsql}
                              GROUP BY c2.id) a ON a.id = c.id";
                $results = $DB->get_records_sql($sql, array_merge(array($this->id, $this->id), $params));
                // Formula to calculate the best ranking order.
                $nbresponses = count($rids);
                foreach ($results as $key => $result) {
                    $result->average = ($result->sum + ($nbresponses - $result->num) * ($this->length + 1)) / $nbresponses;
                    $results[$result->content] = $result;
                    unset($results[$key]);
                }
                return $results;
            }
        } else {
            $sql = 'SELECT A.rank, COUNT(A.response_id) AS num ' .
                   'FROM {questionnaire_' . $this->response_table . '} A ' .
                   'WHERE A.question_id= ? ' . $rsql . ' ' .
                   'GROUP BY A.rank';
            return $DB->get_records_sql($sql, array_merge(array($this->id), $params));
        }
    }

    // Display Methods.

    public function display_results($rids=false,  $sort) {
        $method = 'display_'.$this->response_table.'_results';
        if (method_exists($this, $method)) {
            $a = $this->$method($rids, $sort);
            return $a;
        } else {
            return false;
        }
    }

    private function display_response_bool_results($rids=false) {
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
        if ($rows = $this->get_response_bool_results($rids)) {
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
            $this->mkrespercent(count($rids), $this->precise, $prtotal, $sort = '');
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    private function display_response_text_results($rids = false) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_response_text_results($rids)) {
            // Count identical answers (numeric questions only).
            foreach ($rows as $row) {
                if (!empty($row->response) || $row->response === "0") {
                    $this->text = $row->response;
                    $textidx = clean_text($this->text);
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                    $this->userid[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $isnumeric = $this->type_id == QUESNUMERIC;
            if ($isnumeric) {
                $this->mkreslistnumeric(count($rids), $this->precise);
            } else {
                $this->mkreslisttext($rows);
            }
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    private function display_response_date_results($rids = false) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_response_date_results($rids)) {
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
            $this->mkreslistdate(count($rids), $this->precise, $prtotal);
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    private function display_resp_single_results($rids=false, $sort) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_response_single_results($rids)) {
            foreach ($rows as $idx => $row) {
                if (strpos($idx, 'other') === 0) {
                    $answer = $row->response;
                    $ccontent = $row->content;
                    $content = preg_replace(array('/^!other=/', '/^!other/'),
                            array('', get_string('other', 'questionnaire')), $ccontent);
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
            $this->mkrespercent(count($rids), $this->precise, $prtotal, $sort);
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    private function display_resp_multiple_results($rids=false, $sort) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_response_multiple_results($rids)) {
            foreach ($rows as $idx => $row) {
                if (strpos($idx, 'other') === 0) {
                    $answer = $row->response;
                    $ccontent = $row->content;
                    $content = preg_replace(array('/^!other=/', '/^!other/'),
                            array('', get_string('other', 'questionnaire')), $ccontent);
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

            $this->mkrespercent(count($rids), $this->precise, 0, $sort);
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    private function display_response_rank_results($rids=false, $sort) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }

        if ($rows = $this->get_response_rank_results($rids, $sort)) {
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
                        if ($this->precise == 3) { // Osgood's semantic differential.
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
            $this->mkresavg(count($rids), $this->precise, $prtotal, $this->length, $sort, $stravgvalue);

            $this->mkrescount($rids, $rows, $this->precise, $this->length, $sort);
        } else {
            echo '<p class="generaltable">&nbsp;'.get_string('noresponsedata', 'questionnaire').'</p>';
        }
    }

    private function question_display($formdata, $descendantsdata, $qnum='', $blankquestionnaire) {
        global $qtypenames;

        $method = $qtypenames[$this->type_id].'_survey_display';
        if (method_exists($this, $method)) {
            $this->questionstart_survey_display($qnum, $formdata, $descendantsdata);
            $this->$method($formdata, $descendantsdata, $blankquestionnaire);
            $this->questionend_survey_display($qnum);
        } else {
            print_error('displaymethod', 'questionnaire');
        }
    }

    public function survey_display($formdata, $descendantsdata, $qnum='', $blankquestionnaire=false) {
        $this->question_display($formdata, $descendantsdata, $qnum, $blankquestionnaire);
    }

    public function questionstart_survey_display($qnum, $formdata='') {
        global $OUTPUT, $SESSION, $questionnaire, $PAGE;
        $currenttab = $SESSION->questionnaire->current_tab;
        $pagetype = $PAGE->pagetype;
        $skippedquestion = false;
        $skippedclass = '';
        $autonum = $questionnaire->autonum;
        // If no questions autonumbering.
        $nonumbering = false;
        if ($autonum != 1 && $autonum != 3) {
            $qnum = '';
            $nonumbering = true;
        }
        // If we are on report page and this questionnaire has dependquestions and this question was skipped.
        if ( ($pagetype == 'mod-questionnaire-myreport' || $pagetype == 'mod-questionnaire-report')
                        && $nonumbering == false
                        && $formdata
                        && $this->dependquestion != 0 && !array_key_exists('q'.$this->id, $formdata)) {
            $skippedquestion = true;
            $skippedclass = ' unselected';
            $qnum = '<span class="'.$skippedclass.'">('.$qnum.')</span>';
        }
        // In preview mode, hide children questions that have not been answered.
        // In report mode, If questionnaire is set to no numbering,
        // also hide answers to questions that have not been answered.
        $displayclass = 'qn-container';
        if ($pagetype == 'mod-questionnaire-preview' || ($nonumbering
                        && ($currenttab == 'mybyresponse' || $currenttab == 'individualresp'))) {
            $parent = questionnaire_get_parent ($this);
            if ($parent) {
                $dependquestion = $parent[$this->id]['qdependquestion'];
                $dependchoice = $parent[$this->id]['qdependchoice'];
                $parenttype = $parent[$this->id]['parenttype'];
                $displayclass = 'hidedependquestion';
                if (isset($formdata->{'q'.$this->id}) && $formdata->{'q'.$this->id}) {
                    $displayclass = 'qn-container';
                }

                if ($this->type_id == QUESRATE) {
                    foreach ($this->choices as $key => $choice) {
                        if (isset($formdata->{'q'.$this->id.'_'.$key})) {
                            $displayclass = 'qn-container';
                            break;
                        }
                    }
                }

                if (isset($formdata->$dependquestion) && $formdata->$dependquestion == $dependchoice) {
                    $displayclass = 'qn-container';
                }

                if ($parenttype == QUESDROP) {
                    $qnid = preg_quote('qn-'.$this->id, '/');
                    if (isset($formdata->$dependquestion) && preg_match("/$qnid/", $formdata->$dependquestion)) {
                        $displayclass = 'qn-container';
                    }
                }
            }
        }

        echo html_writer::start_tag('fieldset', array('class' => $displayclass, 'id' => 'qn-'.$this->id));
        echo html_writer::start_tag('legend', array('class' => 'qn-legend'));

        // Do not display the info box for the label question type.
        if ($this->type_id != QUESSECTIONTEXT) {
            if (!$nonumbering) {
                echo html_writer::start_tag('div', array('class' => 'qn-info'));
                echo html_writer::start_tag('div', array('class' => 'accesshide'));
                echo get_string('questionnum', 'questionnaire');
                echo html_writer::end_tag('div');
                echo html_writer::tag('h2', $qnum, array('class' => 'qn-number'));
                echo html_writer::end_tag('div');
            }
            $required = '';
            if ($this->required == 'y') {
                $required = html_writer::start_tag('div', array('class' => 'accesshide'));
                $required .= get_string('required', 'questionnaire');
                $required .= html_writer::end_tag('div');
                $required .= html_writer::empty_tag('img',
                        array('class' => 'req',
                                'title' => get_string('required', 'questionnaire'),
                                'alt' => get_string('required', 'questionnaire'),
                                'src' => $OUTPUT->pix_url('req')));
            }
            echo $required;
        }
        // If question text is "empty", i.e. 2 non-breaking spaces were inserted, empty it.
        if ($this->content == '<p>  </p>') {
            $this->content = '';
        }
        echo html_writer::end_tag('legend');
        echo html_writer::start_tag('div', array('class' => 'qn-content'));
        echo html_writer::start_tag('div', array('class' => 'qn-question '.$skippedclass));
        if ($this->type_id == QUESNUMERIC || $this->type_id == QUESTEXT ||
            $this->type_id == QUESDROP) {
            echo html_writer::start_tag('label', array('for' => $this->type . $this->id));
        }
        if ($this->type_id == QUESESSAY) {
            echo html_writer::start_tag('label', array('for' => 'edit-q' . $this->id));
        }
        $options = array('noclean' => true, 'para' => false, 'filter' => true, 'context' => $this->context, 'overflowdiv' => true);
        echo format_text(file_rewrite_pluginfile_urls($this->content, 'pluginfile.php',
            $this->context->id, 'mod_questionnaire', 'question', $this->id), FORMAT_HTML, $options);
        if ($this->type_id == QUESNUMERIC || $this->type_id == QUESTEXT ||
            $this->type_id == QUESESSAY || $this->type_id == QUESDROP) {
            echo html_writer::end_tag('label');
        }
        echo html_writer::end_tag('div');
        echo html_writer::start_tag('div', array('class' => 'qn-answer'));
    }

    public function questionend_survey_display() {
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('fieldset');
    }

    private function response_check_required ($data) { // JR check all question types
        if ($this->type_id == QUESRATE) { // Rate is a special case.
            foreach ($this->choices as $cid => $choice) {
                $str = 'q'."{$this->id}_$cid";
                if (isset($data->$str)) {
                    return ('&nbsp;');
                }
            }
        }
        if ( ($this->required == 'y') &&  empty($data->{'q'.$this->id}) ) {
            return ('*');
        } else {
            return ('&nbsp;');
        }
    }

    private function yesno_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        // Moved choose_from_radio() here to fix unwanted selection of yesno buttons and radio buttons with identical ID.

        // To display or hide dependent questions on Preview page.
        $onclickdepend = array();
        if ($descendantsdata) {
            $descendants = implode(',', $descendantsdata['descendants']);
            if (isset($descendantsdata['choices'][0])) {
                $choices['y'] = implode(',', $descendantsdata['choices'][0]);
            } else {
                $choices['y'] = '';
            }
            if (isset($descendantsdata['choices'][1])) {
                $choices['n'] = implode(',', $descendantsdata['choices'][1]);
            } else {
                $choices['n'] = '';
            }
            $onclickdepend['y'] = ' onclick="depend(\''.$descendants.'\', \''.$choices['y'].'\')"';
            $onclickdepend['n'] = ' onclick="depend(\''.$descendants.'\', \''.$choices['n'].'\')"';
        }
        global $idcounter;  // To make sure all radio buttons have unique ids. // JR 20 NOV 2007.

        $stryes = get_string('yes');
        $strno = get_string('no');

        $val1 = 'y';
        $val2 = 'n';

        if ($blankquestionnaire) {
            $stryes = ' (1) '.$stryes;
            $strno = ' (0) '.$strno;
        }

        $options = array($val1 => $stryes, $val2 => $strno);
        $name = 'q'.$this->id;
        $checked = (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '');
        $output = '';
        $ischecked = false;

        foreach ($options as $value => $label) {
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            $output .= '<input name="'.$name.'" id="'.$htmlid.'" type="radio" value="'.$value.'"';
            if ($value == $checked) {
                $output .= ' checked="checked"';
                $ischecked = true;
            }
            if ($blankquestionnaire) {
                $output .= ' disabled="disabled"';
            }
            if (isset($onclickdepend[$value])) {
                $output .= $onclickdepend[$value];
            }
            $output .= ' /><label for="'.$htmlid.'">'. $label .'</label>' . "\n";
        }
        // CONTRIB-846.
        if ($this->required == 'n') {
            $id = '';
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"';
            if (!$ischecked && !$blankquestionnaire) {
                $output .= ' checked="checked"';
            }
            if ($onclickdepend) {
                $output .= ' onclick="depend(\''.$descendants.'\', \'\')"';
            }
            $content = get_string('noanswer', 'questionnaire');
            $output .= ' /><label for="'.$htmlid.'" >'.
                format_text($content, FORMAT_HTML).'</label>';
        }
        // End CONTRIB-846.

        $output .= '</span>' . "\n";
        echo $output;
    }

    private function text_survey_display($data) { // Text Box.
        echo '<input onkeypress="return event.keyCode != 13;" type="text" size="'.$this->length.'" name="q'.$this->id.'"'.
             ($this->precise > 0 ? ' maxlength="'.$this->precise.'"' : '').' value="'.
             (isset($data->{'q'.$this->id}) ? stripslashes($data->{'q'.$this->id}) : '').
             '" id="' . $this->type . $this->id . '" />';
    }

    private function essay_survey_display($data) { // Essay.
        // Columns and rows default values.
        $cols = 80;
        $rows = 15;
        // Use HTML editor or not?
        if ($this->precise == 0) {
            $canusehtmleditor = true;
            $rows = $this->length == 0 ? $rows : $this->length;
        } else {
            $canusehtmleditor = false;
            // Prior to version 2.6, "precise" was used for rows number.
            $rows = $this->precise > 1 ? $this->precise : $this->length;
        }
        $name = 'q'.$this->id;
        if (isset($data->{'q'.$this->id})) {
            $value = $data->{'q'.$this->id};
        } else {
            $value = '';
        }
        if ($canusehtmleditor) {
            $editor = editors_get_preferred_editor();
            $editor->use_editor($name, questionnaire_get_editor_options($this->context));
            $texteditor = html_writer::tag('textarea', $value,
                            array('id' => $name, 'name' => $name, 'rows' => $rows, 'cols' => $cols));
        } else {
            $editor = FORMAT_PLAIN;
            $texteditor = html_writer::tag('textarea', $value,
                            array('id' => $name, 'name' => $name, 'rows' => $rows, 'cols' => $cols));
        }
        echo $texteditor;
    }

    private function radio_survey_display($data, $descendantsdata, $blankquestionnaire=false) { // Radio buttons
        global $idcounter;  // To make sure all radio buttons have unique ids. // JR 20 NOV 2007.

        $otherempty = false;
        $output = '';
        // Find out which radio button is checked (if any); yields choice ID.
        if (isset($data->{'q'.$this->id})) {
            $checked = $data->{'q'.$this->id};
        } else {
            $checked = '';
        }
        $horizontal = $this->length;
        $ischecked = false;

        // To display or hide dependent questions on Preview page.
        $onclickdepend = array();
        if ($descendantsdata) {
            $descendants = implode(',', $descendantsdata['descendants']);
            foreach ($descendantsdata['choices'] as $key => $choice) {
                $choices[$key] = implode(',', $choice);
                $onclickdepend[$key] = ' onclick="depend(\''.$descendants.'\', \''.$choices[$key].'\')"';
            }
        } // End dependents.

        foreach ($this->choices as $id => $choice) {
            $other = strpos($choice->content, '!other');
            if ($horizontal) {
                $output .= ' <span style="white-space:nowrap;">';
            }

            // To display or hide dependent questions on Preview page.
            $onclick = '';
            if ($onclickdepend) {
                if (isset($onclickdepend[$id])) {
                    $onclick = $onclickdepend[$id];
                } else {
                    // In case this dependchoice is not used by any child question.
                    $onclick = ' onclick="depend(\''.$descendants.'\', \'\')"';
                }

            } else {
                $onclick = ' onclick="other_check_empty(name, value)"';
            } // End dependents.

            if ($other !== 0) { // This is a normal radio button.
                $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);

                $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"'.$onclick;
                if ($id == $checked) {
                    $output .= ' checked="checked"';
                    $ischecked = true;
                }
                $value = '';
                if ($blankquestionnaire) {
                    $output .= ' disabled="disabled"';
                    $value = ' ('.$choice->value.') ';
                }
                $content = $choice->content;
                $contents = questionnaire_choice_values($choice->content);
                $output .= ' /><label for="'.$htmlid.'" >'.$value.
                    format_text($contents->text, FORMAT_HTML).$contents->image.'</label>';
            } else {             // Radio button with associated !other text field.
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;
                $otherempty = false;
                $otherid = 'q'.$this->id.'_'.$checked;
                if (substr($checked, 0, 6) == 'other_') { // Fix bug CONTRIB-222.
                    $checked = substr($checked, 6);
                }
                $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);

                $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="other_'.$id.'"'.$onclick;
                if (($id == $checked) || !empty($data->$cid)) {
                    $output .= ' checked="checked"';
                    $ischecked = true;
                    if (!$data->$cid) {
                        $otherempty = true;
                    }
                }
                $output .= ' /><label for="'.$htmlid.'" >'.format_text($othertext, FORMAT_HTML).'</label>';

                $choices['other_'.$cid] = $othertext;
                $output .= '<input type="text" size="25" name="'.$cid.'" onclick="other_check(name)"';
                if (isset($data->$cid)) {
                    $output .= ' value="'.stripslashes($data->$cid) .'"';
                }
                $output .= ' />&nbsp;';
            }
            if ($horizontal) {
                // Added a zero-width space character to make MSIE happy!
                $output .= '</span>&#8203;';
            } else {
                $output .= '<br />';
            }
        }

        // CONTRIB-846.
        if ($this->required == 'n') {
            $id = '';
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            if ($horizontal) {
                $output .= ' <span style="white-space:nowrap;">';
            }

            // To display or hide dependent questions on Preview page.
            $onclick = '';
            if ($onclickdepend) {
                $onclick = ' onclick="depend(\''.$descendants.'\', \'\')"';
            } else {
                $onclick = ' onclick="other_check_empty(name, value)"';
            } // End dependents.
            $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"'.$onclick;
            if (!$ischecked && !$blankquestionnaire) {
                $output .= ' checked="checked"';
            }
            $content = get_string('noanswer', 'questionnaire');
            $output .= ' /><label for="'.$htmlid.'" >'.
                format_text($content, FORMAT_HTML).'</label>';

            if ($horizontal) {
                $output .= '</span>&nbsp;&nbsp;';
            } else {
                $output .= '<br />';
            }
        }
        // End CONTRIB-846.

        echo $output;
        if ($otherempty) {
            questionnaire_notify (get_string('otherempty', 'questionnaire'));
        }
    }

    private function check_survey_display($data) { // Check boxes.
        $otherempty = false;
        if (!empty($data) ) {
            if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
                $data->{'q'.$this->id} = array();
            }
            // Verify that number of checked boxes (nbboxes) is within set limits (length = min; precision = max).
            if ( $data->{'q'.$this->id} ) {
                $otherempty = false;
                $boxes = $data->{'q'.$this->id};
                $nbboxes = count($boxes);
                foreach ($boxes as $box) {
                    $pos = strpos($box, 'other_');
                    if (is_int($pos) == true) {
                        $otherchoice = substr($box, 6);
                        $resp = 'q'.$this->id.''.substr($box, 5);
                        if (!$data->$resp) {
                            $otherempty = true;
                        }
                    }
                }
                $nbchoices = count($this->choices);
                $min = $this->length;
                $max = $this->precise;
                if ($max == 0) {
                    $max = $nbchoices;
                }
                if ($min > $max) {
                    $min = $max; // Sanity check.
                }
                $min = min($nbchoices, $min);
                $msg = '';
                if ($nbboxes < $min || $nbboxes > $max) {
                    $msg = get_string('boxesnbreq', 'questionnaire');
                    if ($min == $max) {
                        $msg .= '&nbsp;'.get_string('boxesnbexact', 'questionnaire', $min);
                    } else {
                        if ($min && ($nbboxes < $min)) {
                            $msg .= get_string('boxesnbmin', 'questionnaire', $min);
                            if ($nbboxes > $max) {
                                $msg .= ' & ' .get_string('boxesnbmax', 'questionnaire', $max);
                            }
                        } else {
                            if ($nbboxes > $max ) {
                                $msg .= get_string('boxesnbmax', 'questionnaire', $max);
                            }
                        }
                    }
                    questionnaire_notify($msg);
                }
            }
        }

        foreach ($this->choices as $id => $choice) {

            $other = strpos($choice->content, '!other');
            if ($other !== 0) { // This is a normal check box.
                $contents = questionnaire_choice_values($choice->content);
                $checked = false;
                if (!empty($data) ) {
                    $checked = in_array($id, $data->{'q'.$this->id});
                }
                echo html_writer::checkbox('q'.$this->id.'[]', $id, $checked,
                                               format_text($contents->text, FORMAT_HTML).$contents->image);
                echo '<br />';
            } else {             // Check box with associated !other text field.
                // In case length field has been used to enter max number of choices, set it to 20.
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;
                if (!empty($data) && !empty($data->$cid)) {
                    $checked = true;
                } else {
                    $checked = false;
                }
                $name = 'q'.$this->id.'[]';
                $value = 'other_'.$id;

                echo html_writer::checkbox($name, $value, $checked, format_text($othertext.'', FORMAT_HTML));
                $othertext = '&nbsp;<input type="text" size="25" name="'.$cid.'" onclick="other_check(name)"';
                if ($cid) {
                    $othertext .= ' value="'. (!empty($data->$cid) ? stripslashes($data->$cid) : '') .'"';
                }
                $othertext .= ' />';
                echo $othertext.'<br />';
            }
        }
        if ($otherempty) {
            questionnaire_notify (get_string('otherempty', 'questionnaire'));
        }
    }

    private function drop_survey_display($data, $descendantsdata) { // Drop.
        global $OUTPUT;
        $options = array();

        // To display or hide dependent questions on Preview page.
        if ($descendantsdata) {
            $qdropid = 'q'.$this->id;
            $descendants = implode(',', $descendantsdata['descendants']);
            foreach ($descendantsdata['choices'] as $key => $choice) {
                $choices[$key] = implode(',', $choice);
            }
            foreach ($this->choices as $key => $choice) {
                if ($pos = strpos($choice->content, '=')) {
                    $choice->content = substr($choice->content, $pos + 1);
                }
                if (isset($choices[$key])) {
                    $value = $choices[$key];
                } else {
                    $value = $key;
                }
                $options[$value] = $choice->content;
            }
            $dependdrop = "dependdrop('$qdropid', '$descendants')";
            echo html_writer::select($options, $qdropid, (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : ''),
                            array('' => 'choosedots'), array('id' => $qdropid, 'onchange' => $dependdrop));
            // End dependents.
        } else {
            foreach ($this->choices as $key => $choice) {
                if ($pos = strpos($choice->content, '=')) {
                    $choice->content = substr($choice->content, $pos + 1);
                }
                $options[$key] = $choice->content;
            }
            echo html_writer::select($options, 'q'.$this->id,
                (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : ''),
                array('' => 'choosedots'), array('id' => $this->type . $this->id));
        }
    }

    private function rate_survey_display($data, $descendantsdata='', $blankquestionnaire=false) {
        $disabled = '';
        if ($blankquestionnaire) {
            $disabled = ' disabled="disabled"';
        }
        if (!empty($data) && ( !isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id}) ) ) {
            $data->{'q'.$this->id} = array();
        }

        $isna = $this->precise == 1;
        $osgood = $this->precise == 3;

        // Check if rate question has one line only to display full width columns of choices.
        $nocontent = false;
        $nameddegrees = 0;
        $n = array();
        $v = array();
        $mods = array();
        $maxndlen = 0;
        foreach ($this->choices as $cid => $choice) {
            $content = $choice->content;
            if (!$nocontent && $content == '') {
                $nocontent = true;
            }
            // Check for number from 1 to 3 digits, followed by the equal sign = (to accomodate named degrees).
            if (preg_match("/^([0-9]{1,3})=(.*)$/", $content, $ndd)) {
                $n[$nameddegrees] = format_text($ndd[2], FORMAT_HTML);
                if (strlen($n[$nameddegrees]) > $maxndlen) {
                    $maxndlen = strlen($n[$nameddegrees]);
                }
                $v[$nameddegrees] = $ndd[1];
                $this->choices[$cid] = '';
                $nameddegrees++;
            } else {
                $contents = questionnaire_choice_values($content);
                if ($contents->modname) {
                    $choice->content = $contents->text;
                }
            }
        }

        // The 0.1% right margin is needed to avoid the horizontal scrollbar in Chrome!
        // A one-line rate question (no content) does not need to span more than 50%.
        $width = $nocontent ? "50%" : "99.9%";
        echo '<table style="width:'.$width.'">';
        echo '<tbody>';
        echo '<tr>';
        // If Osgood, adjust central columns to width of named degrees if any.
        if ($osgood) {
            if ($maxndlen < 4) {
                $width = '45%';
            } else if ($maxndlen < 13) {
                $width = '40%';
            } else {
                $width = '30%';
            }
            $nn = 100 - ($width * 2);
            $colwidth = ($nn / $this->length).'%';
            $textalign = 'right';
        } else if ($nocontent) {
            $width = '0%';
            $colwidth = (100 / $this->length).'%';
            $textalign = 'right';
        } else {
            $width = '59%';
            $colwidth = (40 / $this->length).'%';
            $textalign = 'left';
        }

        echo '<td style="width: '.$width.'"></td>';

        if ($isna) {
            $na = get_string('notapplicable', 'questionnaire');
        } else {
            $na = '';
        }
        if ($this->precise == 2) {
            $order = ' onclick="other_rate_uncheck(name, value)" ';
        } else {
            $order = '';
        }

        if ($this->precise != 2) {
            $nbchoices = count($this->choices) - $nameddegrees;
        } else { // If "No duplicate choices", can restrict nbchoices to number of rate items specified.
            $nbchoices = $this->length;
        }

        // Display empty td for Not yet answered column.
        if ($nbchoices > 1 && $this->precise != 2 && !$blankquestionnaire) {
            echo '<td></td>';
        }

        for ($j = 0; $j < $this->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
                $val = $v[$j];
            } else {
                $str = $j + 1;
                $val = $j + 1;
            }
            if ($blankquestionnaire) {
                $val = '<br />('.$val.')';
            } else {
                $val = '';
            }
            echo '<td style="width:'.$colwidth.'; text-align:center;" class="smalltext">'.$str.$val.'</td>';
        }
        if ($na) {
            echo '<td style="width:'.$colwidth.'; text-align:center;" class="smalltext">'.$na.'</td>';
        }
        echo '</tr>';

        $num = 0;
        foreach ($this->choices as $cid => $choice) {
            $str = 'q'."{$this->id}_$cid";
            $num += (isset($data->$str) && ($data->$str != -999));
        }

        $notcomplete = false;
        if ( ($num != $nbchoices) && ($num != 0) ) {
            questionnaire_notify(get_string('checkallradiobuttons', 'questionnaire', $nbchoices));
            $notcomplete = true;
        }

        foreach ($this->choices as $cid => $choice) {
            if (isset($choice->content)) {
                $str = 'q'."{$this->id}_$cid";
                echo '<tr class="raterow">';
                $content = $choice->content;
                if ($osgood) {
                    list($content, $contentright) = preg_split('/[|]/', $content);
                }
                echo '<td style="text-align: '.$textalign.';">'.format_text($content, FORMAT_HTML).'&nbsp;</td>';
                $bg = 'c0 raterow';
                if ($nbchoices > 1 && $this->precise != 2  && !$blankquestionnaire) {
                    $checked = ' checked="checked"';
                    $completeclass = 'notanswered';
                    $title = '';
                    if ($notcomplete && isset($data->$str) && ($data->$str == -999)) {
                        $completeclass = 'notcompleted';
                        $title = get_string('pleasecomplete', 'questionnaire');
                    }
                    // Set value of notanswered button to -999 in order to eliminate it from form submit later on.
                    echo '<td title="'.$title.'" class="'.$completeclass.'" style="width:1%;"><input name="'.
                        $str.'" type="radio" value="-999" '.$checked.$order.' /></td>';
                }
                for ($j = 0; $j < $this->length + $isna; $j++) {
                    $checked = ((isset($data->$str) && ($j == $data->$str || $j ==
                                    $this->length && $data->$str == -1)) ? ' checked="checked"' : '');
                    $checked = '';
                    if (isset($data->$str) && ($j == $data->$str || $j == $this->length && $data->$str == -1)) {
                        $checked = ' checked="checked"';
                    }
                    echo '<td style="text-align:center" class="'.$bg.'">';
                    $i = $j + 1;
                    echo html_writer::tag('span', get_string('option', 'questionnaire', $i),
                        array('class' => 'accesshide'));
                    // If isna column then set na choice to -1 value.
                    $value = ($j < $this->length ? $j : - 1);
                    echo '<input name="'.$str.'" type="radio" value="'.$value .'"'.$checked.$disabled.$order.' /></td>';
                    if ($bg == 'c0 raterow') {
                        $bg = 'c1 raterow';
                    } else {
                        $bg = 'c0 raterow';
                    }
                }
                if ($osgood) {
                    echo '<td>&nbsp;'.format_text($contentright, FORMAT_HTML).'</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
    }

    private function date_survey_display($data) { // Date.

        $datemess = html_writer::start_tag('div', array('class' => 'qn-datemsg'));
        $datemess .= get_string('dateformatting', 'questionnaire');
        $datemess .= html_writer::end_tag('div');
        if (!empty($data->{'q'.$this->id})) {
            $dateentered = $data->{'q'.$this->id};
            $setdate = questionnaire_check_date ($dateentered, false);
            if ($setdate == 'wrongdateformat') {
                $msg = get_string('wrongdateformat', 'questionnaire', $dateentered);
                questionnaire_notify($msg);
            } else if ($setdate == 'wrongdaterange') {
                $msg = get_string('wrongdaterange', 'questionnaire');
                questionnaire_notify($msg);
            } else {
                $data->{'q'.$this->id} = $setdate;
            }
        }
        echo $datemess;
        echo html_writer::start_tag('div', array('class' => 'qn-date'));
        echo '<input onkeypress="return event.keyCode != 13;" type="text" size="12" name="q'.$this->id.'" maxlength="10" value="'.
             (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '').'" />';
        echo html_writer::end_tag('div');
    }

    private function numeric_survey_display($data) { // Numeric.
        $precision = $this->precise;
        $a = '';
        if (isset($data->{'q'.$this->id})) {
            $mynumber = $data->{'q'.$this->id};
            if ($mynumber != '') {
                $mynumber0 = $mynumber;
                if (!is_numeric($mynumber) ) {
                    $msg = get_string('notanumber', 'questionnaire', $mynumber);
                    questionnaire_notify ($msg);
                } else {
                    if ($precision) {
                        $pos = strpos($mynumber, '.');
                        if (!$pos) {
                            if (strlen($mynumber) > $this->length) {
                                $mynumber = substr($mynumber, 0 , $this->length);
                            }
                        }
                        $this->length += (1 + $precision); // To allow for n numbers after decimal point.
                    }
                    $mynumber = number_format($mynumber, $precision , '.', '');
                    if ( $mynumber != $mynumber0) {
                        $a->number = $mynumber0;
                        $a->precision = $precision;
                        $msg = get_string('numberfloat', 'questionnaire', $a);
                        questionnaire_notify ($msg);
                    }
                }
            }
            if ($mynumber != '') {
                $data->{'q'.$this->id} = $mynumber;
            }
        }

        echo '<input onkeypress="return event.keyCode != 13;" type="text" size="'.
            $this->length.'" name="q'.$this->id.'" maxlength="'.$this->length.
             '" value="'.(isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '').
            '" id="' . $this->type . $this->id . '" />';
    }

    private function sectiontext_survey_display($data) {
        return;
    }

    public function response_display($data, $qnum='') {
        global $qtypenames;
        $method = $qtypenames[$this->type_id].'_response_display';

        if (method_exists($this, $method)) {
            $this->questionstart_survey_display($qnum, $data);
            $this->$method($data);
            $this->questionend_survey_display($qnum);
        } else {
            print_error('displaymethod', 'questionnaire');
        }
    }

    public function yesno_response_display($data) {
        static $stryes = null;
        static $strno = null;
        static $uniquetag = 0;  // To make sure all radios have unique names.

        if (is_null($stryes)) {
             $stryes = get_string('yes');
             $strno = get_string('no');
        }

        $val1 = 'y';
        $val2 = 'n';

        echo '<div class="response yesno">';
        if (isset($data->{'q'.$this->id}) && ($data->{'q'.$this->id} == $val1)) {
            echo '<span class="selected">' .
                 '<input type="radio" name="q'.$this->id.$uniquetag++.'y" checked="checked" /> '.$stryes.'</span>';
        } else {
            echo '<span class="unselected">' .
                 '<input type="radio" name="q'.$this->id.$uniquetag++.'y" onclick="this.checked=false;" /> '.$stryes.'</span>';
        }
        if (isset($data->{'q'.$this->id}) && ($data->{'q'.$this->id} == $val2)) {
            echo ' <span class="selected">' .
                 '<input type="radio" name="q'.$this->id.$uniquetag++.'n" checked="checked" /> '.$strno.'</span>';
        } else {
            echo ' <span class="unselected">' .
                 '<input type="radio" name="q'.$this->id.$uniquetag++.'n" onclick="this.checked=false;" /> '.$strno.'</span>';
        }
        echo '</div>';
    }

    public function text_response_display($data) {
        $response = isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '';
        echo '<div class="response text"><span class="selected">'.$response.'</span></div>';
    }

    public function essay_response_display($data) {
        echo '<div class="response text">';
        echo((!empty($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '&nbsp;'));
        echo '</div>';
    }

    public function radio_response_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.
        $horizontal = $this->length;
        $checked = (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '');
        foreach ($this->choices as $id => $choice) {
            if ($horizontal) {
                echo ' <span style="white-space:nowrap;">';
            }
            if (strpos($choice->content, '!other') !== 0) {
                $contents = questionnaire_choice_values($choice->content);
                $choice->content = $contents->text.$contents->image;
                if ($id == $checked) {
                    echo '<span class="selected">'.
                         '<input type="radio" name="'.$id.$uniquetag++.'" checked="checked" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span>&nbsp;';
                } else {
                    echo '<span class="unselected">'.
                         '<input type="radio" disabled="disabled" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span>&nbsp;';
                }

            } else {
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;

                if (isset($data->{'q'.$this->id.'_'.$id})) {
                    echo '<span class="selected">'.
                         '<input type="radio" name="'.$id.$uniquetag++.'" checked="checked" /> '.$othertext.' ';
                    echo '<span class="response text">';
                    echo (!empty($data->$cid) ? htmlspecialchars($data->$cid) : '&nbsp;');
                    echo '</span></span>';
                } else {
                    echo '<span class="unselected"><input type="radio" name="'.$id.$uniquetag++.
                                    '" onclick="this.checked=false;" /> '.
                         $othertext.'</span>';
                }
            }
            if ($horizontal) {
                echo '</span>';
            } else {
                echo '<br />';
            }
        }
    }

    public function check_response_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
            $data->{'q'.$this->id} = array();
        }

        echo '<div class="response check">';
        foreach ($this->choices as $id => $choice) {
            if (strpos($choice->content, '!other') !== 0) {
                $contents = questionnaire_choice_values($choice->content);
                $choice->content = $contents->text.$contents->image;

                if (in_array($id, $data->{'q'.$this->id})) {
                    echo '<span class="selected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" checked="checked" onclick="this.checked=true;" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span><br />';
                } else {
                    echo '<span class="unselected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span><br />';
                }
            } else {
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;

                if (isset($data->$cid)) {
                    echo '<span class="selected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" checked="checked" onclick="this.checked=true;" /> '.
                         ($othertext === '' ? $id : $othertext).' ';
                    echo '<span class="response text">';
                    echo (!empty($data->$cid) ? htmlspecialchars($data->$cid) : '&nbsp;');
                    echo '</span></span><br />';
                } else {
                    echo '<span class="unselected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         ($othertext === '' ? $id : $othertext).'</span><br />';
                }
            }
        }
        echo '</div>';
    }

    public function drop_response_display($data) {
        global $OUTPUT;
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $options = array();
        foreach ($this->choices as $id => $choice) {
            $contents = questionnaire_choice_values($choice->content);
            $options[$id] = format_text($contents->text, FORMAT_HTML);
        }
        echo '<div class="response drop">';
        echo html_writer::select($options, 'q'.$this->id.$uniquetag++,
                        (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : ''));
        if (isset($data->{'q'.$this->id}) ) {
            echo ': <span class="selected">'.$options[$data->{'q'.$this->id}].'</span></div>';
        }
    }

    public function rate_response_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.
        if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
            $data->{'q'.$this->id} = array();
        }
        // Check if rate question has one line only to display full width columns of choices.
        $nocontent = false;
        foreach ($this->choices as $cid => $choice) {
            $content = $choice->content;
            if ($choice->content == '') {
                $nocontent = true;
                break;
            }
        }
        $width = $nocontent ? "50%" : "99.9%";

        echo '<table class="individual" border="0" cellspacing="1" cellpadding="0" style="width:'.$width.'">';
        echo '<tbody><tr>';
        $osgood = $this->precise == 3;
        $bg = 'c0';
        $nameddegrees = 0;
        $cidnamed = array();
        $n = array();
        // Max length of potential named degree in column head.
        $maxndlen = 0;
        foreach ($this->choices as $cid => $choice) {
            $content = $choice->content;
            if (preg_match("/^[0-9]{1,3}=/", $content, $ndd)) {
                $ndd = format_text(substr($content, strlen($ndd[0])), FORMAT_HTML);
                $n[$nameddegrees] = $ndd;
                if (strlen($ndd) > $maxndlen) {
                    $maxndlen = strlen($ndd);
                }
                $cidnamed[$cid] = true;
                $nameddegrees++;
            }
        }
        if ($osgood) {
            if ($maxndlen < 4) {
                $sidecolwidth = '45%';
            } else if ($maxndlen < 13) {
                $sidecolwidth = '40%';
            } else {
                $sidecolwidth = '30%';
            }
            echo '<td style="width: '.$sidecolwidth.'; text-align: right;"></td>';
            $nn = 100 - ($sidecolwidth * 2);
            $colwidth = ($nn / $this->length).'%';
            $textalign = 'right';
        } else {
            echo '<td style="width: 49%"></td>';
            $colwidth = (50 / $this->length).'%';
            $textalign = 'left';
        }
        for ($j = 0; $j < $this->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j + 1;
            }
            echo '<td style="width:'.$colwidth.'; text-align:center" class="'.$bg.' smalltext">'.$str.'</td>';
            if ($bg == 'c0') {
                $bg = 'c1';
            } else {
                $bg = 'c0';
            }
        }
        if ($this->precise == 1) {
            echo '<td style="width:'.$colwidth.'; text-align:center" class="'.$bg.'">'.
                get_string('notapplicable', 'questionnaire').'</td>';
        }
        if ($osgood) {
            echo '<td style="width:'.$sidecolwidth.'%;"></td>';
        }
        echo '</tr>';

        foreach ($this->choices as $cid => $choice) {
            // Do not print column names if named column exist.
            if (!array_key_exists($cid, $cidnamed)) {
                $str = 'q'."{$this->id}_$cid";
                echo '<tr>';
                $content = $choice->content;
                $contents = questionnaire_choice_values($content);
                if ($contents->modname) {
                    $content = $contents->text;
                }
                if ($osgood) {
                    list($content, $contentright) = preg_split('/[|]/', $content);
                }
                echo '<td style="text-align:'.$textalign.'">'.format_text($content, FORMAT_HTML).'&nbsp;</td>';
                $bg = 'c0';
                for ($j = 0; $j < $this->length; $j++) {
                    $checked = ((isset($data->$str) && ($j == $data->$str)) ? ' checked="checked"' : '');
                    // N/A column checked.
                    $checkedna = ((isset($data->$str) && ($data->$str == -1)) ? ' checked="checked"' : '');

                    if ($checked) {
                        echo '<td style="text-align:center;" class="selected">';
                        echo '<span class="selected">'.
                             '<input type="radio" name="'.$str.$j.$uniquetag++.'" checked="checked" /></span>';
                    } else {
                        echo '<td style="text-align:center;" class="'.$bg.'">';
                            echo '<span class="unselected">'.
                                 '<input type="radio" disabled="disabled" name="'.$str.$j.
                                    $uniquetag++.'" onclick="this.checked=false;" /></span>';
                    }
                    echo '</td>';
                    if ($bg == 'c0') {
                        $bg = 'c1';
                    } else {
                        $bg = 'c0';
                    }
                }
                if ($this->precise == 1) { // N/A column.
                    echo '<td style="width:auto; text-align:center;" class="'.$bg.'">';
                    if ($checkedna) {
                        echo '<span class="selected">'.
                             '<input type="radio" name="'.$str.$j.$uniquetag++.'na" checked="checked" /></span>';
                    } else {
                        echo '<span class="unselected">'.
                             '<input type="radio" name="'.$str.$uniquetag++.'na" onclick="this.checked=false;" /></span>';
                    }
                    echo '</td>';
                }
                if ($osgood) {
                    echo '<td>&nbsp;'.format_text($contentright, FORMAT_HTML).'</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
    }

    public function date_response_display($data) {
        if (isset($data->{'q'.$this->id})) {
            echo '<div class="response date">';
            echo('<span class="selected">'.$data->{'q'.$this->id}.'</span>');
            echo '</div>';
        }
    }

    public function numeric_response_display($data) {
        $this->length++; // For sign.
        if ($this->precise) {
            $this->length += 1 + $this->precise;
        }
        echo '<div class="response numeric">';
        if (isset($data->{'q'.$this->id})) {
            echo('<span class="selected">'.$data->{'q'.$this->id}.'</span>');
        }
        echo '</div>';
    }

    public function sectiontext_response_display($data) {
        return;
    }

    /* {{{ proto void mkrespercent(array weights, int total, int precision, bool show_totals)
      Builds HTML showing PERCENTAGE results. */

    private function mkrespercent($total, $precision, $showtotals, $sort) {
        global $CFG, $OUTPUT;
        $precision = 0;
        $i = 0;
        $alt = '';
        $bg = '';
        $imageurl = $CFG->wwwroot.'/mod/questionnaire/images/';
        $strtotal = get_string('total', 'questionnaire');
        $table = new html_table();
        $table->size = array();

        $table->align = array();
        $table->head = array();
        $table->wrap = array();
        $table->size = array_merge($table->size, array('50%', '40%', '10%'));
        $table->align = array_merge($table->align, array('left', 'left', 'right'));
        $table->wrap = array_merge($table->wrap, array('', 'nowrap', ''));
        $table->head = array_merge($table->head, array(get_string('response', 'questionnaire'),
                       get_string('average', 'questionnaire'), get_string('total', 'questionnaire')));

        if (!empty($this->counts) && is_array($this->counts)) {
            $pos = 0;
            switch ($sort) {
                case 'ascending':
                    asort($this->counts);
                    break;
                case 'descending':
                    arsort($this->counts);
                    break;
            }
            $numresponses = 0;
            foreach ($this->counts as $key => $value) {
                $numresponses = $numresponses + $value;
            }
            reset ($this->counts);
            while (list($content, $num) = each($this->counts)) {
                if ($num > 0) {
                    $percent = $num / $numresponses * 100.0;
                } else {
                    $percent = 0;
                }
                if ($percent > 100) {
                    $percent = 100;
                }
                if ($num) {
                    $out = '&nbsp;<img alt="'.$alt.'" src="'.$imageurl.'hbar_l.gif" />'.
                               '<img style="height:9px; width:'.($percent * 1.4).'px;" alt="'.$alt.'" src="'.
                               $imageurl.'hbar.gif" />'.'<img alt="'.$alt.'" src="'.$imageurl.'hbar_r.gif" />'.
                               sprintf('&nbsp;%.'.$precision.'f%%', $percent);
                } else {
                    $out = '';
                }

                $tabledata = array();
                $tabledata = array_merge($tabledata, array(format_text($content, FORMAT_HTML), $out, $num));
                $table->data[] = $tabledata;
                $i += $num;
                $pos++;
            } // End while.

            if ($showtotals) {
                if ($i > 0) {
                    $percent = $i / $total * 100.0;
                } else {
                    $percent = 0;
                }
                if ($percent > 100) {
                    $percent = 100;
                }

                $out = '&nbsp;<img alt="'.$alt.'" src="'.$imageurl.'thbar_l.gif" />'.
                                '<img style="height:9px;  width:'.($percent * 1.4).'px;" alt="'.$alt.'" src="'.
                                $imageurl.'thbar.gif" />'.'<img alt="'.$alt.'" src="'.$imageurl.'thbar_r.gif" />'.
                                sprintf('&nbsp;%.'.$precision.'f%%', $percent);
                $table->data[] = 'hr';
                $tabledata = array();
                $tabledata = array_merge($tabledata, array($strtotal, $out, "$i/$total"));
                $table->data[] = $tabledata;
            }
        } else {
            $tabledata = array();
            $tabledata = array_merge($tabledata, array('', get_string('noresponsedata', 'questionnaire')));
            $table->data[] = $tabledata;
        }

        echo html_writer::table($table);
    }

    /* {{{ proto void mkreslist(array weights, int total, int precision, bool show_totals)
        Builds HTML showing LIST results. */
    private function mkreslist($total, $precision, $showtotals) {
        global $CFG, $OUTPUT;

        if ($total == 0) {
            return;
        }

        $strresponse = get_string('response', 'questionnaire');
        $strnum = get_string('num', 'questionnaire');
        $table = new html_table();
        $table->align = array('left', 'left');

        $imageurl = $CFG->wwwroot.'/mod/questionnaire/images/';

        $table->head = array($strnum, $strresponse);
        $table->size = array('10%', '*');

        if (!empty($this->counts) && is_array($this->counts)) {
            while (list($text, $num) = each($this->counts)) {
                $text = format_text($text, FORMAT_HTML);
                $table->data[] = array($num, $text);
            }
        } else {
            $table->data[] = array('', get_string('noresponsedata', 'questionnaire'));
        }

        echo html_writer::table($table);
    }

    private function mkreslisttext($rows) {
        global $CFG, $SESSION, $questionnaire, $OUTPUT, $DB;
        $strresponse = get_string('response', 'questionnaire');
        $viewsingleresponse = $questionnaire->capabilities->viewsingleresponse;
        $nonanonymous = $questionnaire->respondenttype != 'anonymous';
        $table = new html_table();
        if ($viewsingleresponse && $nonanonymous) {
            $strrespondent = get_string('respondent', 'questionnaire');
            $table->align = array('left', 'left');
            $currentgroupid = '';
            if (isset($SESSION->questionnaire->currentgroupid)) {
                $currentgroupid = $SESSION->questionnaire->currentgroupid;
            }
            $url = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$questionnaire->survey->id.
            '&currentgroupid='.$currentgroupid;
            $table->head = array($strrespondent, $strresponse);
            $table->size = array('*', '*');
        } else {
            $table->align = array('left');
            $table->head = array($strresponse);
            $table->size = array('*');
        }
        foreach ($rows as $row) {
            $text = format_text($row->response, FORMAT_HTML);
            if ($viewsingleresponse && $nonanonymous) {
                $rurl = $url.'&amp;rid='.$row->rid.'&amp;individualresponse=1';
                $title = userdate($row->submitted);
                $user = $DB->get_record('user', array('id' => $row->userid));
                $rusername = '<a href="'.$rurl.'" title="'.$title.'">'.fullname($user).'</a>';
                $table->data[] = array($rusername, $text);
            } else {
                $table->data[] = array($text);
            }
        }
        echo html_writer::table($table);
    }

    private function mkreslistdate($total, $precision, $showtotals) {
        global $CFG, $OUTPUT;
        $dateformat = get_string('strfdate', 'questionnaire');

        if ($total == 0) {
            return;
        }
        $strresponse = get_string('response', 'questionnaire');
        $strnum = get_string('num', 'questionnaire');
        $table = new html_table();
        $table->align = array('left', 'right');
        $table->head = array($strnum, $strresponse);
        $table->size = array('*', '*');
        $table->attributes['class'] = 'generaltable';

        if (!empty($this->counts) && is_array($this->counts)) {
            ksort ($this->counts); // Sort dates into chronological order.
            while (list($text, $num) = each($this->counts)) {
                $text = userdate ( $text, $dateformat, '', false);    // Change timestamp into readable dates.
                $table->data[] = array($num, $text);
            }
        } else {
            $table->data[] = array('', get_string('noresponsedata', 'questionnaire'));
        }

        echo html_writer::table($table);
    }

    private function mkreslistnumeric($total, $precision) {
        global $CFG, $OUTPUT;
        if ($total == 0) {
            return;
        }
        $nbresponses = 0;
        $sum = 0;
        $strtotal = get_string('total', 'questionnaire');
        $strresponse = get_string('response', 'questionnaire');
        $strnum = get_string('num', 'questionnaire');
        $strnoresponsedata = get_string('noresponsedata', 'questionnaire');
        $straverage = get_string('average', 'questionnaire');
        $table = new html_table();
        $table->align = array('left', 'right');
        $table->head = array($strnum, $strresponse);
        $table->size = array('*', '*');
        $table->attributes['class'] = 'generaltable';

        if (!empty($this->counts) && is_array($this->counts)) {
            ksort ($this->counts);
            while (list($text, $num) = each($this->counts)) {
                $table->data[] = array($num, $text);
                $nbresponses += $num;
                $sum += $text * $num;
            }
            $table->data[] = 'hr';
            $table->data[] = array($strtotal , $sum);
            $avg = $sum / $nbresponses;
               $table->data[] = array($straverage , sprintf('%.'.$precision.'f', $avg));
        } else {
            $table->data[] = array('', $strnoresponsedata);
        }

        echo html_writer::table($table);
    }

    /* {{{ proto void mkresavg(array weights, int total, int precision, bool show_totals)
        Builds HTML showing AVG results. */

    private function mkresavg($total, $precision, $showtotals, $length, $sort, $stravgvalue='') {
        global $CFG, $OUTPUT;
        $stravgrank = get_string('averagerank', 'questionnaire');
        $osgood = false;
        if ($precision == 3) { // Osgood's semantic differential.
            $osgood = true;
            $stravgrank = get_string('averageposition', 'questionnaire');
        }
        $stravg = '<div style="text-align:right">'.$stravgrank.$stravgvalue.'</div>';

        $isna = $this->precise == 1;
        $isnahead = '';
        $nbchoices = count ($this->counts);
        $isrestricted = ($length < $nbchoices) && $precision == 2;

        if ($isna) {
            $isnahead = get_string('notapplicable', 'questionnaire');
        }
        $table = new html_table();

        $table->align = array('', '', 'center', 'right');
        $table->width = '    99%';
        if ($isna) {
            $table->head = array('', $stravg, '&dArr;', $isnahead);
        } else {
            if ($osgood) {
                $stravg = '<div style="text-align:center">'.$stravgrank.'</div>';
                $table->head = array('', $stravg, '');
            } else {
                $table->head = array('', $stravg, '&dArr;');
            }
        }
        // TODO JR please calculate the correct width of the question text column (col #1).
        $rightcolwidth = '5%';
        $table->size = array('60%', '*', $rightcolwidth);
        if ($isna) {
            $table->size = array('55%', '*', $rightcolwidth, $rightcolwidth);
        }
        if ($osgood) {
            $table->size = array('25%', '50%', '25%');
        }

        $imageurl = $CFG->wwwroot.'/mod/questionnaire/images/';
        if (!$length) {
            $length = 5;
        }
        // Add an extra column to accomodate lower ranks in this case.
        $length += $isrestricted;
        $nacol = 0;
        $width = 100 / $length;
        $n = array();
        $nameddegrees = 0;
        foreach ($this->choices as $choice) {
            // To take into account languages filter.
            $content = (format_text($choice->content, FORMAT_HTML));
            if (preg_match("/^[0-9]{1,3}=/", $content, $ndd)) {
                $n[$nameddegrees] = substr($content, strlen($ndd[0]));
                $nameddegrees++;
            }
        }
        $nbchoices = $this->length;
        for ($j = 0; $j < $this->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j + 1;
            }
        }
        $out = '<table style="width:100%" cellpadding="2" cellspacing="0" border="1"><tr>';
        for ($i = 0; $i <= $length - 1; $i++) {
            if (isset($n[$i])) {
                $str = $n[$i];
            } else {
                $str = $i + 1;
            }
            if ($isrestricted && $i == $length - 1) {
                $str = "...";
            }
            $out .= '<td style="text-align: center; width:'.$width.'%" class="smalltext">'.$str.'</td>';
        }
        $out .= '</tr></table>';
        $table->data[] = array('', $out, '');

        switch ($sort) {
            case 'ascending':
                uasort($this->counts, 'sortavgasc');
                break;
            case 'descending':
                uasort($this->counts, 'sortavgdesc');
                break;
        }
        reset ($this->counts);

        if (!empty($this->counts) && is_array($this->counts)) {
            while (list($content) = each($this->counts)) {
                // Eliminate potential named degrees on Likert scale.
                if (!preg_match("/^[0-9]{1,3}=/", $content)) {

                    if (isset($this->counts[$content]->avg)) {
                        $avg = $this->counts[$content]->avg;
                        if (isset($this->counts[$content]->avgvalue)) {
                            $avgvalue = $this->counts[$content]->avgvalue;
                        } else {
                            $avgvalue = '';
                        }
                    } else {
                        $avg = '';
                    }
                    $nbna = $this->counts[$content]->nbna;

                    if ($avg) {
                        $out = '';
                        if (($j = $avg * $width) > 0) {
                            $marginposition = ($avg - 0.5 ) / ($this->length + $isrestricted) * 100;
                        }
                        $out .= '<img style="height:12px; width: 6px; margin-left: '.$marginposition.
                            '%;" alt="" src="'.$imageurl.'hbar.gif" />';
                    } else {
                            $out = '';
                    }

                    if ($osgood) {
                        list($content, $contentright) = preg_split('/[|]/', $content);
                    } else {
                        $contents = questionnaire_choice_values($content);
                        if ($contents->modname) {
                            $content = $contents->text;
                        }
                    }
                    if ($osgood) {
                        $table->data[] = array('<div class="mdl-right">'.format_text($content, FORMAT_HTML).'</div>', $out,
                            '<div class="mdl-left">'.format_text($contentright, FORMAT_HTML).'</div>');
                        // JR JUNE 2012 do not display meaningless average rank values for Osgood.
                    } else {
                        if ($avg) {
                            $stravgval = '';
                            if ($stravgvalue) {
                                $stravgval = '('.sprintf('%.1f', $avgvalue).')';
                            }
                            if ($isna) {
                                $table->data[] = array(format_text($content, FORMAT_HTML), $out, sprintf('%.1f', $avg).
                                        '&nbsp;'.$stravgval, $nbna);
                            } else {
                                $table->data[] = array(format_text($content, FORMAT_HTML), $out, sprintf('%.1f', $avg).
                                        '&nbsp;'.$stravgval);
                            }
                        } else if ($nbna != 0) {
                            $table->data[] = array(format_text($content, FORMAT_HTML), $out, '', $nbna);
                        }
                    }
                } // End if named degrees.
            } // End while.
        } else {
            $table->data[] = array('', get_string('noresponsedata', 'questionnaire'));
        }
        echo html_writer::table($table);
    }

    private function mkrescount($rids, $rows, $precision, $length, $sort) {
        // Display number of responses to Rate questions - see http://moodle.org/mod/forum/discuss.php?d=185106.
        global $CFG, $DB;
        $nbresponses = count($rids);
        // Prepare data to be displayed.
        $isrestricted = ($this->length < count($this->choices)) && $this->precise == 2;

        $rsql = '';
        if (!empty($rids)) {
            list($rsql, $params) = $DB->get_in_or_equal($rids);
            $rsql = ' AND response_id ' . $rsql;
        }

        $questionid = $this->id;
        $sql = 'SELECT r.id, c.content, r.rank, c.id AS choiceid ' .
                'FROM ' . $CFG->prefix . 'questionnaire_quest_choice c , ' .
                $CFG->prefix . 'questionnaire_response_rank r ' .
                'WHERE c.question_id = ' . $questionid .
                ' AND r.question_id = c.question_id' .
                ' AND r.choice_id = c.id ' .
                $rsql .
                ' ORDER BY choiceid, rank ASC';
        $choices = $DB->get_records_sql($sql, $params);

        // Sort rows (results) by average value.
        if ($sort != 'default') {
            $sortarray = array();
            foreach ($rows as $row) {
                foreach ($row as $key => $value) {
                    if (!isset($sortarray[$key])) {
                        $sortarray[$key] = array();
                    }
                    $sortarray[$key][] = $value;
                }
            }
            $orderby = "average";
            switch ($sort) {
                case 'ascending':
                    array_multisort($sortarray[$orderby], SORT_ASC, $rows);
                    break;
                case 'descending':
                    array_multisort($sortarray[$orderby], SORT_DESC, $rows);
                    break;
            }
        }
        $nbranks = $this->length;
        $ranks = array();
        foreach ($rows as $row) {
            $choiceid = $row->id;
            foreach ($choices as $choice) {
                if ($choice->choiceid == $choiceid) {
                    $n = 0;
                    for ($i = 0; $i < $nbranks; $i++) {
                        if ($choice->rank == $i) {
                            $n++;
                            if (!isset($ranks[$choice->content][$i])) {
                                $ranks[$choice->content][$i] = 0;
                            }
                            $ranks[$choice->content][$i] += $n;
                        }
                    }
                }
            }
        }

        // Psettings for display.
        $strresp = '<div style="text-align:center">'.get_string('responses', 'questionnaire').'</div>';
        $strtotal = '<strong>'.get_string('total', 'questionnaire').'</strong>';
        $isna = $this->precise == 1;
        $isnahead = '';
        $osgood = false;
        $nbchoices = count ($this->counts);
        if ($precision == 3) { // Osgood's semantic differential.
            $osgood = true;
        }
        if ($isna) {
            $isnahead = get_string('notapplicable', 'questionnaire').'<br />(#)';
        }
        if ($precision == 1) {
            $na = get_string('notapplicable', 'questionnaire');
        } else {
            $na = '';
        }
        $colspan = $length + 1 + ($na != '') + $osgood;
        $nameddegrees = 0;
        $n = array();
        $mods = array();
        foreach ($this->choices as $cid => $choice) {
            $content = $choice->content;
            // Check for number from 1 to 3 digits, followed by the equal sign = (to accomodate named degrees).
            if (preg_match("/^([0-9]{1,3})=(.*)$/", $content, $ndd)) {
                $n[$nameddegrees] = format_text($ndd[2], FORMAT_HTML);
                $nameddegrees++;
            } else {
                $contents = questionnaire_choice_values($content);
                if ($contents->modname) {
                    $choice->content = $contents->text;
                }
            }
        }

        $headings = array('<span class="smalltext">'.get_string('responses', 'questionnaire').'</span>');
        $chartkeys = array();
        if ($osgood) {
            $align = array('right');
        } else {
            $align = array('left');
        }

        // Display the column titles.
        for ($j = 0; $j < $this->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j + 1;
            }
            array_push($headings, '<span class="smalltext">'.$str.'</span>');
            array_push($align, 'center');
        }
        if ($osgood) {
            array_push($headings, '');
            array_push($align, 'left');
        }
        array_push($headings, $strtotal);
        if ($isrestricted) {
            array_push($headings, get_string('notapplicable', 'questionnaire'));
            array_push($align, 'center');
        }
        array_push($align, 'center');
        if ($na) {
            array_push($headings, $na);
            array_push($align, 'center');
        }

        $table = new html_table();
        $table->head = $headings;
        $table->align = $align;
        $table->attributes['class'] = 'generaltable';
        // Now display the responses.
        foreach ($ranks as $content => $rank) {
            $data = array();
            // Eliminate potential named degrees on Likert scale.
            if (!preg_match("/^[0-9]{1,3}=/", $content)) {
                // First display the list of degrees (named or un-named)
                // number of NOT AVAILABLE responses for this possible answer.
                $nbna = $this->counts[$content]->nbna;
                // TOTAL number of responses for this possible answer.
                $total = $this->counts[$content]->num;
                $nbresp = '<strong>'.$total.'<strong>';
                if ($osgood) {
                    list($content, $contentright) = preg_split('/[|]/', $content);
                    $data[] = format_text($content, FORMAT_HTML);
                } else {
                    // Eliminate potentially short-named choices.
                    $contents = questionnaire_choice_values($content);
                    if ($contents->modname) {
                        $content = $contents->text;
                    }
                    $data[] = format_text($content, FORMAT_HTML);
                }
                // Display ranks/rates numbers.
                $maxrank = max($rank);
                for ($i = 0; $i <= $length - 1; $i++) {
                    $percent = '';
                    if (isset($rank[$i])) {
                        $str = $rank[$i];
                        if ($total !== 0 && $str !== 0) {
                            $percent = ' (<span class="percent">'.number_format(($str * 100) / $total).'%</span>)';
                        }
                        // Emphasize responses with max rank value.
                        if ($str == $maxrank) {
                            $str = '<strong>'.$str.'</strong>';
                        }
                    } else {
                        $str = 0;
                    }
                    $data[] = $str.$percent;
                }
                if ($osgood) {
                    $data[] = format_text($contentright, FORMAT_HTML);
                }
                $data[] = $nbresp;
                if ($isrestricted) {
                    $data[] = $nbresponses - $total;
                }
                if (!$osgood) {
                    if ($na) {
                        $data[] = $nbna;
                    }
                }
            } // End named degrees.
            $table->data[] = $data;
        }
        echo html_writer::table($table);
    }
}

function sortavgasc($a, $b) {
    if (isset($a->avg) && isset($b->avg)) {
        if ( $a->avg < $b->avg ) {
            return -1;
        } else if ($a->avg > $b->avg ) {
            return 1;
        } else {
            return 0;
        }
    }
}

function sortavgdesc($a, $b) {
    if (isset($a->avg) && isset($b->avg)) {
        if ( $a->avg > $b->avg ) {
            return -1;
        } else if ($a->avg < $b->avg) {
            return 1;
        } else {
            return 0;
        }
    }
}