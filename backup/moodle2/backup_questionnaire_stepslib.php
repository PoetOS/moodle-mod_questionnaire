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
 * Define all the backup steps that will be used by the backup_questionnaire_activity_task
 */

/**
 * Define the complete choice structure for backup, with file and id annotations
 */
class backup_questionnaire_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $questionnaire = new backup_nested_element('questionnaire', array('id'), array(
            'course', 'name', 'intro', 'introformat', 'qtype',
            'respondenttype', 'resp_eligible', 'resp_view', 'opendate',
            'closedate', 'resume', 'navigate', 'grade', 'sid', 'timemodified'));

        $surveys = new backup_nested_element('surveys');

        $survey = new backup_nested_element('survey', array('id'), array(
            'name', 'owner', 'realm', 'status', 'title', 'email', 'subtitle',
            'info', 'theme', 'thanks_page', 'thank_head', 'thank_body'));

        $questions = new backup_nested_element('questions');

        $question = new backup_nested_element('question', array('id'), array(
            'survey_id', 'name', 'type_id', 'result_id', 'length', 'precise',
            'position', 'content', 'required', 'deleted'));

        $quest_choices = new backup_nested_element('quest_choices');

        $quest_choice = new backup_nested_element('quest_choice', array('id'), array(
            'question_id', 'content', 'value'));

        $attempts = new backup_nested_element('attempts');

        $attempt = new backup_nested_element('attempt', array('id'), array(
            'qid', 'userid', 'rid', 'timemodified'));

        $responses = new backup_nested_element('responses');

        $response = new backup_nested_element('response', array('id'), array(
            'survey_id', 'submitted', 'complete', 'grade', 'username'));

        $response_bools = new backup_nested_element('response_bools');

        $response_bool = new backup_nested_element('response_bool', array('id'), array(
            'response_id', 'question_id', 'choice_id'));

        $response_dates = new backup_nested_element('response_dates');

        $response_date = new backup_nested_element('response_date', array('id'), array(
            'response_id', 'question_id', 'response'));

        $response_multiples = new backup_nested_element('response_multiples');

        $response_multiple = new backup_nested_element('response_multiple', array('id'), array(
            'response_id', 'question_id', 'choice_id'));

        $response_others = new backup_nested_element('response_others');

        $response_other = new backup_nested_element('response_other', array('id'), array(
            'response_id', 'question_id', 'choice_id', 'response'));

        $response_ranks = new backup_nested_element('response_ranks');

        $response_rank = new backup_nested_element('response_rank', array('id'), array(
            'response_id', 'question_id', 'choice_id', 'rank'));

        $response_singles = new backup_nested_element('response_singles');

        $response_single = new backup_nested_element('response_single', array('id'), array(
            'response_id', 'question_id', 'choice_id'));

        $response_texts = new backup_nested_element('response_texts');

        $response_text = new backup_nested_element('response_text', array('id'), array(
            'response_id', 'question_id', 'response'));

        // Build the tree
        $questionnaire->add_child($surveys);
        $surveys->add_child($survey);

        $survey->add_child($questions);
        $questions->add_child($question);

        $question->add_child($quest_choices);
        $quest_choices->add_child($quest_choice);

        $questionnaire->add_child($attempts);
        $attempts->add_child($attempt);

        $attempt->add_child($responses);
        $responses->add_child($response);

        $response->add_child($response_bools);
        $response_bools->add_child($response_bool);

        $response->add_child($response_dates);
        $response_dates->add_child($response_date);

        $response->add_child($response_multiples);
        $response_multiples->add_child($response_multiple);

        $response->add_child($response_others);
        $response_others->add_child($response_other);

        $response->add_child($response_ranks);
        $response_ranks->add_child($response_rank);

        $response->add_child($response_singles);
        $response_singles->add_child($response_single);

        $response->add_child($response_texts);
        $response_texts->add_child($response_text);

        // Define sources
        $questionnaire->set_source_table('questionnaire', array('id' => backup::VAR_ACTIVITYID));

        $survey->set_source_table('questionnaire_survey', array('id' => '../../sid'));

        $question->set_source_table('questionnaire_question', array('survey_id' => backup::VAR_PARENTID));

        $quest_choice->set_source_table('questionnaire_quest_choice', array('question_id' => backup::VAR_PARENTID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $attempt->set_source_table('questionnaire_attempts', array('qid' => backup::VAR_PARENTID));
            $response->set_source_table('questionnaire_response', array('id' => '../../rid'));
            $response_bool->set_source_table('questionnaire_response_bool', array('response_id' => backup::VAR_PARENTID));
            $response_date->set_source_table('questionnaire_response_date', array('response_id' => backup::VAR_PARENTID));
            $response_multiple->set_source_table('questionnaire_resp_multiple', array('response_id' => backup::VAR_PARENTID));
            $response_other->set_source_table('questionnaire_response_other', array('response_id' => backup::VAR_PARENTID));
            $response_rank->set_source_table('questionnaire_response_rank', array('response_id' => backup::VAR_PARENTID));
            $response_single->set_source_table('questionnaire_resp_single', array('response_id' => backup::VAR_PARENTID));
            $response_text->set_source_table('questionnaire_response_text', array('response_id' => backup::VAR_PARENTID));
        }

        // Define id annotations
        $attempt->annotate_ids('user', 'userid');

        // Define file annotations
        $questionnaire->annotate_files('mod_questionnaire', 'intro', null); // This file area hasn't itemid

        $survey->annotate_files('mod_questionnaire', 'info', 'id'); // By survey->id
        $survey->annotate_files('mod_questionnaire', 'thankbody', 'id'); // By survey->id

        $question->annotate_files('mod_questionnaire', 'question', 'id'); // By question->id

        // Return the root element, wrapped into standard activity structure
        return $this->prepare_activity_structure($questionnaire);
    }
}
