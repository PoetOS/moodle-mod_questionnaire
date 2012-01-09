<?php // $Id$
/**
* print the form to add or edit a questionnaire-instance
*
* @version $Id$
* @author Mike Churchward
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package questionnaire
*/

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

class questionnaire_questions_form extends moodleform {

    function questionnaire_questions_form($action, $moveq=false) {
        $this->moveq = $moveq;
        return parent::moodleform($action);
    }

    function definition() {
        global $CFG, $COURSE, $questionnaire, $QUESTIONNAIRE_REALMS, $SESSION;

        $mform    =& $this->_form;

        $mform->addElement('html', '<div class="qcontainer">');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'questionhdr', get_string('questions', 'questionnaire'));
        $mform->setHelpButton('questionhdr', array('questiontypes', get_string('questiontypes', 'questionnaire'), 'questionnaire'));

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
        if (!($qtypes = get_records_select_menu('questionnaire_question_type', $select, '', 'typeid,type'))) {
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
        $mform->addGroup($addqgroup, 'addqgroup', '', '', false);

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
        foreach ($questionnaire->questions as $question) {

            /// Skip displaying this question if we are moving this question.
            //if ($this->moveq && ($this->moveq == $question->id)) {
            //    continue;
            //}

            $required = '';
            $qid = $question->id;
            $tid = $question->type_id;
            $qtype = $question->type;
            $required = $question->required;
            $pos = $question->position;
            $qnum_txt = '&nbsp;';
            if ($tid<99) {
                $qnum++;
                $qnum_txt = $qnum;
            }
            // needed for non-English languages JR
            $qtype = '['.questionnaire_get_type($tid).']';
            $content = '';
            if($tid == 99) {
                $sec++;
                $content .= '<b>'.get_string('sectionbreak', 'questionnaire').'</b>';
            } else {
            // to take into account languages filter
                $content = (format_text($question->content, FORMAT_HTML));
            }

            $quesgroup = 'quesgroup_'.$pos;
            $$quesgroup = array();
            $butclass = array('class' => 'questionnaire_qbut');

            if (!$this->moveq) {

                $uextra = array('value' => $question->id,
                                'alt' => get_string('moveup', 'questionnaire'),
                                'title' => get_string('moveup', 'questionnaire')) + $butclass;
                $dextra = array('value' => $question->id,
                                'alt' => get_string('movedn', 'questionnaire'),
                                'title' => get_string('movedn', 'questionnaire')) + $butclass;
                $mextra = array('value' => $question->id,
                                'alt' => get_string('move', 'questionnaire'),
                                'title' => get_string('move', 'questionnaire')) + $butclass;
                $eextra = array('value' => $question->id,
                                'alt' => get_string('edit', 'questionnaire'),
                                'title' => get_string('edit', 'questionnaire')) + $butclass;
                $rextra = array('value' => $question->id,
                                'alt' => get_string('remove', 'questionnaire'),
                                'title' => get_string('remove', 'questionnaire')) + $butclass;
                if ($pos == 1) {
                    $usrc = $CFG->wwwroot.'/mod/questionnaire/images/upd.gif';
                    $uextra += array('disabled' => 'disabled');
                } else {
                    $usrc = $CFG->wwwroot.'/mod/questionnaire/images/up.gif';
                }
                if ($pos == ($numq)) {
                    $dsrc = $CFG->wwwroot.'/mod/questionnaire/images/downd.gif';
                    $dextra += array('disabled' => 'disabled');
                } else {
                    $dsrc = $CFG->wwwroot.'/mod/questionnaire/images/down.gif';
                }
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
                ${$quesgroup}[] =& $mform->createElement('image', 'moveupbutton['.$question->id.']', $usrc, $uextra);
                ${$quesgroup}[] =& $mform->createElement('image', 'movednbutton['.$question->id.']', $dsrc, $dextra);
                ${$quesgroup}[] =& $mform->createElement('image', 'movebutton['.$question->id.']',
                                   $CFG->wwwroot.'/mod/questionnaire/images/move.gif', $mextra);
                ${$quesgroup}[] =& $mform->createElement('image', 'editbutton['.$question->id.']', $esrc, $eextra);
                ${$quesgroup}[] =& $mform->createElement('image', 'removebutton['.$question->id.']', $rsrc, $rextra);
                ${$quesgroup}[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '</div>');
            } else {
            	${$quesgroup}[] =& $mform->createElement('static', 'qnum', '', '<div class="qnums">'.$qnum_txt.'</div>');
            	if ($this->moveq != $question->id) {
            	                    $mextra = array('value' => $question->id,
                                'alt' => get_string('movehere', 'questionnaire'),
                                'title' => get_string('movehere', 'questionnaire')) + $butclass;
                $msrc = $CFG->wwwroot.'/mod/questionnaire/images/movehere.gif';
                ${$quesgroup}[] =& $mform->createElement('static', 'opentag_'.$question->id, '', '<div class="qicons">');
                $newposition = $max == $pos ? 0 : $pos;
                ${$quesgroup}[] =& $mform->createElement('image', 'moveherebutton['.$newposition.']', $msrc, $mextra);
                ${$quesgroup}[] =& $mform->createElement('static', 'closetag_'.$question->id, '', '</div>');
            	}
            	else {
            		${$quesgroup}[] =& $mform->createElement('static', 'qnums', '', '<div class="qicons">Move From Here</div>');
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
            ${$quesgroup}[] =& $mform->createElement('static', 'qname_'.$question->id, '', '<div class="qname">'.$qname.'</div><br />');
            ${$quesgroup}[] =& $mform->createElement('static', 'qcontent_'.$question->id, '', '<div class="qname">'.$content.'</div><br /><hr />');
            
            $mform->addGroup($$quesgroup, 'questgroup', '', '', false);

            //$mform->addElement('static', 'qcontent_'.$question->id, '', '<div style=>'.$content.'</div>');
			
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

    function validation($data){

    }

}

class questionnaire_edit_question_form extends moodleform {

    function definition() {
        global $CFG, $COURSE, $questionnaire, $question, $QUESTIONNAIRE_REALMS, $SESSION;

        // 'sticky' required response value for further new questions
        if (isset($SESSION->questionnaire->required) && !isset($question->qid)) {
            $question->required = $SESSION->questionnaire->required;
        }
        if (!isset($question->type_id)) {
            error('Undefined question type.');
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
            $ohelpname = 'checkanswers';
            break;
        case QUESRADIO:
            $deflength = 0;
            $defprecise = 0;
            $lhelpname = 'alignment';
            $olabelname = 'possibleanswers';
            $ohelpname = 'radioanswers';
            break;
        case QUESRATE:
            $deflength = 5;
            $defprecise = 0;
            $lhelpname = 'numberscaleitems';
            $phelpname = 'kindofratescale';
            $olabelname = 'possibleanswers';
            $ohelpname = 'rateanswers';
            break;
        case QUESNUMERIC:
            $deflength = 0;
            $defprecise = 0;
            $lhelpname = 'maxdigitsallowed';
            $phelpname = 'numberofdecimaldigits';
            break;
        case QUESDROP:
            $deflength = 0;
            $defprecise = 0;
            $olabelname = 'possibleanswers';
            $ohelpname = 'dropanswers';
            break;
        default:
            $deflength = 0;
            $defprecise = 0;
        }

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        // display different messages for new question creation and existing question modification
        if (isset($question->qid)) {
            $streditquestion = get_string('editquestion', 'questionnaire', questionnaire_get_type($question->type_id));
        } else {
            $streditquestion = get_string('addnewquestion', 'questionnaire', questionnaire_get_type($question->type_id));
        }

        $mform->addElement('header', 'questionhdr', $streditquestion);

        /// Name and required fields:
        if ($question->type_id != QUESSECTIONTEXT && $question->type_id != '') {
            $stryes = get_string('yes');
            $strno  = get_string('no');

            $mform->addElement('text', 'name', get_string('optionalname', 'questionnaire'), array('size'=>'30', 'maxlength'=>'30'));
            $mform->setType('name', PARAM_TEXT);
            $mform->setHelpButton('name', array('questionname', get_string('optionalname', 'questionnaire'), 'questionnaire'));

            $reqgroup = array();
            $reqgroup[] =& $mform->createElement('radio', 'required', '', $stryes, 'y');
            $reqgroup[] =& $mform->createElement('radio', 'required', '', $strno, 'n');
            $mform->addGroup($reqgroup, 'reqgroup', get_string('required', 'questionnaire'), ' ', false);
            $mform->setHelpButton('reqgroup', array('required', get_string('required', 'questionnaire'), 'questionnaire'));
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
            $mform->setHelpButton('lengroup', array($lhelpname, get_string($lhelpname, 'questionnaire'), 'questionnaire'));
        } else { // QUESTEXT or QUESESSAY or QUESRATE
            $question->length = isset($question->length) ? $question->length : $deflength;
            $mform->addElement('text', 'length', get_string($lhelpname, 'questionnaire'), array('size'=>'1'));
            $mform->setHelpButton('length', array($lhelpname, get_string($lhelpname, 'questionnaire'), 'questionnaire'));
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
            $mform->setHelpButton('precise', array($phelpname, get_string($lhelpname, 'questionnaire'), 'questionnaire'));
        } else {
            $question->precise = isset($question->precise) ? $question->precise : $defprecise;
            $mform->addElement('text', 'precise', get_string($phelpname, 'questionnaire'), array('size'=>'1'));
            $mform->setHelpButton('precise', array($phelpname, get_string($lhelpname, 'questionnaire'), 'questionnaire'));
        }

        /// Content field:
        $mform->addElement('htmleditor', 'content', get_string('text', 'questionnaire'), array('rows' => 10));
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', null, 'required', null, 'client');
        $mform->setHelpButton('content', array('writing', 'questions', 'richtext'), false, 'editorhelpbutton');

        /// Options section:
        // has answer options ... so show that part of the form
        if (get_field('questionnaire_question_type', 'has_choices', 'typeid', $question->type_id) == 'y' ) {
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
            $mform->setHelpButton('allchoices', array($ohelpname, get_string($olabelname, 'questionnaire'), 'questionnaire'));

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

    function validation($data){

    }

}
?>