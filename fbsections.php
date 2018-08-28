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
require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

$id = required_param('id', PARAM_INT);    // Course module ID.
$section = optional_param('section', 1, PARAM_INT);
if ($section == 0) {
    $section = 1;
}
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$action = optional_param('action', '', PARAM_ALPHA);
$sectionid = optional_param('sectionid', 0, PARAM_INT);

if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", ["id" => $cm->course])) {
    print_error('coursemisconf');
}

if (! $questionnaire = $DB->get_record("questionnaire", ["id" => $cm->instance])) {
    print_error('invalidcoursemodule');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

$select = 'SELECT f.id as fbid, fs.*, f.feedbacklabel, f.feedbacktext, f.feedbacktextformat, f.minscore, f.maxscore ';
$from = 'FROM {questionnaire_fb_sections} fs LEFT JOIN {questionnaire_feedback} f ON fs.id = f.section_id ';
$order = 'ORDER BY minscore DESC';
if ($sectionid) {
    $where = 'WHERE fs.id = ? ';
    $params = [$sectionid];
} else {
    $where = 'WHERE fs.survey_id = ? AND fs.section = ? ';
    $params = [$questionnaire->survey->id, $section];
}

if (!($feedbackrecs = $DB->get_records_sql($select . $from . $where . $order, $params))) {
    print_error('invalidsectionid');
} else {
    $feedbacksection = new stdClass();
    foreach ($feedbackrecs as $fbid => $feedbackrec) {
        if (!isset($feedbacksection->id)) {
            $feedbacksection->id = $feedbackrec->id;
            $feedbacksection->survey_id = $feedbackrec->survey_id;
            $feedbacksection->section = $feedbackrec->section;
            $feedbacksection->scorecalculation = unserialize($feedbackrec->scorecalculation);
            foreach ($feedbacksection->scorecalculation as $qid => $score) {
                if (!$questionnaire->questions[$qid]->supports_feedback_scores()) {
                    $feedbacksection->scorecalculation[$qid] = -1;
                }
            }
            $feedbacksection->sectionlabel = $feedbackrec->sectionlabel;
            $feedbacksection->sectionheading = $feedbackrec->sectionheading;
            $feedbacksection->sectionheadingformat = $feedbackrec->sectionheadingformat;
            $feedbacksection->feedbacks = [];
        }
        if (!empty($fbid)) {
            $feedbacksection->feedbacks[$feedbackrec->fbid] = new stdClass();
            $feedbacksection->feedbacks[$feedbackrec->fbid]->id = $feedbackrec->fbid;
            $feedbacksection->feedbacks[$feedbackrec->fbid]->feedbacklabel = $feedbackrec->feedbacklabel;
            $feedbacksection->feedbacks[$feedbackrec->fbid]->feedbacktext = $feedbackrec->feedbacktext;
            $feedbacksection->feedbacks[$feedbackrec->fbid]->feedbacktextformat = $feedbackrec->feedbacktextformat;
            $feedbacksection->feedbacks[$feedbackrec->fbid]->minscore = $feedbackrec->minscore;
            $feedbacksection->feedbacks[$feedbackrec->fbid]->maxscore = $feedbackrec->maxscore;
        }
    }
}

// Get all questions that are valid feedback questions.
$validquestions = [];
foreach ($questionnaire->questions as $question) {
    if ($question->valid_feedback()) {
        $validquestions[$question->id] = $question->name;
    }
}

// Add renderer and page objects to the questionnaire object for display use.
$questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
$questionnaire->add_page(new \mod_questionnaire\output\feedbackpage());

$SESSION->questionnaire->current_tab = 'feedback';

if (!$questionnaire->capabilities->editquestions) {
    print_error('nopermissions', 'error', 'mod:questionnaire:editquestions');
}

// Handle confirmed actions that impact display immediately.
if ($action == 'removequestion') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    unset($feedbacksection->scorecalculation[$qid]);
    $scorecalculation = serialize($feedbacksection->scorecalculation);
    $DB->set_field('questionnaire_fb_sections', 'scorecalculation', $scorecalculation, ['id' => $sectionid]);

} else if ($action == 'deletesection') {
    $sectionid = required_param('sectionid', PARAM_INT);
    if ($sectionid == $feedbacksection->id) {
        $DB->delete_records('questionnaire_feedback', ['section_id' => $sectionid]);
        $DB->delete_records('questionnaire_fb_sections', ['id' => $sectionid]);
        $url = new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $cm->id]);
        redirect($url);
    }
}

