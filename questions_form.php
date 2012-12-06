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
* print the form to add or edit a questionnaire-instance
*
* @author Mike Churchward
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package questionnaire
*/

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

class questionnaire_questions_form extends moodleform {

    function __construct($action, $moveq=false) {
        $this->moveq = $moveq;
        return parent::moodleform($action);
    }

    function definition() {
        global $CFG, $questionnaire, $SESSION;
        global $DB;

        $mform    =& $this->_form;

        $mform->addElement('html', '<div class="qcontainer">');
        $mform->addElement('header', 'questionhdr', get_string('questions', 'questionnaire'));
        $mform->addHelpButton('questionhdr', 'questiontypes', 'questionnaire');

        $stredit = get_string('edit', 'questionnaire');
        $strremove = get_string('remove', 'questionnaire');
        $stryes = get_string('yes');
        $strno = get_string('no');

        /// Set up question positions.
        if (!isset($questionnaire->questions)) {
            $questionnaire->questions = array();
        }
        $quespos = array();
        $max = count($questionnaire->questions);
        $sec = 0;
        for ( $i = 1; $i <= $max; $i++) {
            $quespos[$i] = "$i";
        }

        $pos = 0;
        $numq = count($questionnaire->questions);
        $attributes = 'onChange="this.form.submit()"';

        $select = '';
        if (!($qtypes = $DB->get_records_select_menu('questionnaire_question_type', $select, null, '', 'typeid,type'))) {
            $qtypes = array();
        }
        // needed for non-English languages JR
        foreach ($qtypes as $key => $qtype) {
            $qtypes[$key] = questionnaire_get_type($key);
        }
        natsort($qtypes);
        $addqgroup = array();
        $addqgroup[] =& $mform->createElement('select', 'type_id', '', $qtypes);

        // 'sticky' type_id value for further new questions
        if (isset($SESSION->questionnaire->type_id)) {
                $mform->setDefault('type_id', $SESSION->questionnaire->type_id);
        }

        $addqgroup[] =& $mform->createElement('submit', 'addqbutton', get_string('addselqtype', 'questionnaire'));
        if (questionnaire_has_dependencies($questionnaire->questions)) {
            $addqgroup[] =& $mform->createElement('submit', 'validate', get_string('validate', 'questionnaire'));
        }
        $mform->addGroup($addqgroup, 'addqgroup', '', '', false);
        if (isset($SESSION->questionnaire->validateresults) && $SESSION->questionnaire->validateresults != '') {
            $mform->addElement('static', 'validateresult', '', '<div class="qdepend warning">'.$SESSION->questionnaire->validateresults.'</div>');
        }
        
        $mform->addElement('html', '<div class="qheader">');

        $quesgroup = array();
        $quesgroup[] =& $mform->createElement('static', 'qnums', '', '<div class="qnums">'.
                                              get_string('questionnum', 'questionnaire').'</div>');
        $quesgroup[] =& $mform->createElement('static', 'opentagt', '', '<div class="qicons">'.
                                              get_string('action', 'questionnaire').'</div>');
        $quesgroup[] =& $mform->createElement('static', 'qnamet', '', '<div class="qtype">'.
                                              get_string('questiontypes', 'questionnaire').'</div>');
        $quesgroup[] =& $mform->createElement('static', 'qreqt', '', '<div class="qreq">'.
                                              get_string('required').'</div>');
        $quesgroup[] =& $mform->createElement('static', 'qtypet', '', '<div class="qname">'.
                                              get_string('optionalname', 'questionnaire').'</div>');
        $mform->addGroup($quesgroup, 'questgroupt', '', '', false);

        $mform->addElement('html', '</div>');

        $qnum = 0;

        // JR skip logic :: to prevent moving child higher than parent OR parent lower than child
        // we must get now the parent and child positions
        $questionnairehasdependencies = questionnaire_has_dependencies($questionnaire->questions);
        if ($questionnairehasdependencies) {
            $parentpositions = questionnaire_get_parent_positions ($questionnaire->questions);
            $childpositions = questionnaire_get_child_positions ($questionnaire->questions);
        }

        foreach ($questionnaire->questions as $question) {
            //$required = '';
            $qid = $question->id;
            $tid = $question->type_id;
            $qtype = $question->type;
            $required = $question->required;

            // does this questionnaire contain branching questions already?
            $dependency = '';
            if ($questionnairehasdependencies) {
                if ($question->dependquestion != 0) {
                    $parent = questionnaire_get_parent ($question);
                    $dependency = '<strong>'.get_string('dependquestion', 'questionnaire').'</strong> : '.$parent[$qid]['parent'];
                }
            }
                        
            $pos = $question->position;
            $qnum++;
            $qnum_txt = $qnum;
            
            // needed for non-English languages JR
            $qtype = '['.questionnaire_get_type($tid).']';
            $content = '';
            if($tid == QUESPAGEBREAK) {
                $sec++;
            } else {
            // to take into account languages filter
                $content = (format_text($question->content, FORMAT_HTML));
            }

            $quesgroup = 'quesgroup_'.$pos;

            $butclass = array('class' => 'questionnaire_qbut');

            if (!$this->moveq) {
                $mextra = array('value' => $question->id,
                                'alt' => get_string('move', 'questionnaire'),
                                'title' => get_string('move', 'questionnaire')) + $butclass;
                $eextra = array('value' => $question->id,
                                'alt' => get_string('edit', 'questionnaire'),
                                'title' => get_string('edit', 'questionnaire')) + $butclass;
                $rextra = array('value' => $question->id,
                                'alt' => get_string('remove', 'questionnaire'),
                                'title' => get_string('remove', 'questionnaire')) + $butclass;
                if ($question->type_id == QUESPAGEBREAK) {
                    $esrc = $CFG->wwwroot.'/mod/questionnaire/images/editd.gif';
                    $eextra += array('disabled' => 'disabled');
                } else {
                    $esrc = $CFG->wwwroot.'/mod/questionnaire/images/edit.gif';
                }
                $rsrc = $CFG->wwwroot.'/mod/questionnaire/images/delete.gif';

            //Question numbers
                ${$quesgroup}[] =& $mform->createElement('static', 'qnums', '', '<div class="qnums">'.$qnum_txt.'</div>');

            /// Need to index by 'id' since IE doesn't return assigned 'values' for image inputs.
                ${$quesgroup}[] =& $mform->createElement('static', 'opentag_'.$question->id, '', '<div class="qicons">');
                $msrc = $CFG->wwwroot.'/mod/questionnaire/images/move.gif';
                // do not allow moving parent question at position #1 to be moved down if it has a child at position < 4
                if ($questionnairehasdependencies) {
                    if ($pos == 1) {
                        if (isset($childpositions[$qid])) {
                            $maxdown = $childpositions[$qid];
                            if ($maxdown < 4) {
                                // we should use a disabled move icon here (to be created)
                                $msrc = $CFG->wwwroot.'/mod/questionnaire/images/downd.gif';
                                $mextra = array('value' => $question->id,
                                                'alt' => get_string('disabled', 'questionnaire'),
                                                'title' => get_string('disabled', 'questionnaire')) + $butclass;
                                $mextra += array('disabled' => 'disabled');
                            }
                        }
                    }
                }
                ${$quesgroup}[] =& $mform->createElement('image', 'movebutton['.$question->id.']',
                                    $msrc, $mextra);
                ${$quesgroup}[] =& $mform->createElement('image', 'editbutton['.$question->id.']', $esrc, $eextra);
                ${$quesgroup}[] =& $mform->createElement('image', 'removebutton['.$question->id.']', $rsrc, $rextra);
                ${$quesgroup}[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '</div>');
            } else {
                ${$quesgroup}[] =& $mform->createElement('static', 'qnum', '', '<div class="qnums">'.$qnum_txt.'</div>');

                $display = true;
                if ($questionnairehasdependencies) {
                    // prevent moving child to higher position than its parent
                    if (isset($parentpositions[$this->moveq])) {
                        $maxup = $parentpositions[$this->moveq];
                        if ($pos <= $maxup) {
                            $display = false;
                        }
                    }
                    // prevent moving parent to lower position than its (first) child
                    if (isset($childpositions[$this->moveq])) {
                        $maxdown = $childpositions[$this->moveq];
                        if ($pos >= $maxdown) {
                            $display = false;
                        }
                    }
                }
                if ($this->moveq != $question->id && $display) {
                    $mextra = array('value' => $question->id,
                                    'alt' => get_string('movehere', 'questionnaire'),
                                    'title' => get_string('movehere', 'questionnaire')) + $butclass;
                    $msrc = $CFG->wwwroot.'/mod/questionnaire/images/movehere.gif';
                    ${$quesgroup}[] =& $mform->createElement('static', 'opentag_'.$question->id, '', '<div class="qicons">');
                    $newposition = $max == $pos ? 0 : $pos;
                    ${$quesgroup}[] =& $mform->createElement('image', 'moveherebutton['.$newposition.']', $msrc, $mextra);
                    ${$quesgroup}[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '</div>');
                }
                elseif ($display) {
                    ${$quesgroup}[] =& $mform->createElement('static', 'qnums', '', '<div class="qicons">Move From Here</div>');
                } else {
                    ${$quesgroup}[] =& $mform->createElement('static', 'qnums', '', '<div class="qicons">---</div>');
                }
            }

            ${$quesgroup}[] =& $mform->createElement('static', 'qtype_'.$question->id, '', '<div class="qtype">'.$qtype.'</div>');

            $qreq = '';
            if ($question->type_id != QUESPAGEBREAK && $question->type_id != QUESSECTIONTEXT) {
                if ($required == 'y') {
                    $qreq = $stryes;
                } else {
                    $qreq = $strno;
                }
            }
            ${$quesgroup}[] =& $mform->createElement('static', 'qreq_'.$question->id, '', '<div class="qreq">'.$qreq.'</div>');

            $qname = $question->name;
            ${$quesgroup}[] =& $mform->createElement('static', 'qname_'.$question->id, '', '<div class="qname">'.$qname.'</div>');

            $mform->addGroup($$quesgroup, 'questgroup', '', '', false);
            if ($dependency) {
                $mform->addElement('static', 'qdepend_'.$question->id, '', '<div class="qdepend">'.$dependency.'</div>');
            }

            $mform->addElement('static', 'qcontent_'.$question->id, '', '<div class="qcontent">'.$content.'</div>');
            $pos++;
        }

        // If we are moving a question, display one more line for the end.
        if ($this->moveq) {
            $mform->addElement('hidden', 'moveq', $this->moveq);
        }

        //-------------------------------------------------------------------------------
        // Hidden fields
        $mform->addElement('hidden', 'id', 0);
        $mform->addElement('hidden', 'sid', 0);
        $mform->addElement('hidden', 'action', 'main');

        //-------------------------------------------------------------------------------
        // buttons

        $mform->addElement('html', '</div>');

    }

