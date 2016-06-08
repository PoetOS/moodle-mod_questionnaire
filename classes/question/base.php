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

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();
use \html_writer;

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

global $idcounter, $CFG;
$idcounter = 0;

require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

abstract class base {

    // Class Properties.
    /** @var int $id The database id of this question. */
    public $id          = 0;

    /** @var int $survey_id The database id of the survey this question belongs to. */
    // public $survey_id   = 0;  Commenting out for now, as '_' not allowed.

    /** @var string $name The name of this question. */
    public $name        = '';

    /** @var string $type The name of the question type. */
    public $type        = '';

    /** @var array $choices Array holding any choices for this question. */
    public $choices     = array();

    /** @var string $response_table The table name for responses. */
    public $responsetable = '';

    /** @var int $length The length field. */
    public $length      = 0;

    /** @var int $precise The precision field. */
    public $precise     = 0;

    /** @var int $position Position in the questionnaire */
    public $position    = 0;

    /** @var string $content The question's content. */
    public $content     = '';

    /** @var string $allchoices The list of all question's choices. */
    public $allchoices  = '';

    /** @var boolean $required The required flag. */
    public $required    = 'n';

    /** @var boolean $deleted The deleted flag. */
    public $deleted     = 'n';

    /** @var array $qtypenames List of all question names. */
    private static $qtypenames = array(
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

    // Class Methods.

    /**
     * The class constructor
     *
     */
    public function __construct($id = 0, $question = null, $context = null, $params = array()) {
        global $DB;
        static $qtypes = null;

        if ($qtypes === null) {
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

        foreach ($params as $property => $value) {
            $this->$property = $value;
        }

        if ($respclass = $this->responseclass()) {
            $this->response = new $respclass($this);
        }
    }

    /**
     * Short name for this question type - no spaces, etc..
     * @return string
     */
    abstract public function helpname();

    static public function question_builder($qtype, $params = null) {
        global $CFG;

        $qclassname = '\\mod_questionnaire\\question\\'.self::qtypename($qtype);
        if (!empty($params) && is_array($params)) {
            $params = (object)$params;
        }
        return new $qclassname(0, $params, null, array('type_id' => $qtype));
    }

    /**
     * Return the different question type names.
     * @return array
     */
    static public function qtypename($qtype) {
        if (array_key_exists($qtype, self::$qtypenames)) {
            return self::$qtypenames[$qtype];
        } else {
            return('');
        }
    }

    /**
     * Override and return true if the question has choices.
     */
    public function has_choices() {
        return false;
    }

    private function get_choices() {
        global $DB;

        if ($choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $this->id), 'id ASC')) {
            foreach ($choices as $choice) {
                $this->choices[$choice->id] = new \stdClass();
                $this->choices[$choice->id]->content = $choice->content;
                $this->choices[$choice->id]->value = $choice->value;
            }
        } else {
            $this->choices = array();
        }
    }

    /**
     * Insert response data method.
     */
    public function insert_response($rid, $val) {
        if (isset ($this->response) && is_object($this->response) &&
            is_subclass_of($this->response, '\\mod_questionnaire\\response\\base')) {
            return $this->response->insert_response($rid, $val);
        } else {
            return false;
        }
    }

    /**
     * Get results data method.
     */
    public function get_results($rids = false) {
        if (isset ($this->response) && is_object($this->response) &&
            is_subclass_of($this->response, '\\mod_questionnaire\\response\\base')) {
            return $this->response->get_results($rids);
        } else {
            return false;
        }
    }

    /**
     * Display results method.
     */
    public function display_results($rids=false, $sort='') {
        if (isset ($this->response) && is_object($this->response) &&
            is_subclass_of($this->response, '\\mod_questionnaire\\response\\base')) {
            return $this->response->display_results($rids, $sort);
        } else {
            return false;
        }
    }

    /**
     * Each question type must define its response class.
     *
     * @return object The response object based off of questionnaire_response_base.
     *
     */
    abstract protected function responseclass();

