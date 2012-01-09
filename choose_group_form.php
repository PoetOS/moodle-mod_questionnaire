<?php // $Id$
/**
* prints the form to choose the group you want to analyse
*
* @version $Id$
* @author Andreas Grabs
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package questionnaire
*/

require_once $CFG->libdir.'/formslib.php';

class questionnaire_choose_group_form extends moodleform {
    var $questionnairedata;
    function definition() {
        $this->questionnairedata = new object();
        //this function can not be called, because not all data are available at this time
        //I use set_form_elements instead
    }

    //this function set the data used in set_form_elements()
    //in this form the only value have to set is course
    //eg: array('course' => $course)
    function set_questionnairedata($data) {
        if(is_array($data)) {
            foreach($data as $key => $val) {
                $this->questionnairedata->{$key} = $val;
            }
        }
    }

    //here the elements will be set
    //this function have to be called manually
    //the advantage is that the data are already set
    function set_form_elements(){
        global $CFG, $SESSION;
        $mform =& $this->_form;
        $sid = $SESSION->questionnaire_survey_id;
        $elementgroup = array();
        // hidden elements
        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'do_show');
        // visible elements
        $groups_options = array();
        if(isset($this->questionnairedata->currentgroupid)){
        	$currentgroupid = $this->questionnairedata->currentgroupid;
        }
        if(isset($this->questionnairedata->groups)){
        	$canviewallgroups =  $this->questionnairedata->canviewallgroups;
        	$groupmode =  $this->questionnairedata->groupmode;
            if ($canviewallgroups) {
                $groups_options['-1'] = get_string('allparticipants');
            }
            // count number of responses in each group
            foreach($this->questionnairedata->groups as $group) {
                $sql = "SELECT R.id, GM.id 
                    FROM ".$CFG->prefix."questionnaire_response R, ".$CFG->prefix."groups_members GM
                    WHERE R.survey_id=".$sid." AND
                        R.complete='y' AND
                        GM.groupid=".$group->id." AND
                        R.username=GM.userid";
                if (!($resps = get_records_sql($sql))) {
                    $resps = array();
                }
                if (!empty ($resps)) {
                    $respscount = count($resps);
                } else {
                    $respscount = 0;
                }
                $groups_options[$group->id] = get_string('group').': '.$group->name.' ('.$respscount.')';
            }
            if ($canviewallgroups) {
                $groups_options['-2'] = '---'.get_string('membersofselectedgroup','group').' '.get_string('allgroups').'---'; 
                $groups_options['-3'] = '---'.get_string('groupnonmembers').'---'; 
            }
            if ($groupmode == 2) {
                $groups_options['-2'] = '---'.get_string('membersofselectedgroup','group').' '.get_string('allgroups').'---'; 
            }
        }
        $attributes = 'onChange="this.form.submit()"';
        $elementgroup[] =& $mform->createElement('select','currentgroupid', '', $groups_options, $attributes);
        // buttons
		$mform->setDefault('currentgroupid', $currentgroupid);
        $mform->addGroup($elementgroup, 'elementgroup', '', array(' '), false);
        $mform->setHelpButton('elementgroup', array('viewallresponses', get_string('viewallresponses', 'questionnaire'), 'questionnaire'));
//-------------------------------------------------------------------------------
    }

}
?>
