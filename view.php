<?php  // $Id: view.php,v 1.32 2011/08/02 12:39:37 jmg324 Exp $

/// This page prints a particular instance of questionnaire

    require_once("../../config.php");
    require_once("lib.php");
    require_once($CFG->libdir . '/completionlib.php');

    $SESSION->questionnaire->current_tab = 'view';

    $id = optional_param('id', NULL, PARAM_INT);    // Course Module ID, or
    $a = optional_param('a', NULL, PARAM_INT);      // questionnaire ID

    $sid = optional_param('sid', NULL, PARAM_INT);  // Survey id.

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

/// Check login and get context.
    require_course_login($course, true, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/questionnaire:view', $context);

    $url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/view.php');
    if (isset($id)) {
        $url->param('id', $id);
    } else {
        $url->param('a', $a);
    }
    if (isset($sid)) {
        $url->param('sid', $sid);
    }

    $PAGE->set_url($url);
    $PAGE->set_context($context);

	add_to_log($course->id, "questionnaire", "view", "view.php?id=$cm->id", "$questionnaire->name", $cm->id, $USER->id);

/// Print the page header

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);
    $questionnaire->strquestionnaires = get_string("modulenameplural", "questionnaire");
    $questionnaire->strquestionnaire  = get_string("modulename", "questionnaire");

    /// Mark as viewed
    $completion=new completion_info($course);
    $completion->set_module_viewed($cm);
    $questionnaire->view();
?>