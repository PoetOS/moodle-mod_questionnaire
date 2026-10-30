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
use mod_questionnaire\local\feedback\feedback_settings_controller;
use mod_questionnaire\local\feedback\section;
use mod_questionnaire\output\feedback_overview;
use mod_questionnaire\output\page;

$id = required_param('id', PARAM_INT);    // Course module ID.
$action = optional_param('action', '', PARAM_ALPHA);
$sectionid = optional_param('sectionid', 0, PARAM_INT);

$questionnaire = questionnaire::from_cmid($id);

// Needed here for forced language courses.
require_course_login($questionnaire->course(), true, $questionnaire->coursemodule());

$pageurl = new moodle_url('/mod/questionnaire/feedback.php', ['id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_context($questionnaire->context());

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = page::settings();

if (!$questionnaire->capabilities()->can_edit_survey_questions()) {
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

if ($action == 'addsection') {
    require_sesskey();
    $newsection = section::new_section($questionnaire->surveyid(), optional_param('sectionlabel', '', PARAM_TEXT));
    redirect(new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $id, 'sectionid' => $newsection->id]));
} else if ($action == 'deletesection') {
    require_sesskey();
    $questionnaire->feedback()->section($sectionid)->delete();
    redirect($pageurl, get_string('feedbacksectiondeleted', 'questionnaire'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$feedbackform = new \mod_questionnaire\local\feedback\feedback_form('feedback.php', ['questionnaire' => $questionnaire]);
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
    \mod_questionnaire\local\trusttext::for_edit(
        $questionnaire->feedback()->notes(),
        $questionnaire->feedback()->notes_trust(),
        $questionnaire->context()
    )
);
$sdata->feedbacknotes = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url('/mod/questionnaire/view.php', ['id' => $questionnaire->coursemodule()->id]));
}

if ($settings = $feedbackform->get_data()) {
    $controller = new feedback_settings_controller($questionnaire);
    $controller->save($settings);
    $controller->ensure_first_section();
    redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'questionnaire'));
$PAGE->set_heading(format_string($questionnaire->course()->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'questionnaire'));
echo $renderer->header();
if ($action == 'confirmdeletesection') {
    $feedbacksection = $questionnaire->feedback()->section($sectionid);
    $message = get_string('confirmdeletesection', 'questionnaire', format_string($feedbacksection->sectionlabel));
    $urlyes = new moodle_url($pageurl, ['action' => 'deletesection', 'sectionid' => $sectionid]);
    $page->add_to_page('formarea', $renderer->confirm($message, new single_button($urlyes, get_string('yes')), $pageurl));
} else {
    if ($questionnaire->feedback()->has_any_feedback_questions()) {
        $page->add_to_page('formarea', $feedbackform->render());
    }
    $page->add_to_page('formarea', $renderer->render(new feedback_overview($questionnaire)));
}
echo $renderer->render($page);
echo $renderer->footer($questionnaire->course());
