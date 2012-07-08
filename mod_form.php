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

class mod_questionnaire_mod_form extends moodleform_mod {

    function definition() {
        global $COURSE;

        $questionnaire = new questionnaire($this->_instance, null, $COURSE, $this->_cm);

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'questionnaire'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(true, get_string('summary', 'questionnaire'));

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('timing', 'form'));

        $enableopengroup = array();
        $enableopengroup[] =& $mform->createElement('checkbox', 'useopendate', get_string('opendate', 'questionnaire'));
        $enableopengroup[] =& $mform->createElement('date_time_selector', 'opendate', '');
        $mform->addGroup($enableopengroup, 'enableopengroup', get_string('opendate', 'questionnaire'), ' ', false);
        $mform->addHelpButton('enableopengroup', 'opendate', 'questionnaire');
        $mform->disabledIf('enableopengroup', 'useopendate', 'notchecked');

        $enableclosegroup = array();
        $enableclosegroup[] =& $mform->createElement('checkbox', 'useclosedate', get_string('closedate', 'questionnaire'));
        $enableclosegroup[] =& $mform->createElement('date_time_selector', 'closedate', '');
        $mform->addGroup($enableclosegroup, 'enableclosegroup', get_string('closedate', 'questionnaire'), ' ', false);
        $mform->addHelpButton('enableclosegroup', 'closedate', 'questionnaire');
        $mform->disabledIf('enableclosegroup', 'useclosedate', 'notchecked');

        //-------------------------------------------------------------------------------
        global $QUESTIONNAIRE_TYPES, $QUESTIONNAIRE_RESPONDENTS, $QUESTIONNAIRE_ELIGIBLES,
               $QUESTIONNAIRE_RESPONSEVIEWERS, $QUESTIONNAIRE_REALMS;
        $mform->addElement('header', 'questionnairehdr', get_string('responseoptions', 'questionnaire'));

        $mform->addElement('select', 'qtype', get_string('qtype', 'questionnaire'), $QUESTIONNAIRE_TYPES);
        $mform->addHelpButton('qtype', 'qtype', 'questionnaire');
        
        $mform->addElement('hidden', 'cannotchangerespondenttype');
        $mform->addElement('select', 'respondenttype', get_string('respondenttype', 'questionnaire'), $QUESTIONNAIRE_RESPONDENTS);
        $mform->addHelpButton('respondenttype', 'respondenttype', 'questionnaire');
        $mform->disabledIf('respondenttype', 'cannotchangerespondenttype', 'eq', 1);
        
        $mform->addElement('static', 'old_resp_eligible', get_string('respondenteligible', 'questionnaire'),
                           get_string('respeligiblerepl', 'questionnaire'));
        $mform->addHelpButton('old_resp_eligible', 'respondenteligible', 'questionnaire');
        
        $mform->addElement('select', 'resp_view', get_string('responseview', 'questionnaire'), $QUESTIONNAIRE_RESPONSEVIEWERS);
        $mform->addHelpButton('resp_view', 'responseview', 'questionnaire');
        
        $options = array('0'=>get_string('no'),'1'=>get_string('yes'));
        $mform->addElement('select', 'resume', get_string('resume', 'questionnaire'), $options);
        $mform->addHelpButton('resume', 'resume', 'questionnaire');
        
        $mform->addElement('modgrade', 'grade', get_string('grade', 'questionnaire'), false);
        $mform->setDefault('grade', 0);

        //-------------------------------------------------------------------------------
        if (empty($questionnaire->sid)) {
            if (!isset($questionnaire->id)) {
                $questionnaire->id = 0;
            }

            $mform->addElement('header', 'contenthdr', get_string('contentoptions', 'questionnaire'));
            $mform->addHelpButton('contenthdr', 'createcontent', 'questionnaire');
            
            $mform->addElement('radio', 'create', get_string('createnew', 'questionnaire'), '', 'new-0');

            $surveys = questionnaire_get_survey_select($questionnaire->id, $COURSE->id, 0, 'template');
            if (!empty($surveys)) {
                $prelabel = get_string('usetemplate', 'questionnaire');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            } else {
                $mform->addElement('static', 'usetemplate', get_string('usetemplate', 'questionnaire'),
                                   '('.get_string('notemplatesurveys', 'questionnaire').')');
            }

            $surveys = questionnaire_get_survey_select($questionnaire->id, $COURSE->id, 0, 'public');
            if (!empty($surveys)) {
                $prelabel = get_string('usepublic', 'questionnaire');
                foreach ($surveys as $value => $label) {
                    $mform->addElement('radio', 'create', $prelabel, $label, $value);
                    $prelabel = '';
                }
            } else {
                $mform->addElement('static', 'usepublic', get_string('usepublic', 'questionnaire'),
                                   '('.get_string('nopublicsurveys', 'questionnaire').')');
            }

            $mform->setDefault('create', 'new-0');
        }

        //-------------------------------------------------------------------------------
// features definitions moved to lib.php, lines 39 & seq. by JR 21 JAN 2010
/*        $features = new stdClass;
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $features->intro = true;
        $this->standard_coursemodule_elements($features);*/
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values){
        global $DB;
    	if (empty($default_values['opendate'])) {
            $default_values['useopendate'] = 0;
        } else {
            $default_values['useopendate'] = 1;
        }
        if (empty($default_values['closedate'])) {
            $default_values['useclosedate'] = 0;
        } else {
            $default_values['useclosedate'] = 1;
        }
        // prevent questionnaire set to "anonymous" to be reverted to "full name"
        $default_values['cannotchangerespondenttype'] = 0;
        if (!empty($default_values['respondenttype']) && $default_values['respondenttype'] == "anonymous") {
            // if this questionnaire has responses
        	$numresp = $DB->count_records('questionnaire_response', array('survey_id' => $default_values['sid'],'complete' => 'y'));
            if ($numresp) {
                $default_values['cannotchangerespondenttype'] = 1;              
            }
        }
    }

    function validation($data, $files){
        return parent::validation($data, $files);
    }
    
}