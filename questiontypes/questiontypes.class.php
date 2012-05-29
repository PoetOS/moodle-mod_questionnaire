<?php // $Id$

/**
 * This file contains the parent class for questionnaire question types.
 *
 * @author Mike Churchward
 * @version
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

/**
 * Class for describing a question
 *
 * @author Mike Churchward
 * @package questiontypes
 */

 /// Constants
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

$QTYPENAMES = array(
        QUESYESNO =>        'yesno',
        QUESTEXT =>         'text',
        QUESESSAY    =>     'essay',
        QUESRADIO =>        'radio',
        QUESCHECK =>        'check',
        QUESDROP =>         'drop',
        QUESRATE =>         'rate',
        QUESDATE =>         'date',
        QUESNUMERIC =>      'numeric',
        QUESPAGEBREAK =>    'pagebreak',
        QUESSECTIONTEXT =>  'sectiontext'
        );
GLOBAL $idcounter;
$idcounter = 0;

require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

class questionnaire_question {

/// Class Properties
    /**
     * The database id of this question.
     * @var int $id
     */
     var $id          = 0;

    /**
     * The database id of the survey this question belongs to.
     * @var int $survey_id
     */
     var $survey_id   = 0;

    /**
     * The name of this question.
     * @var string $name
     */
     var $name        = '';

    /**
     * The alias of the number of this question.
     * @var string $numberalias
     */
//     var $numberalias = '';

    /**
     * The name of the question type.
     * @var string $type
     */
     var $type        = '';

    /**
     * Array holding any choices for this question.
     * @var array $choices
     */
     var $choices     = array();

    /**
     * The table name for responses.
     * @var string $response_table
     */
     var $response_table = '';

    /**
     * The length field.
     * @var int $length
     */
     var $length      = 0;

    /**
     * The precision field.
     * @var int $precise
     */
     var $precise     = 0;

    /**
     * Position in the questionnaire
     * @var int $position
     */
     var $position    = 0;

    /**
     * The question's content.
     * @var string $content
     */
     var $content     = '';

    /**
     * The list of all question's choices.
     * @var string $allchoices
     */
     var $allchoices  = '';

    /**
     * The required flag.
     * @var boolean $required
     */
     var $required    = 'n';

    /**
     * The deleted flag.
     * @var boolean $deleted
     */
     var $deleted     = 'n';

/// Class Methods

