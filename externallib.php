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
 * External questionnaire API
 *
 * @package    mod_questionnaire
 * @copyright  2015 Antonio Carlos Mariani [antonio.c.mariani@ufsc.br]
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/externallib.php");
require_once("{$CFG->dirroot}/mod/questionnaire/questionnaire.class.php");
require_once("{$CFG->dirroot}/mod/questionnaire/questiontypes/questiontypes.class.php");

class mod_questionnaire_external extends external_api {

    public static function get_questionnaire_parameters() {
        return new external_function_parameters (
                    array('cmid' => new external_value(PARAM_INT, 'Course module id')));
    }

    public static function get_questionnaire($cmid) {
        global $DB;

        $params = self::validate_parameters(self::get_questionnaire_parameters(), array('cmid'=>$cmid));

        if (!$m = $DB->get_record('modules', array('name'=>'questionnaire'))) {
            throw new moodle_exception('invalidcoursemodule', '', '', $cmid);
        }
        if (!$cm = $DB->get_record('course_modules', array('id'=>$cmid, 'module'=>$m->id))) {
            throw new moodle_exception('invalidcoursemodule', '', '', $cmid);
        }

        // Get the module context.
        $modcontext = context_module::instance($cm->id);
        require_capability('mod/questionnaire:view', $modcontext);

        $questionnaire = new questionnaire($cm->instance, null, $course, $cm);

        $result = array(
            'id' => $questionnaire->id,
            'courseid' => $questionnaire->courseid,
            'name' => $questionnaire->name,
            'intro' => $questionnaire->intro,
            'introformat' => $questionnaire->introformat,
            'opendate' => $questionnaire->opendate,
            'closedate' => $questionnaire->closedate,
            'qtype' => $questionnaire->qtype,
            'respondenttype' => $questionnaire->respondenttype,
            'resp_eligible' => $questionnaire->resp_eligible,
            'resp_view' => $questionnaire->resp_view,
            'resume' => $questionnaire->resume == 1,
            'navigate' => $questionnaire->navigate == 1,
            'autonum' => $questionnaire->autonum,
            'grade' => $questionnaire->grade,
            );

        $survey = array(
            'id' => $questionnaire->survey->id,
            'name' => $questionnaire->survey->name,
            'realm' => $questionnaire->survey->realm,
            'title' => $questionnaire->survey->title,
            'subtitle' => $questionnaire->survey->subtitle,
            'info' => $questionnaire->survey->info,
            'email' => empty($questionnaire->survey->email) ? '' : $questionnaire->survey->email,
            'thanks_page' => empty($questionnaire->survey->thanks_page) ? '' : $questionnaire->survey->thanks_page,
            'thanks_head' => empty($questionnaire->survey->thanks_head) ? '' : $questionnaire->survey->thanks_head,
            'thanks_body' => empty($questionnaire->survey->thanks_body) ? '' : $questionnaire->survey->thanks_body,
            'feedbacksections' => $questionnaire->survey->feedbacksections,
            'feedbackscores' => $questionnaire->survey->feedbackscores == 1,
            'feedbacknotes' => $questionnaire->survey->feedbacknotes,
            );
        $result['survey'] = $survey;

        $questions = array();
        foreach ($questionnaire->questions as $qidx => $question) {
            $q = new stdClass();
            $q->id = $question->id;
            $q->name = $question->name;
            $q->typeid = $question->type_id;
            $q->typename = $question->type;
            $q->length = $question->length;
            $q->precise = $question->precise;
            $q->position = $question->position;
            $q->content = $question->content;
            $q->required = $question->required == 'y';
            $q->deleted = $question->deleted == 'y';
            $q->dependquestion = $question->dependquestion;
            $q->dependchoice = $question->dependchoice;

            if (isset($question->choices) && !empty($question->choices)) {
                $q->choices = array();
                foreach($question->choices AS $chid=>$ch) {
                    $choice = new stdClass();
                    $choice->choiceid = $chid;
                    $choice->content = $ch->content;
                    $choice->value = empty($ch->value) ? '' : $ch->value;
                    $q->choices[] = $choice;
                }
            }

            $questions[] = $q;
        }
        $result['questions'] = $questions;

        return $result;
    }

