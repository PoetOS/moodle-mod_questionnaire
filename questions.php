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
 * This page handles the main question editing screen.
 *
 * @package    mod_questionnaire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/questionnaire/classes/local/question/question.php'); // Needed for question type constants.

use mod_questionnaire\questionnaire;
use mod_questionnaire\local\question_type;
use mod_questionnaire\output\questionspage;
use mod_questionnaire\output\question_manager;
use mod_questionnaire\output\question_delete_confirmation;
use mod_questionnaire\local\survey\survey;

$id = required_param('id', PARAM_INT);                 // Course module ID.
$action = optional_param('action', 'main', PARAM_ALPHA);   // Screen.
$qid = optional_param('qid', 0, PARAM_INT);             // Question id.
$qtype = optional_param('typeid', 0, PARAM_INT);         // Question type.
$delq = optional_param('delq', 0, PARAM_INT);             // Question id to delete.
$delpermanentlyq = optional_param('delpermanentlyq', 0, PARAM_INT); // Question id to delete.
$restoreq = optional_param(questionnaire::restore_param(), 0, PARAM_INT); // Question id to restore question.
$togglerequired = optional_param('togglerequired', 0, PARAM_INT); // Question id to toggle required.
$runvalidate = optional_param('validate', 0, PARAM_BOOL); // Re-check page break placement.
$restoredqid = optional_param('restored', 0, PARAM_INT);  // Question id just restored, for the row highlight.
$lasttypeid = optional_param('lasttypeid', null, PARAM_INT); // Sticky add-question type from the last save.
$lastrequired = optional_param('lastrequired', '', PARAM_ALPHA); // Sticky required flag from the last save.

$questionnaire = questionnaire::from_cmid($id);
$course = $questionnaire->course();
$cm = $questionnaire->coursemodule();

require_course_login($course, true, $cm);
$context = $questionnaire->context();

