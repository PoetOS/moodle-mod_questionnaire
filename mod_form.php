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

class mod_questionnaire_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE;

        $questionnaire = new questionnaire($this->_instance, null, $COURSE, $this->_cm);

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'questionnaire'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('htmleditor', 'summary', get_string("summary"), array('rows' => 20));
        $mform->setType('summary', PARAM_RAW);
        $mform->addRule('summary', null, 'required', null, 'client');
        $mform->setHelpButton('summary', array('writing', 'questions', 'richtext'), false, 'editorhelpbutton');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('timing', 'form'));

        $enableopengroup = array();
        $enableopengroup[] =& $mform->createElement('checkbox', 'useopendate', get_string('opendate', 'questionnaire'));
        $enableopengroup[] =& $mform->createElement('date_time_selector', 'opendate', '');
        $mform->addGroup($enableopengroup, 'enableopengroup', get_string('opendate', 'questionnaire'), ' ', false);
        $mform->setHelpButton('enableopengroup', array('opendate', get_string('opendate', 'questionnaire'), 'questionnaire'));
        $mform->disabledIf('enableopengroup', 'useopendate', 'notchecked');

        $enableclosegroup = array();
        $enableclosegroup[] =& $mform->createElement('checkbox', 'useclosedate', get_string('closedate', 'questionnaire'));
        $enableclosegroup[] =& $mform->createElement('date_time_selector', 'closedate', '');
        $mform->addGroup($enableclosegroup, 'enableclosegroup', get_string('closedate', 'questionnaire'), ' ', false);
        $mform->setHelpButton('enableclosegroup', array('closedate', get_string('closedate', 'questionnaire'), 'questionnaire'));
        $mform->disabledIf('enableclosegroup', 'useclosedate', 'notchecked');

        //-------------------------------------------------------------------------------
        global $QUESTIONNAIRE_TYPES, $QUESTIONNAIRE_RESPONDENTS, $QUESTIONNAIRE_ELIGIBLES,
               $QUESTIONNAIRE_RESPONSEVIEWERS, $QUESTIONNAIRE_REALMS;
        $mform->addElement('header', 'questionnairehdr', get_string('responseoptions', 'questionnaire'));

        $mform->addElement('select', 'qtype', get_string('qtype', 'questionnaire'), $QUESTIONNAIRE_TYPES);
        $mform->setHelpButton('qtype', array('qtype', get_string('qtype', 'questionnaire'), 'questionnaire'));

        $mform->addElement('hidden', 'cannotchangerespondenttype');        
        $mform->addElement('select', 'respondenttype', get_string('respondenttype', 'questionnaire'), $QUESTIONNAIRE_RESPONDENTS);
        $mform->setHelpButton('respondenttype', array('respondenttype', get_string('respondenttype', 'questionnaire'), 'questionnaire'));
        $mform->disabledIf('respondenttype', 'cannotchangerespondenttype', 'eq', 1);

        $mform->addElement('static', 'old_resp_eligible', get_string('respondenteligible', 'questionnaire'),
                           get_string('respeligiblerepl', 'questionnaire'));
        $mform->setHelpButton('old_resp_eligible', array('respondenteligible', get_string('respondenteligible', 'questionnaire'), 'questionnaire'));

        $mform->addElement('select', 'resp_view', get_string('responseview', 'questionnaire'), $QUESTIONNAIRE_RESPONSEVIEWERS);
        $mform->setHelpButton('resp_view', array('responseview', get_string('responseview', 'questionnaire'), 'questionnaire'));

        $options = array('0'=>get_string('no'),'1'=>get_string('yes'));
        $mform->addElement('select', 'resume', get_string('resume', 'questionnaire'), $options);
        $mform->setHelpButton('resume', array('resume', get_string('resume', 'questionnaire'), 'questionnaire'));

        $mform->addElement('modgrade', 'grade', get_string('grade', 'questionnaire'));
        $mform->setDefault('grade', 100);

        //-------------------------------------------------------------------------------
        if (empty($questionnaire->sid)) {
            if (!isset($questionnaire->id)) {
                $questionnaire->id = 0;
            }

            $mform->addElement('header', 'contenthdr', get_string('contentoptions', 'questionnaire'));
            $mform->setHelpButton('contenthdr', array('createcontent', get_string('createcontent', 'questionnaire'), 'questionnaire'));

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
        $features = new stdClass;
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);
        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values){
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
			$numresp = count_records('questionnaire_response', 'survey_id', $default_values['sid'], '', '','complete', 'y');
			if ($numresp) {
				$default_values['cannotchangerespondenttype'] = 1;				
			}
        }
    }

    function validation($data){

    }

}
?>