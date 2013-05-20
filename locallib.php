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
 * This library replaces the phpESP application with Moodle specific code. It will eventually
 * replace all of the phpESP application, removing the dependency on that.
 */

/**
 * Updates the contents of the survey with the provided data. If no data is provided,
 * it checks for posted data.
 *
 * @param int $survey_id The id of the survey to update.
 * @param string $old_tab The function that was being executed.
 * @param object $sdata The data to update the survey with.
 *
 * @return string|boolean The function to go to, or false on error.
 *
 */

require_once('questiontypes/questiontypes.class.php');

/// Constants

define ('QUESTIONNAIRE_BGALT_COLOR1', '#FFFFFF');
define ('QUESTIONNAIRE_BGALT_COLOR2', '#EEEEEE');

define ('QUESTIONNAIREUNLIMITED', 0);
define ('QUESTIONNAIREONCE', 1);
define ('QUESTIONNAIREDAILY', 2);
define ('QUESTIONNAIREWEEKLY', 3);
define ('QUESTIONNAIREMONTHLY', 4);

define ('QUESTIONNAIRE_EDITING', 0);
define ('QUESTIONNAIRE_ACTIVE1', 1);
define ('QUESTIONNAIRE_ENDED', 3);
define ('QUESTIONNAIRE_ARCHIVED', 4);
define ('QUESTIONNAIRE_TESTING', 8);
define ('QUESTIONNAIRE_ACTIVE2', 9);

define ('QUESTIONNAIRE_STUDENTVIEWRESPONSES_NEVER', 0);
define ('QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED', 1);
define ('QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED', 2);
define ('QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS', 3);

define('QUESTIONNAIRE_MAX_EVENT_LENGTH', 5*24*60*60);   // 5 days maximum

global $QUESTIONNAIRE_TYPES;
$QUESTIONNAIRE_TYPES = array (QUESTIONNAIREUNLIMITED => get_string('qtypeunlimited', 'questionnaire'),
                              QUESTIONNAIREONCE => get_string('qtypeonce', 'questionnaire'),
                              QUESTIONNAIREDAILY => get_string('qtypedaily', 'questionnaire'),
                              QUESTIONNAIREWEEKLY => get_string('qtypeweekly', 'questionnaire'),
                              QUESTIONNAIREMONTHLY => get_string('qtypemonthly', 'questionnaire'));

global $QUESTIONNAIRE_RESPONDENTS;
$QUESTIONNAIRE_RESPONDENTS = array ('fullname' => get_string('respondenttypefullname', 'questionnaire'),
                                    'anonymous' => get_string('respondenttypeanonymous', 'questionnaire'));

global $QUESTIONNAIRE_ELIGIBLES;
$QUESTIONNAIRE_ELIGIBLES = array ('all' => get_string('respondenteligibleall', 'questionnaire'),
                                  'students' => get_string('respondenteligiblestudents', 'questionnaire'),
                                  'teachers' => get_string('respondenteligibleteachers', 'questionnaire'));

global $QUESTIONNAIRE_REALMS;
$QUESTIONNAIRE_REALMS = array ('private' => get_string('private', 'questionnaire'),
                               'public' => get_string('public', 'questionnaire'),
                               'template' => get_string('template', 'questionnaire'));

global $QUESTIONNAIRE_RESPONSEVIEWERS;
$QUESTIONNAIRE_RESPONSEVIEWERS =
    array ( QUESTIONNAIRE_STUDENTVIEWRESPONSES_NEVER => get_string('responseviewstudentsnever', 'questionnaire'),
            QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED => get_string('responseviewstudentswhenanswered', 'questionnaire'),
            QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED => get_string('responseviewstudentswhenclosed', 'questionnaire'),
            QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS => get_string('responseviewstudentsalways', 'questionnaire'));

