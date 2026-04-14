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

/**
 * Get the information about the standard questionnaire JavaScript module.
 * @return array a standard jsmodule structure.
 */
function questionnaire_get_js_module() {
    return [
            'name' => 'mod_questionnaire',
            'fullpath' => '/mod/questionnaire/module.js',
            'requires' => ['base', 'dom', 'event-delegate', 'event-key',
                    'core_question_engine', 'moodle-core-formchangechecker'],
            'strings' => [
                    ['cancel', 'moodle'],
                    ['flagged', 'question'],
                    ['functiondisabledbysecuremode', 'quiz'],
                    ['startattempt', 'quiz'],
                    ['timesup', 'quiz'],
                    ['changesmadereallygoaway', 'moodle'],
                    ['leftpart', 'questionnaire'],
                    ['leftpartdefault', 'questionnaire'],
                    ['middlepart', 'questionnaire'],
                    ['middlepartdefault', 'questionnaire'],
                    ['middlepartwithtwovalues', 'questionnaire'],
                    ['middlepartwithtwovaluesdefault', 'questionnaire'],
                    ['rightpart', 'questionnaire'],
                    ['rightpartdefault', 'questionnaire'],
                    ['where', 'questionnaire'],
            ],
    ];
}

/**
 * This function *really* shouldn't be needed, but since sometimes we can end up with
 * orphaned surveys, this will clean them up.
 * @return bool
 * @throws dml_exception
 */
function questionnaire_cleanup() {
    global $DB;

    // Find surveys that don't have questionnaires associated with them.
    $sql = 'SELECT qs.* FROM {questionnaire_survey} qs ' .
           'LEFT JOIN {questionnaire} q ON q.sid = qs.id ' .
           'WHERE q.sid IS NULL';

    if ($surveys = $DB->get_records_sql($sql)) {
        foreach ($surveys as $survey) {
            \questionnaire::delete_survey($survey->id, 0);
        }
    }
    // Find deleted questions and remove them from database (with their associated choices, etc.).
    return true;
}

/**
 * Delete permanently questions and data reference.
 *
 * @param int $qid question id.
 * @param int $sid survey question id.
 * @return void
 */
function questionnaire_delete_permanently_questions($qid, $sid) {
    global $DB;
    $select = 'id = :id AND surveyid = :sid AND deleted IS NOT NULL';
    $DB->delete_records_select('questionnaire_question', $select, ['id' => $qid, 'sid' => $sid]);
    $DB->delete_records('questionnaire_response', ['questionnaireid' => $qid]);
    \mod_questionnaire\local\response\questionnaire_responses::delete_responses_for_question($qid);
    $DB->delete_records('questionnaire_dependency', ['questionid' => $qid]);
    $DB->delete_records('questionnaire_dependency', ['dependquestionid' => $qid]);
}

/**
 * Log question deleted event.
 *
 * @param int $cmid of module.
 * @param string $questiontype of question.
 * @param int $courseid of question.
 * @return void.
 */
function questionnaire_observe_event_delete($cmid, $questiontype, $courseid) {
    $context = context_module::instance($cmid);
    $params = [
            'context' => $context,
            'courseid' => $courseid,
            'other' => ['questiontype' => $questiontype],
    ];
    $event = \mod_questionnaire\event\question_deleted::create($params);
    $event->trigger();
}

/**
 * Restore deleted questions.
 *
 * @param int $qid question id.
 * @param int $sid survey id.
 * @return void
 */
function questionnaire_restore_deleted_question($qid, $sid) {
    global $DB;
    // Get current deleted question and last position.
    $sql = "SELECT *, (
                    SELECT position + 1
                      FROM {questionnaire_question}
                     WHERE surveyid = ?
                       AND deleted IS NULL
                  ORDER BY position DESC
                     LIMIT 1 ) as lastposition
              FROM {questionnaire_question}
             WHERE id = ?
               AND surveyid = ?
               AND deleted IS NOT NULL";
    $question = $DB->get_record_sql($sql, [$sid, $qid, $sid]);
    if ($question) {
        // Update question using Moodle API. Only 'id' is needed for update_record.
        $question->deleted = null;
        $question->position = $question->lastposition ?? 1;
        $DB->update_record('questionnaire_question', $question);
    }
}

