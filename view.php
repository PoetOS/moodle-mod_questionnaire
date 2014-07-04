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

require_once("../../config.php");
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$SESSION->questionnaire->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // Or questionnaire ID.

$sid = optional_param('sid', null, PARAM_INT);  // Survey id.

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

// Check login and get context.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

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
$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

$PAGE->set_title(format_string($questionnaire->name));

$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

echo $OUTPUT->heading(format_text($questionnaire->name));

// Print the main part of the page.
if ($questionnaire->intro) {
    echo $OUTPUT->box(format_module_intro('questionnaire', $questionnaire, $cm->id), 'generalbox', 'intro');
}

echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

$cm = $questionnaire->cm;
$currentgroupid = groups_get_activity_group($cm);
if (!groups_is_member($currentgroupid, $USER->id)) {
    $currentgroupid = 0;
}

if (!$questionnaire->is_active()) {
    if ($questionnaire->capabilities->manage) {
        $msg = 'removenotinuse';
    } else {
        $msg = 'notavail';
    }
    echo '<div class="message">'
    .get_string($msg, 'questionnaire')
    .'</div>';

} else if (!$questionnaire->is_open()) {
    echo '<div class="message">'
    .get_string('notopen', 'questionnaire', userdate($questionnaire->opendate))
    .'</div>';
} else if ($questionnaire->is_closed()) {
    echo '<div class="message">'
    .get_string('closed', 'questionnaire', userdate($questionnaire->closedate))
    .'</div>';
} else if ($questionnaire->survey->realm == 'template') {
    print_string('templatenotviewable', 'questionnaire');
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($questionnaire->course);
    exit();
} else if (!$questionnaire->user_is_eligible($USER->id)) {
    if ($questionnaire->questions) {
        echo '<div class="message">'.get_string('noteligible', 'questionnaire').'</div>';
    }
} else if (!$questionnaire->user_can_take($USER->id)) {
    switch ($questionnaire->qtype) {
        case QUESTIONNAIREDAILY:
            $msgstring = ' '.get_string('today', 'questionnaire');
            break;
        case QUESTIONNAIREWEEKLY:
            $msgstring = ' '.get_string('thisweek', 'questionnaire');
            break;
        case QUESTIONNAIREMONTHLY:
            $msgstring = ' '.get_string('thismonth', 'questionnaire');
            break;
        default:
            $msgstring = '';
            break;
    }
    echo ('<div class="message">'.get_string("alreadyfilled", "questionnaire", $msgstring).'</div>');
} else if ($questionnaire->user_can_take($USER->id)) {
    $select = 'survey_id = '.$questionnaire->survey->id.' AND username = \''.$USER->id.'\' AND complete = \'n\'';
    $resume = $DB->get_record_select('questionnaire_response', $select, null) !== false;
    if (!$resume) {
        $complete = get_string('answerquestions', 'questionnaire');
    } else {
        $complete = get_string('resumesurvey', 'questionnaire');
    }
    if ($questionnaire->questions) { // Sanity check.
        echo '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questionnaire/complete.php?'.
        'id='.$questionnaire->cm->id.'&resume='.$resume).'">'.$complete.'</a>';
    }
}
if ($questionnaire->is_active() && !$questionnaire->questions) {
    echo '<p>'.get_string('noneinuse', 'questionnaire').'</p>';
}
if ($questionnaire->is_active() && $questionnaire->capabilities->editquestions && !$questionnaire->questions) { // Sanity check.
    echo '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questionnaire/questions.php?'.
                'id='.$questionnaire->cm->id).'">'.'<strong>'.get_string('addquestions', 'questionnaire').'</strong></a>';
}
echo $OUTPUT->box_end();
if (isguestuser()) {
    $output = '';
    $guestno = html_writer::tag('p', get_string('noteligible', 'questionnaire'));
    $liketologin = html_writer::tag('p', get_string('liketologin'));
    $output .= $OUTPUT->confirm($guestno."\n\n".$liketologin."\n", get_login_url(),
            get_referer(false));
    echo $output;
}

// Log this course module view.
// Needed for the event logging.
$context = context_module::instance($questionnaire->cm->id);
$anonymous = $questionnaire->respondenttype == 'anonymous';

$event = \mod_questionnaire\event\course_module_viewed::create(array(
                'objectid' => $questionnaire->id,
                'anonymous' => $anonymous,
                'context' => $context
));
$event->trigger();

$usernumresp = $questionnaire->count_submissions($USER->id);

if ($questionnaire->capabilities->readownresponses && ($usernumresp > 0)) {
    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
    $argstr = 'instance='.$questionnaire->id.'&user='.$USER->id;
    if ($usernumresp > 1) {
        $titletext = get_string('viewyourresponses', 'questionnaire', $usernumresp);
    } else {
        $titletext = get_string('yourresponse', 'questionnaire');
        $argstr .= '&byresponse=1&action=vresp';
    }

    echo '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questionnaire/myreport.php?'.
        $argstr).'">'.$titletext.'</a>';
    echo $OUTPUT->box_end();
}

if ($survey = $DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid))) {
    $owner = (trim($survey->owner) == trim($course->id));
} else {
    $survey = false;
    $owner = true;
}
$numresp = $questionnaire->count_submissions();

// Number of Responses in currently selected group (or all participants etc.).
if (isset($SESSION->questionnaire->numselectedresps)) {
    $numselectedresps = $SESSION->questionnaire->numselectedresps;
} else {
    $numselectedresps = $numresp;
}

// If questionnaire is set to separate groups, prevent user who is not member of any group
// to view All responses.
$canviewgroups = true;
$groupmode = groups_get_activity_groupmode($cm, $course);
if ($groupmode == 1) {
    $canviewgroups = groups_has_membership($cm, $USER->id);;
}

$canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
if (( (
            // Teacher or non-editing teacher (if can view all groups).
            $canviewallgroups ||
            // Non-editing teacher (with canviewallgroups capability removed), if member of a group.
            ($canviewgroups && $questionnaire->capabilities->readallresponseanytime))
            && $numresp > 0 && $owner && $numselectedresps > 0) ||
            $questionnaire->capabilities->readallresponses && ($numresp > 0) && $canviewgroups &&
            ($questionnaire->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                    ($questionnaire->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED
                            && $questionnaire->is_closed()) ||
                    ($questionnaire->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED
                            && $usernumresp > 0)) &&
            $questionnaire->is_survey_owner()) {
    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
    $argstr = 'instance='.$questionnaire->id.'&group='.$currentgroupid;
    echo '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.
            $argstr).'">'.get_string('viewallresponses', 'questionnaire').'</a>';
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