function questionnaire_check_date ($thisdate, $insert=false) {
    $dateformat = get_string('strfdate', 'questionnaire');
    if (preg_match('/(%[mdyY])(.+)(%[mdyY])(.+)(%[mdyY])/', $dateformat, $matches)) {
        $date_pieces = explode($matches[2], $thisdate);
        foreach ($date_pieces as $datepiece) {
            if (!is_numeric($datepiece)) {
                return 'wrongdateformat';
            }
        }
        $pattern = "/[^dmy]/i";
        $dateorder = strtolower(preg_replace($pattern, '', $dateformat));
        $countpieces = count($date_pieces);
        if ($countpieces == 1) { // assume only year entered
            switch ($dateorder) {
                case 'dmy': // most countries
                case 'mdy': // USA
                    $date_pieces[2] = $date_pieces[0]; // year
                    $date_pieces[0] = '1'; // assumed 1st month of year
                    $date_pieces[1] = '1'; // assumed 1st day of month
                    break;
                case 'ymd': // ISO 8601 standard
                    $date_pieces[1] = '1'; // assumed 1st month of year
                    $date_pieces[2] = '1'; // assumed 1st day of month
                    break;
            }
        }
        if ($countpieces == 2) { // assume only month and year entered
            switch ($dateorder) {
                case 'dmy': // most countries
                    $date_pieces[2] = $date_pieces[1]; //year
                    $date_pieces[1] = $date_pieces[0]; // month
                    $date_pieces[0] = '1'; // assumed 1st day of month
                    break;
                case 'mdy': // USA
                    $date_pieces[2] = $date_pieces[1]; //year
                    $date_pieces[0] = $date_pieces[0]; // month
                    $date_pieces[1] = '1'; // assumed 1st day of month
                    break;
                case 'ymd': // ISO 8601 standard
                    $date_pieces[2] = '1'; // assumed 1st day of month
                    break;
            }
        }
        if (count($date_pieces) > 1) {
            if ($matches[1] == '%m') $month = $date_pieces[0];
            if ($matches[1] == '%d') $day = $date_pieces[0];
            if ($matches[1] == '%y') $year = strftime('%C').$date_pieces[0];
            if ($matches[1] == '%Y') $year = $date_pieces[0];

            if ($matches[3] == '%m') $month = $date_pieces[1];
            if ($matches[3] == '%d') $day = $date_pieces[1];
            if ($matches[3] == '%y') $year = strftime('%C').$date_pieces[1];
            if ($matches[3] == '%Y') $year = $date_pieces[1];

            if ($matches[5] == '%m') $month = $date_pieces[2];
            if ($matches[5] == '%d') $day = $date_pieces[2];
            if ($matches[5] == '%y') $year = strftime('%C').$date_pieces[2];
            if ($matches[5] == '%Y') $year = $date_pieces[2];

            $month = min(12,$month);
            $month = max(1,$month);
            if ($month == 2) {
                $day = min(29, $day);
            } else if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
                $day = min(30, $day);
            } else {
                $day = min(31, $day);
            }
            $day = max(1, $day);
            if (!$thisdate = gmmktime(0, 0, 0, $month, $day, $year)) {
                return 'wrongdaterange';
            } else {
                if ($insert) {
                    $thisdate = trim(userdate ($thisdate, '%Y-%m-%d', '1', false));
                } else {
                    $thisdate = trim(userdate ($thisdate, $dateformat, '1', false));
                }
            }
            return $thisdate;
        }
    } else return ('wrongdateformat');
}

// .mform span.required .mform div.error
// a variant of Moodle's notify function, with a different formatting
function questionnaire_notify($message) {
    $message = clean_text($message);
    $errorstart = '<div class="message">';
    $errorend = '</div>';
    $output = $errorstart.$message.$errorend;
    echo $output;
}