/**
 * Get range of time permanently in setup cron task.
 */
function questionnaire_get_range_time_permanently() {
    return get_config('questionnaire_questiondeletion', 'duration');
}

/**
 * Called by HTML editor in showrespondents and Essay question. Based on question/essay/renderer.
 * Pending general solution to using the HTML editor outside of moodleforms in Moodle pages.
 * @param int $context
 * @return array
 */
function questionnaire_get_editor_options($context) {
    return [
        'subdirs' => 0,
        'maxbytes' => 0,
        'maxfiles' => -1,
        'context' => $context,
        'noclean' => 0,
        'trusttext' => 0,
    ];
}

/**
 * Code snippet used to set up the questionform.
 * @param stdClass $questionnaire
 * @param int $qid
 * @param int $qtype
 * @return mixed|\mod_questionnaire\local\question\question
 */
function questionnaire_prep_for_questionform($questionnaire, $qid, $qtype) {
    $cmid = $questionnaire->coursemodule()->id;
    $context = context_module::instance($cmid);
    if ($qid != 0) {
        $questions = $questionnaire->questions();
        $question = clone($questions[$qid]);
        $question->qid = $question->id();
        $question->sid = $questionnaire->surveyid();
        $question->set_id($cmid);
        $draftideditor = file_get_submitted_draft_itemid('question');
        $content = file_prepare_draft_area(
            $draftideditor,
            $context->id,
            'mod_questionnaire',
            'question',
            $qid,
            ['subdirs' => true],
            $question->content()
        );
        $question->set_content(['text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor]);

        if (isset($question->dependencies)) {
            foreach ($question->dependencies as $dependencies) {
                if ($dependencies->dependandor === "and") {
                    $question->dependquestionsand[] = $dependencies->dependquestionid . ',' . $dependencies->dependchoiceid;
                    $question->dependlogicand[] = $dependencies->dependlogic;
                } else if ($dependencies->dependandor === "or") {
                    $question->dependquestionsor[] = $dependencies->dependquestionid . ',' . $dependencies->dependchoiceid;
                    $question->dependlogicor[] = $dependencies->dependlogic;
                }
            }
        }
    } else {
        $question = \mod_questionnaire\local\question\question::question_builder($qtype);
        $question->sid = $questionnaire->surveyid();
        $question->set_id($cmid);
        $question->set_typeid($qtype);
        $draftideditor = file_get_submitted_draft_itemid('question');
        $content = file_prepare_draft_area(
            $draftideditor,
            $context->id,
            'mod_questionnaire',
            'question',
            0,
            ['subdirs' => true],
            ''
        );
        $question->set_content(['text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor]);
    }
    return $question;
}

/**
 * Get the standard page contructs and check for validity.
 * @param int $id The coursemodule id.
 * @param int $a  The module instance id.
 * @return array An array with the $cm, $course, and $questionnaire records in that order.
 */
function questionnaire_get_standard_page_items($id = null, $a = null) {
    global $DB;

    if ($id) {
        if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questionnaire');
        }

        if (! $course = $DB->get_record("course", ["id" => $cm->course])) {
            throw new \moodle_exception('coursemisconf', 'mod_questionnaire');
        }

        if (! $questionnaire = $DB->get_record("questionnaire", ["id" => $cm->instance])) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questionnaire');
        }
    } else {
        if (! $questionnaire = $DB->get_record("questionnaire", ["id" => $a])) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questionnaire');
        }
        if (! $course = $DB->get_record("course", ["id" => $questionnaire->course])) {
            throw new \moodle_exception('coursemisconf', 'mod_questionnaire');
        }
        if (! $cm = get_coursemodule_from_instance("questionnaire", $questionnaire->id, $course->id)) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_questionnaire');
        }
    }

    return ([$cm, $course, $questionnaire]);
}


/**
 * Create options for remove old responses in the questionare.
 *
 * @return array
 */
function questionnaire_create_remove_options() {
    $options = [];
    $options[0] = get_string('removeoldresponsesdefault', 'questionnaire');
    for ($i = 1; $i <= 36; $i++) {
        $options[$i * 2592000] = $i > 1 ? get_string('nummonths', 'moodle', $i) : get_string('onemonth', 'questionnaire');
    }
    return $options;
}

