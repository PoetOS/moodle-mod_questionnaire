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

// This page prints a particular instance of questionnaire.

require_once("../../config.php");
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$SESSION->questionnaire->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // questionnaire ID.

$sid = optional_param('sid', null, PARAM_INT);  // Survey id.

if ($id) {
    if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $questionnaire->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("questionnaire", $questionnaire->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

// Check login and get context.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/questionnaire:view', $context);

$url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/complete.php');
if (isset($id)) {
    $url->param('id', $id);
} else {
    $url->param('a', $a);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);
add_to_log($course->id, "questionnaire", "view", "view.php?id=$cm->id", "$questionnaire->name", $cm->id, $USER->id);
$questionnaire->strquestionnaires = get_string("modulenameplural", "questionnaire");
$questionnaire->strquestionnaire  = get_string("modulename", "questionnaire");

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);
$questionnaire->view();
