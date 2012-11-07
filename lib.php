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

/// Library of functions and constants for module questionnaire
/// (replace questionnaire with the name of your module and delete this line)

require_once('locallib.php');
/**
 * If start and end date for the questionnaire are more than this many seconds
 * apart they will be represented by two separate events in the calendar
 */
require_once($CFG->libdir.'/eventslib.php');

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

function questionnaire_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_GROUPS:                  return true;
        case FEATURE_MOD_INTRO:               return true;

        default: return null;
    }
}

/**
 * @return array all other caps used in module
 */
function questionnaire_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
} 

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

function questionnaire_add_instance($questionnaire) {
/// Given an object containing all the necessary data,
/// (defined by the form in mod.html) this function
/// will create a new instance and return the id number
/// of the new instance.
    global $COURSE, $DB;

    // Check the realm and set it to the survey if it's set.
    if (!empty($questionnaire->sid) && !empty($questionnaire->realm)) {
// JR not needed
//        $DB->set_field('questionnaire_survey', 'realm', $questionnaire->realm, array('id' => $questionnaire->sid));
    } else if (empty($questionnaire->sid)) {
        /// Create a new survey:
        $cm = new Object();
        $qobject = new questionnaire(0, $questionnaire, $COURSE, $cm);

        if ($questionnaire->create == 'new-0') {
            $sdata = new Object();
            $sdata->name = $questionnaire->name;
            $sdata->realm = 'private';
            $sdata->title = $questionnaire->name;
            $sdata->subtitle = '';
            $sdata->info = '';
            $sdata->theme = '';// theme is deprecated
            $sdata->thanks_page = '';
            $sdata->thank_head = '';
            $sdata->thank_body = '';
            $sdata->email = '';
            $sdata->owner = $COURSE->id;
            if (!($sid = $qobject->survey_update($sdata))) {
                print_error('couldnotcreatenewsurvey', 'questionnaire');
            }
        } else {
            $copyid = explode('-', $questionnaire->create);
            $copyrealm = $copyid[0];
            $copyid = $copyid[1];
            if (empty($qobject->survey)) {
                $qobject->add_survey($copyid);
                $qobject->add_questions($copyid);
            }
            // JR new questionnaires created as "use public" should not create a new survey instance
            if ($copyrealm == 'public') {
                $sid = $copyid;
            } else {
                $sid = $qobject->sid = $qobject->survey_copy($COURSE->id);
                // JR all new questionnaires should be created as "private", even if they are *copies* of public or template questionnaires
                $DB->set_field('questionnaire_survey', 'realm', 'private', array('id' => $sid));
            }
        }
        $questionnaire->sid = $sid;
    }

    $questionnaire->timemodified = time();

    # May have to add extra stuff in here #
    if (empty($questionnaire->useopendate)) {
        $questionnaire->opendate = 0;
    }
    if (empty($questionnaire->useclosedate)) {
        $questionnaire->closedate = 0;
    }

    if ($questionnaire->resume == '1') { //JR
        $questionnaire->resume = 1;
    } else {
        $questionnaire->resume = 0;
    }
    $questionnaire->navigate = 1; // not used at all!

    if(!$questionnaire->id = $DB->insert_record("questionnaire", $questionnaire)) {
        return false;
    }

    questionnaire_set_events($questionnaire);

    return $questionnaire->id;
}


function questionnaire_update_instance($questionnaire) {
        global $DB;

/// Given an object containing all the necessary data,
/// (defined by the form in mod.html) this function
/// will update an existing instance with new data.

    // Check the realm and set it to the survey if its set.
    if (!empty($questionnaire->sid) && !empty($questionnaire->realm)) {
        $DB->set_field('questionnaire_survey', 'realm', $questionnaire->realm, array('id' => $questionnaire->sid));
    }

    $questionnaire->timemodified = time();
    $questionnaire->id = $questionnaire->instance;

    # May have to add extra stuff in here #
    if (empty($questionnaire->useopendate)) {
        $questionnaire->opendate = 0;
    }
    if (empty($questionnaire->useclosedate)) {
        $questionnaire->closedate = 0;
    }

    if ($questionnaire->resume == '1') { //JR
        $questionnaire->resume = 1;
    } else {
        $questionnaire->resume = 0;
    }
    $questionnaire->navigate = 1;

    // get existing grade item
    questionnaire_grade_item_update($questionnaire);

    questionnaire_set_events($questionnaire);
	questionnaire_update_grades($questionnaire);
    return $DB->update_record("questionnaire", $questionnaire);
}