$url = new moodle_url($CFG->wwwroot . '/mod/questionnaire/questions.php');
$url->param('id', $id);
if ($qid) {
    $url->param('qid', $qid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = new questionspage();

if (!$questionnaire->capabilities()->can_edit_questions()) {
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}

$mainurl = new moodle_url('/mod/questionnaire/questions.php', ['id' => $cm->id]);
$questionnairehasdependencies = $questionnaire->navigator()->has_dependencies();

// Delete question: reached directly (page breaks) or via the confirmdelquestion Yes button.
if ($delq) {
    require_sesskey();
    if ($questionnaire->survey()->soft_delete_question($delq, $questionnaire->id())) {
        if ($questionnairehasdependencies) {
            $validationmsg = $questionnaire->survey()->check_page_breaks();
            if (!empty($validationmsg)) {
                \core\notification::warning($validationmsg);
            }
        }
    }
    redirect($mainurl);
}

// Permanently delete a soft-deleted question, reached via the confirmdelpermanentlyq Yes button.
if ($delpermanentlyq) {
    require_sesskey();
    $deletequestions = $questionnaire->survey()->get_delete_questions();
    $deletedquestion = $deletequestions[$delpermanentlyq] ?? null;
    survey::delete_question_permanently($delpermanentlyq, $questionnaire->surveyid());
    if ($deletedquestion !== null) {
        $questiontype = \mod_questionnaire\local\question\question::qtypename($deletedquestion->typeid());
        survey::trigger_question_deleted_event($cm->id, $questiontype, $questionnaire->courseid());
    }
    redirect($mainurl);
}

// Restore a soft-deleted question from the recycle bin.
if ($restoreq) {
    require_sesskey();
    $deletequestions = $questionnaire->survey()->get_delete_questions();
    $qdeleted = $deletequestions[$restoreq] ?? false;
    if ($qdeleted) {
        survey::restore_deleted_question($restoreq, $qdeleted->surveyid());
    }
    redirect(new moodle_url('/mod/questionnaire/questions.php', ['id' => $cm->id, 'restored' => $restoreq]));
}

// Toggle a question's required flag.
if ($togglerequired) {
    require_sesskey();
    $questions = $questionnaire->questions();
    if (isset($questions[$togglerequired])) {
        $questions[$togglerequired]->set_required(!$questions[$togglerequired]->required());
    }
    redirect($mainurl);
}

// Re-check page break placement on demand.
if ($runvalidate) {
    require_sesskey();
    $validationmsg = $questionnaire->survey()->check_page_breaks();
    if (!empty($validationmsg)) {
        \core\notification::warning($validationmsg);
    }
    redirect($mainurl);
}

// Add-question bar: a page break is created immediately; any other type moves to the edit form.
if (optional_param('addqbutton', 0, PARAM_INT)) {
    require_sesskey();
    if ($qtype == QUESPAGEBREAK) {
        $questionrec = new stdClass();
        $questionrec->surveyid = $questionnaire->surveyid();
        $questionrec->typeid = QUESPAGEBREAK;
        $questionrec->content = 'break';
        $question = \mod_questionnaire\local\question\question::question_builder(QUESPAGEBREAK);
        $question->add($questionrec);
        redirect($mainurl);
    }
    redirect(new moodle_url('/mod/questionnaire/questions.php', [
        'id' => $cm->id, 'action' => 'question', 'typeid' => $qtype, 'lastrequired' => $lastrequired,
    ]));
}

if ($action == 'question') {
    [$question, $editorcontent] = $questionnaire->survey()->prep_question_for_form($qid, $qtype);
    // The 'sticky' required response value for further new questions.
    if ($lastrequired !== '' && !isset($question->qid)) {
        $question->set_required_value($lastrequired);
    }
    $questionsform = new \mod_questionnaire\edit_question_form('questions.php', [
        'questionnaire' => $questionnaire,
        'question' => $question,
    ]);
    $formdata = $question->form_data();
    $formdata->content = $editorcontent;
    $questionsform->set_data($formdata);
    if ($questionsform->is_cancelled()) {
        redirect($mainurl);
    } else if ($qformdata = $questionsform->get_data()) {
        // Saving question data.
        if (isset($qformdata->makecopy)) {
            $qformdata->qid = 0;
        }

        $question->form_update($qformdata, $questionnaire);

        // Make these field values 'sticky' for further new questions.
        if (!isset($qformdata->required)) {
            $qformdata->required = 'n';
        }

        $questionnaire->survey()->check_page_breaks();

        // Log question created event.
        $questiontype = \mod_questionnaire\local\question\question::qtypename($qformdata->typeid);
        $params = [
            'context' => $context,
            'courseid' => $questionnaire->courseid(),
            'other' => ['questiontype' => $questiontype],
        ];
        \mod_questionnaire\event\question_created::create($params)->trigger();

        // Back to the main screen; carry the just-used typeid/required forward as defaults for the next add.
        redirect(new moodle_url('/mod/questionnaire/questions.php', [
            'id' => $cm->id,
            'lasttypeid' => (int) $qformdata->typeid,
            'lastrequired' => $qformdata->required,
        ]));
    }
}

// Print the page header.
if ($action == 'question') {
    if (isset($question->qid)) {
        $streditquestion = get_string('editquestion', 'questionnaire', question_type::display_name($question->typeid()));
    } else {
        $streditquestion = get_string('addnewquestion', 'questionnaire', question_type::display_name($question->typeid()));
    }
} else {
    $streditquestion = get_string('managequestions', 'questionnaire');
}

$PAGE->set_title($streditquestion);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($streditquestion);
echo $renderer->header();

if ($action == 'question') {
    $page->add_to_page('formarea', $questionsform->render());
} else if ($action == 'confirmdelquestion') {
    $qid = required_param('qid', PARAM_INT);
    $urlno = new moodle_url('/mod/questionnaire/questions.php', ['id' => $cm->id]);
    $urlyes = new moodle_url('/mod/questionnaire/questions.php', ['id' => $cm->id, 'delq' => $qid, 'sesskey' => sesskey()]);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $msg = $renderer->render(new question_delete_confirmation($questionnaire, $qid));
    $page->add_to_page('formarea', $renderer->confirm($msg, $buttonyes, $buttonno));
} else if ($action === questionnaire::confirm_delete_param()) {
    $qid = required_param('qid', PARAM_INT);
    $urlno = new moodle_url('/mod/questionnaire/questions.php', ['id' => $cm->id]);
    $urlyes = new moodle_url('/mod/questionnaire/questions.php', [
        'id' => $cm->id, 'delpermanentlyq' => $qid, 'sesskey' => sesskey(),
    ]);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $msg = $renderer->render(new question_delete_confirmation($questionnaire, $qid, true));
    $page->add_to_page('formarea', $renderer->confirm($msg, $buttonyes, $buttonno));
} else {
    $manager = new question_manager($questionnaire, $restoredqid, $lasttypeid, $lastrequired);
    $page->add_to_page('formarea', $renderer->render($manager));
    $PAGE->requires->js_call_amd('mod_questionnaire/question_manager', 'init', [$cm->id]);
}

echo $renderer->render($page);
echo $renderer->footer();
