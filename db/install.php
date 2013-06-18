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

/*
 *
 * @package    mod
 * @subpackage questionnaire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * This file is executed right after the install.xml
 * @copyright  2010 Remote Learner (http://www.remote-learner.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_questionnaire_install() {
    global $CFG, $DB, $SITE;

    // Initial insert of mnet applications info.
    $question_type = new stdClass();
    $question_type->typeid = 1;
    $question_type->type = 'Yes/No';
    $question_type->has_choices = 'n';
    $question_type->response_table = 'response_bool';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 2;
    $question_type->type = 'Text Box';
    $question_type->has_choices = 'n';
    $question_type->response_table = 'response_text';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 3;
    $question_type->type = 'Essay Box';
    $question_type->has_choices = 'n';
    $question_type->response_table = 'response_text';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 4;
    $question_type->type = 'Radio Buttons';
    $question_type->has_choices = 'y';
    $question_type->response_table = 'resp_single';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 5;
    $question_type->type = 'Check Boxes';
    $question_type->has_choices = 'y';
    $question_type->response_table = 'resp_multiple';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 6;
    $question_type->type = 'Dropdown Box';
    $question_type->has_choices = 'y';
    $question_type->response_table = 'resp_single';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 8;
    $question_type->type = 'Rate (scale 1..5)';
    $question_type->has_choices = 'y';
    $question_type->response_table = 'response_rank';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 9;
    $question_type->type = 'Date';
    $question_type->has_choices = 'n';
    $question_type->response_table = 'response_date';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 10;
    $question_type->type = 'Numeric';
    $question_type->has_choices = 'n';
    $question_type->response_table = 'response_text';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 99;
    $question_type->type = 'Page Break';
    $question_type->has_choices = 'n';
    $question_type->response_table = '';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

    $question_type = new stdClass();
    $question_type->typeid = 100;
    $question_type->type = 'Section Text';
    $question_type->has_choices = 'n';
    $question_type->response_table = '';
    $id = $DB->insert_record('questionnaire_question_type', $question_type);

}