function questionnaire_delete_instance($id) {
    global $DB;

/// Given an ID of an instance of this module,
/// this function will permanently delete the instance
/// and any data that depends on it.

    if (! $questionnaire = $DB->get_record('questionnaire', array('id' => $id))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records('questionnaire', array('id' => $questionnaire->id))) {
        $result = false;
    }

    if ($survey = $DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid))) {
    /// If this survey is owned by this course, delete all of the survey records and responses.
        if ($survey->owner == $questionnaire->course) {
            $result = $result && questionnaire_delete_survey($questionnaire->sid, $questionnaire->id);
        }
    }

    if ($events = $DB->get_records('event', array("modulename"=>'questionnaire', "instance"=>$questionnaire->id))) {
        foreach($events as $event) {
            delete_event($event->id);
        }
    }

    return $result;
}

function questionnaire_user_outline($course, $user, $mod, $questionnaire) {
/// Return a small object with summary information about what a
/// user has done with a given particular instance of this module
/// Used for user activity reports.
/// $return->time = the time they did it
/// $return->info = a short text description
    $result = '';
    if ($responses = questionnaire_get_user_responses($questionnaire->sid, $user->id)) {
        $n = count($responses);
        if ($n == 1) {
            $result->info = $n.' '.get_string("response", "questionnaire");
        } else {
            $result->info = $n.' '.get_string("responses", "questionnaire");
        }
        $lastresponse = array_pop($responses);
        $result->time = $lastresponse->submitted;
    } else {
            $result->info = get_string("noresponses", "questionnaire");
    }
        return $result;
}

/**
 * Get all the questionnaire responses for a user
 */
function questionnaire_get_user_responses($surveyid, $userid) {
    global $DB;

    return $DB->get_records_sql ("SELECT *
        FROM {questionnaire_response}
        WHERE survey_id = ?
        AND username = ?
        ORDER BY submitted ASC ", array($surveyid, $userid));
}

function questionnaire_user_complete($course, $user, $mod, $questionnaire) {
/// Print a detailed representation of what a  user has done with
/// a given particular instance of this module, for user activity reports.
    if ($responses = questionnaire_get_user_responses($questionnaire->sid, $user->id)) {
        foreach ($responses as $response) {
            echo get_string('submitted', 'questionnaire').' '.userdate($response->submitted).'<br />';
        }
    } else {
       print_string('noresponses', 'questionnaire');
    }

    return true;
}

function questionnaire_print_recent_activity($course, $isteacher, $timestart) {
/// Given a course and a time, this module should find recent activity
/// that has occurred in questionnaire activities and print it out.
/// Return true if there was output, or false is there was none.

    global $CFG;

    return false;  //  True if anything was printed, otherwise false
}

function questionnaire_cron () {
/// Function to be run periodically according to the moodle cron
/// This function searches for things that need to be done, such
/// as sending out mail, toggling flags etc ...

    global $CFG;

    return questionnaire_cleanup();
}

function questionnaire_grades($questionnaireid) {
/// Must return an array of grades for a given instance of this module,
/// indexed by user.  It also returns a maximum allowed grade.

    return NULL;
}

/**
 * Return grade for given user or all users.
 *
 * @param int $questionnaireid id of assignment
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function questionnaire_get_user_grades($questionnaire, $userid=0) {
    global $DB;
    $params = array();
    $usersql = '';
    if (!empty($userid)) {
        $usersql = "AND u.id = ?";
        $params[] = $userid;
    }

    $sql = "SELECT a.id, u.id AS userid, r.grade AS rawgrade, r.submitted AS dategraded, r.submitted AS datesubmitted
            FROM {user} u, {questionnaire_attempts} a, {questionnaire_response} r
            WHERE u.id = a.userid AND a.qid = $questionnaire->id AND r.id = a.rid $usersql";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $assignment null means all assignments
 * @param int $userid specific user only, 0 mean all
 */
function questionnaire_update_grades($questionnaire=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($questionnaire != null) {
        if ($graderecs = questionnaire_get_user_grades($questionnaire, $userid)) {
            $grades = array();
            foreach($graderecs as $v) {
                if (!isset($grades[$v->userid])) {
                    if ($v->rawgrade == -1) {
                        $grades[$v->userid]->rawgrade = null;
                    } else {
                        $grades[$v->userid]->rawgrade = $v->rawgrade;
                    }
                    $grades[$v->userid]->userid = $v->userid;
                    $grades[$v->userid]->feedback = '';
                    $grades[$v->userid]->format = '';
                } else if (isset($grades[$v->userid]) && ($v->rawgrade > $grades[$v->userid]->rawgrade)) {
                    $grades[$v->userid]->rawgrade = $v->rawgrade;
                }
            }
            questionnaire_grade_item_update($questionnaire, $grades);
        } else {
            questionnaire_grade_item_update($questionnaire);
        }

    } else {
        $sql = "SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
                  FROM {questionnaire} q, {course_modules} cm, {modules} m
                 WHERE m.name='questionnaire' AND m.id=cm.module AND cm.instance=q.id";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $questionnaire) {
                if ($questionnaire->grade != 0) {
                    questionnaire_update_grades($questionnaire);
                } else {
                    questionnaire_grade_item_update($questionnaire);
                }
            }
            $rs->close();
        }
    }
}