function questionnaire_choice_values($content) {

    /// If we run the content through format_text first, any filters we want to use (e.g. multilanguage) should work.
    // examines the content of a possible answer from radio button, check boxes or rate question
    // returns ->text to be displayed, ->image if present, ->modname name of modality, image ->title
    $contents = new stdClass();
    $contents->text = '';
    $contents->image = '';
    $contents->modname = '';
    $contents->title = '';
    // has image
    if ($count = preg_match('/(<img)\s .*(src="(.[^"]{1,})")/isxmU',$content,$matches)) {
        $contents->image = $matches[0];
        $imageurl = $matches[3];
        // image has a title or alt text: use one of them
        if (preg_match('/(title=.)([^"]{1,})/',$content,$matches)
             || preg_match('/(alt=.)([^"]{1,})/',$content,$matches) ) {
            $contents->title = $matches[2];
        } else {
            // image has no title nor alt text: use its filename (without the extension)
            preg_match("/.*\/(.*)\..*$/", $imageurl, $matches);
            $contents->title = $matches[1];
        }
        // content has text or named modality plus an image
        if (preg_match('/(.*)(<img.*)/',$content,$matches)) {
            $content = $matches[1];
        } else {
            // just an image
            return $contents;
        }
    }
    // look for named modalities
    $contents->text = $content;
    if ($pos = strpos($content, '=')) {
        // the equal sign used for named modalities must NOT be followed by a double quote
        // because an equal sign followed by double quote might introduce e.g. a lang tag
        if (substr($content, $pos + 1, 1) != '"') {
            $contents->text = substr($content, $pos + 1);
            $contents->modname =substr($content, 0, $pos);
        }
     }
    return $contents;
}

/**
 * Get the information about the standard questionnaire JavaScript module.
 * @return array a standard jsmodule structure.
 */
function questionnaire_get_js_module() {
    global $PAGE;
    return array(
            'name' => 'mod_questionnaire',
            'fullpath' => '/mod/questionnaire/module.js',
            'requires' => array('base', 'dom', 'event-delegate', 'event-key',
                    'core_question_engine', 'moodle-core-formchangechecker'),
            'strings' => array(
                    array('cancel', 'moodle'),
                    array('flagged', 'question'),
                    array('functiondisabledbysecuremode', 'quiz'),
                    array('startattempt', 'quiz'),
                    array('timesup', 'quiz'),
                    array('changesmadereallygoaway', 'moodle'),
            ),
    );
}

/**
 * Get all the questionnaire responses for a user
 */