$customdata = new stdClass();
$customdata->feedbacksection = $feedbacksection;
$customdata->validquestions = $validquestions;
$customdata->survey = $questionnaire->survey;
$customdata->sectionselect = $DB->get_records_menu('questionnaire_fb_sections', ['survey_id' => $questionnaire->survey->id],
    'section', 'id,sectionlabel');

$feedbackform = new \mod_questionnaire\feedback_section_form('fbsections.php', $customdata);
$sdata = clone($feedbacksection);
$sdata->sid = $questionnaire->survey->id;
$sdata->sectionid = $feedbacksection->id;
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('sectionheading');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_questionnaire', 'sectionheading',
    $feedbacksection->id, ['subdirs' => true], $feedbacksection->sectionheading);
$sdata->sectionheading = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    $url = new moodle_url('/mod/questionnaire/feedback.php', ['id' => $cm->id]);
    redirect ($url);
}

if ($settings = $feedbackform->get_data()) {
    // Because formslib doesn't support 'numeric' or 'image' inputs, the results won't show up in the $feedbackform object.
    $fullform = data_submitted();

    if (isset($settings->gotosection)) {
        if ($settings->navigatesections != $feedbacksection->id) {
            $url = new moodle_url('/mod/questionnaire/fbsections.php',
                ['id' => $cm->id, 'sectionid' => $settings->navigatesections]);
            redirect($url);
        }

    } else if (isset($settings->addnewsection)) {
        if (empty($settings->newsectionlabel)) {
            $settings->newsectionlabel = get_string('feedbackdefaultlabel', 'questionnaire');
        }
        $maxsection = $DB->get_field('questionnaire_fb_sections', 'MAX(section)', ['survey_id' => $questionnaire->survey->id]);
        $newrec = new stdClass();
        $newrec->survey_id = $questionnaire->survey->id;
        $newrec->section = $maxsection + 1;
        $newrec->sectionlabel = $settings->newsectionlabel;
        $newsecid = $DB->insert_record('questionnaire_fb_sections', $newrec);
        $url = new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $cm->id, 'sectionid' => $newsecid]);
        redirect($url);

    } else if (isset($fullform->confirmdeletesection)) {
        $url = new moodle_url('/mod/questionnaire/fbsections.php',
            ['id' => $cm->id, 'sectionid' => $feedbacksection->id, 'action' => 'confirmdeletesection']);
        redirect($url);

    } else if (isset($fullform->confirmremovequestion)) {
        $qid = key($fullform->confirmremovequestion);
        $url = new moodle_url('/mod/questionnaire/fbsections.php',
            ['id' => $cm->id, 'sectionid' => $settings->sectionid, 'action' => 'confirmremovequestion', 'qid' => $qid]);
        redirect($url);

    } else if (isset($settings->addquestion)) {
        $scorecalculation = [];
        // Check for added question.
        if (isset($settings->addquestionselect) && ($settings->addquestionselect != 0)) {
            if ($questionnaire->questions[$settings->addquestionselect]->supports_feedback_scores()) {
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
        $newscore = serialize($scorecalculation);
        $DB->set_field('questionnaire_fb_sections', 'scorecalculation', $newscore, ['id' => $feedbacksection->id]);
        $feedbacksection->scorecalculation = $scorecalculation;

    } else if (isset($settings->submitbutton)) {
        $updaterec = new stdClass();
        $updaterec->id = $feedbacksection->id;
        $updaterec->survey_id = $feedbacksection->survey_id;
        $updaterec->section = $feedbacksection->section;
        if (isset($fullform->weight)) {
            $updaterec->scorecalculation = serialize($fullform->weight);
        } else {
            $updaterec->scorecalculation = null;
        }
        $updaterec->sectionlabel = $settings->sectionlabel;
        $updaterec->sectionheading = file_save_draft_area_files((int)$settings->sectionheading['itemid'], $context->id,
            'mod_questionnaire', 'sectionheading', $feedbacksection->id, ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
            $settings->sectionheading['text']);
        $updaterec->sectionheadingformat = $settings->sectionheading['format'];
        $DB->update_record('questionnaire_fb_sections', $updaterec);

        // May have changed the section label and weights, so update the data.
        $customdata->sectionselect[$feedbacksection->id] = $settings->sectionlabel;
        if (isset($fullform->weight)) {
            $customdata->feedbacksection->scorecalculation = $fullform->weight;
        }

        // Save current section's feedbacks
        // first delete all existing feedbacks for this section - if any - because we never know whether editing feedbacks will
        // have more or less texts, so it's easiest to delete all and start afresh.
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
        $DB->delete_records('questionnaire_feedback', ['section_id' => $feedbacksection->id]);
        for ($i = 0; $i <= $settings->feedbackboundarycount; $i++) {
            $feedback = new stdClass();
            $feedback->section_id = $feedbacksection->id;
            if (isset($settings->feedbacklabel[$i])) {
                $feedback->feedbacklabel = $settings->feedbacklabel[$i];

            }
            $feedback->feedbacktext = '';
            $feedback->feedbacktextformat = $settings->feedbacktext[$i]['format'];
            $feedback->minscore = $settings->feedbackboundaries[$i];
            $feedback->maxscore = $settings->feedbackboundaries[$i - 1];
            $feedback->id = $DB->insert_record('questionnaire_feedback', $feedback);

            $feedbacktext = file_save_draft_area_files((int)$settings->feedbacktext[$i]['itemid'],
                $context->id, 'mod_questionnaire', 'feedback', $feedback->id,
                ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
                $settings->feedbacktext[$i]['text']);
            $DB->set_field('questionnaire_feedback', 'feedbacktext', $feedbacktext, ['id' => $feedback->id]);
        }
    }
    $feedbackform = new \mod_questionnaire\feedback_section_form('fbsections.php', $customdata);
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'questionnaire'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'questionnaire'));
echo $questionnaire->renderer->header();
require('tabs.php');

