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
use mod_questionnaire\output\pdf_factory;
use mod_questionnaire\output\reportpage;
use mod_questionnaire\output\reportpagepdf;
use mod_questionnaire\output\responsepagepdf;

$instance = optional_param('instance', false, PARAM_INT);   // Questionnaire ID.
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

if ($instance === false) {
    if (!empty($SESSION->instance)) {
        $instance = $SESSION->instance;
    } else {
        throw new \moodle_exception('requiredparameter', 'mod_questionnaire');
    }
}
$SESSION->instance = $instance;
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
$SESSION->questionnaire->current_tab = 'allreport';

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
$SESSION->questionnaire_surveyid = $sid;

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
if ($usergraph) {
    $charttype = $questionnaire->survey()->charttype();
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
        require_capability('mod/questionnaire:downloadresponses', $context);

        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $renderer->header();

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
                    $groupname = get_string('membersofselectedgroup', 'group') . ' ' .
                        get_string('group') . ' ' . $questionnairegroups[$currentgroupid]->name;
            }
        }
        $output = '';
        $output .= "<br /><br />\n";
        $output .= html_writer::tag('h2', (get_string('downloadtextformat', 'questionnaire'))
                . ':&nbsp;' . get_string('responses', 'questionnaire') . '&nbsp;' .
                $groupname . $renderer->help_icon('downloadtextformat', 'questionnaire'));
        $output .= $renderer->heading(get_string('textdownloadoptions', 'questionnaire'), 3);
        $output .= $renderer->box_start();
        $downloadparams = [
            'instance' => $instance,
            'user' => $user,
            'sid' => $sid,
            'action' => 'dfs',
            'group' => $currentgroupid,
        ];
        $extrafields = $renderer->render_from_template('mod_questionnaire/extrafields', []);
        $output .= $renderer->download_dataformat_selector(
            get_string('downloadtypes', 'questionnaire'),
            'report.php',
            'downloadformat',
            $downloadparams,
            $extrafields
        );
        $output .= $renderer->box_end();

        $page->add_to_page('respondentinfo', $output);
        echo $renderer->render($page);

        echo $renderer->footer('none');

        // Log saved as text action.
        $params = [
            'objectid' => $questionnaire->id(),
            'context' => $questionnaire->context(),
            'courseid' => $course->id,
            'other' => ['action' => $action, 'instance' => $instance, 'currentgroupid' => $currentgroupid],
        ];
        $event = \mod_questionnaire\event\all_responses_saved_as_text::create($params);
        $event->trigger();

        exit();
        break;

    case 'dfs':
        require_capability('mod/questionnaire:downloadresponses', $context);
        // Use the questionnaire name as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($questionnaire->name(), PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", trim($name));

        $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
        $choicetext = optional_param('choicetext', '0', PARAM_INT);
        $showincompletes = optional_param('complete', '0', PARAM_INT);
        $rankaverages = optional_param('rankaverages', '0', PARAM_INT);
        $dataformat = optional_param('downloadformat', '', PARAM_ALPHA);
        $emailroles = optional_param('emailroles', 0, PARAM_INT);
        $emailextra = optional_param('emailextra', '', PARAM_RAW);

        $output = $questionnaire->reporter($renderer, $page)->generate_csv(
            $currentgroupid,
            '',
            $user,
            $choicecodes,
            $choicetext,
            $showincompletes,
            $rankaverages
        );

        $columns = $output[0];
        unset($output[0]);

        // Check if email report was selected.
        $emailreport = optional_param('emailreport', '', PARAM_ALPHA);
        if (empty($emailreport)) {
            \core\dataformat::download_data($name, $dataformat, $columns, $output);
        } else {
            // Emailreport button selected.
            if (get_config('questionnaire', 'allowemailreporting') && (!empty($emailroles) || !empty($emailextra))) {
                require_once('savefileformat.php');
                $users = !empty($emailroles)
                    ? (new \mod_questionnaire\submission_notifier($questionnaire))->get_notifiable_users($USER->id)
                    : [];
                $otheremails = explode(',', $emailextra);
                if (!empty($users) || !empty($otheremails)) {
                    $thisurl = new moodle_url(
                        'report.php',
                        ['instance' => $instance, 'action' => 'dwnpg', 'group' => $currentgroupid]
                    );
                    save_as_dataformat($name, $dataformat, $columns, $output, $users, $otheremails, $thisurl);
                }
            } else {
                redirect(
                    new moodle_url(
                        'report.php',
                        ['instance' => $instance, 'action' => 'dwnpg', 'group' => $currentgroupid]
                    ),
                    get_string('emailsnotspecified', 'questionnaire')
                );
            }
        }
        exit();
        break;

    case 'vall':         // View all responses.
    case 'vallasort':    // View all responses sorted in ascending order.
    case 'vallarsort':   // View all responses sorted in descending order.
        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        $canviewallresponses = has_capability('mod/questionnaire:readallresponses', $context);
        $canviewallresponsesanytime = has_capability('mod/questionnaire:readallresponseanytime', $context);
        if (!$canviewallresponses && !$canviewallresponsesanytime) {
            echo $renderer->header();
            // Should never happen, unless called directly by a snoop.
            throw new \moodle_exception('nopermissions', 'mod_questionnaire');
            // Finish the page.
            echo $renderer->footer($course);
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
        if ($outputtarget != 'print') {
            include('tabs.php');
        }

        $respinfo = '';
        $resps = [];
        // Enable choose_group if there are questionnaire groups and groupmode is not set to "no groups"
        // and if there are more goups than 1 (or if user can view all groups).
        if (is_array($questionnairegroups) && $groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            // Count number of responses in each group.
            foreach ($questionnairegroups as $group) {
                $respscount = $questionnaire->count_submissions(false, $group->id);
                $thisgroupname = groups_get_group_name($group->id);
                $escapedgroupname = preg_quote($thisgroupname, '/');
                if (!empty($respscount)) {
                    // Add number of responses to name of group in the groups select list.
                    $groupselect = preg_replace(
                        '/\<option value="' . $group->id . '">' . $escapedgroupname . '<\/option>/',
                        '<option value="' . $group->id . '">' . $thisgroupname . ' (' . $respscount . ')</option>',
                        $groupselect
                    );
                } else {
                    // Remove groups with no responses from the groups select list.
                    $groupselect = preg_replace(
                        '/\<option value="' . $group->id . '">' . $escapedgroupname . '<\/option>/',
                        '',
                        $groupselect
                    );
                }
            }
            $respinfo .= isset($groupselect) ? ($groupselect . ' ') : '';
            $currentgroupid = groups_get_activity_group($cm);
        }
        if ($currentgroupid > 0) {
            $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
        } else {
            $groupname = '<strong>' . $responsestatus[$userview] . '</strong>';
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (!($resps = $questionnaire->get_responses(false, $currentgroupid))) {
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
            // NOTE: response_analysis uses $resps to get the id's of the responses only.
            // Need to figure out what this function does.
            $feedbackmessages = $questionnaire->reporter($renderer, $page)
                ->response_analysis(0, $resps, false, false, true, $currentgroupid);

            if ($feedbackmessages) {
                $msgout = '';
                foreach ($feedbackmessages as $msg) {
                    $msgout .= $msg;
                }
                $page->add_to_page('feedbackmessages', $msgout);
            }
        }

        $params = [
            'objectid' => $questionnaire->id(),
            'context' => $context,
            'courseid' => $course->id,
            'other' => ['action' => $action, 'instance' => $instance, 'groupid' => $currentgroupid],
        ];

        if ($outputtarget == 'pdf') {
            $pdf = pdf_factory::create();
            if ($currentgroupid > 0) {
                $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            } else {
                $groupname = '<strong>' . $responsestatus[$userview] . '</strong>';
            }
            $respinfo = get_string('view') . ' ' . $groupname;
            $strsort = get_string('order_' . $sort, 'questionnaire');
            $respinfo .= $strsort;
            $page->add_to_page('respondentinfo', $respinfo);
            $questionnaire->reporter($renderer, $page)->survey_results('', false, true, $currentgroupid, $sort);
            $html = $renderer->render($page);

            // Supress any warnings. There is at least one error in the TCPF library at line 16749 where 'text-align' is
            // not an array.
            $errorreporting = error_reporting(0);
            $pdf->writeHTML($html);
            @$pdf->Output(clean_param($questionnaire->name(), PARAM_FILE) . '.pdf', 'D');
            error_reporting($errorreporting);
        } else { // Default to HTML.
            $event = \mod_questionnaire\event\all_responses_viewed::create($params);
            $event->trigger();

            if ($outputtarget != 'print') {
                $linkname = get_string('downloadpdf', 'mod_questionnaire');
                $link = new moodle_url(
                    '/mod/questionnaire/report.php',
                    [
                        'action' => 'vall',
                        'instance' => $instance,
                        'group' => $currentgroupid,
                        'target' => 'pdf',
                        'responsestats' => $userview,
                    ]
                );
                $downpdficon = new pix_icon('f/pdf', $linkname);
                $respinfo .= $renderer->action_link($link, null, null, null, $downpdficon);

                $linkname = get_string('print', 'mod_questionnaire');
                $link = new \moodle_url(
                    '/mod/questionnaire/report.php',
                    [
                        'action' => 'vall',
                        'instance' => $instance,
                        'group' => $currentgroupid,
                        'target' => 'print',
                        'responsestats' => $userview,
                        ]
                );
                $htmlicon = new pix_icon('t/print', $linkname);
                $options = ['menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
                    'height' => 600, 'width' => 800, 'title' => $linkname];
                $name = 'popup';
                $action = new popup_action('click', $link, $name, $options);
                $class = '';
                $respinfo .= $renderer->action_link(
                    $link,
                    null,
                    $action,
                    ['class' => $class, 'title' => $linkname],
                    $htmlicon
                ) . '&nbsp;';

                $respinfo .= $renderer->viewresponse_print_menu($url->out(), $responsestatus, $userview);
                $strsort = get_string('order_' . $sort, 'questionnaire');
                $respinfo .= $strsort;
                $respinfo .= $renderer->help_icon('orderresponses', 'questionnaire');
                $page->add_to_page('respondentinfo', $respinfo);
            }

            $ret = $questionnaire->reporter($renderer, $page)->survey_results('', false, false, $currentgroupid, $sort);

            echo $renderer->header();
            echo $renderer->render($page);
            echo $renderer->footer($course);
        }
        break;

    case 'vresp': // View by response.
    default:
        if (!$questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        } else if ($questionnaire->survey()->owning_courseid() != $course->id) {
            throw new \moodle_exception('surveyowner', 'mod_questionnaire');
        }
        $noresponses = false;
        if ($usergraph) {
            $charttype = $questionnaire->survey()->charttype();
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

        // Add group filter dropdown.
        if ($groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            $page->add_to_page('respondentinfo', $groupselect);
            $currentgroupid = groups_get_activity_group($cm);
        }

        if ($byresponse || $rid) {
            // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
            if ($groupmode > 0) {
                switch ($currentgroupid) {
                    case 0:     // All participants.
                        $resps = $respsallparticipants;
                        break;
                    default:     // Members of a specific group.
                        $resps = $questionnaire->get_responses(false, $currentgroupid);
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else if ($rid === false) {
                    $rid = current($resps)->id;
                }
            } else {
                $resps = $respsallparticipants;
            }
        }
        $rids = array_keys($resps);
        if (!$rid && !$noresponses) {
            $rid = $rids[0];
        }

        if ($outputtarget == 'pdf') {
            $pdf = pdf_factory::create();
            if ($currentgroupid > 0) {
                $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            } else {
                $groupname = '<strong>' . $responsestatus[$userview] . '</strong>';
            }
            if (!$byresponse) { // Show respondents individual responses.
                $questionnaire->reporter($renderer, $page)
                    ->view_response($rid, '', $resps, true, true, false, $currentgroupid, $outputtarget);
            }
            $html = $renderer->render($page);
            // Supress any warnings. There is at least one error in the TCPF library at line 16749 where 'text-align' is
            // not an array.
            $errorreporting = error_reporting(0);
            $pdf->writeHTML($html);
            @$pdf->Output(clean_param($questionnaire->name(), PARAM_FILE), 'D');
            error_reporting($errorreporting);
        } else { // Default to HTML.
            if ($noresponses) {
                $page->add_to_page(
                    'respondentinfo',
                    get_string('group') . ' <strong>' .
                        groups_get_group_name($currentgroupid) . '</strong>: ' . get_string('noresponses', 'questionnaire')
                );
            }

            // Print the page header.
            $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
            $PAGE->set_heading(format_string($course->fullname));

            // Print the tabs.
            if ($byresponse) {
                $SESSION->questionnaire->current_tab = 'vrespsummary';
            }
            if ($individualresponse) {
                $SESSION->questionnaire->current_tab = 'individualresp';
            }
            if ($outputtarget == 'html') {
                include('tabs.php');
            }

            // Print the main part of the page.
            // TODO provide option to select how many columns and/or responses per page.

            $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            if ($currentgroupid == 0) {
                $groupname = $responsestatus[$userview];
            }
            if ($byresponse) {
                $respinfo = '';
                $respinfo .= $renderer->box_start();
                $respinfo .= $renderer->help_icon('viewindividualresponse', 'questionnaire') . '&nbsp;';
                $respinfo .= get_string('viewindividualresponse', 'questionnaire') . ' <strong> : ' . $groupname . '</strong>';
                $respinfo .= $renderer->box_end();
                $page->add_to_page('respondentinfo', $respinfo);
            }
            if ($outputtarget == 'html') {
                $questionnaire->reporter($renderer, $page)->survey_results_navbar_alpha($rid, $currentgroupid, $byresponse);
            }
            if (!$byresponse) { // Show respondents individual responses.
                $questionnaire->reporter($renderer, $page)
                    ->view_response($rid, '', $resps, true, true, false, $currentgroupid, $outputtarget);
            }
            echo $renderer->header();
            echo $renderer->render($page);
            echo $renderer->footer($course);
        }
        break;
}
