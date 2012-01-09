<?php  // $Id: myreport.php,v 1.22 2011/01/18 17:01:33 joseph_rezeau Exp $

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

    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $instance))) {
        print_error('incorrectquestionnaire', 'questionnaire');
    }
    if (! $course = $DB->get_record("course", array("id" => $questionnaire->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("questionnaire", $questionnaire->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    require_course_login($course, true, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    /// Should never happen, unless called directly by a snoop...
    if ( !has_capability('mod/questionnaire:readownresponses',$context) 
        || $userid != $USER->id) {
        print_error('Permission denied');
    }
    $url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/myreport.php', array('instance'=>$instance));
    if (isset($userid)) {
        $url->param('userid', $userid);
    }
    if (isset($rid)) {
        $url->param('rid', $rid);
        $params['rid'] = $rid;
    }
    if (isset($byresponse)) {
        $url->param('byresponse', $byresponse);
    }
    if (isset($action)) {
        $url->param('action', $action);
    }

    $PAGE->set_url($url);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->navbar->add(get_string('questionnairereport', 'questionnaire'));
    $PAGE->navbar->add($strmyresults);

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

/// Tab setup:
    $SESSION->questionnaire->current_tab = 'myreport';

    switch ($action) {
    case $strsummary:
    case 'summary':
        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        }
        $SESSION->questionnaire->current_tab = 'mysummary';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $resps = $DB->get_records_select('questionnaire_response', $select);
        if (!$resps = $DB->get_records_select('questionnaire_response', $select)) {
            $resps = array();
        }
        $rids = array_keys($resps);
        $titletext = get_string('myresponsetitle', 'questionnaire', count($resps));

    /// Print the page header
        echo $OUTPUT->header();

        /// print the tabs
        include('tabs.php');

        echo $OUTPUT->heading($titletext);
        echo '<div class = "generalbox">';
        $questionnaire->survey_results(1, 1, '', '', $rids, '', $USER->id);
        echo '</div>';

    /// Finish the page
        echo $OUTPUT->footer($course);
        break;

    case $strall:
    case 'vall':
        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        }
        $SESSION->questionnaire->current_tab = 'myvall';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $resps = $DB->get_records_select('questionnaire_response', $select);
        $titletext = get_string('myresponses', 'questionnaire');

    /// Print the page header
        echo $OUTPUT->header();

        /// print the tabs
        include('tabs.php');

        echo $OUTPUT->heading($titletext.':');

        $questionnaire->view_all_responses($resps);

    /// Finish the page
        echo $OUTPUT->footer($course);
        break;

    case $strviewbyresponse:
    case 'vresp':
        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        }
        $SESSION->questionnaire->current_tab = 'mybyresponse';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $resps = $DB->get_records_select('questionnaire_response', $select);
        $rids = array_keys($resps);
        if (!$rid) {
            $rid = $rids[0];
        }
        if ($rid) {
        	$numresp = $questionnaire->count_submissions($USER->id);
            $titletext = '<strong>'.get_string('myresponsetitle', 'questionnaire', $numresp).'</strong>: ';
        }

    /// Print the page header
        echo $OUTPUT->header();

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
        echo $OUTPUT->footer($course);
        break;

    case get_string('return', 'questionnaire'):
    default:
        redirect('view.php?id='.$cm->id);
    }
?>