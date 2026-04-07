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
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

use mod_questionnaire\questionnaire;
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

// Add renderer and page objects to the questionnaire object for display use.
$questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
$questionnaire->add_page(new feedbackpage());

$SESSION->questionnaire->current_tab = 'feedback';

if (!$questionnaire->can_edit_questions()) {
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
    if (isset($settings->feedbacksettingsbutton1) || isset($settings->feedbacksettingsbutton2) || isset($settings->buttongroup)) {
        if (isset($settings->feedbackscores)) {
            $sdata->feedbackscores = $settings->feedbackscores;
        } else {
            $sdata->feedbackscores = 0;
        }

        if (isset($settings->feedbacknotes)) {
            $sdata->fbnotesitemid = $settings->feedbacknotes['itemid'];
            $sdata->fbnotesformat = $settings->feedbacknotes['format'];
            $sdata->feedbacknotes = $settings->feedbacknotes['text'];
            $sdata->feedbacknotes = file_save_draft_area_files(
                $sdata->fbnotesitemid,
                $questionnaire->context()->id,
                'mod_questionnaire',
                'feedbacknotes',
                $sdata->id,
                ['subdirs' => true],
                $sdata->feedbacknotes
            );
        } else {
            $sdata->feedbacknotes = '';
        }

        if ($settings->feedbacksections > 0) {
            $sdata->feedbacksections = $settings->feedbacksections;
            $usergraph = get_config('questionnaire', 'usergraph');
            if ($usergraph) {
                if ($settings->feedbacksections == 1) {
                    $sdata->charttype = $settings->chart_type_global;
                } else if ($settings->feedbacksections == 2) {
                    $sdata->charttype = $settings->chart_type_two_sections;
                } else if ($settings->feedbacksections > 2) {
                    $sdata->charttype = $settings->chart_type_sections;
                }
            }
        } else {
            $sdata->feedbacksections = 0;
        }
        $sdata->courseid = $settings->courseid;
        if (!($sid = questionnaire::update_survey($questionnaire->surveyid(), $sdata))) {
            throw new \moodle_exception('couldnotcreatenewsurvey', 'mod_questionnaire');
        }
    }

    // Handle the edit feedback sections action.
    if (isset($settings->buttongroup['feedbackeditbutton'])) {
        // Create a single section for Global Feedback if not existent.
        $firstsection =
            $DB->get_field('questionnaire_fb_sections', 'MIN(section)', ['surveyid' => $questionnaire->surveyid()]) ?: 0;
        if (($sdata->feedbacksections > 0) && ($firstsection == 0)) {
            if ($sdata->feedbacksections == 1) {
                $sectionlabel = get_string('feedbackglobal', 'questionnaire');
            } else {
                $sectionlabel = get_string('feedbackdefaultlabel', 'questionnaire');
            }
            $feedbacksection = mod_questionnaire\local\feedback\section::new_section($questionnaire->surveyid(), $sectionlabel);
        }
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
echo $questionnaire->renderer->header();
require('tabs.php');
if (!$validquestions) {
    $questionnaire->page->add_to_page('formarea', get_string('feedbackoptions_help', 'questionnaire'));
} else {
    $questionnaire->page->add_to_page('formarea', $feedbackform->render());
}
echo $questionnaire->renderer->render($questionnaire->page);
echo $questionnaire->renderer->footer($questionnaire->course());
