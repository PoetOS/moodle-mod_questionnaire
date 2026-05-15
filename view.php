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
 * This main view page for a questionnaire.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once("../../config.php");
require_once($CFG->libdir . '/completionlib.php');

use mod_questionnaire\questionnaire;
use mod_questionnaire\output\viewpage;

if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$SESSION->questionnaire->current_tab = 'view';

$cmid = optional_param('id', null, PARAM_INT);    // Course Module ID.
$qid = optional_param('a', null, PARAM_INT);      // Or questionnaire ID.
$sid = optional_param('sid', null, PARAM_INT);  // Survey id.
if (!empty($cmid)) {
    $questionnaire = questionnaire::from_cmid($cmid);
} else {
    $questionnaire = questionnaire::from_instanceid($qid);
}

// Check login and get context.
require_course_login($questionnaire->courseid(), true, $questionnaire->coursemodule());

$url = new moodle_url($CFG->wwwroot . '/mod/questionnaire/view.php');
if (isset($cmid)) {
    $url->param('id', $cmid);
} else {
    $url->param('a', $qid);
}
if (isset($sid)) {
    $url->param('sid', $sid);
}

// Log this course module view.
$anonymous = $questionnaire->is_anonymous();
$event = \mod_questionnaire\event\course_module_viewed::create(
    [
        'objectid' => $questionnaire->id(),
        'anonymous' => $anonymous,
        'context' => $questionnaire->context(),
    ]
);
$event->trigger();

$PAGE->set_url($url);
$PAGE->set_context($questionnaire->context());
$PAGE->set_title(format_string($questionnaire->name()));
$PAGE->set_heading(format_string($questionnaire->course()->fullname));

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = new viewpage();

// No need to print out intro or name in Moodle 4 and above.
$cm = $questionnaire->coursemodule();
$currentgroupid = groups_get_activity_group($cm);
if (!groups_is_member($currentgroupid, $USER->id)) {
    $currentgroupid = 0;
}

$message = $questionnaire->user_access_messages($USER->id);
if ($message !== null) {
    $page->add_to_page('message', $message);
} else if ($questionnaire->capabilities()->user_can_take($USER->id)) {
    if ($questionnaire->questions()) { // Sanity check.
        if (!$questionnaire->user_has_saved_response($USER->id)) {
            $page->add_to_page(
                'complete',
                '<a href="' . $CFG->wwwroot .
                    htmlspecialchars('/mod/questionnaire/complete.php?' . 'id=' . $questionnaire->coursemodule()->id) .
                    '" class="btn btn-primary">' . get_string('answerquestions', 'questionnaire') . '</a>'
            );
        } else {
            $resumesurvey = get_string('resumesurvey', 'questionnaire');
            $page->add_to_page(
                'complete',
                '<a href="' .
                    $CFG->wwwroot . htmlspecialchars(
                        '/mod/questionnaire/complete.php?' . 'id=' . $questionnaire->coursemodule()->id . '&resume=1'
                    ) .
                    '" title="' . $resumesurvey . '" class="btn btn-primary">' . $resumesurvey . '</a>'
            );
        }
    } else {
        $page->add_to_page('message', get_string('noneinuse', 'questionnaire'));
    }
}

if ($questionnaire->capabilities()->can_edit_questions() && !$questionnaire->questions() && $questionnaire->is_active()) {
    $page->add_to_page(
        'complete',
        '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/questions.php?' .
            'id=' . $questionnaire->coursemodule()->id) . '" class="btn btn-primary">' .
            get_string('addquestions', 'questionnaire') . '</a>'
    );
}

// Time zone message (if required).
if ($message === null && $questionnaire->is_open() && !$questionnaire->is_closed()) {
    $info = $questionnaire->view_information();
    $page->add_to_page('info', $questionnaire->access_messages($info));
}

if (isguestuser()) {
    $guestno = html_writer::tag('p', get_string('noteligible', 'questionnaire'));
    $liketologin = html_writer::tag('p', get_string('liketologin'));
    $page->add_to_page(
        'guestuser',
        $renderer->confirm($guestno . "\n\n" . $liketologin . "\n", get_login_url(), get_local_referer(false))
    );
}

$usernumresp = $questionnaire->count_submissions($USER->id);

if ($questionnaire->capabilities()->can_read_own_responses() && ($usernumresp > 0)) {
    $argstr = 'instance=' . $questionnaire->id() . '&user=' . $USER->id;
    if ($usernumresp > 1) {
        $titletext = get_string('viewyourresponses', 'questionnaire', $usernumresp);
    } else {
        $titletext = get_string('yourresponse', 'questionnaire');
        $argstr .= '&byresponse=1&action=vresp';
    }
    $page->add_to_page(
        'yourresponse',
        '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/myreport.php?' . $argstr) .
            '" class="btn btn-primary">' . $titletext . '</a>'
    );
}

if ($questionnaire->capabilities()->can_view_all_responses($usernumresp)) {
    $argstr = 'instance=' . $questionnaire->id() . '&group=' . $currentgroupid;
    $page->add_to_page(
        'allresponses',
        '<a href="' . $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr) .
            '" class="btn btn-primary">' . get_string('viewallresponses', 'questionnaire') . '</a>'
    );
}

echo $renderer->header();
echo $renderer->render($page);
echo $renderer->footer();
