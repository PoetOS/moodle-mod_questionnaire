<?php // $Id: settings_form.php,v 1.2.2.3 2009/11/19 08:13:07 joseph_rezeau Exp $
/**
* print the form to add or edit a questionnaire-instance
*
* @version $Id: settings_form.php,v 1.2.2.3 2009/11/19 08:13:07 joseph_rezeau Exp $
* @author Mike Churchward
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package questionnaire
*/

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
// JR removed this require_once to solve course forced language pb in settings_form.php 
//require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

class questionnaire_settings_form extends moodleform {

    function definition() {
        global $CFG, $COURSE, $ESPCONFIG, $questionnaire, $QUESTIONNAIRE_REALMS;

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'contenthdr', get_string('contentoptions', 'questionnaire'));
        
        $mform->addElement('select', 'realm', get_string('realm', 'questionnaire'), $QUESTIONNAIRE_REALMS);
        $mform->setDefault('realm', $questionnaire->survey->realm);
        $mform->setHelpButton('realm', array('realm', get_string('realm', 'questionnaire'), 'questionnaire'));

        $mform->addElement('text', 'title', get_string('title', 'questionnaire'), array('size'=>'60'));
        $mform->setDefault('title', $questionnaire->survey->title);
        $mform->setType('title', PARAM_TEXT);
        $mform->setHelpButton('title', array('title', get_string('title', 'questionnaire'), 'questionnaire'));

        $mform->addElement('text', 'subtitle', get_string('subtitle', 'questionnaire'), array('size'=>'60'));
        $mform->setDefault('subtitle', $questionnaire->survey->subtitle);
        $mform->setType('subtitle', PARAM_TEXT);
        $mform->setHelpButton('subtitle', array('subtitle', get_string('subtitle', 'questionnaire'), 'questionnaire'));

        $mform->addElement('htmleditor', 'info', get_string('additionalinfo', 'questionnaire'), array('rows' => 10));
        $mform->setDefault('info', $questionnaire->survey->info);
        $mform->setType('info', PARAM_RAW);
        $mform->setHelpButton('info', array('additionalinfo', get_string('additionalinfo', 'questionnaire'), 'questionnaire'));

        $themes_array = array();
        $dir = dir($ESPCONFIG['css_path']);
        $dir->rewind();
        while ($file=$dir->read()) {
            if (stristr($file,".css")) {
                $pos = strrpos($file, ".");
                $name = substr($file, 0,$pos);
                $themes_array[$file] = $name;
            }
        }
        $dir->close();
        if (!empty($questionnaire->survey->theme)) {
            $selected = $questionnaire->survey->theme;
        } else {
            $selected = 'default';
        }
        $mform->addElement('select', 'theme', get_string('theme', 'questionnaire'), $themes_array);
        $mform->setDefault('theme', $questionnaire->survey->theme);
        $mform->setHelpButton('theme', array('selecttheme', get_string('selecttheme', 'questionnaire'), 'questionnaire'));

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'submithdr', get_string('submitoptions', 'questionnaire'));
        
        $mform->addElement('text', 'thanks_page', get_string('url', 'questionnaire'), array('size'=>'60'));
        $mform->setType('thanks_page', PARAM_TEXT);
        $mform->setDefault('thanks_page', $questionnaire->survey->thanks_page);
        $mform->setHelpButton('thanks_page', array('confurl', get_string('url', 'questionnaire'), 'questionnaire'));

        $mform->addElement('static', 'confmes', get_string('confalts', 'questionnaire'));
        $mform->setHelpButton('confmes', array('confpage', get_string('headingtext', 'questionnaire'), 'questionnaire'));

        $mform->addElement('text', 'thank_head', get_string('headingtext', 'questionnaire'), array('size'=>'30'));
        $mform->setType('thank_head', PARAM_TEXT);
        $mform->setDefault('thank_head', $questionnaire->survey->thank_head);

        $mform->addElement('htmleditor', 'thank_body', get_string('bodytext', 'questionnaire'), array('rows' => 10));
        $mform->setType('thank_body', PARAM_RAW);
        $mform->setDefault('thank_body', $questionnaire->survey->thank_body);
        $mform->setHelpButton('thank_body', array('writing', 'questions', 'richtext'), false, 'editorhelpbutton');

        $mform->addElement('text', 'email', get_string('email', 'questionnaire'), array('size'=>'75'));
        $mform->setType('email', PARAM_TEXT);
        $mform->setDefault('email', $questionnaire->survey->email);
        $mform->setHelpButton('email', array('sendemail', get_string('sendemail', 'questionnaire'), 'questionnaire'));

        //-------------------------------------------------------------------------------
        // Hidden fields
        $mform->addElement('hidden', 'id', 0);
        $mform->addElement('hidden', 'sid', 0);
        $mform->addElement('hidden', 'name', '');
        $mform->addElement('hidden', 'owner', '');

        //-------------------------------------------------------------------------------
        // buttons
        $mform->addElement('submit', 'submitbutton', get_string('savesettings', 'questionnaire'));
    }

    function validation($data){

    }

}
?>