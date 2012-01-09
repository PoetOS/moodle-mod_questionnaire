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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_questionnaire_activity_task
 */

/**
 * Structure step to restore one questionnaire activity
 */
class restore_questionnaire_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('questionnaire', '/activity/questionnaire');
        $paths[] = new restore_path_element('questionnaire_survey', '/activity/questionnaire/surveys/survey');
        $paths[] = new restore_path_element('questionnaire_question', '/activity/questionnaire/surveys/survey/questions/question');
        $paths[] = new restore_path_element('questionnaire_quest_choice', '/activity/questionnaire/surveys/survey/questions/question/quest_choices/quest_choice');

        if ($userinfo) {
            $paths[] = new restore_path_element('questionnaire_attempt', '/activity/questionnaire/attempts/attempt');
            $paths[] = new restore_path_element('questionnaire_response', '/activity/questionnaire/attempts/attempt/responses/response');
            $paths[] = new restore_path_element('questionnaire_response_bool', '/activity/questionnaire/attempts/attempt/responses/response/response_bools/response_bool');
            $paths[] = new restore_path_element('questionnaire_response_date', '/activity/questionnaire/attempts/attempt/responses/response/response_dates/response_date');
            $paths[] = new restore_path_element('questionnaire_response_multiple', '/activity/questionnaire/attempts/attempt/responses/response/response_multiples/response_multiple');
            $paths[] = new restore_path_element('questionnaire_response_other', '/activity/questionnaire/attempts/attempt/responses/response/response_others/response_other');
            $paths[] = new restore_path_element('questionnaire_response_rank', '/activity/questionnaire/attempts/attempt/responses/response/response_ranks/response_rank');
            $paths[] = new restore_path_element('questionnaire_response_single', '/activity/questionnaire/attempts/attempt/responses/response/response_singles/response_single');
            $paths[] = new restore_path_element('questionnaire_response_text', '/activity/questionnaire/attempts/attempt/responses/response/response_texts/response_text');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_questionnaire($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the questionnaire record
        $newitemid = $DB->insert_record('questionnaire', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_questionnaire_survey($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->owner = $this->get_courseid();

        // insert the questionnaire_survey record
        $newitemid = $DB->insert_record('questionnaire_survey', $data);
        $this->set_mapping('questionnaire_survey', $oldid, $newitemid, true);

        // Update the questionnaire record we just created with the new survey id
        $DB->set_field('questionnaire', 'sid', $newitemid, array('id' => $this->get_new_parentid('questionnaire')));
    }

    protected function process_questionnaire_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->survey_id = $this->get_new_parentid('questionnaire_survey');

        // insert the questionnaire_question record
        $newitemid = $DB->insert_record('questionnaire_question', $data);
        $this->set_mapping('questionnaire_question', $oldid, $newitemid, true);
    }

    protected function process_questionnaire_quest_choice($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->question_id = $this->get_new_parentid('questionnaire_question');

        // insert the questionnaire_quest_choice record
        $newitemid = $DB->insert_record('questionnaire_quest_choice', $data);
        $this->set_mapping('questionnaire_quest_choice', $oldid, $newitemid);
    }

    protected function process_questionnaire_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->qid = $this->get_new_parentid('questionnaire');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // insert the questionnaire_attempts record
        $newitemid = $DB->insert_record('questionnaire_attempts', $data);
        $this->set_mapping('questionnaire_attempt', $oldid, $newitemid);
    }

    protected function process_questionnaire_response($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->survey_id = $this->get_mappingid('questionnaire_survey', $data->survey_id);
        $data->username = $this->get_mappingid('user', $data->username);

        // insert the questionnaire_response record
        $newitemid = $DB->insert_record('questionnaire_response', $data);
        $this->set_mapping('questionnaire_response', $oldid, $newitemid);

        // Update the questionnaire_attempts record we just created with the new response id
        $DB->set_field('questionnaire_attempts', 'rid', $newitemid, array('id' => $this->get_new_parentid('questionnaire_attempt')));
    }

    protected function process_questionnaire_response_bool($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questionnaire_response');
        $data->question_id = $this->get_mappingid('questionnaire_question', $data->question_id);

        // insert the questionnaire_response_bool record
        $newitemid = $DB->insert_record('questionnaire_response_bool', $data);
    }

    protected function process_questionnaire_response_date($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questionnaire_response');
        $data->question_id = $this->get_mappingid('questionnaire_question', $data->question_id);

        // insert the questionnaire_response_date record
        $newitemid = $DB->insert_record('questionnaire_response_date', $data);
    }

    protected function process_questionnaire_response_multiple($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questionnaire_response');
        $data->question_id = $this->get_mappingid('questionnaire_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questionnaire_quest_choice', $data->choice_id);

        // insert the questionnaire_resp_multiple record
        $newitemid = $DB->insert_record('questionnaire_resp_multiple', $data);
    }

    protected function process_questionnaire_response_other($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questionnaire_response');
        $data->question_id = $this->get_mappingid('questionnaire_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questionnaire_quest_choice', $data->choice_id);

        // insert the questionnaire_response_other record
        $newitemid = $DB->insert_record('questionnaire_response_other', $data);
    }

    protected function process_questionnaire_response_rank($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questionnaire_response');
        $data->question_id = $this->get_mappingid('questionnaire_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questionnaire_quest_choice', $data->choice_id);

        // insert the questionnaire_response_rank record
        $newitemid = $DB->insert_record('questionnaire_response_rank', $data);
    }

    protected function process_questionnaire_response_single($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questionnaire_response');
        $data->question_id = $this->get_mappingid('questionnaire_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('questionnaire_quest_choice', $data->choice_id);

        // insert the questionnaire_resp_single record
        $newitemid = $DB->insert_record('questionnaire_resp_single', $data);
    }

    protected function process_questionnaire_response_text($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->response_id = $this->get_new_parentid('questionnaire_response');
        $data->question_id = $this->get_mappingid('questionnaire_question', $data->question_id);

        // insert the questionnaire_response_text record
        $newitemid = $DB->insert_record('questionnaire_response_text', $data);
    }

    protected function after_execute() {
        // Add questionnaire related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_questionnaire', 'intro', null);
        $this->add_related_files('mod_questionnaire', 'info', 'questionnaire_survey');
        $this->add_related_files('mod_questionnaire', 'thankbody', 'questionnaire_survey');
        $this->add_related_files('mod_questionnaire', 'question', 'questionnaire_question');
    }
}
