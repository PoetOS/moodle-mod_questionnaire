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
 * Show the non-respondents to a questionnaire.
 * @author Joseph Rézeau (copied from feedback plugin show_nonrespondents by original author Andreas Grabs)
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questionnaire
 *
 */

require_once("../../config.php");

use mod_questionnaire\local\report\nonrespondent_messenger;
use mod_questionnaire\local\response\questionnaire_responses;
use mod_questionnaire\output\nonrespondents_table;
use mod_questionnaire\output\nonrespondentspage;
use mod_questionnaire\questionnaire;

$id = required_param('id', PARAM_INT);
$subject = optional_param('subject', '', PARAM_TEXT);
$message = optional_param('message', '', PARAM_CLEANHTML);
$messageuser = optional_param_array('messageuser', [], PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$selectedanonymous = optional_param('selectedanonymous', '', PARAM_ALPHA);
$perpage = optional_param('perpage', questionnaire::default_page_count(), PARAM_INT);
$showall = optional_param('showall', 0, PARAM_INT);

$questionnaire = questionnaire::from_cmid($id);
$course = $questionnaire->course();
$cm = $questionnaire->coursemodule();
$context = $questionnaire->context();

require_course_login($course, true, $cm);
require_capability('mod/questionnaire:viewsingleresponse', $context);

$PAGE->set_url(new moodle_url('/mod/questionnaire/show_nonrespondents.php', ['id' => $cm->id]));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($questionnaire->name()));
$renderer = $PAGE->get_renderer('mod_questionnaire');

$fullname = $questionnaire->respondenttype() == 'fullname';
$messenger = new nonrespondent_messenger($questionnaire);

// Named questionnaires honour the activity's group mode; anonymous ones never reveal groups.
$groupselect = '';
$groupid = false;
if ($fullname) {
    $groupselect = groups_print_activity_menu($cm, $PAGE->url->out(), true);
    if (groups_get_activity_groupmode($cm, $course) && ($mygroupid = groups_get_activity_group($cm)) > 0) {
        $groupid = $mygroupid;
    }
}
$nonrespondents = questionnaire_responses::get_incomplete_users($cm, $questionnaire->surveyid(), $groupid) ?: [];

$error = null;
if ($action == 'sendmessage') {
    require_sesskey();
    require_capability('mod/questionnaire:message', $context);
    $recipients = $fullname
        ? array_values(array_intersect($messageuser, $nonrespondents))
        : $messenger->recipients_for_selection($selectedanonymous, $nonrespondents);
    if ($subject === '' || trim(html_to_text($message)) === '') {
        $error = get_string('messagefieldsrequired', 'questionnaire');
    } else if (empty($recipients)) {
        $error = get_string('nousersselected', 'questionnaire');
    } else {
        $sent = $messenger->send($recipients, $subject, $messenger->message_body($renderer, $message), $USER);
        redirect(
            new moodle_url('/mod/questionnaire/view.php', ['id' => $cm->id]),
            get_string($sent ? 'messagedselectedusers' : 'messagedselectedusersfailed'),
            null,
            $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
        );
    }
}

$count = count($nonrespondents);
$page = new nonrespondentspage($questionnaire, $count, $groupselect);
if ($fullname && $count > 0) {
    $table = new nonrespondents_table(
        $questionnaire,
        new moodle_url('/mod/questionnaire/show_nonrespondents.php', ['id' => $cm->id, 'showall' => $showall])
    );
    $table->setup();
    if (!$showall) {
        $table->pagesize($perpage, $count);
    }
    $pageusers = questionnaire_responses::get_incomplete_users(
        $cm,
        $questionnaire->surveyid(),
        $groupid,
        $table->get_sql_sort(),
        $showall ? false : $table->get_page_start(),
        $showall ? false : $table->get_page_size()
    ) ?: [];
    $users = $DB->get_records_list('user', 'id', $pageusers);
    $started = $messenger->started_userids($pageusers);
    foreach ($pageusers as $userid) {
        $table->add_user($users[$userid], in_array($userid, $started));
    }

    $toggleurl = null;
    $togglelabel = '';
    if ($showall) {
        $toggleurl = new moodle_url($PAGE->url, ['showall' => 0]);
        $togglelabel = get_string('showperpage', '', questionnaire::default_page_count());
    } else if ($perpage < $count) {
        $toggleurl = new moodle_url($PAGE->url, ['showall' => 1]);
        $togglelabel = get_string('showall', '', $count);
    }
    $page->set_table($table, $showall || $perpage >= $count, $toggleurl, $togglelabel);
} else if (!$fullname) {
    $page->set_started_count(count($messenger->started_userids($nonrespondents)));
}
$page->set_submission($subject, $message, $selectedanonymous, $error);

echo $renderer->header();
echo $renderer->render($page);
echo $renderer->footer();

\mod_questionnaire\event\non_respondents_viewed::create([
    'objectid' => $questionnaire->id(),
    'anonymous' => !$fullname,
    'context' => $context,
])->trigger();