    public static function get_questionnaire_returns() {
        return new external_single_structure(array(
                   'id' => new external_value(PARAM_INT, 'Questionnaire id'),
                   'courseid' => new external_value(PARAM_INT, 'Course id'),
                   'name' => new external_value(PARAM_RAW, 'Questionnaire name'),
                   'intro' => new external_value(PARAM_RAW, 'Description'),
                   'introformat' => new external_value(PARAM_INT, 'description format'),
                   'opendate' => new external_value(PARAM_INT, 'Open date (unix timestamp)'),
                   'closedate' => new external_value(PARAM_INT, 'Close date (unix timestamp)'),
                   'qtype' => new external_value(PARAM_INT, 'Questionnaire repond type: 0-many; 1-once; 2-daily; 3-weekly; 4-monthly'),
                   'respondenttype' => new external_value(PARAM_TEXT, 'Respondent type: fullname or anonymous'),
                   'resp_eligible' => new external_value(PARAM_TEXT, 'Respondent eligigle'),
                   'resp_view' => new external_value(PARAM_INT, 'View response: 1-after answering; 2-after closed; 3-always'),
                   'resume' => new external_value(PARAM_BOOL, 'Save/resume answers'),
                   'navigate' => new external_value(PARAM_BOOL, 'Allow branching questions for Yes/No and Raio Buttons questions'),
                   'autonum' => new external_value(PARAM_INT, 'Auto number: 0-Do not; 1-questions; 2-pages; 3-pages and questions'),
                   'grade' => new external_value(PARAM_INT, 'Grade'),

                   'survey' => new external_single_structure(array(
                                   'id' => new external_value(PARAM_INT, 'Survey id'),
                                   'name' => new external_value(PARAM_RAW, 'Question name'),
                                   'realm' => new external_value(PARAM_TEXT, 'Realm: private; public; template'),
                                   'title' => new external_value(PARAM_RAW, 'Title'),
                                   'subtitle' => new external_value(PARAM_RAW, 'Subtitle'),
                                   'info' => new external_value(PARAM_RAW, 'Aditional info'),
                                   'email' => new external_value(PARAM_TEXT, 'Email to send submission copy to'),
                                   'thanks_page' => new external_value(PARAM_TEXT, 'URL to redirect after completing'),
                                   'thanks_head' => new external_value(PARAM_RAW, 'Heading text of the confirmation page'),
                                   'thanks_body' => new external_value(PARAM_RAW, 'Body text of the confirmation page'),
                                   'feedbacksections' => new external_value(PARAM_INT, 'Feedback options: 0-no; 1-global'),
                                   'feedbackscores' => new external_value(PARAM_BOOL, 'Display scores'),
                                   'feedbacknotes' => new external_value(PARAM_RAW, 'Feedback notes'),
                               )),
                   'questions' => new external_multiple_structure(
                                      new external_single_structure(array(
                                          'id' => new external_value(PARAM_INT, 'Question id'),
                                          'name' => new external_value(PARAM_RAW, 'Question name'),
                                          'typeid' => new external_value(PARAM_INT, 'Question type id'),
                                          'typename' => new external_value(PARAM_TEXT, 'Question type name'),
                                          'length' => new external_value(PARAM_INT, 'Length'),
                                          'precise' => new external_value(PARAM_INT, 'Precise'),
                                          'position' => new external_value(PARAM_INT, 'Position'),
                                          'content' => new external_value(PARAM_RAW, 'Question content'),
                                          'required' => new external_value(PARAM_BOOL, 'Required'),
                                          'deleted' => new external_value(PARAM_BOOL, 'Deleted'),
                                          'dependquestion' => new external_value(PARAM_INT, 'Dependquestion'),
                                          'dependchoice' => new external_value(PARAM_INT, 'Dependchoice'),
                                          'choices' => new external_multiple_structure(
                                                          new external_single_structure(array(
                                                              'choiceid' => new external_value(PARAM_INT, 'Choice id'),
                                                              'content' => new external_value(PARAM_RAW, 'Choice content'),
                                                              'value' => new external_value(PARAM_RAW, 'Choice value'),
                                                              )
                                                          ), 'Choices', VALUE_OPTIONAL
                                                       )
                                      ))
                                  ),
               ));
    }

    // --------------------------------------------------------------------------------------

    public static function get_responses_parameters() {
        return new external_function_parameters (
                    array('cmid' => new external_value(PARAM_INT, 'Course module id'),
                          'questionid' => new external_value(PARAM_INT, 'Question id')));
    }

    public static function get_responses($cmid, $questionid) {
        global $DB;

        $params = self::validate_parameters(self::get_responses_parameters(), array('cmid'=>$cmid, 'questionid'=>$questionid));

        if (!$m = $DB->get_record('modules', array('name'=>'questionnaire'))) {
            throw new moodle_exception('invalidcoursemodule', '', '', $cmid);
        }
        if (!$cm = $DB->get_record('course_modules', array('id'=>$cmid, 'module'=>$m->id))) {
            throw new moodle_exception('invalidcoursemodule', '', '', $cmid);
        }

        // Get the module context.
        $modcontext = context_module::instance($cm->id);
        require_capability('mod/questionnaire:viewsingleresponse', $modcontext);

        $question = new questionnaire_question($questionid);
        if (empty($question->survey_id)) {
            throw new moodle_exception('invalidrecordunknown');
        }

        if (!$DB->record_exists('questionnaire', array('sid'=>$question->survey_id, 'course'=>$cm->course))) {
            throw new moodle_exception('invalidrecordunknown b');
        }

        return $question->get_responses();
    }

    public static function get_responses_returns() {
        return new external_multiple_structure(
                    new external_single_structure(array(
                        'userid' => new external_value(PARAM_INT, 'User id'),
                        'username' => new external_value(PARAM_USERNAME, 'User name'),
                        'submitted' => new external_value(PARAM_INT, 'Date submitted'),
                        'complete' => new external_value(PARAM_BOOL, 'Completed'),
                        'grade' => new external_value(PARAM_FLOAT, 'Grade'),
                        'response_field' => new external_value(PARAM_TEXT, 'Name of the field that contains the response'),

                        'text' => new external_value(PARAM_RAW, 'Text response', VALUE_OPTIONAL),
                        'check' => new external_multiple_structure(
                                      new external_value(PARAM_RAW, 'Checkboxes response'), 'Multiple Response', VALUE_OPTIONAL),
                        'rate' => new external_multiple_structure(
                                      new external_single_structure(array(
                                          'item' => new external_value(PARAM_RAW, 'Rank item text'),
                                          'rank' => new external_value(PARAM_INT, 'Rank'),
                                          )), 'Rank response', VALUE_OPTIONAL),
                    ))
               );
    }

}
