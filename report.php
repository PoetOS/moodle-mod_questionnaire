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
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
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
if ($user) {
    $url->param('user', $user);
}
if ($action == 'dresp') {
    $url->param('action', 'dresp');
    $url->param('byresponse', 1);
    $url->param('rid', $rid);
    $url->param('individualresponse', 1);
}
if ($currentgroupid !== null) {
    $url->param('group', $currentgroupid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);

// Tab setup.
if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$SESSION->questionnaire->current_tab = 'allreport';

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
        if ($groupscount === 0 && $groupmode == 1) {
            $currentgroupid = 0;
        }
        if ($groupmode == 1 && !$questionnaire->canviewallgroups && $currentgroupid == 0) {
            $currentgroupid = $firstgroupid;
        }

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

    if ($currentgroupid > 0) {
        $groupname = get_string('group').' <strong>'.groups_get_group_name($currentgroupid).'</strong>';
    } else {
        $groupname = '<strong>'.get_string('allparticipants').'</strong>';
    }
}
if ($CFG->questionnaire_usergraph) {
    $charttype = $questionnaire->survey->chart_type;
    if ($charttype) {
        $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.common.core.js');

        switch ($charttype) {
            case 'bipolar':
                $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.bipolar.js');
                break;
            case 'hbar':
                $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.hbar.js');
                break;
            case 'radar':
                $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.radar.js');
                break;
            case 'rose':
                $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.rose.js');
                break;
            case 'vprogress':
                $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.vprogress.js');
                break;
        }
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
        $SESSION->questionnaire->current_tab = 'deleteresp';
        include('tabs.php');
        
        $timesubmitted = '<br />'.get_string('submitted', 'questionnaire').'&nbsp;'.userdate($resp->submitted);
        if ($questionnaire->respondenttype == 'anonymous') {
                $ruser = '- '.get_string('anonymous', 'questionnaire').' -';
                $timesubmitted = '';
        }
        
        // Print the confirmation.
        echo '<p>&nbsp;</p>';
        $msg = '<div class="warning centerpara">';
        $msg .= get_string('confirmdelresp', 'questionnaire', $ruser.$timesubmitted);
        $msg .= '</div>';
        $urlyes = new moodle_url('report.php', array('action' => 'dvresp',
                'rid' => $rid, 'individualresponse' => 1, 'instance' => $instance, 'group' => $currentgroupid));
        $urlno = new moodle_url('report.php', array('action' => 'vresp', 'instance' => $instance,
                'rid' => $rid, 'individualresponse' => 1, 'group' => $currentgroupid));
        $buttonyes = new single_button($urlyes, get_string('yes'), 'post');
        $buttonno = new single_button($urlno, get_string('no'), 'get');
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

        $urlyes = new moodle_url('report.php', array('action' => 'dvallresp', 'sid' => $sid,
                         'instance' => $instance, 'group' => $currentgroupid));
        $urlno = new moodle_url('report.php', array('instance' => $instance, 'group' => $currentgroupid));
        $buttonyes = new single_button($urlyes, get_string('yes'), 'post');
        $buttonno = new single_button($urlno, get_string('no'), 'get');

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
        } else if (!($response = $DB->get_record('questionnaire_response', array('id' => $rid)))) {
            print_error('invalidresponserecord', 'questionnaire');
        }

        $ruser = false;
        if (is_numeric($response->username)) {
            if ($user = $DB->get_record('user', array('id' => $response->username))) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'questionnaire').' -';
            }
        } else {
            $ruser = $response->username;
        }

        if (questionnaire_delete_response($response, $questionnaire)) {
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
                $redirection = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;instance='.
                    $instance.'&amp;byresponse=1';
            }

            // Log this questionnaire delete single response action.
            $anonymous = $questionnaire->respondenttype == 'anonymous';

            $params = array(
                            'objectid' => $questionnaire->survey->id,
                            'context' => $questionnaire->context,
                            'courseid' => $questionnaire->course->id,
                            'relateduserid' => $user->id
            );
            $event = \mod_questionnaire\event\response_deleted::create($params);
            $event->trigger();

            redirect($redirection);
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
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                        FROM {questionnaire_response} R,
                            {groups_members} GM
                         WHERE R.survey_id = ? AND
                           R.complete='y' AND
                           GM.groupid = ? AND " . $castsql . "=GM.userid
                        ORDER BY R.id";
                    if (!($resps = $DB->get_records_sql($sql, array($sid, $currentgroupid)))) {
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
                questionnaire_delete_response($response, $questionnaire);
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

            // Log this questionnaire delete all responses action.
            $context = context_module::instance($questionnaire->cm->id);
            $anonymous = $questionnaire->respondenttype == 'anonymous';

            $event = \mod_questionnaire\event\all_responses_deleted::create(array(
                            'objectid' => $questionnaire->id,
                            'anonymous' => $anonymous,
                            'context' => $context
            ));
            $event->trigger();

            redirect($redirection);
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
                case 0:     // All participants.
                    $groupname = get_string('allparticipants');
                    break;
                default:     // Members of a specific group.
                    $groupname = get_string('membersofselectedgroup', 'group').' '.get_string('group').' '.
                        $questionnairegroups[$currentgroupid]->name;
            }
        }
        echo "<br /><br />\n";
        echo $OUTPUT->help_icon('downloadtextformat', 'questionnaire');
        echo '&nbsp;'.(get_string('downloadtext')).':&nbsp;'.get_string('responses', 'questionnaire').'&nbsp;'.$groupname;
        echo $OUTPUT->heading(get_string('textdownloadoptions', 'questionnaire'));
        echo $OUTPUT->box_start();
        echo "<form action=\"{$CFG->wwwroot}/mod/questionnaire/report.php\" method=\"GET\">\n";
        echo "<input type=\"hidden\" name=\"instance\" value=\"$instance\" />\n";
        echo "<input type=\"hidden\" name=\"user\" value=\"$user\" />\n";
        echo "<input type=\"hidden\" name=\"sid\" value=\"$sid\" />\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"dcsv\" />\n";
        echo "<input type=\"hidden\" name=\"group\" value=\"$currentgroupid\" />\n";
        echo html_writer::checkbox('choicecodes', 1, true, get_string('includechoicecodes', 'questionnaire'));
        echo "<br />\n";
        echo html_writer::checkbox('choicetext', 1, true, get_string('includechoicetext', 'questionnaire'));
        echo "<br />\n";
        echo "<br />\n";
        echo "<input type=\"submit\" name=\"submit\" value=\"".get_string('download', 'questionnaire')."\" />\n";
        echo "</form>\n";
        echo $OUTPUT->box_end();

        echo $OUTPUT->footer('none');

        // Log saved as text action.
        $params = array(
                        'objectid' => $questionnaire->id,
                        'context' => $questionnaire->context,
                        'courseid' => $course->id,
                        'other' => array('action' => $action, 'instance' => $instance, 'currentgroupid' => $currentgroupid)
        );
        $event = \mod_questionnaire\event\all_responses_saved_as_text::create($params);
        $event->trigger();

        exit();
        break;

    case 'dcsv': // Download responses data as text (cvs) format.
        require_capability('mod/questionnaire:downloadresponses', $context);

        // Use the questionnaire name as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($questionnaire->name, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", trim($name));

            $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
            $choicetext  = optional_param('choicetext', '0', PARAM_INT);
            $output = $questionnaire->generate_csv('', $user, $choicecodes, $choicetext, $currentgroupid);
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
        include('tabs.php');

        $resps = array();
        // Enable choose_group if there are questionnaire groups and groupmode is not set to "no groups"
        // and if there are more goups than 1 (or if user can view all groups).
        if (is_array($questionnairegroups) && $groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            // Count number of responses in each group.
            $castsql = $DB->sql_cast_char2int('R.username');
            foreach ($questionnairegroups as $group) {
                $sql = "SELECT R.id, GM.id as groupid
                    FROM {questionnaire_response} R, {groups_members} GM
                    WHERE R.survey_id= ? AND
                          R.complete='y' AND
                          GM.groupid= ? AND " . $castsql . "=GM.userid";
                if (!($resps = $DB->get_records_sql($sql, array($sid, $group->id)))) {
                    $resps = array();
                }
                $thisgroupname = groups_get_group_name($group->id);
                $escapedgroupname = preg_quote($thisgroupname, '/');
                if (!empty ($resps)) {
                    // Add number of responses to name of group in the groups select list.
                    $respscount = count($resps);
                    $groupresps[$group->id] = $resps;
                    $groupselect = preg_replace('/\<option value="'.$group->id.'">'.$escapedgroupname.'<\/option>/',
                        '<option value="'.$group->id.'">'.$thisgroupname.' ('.$respscount.')</option>', $groupselect);
                } else {
                    // Remove groups with no responses from the groups select list.
                    $groupselect = preg_replace('/\<option value="'.$group->id.'">'.$escapedgroupname.'<\/option>/', '', $groupselect);
                }
            }
            echo isset($groupselect) ? $groupselect : '';
            $currentgroupid = groups_get_activity_group($cm);
        } else {
            echo ('<br />');
        }
        if ($currentgroupid > 0) {
             $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
        } else {
            $groupname = '<strong>'.get_string('allparticipants').'</strong>';
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (isset($groupresps [$currentgroupid])) {
                        $resps = $groupresps [$currentgroupid];
                    } else {
                        $resps = '';
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            }
        } else {
            $resps = $respsallparticipants;
        }
        if (!empty($resps)) {
            $ret = $questionnaire->response_analysis($rid = 0, $resps, $compare = false,
                            $isgroupmember = false, $allresponses = true, $currentgroupid);
        }

        $params = array(
                        'objectid' => $questionnaire->id,
                        'context' => $context,
                        'courseid' => $course->id,
                        'other' => array('action' => $action, 'instance' => $instance, 'groupid' => $currentgroupid)
        );
        $event = \mod_questionnaire\event\all_responses_viewed::create($params);
        $event->trigger();

        echo'<div class = "generalbox">';
        echo (get_string('viewallresponses', 'questionnaire').'. '.$groupname.'. ');
        $strsort = get_string('order_'.$sort, 'questionnaire');
        echo $strsort;
        echo $OUTPUT->help_icon('orderresponses', 'questionnaire');

        $ret = $questionnaire->survey_results(1, 1, '', '', '', $uid = false, $currentgroupid, $sort);
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
        if ($CFG->questionnaire_usergraph) {
            $charttype = $questionnaire->survey->chart_type;
            if ($charttype) {
                $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.common.core.js');

                switch ($charttype) {
                    case 'bipolar':
                        $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.bipolar.js');
                        break;
                    case 'hbar':
                        $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.hbar.js');
                        break;
                    case 'radar':
                        $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.radar.js');
                        break;
                    case 'rose':
                        $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.rose.js');
                        break;
                    case 'vprogress':
                        $PAGE->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.vprogress.js');
                        break;
                }
            }
        }

        if ($byresponse || $rid) {
            // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
            if ($groupmode > 0) {
                switch ($currentgroupid) {
                    case 0:     // All participants.
                        $resps = $respsallparticipants;
                        break;
                    default:     // Members of a specific group.
                        $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                            FROM {questionnaire_response} R,
                                {groups_members} GM
                             WHERE R.survey_id= ? AND
                               R.complete='y' AND
                               GM.groupid= ? AND ".$castsql."=GM.userid
                              ORDER BY R.id";
                        if (!($resps = $DB->get_records_sql($sql, array($sid, $currentgroupid)))) {
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
        if (!$rid && !$noresponses) {
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

        if ($noresponses) {
            echo (get_string('group').' <strong>'.groups_get_group_name($currentgroupid).'</strong>: '.
                get_string('noresponses', 'questionnaire'));
        } else {
            $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
            if ($currentgroupid == 0 ) {
                $groupname = get_string('allparticipants');
            }
            if ($byresponse) {
                echo $OUTPUT->box_start();
                echo $OUTPUT->help_icon('viewindividualresponse', 'questionnaire').'&nbsp;';
                echo (get_string('viewindividualresponse', 'questionnaire').' <strong> : '.$groupname.'</strong>');
                echo $OUTPUT->box_end();
            }
            $questionnaire->survey_results_navbar_alpha($rid, $currentgroupid, $cm, $byresponse);
            if (!$byresponse) { // Show respondents individual responses.
                $questionnaire->view_response($rid, $referer = '', $blankquestionnaire = false, $resps, $compare = true,
                    $isgroupmember = true, $allresponses = false, $currentgroupid);
            }
        }
        echo $OUTPUT->box_end();

        // Finish the page.
        echo $OUTPUT->footer($course);
        break;
}