<?php // $Id: print.php,v 1.21.2.1 2011/06/16 20:50:13 mchurch Exp $

    require_once("../../config.php");
    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

    $qid = required_param('qid', PARAM_INT);
    $rid = required_param('rid', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);
    $sec = required_param('sec', PARAM_INT);
    $null = null;
    $referer = $CFG->wwwroot.'/mod/questionnaire/report.php';

    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $qid))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $questionnaire->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("questionnaire", $questionnaire->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

/// Check login and get context.
    require_login($courseid);

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

    /// If you can't view the questionnaire, or can't view a specified response, error out.
    if (!($questionnaire->capabilities->view && (($rid == 0) || $questionnaire->can_view_response($rid)))) {
        /// Should never happen, unless called directly by a snoop...
        print_error('nopermissions', 'moodle', $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id);
    }

    $PAGE->set_title($questionnaire->survey->title);
    $PAGE->set_pagelayout('popup');
    echo $OUTPUT->header();
    $questionnaire->survey_print_render('', '', $courseid);
    echo $OUTPUT->close_window_button();
    echo $OUTPUT->footer();
?>