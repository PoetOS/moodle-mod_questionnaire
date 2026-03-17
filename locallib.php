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
// Constants.

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
$questionnairetypes = [
    QUESTIONNAIREUNLIMITED => get_string('qtypeunlimited', 'questionnaire'),
    QUESTIONNAIREONCE => get_string('qtypeonce', 'questionnaire'),
    QUESTIONNAIREDAILY => get_string('qtypedaily', 'questionnaire'),
    QUESTIONNAIREWEEKLY => get_string('qtypeweekly', 'questionnaire'),
    QUESTIONNAIREMONTHLY => get_string('qtypemonthly', 'questionnaire'),
];

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
$questionnaireresponseviewers = [
    QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED => get_string('responseviewstudentswhenanswered', 'questionnaire'),
    QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED => get_string('responseviewstudentswhenclosed', 'questionnaire'),
    QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS => get_string('responseviewstudentsalways', 'questionnaire'),
    QUESTIONNAIRE_STUDENTVIEWRESPONSES_NEVER => get_string('responseviewstudentsnever', 'questionnaire'),
];

global $autonumbering;
$autonumbering = [
    0 => get_string('autonumberno', 'questionnaire'),
    1 => get_string('autonumberquestions', 'questionnaire'),
    2 => get_string('autonumberpages', 'questionnaire'),
    3 => get_string('autonumberpagesandquestions', 'questionnaire'),
];

/**
 * Return the choice values for the content.
 * @param string $content
 * @return stdClass
 */
function questionnaire_choice_values($content) {

    // If we run the content through format_text first, any filters we want to use (e.g. multilanguage) should work.
    // examines the content of a possible answer from radio button, check boxes or rate question
    // returns ->text to be displayed, ->image if present, ->modname name of modality, image ->title.
    $contents = new stdClass();
    $contents->text = '';
    $contents->image = '';
    $contents->modname = '';
    $contents->title = '';
    // Has image.
    if (preg_match('/(<img)\s .*(src="(.[^"]{1,})")/isxmU', $content, $matches)) {
        $contents->image = $matches[0];
        $imageurl = $matches[3];
        // Image has a title or alt text: use one of them.
        if (
            preg_match('/(title=.)([^"]{1,})/', $content, $matches) ||
            preg_match('/(alt=.)([^"]{1,})/', $content, $matches)
        ) {
            $contents->title = $matches[2];
        } else {
            // Image has no title nor alt text: use its filename (without the extension).
            preg_match("/.*\/(.*)\..*$/", $imageurl, $matches);
            $contents->title = $matches[1];
        }
        // Content has text or named modality plus an image.
        if (preg_match('/(.*)(<img.*)/', $content, $matches)) {
            $content = $matches[1];
        } else {
            // Just an image.
            return $contents;
        }
    }

    // Check for score value first (used e.g. by personality test feature).
    $r = preg_match_all("/^(\d{1,2}=)(.*)$/", $content, $matches);
    if ($r) {
        $content = $matches[2][0];
    }

    // Look for named modalities.
    $contents->text = $content;
    // DEV JR from version 2.5, a double colon :: must be used here instead of the equal sign.
    if ($pos = strpos($content, '::')) {
        $contents->text = substr($content, $pos + 2);
        $contents->modname = substr($content, 0, $pos);
    }
    return $contents;
}

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
    \mod_questionnaire\local\response\manager::delete_responses_for_question($qid);
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
 * Delete all page break deleted.
 *
 * @param int $sid question survey id.
 */
function questionnaire_delete_pagebreaks($sid) {
    global $DB;
    $DB->delete_records_select(
        'questionnaire_question',
        'surveyid = :sid AND deleted IS NOT NULL AND typeid = :typeid',
        [
            'sid' => $sid,
            'typeid' => QUESPAGEBREAK,
        ]
    );
}

/**
 * Return the language string for the specified question type.
 * @param int $id
 * @return lang_string|mixed|string
 * @throws coding_exception
 */
function questionnaire_get_type($id) {
    switch ($id) {
        case 1:
            return get_string('yesno', 'questionnaire');
        case 2:
            return get_string('textbox', 'questionnaire');
        case 3:
            return get_string('essaybox', 'questionnaire');
        case 4:
            return get_string('radiobuttons', 'questionnaire');
        case 5:
            return get_string('checkboxes', 'questionnaire');
        case 6:
            return get_string('dropdown', 'questionnaire');
        case 8:
            return get_string('ratescale', 'questionnaire');
        case 9:
            return get_string('date', 'questionnaire');
        case 10:
            return get_string('numeric', 'questionnaire');
        case 11:
            return get_string('slider', 'questionnaire');
        case 12:
            return get_string('file', 'questionnaire');
        case 100:
            return get_string('sectiontext', 'questionnaire');
        case 99:
            return get_string('sectionbreak', 'questionnaire');
        default:
            return $id;
    }
}

