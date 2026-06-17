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

/**
 * Manage feedback settings.
 *
 * @package mod_questionnaire
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Joseph Rezeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once("../../config.php");

use mod_questionnaire\questionnaire;
use mod_questionnaire\feedback_settings_controller;
use mod_questionnaire\output\feedbackpage;

$id = required_param('id', PARAM_INT);    // Course module ID.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$action = optional_param('action', '', PARAM_ALPHA);

$questionnaire = questionnaire::from_cmid($id);

// Needed here for forced language courses.
require_course_login($questionnaire->course(), true, $questionnaire->coursemodule());

$PAGE->set_url(new moodle_url('/mod/questionnaire/feedback.php', ['id' => $id]));
$PAGE->set_context($questionnaire->context());

if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = new feedbackpage();

$SESSION->questionnaire->current_tab = 'feedback';

if (!$questionnaire->capabilities()->can_edit_questions()) {
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

$feedbackform = new \mod_questionnaire\feedback_form('feedback.php');
$sdata = $questionnaire->survey()->to_stdclass();
$sdata->sid = $questionnaire->surveyid();
$sdata->id = $questionnaire->coursemodule()->id;

$draftideditor = file_get_submitted_draft_itemid('feedbacknotes');
$currentinfo = file_prepare_draft_area(
    $draftideditor,
    $questionnaire->context()->id,
    'mod_questionnaire',
    'feedbacknotes',
    $sdata->sid,
    ['subdirs' => true],
    $questionnaire->survey()->feedbacknotes()
);
$sdata->feedbacknotes = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url('/mod/questionnaire/view.php', ['id' => $questionnaire->coursemodule()->id]));
}
// Confirm that feedback can be used for this questionnaire...
// Get all questions that are valid feedback questions.
$validquestions = false;
foreach ($questionnaire->questions() as $question) {
    if ($question->valid_feedback()) {
        $validquestions = true;
        break;
    }
}

if ($settings = $feedbackform->get_data()) {
    $controller = new feedback_settings_controller($questionnaire);
    if (isset($settings->feedbacksettingsbutton1) || isset($settings->feedbacksettingsbutton2) || isset($settings->buttongroup)) {
        $controller->save($settings);
    }

    // Handle the edit feedback sections action.
    if (isset($settings->buttongroup['feedbackeditbutton'])) {
        $firstsection = $controller->ensure_first_section();
        redirect(new moodle_url(
            '/mod/questionnaire/fbsections.php',
            ['id' => $questionnaire->coursemodule()->id, 'section' => $firstsection]
        ));
    }
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'questionnaire'));
$PAGE->set_heading(format_string($questionnaire->course()->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'questionnaire'));
echo $renderer->header();
require('tabs.php');
if (!$validquestions) {
    $page->add_to_page('formarea', get_string('feedbackoptions_help', 'questionnaire'));
} else {
    $page->add_to_page('formarea', $feedbackform->render());
}
echo $renderer->render($page);
echo $renderer->footer($questionnaire->course());
