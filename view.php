<?php  // $Id: view.php,v 1.19.2.4 2009/12/17 22:01:43 joseph_rezeau Exp $

/// This page prints a particular instance of questionnaire

    require_once("../../config.php");
    require_once("lib.php");

    /// Used by the phpESP code.
    global $HTTP_POST_VARS, $HTTP_GET_VARS, $HTTP_SERVER_VARS;

    $SESSION->questionnaire->current_tab = 'view';

    $id = optional_param('id', NULL, PARAM_INT);    // Course Module ID, or
    $a = optional_param('a', NULL, PARAM_INT);      // questionnaire ID

    $qac = optional_param('qac');                   // possible actions
    $sid = optional_param('sid', NULL, PARAM_INT);  // Survey id.

    if ($id) {
        if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
            error("Course Module ID was incorrect");
        }

        if (! $course = get_record("course", "id", $cm->course)) {
            error("Course is misconfigured");
        }

        if (! $questionnaire = get_record("questionnaire", "id", $cm->instance)) {
            error("Course module is incorrect");
        }

    } else {
        if (! $questionnaire = get_record("questionnaire", "id", $a)) {
            error("Course module is incorrect");
        }
        if (! $course = get_record("course", "id", $questionnaire->course)) {
            error("Course is misconfigured");
        }
        if (! $cm = get_coursemodule_from_instance("questionnaire", $questionnaire->id, $course->id)) {
            error("Course Module ID was incorrect");
        }
    }

/// Check login and get context.
    require_course_login($course, true, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/questionnaire:view', $context);

    add_to_log($course->id, "questionnaire", "view", "view.php?id=$cm->id", "$questionnaire->name", $cm->id, $USER->id);

/// Print the page header

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);
    $questionnaire->strquestionnaires = get_string("modulenameplural", "questionnaire");
    $questionnaire->strquestionnaire  = get_string("modulename", "questionnaire");

    $questionnaire->view();
?>