    /**
     * Question specific display method.
     *
     * @param object $formdata
     * @param string $descendantdata
     * @param integer $qnum
     * @param boolean $blankquestionnaire
     *
     */
    abstract protected function question_survey_display($formdata, $descendantsdata, $blankquestionnaire);

    /**
     * Question specific response display method.
     *
     * @param object $data
     * @param integer $qnum
     *
     */
    abstract protected function response_survey_display($data);

    /**
     * Check question's form data for complete response.
     *
     * @param object $responsedata The data entered into the response.
     * @return boolean
     */
    public function response_complete($responsedata) {
        return !(($this->required == 'y') && ($this->deleted == 'n') &&
                 (!isset($responsedata->{'q'.$this->id}) || $responsedata->{'q'.$this->id} == ''));
    }

    /**
     * Check question's form data for valid response. Override this is type has specific format requirements.
     *
     * @param object $responsedata The data entered into the response.
     * @return boolean
     */
    public function response_valid($responsedata) {
        return true;
    }

    /**
     * Update data record from object or optional question data.
     *
     * @param object $questionrecord An object with all updated question record data.
     * @param boolean $updatechoices True if choices should also be updated.
     */
    public function update($questionrecord = null, $updatechoices = true) {
        global $DB;

        if ($questionrecord === null) {
            $questionrecord = new \stdClass();
            $questionrecord->id = $this->id;
            $questionrecord->survey_id = $this->survey_id;
            $questionrecord->name = $this->name;
            $questionrecord->type_id = $this->type_id;
            $questionrecord->result_id = $this->result_id;
            $questionrecord->length = $this->length;
            $questionrecord->precise = $this->precise;
            $questionrecord->position = $this->position;
            $questionrecord->content = $this->content;
            $questionrecord->required = $this->required;
            $questionrecord->deleted = $this->deleted;
            $questionrecord->dependquestion = $this->dependquestion;
            $questionrecord->dependchoice = $this->dependchoice;
        } else {
            // Make sure the "id" field is this question's.
            if (isset($this->qid) && ($this->qid > 0)) {
                $questionrecord->id = $this->qid;
            } else {
                $questionrecord->id = $this->id;
            }
        }
        $DB->update_record('questionnaire_question', $questionrecord);

        if ($updatechoices && $this->has_choices()) {
            $this->update_choices();
        }
    }

    /**
     * Add the question to the database from supplied arguments.
     * @param object $questionrecord The required data for adding the question.
     * @param array $choicerecords An array of choice records with 'content' and 'value' properties.
     * @param boolean $calcposition Whether or not to calculate the next available position in the survey.
     */
    public function add($questionrecord, array $choicerecords = null, boolean $calcposition = null) {
        global $DB;

        // Default boolean parameter to "true".
        if ($calcposition === null) {
            $calcposition = true;
        }

        // Create new question.
        if ($calcposition) {
            // Set the position to the end.
            $sql = 'SELECT MAX(position) as maxpos '.
                   'FROM {questionnaire_question} '.
                   'WHERE survey_id = ? AND deleted = ?';
            $params = array('survey_id' => $questionrecord->survey_id, 'deleted' => 'n');
            if ($record = $DB->get_record_sql($sql, $params)) {
                $questionrecord->position = $record->maxpos + 1;
            } else {
                $questionrecord->position = 1;
            }
        }

        // Make sure we add all necessary data.
        if (!isset($questionrecord->type_id) || empty($questionrecord->type_id)) {
            $questionrecord->type_id = $this->type_id;
        }

        $this->qid = $DB->insert_record('questionnaire_question', $questionrecord);

        if ($this->has_choices() && !empty($choicerecords)) {
            foreach ($choicerecords as $choicerecord) {
                $choicerecord->question_id = $this->qid;
                $this->add_choice($choicerecord);
            }
        }
    }