// Handle confirmations differently.
if ($action == 'confirmremovequestion') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    $msgargs = new stdClass();
    $msgargs->qname = $questionnaire->questions[$qid]->name;
    $msgargs->sname = $feedbacksection->sectionlabel;
    $msg = '<div class="warning centerpara"><p>' . get_string('confirmremovequestion', 'questionnaire', $msgargs) . '</p></div>';
    $args = ['id' => $questionnaire->cm->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $args['action'] = 'removequestion';
    $args['qid'] = $qid;
    $urlyes = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $questionnaire->page->add_to_page('formarea', $questionnaire->renderer->confirm($msg, $buttonyes, $buttonno));

} else if ($action == 'confirmdeletesection') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $msg = '<div class="warning centerpara"><p>' .
        get_string('confirmdeletesection', 'questionnaire', $feedbacksection->sectionlabel) . '</p></div>';
    $args = ['id' => $questionnaire->cm->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $args['action'] = 'deletesection';
    $urlyes = new moodle_url('/mod/questionnaire/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $questionnaire->page->add_to_page('formarea', $questionnaire->renderer->confirm($msg, $buttonyes, $buttonno));

} else {
    $questionnaire->page->add_to_page('formarea', $feedbackform->render());
}

echo $questionnaire->renderer->render($questionnaire->page);
echo $questionnaire->renderer->footer($course);
