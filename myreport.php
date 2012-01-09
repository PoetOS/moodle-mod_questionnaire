<?php  // $Id$

/// This page shows results of a questionnaire to a student.

    require_once("../../config.php");
    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

    $strsummary = get_string('summary', 'questionnaire');
    $strall = get_string('myresponses', 'questionnaire'); 
    $strviewbyresponse = get_string('viewbyresponse', 'questionnaire');
    $strmodname = get_string('modulename', 'questionnaire');
    $strmodnameplural = get_string('modulenameplural', 'questionnaire');
    $strmyresults = get_string('myresults', 'questionnaire');
    $strquestionnaires = get_string('modulenameplural', 'questionnaire');

    $instance = required_param('instance', PARAM_INT);   // questionnaire ID
    $userid = optional_param('user', $USER->id, PARAM_INT);
    $rid = optional_param('rid', false, PARAM_INT);
    $byresponse = optional_param('byresp', false, PARAM_INT);
    $action = optional_param('action', $strsummary, PARAM_RAW); // for languages utf-8 compatibility JR

    if (! $questionnaire = get_record("questionnaire", "id", $instance)) {
        error("Questionnaire is incorrect");
    }
    if (! $course = get_record("course", "id", $questionnaire->course)) {
        error("Course is misconfigured");
    }
    if (! $cm = get_coursemodule_from_instance("questionnaire", $questionnaire->id, $course->id)) {
        error("Course Module ID was incorrect");
    }

    require_login($course->id);

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    /// Should never happen, unless called directly by a snoop...
    if ( !has_capability('mod/questionnaire:readownresponses',$context) 
        || $userid != $USER->id) {
        error('Permission denied');
    }
    
    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

/// Tab setup:
    $SESSION->questionnaire->current_tab = 'myreport';

    switch ($action) {
    case $strsummary:
    case 'summary':
        if (empty($questionnaire->survey)) {
            error('Survey does not exist.');
        }
        $SESSION->questionnaire->current_tab = 'mysummary';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $resps = get_records_select('questionnaire_response', $select);
        if (!$resps = get_records_select('questionnaire_response', $select)) {
            $resps = array();            
        }
        $rids = array_keys($resps);
        $titletext = get_string('myresponsetitle', 'questionnaire', count($resps));

    /// Print the page header
        $extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => $strmyresults, 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $cm);
        print_header_simple(get_string('questionnairereport', 'questionnaire'), '', $navigation);
        
        /// print the tabs
        include('tabs.php');

        if (!empty($questionnaire->survey->theme)) {
            $href = $CFG->wwwroot.'/mod/questionnaire/css/'.$questionnaire->survey->theme;
            echo '<script type="text/javascript">
                //<![CDATA[
                document.write("<link rel=\"stylesheet\" type=\"text/css\" href=\"'.$href.'\">")
                //]]>
                </script>';
        }   

        print_heading($titletext, 'center', '4');
        echo '<div class = "active">';
        $questionnaire->survey_results(1, 1, '', '', $rids, '', $USER->id);
        echo '</div>';

    /// Finish the page
        print_footer($course);
        break;

    case $strall:
    case 'vall':
        if (empty($questionnaire->survey)) {
            error('Survey does not exist.');
        }
        $SESSION->questionnaire->current_tab = 'myvall';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $resps = get_records_select('questionnaire_response', $select);
        $titletext = get_string('myresponses', 'questionnaire');

    /// Print the page header
        $extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => $strmyresults, 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $cm);
        print_header_simple(get_string('questionnairereport', 'questionnaire'), '', $navigation);
            
        /// print the tabs
        include('tabs.php');

        print_heading($titletext.':', 'center', '4');

        echo '<table class = "active"><tr><td>';
        $questionnaire->view_all_responses($resps);
        echo '</td></tr></table>';
        
    /// Finish the page
        print_footer($course);
        break;

    case $strviewbyresponse:
    case 'vresp':
        if (empty($questionnaire->survey)) {
            error(get_string('surveynotexists', 'questionnaire'));
        }
        $SESSION->questionnaire->current_tab = 'mybyresponse';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $resps = get_records_select('questionnaire_response', $select);
        $rids = array_keys($resps);
        if (!$rid) {
            $rid = $rids[0];
        }
        if ($rid) {
            $titletext = '<strong>'.get_string('viewyourresponses', 'questionnaire').'</strong>: ';
        }

    /// Print the page header
        $extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => $strmyresults, 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $cm);
        print_header_simple(get_string('questionnairereport', 'questionnaire'), '', $navigation);
           
        /// print the tabs
        include('tabs.php');

        if (count($resps) > 1) {       
            echo '<div style="text-align:center; padding-bottom:5px;">';
            echo ($titletext);
            $questionnaire->survey_results_navbar_student ($rid, $userid, $instance, $resps);
            echo '</div>';
        }       
        echo '<table class = "active"><tr><td>';
        $questionnaire->view_response($rid);
        echo ('</td></tr></table>');
        if (count($resps) > 1) {       
            echo '<div style="text-align:center; padding-bottom:5px;">';
            echo ($titletext);
            $questionnaire->survey_results_navbar_student ($rid, $userid, $instance, $resps);
            echo '</div>';
        }       


    /// Finish the page
        print_footer($course);
        break;

    case get_string('return', 'questionnaire'):
    default:
        redirect('view.php?id='.$cm->id);
    }
?>