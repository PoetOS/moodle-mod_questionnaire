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

// This page shows results of a questionnaire to a student.

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

$strsummary = get_string('summary', 'questionnaire');
$strall = get_string('myresponses', 'questionnaire');
$strviewbyresponse = get_string('viewbyresponse', 'questionnaire');
$strmodname = get_string('modulename', 'questionnaire');
$strmodnameplural = get_string('modulenameplural', 'questionnaire');
$strmyresults = get_string('myresults', 'questionnaire');
$strquestionnaires = get_string('modulenameplural', 'questionnaire');

$instance = required_param('instance', PARAM_INT);   // Questionnaire ID.
$userid = optional_param('user', $USER->id, PARAM_INT);
$rid = optional_param('rid', null, PARAM_INT);
$byresponse = optional_param('byresponse', 0, PARAM_INT);
$action = optional_param('action', 'summary', PARAM_RAW);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

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
$context = context_module::instance($cm->id);
// Should never happen, unless called directly by a snoop...
if ( !has_capability('mod/questionnaire:readownresponses', $context)
    || $userid != $USER->id) {
    print_error('Permission denied');
}
$url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/myreport.php', array('instance'=>$instance));
if (isset($userid)) {
    $url->param('userid', $userid);
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

$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

// Tab setup.
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
        if (count($resps) > 1) {
            $titletext = get_string('myresponsetitle', 'questionnaire', count($resps));
        } else {
            $titletext = get_string('yourresponse', 'questionnaire');
        }

        // Print the page header.
        echo $OUTPUT->header();

        // Print the tabs.
        include('tabs.php');

        echo $OUTPUT->heading($titletext);
        echo '<div class = "generalbox">';
        $questionnaire->survey_results(1, 1, '', '', $rids, $USER->id);
        echo '</div>';

        // Finish the page.
        echo $OUTPUT->footer($course);
        break;

    case $strall:
    case 'vall':
        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        }
        $SESSION->questionnaire->current_tab = 'myvall';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $sort = 'submitted ASC';
        $resps = $DB->get_records_select('questionnaire_response', $select, $params=null, $sort);
        $titletext = get_string('myresponses', 'questionnaire');

        // Print the page header.
        echo $OUTPUT->header();

        // Print the tabs.
        include('tabs.php');

        echo $OUTPUT->heading($titletext.':');
        $questionnaire->view_all_responses($resps);

        // Finish the page.
        echo $OUTPUT->footer($course);
        break;

    case $strviewbyresponse:
    case 'vresp':
        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        }
        $SESSION->questionnaire->current_tab = 'mybyresponse';
        $select = 'survey_id = '.$questionnaire->sid.' AND username = \''.$userid.'\' AND complete=\'y\'';
        $sort = 'submitted ASC';
        $resps = $DB->get_records_select('questionnaire_response', $select, $params=null, $sort);
        $rids = array_keys($resps);

        // If more than one response for this respondent, display most recent response.
        if (!$rid) {
            $rid = end($rids);
        }
        $numresp = count($rids);
        if ($numresp > 1) {
            $titletext = get_string('myresponsetitle', 'questionnaire', $numresp);
        } else {
            $titletext = get_string('yourresponse', 'questionnaire');
        }

        // Print the page header.
        echo $OUTPUT->header();

        // Print the tabs.
        include('tabs.php');
        echo $OUTPUT->box_start();

        echo $OUTPUT->heading($titletext);

        if (count($resps) > 1) {
            echo '<div style="text-align:center; padding-bottom:5px;">';
            $questionnaire->survey_results_navbar_student ($rid, $userid, $instance, $resps);
            echo '</div>';
        }
        $questionnaire->view_response($rid);
        if (count($resps) > 1) {
            echo '<div style="text-align:center; padding-bottom:5px;">';
            $questionnaire->survey_results_navbar_student ($rid, $userid, $instance, $resps);
            echo '</div>';
        }
        echo $OUTPUT->box_end();
        // Finish the page.
        echo $OUTPUT->footer($course);
        break;

    case get_string('return', 'questionnaire'):
    default:
        redirect('view.php?id='.$cm->id);
}