function questionnaire_get_user_responses($surveyid, $userid, $complete=true) {
    global $DB;
    $andcomplete = '';
    if ($complete) {
        $andcomplete = " AND complete = 'y' ";
    }
    return $DB->get_records_sql ("SELECT *
        FROM {questionnaire_response}
        WHERE survey_id = ?
        AND username = ?
        ".$andcomplete."
        ORDER BY submitted ASC ", array($surveyid, $userid));
}

/**
 *  get the capabilities for the questionnaire
 *  @param int $cmid
 *  @return object the available capabilities from current user
 */
function questionnaire_load_capabilities($cmid) {
    static $cb;

    if(isset($cb)) return $cb;

    $context = questionnaire_get_context($cmid);

    $cb = new object;
    $cb->view                   = has_capability('mod/questionnaire:view', $context);
    $cb->submit                 = has_capability('mod/questionnaire:submit', $context);
    $cb->viewsingleresponse     = has_capability('mod/questionnaire:viewsingleresponse', $context);
    $cb->downloadresponses      = has_capability('mod/questionnaire:downloadresponses', $context);
    $cb->deleteresponses        = has_capability('mod/questionnaire:deleteresponses', $context);
    $cb->manage                 = has_capability('mod/questionnaire:manage', $context);
    $cb->editquestions          = has_capability('mod/questionnaire:editquestions', $context);
    $cb->createtemplates        = has_capability('mod/questionnaire:createtemplates', $context);
    $cb->createpublic           = has_capability('mod/questionnaire:createpublic', $context);
    $cb->copysurveys            = has_capability('mod/questionnaire:copysurveys', $context);
    $cb->readownresponses       = has_capability('mod/questionnaire:readownresponses', $context);
    $cb->readallresponses       = has_capability('mod/questionnaire:readallresponses', $context);
    $cb->readallresponseanytime = has_capability('mod/questionnaire:readallresponseanytime', $context);
    $cb->printblank = has_capability('mod/questionnaire:printblank', $context);

    $cb->viewhiddenactivities   = has_capability('moodle/course:viewhiddenactivities', $context, NULL, false);

    return $cb;
}

/**
 *  returns the context-id related to the given coursemodule-id
 *  @param int $cmid the coursemodule-id
 *  @return object $context
 */
function questionnaire_get_context($cmid) {
    static $context;

    if(isset($context)) return $context;

    if (!$context = get_context_instance(CONTEXT_MODULE, $cmid)) {
            print_error('badcontext');
    }
    return $context;
}

/// This function *really* shouldn't be needed, but since sometimes we can end up with
/// orphaned surveys, this will clean them up.
function questionnaire_cleanup() {
    global $DB;

    /// Find surveys that don't have questionnaires associated with them.
    $sql = 'SELECT qs.* FROM {questionnaire_survey} qs '.
           'LEFT JOIN {questionnaire} q ON q.sid = qs.id '.
           'WHERE q.sid IS NULL';

    if ($surveys = $DB->get_records_sql($sql)) {
        foreach ($surveys as $survey) {
            questionnaire_delete_survey($survey->id, 0);
        }
    }
    /// Find deleted questions and remove them from database (with their associated choices, etc. // TODO
    return true;
}

function questionnaire_record_submission(&$questionnaire, $userid, $rid=0) {
    global $DB;

    $attempt['qid'] = $questionnaire->id;
    $attempt['userid'] = $userid;
    $attempt['rid'] = $rid;
    $attempt['timemodified'] = time();
    return $DB->insert_record("questionnaire_attempts", (object)$attempt, false);
}

function questionnaire_delete_survey($sid, $qid) {
    global $DB;
/// Until backup is implemented, just mark the survey as archived.

    $status = true;

    /// Delete all responses for the survey:
    if ($responses = $DB->get_records('questionnaire_response', array('survey_id' => $sid), 'id')) {
        foreach ($responses as $response) {
            $status = $status && questionnaire_delete_response($response->id);
        }
    }

    /// There really shouldn't be any more, but just to make sure...
    $DB->delete_records('questionnaire_response', array('survey_id' => $sid));
    $DB->delete_records('questionnaire_attempts', array('qid' => $qid));

    /// Delete all question data for the survey:
    if ($questions = $DB->get_records('questionnaire_question', array('survey_id' => $sid), 'id')) {
        foreach ($questions as $question) {
            $DB->delete_records('questionnaire_quest_choice', array('question_id' => $question->id));
        }
        $status = $status && $DB->delete_records('questionnaire_question', array('survey_id' => $sid));
    }

    $status = $status && $DB->delete_records('questionnaire_survey', array('id' => $sid));

    return $status;
}

function questionnaire_delete_response($rid) {
    global $DB;

    $status = true;

    /// Delete all of the survey response data:
    $DB->delete_records('questionnaire_response_bool', array('response_id' => $rid));
    $DB->delete_records('questionnaire_response_date', array('response_id' => $rid));
    $DB->delete_records('questionnaire_resp_multiple', array('response_id' => $rid));
    $DB->delete_records('questionnaire_response_other', array('response_id' => $rid));
    $DB->delete_records('questionnaire_response_rank', array('response_id' => $rid));
    $DB->delete_records('questionnaire_resp_single', array('response_id' => $rid));
    $DB->delete_records('questionnaire_response_text', array('response_id' => $rid));

    $status = $status && $DB->delete_records('questionnaire_response', array('id' => $rid));
    $status = $status && $DB->delete_records('questionnaire_attempts', array('rid' => $rid));

    return $status;
}

/// Functions to call directly into phpESP.
/// Make sure a "require_once('phpESP/admin/phpESP.ini.php')" line is included.
/// Don't need to include this for all library functions, so don't.
function questionnaire_get_survey_list($courseid=0, $type='') {
    global $DB;

    if ($courseid == 0) {
        if (isadmin()) {
            $sql = "SELECT id,name,owner,realm,status " .
                   "{questionnaire_survey} " .
                   "ORDER BY realm,name ";
            $params = null;
        } else {
            return false;
        }
    } else if (!empty($type)) {
        if ($type == 'public') {
            $sql = "SELECT s.id,s.name,s.owner,s.realm,s.status,s.title,q.id as qid " .
                   "FROM {questionnaire} q " .
                   "INNER JOIN {questionnaire_survey} s ON s.id = q.sid " .
                   "WHERE status != ? AND realm = ? " .
                   "ORDER BY realm,name ";
            $params = array(QUESTIONNAIRE_ARCHIVED, $type);
    /// Any survey owned by the user or typed as 'template' can be copied.
        } else if ($type == 'template') {
            $sql = "SELECT s.id,s.name,s.owner,s.realm,s.status,s.title,q.id as qid " .
                   "FROM {questionnaire} q " .
                   "INNER JOIN {questionnaire_survey} s ON s.id = q.sid " .
                   "WHERE status != ? AND (realm = ? OR owner = ?) " .
                   "ORDER BY realm,name ";
            $params = array(QUESTIONNAIRE_ARCHIVED, $type, $courseid);
        }
    } else {
        $sql = "SELECT s.id,s.name,s.owner,s.realm,s.status,q.id as qid " .
               "FROM {questionnaire} q " .
               "INNER JOIN {questionnaire_survey} s ON s.id = q.sid " .
               "WHERE status != ? AND owner = ? " .
               "ORDER BY realm,name ";
        $params = array(QUESTIONNAIRE_ARCHIVED, $courseid);
    }
    return $DB->get_records_sql($sql, $params);
}

function questionnaire_get_survey_select($instance, $courseid=0, $sid=0, $type='') {
    global $OUTPUT;

    $surveylist = array();
    if ($surveys = questionnaire_get_survey_list($courseid, $type)) {

        $strpreview = get_string('preview');
        $strunknown = get_string('unknown', 'questionnaire');
        $strpublic = get_string('public', 'questionnaire');
        $strprivate = get_string('private', 'questionnaire');
        $strtemplate = get_string('template', 'questionnaire');
        $strviewresp = get_string('viewresponses', 'questionnaire');

        foreach ($surveys as $survey) {
            if (empty($survey->realm)) {
                $stat = $strunknown;
            } else if ($survey->realm == 'public') {
                $stat = $strpublic;
            } else if ($survey->realm == 'private') {
                $stat = $strprivate;
            } else if ($survey->realm == 'template') {
                $stat = $strtemplate;
            } else {
                $stat = $strunknown;
            }
            // prevent creation of a new questionnaire using a public questionnaire IN THE SAME COURSE!
            if ($type == 'public' && $survey->owner == $courseid) {
                continue;
            } else {
                $args = "sid={$survey->id}&popup=1";
                if (!empty($survey->qid)) {
                    $args .= "&qid={$survey->qid}";
                }
                $link = new moodle_url("/mod/questionnaire/preview.php?{$args}");
                $action = new popup_action('click', $link);
                $label = $OUTPUT->action_link($link, $survey->title, $action, array('title'=>$survey->title));
                $surveylist[$type.'-'.$survey->id] = $label;
            }
        }
    }
    return $surveylist;
}

function questionnaire_get_type ($id) {
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
    case 100:
        return get_string('sectiontext', 'questionnaire');
    case 99:
        return get_string('sectionbreak', 'questionnaire');
    default:
        return $id;
    }
}

