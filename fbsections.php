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
use mod_questionnaire\output\feedbackpage;

$id = required_param('id', PARAM_INT);    // Course module ID.
$section = optional_param('section', 1, PARAM_INT);
if ($section == 0) {
    $section = 1;
}
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$action = optional_param('action', '', PARAM_ALPHA);
$sectionid = optional_param('sectionid', 0, PARAM_INT);

$questionnaire = questionnaire::from_cmid($id);

// Needed here for forced language courses.
require_course_login($questionnaire->course(), true, $questionnaire->coursemodule());

$PAGE->set_url(new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $id]));
$PAGE->set_context($questionnaire->context());

if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}

$surveyid = $questionnaire->surveyid();

if ($sectionid) {
    // Get the specified section by its id.
    $feedbacksection = new mod_questionnaire\local\feedback\section($questionnaire->questions(), ['id' => $sectionid]);
} else if (!$DB->count_records('questionnaire_fb_sections', ['surveyid' => $surveyid])) {
    // There are no sections currently, so create one.
    if ($questionnaire->survey()->feedbacksections() == 1) {
        $sectionlabel = get_string('feedbackglobal', 'questionnaire');
    } else {
        $sectionlabel = get_string('feedbackdefaultlabel', 'questionnaire');
    }
    $feedbacksection = mod_questionnaire\local\feedback\section::new_section($surveyid, $sectionlabel);
} else {
    // Get the specified section by section number.
    $feedbacksection = new mod_questionnaire\local\feedback\section(
        $questionnaire->questions(),
        ['surveyid' => $surveyid, 'sectionnum' => $section]
    );
}

// Get all questions that are valid feedback questions.
$validquestions = [];
foreach ($questionnaire->questions() as $question) {
    if ($question->valid_feedback()) {
        $validquestions[$question->id()] = $question->name();
    }
}

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = new feedbackpage();

$SESSION->questionnaire->current_tab = 'feedback';

if (!$questionnaire->capabilities()->can_edit_questions()) {
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

// Handle confirmed actions that impact display immediately.
if ($action == 'removequestion') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    $feedbacksection->remove_question($qid);
} else if ($action == 'deletesection') {
    $sectionid = required_param('sectionid', PARAM_INT);
    if ($sectionid == $feedbacksection->id) {
        $feedbacksection->delete();
        redirect(new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $questionnaire->coursemodule()->id]));
    }
}

$customdata = new stdClass();
$customdata->feedbacksection = $feedbacksection;
$customdata->validquestions = $validquestions;
$customdata->survey = $questionnaire->survey()->to_stdclass();
$customdata->sectionselect = $DB->get_records_menu(
    'questionnaire_fb_sections',
    ['surveyid' => $surveyid],
    'section',
    'id,sectionlabel'
);

$feedbackform = new \mod_questionnaire\feedback_section_form('fbsections.php', $customdata, $renderer);
$sdata = clone($feedbacksection);
$sdata->sid = $surveyid;
$sdata->sectionid = $feedbacksection->id;
$sdata->id = $questionnaire->coursemodule()->id;

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
$sdata->sectionheading = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url('/mod/questionnaire/feedback.php', ['id' => $questionnaire->coursemodule()->id]));
}

