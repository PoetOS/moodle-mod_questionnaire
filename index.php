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

/// This page lists all the instances of Questionnaire in a particular course


    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id', PARAM_INT);

    if (! $course = $DB->get_record('course', array('id' => $id))) {
        print_error('incorrectcourseid', 'questionnaire');
    }

    require_login($course->id);

    add_to_log($course->id, "questionnaire", "view all", "index.php?id=$course->id", "");


/// Get all required strings

    $strquestionnaires = get_string("modulenameplural", "questionnaire");
    $strquestionnaire  = get_string("modulename", "questionnaire");

    $url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/index.php', array('id' => $id));

    $PAGE->set_url($url);
    $PAGE->set_title("$course->shortname: $strquestionnaires");
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->navbar->add($strquestionnaires);
    echo $OUTPUT->header();

/// Get all the appropriate data

    if (! $questionnaires = $DB->get_records('questionnaire', array('course' => $course->id))) {
        notice("There are no questionnaires", "../../course/view.php?id=$course->id");
        die;
    }

    $modinfo =& get_fast_modinfo($course);

    if (!isset($modinfo->instances['questionnaire'])) {
        $modinfo->instances['questionnaire'] = array();
    }

/// Print the list of instances (your module will probably extend this)

    $timenow = time();
    $strname  = get_string("name");
    $strsummary = get_string("summary");
    $strtype = get_string('realm', 'questionnaire');

    $table = new html_table();
    $table->head  = array ($strname, $strsummary, $strtype);
    $table->align = array ("LEFT", "LEFT", 'LEFT');

    foreach ($modinfo->instances['questionnaire'] as $questionnaireid=>$cm) {

        if (!$cm->uservisible or !isset($questionnaires[$questionnaireid])) {
            continue;
        }

        $questionnaire = $questionnaires[$questionnaireid];
        $realm = $DB->get_field('questionnaire_survey', 'realm', array('id' => $questionnaire->sid));
        // template surveys should NOT be displayed as an activity to students
        if (!($realm == 'template' && !has_capability('mod/questionnaire:manage',get_context_instance(CONTEXT_MODULE,$questionnaire->coursemodule)))) {
            //Show normal if the mod is visible
            $link = "<a href=\"view.php?id=$cm->id\">$questionnaire->name</a>";
            $intro = format_module_intro('questionnaire', $questionnaire, $cm->id);
            $qtype = $DB->get_field('questionnaire_survey', 'realm', array('id' => $questionnaire->sid));
            $table->data[] = array ($link, $intro, get_string($qtype,'questionnaire'));
        }
    }

    echo "<br />";

    echo html_writer::table($table);

/// Finish the page

    echo $OUTPUT->footer();