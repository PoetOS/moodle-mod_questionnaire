<?php  // $Id: qsettings.php,v 1.7.2.2 2008/06/20 13:36:43 mchurch Exp $
/// This page prints a particular instance of questionnaire

    require_once("../../config.php");
// JR moved further down after course_require_login 
//    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');
    require_once($CFG->dirroot.'/mod/questionnaire/settings_form.php');

    $id = required_param('id', PARAM_INT);        // course module ID

    if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }

    if (! $questionnaire = get_record("questionnaire", "id", $cm->instance)) {
        error("Course module is incorrect");
    }

    // needed here for forced language courses
    require_course_login($course->id);
    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);
    $SESSION->questionnaire->current_tab = 'settings';

    if (!$questionnaire->capabilities->manage) {
        error(get_string('nopermissions', 'error','mod:questionnaire:manage'));
    }
    
    $settings_form = new questionnaire_settings_form('qsettings.php');
    $sdata = clone($questionnaire->survey);
    $sdata->sid = $questionnaire->survey->id;
    $sdata->id = $cm->id;
    $settings_form->set_data($sdata);

    if ($settings = $settings_form->get_data()) {
        $sdata = new Object();
        $sdata->id = $settings->sid;
        $sdata->name = $settings->name;
        $sdata->realm = $settings->realm;
        $sdata->title = $settings->title;
        $sdata->subtitle = $settings->subtitle;
        $sdata->info = $settings->info;
        $sdata->theme = $settings->theme;
        $sdata->thanks_page = $settings->thanks_page;
        $sdata->thank_head = $settings->thank_head;
        $sdata->thank_body = $settings->thank_body;
        $sdata->email = $settings->email;
        $sdata->owner = $settings->owner;
        if (!($sid = $questionnaire->survey_update($sdata))) {
            error('Could not create a new survey!');
        } else {
            redirect ($CFG->wwwroot.'/mod/questionnaire/view.php?id='.$questionnaire->cm->id,
                      get_string('settingssaved', 'questionnaire'));
        }
    }

/// Print the page header
    $navigation = build_navigation(get_string('editingquestionnaire', 'questionnaire'), $questionnaire->cm);
    print_header_simple(get_string('editingquestionnaire', 'questionnaire'), '', $navigation);
    include('tabs.php');
    $settings_form->display();
    print_footer($course);


?>