/**
 * Create grade item for given questionnaire
 *
 * @param object $questionnaire object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function questionnaire_grade_item_update($questionnaire, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($questionnaire->courseid)) {
        $questionnaire->courseid = $questionnaire->course;
    }

    if ($questionnaire->cmidnumber != '') {
        $params = array('itemname'=>$questionnaire->name, 'idnumber'=>$questionnaire->cmidnumber);
    } else {
        $params = array('itemname'=>$questionnaire->name);
    }

    if ($questionnaire->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $questionnaire->grade;
        $params['grademin']  = 0;

    } else if ($questionnaire->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$questionnaire->grade;

    } else if ($questionnaire->grade == 0) { //No Grade..be sure to delete the grade item if it exists
        $grades = NULL;
        $params = array('deleted' => 1);

    } else {
        $params = NULL; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/questionnaire', $questionnaire->courseid, 'mod', 'questionnaire', $questionnaire->id, 0, $grades, $params);
}

function questionnaire_get_participants($questionnaireid) {
//Must return an array of user records (all data) who are participants
//for a given instance of questionnaire. Must include every user involved
//in the instance, independient of his role (student, teacher, admin...)
//See other modules as example.
    global $DB;

    //Get students
    $users = $DB->get_records_sql('SELECT DISTINCT u.* '.
                             'FROM {user} u, '.
                             '     {questionnaire_attempts} qa '.
                             'WHERE qa.qid = \''.$questionnaireid.'\' AND '.
                             '      u.id = qa.userid');
    return ($users);
}

/**
 * This function returns if a scale is being used by one book
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 * @param $bookid int
 * @param $scaleid int
 * @return boolean True if the scale is used by any journal
 */
