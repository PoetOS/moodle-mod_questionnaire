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


class mod_questionnaire_generator extends testing_module_generator {

    /**
     * Create a questionnaire activity.
     * @param array $record
     * @param array $options
     * @return int
     */
    public function create_instance($record = array(), array $options = null) {
        $record = (object)$record;

        $defaultquestionnairesettings = array(
            'qtype'                 => 0,
            'respondenttype'        => 'fullname',
            'resp_eligible'         => 'all',
            'resp_view'             => 0,
            'opendate'              => 0,
            'closedate'             => 0,
            'resume'                => 0,
            'navigate'              => 0,
            'grade'                 => 0,
            'sid'                   => 0,
            'timemodified'          => time(),
            'completionsubmit'      => 0,
            'autonum'               => 3,
            'create'                => 'new-0',        // Used in form only to indicate a new, empty instance.
        );

        foreach ($defaultquestionnairesettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Create a survey instance with data from an existing questionnaire object.
     * @param object $questionnaire
     * @param array $options
     * @return int
     */
    public function create_content($questionnaire, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

        $survey = $DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid), '*', MUST_EXIST);
        foreach ($record as $name => $value) {
            $survey->{$name} = $value;
        }
        return $questionnaire->survey_update($survey);
    }

    /**
     * Create an default question as a generic object.
     * @param array $data
     * @return object
     */
    public function create_question($questiondata = array(), $choicedata = array()) {
        // NOTE - Currently, there is no API to create a question record.
        // NOTE - The code does this in the "questions.php" page. This needs to be changed.
        global $DB;

        // Construct a new question object.
        $question = new stdClass();
        $question->id = 0;
        $question->survey_id = 0;
        $question->name = '';
        $question->type_id = 0;
        $question->length = 0;
        $question->precise = 0;
        $question->position = 0;
        $question->content = '';
        $question->required = 'n';
        $question->deleted = 'n';
        $question->dependquestion = 0;
        $question->dependchoice = 0;

        foreach ($questiondata as $name => $value) {
            $question->{$name} = $value;
        }
        $question->id = $DB->insert_record('questionnaire_question', $question);

        // Handle any choice data provided.
        foreach ($choicedata as $content => $value) {
            $this->create_question_choice(array('question_id' => $question->id,
                                                'content' => $content,
                                                'value' => $value));
        }

        return $question;
    }

    /**
     * Create question choices as a generic object.
     * @param array $data
     * @return object
     */
    public function create_question_choice($data = array()) {
        // *** Currently, there is no API to create a question choice records.
        // *** The code does this in the "questions.php" page. This needs to be changed.
        global $DB;

        // Construct a new question object.
        $choice = new stdClass();
        $choice->id = 0;
        $choice->question_id = 0;
        $choice->content = '';
        $choice->value = '';

        foreach ($data as $name => $value) {
            $choice->{$name} = $value;
        }

        // Currently, there is no API to create the record. The code does this in the page. This needs to be fixed.
        $choice->id = $DB->insert_record('questionnaire_quest_choice', $choice);
        return $choice;
    }

    /**
     * Create a check box question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @param array $choicedata
     * @return object
     */
    public function create_question_checkbox($surveyid, $questiondata = array(), $choicedata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $question = $this->create_question(array('survey_id' => $surveyid, 'type_id' => QUESCHECK) + $questiondata, $choicedata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a date question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @return object
     */
    public function create_question_date($surveyid, $questiondata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $question = $this->create_question(array('survey_id' => $surveyid, 'type_id' => QUESDATE) + $questiondata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a dropdown question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @param array $choicedata
     * @return object
     */
    public function create_question_dropdown($surveyid, $questiondata = array(), $choicedata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $question = $this->create_question(array('survey_id' => $surveyid, 'type_id' => QUESDROP) + $questiondata, $choicedata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create an essay question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @return object
     */
    public function create_question_essay($surveyid, $questiondata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $questiondata['survey_id'] = $surveyid;
        $questiondata['type_id'] = QUESESSAY;
        $questiondata['length'] = 0;
        $questiondata['precise'] = 5;
        $question = $this->create_question($questiondata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a sectiontext question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @return object
     */
    public function create_question_sectiontext($surveyid, $questiondata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $question = $this->create_question(array('survey_id' => $surveyid, 'type_id' => QUESSECTIONTEXT) + $questiondata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a numeric question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @return object
     */
    public function create_question_numeric($surveyid, $questiondata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $questiondata['survey_id'] = $surveyid;
        $questiondata['type_id'] = QUESNUMERIC;
        $questiondata['length'] = 10;
        $questiondata['precise'] = 0;
        $question = $this->create_question($questiondata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a radio button question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @param array $choicedata
     * @return object
     */
    public function create_question_radiobuttons($surveyid, $questiondata = array(), $choicedata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $question = $this->create_question(array('survey_id' => $surveyid, 'type_id' => QUESRADIO) + $questiondata, $choicedata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a ratescale question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @param array $choicedata
     * @return object
     */
    public function create_question_ratescale($surveyid, $questiondata = array(), $choicedata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $question = $this->create_question(array('survey_id' => $surveyid, 'type_id' => QUESRATE) + $questiondata, $choicedata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a textbox question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @return object
     */
    public function create_question_textbox($surveyid, $questiondata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $questiondata['survey_id'] = $surveyid;
        $questiondata['type_id'] = QUESTEXT;
        $questiondata['length'] = 20;
        $questiondata['precise'] = 25;
        $question = $this->create_question($questiondata);
        return new questionnaire_question($question->id);
    }

    /**
     * Create a yes/no question type as a question object.
     * @param integer $surveyid
     * @param array $questiondata
     * @return object
     */
    public function create_question_yesno($surveyid, $questiondata = array()) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $question = $this->create_question(array('survey_id' => $surveyid, 'type_id' => QUESYESNO) + $questiondata);
        return new questionnaire_question($question->id);
    }
}