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

global $SESSION, $CFG;
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

$instance = optional_param('instance', false, PARAM_INT);   // Questionnaire ID.
$action = optional_param('action', 'vall', PARAM_ALPHA);
$sid = optional_param('sid', null, PARAM_INT);              // Survey id.
$rid = optional_param('rid', false, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$byresponse = optional_param('byresponse', false, PARAM_INT);
$individualresponse = optional_param('individualresponse', false, PARAM_INT);
$currentgroupid = optional_param('currentgroupid', -1, PARAM_INT); // Groupid.
$user = optional_param('user', '', PARAM_INT);
$userid = $USER->id;
switch ($action) {
    case 'vallasort':
        $sort = 'ascending';
       break;
    case 'vallarsort':
        $sort = 'descending';
       break;
    default:
        $sort = 'default';
}

if ($instance === false) {
    if (!empty($SESSION->instance)) {
        $instance = $SESSION->instance;
    } else {
        print_error('requiredparameter', 'questionnaire');
    }
}
$SESSION->instance = $instance;

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

$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

// If you can't view the questionnaire, or can't view a specified response, error out.
$context = context_module::instance($cm->id);
if (!has_capability('mod/questionnaire:readallresponseanytime', $context) &&
  !($questionnaire->capabilities->view && $questionnaire->can_view_response($rid))) {
    // Should never happen, unless called directly by a snoop...
    print_error('nopermissions', 'moodle', $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id);
}

$questionnaire->canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
$sid = $questionnaire->survey->id;

$url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/report.php');
if ($instance) {
    $url->param('instance', $instance);
}

$url->param('action', $action);

if ($type) {
    $url->param('type', $type);
}
if ($byresponse || $individualresponse) {
    $url->param('byresponse', 1);
}
// If we are deleting a single response, keep displaying navigation links to 'view by response'.
if ($action == 'dresp') {
    $url->param('action', 'vresp');
    $url->param('byresponse', 1);
}
if ($user) {
    $url->param('user', $user);
}
$PAGE->set_url($url);
$PAGE->set_context($context);

// Tab setup.

$SESSION->questionnaire->current_tab = 'allreport';

$strdeleteallresponses = get_string('deleteallresponses', 'questionnaire');
$strdeleteresp = get_string('deleteresp', 'questionnaire');
$strdownloadcsv = get_string('downloadtext');
$strviewallresponses = get_string('viewallresponses', 'questionnaire');
$strsummary = get_string('summary', 'questionnaire');
$strviewbyresponse = get_string('viewbyresponse', 'questionnaire');
$strquestionnaires = get_string('modulenameplural', 'questionnaire');

// Get all responses for further use in viewbyresp and deleteall etc.
// All participants.
$sql = "SELECT R.id, R.survey_id, R.submitted, R.username
         FROM {questionnaire_response} R
         WHERE R.survey_id = ? AND
               R.complete='y'
         ORDER BY R.id";
if (!($respsallparticipants = $DB->get_records_sql($sql, array($sid)))) {
    $respsallparticipants = array();
}
$SESSION->questionnaire->numrespsallparticipants = count ($respsallparticipants);
$SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numrespsallparticipants;
$castsql = $DB->sql_cast_char2int('R.username');

// Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
$groupmode = groups_get_activity_groupmode($cm, $course);
$questionnairegroups = '';
$groupscount = 0;
$currentsessiongroupid = -1;
$SESSION->questionnaire->respscount = 0;
$SESSION->questionnaire_survey_id = $sid;

if ($groupmode > 0) {
    if ($groupmode == 1) {
        $questionnairegroups = groups_get_all_groups($course->id, $userid);
    }
    if ($groupmode == 2 || $questionnaire->canviewallgroups) {
        $questionnairegroups = groups_get_all_groups($course->id);
    }

    if (!empty($questionnairegroups)) {
        $groupscount = count($questionnairegroups);
        foreach ($questionnairegroups as $key) {
            $firstgroupid = $key->id;
            break;
        }
        $SESSION->questionnaire->currentsessiongroupid = $currentgroupid;
        if ($groupscount === 0 && $groupmode == 1) {
            $currentgroupid = 0;
        }
        if ($groupmode == 1 && !$questionnaire->canviewallgroups && $currentgroupid == -1) {
            $currentgroupid = $firstgroupid;
        }
        if (isset($SESSION->questionnaire->currentgroupid)) { // Needed for view by resp and delete all.
            $currentsessiongroupid = $SESSION->questionnaire->currentgroupid;
        } else {
            $currentsessiongroupid = -1;
        }

        // All members of any group.
        $sql = "SELECT DISTINCT R.id, R.survey_id, R.submitted, R.username
                FROM {questionnaire_response} R,
                    {groups_members} GM
                WHERE R.survey_id = ? AND
                      R.complete='y' AND
                      GM.groupid>0 AND " . $castsql. " = GM.userid
                ORDER BY R.id";
        if (!($respsallgroupmembers = $DB->get_records_sql($sql, array($sid)))) {
            $respsallgroupmembers = array();
        }
        $SESSION->questionnaire->numrespsallgroupmembers = count ($respsallgroupmembers);

        // Not members of any group.
        $sql = "SELECT R.id, R.survey_id, R.submitted, R.username, U.id AS userid
                FROM {questionnaire_response} R,
                    {user} U
                 WHERE R.survey_id = ? AND
                   R.complete='y' AND " . $castsql . "=U.id
                ORDER BY userid";
        if (!($respsnongroupmembers = $DB->get_records_sql($sql, array($sid)))) {
            $respsnongroupmembers = array();
        }
        foreach ($respsnongroupmembers as $resp => $key) {
            if (groups_has_membership($cm, $key->userid)) {
                unset($respsnongroupmembers[$resp]);
            }
        }
        if (!($respsnongroupmembers)) {
            $respsnongroupmembers = array();
        }
        $SESSION->questionnaire->numrespsnongroupmembers = count ($respsnongroupmembers);

        // Current group members.
        $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
            FROM {questionnaire_response} R,
                {groups_members} GM
             WHERE R.survey_id= ? AND
               R.complete='y' AND
               GM.groupid = ? AND " . $castsql . "=GM.userid
            ORDER BY R.id";
        if (!($currentgroupresps = $DB->get_records_sql($sql, array($sid, $currentgroupid)))) {
            $currentgroupresps = array();
        }
        $SESSION->questionnaire->numcurrentgroupresps = count ($currentgroupresps);

    } else {
        // Groupmode = separate groups but user is not member of any group
        // and does not have moodle/site:accessallgroups capability -> refuse view responses.
        if (!$questionnaire->canviewallgroups) {
            $currentgroupid = 0;
        }
    }

    if ($currentsessiongroupid > 0) {
        $groupname = get_string('group').' <strong>'.groups_get_group_name($currentsessiongroupid).'</strong>';
    } else {
        switch ($currentsessiongroupid) {
            case '0':    // Should not happen.
                $groupname = '<strong>'.get_string('groupmembersonlyerror', 'group').'</strong>';
                break;
            case '-1':
                $groupname = '<strong>'.get_string('allparticipants').'</strong>';
                break;
            case '-2':
                $groupname = '<strong>'.get_string('allgroups').'</strong>';
                break;
            case '-3':
                $groupname = '<strong>'.get_string('groupnonmembers').'</strong>';
                break;
        }
    }
}

if ($currentgroupid > 0) {
    $SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numcurrentgroupresps;
} else {
    switch ($currentgroupid) {
        case '0':
                $SESSION->questionnaire->numselectedresps = 0;
            break;
        case '-2':
            $SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numrespsallgroupmembers;
            break;
        case '-3':
            $SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numrespsnongroupmembers;
    }
}

switch ($action) {

    case 'dresp':  // Delete individual response? Ask for confirmation.

        require_capability('mod/questionnaire:deleteresponses', $context);

        if (empty($questionnaire->survey)) {
            $id = $questionnaire->survey;
            notify ("questionnaire->survey = /$id/");

            print_error('surveynotexists', 'questionnaire');
        } else if ($questionnaire->survey->owner != $course->id) {
            print_error('surveyowner', 'questionnaire');
        } else if (!$rid || !is_numeric($rid)) {
            print_error('invalidresponse', 'questionnaire');
        } else if (!($resp = $DB->get_record('questionnaire_response', array('id' => $rid)))) {
            print_error('invalidresponserecord', 'questionnaire');
        }

        $ruser = false;
        if (is_numeric($resp->username)) {
            if ($user = $DB->get_record('user', array('id' => $resp->username))) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'questionnaire').' -';
            }
        } else {
            $ruser = $resp->username;
        }

        // Print the page header.
        $PAGE->set_title(get_string('deletingresp', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();

        // Print the tabs.
        $SESSION->questionnaire->current_tab = 'individualresp';
        include('tabs.php');

        if ($questionnaire->respondenttype == 'anonymous') {
                $ruser = '- '.get_string('anonymous', 'questionnaire').' -';
        }

        // Print the confirmation.
        echo '<p>&nbsp;</p>';
        $msg = '<div class="warning centerpara">';
        $msg .= get_string('confirmdelresp', 'questionnaire', $ruser);
        $msg .= '</div>';
        $args = "action=dvresp&sid={$sid}&rid={$rid}&individualresponse=1";
        $urlyes = new moodle_url("/mod/questionnaire/report.php?{$args}");
        $args = "action=vresp&sid={$sid}&rid={$rid}&individualresponse=1";
        $urlno = new moodle_url("/mod/questionnaire/report.php?{$args}");
        $buttonyes = new single_button($urlyes, get_string('yes'));
        $buttonno = new single_button($urlno, get_string('no'));

        echo $OUTPUT->confirm($msg, $buttonyes, $buttonno);

        // Finish the page.
        echo $OUTPUT->footer($course);
        break;

    case 'delallresp': // Delete all responses? Ask for confirmation.

        require_capability('mod/questionnaire:deleteresponses', $context);

        $select = 'survey_id='.$sid.' AND complete = \'y\'';

        if (!($responses = $DB->get_records_select('questionnaire_response', $select, null, 'id', 'id'))) {
            return;
        }

        // Print the page header.
        $PAGE->set_title(get_string('deletingresp', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();

        // Print the tabs.
        $SESSION->questionnaire->current_tab = 'deleteall';
        include('tabs.php');

        // Print the confirmation.
        echo '<p>&nbsp;</p>';
        $msg = '<div class="warning centerpara">';
        if ($groupmode == 0) {   // No groups or visible groups.
            $msg .= get_string('confirmdelallresp', 'questionnaire');
        } else {                 // Separate groups.
            $msg .= get_string('confirmdelgroupresp', 'questionnaire', $groupname);
        }
        $msg .= '</div>';

        $args = "action=dvallresp&sid={$sid}&instance={$instance}";
        $urlyes = new moodle_url("/mod/questionnaire/report.php?{$args}");
        $args = "action=vall&sid={$sid}&instance={$instance}";
        $urlno = new moodle_url("/mod/questionnaire/report.php?{$args}");
        $buttonyes = new single_button($urlyes, get_string('yes'));
        $buttonno = new single_button($urlno, get_string('no'));

        echo $OUTPUT->confirm($msg, $buttonyes, $buttonno);

        // Finish the page.
        echo $OUTPUT->footer($course);
        break;

    case 'dvresp': // Delete single response. Do it!

        require_capability('mod/questionnaire:deleteresponses', $context);

        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        } else if ($questionnaire->survey->owner != $course->id) {
            print_error('surveyowner', 'questionnaire');
        } else if (!$rid || !is_numeric($rid)) {
            print_error('invalidresponse', 'questionnaire');
        } else if (!($resp = $DB->get_record('questionnaire_response', array('id' => $rid)))) {
            print_error('invalidresponserecord', 'questionnaire');
        }

        $ruser = false;
        if (is_numeric($resp->username)) {
            if ($user = $DB->get_record('user', array('id' => $resp->username))) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'questionnaire').' -';
            }
        } else {
            $ruser = $resp->username;
        }

        if (questionnaire_delete_response($rid)) {
            if ($questionnaire->respondenttype == 'anonymous') {
                    $ruser = '- '.get_string('anonymous', 'questionnaire').' -';
            }
            $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                FROM {questionnaire_response} R
                WHERE R.survey_id = ? AND
                R.complete='y'
                ORDER BY R.id";
                $resps = $DB->get_records_sql($sql, array($sid));
            if (empty($resps)) {
                $redirection = $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id;
            } else {
                $redirection = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$sid.
                    '&amp;instance='.$instance.'&amp;byresponse=1';
            }
            $deletedstr = get_string('deletedresp', 'questionnaire').$rid.get_string('by', 'questionnaire').$ruser.'.';
            redirect($redirection, $deletedstr, -1);
        } else {
            error (get_string('couldnotdelresp', 'questionnaire').$rid.get_string('by', 'questionnaire').$ruser.'?',
                   $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$sid.'&amp;&amp;instance='.
                   $instance.'byresponse=1');
        }
        break;

    case 'dvallresp': // Delete all responses in questionnaire (or group). Do it!

        require_capability('mod/questionnaire:deleteresponses', $context);

        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        } else if ($questionnaire->survey->owner != $course->id) {
            print_error('surveyowner', 'questionnaire');
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        $groupid = $currentsessiongroupid;
        if ($groupmode > 0) {
            switch ($groupid) {
                case -1:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                case -2:     // All members of any group.
                    $resps = $respsallgroupmembers;
                        break;
                case -3:     // Not members of any group.
                    $resps = $respsnongroupmembers;
                        break;
                default:     // Members of a specific group.
                    $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                        FROM {questionnaire_response} R,
                            {groups_members} GM
                         WHERE R.survey_id = ? AND
                           R.complete='y' AND
                           GM.groupid = ? AND " . $castsql . "=GM.userid
                        ORDER BY R.id";
                    if (!($resps = $DB->get_records_sql($sql, array($sid, $groupid)))) {
                        $resps = array();
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            } else {
                if ($rid === false) {
                    $resp = current($resps);
                    $rid = $resp->id;
                } else {
                    $resp = $DB->get_record('questionnaire_response', array('id' => $rid));
                }
                if (is_numeric($resp->username)) {
                    if ($user = $DB->get_record('user', array('id' => $resp->username))) {
                        $ruser = fullname($user);
                    } else {
                        $ruser = '- '.get_string('unknown', 'questionnaire').' -';
                    }
                } else {
                    $ruser = $resp->username;
                }
            }
        } else {
            $resps = $respsallparticipants;
        }

        if (!empty($resps)) {
            foreach ($resps as $response) {
                questionnaire_delete_response($response->id);
            }
            echo '<br /><br />';
            if ($groupid == -1) { // Deleted ALL responses.
                $deletedstr = get_string('deletedallresp', 'questionnaire');
            } else if ($groupid == -3) {
                $deletedstr = get_string('deletedallgroupresp', 'questionnaire', '<strong>'.
                                get_string('groupnonmembers').'</strong>');
            } else {              // Deleted responses in current group only.
                $deletedstr = get_string('deletedallgroupresp', 'questionnaire', '<strong>'.
                                groups_get_group_name($groupid).'</strong>');
            }
            $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                     FROM {questionnaire_response} R
                     WHERE R.survey_id = ? AND
                           R.complete='y'
                     ORDER BY R.id";
            if (!($resps = $DB->get_records_sql($sql, array($sid)))) {
                $respsallparticipants = array();
            }
            if (empty($resps)) {
                $redirection = $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id;
            } else {
                $redirection = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance;
            }
            redirect($redirection, $deletedstr, -1);
        } else {
            error (get_string('couldnotdelresp', 'questionnaire'),
                   $CFG->wwwroot.'/mod/questionnaire/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance);
        }
        break;

    case 'dwnpg': // Download page options.

        require_capability('mod/questionnaire:downloadresponses', $context);

        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();

        // Print the tabs.
        // Tab setup.
        if (empty($user)) {
            $SESSION->questionnaire->current_tab = 'downloadcsv';
        } else {
            $SESSION->questionnaire->current_tab = 'mydownloadcsv';
        }

        include('tabs.php');

        $groupname = '';
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case -1:     // All participants.
                    $groupname = get_string('allparticipants');
                    break;
                case -2:     // All members of any group.
                    $groupname = get_string('membersofselectedgroup', 'group').' '.get_string('allgroups');
                    break;
                case -3:     // Not members of any group.
                    $groupname = get_string('groupnonmembers');
                    break;
                default:     // Members of a specific group.
                    $groupname = get_string('membersofselectedgroup', 'group').' '.get_string('group').' '.
                        $questionnairegroups[$currentgroupid]->name;
            }
        }
        echo "<br /><br />\n";
        echo $OUTPUT->help_icon('downloadtextformat', 'questionnaire');
        echo '&nbsp;'.(get_string('downloadtext'));
        echo $OUTPUT->heading(get_string('textdownloadoptions', 'questionnaire'));
        echo $OUTPUT->box_start();
        echo "<form action=\"{$CFG->wwwroot}/mod/questionnaire/report.php\" method=\"GET\">\n";
        echo "<input type=\"hidden\" name=\"instance\" value=\"$instance\" />\n";
        echo "<input type=\"hidden\" name=\"user\" value=\"$user\" />\n";
        echo "<input type=\"hidden\" name=\"sid\" value=\"$sid\" />\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"dcsv\" />\n";
        echo html_writer::checkbox('choicecodes', 1, true, get_string('includechoicecodes', 'questionnaire'));
        echo "<br />\n";
        echo html_writer::checkbox('choicetext', 1, true, get_string('includechoicetext', 'questionnaire'));
        echo "<br />\n";
        echo "<br />\n";
        echo "<input type=\"submit\" name=\"submit\" value=\"".get_string('download', 'questionnaire')."\" />\n";
        echo "</form>\n";
        echo $OUTPUT->box_end();

        echo $OUTPUT->footer('none');
        exit();
        break;

    case 'dcsv': // Download responses data as text (cvs) format.

        require_capability('mod/questionnaire:downloadresponses', $context);

        // Use the questionnaire name as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($questionnaire->name, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", trim($name));

            $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
            $choicetext  = optional_param('choicetext', '0', PARAM_INT);
            $output = $questionnaire->generate_csv('', $user, $choicecodes, $choicetext);
            // CSV
            // SEP. 2007 JR changed file extension to *.txt for non-English Excel users' sake
            // and changed separator to tabulation
            // JAN. 2008 added \r carriage return for better Windows implementation.
            header("Content-Disposition: attachment; filename=$name.txt");
            header("Content-Type: text/comma-separated-values");
        foreach ($output as $row) {
            $text = implode("\t", $row);
            echo $text."\r\n";
        }
        exit();
        break;

    case 'vall':         // View all responses.
    case 'vallasort':    // View all responses sorted in ascending order.
    case 'vallarsort':   // View all responses sorted in descending order.

        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();
        if (!$questionnaire->capabilities->readallresponses && !$questionnaire->capabilities->readallresponseanytime) {

            // Should never happen, unless called directly by a snoop.
            print_error('nopermissions', '', '', get_string('viewallresponses', 'questionnaire'));

            // Finish the page.
            echo $OUTPUT->footer($course);
            break;
        }

        // Print the tabs.
        switch ($action) {
            case 'vallasort':
                $SESSION->questionnaire->current_tab = 'vallasort';
                break;
            case 'vallarsort':
                $SESSION->questionnaire->current_tab = 'vallarsort';
                break;
            default:
                $SESSION->questionnaire->current_tab = 'valldefault';
        }

        $SESSION->questionnaire->currentsessiongroupid = $currentgroupid;
        include('tabs.php');

        echo ('<br />');

        // Enable choose_group if there are questionnaire groups and groupmode is not set to "no groups"
        // and if there are more goups than 1 (or if user can view all groups).
        $SESSION->questionnaire->currentgroupid = $currentgroupid;
        if (is_array($questionnairegroups) && $groupmode > 0 && $groupscount > 1 - $questionnaire->canviewallgroups) {
            require_once($CFG->dirroot.'/mod/questionnaire/choose_group_form.php');
            $choosegroupform = new questionnaire_choose_group_form();
            $choosegroupform->set_questionnairedata(array('groups'=>$questionnairegroups,
                 'currentgroupid'=>$currentgroupid, 'groupmode'=>$groupmode, 'canviewallgroups'=>$questionnaire->canviewallgroups));
            $choosegroupform->set_form_elements();
            $choosegroupform->display();
        } else {
            echo ('<br />');
        }
        if ($currentgroupid > 0) {
            $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
        } else {
            switch ($currentgroupid) {
                case '0':
                    $groupname = '<p><strong>'.get_string('groupmembersonlyerror', 'group').'</strong></p>';
                    break;
                case '-1':
                    $groupname = '<strong>'.get_string('allparticipants').'</strong>';
                    break;
                case '-2':
                    $groupname = '<strong>'.get_string('allgroups').'</strong>';
                    break;
                case '-3':
                    $groupname = '<strong>'.get_string('groupnonmembers').'</strong>';
                    break;
            }
        }
        echo'<div class = "generalbox">';
        echo (get_string('viewallresponses', 'questionnaire').'. '.$groupname.'. ');
        $strsort = get_string('order_'.$sort, 'questionnaire');
        echo $strsort;
        echo $OUTPUT->help_icon('orderresponses', 'questionnaire');

        $ret = $questionnaire->survey_results(1, 1, '', '', '', $uid=false, $currentgroupid, $sort);
        echo '</div>';

        // Finish the page.
        echo $OUTPUT->footer($course);
        break;

    case 'vresp': // View by response.

    default:
        if (empty($questionnaire->survey)) {
            print_error('surveynotexists', 'questionnaire');
        } else if ($questionnaire->survey->owner != $course->id) {
            print_error('surveyowner', 'questionnaire');
        }
        $ruser = false;
        $noresponses = false;

        if ($byresponse || $rid) {

            // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
            $groupid = $currentsessiongroupid;
            if ($groupmode > 0) {
                switch ($groupid) {
                    case -1:     // All participants.
                        $resps = $respsallparticipants;
                        break;
                    case -2:     // All members of any group.
                        $resps = $respsallgroupmembers;
                            break;
                    case -3:     // Not members of any group.
                        $resps = $respsnongroupmembers;
                            break;
                    default:     // Members of a specific group.
                        $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                            FROM {questionnaire_response} R,
                                {groups_members} GM
                             WHERE R.survey_id= ? AND
                               R.complete='y' AND
                               GM.groupid= ? AND ".$castsql."=GM.userid
                              ORDER BY R.id";
                        if (!($resps = $DB->get_records_sql($sql, array($sid, $groupid)))) {
                            $resps = array();
                        }
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else {
                    if ($rid === false) {
                        $resp = current($resps);
                        $rid = $resp->id;
                    } else {
                        $resp = $DB->get_record('questionnaire_response', array('id' => $rid));
                    }
                    if (is_numeric($resp->username)) {
                        if ($user = $DB->get_record('user', array('id' => $resp->username))) {
                            $ruser = fullname($user);
                        } else {
                            $ruser = '- '.get_string('unknown', 'questionnaire').' -';
                        }
                    } else {
                        $ruser = $resp->username;
                    }
                }
            } else {
                $resps = $respsallparticipants;
            }
        }
        $rids = array_keys($resps);
        if (!$rid) {
            $rid = $rids[0];
        }

        // Print the page header.
        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();

        // Print the tabs.
        if ($byresponse) {
            $SESSION->questionnaire->current_tab = 'vrespsummary';
        }
        if ($individualresponse) {
            $SESSION->questionnaire->current_tab = 'individualresp';
        }
        include('tabs.php');

        // Print the main part of the page.
        // TODO provide option to select how many columns and/or responses per page.

        echo $OUTPUT->box_start();

        if ($groupid === 0) {    // Should not happen.
            $groupname = '<strong>'.get_string('groupmembersonlyerror', 'group').'</strong>';
            echo (get_string('viewindividualresponse', 'questionnaire').'. '.$groupname.'. ');
        } else if ($noresponses) {
            echo (get_string('group').' <strong>'.groups_get_group_name($groupid).'</strong>: '.
                get_string('noresponses', 'questionnaire'));
        } else {
            $groupname = get_string('group').': <strong>'.groups_get_group_name($groupid).'</strong>';
            if ($groupid == -1 ) {
                $groupname = get_string('allparticipants');
            }
            // No need to display names list if questionnaire is anonymous!
            if ($questionnaire->respondenttype != 'anonymous') {
                if ($byresponse) {
                    echo $OUTPUT->box_start();
                    echo $OUTPUT->help_icon('viewindividualresponse', 'questionnaire').'&nbsp;';
                    echo (get_string('viewindividualresponse', 'questionnaire').' <strong> : '.$groupname.'</strong>');
                    echo $OUTPUT->box_end();
                }
                $questionnaire->survey_results_navbar_alpha($rid, $groupid, $cm, $byresponse);
                if (!$byresponse) { // Show respondents individual responses.
                    $questionnaire->view_response($rid);
                }
            }
        }
        echo $OUTPUT->box_end();

        // Finish the page.
        echo $OUTPUT->footer($course);
        break;
}