if ($settings = $feedbackform->get_data()) {
    // Because formslib doesn't support 'numeric' or 'image' inputs, the results won't show up in the $feedbackform object.
    $fullform = data_submitted();

    if (isset($settings->gotosection)) {
        if ($settings->navigatesections != $feedbacksection->id) {
            redirect(
                new moodle_url(
                    '/mod/questionnaire/fbsections.php',
                    ['id' => $questionnaire->coursemodule()->id, 'sectionid' => $settings->navigatesections]
                )
            );
        }
    } else if (isset($settings->addnewsection)) {
        $newsection = mod_questionnaire\local\feedback\section::new_section($surveyid, $settings->newsectionlabel);
        redirect(new moodle_url(
            '/mod/questionnaire/fbsections.php',
            ['id' => $questionnaire->coursemodule()->id, 'sectionid' => $newsection->id]
        ));
    } else if (isset($fullform->confirmdeletesection)) {
        redirect(
            new moodle_url(
                '/mod/questionnaire/fbsections.php',
                ['id' => $questionnaire->coursemodule()->id, 'sectionid' => $feedbacksection->id,
                    'action' => 'confirmdeletesection']
            )
        );
    } else if (isset($fullform->confirmremovequestion)) {
        $qid = key($fullform->confirmremovequestion);
        redirect(
            new moodle_url(
                '/mod/questionnaire/fbsections.php',
                ['id' => $questionnaire->coursemodule()->id, 'sectionid' => $settings->sectionid,
                    'action' => 'confirmremovequestion', 'qid' => $qid]
            )
        );
    } else if (isset($settings->addquestion)) {
        $scorecalculation = [];
        // Check for added question.
        if (isset($settings->addquestionselect) && ($settings->addquestionselect != 0)) {
            if ($questionnaire->questions()[$settings->addquestionselect]->supports_feedback_scores()) {
                $scorecalculation[$settings->addquestionselect] = 1;
            } else {
                $scorecalculation[$settings->addquestionselect] = -1;
            }
        }
        // Get all current asigned questions.
        if (isset($fullform->weight)) {
            foreach ($fullform->weight as $qid => $value) {
                $scorecalculation[$qid] = $value;
            }
        }
        // Update the section with question weights.
        $feedbacksection->set_new_scorecalculation($scorecalculation);
    } else if (isset($settings->submitbutton)) {
        if (isset($fullform->weight)) {
            $feedbacksection->scorecalculation = $fullform->weight;
        } else {
            $feedbacksection->scorecalculation = [];
        }
        $feedbacksection->sectionlabel = $settings->sectionlabel;
        $feedbacksection->sectionheading = file_save_draft_area_files(
            (int)$settings->sectionheading['itemid'],
            $questionnaire->context()->id,
            'mod_questionnaire',
            'sectionheading',
            $feedbacksection->id,
            ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
            $settings->sectionheading['text']
        );
        $feedbacksection->sectionheadingformat = $settings->sectionheading['format'];

        // May have changed the section label and weights, so update the data.
        $customdata->sectionselect[$feedbacksection->id] = $settings->sectionlabel;
        if (isset($fullform->weight)) {
            $customdata->feedbacksection->scorecalculation = $fullform->weight;
        }

        // Save current section's feedbacks
        // first delete all existing feedbacks for this section - if any - because we never know whether editing feedbacks will
        // have more or less texts, so it's easiest to delete all and start afresh.
        $feedbacksection->delete_sectionfeedback();

        $i = 0;
        while (!empty($settings->feedbackboundaries[$i])) {
            $boundary = trim($settings->feedbackboundaries[$i]);
            if (strlen($boundary) > 0 && $boundary[strlen($boundary) - 1] == '%') {
                $boundary = trim(substr($boundary, 0, -1));
            }
            $settings->feedbackboundaries[$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;
        $settings->feedbackboundaries[-1] = 101;
        $settings->feedbackboundaries[$numboundaries] = 0;
        $settings->feedbackboundarycount = $numboundaries;

        // Now set up new section feedback records for each saved boundary.
        for ($i = 0; $i <= $settings->feedbackboundarycount; $i++) {
            $feedback = new stdClass();
            $feedback->sectionid = $feedbacksection->id;
            if (isset($settings->feedbacklabel[$i])) {
                $feedback->feedbacklabel = $settings->feedbacklabel[$i];
            } else {
                $feedback->feedbacklabel = null;
            }
            $feedback->feedbacktext = '';
            $feedback->feedbacktextformat = $settings->feedbacktext[$i]['format'];
            $feedback->minscore = $settings->feedbackboundaries[$i];
            $feedback->maxscore = $settings->feedbackboundaries[$i - 1];

            $fbid = $feedbacksection->load_sectionfeedback($feedback);

            $feedbacktext = file_save_draft_area_files(
                (int)$settings->feedbacktext[$i]['itemid'],
                $questionnaire->context()->id,
                'mod_questionnaire',
                'feedback',
                $fbid,
                ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
                $settings->feedbacktext[$i]['text']
            );
            $feedbacksection->sectionfeedback[$fbid]->feedbacktext = $feedbacktext;
        }

        // Update all feedback data.
        $feedbacksection->update();
    }
    $feedbackform = new \mod_questionnaire\feedback_section_form('fbsections.php', $customdata, $renderer);
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'questionnaire'));
$PAGE->set_heading(format_string($questionnaire->course()->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'questionnaire'));
echo $renderer->header();
require('tabs.php');

// Handle confirmations differently.
if ($action == 'confirmremovequestion') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    $msgargs = new stdClass();
    $msgargs->qname = $questionnaire->questions()[$qid]->name();
    $msgargs->sname = $feedbacksection->sectionlabel;
    $msg = '<div class="warning centerpara"><p>' . get_string('confirmremovequestion', 'questionnaire', $msgargs) . '</p></div>';
    $args = ['id' => $questionnaire->coursemodule()->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $args['action'] = 'removequestion';
    $args['qid'] = $qid;
    $urlyes = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $page->add_to_page('formarea', $renderer->confirm($msg, $buttonyes, $buttonno));
} else if ($action == 'confirmdeletesection') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $msg = '<div class="warning centerpara"><p>' .
        get_string('confirmdeletesection', 'questionnaire', $feedbacksection->sectionlabel) . '</p></div>';
    $args = ['id' => $questionnaire->coursemodule()->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $args['action'] = 'deletesection';
    $urlyes = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $page->add_to_page('formarea', $renderer->confirm($msg, $buttonyes, $buttonno));
} else {
    $page->add_to_page('formarea', $feedbackform->render());
}

echo $renderer->render($page);
echo $renderer->footer($questionnaire->course());
