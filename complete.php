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
 * This page prints a particular instance of questionnaire.
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once("../../config.php");
require_once($CFG->libdir . '/completionlib.php');

use mod_questionnaire\questionnaire;
use mod_questionnaire\output\completepage;

if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$SESSION->questionnaire->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // Questionnaire ID.
$resume = optional_param('resume', null, PARAM_INT);    // Is this attempt a resume of a saved attempt?

if (!empty($id)) {
    $questionnaire = questionnaire::from_cmid($id);
} else {
    $questionnaire = questionnaire::from_instanceid($a);
}

// Check login and get context.
require_course_login($questionnaire->course(), true, $questionnaire->coursemodule());
require_capability('mod/questionnaire:view', $questionnaire->context());

$url = new moodle_url('/mod/questionnaire/complete.php');
if (isset($id)) {
    $url->param('id', $id);
} else {
    $url->param('a', $a);
}

$PAGE->set_url($url);
$PAGE->set_context($questionnaire->context());
$PAGE->set_title(format_string($questionnaire->name()));
$PAGE->set_heading(format_string($questionnaire->course()->fullname));

// Add renderer and page objects to the questionnaire object for display use.
$questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
$questionnaire->add_page(new completepage());

// Mark as viewed.
$completion = new completion_info($questionnaire->course());
$completion->set_module_viewed($questionnaire->coursemodule());

if ($resume) {
    $anonymous = $questionnaire->is_anonymous();

    $event = \mod_questionnaire\event\attempt_resumed::create([
        'objectid' => $questionnaire->id(),
        'anonymous' => $anonymous,
        'context' => context_module::instance($questionnaire->coursemodule()->id),
    ]);
    $event->trigger();
}

// Generate the view HTML in the page.
$questionnaire->view();

// Output the page.
echo $questionnaire->renderer->header();
echo $questionnaire->renderer->render($questionnaire->page);
echo $questionnaire->renderer->footer($questionnaire->course());