    function validation($data, $files){
        return parent::validation($data, $files);
    }

}

class questionnaire_edit_question_form extends moodleform {

    function definition() {
        global $CFG, $COURSE, $questionnaire, $question, $QUESTIONNAIRE_REALMS, $SESSION;
        global $DB;

        // 'sticky' required response value for further new questions
        if (isset($SESSION->questionnaire->required) && !isset($question->qid)) {
            $question->required = $SESSION->questionnaire->required;
        }
        if (!isset($question->type_id)) {
            print_error('undefinedquestiontype', 'questionnaire');
        }

        /// Initialize question type defaults:
        switch ($question->type_id) {
        case QUESTEXT:
            $deflength = 20;
            $defprecise = 25;
            $lhelpname = 'fieldlength';
            $phelpname = 'maxtextlength';
            break;
        case QUESESSAY:
            $deflength = '';
            $defprecise = '';
            $lhelpname = 'textareacolumns';
            $phelpname = 'textarearows';
            break;
        case QUESCHECK:
            $deflength = 0;
            $defprecise = 0;
            $lhelpname = 'minforcedresponses';
            $phelpname = 'maxforcedresponses';
            $olabelname = 'possibleanswers';
            $ohelpname = 'checkboxes';
            break;
        case QUESRADIO:
            $deflength = 0;
            $defprecise = 0;
            $lhelpname = 'alignment';
            $olabelname = 'possibleanswers';
            $ohelpname = 'radiobuttons';
            break;
        case QUESRATE:
            $deflength = 5;
            $defprecise = 0;
            $lhelpname = 'numberscaleitems';
            $phelpname = 'kindofratescale';
            $olabelname = 'possibleanswers';
            $ohelpname = 'ratescale';
            break;
        case QUESNUMERIC:
            $deflength = 10;
            $defprecise = 0;
            $lhelpname = 'maxdigitsallowed';
            $phelpname = 'numberofdecimaldigits';
            break;
        case QUESDROP:
            $deflength = 0;
            $defprecise = 0;
            $olabelname = 'possibleanswers';
            $ohelpname = 'dropdown';
            break;
        default:
            $deflength = 0;
            $defprecise = 0;
        }

        $defdependquestion = 0;
        $defdependchoice = 0;
        $dlabelname = 'dependquestion';

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        // display different messages for new question creation and existing question modification
        if (isset($question->qid)) {
            $streditquestion = get_string('editquestion', 'questionnaire', questionnaire_get_type($question->type_id));
        } else {
            $streditquestion = get_string('addnewquestion', 'questionnaire', questionnaire_get_type($question->type_id));
        }
		switch ($question->type_id) {
		    case 1:
		        $qtype='yesno';
		        break;
		    case 2:
		        $qtype='textbox';
                break;
	        case 3:
		        $qtype='essaybox';
                break;
		    case 4:
		        $qtype='radiobuttons';
		        break;
            case 5:
		        $qtype='checkboxes';
                break;
		    case 6:
		        $qtype='dropdown';
                break;
		    case 8:
		        $qtype='ratescale';
                break;
		    case 9:
		        $qtype='date';
		        break;
		    case 10:
		        $qtype='numeric';
		        break;
		    case 100:
		        $qtype='sectiontext';
		        break;
            case 99:
		        $qtype='sectionbreak';
		    }

        $mform->addElement('header', 'questionhdr', $streditquestion);
        $mform->addHelpButton('questionhdr', $qtype, 'questionnaire');

        /// Name and required fields:
        if ($question->type_id != QUESSECTIONTEXT && $question->type_id != '') {
            $stryes = get_string('yes');
            $strno  = get_string('no');

            $mform->addElement('text', 'name', get_string('optionalname', 'questionnaire'), array('size'=>'30', 'maxlength'=>'30'));
            $mform->setType('name', PARAM_TEXT);
            $mform->addHelpButton('name', 'optionalname', 'questionnaire');

            $reqgroup = array();
            $reqgroup[] =& $mform->createElement('radio', 'required', '', $stryes, 'y');
            $reqgroup[] =& $mform->createElement('radio', 'required', '', $strno, 'n');
            $mform->addGroup($reqgroup, 'reqgroup', get_string('required', 'questionnaire'), ' ', false);
            $mform->addHelpButton('reqgroup', 'required', 'questionnaire');
        }

        /// Length field:
        if ($question->type_id == QUESYESNO || $question->type_id == QUESDROP || $question->type_id == QUESDATE ||
            $question->type_id == QUESSECTIONTEXT) {
            $mform->addElement('hidden', 'length', $deflength);
        } else if ($question->type_id == QUESRADIO) {
            $lengroup = array();
            $lengroup[] =& $mform->createElement('radio', 'length', '', get_string('vertical', 'questionnaire'), '0');
            $lengroup[] =& $mform->createElement('radio', 'length', '', get_string('horizontal','questionnaire'), '1');
            $mform->addGroup($lengroup, 'lengroup', get_string($lhelpname, 'questionnaire'), ' ', false);
            $mform->addHelpButton('lengroup', $lhelpname, 'questionnaire');
        } else { // QUESTEXT or QUESESSAY or QUESRATE
            $question->length = isset($question->length) ? $question->length : $deflength;
            $mform->addElement('text', 'length', get_string($lhelpname, 'questionnaire'), array('size'=>'1'));
            $mform->addHelpButton('length', $lhelpname, 'questionnaire');
        }

        /// Precision field:
        if ($question->type_id == QUESYESNO || $question->type_id == QUESDROP || $question->type_id == QUESDATE ||
            $question->type_id == QUESSECTIONTEXT || $question->type_id == QUESRADIO) {
            $mform->addElement('hidden', 'precise', $defprecise);
        } else if ($question->type_id == QUESRATE) {
            $precoptions = array("0" => get_string('normal','questionnaire'),
                                 "1" => get_string('notapplicablecolumn','questionnaire'),
                                 "2" => get_string('noduplicates','questionnaire'),
                                 "3" => get_string('osgood','questionnaire'));
            $mform->addElement('select', 'precise', get_string($phelpname, 'questionnaire'), $precoptions);
            $mform->addHelpButton('precise', $phelpname, 'questionnaire');
        } else {
            $question->precise = isset($question->precise) ? $question->precise : $defprecise;
            $mform->addElement('text', 'precise', get_string($phelpname, 'questionnaire'), array('size'=>'1'));
        }

        /// Dependence fields:
        $position = isset($question->position) ? $question->position : count($questionnaire->questions) + 1;  
        $dependencies = questionnaire_get_dependencies($questionnaire->questions, $position);
        if (count($dependencies) > 1) {
            $question->dependquestion = isset($question->dependquestion) ? $question->dependquestion.','.$question->dependchoice : '0,0';
            $group = array($mform->createElement('selectgroups', 'dependquestion', '', $dependencies) );
            $mform->addGroup($group, 'selectdependency', get_string('dependquestion', 'questionnaire'), '', false);
            $mform->addHelpButton('selectdependency', 'dependquestion', 'questionnaire');
        }
            
        /// Content field:
        $modcontext    = $this->_customdata['modcontext'];
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$modcontext);
        $mform->addElement('editor', 'content', get_string('text', 'questionnaire'), null, $editoroptions);
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', null, 'required', null, 'client');

