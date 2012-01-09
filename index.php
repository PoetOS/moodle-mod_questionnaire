<?php // $Id$
/// This page lists all the instances of Questionnaire in a particular course


    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id', PARAM_INT);

    if (! $course = get_record("course", "id", $id)) {
        error(get_string('incorrectcourseid', 'questionnaire'));
    }

    require_login($course->id);

    add_to_log($course->id, "questionnaire", "view all", "index.php?id=$course->id", "");


/// Get all required strings

    $strquestionnaires = get_string("modulenameplural", "questionnaire");
    $strquestionnaire  = get_string("modulename", "questionnaire");


/// Print the header
    $navigation = build_navigation(array(array('name' => $strquestionnaires, 'link' => '', 'type' => 'activity')));    
    print_header("$course->shortname: $strquestionnaires", "$course->fullname", $navigation, "", "", true, "", navmenu($course));

/// Get all the appropriate data

    if (! $questionnaires = get_all_instances_in_course("questionnaire", $course)) {
        notice("There are no questionnaires", "../../course/view.php?id=$course->id");
        die;
    }

/// Print the list of instances (your module will probably extend this)

    $timenow = time();
    $strname  = get_string("name");
    $strsummary = get_string("summary");
    $strtype = get_string('realm', 'questionnaire');

    $table->head  = array ($strname, $strsummary, $strtype);
    $table->align = array ("LEFT", "LEFT", 'LEFT');

    foreach ($questionnaires as $questionnaire) {
        $realm = get_field('questionnaire_survey', 'realm', 'id', $questionnaire->sid);
        // template surveys should NOT be displayed as an activity to students
        if (!($realm == 'template' && !has_capability('mod/questionnaire:manage',get_context_instance(CONTEXT_MODULE,$questionnaire->coursemodule)))) {
            if (!$questionnaire->visible) {
                //Show dimmed if the mod is hidden
                $link = "<a class=\"dimmed\" href=\"view.php?id=$questionnaire->coursemodule\">$questionnaire->name</a>";
            } else {
                //Show normal if the mod is visible
                $link = "<a href=\"view.php?id=$questionnaire->coursemodule\">$questionnaire->name</a>";
            }
    
            $qtype = get_field('questionnaire_survey', 'realm', 'id', $questionnaire->sid);
            $table->data[] = array ($link, $questionnaire->summary, get_string($qtype,'questionnaire'));
        }
    }

    echo "<br />";

    print_table($table);

/// Finish the page

    print_footer($course);

?>
