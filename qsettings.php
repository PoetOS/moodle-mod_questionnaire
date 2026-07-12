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
 * This page handles the question settings.
 *
 * @package    mod_questionnaire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 */

require_once("../../config.php");

use mod_questionnaire\questionnaire;
use mod_questionnaire\survey_settings_controller;
use mod_questionnaire\output\qsettingspage;

$id = required_param('id', PARAM_INT);    // Course module ID.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$cancel = optional_param('cancel', '', PARAM_ALPHA);
$submitbutton2 = optional_param('submitbutton2', '', PARAM_ALPHA);

if (! $cmrecord = get_coursemodule_from_id('questionnaire', $id)) {
    throw new \moodle_exception('invalidcoursemodule', 'mod_questionnaire');
}
$cm = \cm_info::create($cmrecord);

if (! $course = $DB->get_record("course", ["id" => $cm->course])) {
    throw new \moodle_exception('coursemisconf', 'mod_questionnaire');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot . '/mod/questionnaire/qsettings.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$questionnaire = questionnaire::from_cm($cm);

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = new qsettingspage();

if (!$questionnaire->capabilities()->can_manage_questionnaire()) {
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

$settingsform = new \mod_questionnaire\settings_form('qsettings.php');
$sdata = $questionnaire->survey()->to_stdclass();
$sdata->sid = $questionnaire->surveyid();
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('info');
$currentinfo = file_prepare_draft_area(
    $draftideditor,
    $context->id,
    'mod_questionnaire',
    'info',
    $sdata->sid,
    ['subdirs' => true],
    $questionnaire->survey()->info()
);
$sdata->info = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$draftideditor = file_get_submitted_draft_itemid('thankbody');
$currentinfo = file_prepare_draft_area(
    $draftideditor,
    $context->id,
    'mod_questionnaire',
    'thankbody',
    $sdata->sid,
    ['subdirs' => true],
    $questionnaire->survey()->thankbody()
);
$sdata->thankbody = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$settingsform->set_data($sdata);

if ($settingsform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/questionnaire/view.php?id=' . $questionnaire->coursemodule()->id, '');
}

if ($settings = $settingsform->get_data()) {
    (new survey_settings_controller($questionnaire))->save($settings);

    if ($submitbutton2) {
        $redirecturl = course_get_url($cm->course);
    } else {
        $redirecturl = $CFG->wwwroot . '/mod/questionnaire/view.php?id=' . $questionnaire->coursemodule()->id;
    }

    // Save current advanced settings only.
    if (isset($settings->submitbutton) || isset($settings->submitbutton2)) {
        redirect($redirecturl, get_string('settingssaved', 'questionnaire'));
    }
}

// Print the page header.
$PAGE->set_title(get_string('editingquestionnaire', 'questionnaire'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingquestionnaire', 'questionnaire'));
echo $renderer->header();
$page->add_to_page('formarea', $settingsform->render());
echo $renderer->render($page);
echo $renderer->footer($course);