/**
 *  This creates new events given as opendate and closedate by $questionnaire.
 *  @param object $questionnaire
 *  @return void
 */
 /* added by JR 16 march 2009 based on lesson_process_post_save script */

function questionnaire_set_events($questionnaire) {
    // adding the questionnaire to the eventtable
    global $DB;
    if ($events = $DB->get_records('event', array('modulename'=>'questionnaire', 'instance'=>$questionnaire->id))) {
        foreach($events as $event) {
            delete_event($event->id);
        }
    }

    // the open-event
    $event = new stdClass;
    $event->description = $questionnaire->name;
    $event->courseid = $questionnaire->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->modulename = 'questionnaire';
    $event->instance = $questionnaire->id;
    $event->eventtype = 'open';
    $event->timestart = $questionnaire->opendate;
    $event->visible = instance_is_visible('questionnaire', $questionnaire);
    $event->timeduration = ($questionnaire->closedate - $questionnaire->opendate);

    if ($questionnaire->closedate and $questionnaire->opendate and $event->timeduration <= QUESTIONNAIRE_MAX_EVENT_LENGTH) {
        // Single event for the whole questionnaire.
        $event->name = $questionnaire->name;
        add_event($event);
    } else {
        // Separate start and end events.
        $event->timeduration  = 0;
        if ($questionnaire->opendate) {
            $event->name = $questionnaire->name.' ('.get_string('questionnaireopens', 'questionnaire').')';
            add_event($event);
            unset($event->id); // So we can use the same object for the close event.
        }
        if ($questionnaire->closedate) {
            $event->name = $questionnaire->name.' ('.get_string('questionnairecloses', 'questionnaire').')';
            $event->timestart = $questionnaire->closedate;
            $event->eventtype = 'close';
            add_event($event);
        }
    }
}

