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
 * Updates the contents of the survey with the provided data. If no data is provided, it checks for posted data.
 *
 * This library replaces the phpESP application with Moodle specific code. It will eventually
 * replace all of the phpESP application, removing the dependency on that.
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');
// Legacy constant aliases — kept for questionnaire.class.php and any code not yet on the new class.
// Canonical values live as private const on mod_questionnaire\questionnaire; use its behavior
// methods (response_frequency_options, response_viewer_options, etc.) for new code.

define('QUESTIONNAIREUNLIMITED', 0);
define('QUESTIONNAIREONCE', 1);
define('QUESTIONNAIREDAILY', 2);
define('QUESTIONNAIREWEEKLY', 3);
define('QUESTIONNAIREMONTHLY', 4);

define('QUESTIONNAIRE_STUDENTVIEWRESPONSES_NEVER', 0);
define('QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED', 1);
define('QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED', 2);
define('QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS', 3);

define('QUESTIONNAIRE_MAX_EVENT_LENGTH', 5 * 24 * 60 * 60);   // 5 days maximum.

define('QUESTIONNAIRE_DEFAULT_PAGE_COUNT', 20);

define('QUESTIONNAIRE_CONFIRM_DELETE_PERMANENTLY', 'confirmdelpermanentlyq');
define('QUESTIONNAIRE_RESTORE_PARAM', 'restoreq');

global $questionnairetypes;
$questionnairetypes = \mod_questionnaire\questionnaire::response_frequency_options();

global $questionnairerespondents;
$questionnairerespondents = [
    'fullname' => get_string('respondenttypefullname', 'questionnaire'),
    'anonymous' => get_string('respondenttypeanonymous', 'questionnaire'),
];

global $questionnairerealms;
$questionnairerealms = [
    'private' => get_string('private', 'questionnaire'),
    'public' => get_string('public', 'questionnaire'),
    'template' => get_string('template', 'questionnaire'),
];

global $questionnaireresponseviewers;
$questionnaireresponseviewers = \mod_questionnaire\questionnaire::response_viewer_options();

global $autonumbering;
$autonumbering = [
    0 => get_string('autonumberno', 'questionnaire'),
    1 => get_string('autonumberquestions', 'questionnaire'),
    2 => get_string('autonumberpages', 'questionnaire'),
    3 => get_string('autonumberpagesandquestions', 'questionnaire'),
];
