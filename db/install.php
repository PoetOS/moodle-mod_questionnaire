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
 * The file containing the install functions.
 * @package    mod_questionnaire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * This file is executed right after the install.xml
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The install function.
 */
function xmldb_questionnaire_install() {
    global $DB;

    // Initial insert of mnet applications info.
    $questiontype = new stdClass();
    $questiontype->typeid = 1;
    $questiontype->type = 'Yes/No';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = 'response_bool';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 2;
    $questiontype->type = 'Text Box';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = 'response_text';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 3;
    $questiontype->type = 'Essay Box';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = 'response_text';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 4;
    $questiontype->type = 'Radio Buttons';
    $questiontype->haschoices = 'y';
    $questiontype->responsetable = 'resp_single';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 5;
    $questiontype->type = 'Check Boxes';
    $questiontype->haschoices = 'y';
    $questiontype->responsetable = 'resp_multiple';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 6;
    $questiontype->type = 'Dropdown Box';
    $questiontype->haschoices = 'y';
    $questiontype->responsetable = 'resp_single';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 8;
    $questiontype->type = 'Rate (scale 1..5)';
    $questiontype->haschoices = 'y';
    $questiontype->responsetable = 'response_rank';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 9;
    $questiontype->type = 'Date';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = 'response_date';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 10;
    $questiontype->type = 'Numeric';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = 'response_text';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 11;
    $questiontype->type = 'Slider';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = 'response_text';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 12;
    $questiontype->type = 'File';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = 'response_file';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 99;
    $questiontype->type = 'Page Break';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = '';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);

    $questiontype = new stdClass();
    $questiontype->typeid = 100;
    $questiontype->type = 'Section Text';
    $questiontype->haschoices = 'n';
    $questiontype->responsetable = '';
    $id = $DB->insert_record('questionnaire_question_type', $questiontype);
}
