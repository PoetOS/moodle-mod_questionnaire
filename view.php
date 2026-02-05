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

use mod_questionnaire\questionnaire;

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

global $PAGE, $USER;

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

// Check login.
require_course_login($questionnaire->courseid(), true, $questionnaire->coursemodule());


$url = new \moodle_url('/mod/questionnaire/view.php');
if (isset($cmid)) {
    $url->param('id', $cmid);
} else {
    $url->param('a', $qid);
}
if (isset($sid)) {
    $url->param('sid', $sid);
}

// Log this course module view.
// Needed for the event logging.
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

// Get the renderer and page objects for display use.
$output = $PAGE->get_renderer('mod_questionnaire');
$page = new \mod_questionnaire\output\viewpage($questionnaire);

echo $output->header();
echo $output->render($page);
echo $output->footer($questionnaire->course());