/**
 * Get users who have not completed the questionnaire
 *
 * @param object $cm
 * @param int $sid
 * @param bool $group single groupid
 * @param string $sort
 * @param bool $startpage
 * @param bool $pagecount
 * @return object the userrecords
 * @throws coding_exception
 * @throws dml_exception
 */
function questionnaire_get_incomplete_users(
    $cm,
    $sid,
    $group = false,
    $sort = '',
    $startpage = false,
    $pagecount = false
) {

    global $DB;

    $context = context_module::instance($cm->id);

    // First get all users who can complete this questionnaire.
    $cap = 'mod/questionnaire:submit';
    $fields = 'u.id, u.username';
    if (!$allusers = get_enrolled_users($context, $cap, $group, $fields, $sort)) {
        return false;
    }
    $allusers = array_keys($allusers);

    // Nnow get all completed questionnaires.
    $params = ['questionnaireid' => $cm->instance, 'complete' => 'y'];
    $sql = "SELECT userid FROM {questionnaire_response} " .
           "WHERE questionnaireid = :questionnaireid AND complete = :complete " .
           "GROUP BY userid ";

    if (!$completedusers = $DB->get_records_sql($sql, $params)) {
        return $allusers;
    }
    $completedusers = array_keys($completedusers);
    // Now strike all completedusers from allusers.
    $allusers = array_diff($allusers, $completedusers);
    // For paging I use array_slice().
    if (($startpage !== false) && ($pagecount !== false)) {
        $allusers = array_slice($allusers, $startpage, $pagecount);
    }
    return $allusers;
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
 * Get the parent of a child question.
 * @param stdClass $question
 * @return array
 */
function questionnaire_get_parent($question) {
    global $DB;
    $qid = $question->id;
    $parent = [];
    $dependquestion = $DB->get_record(
        'questionnaire_question',
        ['id' => $question->dependquestionid],
        'id, position, name, typeid'
    );
    if (is_object($dependquestion)) {
        $qdependchoice = '';
        switch ($dependquestion->typeid) {
            case QUESRADIO:
            case QUESDROP:
            case QUESCHECK:
                $dependchoice = $DB->get_record('questionnaire_quest_choice', ['id' => $question->dependchoiceid], 'id,content');
                $qdependchoice = $dependchoice->id;
                $dependchoice = $dependchoice->content;

                $contents = questionnaire_choice_values($dependchoice);
                if ($contents->modname) {
                    $dependchoice = $contents->modname;
                }
                break;
            case QUESYESNO:
                switch ($question->dependchoiceid) {
                    case 0:
                        $dependchoice = get_string('yes');
                        $qdependchoice = 'y';
                        break;
                    case 1:
                        $dependchoice = get_string('no');
                        $qdependchoice = 'n';
                        break;
                }
                break;
        }
        // Qdependquestion, parenttype and qdependchoice fields to be used in preview mode.
        $parent[$qid]['qdependquestion'] = 'q' . $dependquestion->id;
        $parent[$qid]['qdependchoice'] = $qdependchoice;
        $parent[$qid]['parenttype'] = $dependquestion->typeid;
        // Other fields to be used in Questions edit mode.
        $parent[$qid]['position'] = $question->position;
        $parent[$qid]['name'] = $question->name;
        $parent[$qid]['content'] = $question->content;
        $parent[$qid]['parentposition'] = $dependquestion->position;
        $parent[$qid]['parent'] = format_string($dependquestion->name) . '->' . format_string($dependchoice);
    }
    return $parent;
}

/**
 * Get parent position of all child questions in current questionnaire.
 * Use the parent with the largest position value.
 *
 * @param array $questions
 * @return array An array with Child-ID->Parentposition.
 */
function questionnaire_get_parent_positions($questions) {
    $parentpositions = [];
    foreach ($questions as $question) {
        foreach ($question->dependencies as $dependency) {
            $dependquestion = $dependency->dependquestionid;
            if (isset($dependquestion) && $dependquestion != 0) {
                $childid = $question->id;
                $parentpos = $questions[$dependquestion]->position;

                if (!isset($parentpositions[$childid])) {
                    $parentpositions[$childid] = $parentpos;
                }
                if (isset($parentpositions[$childid]) && $parentpos > $parentpositions[$childid]) {
                    $parentpositions[$childid] = $parentpos;
                }
            }
        }
    }
    return $parentpositions;
}

/**
 * Get child position of all parent questions in current questionnaire.
 * Use the child with the smallest position value.
 *
 * @param array $questions
 * @return array An array with Parent-ID->Childposition.
 */
function questionnaire_get_child_positions($questions) {
    $childpositions = [];
    foreach ($questions as $question) {
        foreach ($question->dependencies as $dependency) {
            $dependquestion = $dependency->dependquestionid;
            if (isset($dependquestion) && $dependquestion != 0) {
                $parentid = $questions[$dependquestion]->id; // Equals $dependquestion?.
                $childpos = $question->position;

                if (!isset($childpositions[$parentid])) {
                    $childpositions[$parentid] = $childpos;
                }

                if (isset($childpositions[$parentid]) && $childpos < $childpositions[$parentid]) {
                    $childpositions[$parentid] = $childpos;
                }
            }
        }
    }
    return $childpositions;
}

/**
 * Code snippet used to set up the questionform.
 * @param stdClass $questionnaire
 * @param int $qid
 * @param int $qtype
 * @return mixed|\mod_questionnaire\local\question\question
 */
function questionnaire_prep_for_questionform($questionnaire, $qid, $qtype) {
    $context = context_module::instance($questionnaire->cm->id);
    if ($qid != 0) {
        $question = clone($questionnaire->questions[$qid]);
        $question->qid = $question->id;
        $question->sid = $questionnaire->survey->id;
        $question->id = $questionnaire->cm->id;
        $draftideditor = file_get_submitted_draft_itemid('question');
        $content = file_prepare_draft_area(
            $draftideditor,
            $context->id,
            'mod_questionnaire',
            'question',
            $qid,
            ['subdirs' => true],
            $question->content
        );
        $question->content = ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

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
        $question->sid = $questionnaire->survey->id;
        $question->id = $questionnaire->cm->id;
        $question->typeid = $qtype;
        $question->type = '';
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
        $question->content = ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];
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
 * Count responses already saved for that question.
 *
 * @param int $qid question id.
 * @param int $qtype question type.
 * @return int number or 0 if responses were not found.
 */
function count_reponses_question(int $qid, int $qtype): int {
    global $DB;

    $countresps = 0;
    if ($qtype != QUESSECTIONTEXT) {
        $responsetable = $DB->get_field('questionnaire_question_type', 'responsetable', ['typeid' => $qtype]);
        if (!empty($responsetable)) {
            $countresps = $DB->count_records('questionnaire_' . $responsetable, ['questionid' => $qid]);
        }
    }

    return $countresps;
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

/**
 * Delete all the old responses when we have setting the questionnaire.
 *
 * @throws coding_exception
 * @throws dml_exception
 */
function questionnaire_delete_old_responses() {
    global $DB;
    $currenttime = time();

    $sql = "SELECT qr.id
              FROM {questionnaire} q
              JOIN {questionnaire_response} qr ON qr.questionnaireid = q.id AND qr.complete = 'y'
             WHERE q.removeafter <> 0 AND (q.removeafter < :currenttime - qr.submitted)";

    // Get all old response IDs from questionnaires.
    $oldresponses = $DB->get_records_sql($sql, ['currenttime' => $currenttime]);

    if (!empty($oldresponses)) {
        try {
            $oldresponsesid = array_keys($oldresponses);
            $count = count($oldresponsesid);
            if (!PHPUNIT_TEST) {
                mtrace("\nBeginning deleting $count old responses requests");
            }
            // Tables to delete responses from.
            $responsetables = [
                    'questionnaire_response_bool', 'questionnaire_response_date', 'questionnaire_resp_multiple',
                    'questionnaire_response_other', 'questionnaire_response_rank', 'questionnaire_resp_single',
                    'questionnaire_response_text'];

            // Delete related response data.
            foreach ($responsetables as $tablename) {
                $DB->delete_records_list($tablename, 'responseid', $oldresponsesid);
            }

            // Delete from the main response table.
            $DB->delete_records_list('questionnaire_response', 'id', $oldresponsesid);
            if (!PHPUNIT_TEST) {
                mtrace("\nCompleted deleting $count old responses requests");
            }
        } catch (\dml_exception $ex) {
            debugging('Error: ' . $ex->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