    public function update_choices() {
        global $DB;

        $retvalue = true;
        if ($this->has_choices() && isset($this->choices)) {
            // Need to fix this messed-up qid/id issue.
            if (isset($this->qid) && ($this->qid > 0)) {
                $qid = $this->qid;
            } else {
                $qid = $this->id;
            }
            foreach ($this->choices as $key => $choice) {
                $choicrecord = new \stdClass();
                $choicerecord->id = $key;
                $choicerecord->question_id = $qid;
                $choicerecord->content = $choice->content;
                $choicerecord->value = $choice->value;
                $retvalue &= $this->update_choice($choicerecord);
            }
        }
        return $retvalue;
    }

    public function update_choice($choicerecord) {
        global $DB;
        return $DB->update_record('questionnaire_quest_choice', $choicerecord);
    }

    public function add_choice($choicerecord) {
        global $DB;
        $retvalue = true;
        if ($cid = $DB->insert_record('questionnaire_quest_choice', $choicerecord)) {
            $this->choices[$cid] = new \stdClass();
            $this->choices[$cid]->content = $choicerecord->content;
            $this->choices[$cid]->value = isset($choicerecord->value) ? $choicerecord->value : null;
        } else {
            $retvalue = false;
        }
        return $retvalue;
    }

    /**
     * Delete the choice from the question object and the database.
     *
     * @param integer|object $choice Either the integer id of the choice, or the choice record.
     */
    public function delete_choice($choice) {
        global $DB;
        $retvalue = true;
        if (is_int($choice)) {
            $cid = $choice;
        } else {
            $cid = $choice->id;
        }
        if ($DB->delete_records('questionnaire_quest_choice', array('id' => $cid))) {
            unset($this->choices[$cid]);
        } else {
            $retvalue = false;
        }
        return $retvalue;
    }

    /**
     * Set the question required field in the object and database.
     *
     * @param boolean $required Whether question should be required or not.
     */
    public function set_required($required) {
        global $DB;
        $rval = $required ? 'y' : 'n';
        // Need to fix this messed-up qid/id issue.
        if (isset($this->qid) && ($this->qid > 0)) {
            $qid = $this->qid;
        } else {
            $qid = $this->id;
        }
        $this->required = $rval;
        return $DB->set_field('questionnaire_question', 'required', $rval, array('id' => $qid));
    }
    /**
     * Main function for displaying a question.
     *
     * @param object $formdata
     * @param string $descendantdata
     * @param integer $qnum
     * @param boolean $blankquestionnaire
     *
     */
    private function question_display($formdata, $descendantsdata, $qnum='', $blankquestionnaire) {
        $this->questionstart_survey_display($qnum, $formdata, $descendantsdata);
        $this->question_survey_display($formdata, $descendantsdata, $blankquestionnaire);
        $this->questionend_survey_display($qnum);
    }

    public function survey_display($formdata, $descendantsdata, $qnum='', $blankquestionnaire=false) {
        $this->question_display($formdata, $descendantsdata, $qnum, $blankquestionnaire);
    }

