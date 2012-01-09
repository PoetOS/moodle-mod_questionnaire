<?php // $Id$

/// This page displays a non-completable instance of questionnaire

    require_once("../../config.php");
    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

    $id     = optional_param('id', 0, PARAM_INT);
    $sid    = optional_param('sid', 0, PARAM_INT);
    $popup  = optional_param('popup', 0, PARAM_INT);

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
        if (! $survey = get_record("questionnaire_survey", "id", $sid)) {
            error("Survey does not exist");
        }
        if (! $course = get_record("course", "id", $survey->owner)) {
            error("Course is misconfigured");
        }
        /// Dummy questionnaire object:
        $questionnaire = new Object();
        $questionnaire->id = 0;
        $questionnaire->course = $course->id;
        $questionnaire->name = $survey->title;
        $questionnaire->sid = $sid;
        $questionnaire->resume = 0;
        ///Dummy cm object:
        $cm = false;
    }

/// Check login and get context.
    require_login($course->id, false, $cm);
    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);
    $owner = (trim($questionnaire->survey->owner) == trim($course->id));

    $canpreview = (!isset($questionnaire->capabilities) &&
                   has_capability('mod/questionnaire:manage', get_context_instance(CONTEXT_COURSE, $course->id))) ||
                  (isset($questionnaire->capabilities) && $questionnaire->capabilities->editquestions && $owner);
    if (!$canpreview) {
        /// Should never happen, unless called directly by a snoop...
        print_error('nopermissions', 'questionnaire', $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id);
    }

    $SESSION->questionnaire->current_tab = 'preview';

    $qp = get_string('preview_questionnaire', 'questionnaire');
    $pq = get_string('previewing', 'questionnaire');
    $currentcss = '';
    if ( !empty($questionnaire->survey->theme) ) {
        $currentcss = '<link rel="stylesheet" type="text/css" href="'.
            $CFG->wwwroot.'/mod/questionnaire/css/'.$questionnaire->survey->theme.'" />';
    } else {
        $currentcss = '<link rel="stylesheet" type="text/css" href="'.
            $CFG->wwwroot.'/mod/questionnaire/css/default.css" />';
    }

/// Print the page header
    if (!$popup) {
        $navigation = build_navigation($pq, $cm);
    } else {
        $navigation = '';
    }
    print_header($qp, '', $navigation,  '', $currentcss);
    if (!$popup) {
        include('tabs.php');
    }
    $questionnaire->survey_print_render('', '', $course->id);
    if ($popup) {
        close_window_button();
        echo '</body></html>';
    } else {
        print_footer($course);
    }
?>