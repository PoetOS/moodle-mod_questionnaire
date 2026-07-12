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
 * This page shows results of a questionnaire to a student.
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

use mod_questionnaire\questionnaire;
use mod_questionnaire\output\reportpage;

$instance = required_param('instance', PARAM_INT);   // Questionnaire ID.
$userid = optional_param('user', $USER->id, PARAM_INT);
$rid = optional_param('rid', null, PARAM_INT);
$byresponse = optional_param('byresponse', 0, PARAM_INT);
$action = optional_param('action', 'summary', PARAM_ALPHA);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

$questionnaire = questionnaire::from_instanceid($instance);
$course = $questionnaire->course();
$cm = $questionnaire->coursemodule();

require_course_login($course, true, $cm);

// Should never happen, unless called directly by a snoop...
if (!$questionnaire->capabilities()->can_read_own_responses() || $userid != $USER->id) {
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

$url = new moodle_url($CFG->wwwroot . '/mod/questionnaire/myreport.php', ['instance' => $instance]);
if (isset($userid)) {
    $url->param('userid', $userid);
}
if (isset($byresponse)) {
    $url->param('byresponse', $byresponse);
}
if (isset($currentgroupid)) {
    $url->param('group', $currentgroupid);
}
if (isset($action)) {
    $url->param('action', $action);
}

$PAGE->set_url($url);
$PAGE->set_context($questionnaire->context());
$PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
$PAGE->set_heading(format_string($course->fullname));

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = new reportpage();

$actionbar = \mod_questionnaire\output\report_action_bar::for_myreport(
    $questionnaire,
    $userid,
    $currentgroupid,
    $questionnaire->count_submissions($USER->id),
    $action
);
if ($actionbar->has_content()) {
    $page->add_to_page('actionbar', $renderer->render($actionbar));
}

$sid = $questionnaire->surveyid();
$courseid = $questionnaire->courseid();

switch ($action) {
    case 'summary':
        if (!$questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        }
        $resps = $questionnaire->get_responses($userid);
        $rids = array_keys($resps);
        if (count($resps) > 1) {
            $titletext = get_string('myresponsetitle', 'questionnaire', count($resps));
        } else {
            $titletext = get_string('yourresponse', 'questionnaire');
        }

        // Print the page header.
        echo $renderer->header();

        $page->add_to_page('myheaders', $titletext);
        $questionnaire->reporter($renderer, $page)->survey_results($rids, $USER->id);

        echo $renderer->render($page);

        // Finish the page.
        echo $renderer->footer($course);
        break;

    case 'vall':
        if (!$questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        }
        $questionnaire->reporter($renderer, $page)->add_user_responses($userid);
        $titletext = get_string('myresponses', 'questionnaire');

        // Print the page header.
        echo $renderer->header();

        $page->add_to_page('myheaders', $titletext);
        $questionnaire->reporter($renderer, $page)->view_all_responses();
        echo $renderer->render($page);
        // Finish the page.
        echo $renderer->footer($course);
        break;

    case 'vresp':
        if (!$questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        }
        // The chart_renderer queues RGraph scripts on $PAGE itself when build_scoreboard
        // emits a chart, so no preload needed here.
        $resps = $questionnaire->get_responses($userid);

        // All participants.
        $respsallparticipants = $questionnaire->get_responses();

        $respsuser = $questionnaire->get_responses($userid);

        $iscurrentgroupmember = false;

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode > 0) {
            // Check if current user is member of any group.
            $usergroups = groups_get_user_groups($courseid, $userid);
            $isgroupmember = count($usergroups[0]) > 0;
            // Check if current user is member of current group.
            $iscurrentgroupmember = groups_is_member($currentgroupid, $userid);

            if ($groupmode == 1) {
                $questionnairegroups = groups_get_all_groups($course->id, $userid);
            }
            if ($groupmode == 2 || $questionnaire->capabilities()->can_view_all_groups()) {
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
                if ($groupmode == 1 && !$questionnaire->capabilities()->can_view_all_groups() && $currentgroupid == 0) {
                    $currentgroupid = $firstgroupid;
                }
                // If currentgroup is All Participants, current user is of course member of that "group"!
                if ($currentgroupid == 0) {
                    $iscurrentgroupmember = true;
                }
                // Current group members.
                $currentgroupresps = $questionnaire->get_responses(false, $currentgroupid);
            } else {
                // Groupmode = separate groups but user is not member of any group
                // and does not have moodle/site:accessallgroups capability -> refuse view responses.
                if (!$questionnaire->capabilities()->can_view_all_groups()) {
                    $currentgroupid = 0;
                }
            }

            if ($currentgroupid > 0) {
                $groupname = get_string('group') . ' <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            } else {
                $groupname = '<strong>' . get_string('allparticipants') . '</strong>';
            }
        }

        $rids = array_keys($resps);
        if (!$rid) {
            // If more than one response for this respondent, display most recent response.
            $rid = end($rids);
        }
        $numresp = count($rids);
        if ($numresp > 1) {
            $titletext = get_string('myresponsetitle', 'questionnaire', $numresp);
        } else {
            $titletext = get_string('yourresponse', 'questionnaire');
        }

        $compare = false;
        // Print the page header.
        echo $renderer->header();

        $page->add_to_page('myheaders', $titletext);

        if (count($resps) > 1) {
            $userresps = $resps;
            $questionnaire->reporter($renderer, $page)->survey_results_navbar_student($rid, $userid, $instance, $userresps);
        }
        $resps = [];
        // Determine here which "global" responses should get displayed for comparison with current user.
        // Current user is viewing his own group's results.
        if (isset($currentgroupresps)) {
            $resps = $currentgroupresps;
        }

        // Current user is viewing another group's results so we must add their own results to that group's results.

        if (!$iscurrentgroupmember) {
            $resps += $respsuser;
        }
        // No groups.
        if ($groupmode == 0 || $currentgroupid == 0) {
            $resps = $respsallparticipants;
        }
        $compare = true;
        $questionnaire->reporter($renderer, $page)
            ->view_response($rid, '', $resps, $compare, $iscurrentgroupmember, false, $currentgroupid);
        // Finish the page.
        echo $renderer->render($page);
        echo $renderer->footer($course);
        break;

    case get_string('return', 'questionnaire'):
    default:
        redirect('view.php?id=' . $cm->id);
}
