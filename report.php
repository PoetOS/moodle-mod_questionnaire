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
 * The main report page for a questionnaire.
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once("../../config.php");

use mod_questionnaire\questionnaire;
use mod_questionnaire\report_actions;
use mod_questionnaire\report_downloader;
use mod_questionnaire\report_viewer;
use mod_questionnaire\output\reportpage;
use mod_questionnaire\output\reportpagepdf;
use mod_questionnaire\output\responsepagepdf;

$instance = required_param('instance', PARAM_INT);   // Questionnaire ID.
$action = optional_param('action', 'vall', PARAM_ALPHA);
$sid = optional_param('sid', null, PARAM_INT);              // Survey id.
$rid = optional_param('rid', false, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$byresponse = optional_param('byresponse', false, PARAM_INT);
$individualresponse = optional_param('individualresponse', false, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$user = optional_param('user', '', PARAM_INT);
$outputtarget = optional_param('target', 'html', PARAM_ALPHA); // Default 'html'. Could be 'pdf'.
$userview = optional_param('responsestats', '0', PARAM_ALPHANUM);

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

$usergraph = get_config('questionnaire', 'usergraph');

$questionnaire = questionnaire::from_instanceid($instance);
$course = $questionnaire->course();
$cm = $questionnaire->coursemodule();

require_course_login($course, true, $cm);

$renderer = $PAGE->get_renderer('mod_questionnaire');
if ($outputtarget == 'pdf') {
    $page = ($action == 'vresp') ? new responsepagepdf() : new reportpagepdf();
} else { // Default to HTML.
    $page = new reportpage();
}

// If you can't view the questionnaire, or can't view a specified response, error out.
$context = $questionnaire->context();
if (!$questionnaire->capabilities()->can_view_all_responses(null, true) && !$individualresponse) {
    // Should never happen, unless called directly by a snoop...
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

$sid = $questionnaire->surveyid();

$url = new moodle_url($CFG->wwwroot . '/mod/questionnaire/report.php');
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
if ($outputtarget == 'print') {
    $PAGE->set_pagelayout('popup');
    $PAGE->requires->js_init_call('M.mod_questionnaire.init_printing');
}

// Tab setup.
if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}

// Get all responses for further use in viewbyresp and deleteall etc.
// All participants.
$respsallparticipants = $questionnaire->get_responses();
$SESSION->questionnaire->numrespsallparticipants = count($respsallparticipants);
$SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numrespsallparticipants;

// Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
$groupmode = groups_get_activity_groupmode($cm, $course);
$questionnairegroups = '';
$groupscount = 0;
$SESSION->questionnaire->respscount = 0;

if ($groupmode > 0) {
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
$responsestatus = [
        'y' => get_string('fullsubmissions', 'questionnaire'),
        '0' => get_string('allresponses', 'questionnaire'),
        'n' => get_string('responsesnotsubmitted', 'questionnaire'),
];
// Set default userview to all responses.
$userview = array_key_exists($userview, $responsestatus) ? $userview : '0';

switch ($action) {
    case 'dresp':  // Delete individual response? Ask for confirmation.
        (new report_actions($questionnaire, $renderer))
            ->confirm_delete_response($page, (int)$rid, $currentgroupid);
        break;

    case 'delallresp': // Delete all responses? Ask for confirmation.
        (new report_actions($questionnaire, $renderer))
            ->confirm_delete_all_responses($page, $groupmode, $currentgroupid, $groupname ?? '', $respsallparticipants);
        break;

    case 'dvresp': // Delete single response. Do it!
        (new report_actions($questionnaire, $renderer))->delete_response((int)$rid);
        break;

    case 'dvallresp': // Delete all responses in questionnaire (or group). Do it!
        (new report_actions($questionnaire, $renderer))
            ->delete_all_responses($groupmode, $currentgroupid, $respsallparticipants);
        break;

    case 'dwnpg': // Download page options.
        (new report_downloader($questionnaire, $renderer))
            ->show_download_options($page, $groupmode, $currentgroupid, $questionnairegroups ?: [], (int)$user);
        break;

    case 'dfs':
        (new report_downloader($questionnaire, $renderer))
            ->download_responses($page, $currentgroupid, (int)$user);
        break;

    case 'vall':         // View all responses.
    case 'vallasort':    // View all responses sorted in ascending order.
    case 'vallarsort':   // View all responses sorted in descending order.
        (new report_viewer($questionnaire, $renderer))->view_all_responses(
            $page,
            $action,
            $outputtarget,
            $url,
            $groupmode,
            $currentgroupid,
            $questionnairegroups ?: [],
            $respsallparticipants,
            $sort,
            $userview,
            $responsestatus,
            (bool)$usergraph
        );
        break;

    case 'vresp': // View by response.
    default:
        (new report_viewer($questionnaire, $renderer))->view_individual_response(
            $page,
            $outputtarget,
            $url,
            $groupmode,
            $currentgroupid,
            $respsallparticipants,
            $rid,
            (bool)$byresponse,
            (bool)$individualresponse,
            (bool)$usergraph,
            $userview,
            $responsestatus
        );
        break;
}
