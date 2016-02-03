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
 * mod_questionnaire data generator
 *
 * @package    mod_questionnaire
 * @copyright  2015 Mike Churchward (mike@churchward.ca)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

class mod_questionnaire_generator extends testing_module_generator {

    /**
     * Create a questionnaire activity.
     * @param array $record Will be changed in this function.
     * @param array $options
     * @return int
     */
    public function create_instance($record = array(), array $options = array()) {
        global $COURSE; // Needed for add_instance.

        if (is_array($record)) {
            $record = (object)$record;
        }

        $defaultquestionnairesettings = array(
            'qtype'                 => 0,
            'respondenttype'        => 'fullname',
            'resp_eligible'         => 'all',
            'resp_view'             => 0,
            'useopendate'           => true, // Used in form only to indicate opendate can be used.
            'opendate'              => 0,
            'useclosedate'          => true, // Used in form only to indicate closedate can be used.
            'closedate'             => 0,
            'resume'                => 0,
            'navigate'              => 0,
            'grade'                 => 0,
            'sid'                   => 0,
            'timemodified'          => time(),
            'completionsubmit'      => 0,
            'autonum'               => 3,
            'create'                => 'new-0', // Used in form only to indicate a new, empty instance.
        );

        foreach ($defaultquestionnairesettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        if (isset($record->course)) {
            $COURSE->id = $record->course;
        }

        return parent::create_instance($record, $options);
    }

    /**
     * Create a survey instance with data from an existing questionnaire object.
     * @param object $questionnaire
     * @param array $options
     * @return int
     */
    public function create_content($questionnaire, $record = array()) {
        global $DB;

        $survey = $DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid), '*', MUST_EXIST);
        foreach ($record as $name => $value) {
            $survey->{$name} = $value;
        }
        return $questionnaire->survey_update($survey);
    }

    /**
     * Create a question object of a specific question type and add it to the database.
     * @param integer $qtype The question type to create.
     * @param array $questiondata Any data to load into the question.
     * @param array $choicedata Any choice data for the question.
     * @return object
     */
    public function create_question($qtype, $questiondata = array(), $choicedata = array()) {
        // Construct a new question object.
        $question = questionnaire_question_base::question_builder($qtype);
        $questiondata = (object)$questiondata;
        $question->add($questiondata, $choicedata);

        return $question;
    }

    /**
     * Create a questionnaire with questions and response data for use in other tests.
     */
    public function create_test_questionnaire($course, $qtype = null, $questiondata = array(), $choicedata = null) {
        $questionnaire = $this->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        if (!is_null($qtype)) {
            $questiondata['survey_id'] = $questionnaire->sid;
            $questiondata['name'] = isset($questiondata['name']) ? $questiondata['name'] : 'Q1';
            $questiondata['content'] = isset($questiondata['content']) ? $questiondata['content'] : 'Test content';
            $this->create_question($qtype, $questiondata, $choicedata);
        }

        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);

        return $questionnaire;
    }

    /**
     * Create a reponse to the supplied question.
     */
    public function create_question_response($questionnaire, $question, $respval, $userid = 1, $section = 1) {
        global $DB;

        $currentrid = 0;
        $_POST['q'.$question->id] = $respval;
        $responseid = $questionnaire->response_insert($question->survey_id, $section, $currentrid, $userid);
        $this->response_commit($questionnaire, $responseid);
        questionnaire_record_submission($questionnaire, $userid, $responseid);
        return $DB->get_record('questionnaire_response', array('id' => $responseid));
    }

    /**
     * Need to create a method to access a private questionnaire method.
     */
    private function response_commit($questionnaire, $responseid) {
        $method = new ReflectionMethod('questionnaire', 'response_commit');
        $method->setAccessible(true);
        return $method->invoke($questionnaire, $responseid);
    }
}