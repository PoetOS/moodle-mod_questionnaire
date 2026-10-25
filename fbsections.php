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
 * Manage feedback sections.
 *
 * @package mod_questionnaire
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Joseph Rezeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once("../../config.php");

use mod_questionnaire\questionnaire;
use mod_questionnaire\local\feedback\feedback_section_controller;
use mod_questionnaire\local\feedback\feedback_section_form;
use mod_questionnaire\output\page;

$id = required_param('id', PARAM_INT);    // Course module ID.
$sectionid = optional_param('sectionid', 0, PARAM_INT);

$questionnaire = questionnaire::from_cmid($id);

// Needed here for forced language courses.
require_course_login($questionnaire->course(), true, $questionnaire->coursemodule());

$PAGE->set_url(new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $id, 'sectionid' => $sectionid]));
$PAGE->set_context($questionnaire->context());

if (!$questionnaire->capabilities()->can_edit_questions()) {
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

$overviewurl = new moodle_url('/mod/questionnaire/feedback.php', ['id' => $id]);
if ($sectionid) {
    $feedbacksection = $questionnaire->feedback()->section($sectionid);
} else {
    // Without a section id, edit the first section; sections are only created from the feedback page.
    $sections = $questionnaire->feedback()->sections();
    if (empty($sections)) {
        redirect($overviewurl);
    }
    $feedbacksection = reset($sections);
}

$feedbackform = new feedback_section_form(
    new moodle_url('/mod/questionnaire/fbsections.php'),
    ['questionnaire' => $questionnaire, 'section' => $feedbacksection]
);

$draftideditor = file_get_submitted_draft_itemid('sectionheading');
$currentinfo = file_prepare_draft_area(
    $draftideditor,
    $questionnaire->context()->id,
    'mod_questionnaire',
    'sectionheading',
    $feedbacksection->id,
    ['subdirs' => true],
    $feedbacksection->sectionheading
);
$feedbackform->set_data([
    'id' => $id,
    'sectionid' => $feedbacksection->id,
    'sectionlabel' => $feedbacksection->sectionlabel,
    'sectionheading' => ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor],
]);

if ($feedbackform->is_cancelled()) {
    redirect($overviewurl);
}
if ($formdata = $feedbackform->get_data()) {
    (new feedback_section_controller($questionnaire, $feedbacksection))->save($formdata);
    redirect($overviewurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = page::settings();
$PAGE->set_title(get_string('editingfeedback', 'questionnaire'));
$PAGE->set_heading(format_string($questionnaire->course()->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'questionnaire'));
echo $renderer->header();
$page->add_to_page('formarea', $feedbackform->render());
echo $renderer->render($page);
echo $renderer->footer($questionnaire->course());