        /// Options section:
        // has answer options ... so show that part of the form
        if ($DB->get_field('questionnaire_question_type', 'has_choices', array('typeid' => $question->type_id)) == 'y' ) {
            if (!empty($question->choices)) {
                $num_choices = count($question->choices);
            } else {
                $num_choices = 0;
            }

            if (!empty($question->choices)) {
                foreach ($question->choices as $choiceid => $choice) {
                    if (!empty($question->allchoices)) {
                        $question->allchoices .= "\n";
                    }
                    $question->allchoices .= $choice->content;
                }
            } else {
                $question->allchoices = '';
            }

            $mform->addElement('html', '<div class="qoptcontainer">');

            $options = array('wrap' => 'virtual', 'class' => 'qopts');
            $mform->addElement('textarea', 'allchoices', get_string('possibleanswers', 'questionnaire'), $options);
            $mform->setType('allchoices', PARAM_RAW);
            $mform->addRule('allchoices', null, 'required', null, 'client');
            $mform->addHelpButton('allchoices', $ohelpname, 'questionnaire');

            $mform->addElement('html', '</div>');

            $mform->addElement('hidden', 'num_choices', $num_choices);
        }

        //-------------------------------------------------------------------------------
        // Hidden fields
        $mform->addElement('hidden', 'id', 0);
        $mform->addElement('hidden', 'qid', 0);
        $mform->addElement('hidden', 'sid', 0);
        $mform->addElement('hidden', 'type_id', $question->type_id);
        $mform->addElement('hidden', 'action', 'question');

        //-------------------------------------------------------------------------------
        // buttons

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        if (isset($question->qid)) {
            $buttonarray[] = &$mform->createElement('submit', 'makecopy', get_string('saveasnew', 'questionnaire'));
        }
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }

    function validation($data, $files){
        return parent::validation($data, $files);
    }

}