    /**
     * The class constructor
     *
     */
    function questionnaire_question($id = 0, $question = null) {

        static $qtypes = null;

        if (is_null($qtypes)) {
            $qtypes = get_records('questionnaire_question_type', '', '', 'typeid',
                                  'typeid,type,has_choices,response_table');
        }

        if ($id) {
            $question = get_record('questionnaire_question', 'id', $id);
        }

        if (is_object($question)) {
            $this->id = $question->id;
            $this->survey_id = $question->survey_id;
            $this->name = $question->name;
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
    }

    /**
     * Fake constructor to keep PHP5 happy
     *
     */
    function __construct($id = 0, $question = null) {
        $this->questionnaire_question($id, $question);
    }

    function get_choices() {
        if ($choices = get_records('questionnaire_quest_choice', 'question_id', $this->id, 'id ASC')) {
            foreach ($choices as $choice) {
                $this->choices[$choice->id]->content = $choice->content;
                $this->choices[$choice->id]->value = $choice->value;
            }
        } else {
            $this->choices = array();
        }
    }

/// Storage Methods:
/// The following methods are defined by the tables they use. Questions should call the
/// appropriate function based on its table.

    function insert_response($rid, $formdata) {
        if (isset($formdata->{'q'.$this->id})) {
            $val = clean_param($formdata->{'q'.$this->id}, PARAM_CLEAN);
        }
        else {
            $val = '';
        }

        $method = 'insert_'.$this->response_table;
        if (method_exists($this, $method)) {
            return $this->$method($rid, $formdata);
        } else {
            return false;
        }
    }

    function insert_response_bool($rid, $formdata) {
        if (isset($formdata->{'q'.$this->id})
            && !empty($formdata->{'q'.$this->id}) // if "no answer" then choice is empty (CONTRIB-846)
            ) {
            $record = new Object();
            $record->response_id = $rid;
            $record->question_id = $this->id;
            $record->choice_id = clean_param($formdata->{'q'.$this->id}, PARAM_TEXT);
            return insert_record('questionnaire_'.$this->response_table, $record);
        } else {
            return false;
        }
    }

    function insert_response_text($rid, $formdata) {
        // only insert if non-empty content
        if($this->type_id == 10) { // numeric
//            $formdata->{'q'.$this->id} = ereg_replace("[^0-9.\-]*(-?[0-9]*\.?[0-9]*).*", '\1', $formdata->{'q'.$this->id});
            $formdata->{'q'.$this->id} = clean_param($formdata->{'q'.$this->id}, PARAM_NUMBER);
        }

        if(ereg("[^ \t\n]",$formdata->{'q'.$this->id})) {
            $record = new Object();
            $record->response_id = $rid;
            $record->question_id = $this->id;
            $record->response = clean_param($formdata->{'q'.$this->id}, PARAM_CLEAN);
            return insert_record('questionnaire_'.$this->response_table, $record);
        } else {
            return false;
        }
    }

    function insert_response_date($rid, $formdata) {
        $formdata->{'q'.$this->id} = clean_param($formdata->{'q'.$this->id}, PARAM_CLEAN);

        $checkdateresult = '';
        $checkdateresult = check_date($formdata->{'q'.$this->id});
        $thisdate = $formdata->{'q'.$this->id};
        if (substr($checkdateresult,0,5) == 'wrong') {
            return false;
        }
        // now use ISO date formatting
        $checkdateresult = check_date($thisdate, $insert=true);
        $record = new Object();
        $record->response_id = $rid;
        $record->question_id = $this->id;
        $record->response = $checkdateresult;
        return insert_record('questionnaire_'.$this->response_table, $record);
    }

    function insert_resp_single($rid, $formdata) {

        $formdata->{'q'.$this->id} = clean_param($formdata->{'q'.$this->id}, PARAM_CLEAN);

        if(!empty($formdata->{'q'.$this->id})) {
            foreach ($this->choices as $cid => $choice) {
                if (strpos($choice->content, '!other') === 0) {
                    if (!isset($formdata->{'q'.$this->id.'_'.$cid})) {
                        continue;
                    }
                    $other = clean_param($formdata->{'q'.$this->id.'_'.$cid}, PARAM_CLEAN);
                    if(ereg("[^ \t\n]",$other)) {
                        $record = new Object();
                        $record->response_id = $rid;
                        $record->question_id = $this->id;
                        $record->choice_id = $cid;
                        $record->response = $other;
                        $resid = insert_record('questionnaire_response_other', $record);
                        $formdata->{'q'.$this->id} = $cid;
                        break;
                    }
                }
            }
        }
        if(ereg("other_q([0-9]+)", (isset($formdata->{'q'.$this->id})?$formdata->{'q'.$this->id}:''), $regs)) {
            $cid=$regs[1];
            if (!isset($formdata->{'q'.$this->id.'_'.$cid})) {
                break; // out of the case
            }
            $other = clean_param($formdata->{'q'.$this->id.'_'.$cid}, PARAM_CLEAN);
            if(ereg("[^ \t\n]",$other)) {
                $record = new object;
                $record->response_id = $rid;
                $record->question_id = $this->id;
                $record->choice_id = $cid;
                $record->response = $other;
                $resid = insert_record('questionnaire_response_other', $record);
                $formdata->{'q'.$this->id} = $cid;
            }
        }
        $record = new Object();
        $record->response_id = $rid;
        $record->question_id = $this->id;
        $record->choice_id = isset($formdata->{'q'.$this->id}) ? $formdata->{'q'.$this->id} : 0;
        if ($record->choice_id) {// if "no answer" then choice_id is empty (CONTRIB-846)
            return insert_record('questionnaire_'.$this->response_table, $record);
        } else {
            return false;
        }
    }

    function insert_resp_multiple($rid, $formdata) {

        foreach ($this->choices as $cid => $choice) {
            if (strpos($choice->content, '!other') === 0) {
                if (!isset($formdata->{'q'.$this->id.'_'.$cid}) || empty($formdata->{'q'.$this->id.'_'.$cid})) {
                    continue;
                }
                if (!isset($formdata->{'q'.$this->id})) {
                    $formdata->{'q'.$this->id} = array($cid);
                } else {
                    array_push($formdata->{'q'.$this->id}, $cid);
                }
                $other = clean_param($formdata->{'q'.$this->id.'_'.$cid}, PARAM_CLEAN);
                if(ereg("[^ \t\n]",$other)) {
                    $record = new Object();
                    $record->response_id = $rid;
                    $record->question_id = $this->id;
                    $record->choice_id = $cid;
                    $record->response = $other;
                    $resid = insert_record('questionnaire_response_other', $record);
                }
            }
        }

        if(!isset($formdata->{'q'.$this->id}) || count($formdata->{'q'.$this->id}) < 1) {
            return false;
        }

        foreach($formdata->{'q'.$this->id} as $cid) {
            $cid = clean_param($cid, PARAM_CLEAN);
            if ($cid != 0) { //do not save response if choice is empty
                if(ereg("other_q[0-9]+", $cid))
                    continue;
                $record = new Object();
                $record->response_id = $rid;
                $record->question_id = $this->id;
                $record->choice_id = $cid;
                $resid = insert_record('questionnaire_'.$this->response_table, $record);
            }
        }
        return $resid;
    }

    function insert_response_rank($rid, $formdata) {
        if($this->type_id == 8) { // Rank
            $resid = false;
            foreach ($this->choices as $cid => $choice) {
                if (!isset($formdata->{'q'.$this->id.'_'.$cid})) {
                    continue;
                }
                $val = clean_param($formdata->{'q'.$this->id.'_'.$cid}, PARAM_CLEAN);
                if($val == get_string('notapplicable', 'questionnaire')) {
                    $rank = -1;
                } else {
                    $rank = intval($val);
                }
                $record = new Object();
                $record->response_id = $rid;
                $record->question_id = $this->id;
                $record->choice_id = $cid;
                $record->rank = $rank;
                $resid = insert_record('questionnaire_'.$this->response_table, $record);
            }
            return $resid;
        } else { // THIS SHOULD NEVER HAPPEN
            $r = clean_param($formdata->{'q'.$this->id}, PARAM_CLEAN);
            if($formdata->{'q'.$this->id} == get_string('notapplicable', 'questionnaire')) {
                $rank = -1;
            } else {
                $rank = intval($formdata->{'q'.$this->id});
            }
            $record = new Object();
            $record->response_id = $rid;
            $record->question_id = $this->id;
            $record->rank = $rank;
            return insert_record('questionnaire_'.$this->response_table, $record);
        }
    }


/// Results Methods:
/// The following methods are defined by the tables they use. Questions should call the
/// appropriate function based on its table.

    function get_results($rids=false) {

        $method = 'get_'.$this->response_table.'_results';
        if (method_exists($this, $method)) {
            return $this->$method($rids);
        } else {
            return false;
        }
    }

    function get_response_bool_results($rids=false) {
        global $CFG;

        $ridstr = '';
        if (is_array($rids)) {
            foreach ($rids as $rid) {
                $ridstr .= (empty($ridstr) ? ' AND response_id IN ('.$rid : ', '.$rid);
            }
            $ridstr .= ') ';
        } else if (is_int($rids)) {
            $ridstr = ' AND response_id = '.$rids.' ';
        }

        $sql = 'SELECT choice_id, COUNT(response_id) AS num '.
               'FROM '.$CFG->prefix.'questionnaire_'.$this->response_table.' '.
               'WHERE question_id='.$this->id.$ridstr.' AND choice_id != \'\' '.
               'GROUP BY choice_id';
        return get_records_sql($sql);
    }

    function get_response_text_results($rids = false) {
        global $CFG;

        $ridstr = '';
        if (is_array($rids)) {
            foreach ($rids as $rid) {
                $ridstr .= (empty($ridstr) ? ' AND response_id IN ('.$rid : ', '.$rid);
            }
            $ridstr .= ') ';
        } else if (is_int($rids)) {
            $ridstr = ' AND response_id = '.$rids.' ';
        }
        $sql = 'SELECT id, response '.
               'FROM '.$CFG->prefix.'questionnaire_'.$this->response_table.' '.
               'WHERE question_id='.$this->id.$ridstr;
        return get_records_sql($sql);
    }


    function get_response_date_results($rids = false) {
        global $CFG;

        $ridstr = '';
        if (is_array($rids)) {
            foreach ($rids as $rid) {
                $ridstr .= (empty($ridstr) ? ' AND response_id IN ('.$rid : ', '.$rid);
            }
            $ridstr .= ') ';
        } else if (is_int($rids)) {
            $ridstr = ' AND response_id = '.$rids.' ';
        }

        $sql = 'SELECT id, response '.
               'FROM '.$CFG->prefix.'questionnaire_'.$this->response_table.' '.
               'WHERE question_id='.$this->id.$ridstr;

        return get_records_sql($sql);
    }

    function get_response_single_results($rids=false) {
        global $CFG;
        $ridstr = '';
        if (is_array($rids)) {
            foreach ($rids as $rid) {
                $ridstr .= (empty($ridstr) ? ' AND response_id IN ('.$rid : ', '.$rid);
            }
            $ridstr .= ') ';
        } else if (is_int($rids)) {
            $ridstr = ' AND response_id = '.$rids.' ';
        }
        // JR added qc.id to preserve original choices ordering
        $sql = 'SELECT rt.id, qc.id as cid, qc.content '.
               'FROM '.$CFG->prefix.'questionnaire_quest_choice qc, '.
                       $CFG->prefix.'questionnaire_'.$this->response_table.' rt '.
               'WHERE qc.question_id='.$this->id.' AND qc.content NOT LIKE \'!other%\' AND '.
                     'rt.question_id=qc.question_id AND rt.choice_id=qc.id'.$ridstr.' '.
               'ORDER BY qc.id';

        $rows = get_records_sql($sql);

        // handle 'other...'
        $sql = 'SELECT rt.id, rt.response, qc.content '.
               'FROM '.$CFG->prefix.'questionnaire_response_other rt, '.
                       $CFG->prefix.'questionnaire_quest_choice qc '.
               'WHERE rt.question_id='.$this->id.' AND rt.choice_id=qc.id'.$ridstr.' '.
               'ORDER BY qc.id';

        if ($recs = get_records_sql($sql)) {
            $i = 1;
            foreach ($recs as $rec) {
                $rows['other'.$i]->content = $rec->content;
                $rows['other'.$i]->response = $rec->response;
                $i++;
            }
        }

        return $rows;
    }

    function get_response_multiple_results($rids) {
        return $this->get_response_single_results($rids); // JR both functions are equivalent
    }

    function get_response_rank_results($rids=false, $guicross=false, $sort) { //TODO
        global $CFG;

        $ridstr = '';
        if (is_array($rids)) {
            foreach ($rids as $rid) {
                $ridstr .= (empty($ridstr) ? ' AND response_id IN ('.$rid : ', '.$rid);
            }
            $ridstr .= ') ';
        } else if (is_int($rids)) {
            $ridstr = ' AND response_id = '.$rids.' ';
        }

        if($this->type_id  == 8) { //Rank
         // JR there can't be an !other field in rating questions ???
            $select = 'question_id='.$this->id.' AND content NOT LIKE \'!other%\' ORDER BY id ASC'; //JR 4 NOV 2009 added ORDER
            if ($rows = get_records_select('questionnaire_quest_choice', $select)) {
                foreach ($rows as $row) {
                    $this->counts[$row->content] = null;
                    $nbna = count_records('questionnaire_response_rank', 'question_id', $this->id, 'choice_id', $row->id, 'rank', '-1');
                    $this->counts[$row->content]->nbna = $nbna;
                }
            }
            $isrestricted = ($this->length < count($this->choices)) && $this->precise == 2;
            // usual case
            if (!$isrestricted) {
                $sql = "SELECT c.id, c.content, a.average, a.num
                        FROM {$CFG->prefix}questionnaire_quest_choice c
                        INNER JOIN
                             (SELECT c2.id, AVG(a2.rank+1) AS average, COUNT(a2.response_id) AS num
                              FROM {$CFG->prefix}questionnaire_quest_choice c2, {$CFG->prefix}questionnaire_{$this->response_table} a2
                              WHERE c2.question_id = {$this->id} AND a2.question_id = {$this->id} AND a2.choice_id = c2.id AND a2.rank >= 0{$ridstr}
                              GROUP BY c2.id) a ON a.id = c.id";
                if ($results = get_records_sql($sql) ){
	                /// Reindex by 'content'. Can't do this from the query as it won't work with MS-SQL.
	                foreach ($results as $key => $result) {
	                    $results[$result->content] = $result;
	                    unset($results[$key]);
	                }
	                return $results;
                }

            // case where scaleitems is less than possible choices
            } else {
                $sql = "SELECT c.id, c.content, a.sum, a.num
                        FROM {$CFG->prefix}questionnaire_quest_choice c
                        INNER JOIN
                             (SELECT c2.id, SUM(a2.rank+1) AS sum, COUNT(a2.response_id) AS num
                              FROM {$CFG->prefix}questionnaire_quest_choice c2, {$CFG->prefix}questionnaire_{$this->response_table} a2
                              WHERE c2.question_id = {$this->id} AND a2.question_id = {$this->id} AND a2.choice_id = c2.id AND a2.rank >= 0{$ridstr}
                              GROUP BY c2.id) a ON a.id = c.id";
                if ($results = get_records_sql($sql) ){
	                // formula to calculate the best ranking order
	                $nbresponses = count($rids);
	                foreach ($results as $key => $result) {
	                    $result->average = ($result->sum + ($nbresponses - $result->num) * ($this->length + 1)) / $nbresponses;
	                    $results[$result->content] = $result;
	                    unset($results[$key]);
	                }
	                return $results;
                }
            }
        } else {
            $sql = 'SELECT A.rank, COUNT(A.response_id) AS num '.
                   'FROM '.$CFG->prefix.'questionnaire_'.$this->response_table.' A '.
                   'WHERE A.question_id='.$this->id.$ridstr.' '.
                   'GROUP BY A.rank';
            return get_records_sql($sql);
        }
    }

/// Display Methods
    function theme_bars_url () {
        global $CFG, $questionnaire;
        $currentcss = '';
        $image_url = '';
        $survey_id = $this->survey_id;
        if (isset($questionnaire)) {
            $currenttheme = $questionnaire->survey->theme;
        }
        $currhbar = substr($currenttheme,0, strlen($currenttheme) - 4).'_';
        $filename = $image_url.'hbar_l.gif';
        $currthemehbarurl = $CFG->dirroot.'/mod/questionnaire/images/'.$currhbar;
        if ( !file_exists($currthemehbarurl.'hbar.gif')
                || !file_exists($currthemehbarurl.'hbar_l.gif')
                || !file_exists($currthemehbarurl.'hbar_r.gif') ) {
            $currhbar = '';
        }
        return $currhbar;
    }

    function display_results($rids=false, $guicross=false, $sort) {
        $method = 'display_'.$this->response_table.'_results';
        if (method_exists($this, $method)) {
            $a = $this->$method($rids, $guicross, $sort);
            echo ('<hr />');
            return $a;
        } else {
            return false;
        }
    }

    function display_response_bool_results($rids=false, $guicross=false) {
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
                $this->counts[$this->choice] = $count;
            }
            $this->mkrespercent(count($rids), $this->precise, $prtotal, $guicross, $sort='');
        } else {
            print_string('noresponsedata', 'questionnaire');
        }
    }

    function display_response_text_results($rids = false) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_response_text_results($rids)) {
        	/// Count identical answers (numeric questions only)
        	foreach ($rows as $row) {
					if(!empty($row->response)) {
                $this->text = $row->response;
                	$textidx = clean_text($this->text);
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
	                    $this->userid[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $isnumeric = $this->type_id == 10;
            if ($isnumeric) {
                $this->mkreslistnumeric(count($rids), $this->precise);
            } else {
                $this->mkreslist(count($rids), $this->precise, $prtotal);
            }
        } else {
            print_string('noresponsedata', 'questionnaire');
        }
    }

    function display_response_date_results($rids = false) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_response_date_results($rids)) {
            foreach ($rows as $row) {
            /// Count identical answers (case insensitive)
            	$this->text = $row->response;
                if(!empty($this->text)) {
                    $dateparts = split('-', $this->text);
                    $this->text = make_timestamp($dateparts[0], $dateparts[1], $dateparts[2]); // Unix timestamp
                    $textidx = clean_text($this->text);
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $this->mkreslistdate(count($rids), $this->precise, $prtotal);
        } else {
            print_string('noresponsedata', 'questionnaire');
        }
    }

    function display_resp_single_results($rids=false, $guicross=false, $sort) {
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
                    $contents = choice_values($row->content);
                    $this->choice = $contents->text.$contents->image;
                    $textidx = $this->choice;
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $this->mkrespercent(count($rids), $this->precise, $prtotal, $guicross, $sort);
        } else {
            print_string('noresponsedata', 'questionnaire');
        }
    }

    function display_resp_multiple_results($rids=false, $guicross=false, $sort) {
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
                    $contents = choice_values($row->content);
                    $this->choice = $contents->text.$contents->image;
                    $textidx = $this->choice;
                    $this->counts[$textidx] = !empty($this->counts[$textidx]) ? ($this->counts[$textidx] + 1) : 1;
                }
            }
            $this->mkrespercent(count($rids), $this->precise, 0, $guicross, $sort);
        } else {
            print_string('noresponsedata', 'questionnaire');
        }
    }

    function display_response_rank_results($rids=false, $guicross=false, $sort) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_response_rank_results($rids, $guicross=false, $sort)) {
        	if($this->type_id == 8) { //Rank
                foreach ($this->counts as $key => $value) {
                    $ccontent = $key;
                    if (array_key_exists($ccontent, $rows)) {
	                    $avg = $rows[$ccontent]->average;
	                    $this->counts[$ccontent]->num = $rows[$ccontent]->num;
                    } else {
                    	$avg = 0;
                    }
                    $this->counts[$ccontent]->avg = $avg;
                }
                $this->mkresavg(count($rids), $this->precise, $prtotal, $this->length, $sort);
            } else { // else condition probably never met !
                foreach ($rows as $row) {
                    $rank = $row->rank;
                    $num = $row->num;
                    if($rank == -1) {
                        $rank = get_string('notapplicable', 'questionnaire');
                    }
                    $this->counts[$rank] += $num;
                }
                echo format_text($this->content, FORMAT_HTML).'</div>';
                $this->mkresrank(count($rids), $this->precise, $prtotal);
            }
        } else {
            print_string('noresponsedata', 'questionnaire');
        }
    }

    function question_display($data, $qnum='') {
        global $QTYPENAMES;
        $method = $QTYPENAMES[$this->type_id].'_survey_display';
        if (method_exists($this, $method)) {
            $this->questionstart_survey_display($qnum, $data);
            $this->$method($data);
            $this->questionend_survey_display($qnum);
        } else {
            error('Display method not defined for question.');
        }
    }

    function survey_display($data, $qnum='', $usehtmleditor=null) {
        if (!is_null($usehtmleditor)) {
           $this->usehtmleditor = can_use_html_editor();
        } else {
            $this->usehtmleditor = $usehtmleditor;
        }

        $this->question_display($data, $qnum);
    }

    function questionstart_survey_display($qnum, $data='') {
        global $CFG;
        if ($this->type_id == QUESSECTIONTEXT) {
            return;
        }

        echo '
    <div class="questioncontainer">
        <div class="qnOuter">
            <table class="qnInnerTable" style="width:100%" cellpadding="10"  cellspacing="1">
                <tr>
                    <td class="qnInnerTd" rowspan="2" valign="top">';
                    if ($this->required == 'y') {
                        echo ('<img class="req" title="'.get_string('requiredelement', 'form').'" alt="'.get_string('requiredelement', 'form').
                        '" src="'.$CFG->pixpath.'/req.gif" />');
                    }
                   echo $qnum.'
                   </td>
                    <td class="qnInner">'.
                    format_text($this->content, FORMAT_HTML).'
                    </td>
                </tr>
                <tr>
                    <td class="qnType">
        ';
    }

    function questionend_survey_display() {
        if ($this->type_id == QUESSECTIONTEXT) {
            return;
        }
        echo '
                    </td>
                </tr>
            </table>
         </div>
    </div>
        ';
    }
    function response_check_required ($data) { // JR check all question types
        if ($this->type_id == 8) { // Rate is a special case
            foreach ($this->choices as $cid => $choice) {
                $str = 'q'."{$this->id}_$cid";
                if (isset($data->$str)) {
                    return ('&nbsp;');
                }
            }
        }
        if( ($this->required == 'y') &&  empty($data->{'q'.$this->id}) ) {
            return ('*');
        } else {
            return ('&nbsp;');
        }
    }

    function yesno_survey_display($data) {
        /// moved choose_from_radio() here to fix unwanted selection of yesno buttons and radio buttons with identical ID
        static $stryes = null;
        static $strno = null;
        global $idcounter;  // To make sure all radio buttons have unique ids. // JR 20 NOV 2007

        if (is_null($stryes)) {
             $stryes = get_string('yes');
             $strno = get_string('no');
        }

        $val1 = 'y';
        $val2 = 'n';

        $options = array($val1 => $stryes, $val2 => $strno);
        $name =  'q'.$this->id;
        $checked=(isset($data->{'q'.$this->id})?$data->{'q'.$this->id}:'');
        $output = '<span class="radiogroup '.$name."\">\n";

        $currentradio = 0;
        $ischecked = false;
        foreach ($options as $value => $label) {
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            $output .= ' <span class="radioelement '.$name.' rb'.$currentradio."\">";
            $output .= '<input name="'.$name.'" id="'.$htmlid.'" type="radio" value="'.$value.'"';
            if ($value == $checked) {
                $output .= ' checked="checked"';
                $ischecked = true;
            }
            $output .= ' /> <label for="'.$htmlid.'" style="vertical-align:top;">'.  $label .'</label></span>' .  "\n";
            $currentradio = ($currentradio + 1) % 2;
        }
        // CONTRIB-846
        if ($this->required == 'n') {
            $id='';
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"'.
                ' onclick="other_check_empty(name, value)"';
            if (!$ischecked) {
                $output .= ' checked="checked"';
            }
            $content = get_string('noanswer','questionnaire');
            $output .= ' />&nbsp;<label for="'.$htmlid.'" style="vertical-align:top;">'.
                format_text($content, FORMAT_HTML).'</label>&nbsp;&nbsp;';
            $currentradio = ($currentradio + 1) % 2;
        }
        // end CONTRIB-846

        $output .= '</span>' .  "\n";
        echo $output;
    }

    function text_survey_display($data) { // Text Box
        echo '<input type="text" size="'.$this->length.'" name="q'.$this->id.'"'.
             ($this->precise > 0 ? ' maxlength="'.$this->precise.'"' : '').' value="'.
             (isset($data->{'q'.$this->id}) ? stripslashes($data->{'q'.$this->id}) : '').'" />';
    }

    function essay_survey_display($data) { // Essay
        $cols = $this->length;
        $rows = $this->precise;
        $str = '';
        // if NO cols or rows specified: use HTML editor (if available in this context)
        if (!$cols || !$rows) {
            $cols = 60;
            $rows = 5;
            $canusehtmleditor = $this->usehtmleditor;

        // if cols & rows specified, do not use HTML editor but plain text box
        // use default (60 cols and 5 rows) OR user-specified values
        } else {
            $canusehtmleditor = false;
        }
        $name = 'q'.$this->id;
        if (isset($data->{'q'.$this->id})) {
            $value = stripslashes($data->{'q'.$this->id});
        } else {
            $value = '';
        }
        if ($canusehtmleditor) {
            echo ('<div style="width:600px">'); // JR to avoid HTMLarea spreading the whole width of window
            print_textarea($canusehtmleditor, '', '', $cols, $rows, $name, $value);
            echo ('</div>');
            use_html_editor($name); //use HTML EDITOR for this textarea only...
        } else {
            $str .= '<textarea class="form-textarea" id="edit-'. $name .'" name="'. $name .'" rows="'. $rows .'" cols="'. $cols .'">'
            .s($value).'</textarea>';
            echo $str;
        }
    }

    function radio_survey_display($data) { // Radio buttons
        global $idcounter;  // To make sure all radio buttons have unique ids. // JR 20 NOV 2007
        $currentradio = 0;
        $otherempty = false;
        $output = '';
        // find out which radio button is checked (if any); yields choice ID
        if (isset($data->{'q'.$this->id})) {
            $checked = $data->{'q'.$this->id};
        } else {
            $checked = '';
        }
        $horizontal = $this->length;
        $ischecked = false;
        foreach ($this->choices as $id => $choice) {
            $other = strpos($choice->content, '!other');
            if ($other !== 0)  { // this is a normal radio button
                $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
                if ($horizontal) {
                    $output .= ' <span class="radioelement">';
                }
                $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"'.
                    ' onclick="other_check_empty(name, value)"';
                if ($id == $checked) {
                    $output .= ' checked="checked"';
                    $ischecked = true;
                }
                $content = $choice->content;
                $valign = 'top';
                $contents = choice_values($choice->content);
                if ($contents->image != '') {
                     $valign = 'baseline';
                }
                $output .= ' />&nbsp;<label for="'.$htmlid.'" style="vertical-align:'.$valign.';">'.
                    format_text($contents->text, FORMAT_HTML).$contents->image.'</label>&nbsp;&nbsp;';
                $currentradio = ($currentradio + 1) % 2;
                if ($horizontal) {
                    $output .='</span>';
                } else {
                    $output .= '<br />';
                }
            } else { // radio button with associated !other text field
                $other_text = preg_replace(
                        array("/^!other=/","/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;
                $otherempty = false;
                $otherid = 'q'.$this->id.'_'.$checked;
                if (substr($checked, 0, 6) == 'other_') { // fix bug CONTRIB-222
                    $checked = substr($checked,6);
                }
                $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
                if ($horizontal) {
                    $output .= ' <span style="white-space:nowrap;">';
                }
                $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="other_'.$id.'"'.
                    ' onclick="other_check_empty(name, value)"';
                if (($id == $checked) || !empty($data->$cid)) {
                    $output .= ' checked="checked"';
                    $ischecked = true;
                    if (!$data->$cid) {
                        $otherempty = true;
                    }
                }
                $output .= ' /> <label for="'.$htmlid.'" style="vertical-align:top;">'.format_text($other_text, FORMAT_HTML).'</label>';
                $currentradio = ($currentradio + 1) % 2;

                $choices['other_'.$cid] = $other_text;
                $output .= '&nbsp;<input type="text" size="25" name="'.$cid.'" onclick="other_check(name)"';
                if (isset($data->$cid)) {
                    $output .= ' value="'.stripslashes($data->$cid) .'"';
                }
                $output .= ' />';
                if ($horizontal) {
                    $output .= '</span>';
                } else {
                    $output .= '<br />';
                }
            }
        }

        // CONTRIB-846
        if ($this->required == 'n') {
            $id='';
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            if ($horizontal) {
                $output .= ' <span class="radioelement">';
            }
            $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"'.
                ' onclick="other_check_empty(name, value)"';
            if (!$ischecked) {
                $output .= ' checked="checked"';
            }
            $valign = 'top';
            $content = get_string('noanswer','questionnaire');
            $output .= ' />&nbsp;<label for="'.$htmlid.'" style="vertical-align:'.$valign.';">'.
                format_text($content, FORMAT_HTML).'</label>&nbsp;&nbsp;';
            $currentradio = ($currentradio + 1) % 2;
            if ($horizontal) {
                $output .='</span>';
            } else {
                $output .= '<br />';
            }
        }
        // end CONTRIB-846

        echo $output;
        if ($otherempty) {
            questionnaire_notify (get_string('otherempty', 'questionnaire'));
        }
    }

    function check_survey_display($data) { // Check boxes
        $otherempty = false;
        if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
            $data->{'q'.$this->id} = array();
        }
        // Verify that number of checked boxes (nbboxes) is within set limits (length = min; precision = max)
        if ( $data->{'q'.$this->id} ) {
            $otherempty = false;
            $boxes = $data->{'q'.$this->id};
            $nbboxes = count($boxes);
            foreach ($boxes as $box) {
                $pos = strpos($box, 'other_');
                if (is_int($pos) == true) {
                    $otherchoice = substr($box,6);
                    $resp = 'q'.$this->id.''.substr($box,5);
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
                $min = $max; // sanity check
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
        foreach ($this->choices as $id => $choice) {

            $other = strpos($choice->content, '!other');
            if ($other !== 0)  { // this is a normal check box
                $contents = choice_values($choice->content);
                print_checkbox ('q'.$this->id.'[]', $id, in_array($id, $data->{'q'.$this->id}),
                                format_text($contents->text, FORMAT_HTML).$contents->image);
                echo '<br />';
            } else { // check box with associated !other text field
                // in case length field has been used to enter max number of choices, set it to 20
                $other_text = preg_replace(
                        array("/^!other=/","/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;
                if (!empty($data->$cid)) {
                    $checked = true;
                } else {
                    $checked = false;
                }
                $name = 'q'.$this->id.'[]';
                $value = 'other_'.$id;
                print_checkbox ($name, $value, $checked, format_text($other_text.'', FORMAT_HTML), '', 'checkbox_empty(name)');
                $other_text = '&nbsp;<input type="text" size="25" name="'.$cid.'" onclick="other_check(name)"';
                if ($cid) {
                    $other_text .= ' value="'. (!empty($data->$cid) ? stripslashes($data->$cid) : '') .'"';
                }
                $other_text .= ' />';
                echo $other_text.'<br />';
            }
        }
            if ($otherempty) {
                questionnaire_notify (get_string('otherempty', 'questionnaire'));
            }
    }

    function drop_survey_display($data) { // Drop
        $options = array();
        foreach ($this->choices as $id => $choice) {
            $contents = choice_values($choice->content);
            $options[$id] = format_text($contents->text, FORMAT_HTML);
        }
        choose_from_menu ($options, 'q'.$this->id, (isset($data->{'q'.$this->id})?$data->{'q'.$this->id}:''));
    }

    function rate_survey_display($data) { // Rate
        if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
            $data->{'q'.$this->id} = array();
        }
        echo '<table border="0" cellspacing="1" cellpadding="0">';
        echo '<tbody>';
        echo '<tr>';
        echo '<td></td>';
        $bg = 'qntype c0';
        if ($this->precise == 1) {
            $na = get_string('notapplicable', 'questionnaire');
        } else {
            $na = '';
        }
        if ($this->precise == 2) {
            $order = ' onclick="other_rate_uncheck(name, value)" ';
        } else {
            $order = '';
        }
        $osgood = false;
        if ($this->precise == 3) { // Osgood's semantic differential
            $osgood = true;
        }
        $nameddegrees = 0;
        $n = array();
        $mods = array();
        foreach ($this->choices as $cid => $choice) {
            $content = $choice->content;
            // check for number from 1 to 3 digits, followed by the equal sign = (to accomodate named degrees)
            if (ereg("^([0-9]{1,3})=(.*)$", $content,$ndd)) {
                $n[$nameddegrees] = format_text($ndd[2], FORMAT_HTML);
                $this->choices[$cid] = '';
                $nameddegrees++;
            }
            else {
                $contents = choice_values($content);
                if ($contents->modname) {
                    $choice->content = $contents->text;
                }
            }
        }
        // if we have named degrees, provide for wider degree columns (than for numbers)
        // do not provide wider degree columns if we have an Osgood's semantic differential
        if ($nameddegrees && !$osgood) {
            $colwidth = 'auto';
        } else {
            $colwidth = '40px';
        }
        for ($j = 0; $j < $this->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j+1;
            }
            echo '<td style="width:'.$colwidth.'; text-align:center;" class="'.$bg.'">'.$str.'</td>';
            if ($bg == 'qntype c0') {
                $bg = 'qntype c1';
            } else {
                $bg = 'qntype c0';
            }
        }
        if ($na) {
            echo '<td style="width:'.$colwidth.'; text-align:center;" class="'.$bg.'">'.$na.'</td>';
        }
        echo '</tr>';

        $num = 0;
		if ($this->precise != 2) {  //dev jr 9 JUL 2010
        	$nbchoices = count($this->choices) - $nameddegrees;
		} else { // if "No duplicate choices", can restrict nbchoices to number of rate items specified
			$nbchoices = $this->length;
		}

        foreach ($this->choices as $cid => $choice) {
            $str = 'q'."{$this->id}_$cid";
            for ($j = 0; $j < $this->length; $j++) {
                $num += (isset($data->$str) && ($j == $data->$str));
            }
            $num += (($na != '') && isset($data->$str) && ($data->$str == -1));
        }
        if ( ($num != $nbchoices) && ($num!=0) ) {
            questionnaire_notify(get_string('checkallradiobuttons', 'questionnaire', $nbchoices));
        }
        $bgr = 'qntype r0';
        foreach ($this->choices as $cid => $choice) {
            if (isset($choice->content)) {
                $str = 'q'."{$this->id}_$cid";
                echo '<tr>';
                $content = $choice->content;
                if ($osgood) {
                	list($content, $contentright) = split('[|]', $content);
                }
                echo '<td class="'.$bgr.'">'.format_text($content, FORMAT_HTML).'&nbsp;</td>';
                if ($bgr == 'qntype r0') {
                    $bgr = 'qntype r1';
                } else {
                    $bgr = 'qntype r0';
                }
                $bg = 'qntype c0';
                for ($j = 0; $j < $this->length; $j++) {
                    $checked = ((isset($data->$str) && ($j == $data->$str)) ? ' checked="checked"' : '');
                    echo '<td style="text-align:center" class="'.$bg.'">';
                    echo '<input name="'.$str.'" type="radio" value="'.$j.'"'.$checked.$order.' /></td>';
                    if ($bg == 'qntype c0') {
                        $bg = 'qntype c1';
                    } else {
                        $bg = 'qntype c0';
                    }
                }
                if ($na) {
                    if ( (in_array($na, $data->{'q'.$this->id})) ||
                        (isset($data->$str) && $data->$str == -1) ||
                        $this->required == 'n' ) { // automatically check N/A buttons if rate question is not required
                        $checked = ' checked="checked"';
                    } else {
                        $checked = '';
                    }
                    echo '<td style="width:40; text-align:center" class="'.$bg.'">';
                    echo '<input name="'.$str.'" type="radio" value="'.$na.'"'.$checked.' /></td>';
                }
                if ($osgood) {
                    if ($bgr == 'qntype r0') {
                        $bgr2 = 'qntype r1';
                    } else {
                        $bgr2 = 'qntype r0';
                    }
                    echo '<td class="'.$bgr2.'">&nbsp;'.format_text($contentright, FORMAT_HTML).'</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
    }

    function date_survey_display($data) { // Date

        $date_mess = '&nbsp;'.get_string('dateformatting', 'questionnaire');
        if (!empty($data->{'q'.$this->id})) {
            $dateentered = $data->{'q'.$this->id};
            $setdate = check_date ($dateentered, false);
            if ($setdate == 'wrongdateformat') {
                $msg = get_string('wrongdateformat', 'questionnaire', $dateentered);
                questionnaire_notify($msg);
            } elseif ($setdate == 'wrongdaterange') {
                $msg = get_string('wrongdaterange', 'questionnaire');
                questionnaire_notify($msg);
            } else {
                $data->{'q'.$this->id} = $setdate;
            }
        }
        echo '<input type="text" size="12" name="q'.$this->id.'" maxlength="10" value="'.
             (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '').'" />';
        echo $date_mess;
    }

    function numeric_survey_display($data) { // Numeric
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
                    if($precision) {
                        $pos = strpos($mynumber, '.');
                        if (!$pos) {
                            if (strlen($mynumber) > $this->length) {
                                $mynumber = substr($mynumber, 0 , $this->length);
                            }
                        }
                        $this->length += (1 + $precision); // to allow for n numbers after decimal point
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

        echo '<input type="text" size="'.$this->length.'" name="q'.$this->id.'" maxlength="'.$this->length.
             '" value="'.(isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '').'" />';
    }

    function sectiontext_survey_display($data) {
        echo '
    <div class="questioncontainer">
        <div class="qnOuter">
            <table class="qnInnerTable" style="width:100%" cellpadding="10"  cellspacing="1">
                <tr>
                    <td class="qnInnerTd" valign="top">&nbsp;</td>
                    <td class="qnInner" style="height:35px">' .
						format_text($this->content, FORMAT_HTML).'
                    </td>
                </tr>
            </table>
        </div>
    </div>
        ';
    }

///***
    function response_display($data, $qnum='') {
        global $QTYPENAMES;

        $method = $QTYPENAMES[$this->type_id].'_response_display';
        if (method_exists($this, $method)) {
            $this->questionstart_survey_display($qnum, $data);
            $this->$method($data);
            $this->questionend_survey_display($qnum);
        } else {
            error('Display method not defined for question.');
        }
    }

    function yesno_response_display($data) {
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

    function text_response_display($data) {
        $response = !empty($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '&nbsp;';
        echo '<div class="response text">'.$response.'</div>';
    }

    function essay_response_display($data) {
        $response = !empty($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '&nbsp;';
        echo '<div class="response text">'.(format_text($response, FORMAT_HTML)).'</div>';
    }

    function radio_response_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $currentradio = 0;
        $checked = (isset($data->{'q'.$this->id})?$data->{'q'.$this->id}:'');
        echo '<div class="response radio">';
        foreach ($this->choices as $id => $choice) {
            if (strpos($choice->content, '!other') !== 0) {
                $contents = choice_values($choice->content);
                $choice->content = $contents->text.$contents->image;
                if ($id == $checked) {
                    echo '<span class="selected">'.
                         '<input type="radio" name="'.$id.$uniquetag++.'" checked="checked" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span><br />';
                } else {
                    echo '<span class="unselected">'.
                         '<input type="radio" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span><br />';
                }
                $currentradio = ($currentradio + 1) % 2;

            } else {
                $other_text = preg_replace(
                        array("/^!other=/","/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;

                if (isset($data->{'q'.$this->id.'_'.$id})) {
                    echo '<span class="selected">'.
                         '<input type="radio" name="'.$id.$uniquetag++.'" checked="checked" /> '.$other_text.' ';
                    echo '<span class="response text">';
                    echo (!empty($data->$cid) ? htmlspecialchars($data->$cid) : '&nbsp;');
                    echo '</span></span><br />';
                } else {
                    echo '<span class="unselected"><input type="radio" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         $other_text.'</span><br />';
                }
            }
        }
        echo '</div>';
    }

    function check_response_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
            $data->{'q'.$this->id} = array();
        }

        echo '<div class="response check">';
        foreach ($this->choices as $id => $choice) {
            if (strpos($choice->content, '!other') !== 0) {
                $contents = choice_values($choice->content);
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
                $other_text = preg_replace(
                        array("/^!other=/","/^!other/U"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;

                if (isset($data->$cid)) {
                    echo '<span class="selected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" checked="checked" onclick="this.checked=true;" /> '.
                         ($other_text === '' ? $id : $other_text).' ';
                    echo '<span class="response text">';
                    echo (!empty($data->$cid) ? htmlspecialchars($data->$cid) : '&nbsp;');
                    echo '</span></span><br />';
                } else {
                    echo '<span class="unselected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         ($other_text === '' ? $id : $other_text).'</span><br />';
                }
            }
        }
        echo '</div>';
    }

    function drop_response_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $options = array();
        foreach ($this->choices as $id => $choice) {
            $contents = choice_values($choice->content);
            $options[$id] = format_text($contents->text, FORMAT_HTML);
        }
        echo '<div class="response drop">';
        choose_from_menu ($options, 'q'.$this->id.$uniquetag++, (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} :''));
        if (isset($data->{'q'.$this->id}) ) {
           echo ': <span class="selected">'.$options[$data->{'q'.$this->id}].'</span></div>';
        }
    }

    function rate_response_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
            $data->{'q'.$this->id} = array();
        }

        echo '<div class="response rate">';
        echo '<table border="0" cellspacing="1" cellpadding="0">';
        echo '<tbody><tr><td></td>';
        $bg = 'qntype c0';
        $osgood = false;
        if ($this->precise == 3) { // Osgood's semantic differential
            $osgood = true;
        }
        $nameddegrees = 0;
        $cidnamed = array();
        $n = array();
        foreach ($this->choices as $cid => $choice) {
            $content = $choice->content;
             if (ereg("^[0-9]{1,3}=", $content,$ndd)) {
                $n[$nameddegrees] = format_text(substr($content, strlen($ndd[0])), FORMAT_HTML);
                $cidnamed[$cid] = true;
                $nameddegrees++;
             }
        }
        if ($nameddegrees && !$osgood) {
            $colwidth = 80;
        } else {
            $colwidth = 40;
        }

        for ($j = 0; $j < $this->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j+1;
            }
            echo '<td style="width:'.$colwidth.'; text-align:center" class="'.$bg.'">'.$str.'</td>';
            if ($bg == 'qntype c0') {
                $bg = 'qntype c1';
            } else {
                $bg = 'qntype c0';
            }
        }
        if ($this->precise == 1) {
            echo '<td style="width:'.$colwidth.'; text-align:center" class="'.$bg.'">'.get_string('notapplicable', 'questionnaire').'</td>';
        }
        echo '</tr>';

        foreach ($this->choices as $cid => $choice) {
            // do not print column names if named column exist
            if (!array_key_exists($cid, $cidnamed)) {
                $str = 'q'."{$this->id}_$cid";
                echo '<tr>';
                $content = $choice->content;
                $contents = choice_values($content);
                if ($contents->modname) {
                    $content = $contents->text;
                }
                if ($osgood) {
                    list($content, $contentright) = split('[|]', $content);
                }
                echo '<td align="left">'.format_text($content, FORMAT_HTML).'&nbsp;</td>';
                $bg = 'qntype c0';
                for ($j = 0; $j < $this->length; $j++) {
                    $checked = ((isset($data->$str) && ($j == $data->$str)) ? ' checked="checked"' : '');
                    $checkedna = ((isset($data->$str) && ($data->$str == -1)) ? ' checked="checked"' : ''); // N/A column checked
                    echo '<td style="width:40; text-align:center;" class="'.$bg.'">';
                    if ($checked) {
                        echo '<span class="selected">'.
                             '<input type="radio" name="'.$str.$j.$uniquetag++.'" checked="checked" /></span>';
                    } else {
                            echo '<span class="unselected">'.
                                 '<input type="radio" name="'.$str.$j.$uniquetag++.'" onclick="this.checked=false;" /></span>';
                    }
                    echo '</td>';
                    if ($bg == 'qntype c0') {
                        $bg = 'qntype c1';
                    } else {
                        $bg = 'qntype c0';
                    }
                }
                if ($this->precise == 1) { // N/A column
                    echo '<td style="width:40; text-align:center;" class="'.$bg.'">';
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
        echo '</tbody></table></div>';
    }

    function date_response_display($data) {
        if (isset($data->{'q'.$this->id})) {
            echo '<div class="response date">';
            echo('<span class="selected">'.$data->{'q'.$this->id}.'</span>');
            echo '</div>';
        }
    }

    function numeric_response_display($data) {
        $this->length++; // for sign
        if($this->precise) {
            $this->length += 1 + $this->precise;
        }
        echo '<div class="response numeric">';
        if (isset($data->{'q'.$this->id})) {
            echo('<span class="selected">'.$data->{'q'.$this->id}.'</span>');
        }
        echo '</div>';
    }

    function sectiontext_response_display($data) {
        echo '
    <div class="questioncontainer">
        <div class="qnOuter">
            <table class="qnInnerTable" style="width:100%" cellpadding="10"  cellspacing="1">
                <tr>
                    <td class="qnInnerTd" style="vertical-align:top;">&nbsp;</td>
                    <td class="qnInner" style="height:35px">' .
                    $this->content.'
                    </td>
                </tr>
            </table>
        </div>
    </div>
        ';
    }
///****

    /* {{{ proto void mkrespercent(array weights, int total, int precision, bool show_totals)
      Builds HTML showing PERCENTAGE results. */
    function mkrespercent($total, $precision, $showTotals, $guicross=false, $sort) {
        global $CFG;
        $precision = 0;
        $i=0;
        $alt = '';
        $bg='';
        $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
        $currhbar = $this->theme_bars_url();
        $strtotal = get_string('total', 'questionnaire'); //devjr
        $table = new Object();
        $table->size = array();
        $table->align = array();
        $table->head = array();
        $table->wrap = array();
        if ($guicross) {
            $table->size[] = '34';
            $table->align[] = 'center';
            $table->head[] = ' ';
            $table->wrap[] = '';
        }
        $table->size = array_merge($table->size, array('*', '50%', '7%'));
        $table->align = array_merge($table->align, array('left', 'left', 'right'));
        $table->wrap = array_merge($table->wrap, array('', 'nowrap', ''));
        $table->head = array_merge($table->head, array(get_string('response', 'questionnaire'),
                       get_string('average', 'questionnaire'), get_string('total', 'questionnaire'))); //devjr

        if (!empty($this->counts) && is_array($this->counts)) {
            $pos = 0;
            switch ($sort) {
            case 'ascending':
            	asort($this->counts); // DEV JR
            	break;
            case 'descending':
            	arsort($this->counts); // DEV JR
            	break;
            }
			$numresponses = 0; //devjr
			foreach ($this->counts as $key => $value) {
				$numresponses = $numresponses + $value;
			}
			reset ($this->counts);
            while(list($content,$num) = each($this->counts)) {
				if($num>0) { $percent = $num/$numresponses*100.0; } //devjr
                else { $percent = 0; }
                if($percent > 100) {
                    $percent = 100;
                }
                if ($num) {
                    $out = '&nbsp;<img alt="'.$alt.'" src="'.$image_url.$currhbar.'hbar_l.gif" height="9" width="4" />'.
                           sprintf('<img alt="'.$alt.'" src="'.$image_url.$currhbar.'hbar.gif" height="9" width="%d" />', $percent*4).
                           '<img alt="'.$alt.'" src="'.$image_url.$currhbar.'hbar_r.gif" height="9" width="4" />'.
                           sprintf('&nbsp;%.'.$precision.'f%%', $percent);
                } else {
                    $out = '';
                }
                $tabledata = array();
                if ($guicross) {
                    $tabledata[] = $this->mkcrossformat($pos, $this->id, $this->type_id);
                }
                $tabledata = array_merge($tabledata, array(format_text($content, FORMAT_HTML), $out, $num));
                $table->data[] = $tabledata;
                $i += $num;
                $pos++;
            } // end while

            if($showTotals) {
                if($i>0) { $percent = $i/$total*100.0; }
                else { $percent = 0; }
                if($percent > 100) {
                    $percent = 100;
                }

                $out = '&nbsp;<img alt="'.$alt.'" src="'.$image_url.$currhbar.'thbar_l.gif" height="9" width="4" />'.
                       sprintf('<img alt="'.$alt.'" src="'.$image_url.$currhbar.'thbar.gif" height="9" width="%d" />', $percent*4).
                       '<img alt="'.$alt.'" src="'.$image_url.$currhbar.'thbar_r.gif" height="9" width="4" />'.
                       sprintf('&nbsp;%.'.$precision.'f%%', $percent);
                $table->data[] = 'hr';
                $tabledata = array();
                if ($guicross) {
                    $tabledata[] = ' ';
                }
                $tabledata = array_merge($tabledata, array($strtotal, $out, "$i/$total"));
                $table->data[] = $tabledata;
            }
        } else {
            $tabledata = array();
            if ($guicross) {
                $tabledata[] = ' ';
            }
            $tabledata = array_merge($tabledata, array('', get_string('noresponsedata', 'questionnaire')));
            $table->data[] = $tabledata;
        }

        print_table($table);
    }

    /* {{{ proto void mkreslist(array weights, int total, int precision, bool show_totals)
        Builds HTML showing LIST results. */
    function mkreslist($total, $precision, $showTotals) {
        global $CFG;

        if($total == 0) {
            return;
        }

        $strresponse = get_string('response', 'questionnaire');
        $strnum = get_string('num', 'questionnaire');
        $table = new Object();
        $table->align = array('left', 'left');

        $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';

        $table->head = array($strnum, $strresponse);
        $table->size = array('10%', '*');

        if (!empty($this->counts) && is_array($this->counts)) {
            while(list($text,$num) = each($this->counts)) {
                $text = format_text($text, FORMAT_HTML);
                $table->data[] = array($num, $text);
            }
        } else {
            $table->data[] = array('', get_string('noresponsedata', 'questionnaire'));
        }

        print_table($table);
    }

    function mkreslistdate($total, $precision, $showTotals) {
        global $CFG;
        $dateformat = get_string('strfdate', 'questionnaire');

        if($total == 0) {
            return;
        }
        $strresponse = get_string('response', 'questionnaire');
        $strnum = get_string('num', 'questionnaire');
        $table = new Object();
        $table->align = array('left', 'right');
        $table->head = array($strnum, $strresponse,'');
        $table->size = array('10%', '15%', '*');

        if (!empty($this->counts) && is_array($this->counts)) {
            ksort ($this->counts); // sort dates into chronological order
            while(list($text,$num) = each($this->counts)) {
				$text = userdate ( $text, $dateformat, '', false);    // change timestamp into readable dates
            	$table->data[] = array($num, $text);
            }
        } else {
            $table->data[] = array('', get_string('noresponsedata', 'questionnaire'));
        }

        print_table($table);
    }

    function mkreslistnumeric($total, $precision) {
        global $CFG;
        if($total == 0) {
            return;
        }
        $nbresponses = 0;
        $sum = 0;
        $strtotal = get_string('total', 'questionnaire');
        $strresponse = get_string('response', 'questionnaire');
        $strnum = get_string('num', 'questionnaire');
        $strnoresponsedata = get_string('noresponsedata', 'questionnaire');
        $straverage = get_string('average', 'questionnaire');
        $table = new Object();
        $table->align = array('left', 'right');
        $table->head = array($strnum, $strresponse,'');
        $table->size = array('10%', '15%','*');

        if (!empty($this->counts) && is_array($this->counts)) {
            ksort ($this->counts);
            while(list($text,$num) = each($this->counts)) {
                $table->data[] = array($num, $text);
                $nbresponses += $num;
                $sum += $text * $num;
            }
            $table->data[] = 'hr';
               $table->data[] = array($strtotal , $sum);
            $avg = $sum/$nbresponses;
               $table->data[] = array($straverage , sprintf('%.'.$precision.'f', $avg));
        } else {
            $table->data[] = array('', $strnoresponsedata);
        }

        print_table($table);
    }

    /* {{{ proto void mkresavg(array weights, int total, int precision, bool show_totals)
        Builds HTML showing AVG results. */
    function mkresavg($total, $precision, $showTotals, $length, $sort) {
        global $CFG;

        $stravg = '<div style="text-align:center">'.get_string('averagerank', 'questionnaire').'</div>';
        $isna = $this->precise == 1;
        $isnahead = '';
        $osgood = false;
        $nbchoices = count ($this->counts);
        if ($precision == 3) { // Osgood's semantic differential
            $osgood = true;
        }
        $isrestricted = ($length < $nbchoices) && $precision == 2;

        if ($isna) {
            $isnahead = get_string('notapplicable', 'questionnaire').'<br />(#)';
        }
        $table = new Object();

        $table->align = array('', 'left', 'right', 'center');
        if ($isna) {
            $table->head = array('', $stravg, '',$isnahead);
        }  else {
            if ($osgood) {
                $table->head = array('', $stravg, '', '');
            } else {
                $table->head = array('', $stravg, '');
            }
        }
        if (!$osgood) {
            $rightcolwidth = '5%';
        } else {
            $rightcolwidth = '25%';
        }
        $table->size = array('*', '40%', $rightcolwidth,'5%');

        $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
        $currhbar = $this->theme_bars_url();

        if (!$length) {
            $length = 5;
        }
        // add an extra column to accomodate lower ranks in this case
        $length += $isrestricted;
        $nacol = 0;
        $width = 100 / $length ;
        $n = array();
        $nameddegrees = 0;
        foreach ($this->choices as $choice) {
            // to take into account languages filter
            $content = (format_text($choice->content, FORMAT_HTML));
            if (ereg("^[0-9]{1,3}=", $content,$ndd)) {
                $n[$nameddegrees] = substr($content, strlen($ndd[0]));
                $nameddegrees++;
            }
        }
        $align = 'center';
        for ($j = 0; $j < $this->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j+1;
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
            $out .= '<td align = "center" style="width:'.$width.'%" >'.$str.'</td>';
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
            while(list($content) = each($this->counts)) {

                // eliminate potential named degrees on Likert scale
                 if (!ereg("^[0-9]{1,3}=", $content)) {
                    if (isset($this->counts[$content]->avg)) {
                        $avg = $this->counts[$content]->avg;
                    } else {
                        $avg = '';
                    }
                    $nbna = $this->counts[$content]->nbna;

                    if($avg) {
                        $out = '';
                        if (($j = $avg * $width) > 0) {
                            $interval = 50 / $length;
                            $out .= sprintf('<img alt="" src="'.$image_url.$currhbar.
                                'rhbar.gif" height="0" width="%d%%" style="visibility:hidden" />', $j - $interval - 0.3);
                        }
                        $out .= '<img alt="" src="'.$image_url.$currhbar.'rhbar.gif" height="12" width="6" />';
                    } else {
                            $out = '';
                    }

                    $contents = choice_values($content);
                    if ($contents->modname) {
                        $content = $contents->text;
                    }
                    if ($osgood) {
                        list($content, $contentright) = split('[|]', $content);
                    }
                    if (!$isna) {
                        if ($osgood) {
                            $table->data[] = array('<div align="right">'.format_text($content, FORMAT_HTML).'</div>', $out, '<div align="left"><strong>'.format_text($contentright, FORMAT_HTML).'</strong></div>', sprintf('%.1f', $avg));
                        } else {
                            if($avg) {
                            $table->data[] = array(format_text($content, FORMAT_HTML), $out, sprintf('%.1f', $avg));
                            } else {
                                $table->data[] = array(format_text($content, FORMAT_HTML), $out, get_string('notapplicable', 'questionnaire'));
                            }
                        }
                    } else {
                        if ($avg) {
                            $avg = sprintf('%.1f', $avg);
                        }
                        $table->data[] = array(format_text($content, FORMAT_HTML), $out, $avg, $nbna);
                    }
                } // end if named degrees
            } // end while
        } else {
            $table->data[] = array('', get_string('noresponsedata', 'questionnaire'));
        }
        print_table($table);
    }

    /* {{{ proto void mkresrank(array weights, int total, int precision, bool show_totals)
       Builds HTML showing RANK results. */
    function mkresrank($total, $precision, $showTotals) {
        global $CFG;

        $bg='';
        $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
    ?>
    <table border="0">
        <tr>
            <td align="right"><b><?php print_string('rank', 'questionnaire'); ?></b></td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    <?php
        arsort($this->counts);
        $i=0; $pt=0;
        while(list($content,$num) = each($this->counts)) {
            if($num)
                $p = $num/$total*100.0;
            else
                $p = 0;
            $pt += $p;

            if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
                $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
            else
                $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
    ?>
            <td><?php echo($content); ?></td>
            <td align="right" width="60"><?php if($p) printf("%.${precision}f%%",$p); ?></td>
            <td align="right" width="60">(<?php echo($num); ?>)</td>
        </tr>
    <?php
        } // end while
        if($showTotals) {
            if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
                $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
            else
                $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
    ?>
            <td colspan=2 align="left"><b><?php print_string('total', 'questionnaire'); ?></b></td>
            <td align="right"><b><?php printf("%.${precision}f%%",$pt); ?></b></td>
            <td align="right"><b><?php echo($total); ?></b></td>
        </tr>
    <?php } ?>
    </table>
    <?php
    }

    function mkcrossformat($pos, $qid, $tid) {
        global $ESPCONFIG;

        $cids = array();
        $cidCount = 0;

        // let's grab the cid values for each of the questions
        // that we allow cross analysis on.
        if ($tid == 1) {
            $cids = array('y', 'n');
        } else if ($records = get_records('questionnaire_quest_choice', 'question_id', $qid, 'id')) {
            foreach ($records as $record) {
                array_push($cids, $record->id);
            }
        }

        $bg = $ESPCONFIG['bgalt_color1'];
        $output = '';
        if ($pos >= count($cids)) {
            $pos = count($cids) - 1;
        }
        $output .= '<input type="checkbox" name="cids[]" value="'.$cids[$pos].'" />';
        return $output;
    }
}
function sortavgasc($a, $b) {
	if (isset($a->avg) && isset($b->avg)) {
		if ( $a->avg < $b->avg ) {
			return -1;
	    } elseif ( $a->avg > $b->avg ) {
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
	    } elseif ( $a->avg < $b->avg ) {
			return 1;
	    } else {
			return 0;
	    }
	}
}
?>