// DEPRECATED FROM HERE ON

/** {{{ proto void mkcrossformat (array weights, integer qid)
 * Builds HTML to allow for cross tabulation/analysis reporting.
 * @deprecated since Moodle 2.5
 */
function questionnaire_response_key_cmp($l, $r) {
    debugging('questionnaire_response_key_cmp() has been deprecated.');
	$lx = explode('_', $l);
    $rx = explode('_', $r);
    $lc = intval($lx[0]);
    $rc = intval($rx[0]);
    if ($lc == $rc) {
        if (count($lx) > 1 && count($rx) > 1) {
            $lc = intval($lx[1]);
            $rc = intval($rx[1]);
        } else if (count($lx) > 1) {
            $lc++;
        } else if (count($rx) > 1) {
            $rc++;
        }
    }
    if ($lc == $rc)
        return 0;
    return ($lc > $rc) ? 1 : -1;
}

/**
 * @deprecated since Moodle 2.5
 */
function questionnaire_preview ($questionnaire) {
    debugging('questionnaire_preview() has been deprecated.');
	global $DB;
    /// Print the page header
    /// Templates may not have questionnaires yet...
    $tempsid = $questionnaire->survey->id; // this is needed for Preview cases later on

    if (!isset($questionnaire->name)) {
        $name = $DB->get_field('questionnaire_survey', 'name', array('id' => $tempsid));
        $questionnaire->sid = $tempsid;
        $questionnaire->add_questions($tempsid);
    } else {
        $name = $questionnaire->name;
    }
    $qp = get_string('preview_questionnaire', 'questionnaire');
    $pq = get_string('previewing', 'questionnaire');
    $course = $questionnaire->course;
    print_header($course->shortname.$qp,
                 $course->fullname.$pq.$name, '', '', '', false);
    /// Print the main part of the page
    $SESSION->questionnaire_survey_id = $tempsid;
    if (isset($formdata->sid) && $formdata->sid != 0) {
        $sid = $SESSION->questionnaire_survey_id = $formdata->sid;
    } else {
        $sid = $SESSION->questionnaire_survey_id;
    }
    $questionnaire->survey = $DB->get_record('questionnaire_survey', array('id' => $sid));
    $n = $DB->count_records('questionnaire_question', array('survey_id' => $sid, 'type_id' => '99', 'deleted' => 'n'));
    for ($i=1; $i<$n+2 ; $i++) {
        $questionnaire->survey_render($i, '', $formdata);
    }
    close_window_button();
    echo '</div></div></body></html>';
    break;
}

/**
 * @deprecated since Moodle 2.5
 */
function questionnaire_get_active_surveys_menu() {
    debugging('questionnaire_get_active_surveys_menu() has been deprecated.');
    global $DB;

    $select = "status in (". QUESTIONNAIRE_ACTIVE1 . "," . QUESTIONNAIRE_ACTIVE2 . ")";
    return $DB->get_records_select_menu('questionnaire_survey', $select);
}

/**
 * @deprecated since Moodle 2.5
 */
function questionnaire_get_surveys_menu($status=NULL) {
    debugging('questionnaire_get_surveys_menu() has been deprecated.');
    global $DB;

    $field = ($status) ? 'status' : $status;
    return $DB->get_records_menu('questionnaire_survey', array($field => $status));
}

/**
 * @deprecated since Moodle 2.5
 */
function questionnaire_survey_has_questions($sid) {
    debugging('questionnaire_survey_has_questions() has been deprecated.');
    global $DB;

    return $DB->record_exists('questionnaire_question', array('survey_id' => $sid, 'deleted' => 'n'));
}

/**
 * @deprecated since Moodle 2.5
 */
function questionnaire_survey_exists($sid) {
    debugging('questionnaire_survey_exists() has been deprecated.');
	global $DB;

    return $DB->record_exists('questionnaire_survey', array('id' => $sid));
}
