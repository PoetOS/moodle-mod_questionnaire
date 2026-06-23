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
 * This page displays a non-completable instance of questionnaire.
 *
 * @package    mod_questionnaire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 */

require_once("../../config.php");

use mod_questionnaire\questionnaire as questionnaire_class;

$id = optional_param('id', 0, PARAM_INT);
$sid = optional_param('sid', 0, PARAM_INT);
$popup = optional_param('popup', 0, PARAM_INT);
$qid = optional_param('qid', 0, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

if ($id) {
    // Normal module instance.
    $questionnaire = questionnaire_class::from_cmid($id);
    $course = $questionnaire->course();
    $cm = $questionnaire->coursemodule();
} else {
    // Survey-only (template/public preview from "Add questionnaire" page).
    if (!$survey = $DB->get_record('questionnaire_survey', ['id' => $sid])) {
        throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
    }
    if (!$course = $DB->get_record('course', ['id' => $survey->courseid])) {
        throw new \moodle_exception('coursemisconf', 'mod_questionnaire');
    }
    $cm = !empty($qid) ? get_coursemodule_from_instance('questionnaire', $qid, $course->id) : false;
    if ($cm) {
        $questionnaire = questionnaire_class::from_cmid($cm->id);
    } else {
        $questionnaire = questionnaire_class::from_survey($sid, $course);
    }
}
$canpreview = $questionnaire->capabilities()->can_preview();
$canprintblank = $questionnaire->capabilities()->can_print_blank();

// Check login and get context.
// Do not require login if this questionnaire is viewed from the Add questionnaire page
// to enable teachers to view template or public questionnaires located in a course where they are not enroled.
if (!$popup) {
    require_login($course->id, false, $cm ?: null);
}
$context = $cm ? context_module::instance($cm->id) : false;

$url = new moodle_url('/mod/questionnaire/preview.php');
if ($id !== 0) {
    $url->param('id', $id);
}
if ($sid) {
    $url->param('sid', $sid);
}
$PAGE->set_url($url);
$PAGE->set_context($context ?: context_course::instance($course->id));
if ($cm) {
    $PAGE->set_cm($cm);
}

if (!$canpreview && !$popup) {
    // Should never happen, unless called directly by a snoop...
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

$qp = get_string('preview_questionnaire', 'questionnaire');
$pq = get_string('previewing', 'questionnaire');

// Print the page header.
if ($popup) {
    $PAGE->set_pagelayout('popup');
}
$PAGE->set_title(format_string($qp));
if (!$popup) {
    $PAGE->set_heading(format_string($course->fullname));
}

$PAGE->requires->js('/mod/questionnaire/module.js');

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = new \mod_questionnaire\output\previewpage();

echo $renderer->header();
if (!$popup) {
    (new \mod_questionnaire\output\tabs($questionnaire, 'preview'))->render($page);
}
$page->add_to_page('heading', clean_text($pq));

if ($canprintblank) {
    // Open print friendly as popup window.
    $linkname = '&nbsp;' . get_string('printblank', 'questionnaire');
    $title = get_string('printblanktooltip', 'questionnaire');
    $url = '/mod/questionnaire/print.php?qid=' . $questionnaire->id() . '&amp;rid=0&amp;' . 'courseid=' .
            $course->id . '&amp;sec=1';
    $options = [
        'menubar' => true,
        'location' => false,
        'scrollbars' => true,
        'resizable' => true,
        'height' => 600,
        'width' => 800,
        'title' => $title,
    ];
    $name = 'popup';
    $link = new moodle_url($url);
    $action = new popup_action('click', $link, $name, $options);
    $class = "floatprinticon";
    $page->add_to_page(
        'printblank',
        $renderer->action_link(
            $link,
            $linkname,
            $action,
            ['class' => $class, 'title' => $title],
            new pix_icon('t/print', $title)
        )
    );
}

(new \mod_questionnaire\local\report\report_view_builder($renderer, $page))
    ->build_print_view($questionnaire, $course->id, '', 'preview', 0, $popup);
if ($popup) {
    $page->add_to_page('closebutton', $renderer->close_window_button());
}
echo $renderer->render($page);
echo $renderer->footer($course);

// Log this questionnaire preview (skip in survey-only mode — no real module instance).
if ($questionnaire->id() > 0) {
    $anonymous = $questionnaire->respondenttype() == 'anonymous';
    $event = \mod_questionnaire\event\questionnaire_previewed::create([
        'objectid' => $questionnaire->id(),
        'anonymous' => $anonymous,
        'context' => $questionnaire->context(),
    ]);
    $event->trigger();
}
