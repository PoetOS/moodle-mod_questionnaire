<?php  // $Id: qsettings.php,v 1.13 2011/01/10 19:41:38 mchurch Exp $
/// This page prints a particular instance of questionnaire

    require_once("../../config.php");
// JR moved further down after course_require_login
//    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');
    require_once($CFG->dirroot.'/mod/questionnaire/settings_form.php');

    $id = required_param('id', PARAM_INT);        // course module ID

    if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

    // needed here for forced language courses
    require_course_login($course, true, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

    $url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/qsettings.php', array('id' => $id));
    $PAGE->set_url($url);
    $PAGE->set_context($context);

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);
    $SESSION->questionnaire->current_tab = 'settings';

    if (!$questionnaire->capabilities->manage) {
        print_error('nopermissions', 'error','mod:questionnaire:manage');
    }

    $settings_form = new questionnaire_settings_form('qsettings.php');
    $sdata = clone($questionnaire->survey);
    $sdata->sid = $questionnaire->survey->id;
    $sdata->id = $cm->id;

    $draftid_editor = file_get_submitted_draft_itemid('info');
    $currentinfo = file_prepare_draft_area($draftid_editor, $context->id, 'mod_questionnaire', 'info', $sdata->sid, array('subdirs'=>true), $questionnaire->survey->info);
    $sdata->info = array('text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid'=>$draftid_editor);

    $draftid_editor = file_get_submitted_draft_itemid('thankbody');
    $currentinfo = file_prepare_draft_area($draftid_editor, $context->id, 'mod_questionnaire', 'thankbody', $sdata->sid, array('subdirs'=>true), $questionnaire->survey->thank_body);
    $sdata->thank_body = array('text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid'=>$draftid_editor);

    $settings_form->set_data($sdata);

    if ($settings = $settings_form->get_data()) {
        $sdata = new Object();
        $sdata->id = $settings->sid;
        $sdata->name = $settings->name;
        $sdata->realm = $settings->realm;
        $sdata->title = $settings->title;
        $sdata->subtitle = $settings->subtitle;

        $sdata->infoitemid = $settings->info['itemid'];
        $sdata->infoformat = $settings->info['format'];
        $sdata->info       = $settings->info['text'];
        $sdata->info       = file_save_draft_area_files($sdata->infoitemid, $context->id, 'mod_questionnaire', 'info',
                                                        $sdata->id, array('subdirs'=>true), $sdata->info);

        $sdata->theme = ''; // deprecated theme field
        $sdata->thanks_page = $settings->thanks_page;
        $sdata->thank_head = $settings->thank_head;

        $sdata->thankitemid = $settings->thank_body['itemid'];
        $sdata->thankformat = $settings->thank_body['format'];
        $sdata->thank_body  = $settings->thank_body['text'];
        $sdata->thank_body  = file_save_draft_area_files($sdata->thankitemid, $context->id, 'mod_questionnaire', 'thankbody',
                                                         $sdata->id, array('subdirs'=>true), $sdata->thank_body);

        $sdata->email = $settings->email;
        $sdata->owner = $settings->owner;
        if (!($sid = $questionnaire->survey_update($sdata))) {
            print_error('couldnotcreatenewsurvey', 'questionnaire');
        } else {
            redirect ($CFG->wwwroot.'/mod/questionnaire/view.php?id='.$questionnaire->cm->id,
                      get_string('settingssaved', 'questionnaire'));
        }
    }

/// Print the page header
    $PAGE->set_title(get_string('editingquestionnaire', 'questionnaire'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->navbar->add(get_string('editingquestionnaire', 'questionnaire'));
    echo $OUTPUT->header();
 //   $navigation = build_navigation(get_string('editingquestionnaire', 'questionnaire'), $questionnaire->cm);
 //   print_header_simple(get_string('editingquestionnaire', 'questionnaire'), '', $navigation);
    include('tabs.php');
    $settings_form->display();
    echo $OUTPUT->footer($course);


?>