    public function response_display($data, $qnum='') {
        $this->questionstart_survey_display($qnum, $data);
        $this->response_survey_display($data);
        $this->questionend_survey_display($qnum);
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

    private function response_check_required ($data) {
        // JR check all question types
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

    // This section contains functions for editing the specific question types.
    // There are required methods that must be implemented, and helper functions that can be used.

    // Required functions that can be overridden by the question type.

    /**
     * Override this, or any of the internal methods, to provide specific form data for editing the question type.
     * The structure of the elements here is the default layout for the question form.
     */
    public function edit_form(\MoodleQuickForm $mform, $questionnaire, $modcontext) {
        $this->form_header($mform);
        $this->form_name($mform);
        $this->form_required($mform);
        $this->form_length($mform);
        $this->form_precise($mform);
        $this->form_dependencies($mform, $questionnaire);
        $this->form_question_text($mform, $modcontext);

        if ($this->has_choices()) {
            $this->allchoices = $this->form_choices($mform, $this->choices);
        }

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'qid', 0);
        $mform->setType('qid', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'type_id', $this->type_id);
        $mform->setType('type_id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'question');
        $mform->setType('action', PARAM_ALPHA);

        // Buttons.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        if (isset($this->qid)) {
            $buttonarray[] = &$mform->createElement('submit', 'makecopy', get_string('saveasnew', 'questionnaire'));
        }
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

        return true;
    }

    protected function form_header(\MoodleQuickForm $mform, $helpname = '') {
        // Display different messages for new question creation and existing question modification.
        if (isset($this->qid) && !empty($this->qid)) {
            $header = get_string('editquestion', 'questionnaire', questionnaire_get_type($this->type_id));
        } else {
            $header = get_string('addnewquestion', 'questionnaire', questionnaire_get_type($this->type_id));
        }
        if (empty($helpname)) {
            $helpname = $this->helpname();
        }

        $mform->addElement('header', 'questionhdredit', $header);
        $mform->addHelpButton('questionhdredit', $helpname, 'questionnaire');
    }

    protected function form_name(\MoodleQuickForm $mform) {
        $mform->addElement('text', 'name', get_string('optionalname', 'questionnaire'),
                        array('size' => '30', 'maxlength' => '30'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'optionalname', 'questionnaire');
        return $mform;
    }

    protected function form_required(\MoodleQuickForm $mform) {
        $reqgroup = array();
        $reqgroup[] =& $mform->createElement('radio', 'required', '', get_string('yes'), 'y');
        $reqgroup[] =& $mform->createElement('radio', 'required', '', get_string('no'), 'n');
        $mform->addGroup($reqgroup, 'reqgroup', get_string('required', 'questionnaire'), ' ', false);
        $mform->addHelpButton('reqgroup', 'required', 'questionnaire');
        return $mform;
    }

    protected function form_length(\MoodleQuickForm $mform, $helpname = '') {
        self::form_length_text($mform, $helpname);
    }

    protected function form_precise(\MoodleQuickForm $mform, $helpname = '') {
        self::form_precise_text($mform, $helpname);
    }

    protected function form_dependencies(\MoodleQuickForm $mform, $questionnaire) {
        // Dependence fields.

        if ($questionnaire->navigate) {
            $position = isset($this->position) ? $this->position : count($questionnaire->questions) + 1;
            $dependencies = questionnaire_get_dependencies($questionnaire->questions, $position);
            $canchangeparent = true;
            if (count($dependencies) > 1) {
                if (isset($this->qid)) {
                    $haschildren = questionnaire_get_descendants ($questionnaire->questions, $this->qid);
                    if (count($haschildren) !== 0) {
                        $canchangeparent = false;
                        $parent = questionnaire_get_parent ($this);
                        $fixeddependency = $parent [$this->id]['parent'];
                    }
                }
                if ($canchangeparent) {
                    $this->dependquestion = isset($this->dependquestion) ? $this->dependquestion.','.
                                    $this->dependchoice : '0,0';
                    $group = array($mform->createElement('selectgroups', 'dependquestion', '', $dependencies) );
                    $mform->addGroup($group, 'selectdependency', get_string('dependquestion', 'questionnaire'), '', false);
                    $mform->addHelpButton('selectdependency', 'dependquestion', 'questionnaire');
                } else {
                    $mform->addElement('static', 'selectdependency', get_string('dependquestion', 'questionnaire'),
                                    '<div class="dimmed_text">'.$fixeddependency.'</div>');
                }
                $mform->addHelpButton('selectdependency', 'dependquestion', 'questionnaire');
            }
        }
    }

    protected function form_question_text(\MoodleQuickForm $mform, $context) {
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true, 'context' => $context);
        $mform->addElement('editor', 'content', get_string('text', 'questionnaire'), null, $editoroptions);
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', null, 'required', null, 'client');
        return $mform;
    }

    protected function form_choices(\MoodleQuickForm $mform, array $choices, $helpname = '') {
        $numchoices = count($choices);
        $allchoices = '';
        foreach ($choices as $choice) {
            if (!empty($allchoices)) {
                $allchoices .= "\n";
            }
            $allchoices .= $choice->content;
        }
        if (empty($helpname)) {
            $helpname = $this->helpname();
        }

        $mform->addElement('html', '<div class="qoptcontainer">');
        $options = array('wrap' => 'virtual', 'class' => 'qopts');
        $mform->addElement('textarea', 'allchoices', get_string('possibleanswers', 'questionnaire'), $options);
        $mform->setType('allchoices', PARAM_RAW);
        $mform->addRule('allchoices', null, 'required', null, 'client');
        $mform->addHelpButton('allchoices', $helpname, 'questionnaire');
        $mform->addElement('html', '</div>');
        $mform->addElement('hidden', 'num_choices', $numchoices);
        $mform->setType('num_choices', PARAM_INT);
        return $allchoices;
    }

    // Helper functions for commonly used editing functions.

    static public function form_length_hidden(\MoodleQuickForm $mform, $value = 0) {
        $mform->addElement('hidden', 'length', $value);
        $mform->setType('length', PARAM_INT);
        return $mform;
    }

    static public function form_length_text(\MoodleQuickForm $mform, $helpname = '', $value = 0) {
        $mform->addElement('text', 'length', get_string($helpname, 'questionnaire'), array('size' => '1'), $value);
        $mform->setType('length', PARAM_INT);
        if (!empty($helpname)) {
            $mform->addHelpButton('length', $helpname, 'questionnaire');
        }
        return $mform;
    }

    static public function form_precise_hidden(\MoodleQuickForm $mform, $value = 0) {
        $mform->addElement('hidden', 'precise', $value);
        $mform->setType('precise', PARAM_INT);
        return $mform;
    }

    static public function form_precise_text(\MoodleQuickForm $mform, $helpname = '', $value = 0) {
        $mform->addElement('text', 'precise', get_string($helpname, 'questionnaire'), array('size' => '1'));
        $mform->setType('precise', PARAM_INT);
        if (!empty($helpname)) {
            $mform->addHelpButton('precise', $helpname, 'questionnaire');
        }
        return $mform;
    }

    /**
     * Create and update question data from the forms.
     */
    public function form_update($formdata, $questionnaire) {
        global $DB;

        $this->form_preprocess_data($formdata);

        if (!empty($formdata->qid)) {

            // Update existing question.
            // Handle any attachments in the content.
            $formdata->itemid  = $formdata->content['itemid'];
            $formdata->format  = $formdata->content['format'];
            $formdata->content = $formdata->content['text'];
            $formdata->content = file_save_draft_area_files($formdata->itemid, $questionnaire->context->id, 'mod_questionnaire',
                'question', $formdata->qid, array('subdirs' => true), $formdata->content);

            $fields = array('name', 'type_id', 'length', 'precise', 'required', 'content', 'dependquestion', 'dependchoice');
            $questionrecord = new \stdClass();
            $questionrecord->id = $formdata->qid;
            foreach ($fields as $f) {
                if (isset($formdata->$f)) {
                    $questionrecord->$f = trim($formdata->$f);
                }
            }
            $result = $this->update($questionrecord, false);

            if (questionnaire_has_dependencies($questionnaire->questions)) {
                questionnaire_check_page_breaks($questionnaire);
            }
        } else {
            // Create new question:
            // Need to update any image content after the question is created, so create then update the content.
            $formdata->survey_id = $formdata->sid;
            $fields = array('survey_id', 'name', 'type_id', 'length', 'precise', 'required', 'position',
                            'dependquestion', 'dependchoice');
            $questionrecord = new \stdClass();
            foreach ($fields as $f) {
                if (isset($formdata->$f)) {
                    $questionrecord->$f = trim($formdata->$f);
                }
            }
            $questionrecord->content = '';

            $this->add($questionrecord);

            // Handle any attachments in the content.
            $formdata->itemid  = $formdata->content['itemid'];
            $formdata->format  = $formdata->content['format'];
            $formdata->content = $formdata->content['text'];
            $content           = file_save_draft_area_files($formdata->itemid, $questionnaire->context->id, 'mod_questionnaire',
                'question', $this->qid, array('subdirs' => true), $formdata->content);
            $result = $DB->set_field('questionnaire_question', 'content', $content, array('id' => $this->qid));
        }

        if ($this->has_choices()) {
            // Now handle any choice updates.
            $cidx = 0;
            if (isset($this->choices) && !isset($formdata->makecopy)) {
                $oldcount = count($this->choices);
                $echoice = reset($this->choices);
                $ekey = key($this->choices);
            } else {
                $oldcount = 0;
            }

            $newchoices = explode("\n", $formdata->allchoices);
            $nidx = 0;
            $newcount = count($newchoices);

            while (($nidx < $newcount) && ($cidx < $oldcount)) {
                if ($newchoices[$nidx] != $echoice->content) {
                    $choicerecord = new \stdClass();
                    $choicerecord->id = $ekey;
                    $choicerecord->question_id = $this->qid;
                    $choicerecord->content = trim($newchoices[$nidx]);
                    $r = preg_match_all("/^(\d{1,2})(=.*)$/", $newchoices[$nidx], $matches);
                    // This choice has been attributed a "score value" OR this is a rate question type.
                    if ($r) {
                        $newscore = $matches[1][0];
                        $choicerecord->value = $newscore;
                    } else {     // No score value for this choice.
                        $choicerecord->value = null;
                    }
                    $this->update_choice($choicerecord);
                }
                $nidx++;
                $echoice = next($this->choices);
                $ekey = key($this->choices);
                $cidx++;
            }

            while ($nidx < $newcount) {
                // New choices...
                $choicerecord = new \stdClass();
                $choicerecord->question_id = $this->qid;
                $choicerecord->content = trim($newchoices[$nidx]);
                $r = preg_match_all("/^(\d{1,2})(=.*)$/", $choicerecord->content, $matches);
                // This choice has been attributed a "score value" OR this is a rate question type.
                if ($r) {
                    $choicerecord->value = $matches[1][0];
                }
                $this->add_choice($choicerecord);
                $nidx++;
            }

            while ($cidx < $oldcount) {
                end($this->choices);
                $ekey = key($this->choices);
                $this->delete_choice($ekey);
                $cidx++;
            }
        }
    }

    /**
     * Any preprocessing of general data.
     */
    protected function form_preprocess_data($formdata) {
        if ($this->has_choices()) {
            // Eliminate trailing blank lines.
            $formdata->allchoices = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $formdata->allchoices);
            // Trim to eliminate potential trailing carriage return.
            $formdata->allchoices = trim($formdata->allchoices);
            $this->form_preprocess_choicedata($formdata);
        }
        $dependency = array();
        if (isset($formdata->dependquestion) && $formdata->dependquestion != 0) {
            $dependency = explode(",", $formdata->dependquestion);
            $formdata->dependquestion = $dependency[0];
            $formdata->dependchoice = $dependency[1];
        }
        return true;
    }

    /**
     * Override this function for question specific choice preprocessing.
     */
    protected function form_preprocess_choicedata($formdata) {
        if (empty($formdata->allchoices)) {
            error (get_string('enterpossibleanswers', 'questionnaire'));
        }
        return false;
    }

    /**
     * Return all the fields to be used for users in questionnaire sql.
     *
     * @author: Guy Thomas
     * @return string
     */
    protected function user_fields_sql() {
        $userfieldsarr = get_all_user_name_fields();
        $userfieldsarr = array_merge($userfieldsarr, ['username', 'department', 'institution']);
        $userfields = '';
        foreach ($userfieldsarr as $field) {
            $userfields .= $userfields === '' ? '' : ', ';
            $userfields .= 'u.'.$field;
        }
        $userfields .= ', u.id as userid';
        return $userfields;
    }
}