function questionnaire_scale_used ($bookid,$scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of book
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any journal
 */
function questionnaire_scale_used_anywhere($scaleid) {
    return false;
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

/**
 * Serves the questionnaire attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function questionnaire_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = array('intro', 'info', 'thankbody', 'question');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $componentid = (int)array_shift($args);

    if ($filearea != 'question') {
        if (!$survey = $DB->get_record('questionnaire_survey', array('id'=>$componentid))) {
            return false;
        }
    } else {
        if (!$question = $DB->get_record('questionnaire_question', array('id'=>$componentid))) {
            return false;
        }
    }

    if (!$questionnaire = $DB->get_record('questionnaire', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_questionnaire/$filearea/$componentid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!
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

//////////////////////////////////////////////////////////////////////////////////////
/// Any other questionnaire functions go here.  Each of them must have a name that
/// starts with questionnaire_

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

function questionnaire_get_active_surveys_menu() {
    global $DB;

    $select = "status in (". QUESTIONNAIRE_ACTIVE1 . "," . QUESTIONNAIRE_ACTIVE2 . ")";
    return $DB->get_records_select_menu('questionnaire_survey', $select);
}

function questionnaire_get_surveys_menu($status=NULL) {
    global $DB;

    $field = ($status) ? 'status' : $status;
    return $DB->get_records_menu('questionnaire_survey', array($field => $status));
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

function questionnaire_survey_has_questions($sid) {
    global $DB;

    return $DB->record_exists('questionnaire_question', array('survey_id' => $sid, 'deleted' => 'n'));
}

function questionnaire_survey_exists($sid) {
    global $DB;

    return $DB->record_exists('questionnaire_survey', array('id' => $sid));
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

function questionnaire_get_view_actions() {
    return array('view','view all');
}

function questionnaire_get_post_actions() {
    return array('submit','update');
}

/**
 * This function prints the recent activity (since current user's last login)
 * for specified courses.
 * @param array $courses Array of courses to print activity for.
 * @param string by reference $htmlarray Array of html snippets for display some
 *        -where, which this function adds its new html to.
 */
function questionnaire_print_overview($courses,&$htmlarray) {

    global $USER, $CFG;
    global $DB;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$questionnaires = get_all_instances_in_courses('questionnaire',$courses)) {
        return;
    }

    // get all questionnaire logs in ONE query (much better!)
    $params = array();
    $sql = "SELECT instance,cmid,l.course,COUNT(l.id) as count FROM {log} l "
        ." JOIN {course_modules} cm ON cm.id = cmid "
        ." JOIN {questionnaire} q ON cm.instance = q.id "
        ." WHERE (";
    foreach ($courses as $course) {
        $sql .= '(l.course = ? AND l.time > ?) OR ';
        $params[] = $course->id;
        $params[] = $course->lastaccess;
    }


    $sql = substr($sql,0,-3); // take off the last OR

    $sql .= ") AND l.module = 'questionnaire' AND action = 'submit' "
        ." AND userid != ?"
        ." AND q.resp_view <> ?"
        ." GROUP BY cmid,l.course,instance";

    $params[] = $USER->id;
    $params[] = QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED;

    if (!$new = $DB->get_records_sql($sql, $params)) {
        $new = array(); // avoid warnings
    }

    $strquestionnaires = get_string('modulename','questionnaire');

    $site = get_site();
    if( count( $courses ) == 1 && isset( $courses[$site->id] ) ){

        $strnumrespsince1 = get_string('overviewnumresplog1','questionnaire');
        $strnumrespsince = get_string('overviewnumresplog','questionnaire');

    }else{

        $strnumrespsince1 = get_string('overviewnumrespvw1','questionnaire');
        $strnumrespsince = get_string('overviewnumrespvw','questionnaire');

    }

    //Go through the list of all questionnaires build previously, and check whether
    //they have had any activity.
    foreach ($questionnaires as $questionnaire) {

        if (array_key_exists($questionnaire->id, $new) && !empty($new[$questionnaire->id])) {

            $cm = get_coursemodule_from_instance('questionnaire',$questionnaire->id);
            $context = get_context_instance(CONTEXT_MODULE,$cm->id);
            $qobject = new questionnaire($questionnaire->id, $questionnaire, $questionnaire->course, $cm);
            $is_closed = $qobject->is_closed();
            $answered =  !$qobject->user_can_take($USER->id);
            $count = $new[$questionnaire->id]->count;

            if( $count > 0  &&
            (has_capability('mod/questionnaire:readallresponseanytime',$context) ||
            (has_capability('mod/questionnaire:readallresponses',$context) && (
                $questionnaire->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                ($questionnaire->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $is_closed) ||
                ($questionnaire->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED  && $answered)
            )))){

                if( $count == 1 ){
                    $strresp = $strnumrespsince1;
                }else{
                    $strresp = $strnumrespsince;
                }

                $str = '<div class="overview questionnaire"><div class="name">'.
                    $strquestionnaires.': <a title="'.$strquestionnaires.'" href="'.
                    $CFG->wwwroot.'/mod/questionnaire/view.php?a='.$questionnaire->id.'">'.
                    $questionnaire->name.'</a></div>';
                $str .= '<div class="info">';
                $str .= $count.' '.$strresp;
                $str .= '</div></div>';

                if (!array_key_exists($questionnaire->course,$htmlarray)) {
                    $htmlarray[$questionnaire->course] = array();
                }
                if (!array_key_exists('questionnaire',$htmlarray[$questionnaire->course])) {
                    $htmlarray[$questionnaire->course]['questionnaire'] = ''; // initialize, avoid warnings
                }
                $htmlarray[$questionnaire->course]['questionnaire'] .= $str;
            }
        }
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
