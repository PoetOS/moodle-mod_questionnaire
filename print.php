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
 * The main page to print a questionnaire.
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once("../../config.php");

use mod_questionnaire\questionnaire;

$qid = required_param('qid', PARAM_INT);
$rid = required_param('rid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sec = required_param('sec', PARAM_INT);
$referer = '/mod/questionnaire/report.php';

$questionnaire = questionnaire::from_instanceid($qid);

// Check login and get context.
require_login($courseid);

$renderer = $PAGE->get_renderer('mod_questionnaire');
$page = !empty($rid)
    ? new \mod_questionnaire\output\reportpage()
    : new \mod_questionnaire\output\previewpage();

// If you can't view the questionnaire, or can't view a specified response, error out.
if (!($questionnaire->capabilities()->can_view() && (($rid == 0) || $questionnaire->capabilities()->can_view_response($rid)))) {
    // Should never happen, unless called directly by a snoop...
    throw new \moodle_exception('nopermissions', 'mod_questionnaire');
}
$blankquestionnaire = ($rid == 0);

$url = new moodle_url('/mod/questionnaire/print.php');
$url->param('qid', $qid);
$url->param('rid', $rid);
$url->param('courseid', $courseid);
$url->param('sec', $sec);
$PAGE->set_url($url);
$PAGE->set_title($questionnaire->surveytitle());
$PAGE->set_pagelayout('popup');
echo $renderer->header();
$page->add_to_page('closebutton', $renderer->close_window_button());
(new \mod_questionnaire\local\report\report_view_builder($renderer, $page))
    ->build_print_view($questionnaire, $courseid, '', 'print', $rid, $blankquestionnaire);
echo $renderer->render($page);
echo $renderer->footer();
