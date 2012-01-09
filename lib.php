<?php  // $Id$

require_once('locallib.php');

/// Library of functions and constants for module questionnaire
/// (replace questionnaire with the name of your module and delete this line)

define ('QUESTIONNAIREUNLIMITED', 0);
define ('QUESTIONNAIREONCE', 1);
define ('QUESTIONNAIREDAILY', 2);
define ('QUESTIONNAIREWEEKLY', 3);
define ('QUESTIONNAIREMONTHLY', 4);
$QUESTIONNAIRE_TYPES = array (QUESTIONNAIREUNLIMITED => get_string('qtypeunlimited', 'questionnaire'),
                              QUESTIONNAIREONCE => get_string('qtypeonce', 'questionnaire'),
                              QUESTIONNAIREDAILY => get_string('qtypedaily', 'questionnaire'),
                              QUESTIONNAIREWEEKLY => get_string('qtypeweekly', 'questionnaire'),
                              QUESTIONNAIREMONTHLY => get_string('qtypemonthly', 'questionnaire'));
$QUESTIONNAIRE_RESPONDENTS = array ('fullname' => get_string('respondenttypefullname', 'questionnaire'),
                                    'anonymous' => get_string('respondenttypeanonymous', 'questionnaire'));
$QUESTIONNAIRE_ELIGIBLES = array ('all' => get_string('respondenteligibleall', 'questionnaire'),
                                  'students' => get_string('respondenteligiblestudents', 'questionnaire'),
                                  'teachers' => get_string('respondenteligibleteachers', 'questionnaire'));
$QUESTIONNAIRE_REALMS = array ('private' => get_string('private', 'questionnaire'),
                               'public' => get_string('public', 'questionnaire'),
                               'template' => get_string('template', 'questionnaire'));

$QUESTIONNAIRE_STUDENTVIEWRESPONSES_NEVER = 0;
$QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED = 1;
$QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED = 2;
$QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS = 3;

$QUESTIONNAIRE_RESPONSEVIEWERS =
    array ( $QUESTIONNAIRE_STUDENTVIEWRESPONSES_NEVER => get_string('responseviewstudentsnever', 'questionnaire'),
            $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED => get_string('responseviewstudentswhenanswered', 'questionnaire'),
            $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED => get_string('responseviewstudentswhenclosed', 'questionnaire'),
            $QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS => get_string('responseviewstudentsalways', 'questionnaire'));

$QUESTIONNAIRE_EDITING = 0;
$QUESTIONNAIRE_ACTIVE1 = 1;
$QUESTIONNAIRE_ENDED = 3;
$QUESTIONNAIRE_ARCHIVED = 4;
$QUESTIONNAIRE_TESTING = 8;
$QUESTIONNAIRE_ACTIVE2 = 9;

/**
 * If start and end date for the questionnaire are more than this many seconds
 * apart they will be represented by two separate events in the calendar
 */
define("QUESTIONNAIRE_MAX_EVENT_LENGTH", 5*24*60*60);   // 5 days maximum

function questionnaire_add_instance($questionnaire) {
/// Given an object containing all the necessary data,
/// (defined by the form in mod.html) this function
/// will create a new instance and return the id number
/// of the new instance.
    global $COURSE;

    // Check the realm and set it to the survey if it's set.
    if (!empty($questionnaire->sid) && !empty($questionnaire->realm)) {
// JR not needed
//        set_field('questionnaire_survey', 'realm', $questionnaire->realm, 'id', $questionnaire->sid);
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
            $sdata->theme = 'default.css';// JR DEV
            $sdata->thanks_page = '';
            $sdata->thank_head = '';
            $sdata->thank_body = '';
            $sdata->email = '';
            $sdata->owner = $COURSE->id;
            if (!($sid = $qobject->survey_update($sdata))) {
                error('Could not create a new survey!');
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
                set_field('questionnaire_survey', 'realm', 'private', 'id', $sid);
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
    //saving the questionnaire in db
    if(!$questionnaire->id = insert_record("questionnaire", $questionnaire)) {
        return false;
    }

    questionnaire_set_events($questionnaire);

    return $questionnaire->id;

}


function questionnaire_update_instance($questionnaire) {
/// Given an object containing all the necessary data,
/// (defined by the form in mod.html) this function
/// will update an existing instance with new data.

    // Check the realm and set it to the survey if its set.
    if (!empty($questionnaire->sid) && !empty($questionnaire->realm)) {
        set_field('questionnaire_survey', 'realm', $questionnaire->realm, 'id', $questionnaire->sid);
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
    questionnaire_grade_item_update($questionnaire);
    questionnaire_set_events($questionnaire);
    questionnaire_update_grades($questionnaire);
    return update_record("questionnaire", $questionnaire);
}


function questionnaire_delete_instance($id) {
/// Given an ID of an instance of this module,
/// this function will permanently delete the instance
/// and any data that depends on it.

    if (! $questionnaire = get_record('questionnaire', 'id', $id)) {
        return false;
    }

    $result = true;

    if (! delete_records('questionnaire', 'id', $questionnaire->id)) {
        $result = false;
    }

    if ($survey = get_record('questionnaire_survey', 'id', $questionnaire->sid)) {
    /// If this survey is owned by this course, delete all of the survey records and responses.
        if ($survey->owner == $questionnaire->course) {
            $result = $result && questionnaire_delete_survey($questionnaire->sid, $questionnaire->id);
        }
    }

    if ($events = get_records_select('event', "modulename = 'questionnaire' and instance = '$questionnaire->id'")) {
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
    global $CFG;

    return get_records_sql ("SELECT *
        FROM {$CFG->prefix}questionnaire_response
        WHERE survey_id = '$surveyid'
        AND username = '$userid'
        ORDER BY submitted ASC ");
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
    global $CFG;

    $user = $userid ? "AND u.id = $userid" : "";

    $sql = "SELECT u.id, u.id AS userid, r.grade AS rawgrade, r.submitted AS dategraded, r.submitted AS datesubmitted
            FROM {$CFG->prefix}user u, {$CFG->prefix}questionnaire_attempts a, {$CFG->prefix}questionnaire_response r
            WHERE u.id = a.userid AND a.qid = $questionnaire->id AND r.id = a.rid $user";

    return get_records_sql($sql);
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $assignment null means all assignments
 * @param int $userid specific user only, 0 mean all
 */
function questionnaire_update_grades($questionnaire=null, $userid=0, $nullifnone=true) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($questionnaire != null) {
        if ($grades = questionnaire_get_user_grades($questionnaire, $userid)) {
            foreach($grades as $k=>$v) {
                if ($v->rawgrade == -1) {
                    $grades[$k]->rawgrade = null;
                }
                $grades[$k]->feedback = '';
                $grades[$k]->format = '';
            }
            questionnaire_grade_item_update($questionnaire, $grades);
        } else {
            questionnaire_grade_item_update($questionnaire);
        }

    } else {
        $sql = "SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
                  FROM {$CFG->prefix}questionnaire q, {$CFG->prefix}course_modules cm, {$CFG->prefix}modules m
                 WHERE m.name='questionnaire' AND m.id=cm.module AND cm.instance=q.id";
        if ($rs = get_recordset_sql($sql)) {
            while ($questionnaire = rs_fetch_next_record($rs)) {
                if ($questionnaire->grade != 0) {
                    questionnaire_update_grades($questionnaire);
                } else {
                    questionnaire_grade_item_update($questionnaire);
                }
            }
            rs_close($rs);
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
    global $CFG;

    //Get students
    $users = get_records_sql('SELECT DISTINCT u.* '.
                             'FROM '.$CFG->prefix.'user u, '.
                             '     '.$CFG->prefix.'questionnaire_attempts qa '.
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
    global $CFG;

    /// Find surveys that don't have questionnaires associated with them.
    $sql = 'SELECT qs.* FROM '.$CFG->prefix.'questionnaire_survey qs '.
           'LEFT JOIN '.$CFG->prefix.'questionnaire q ON q.sid = qs.id '.
           'WHERE q.sid IS NULL';

    if ($surveys = get_records_sql($sql)) {
        foreach ($surveys as $survey) {
            questionnaire_delete_survey($survey->id, 0);
        }
    }
    /// Find deleted questions and remove them from database (with their associated choices, etc. // TODO
    return true;
}

function questionnaire_record_submission(&$questionnaire, $userid, $rid=0) {
    $attempt['qid'] = $questionnaire->id;
    $attempt['userid'] = $userid;
    $attempt['rid'] = $rid;
    $attempt['timemodified'] = time();
    return insert_record("questionnaire_attempts", (object)$attempt, false);
}

function questionnaire_delete_survey($sid, $qid) {
    global $QUESTIONNAIRE_ARCHIVED;
/// Until backup is implemented, just mark the survey as archived.

    $status = true;

    /// Delete all responses for the survey:
    if ($responses = get_records('questionnaire_response', 'survey_id', $sid, 'id')) {
        foreach ($responses as $response) {
            $status = $status && questionnaire_delete_response($response->id);
        }
    }

    /// There really shouldn't be any more, but just to make sure...
    delete_records('questionnaire_response', 'survey_id', $sid);
    delete_records('questionnaire_attempts', 'qid', $qid);

    /// Delete all question data for the survey:
    if ($questions = get_records('questionnaire_question', 'survey_id', $sid, 'id')) {
        foreach ($questions as $question) {
            delete_records('questionnaire_quest_choice', 'question_id', $question->id);
        }
        $status = $status && delete_records('questionnaire_question', 'survey_id', $sid);
    }

    $status = $status && delete_records('questionnaire_survey', 'id', $sid);

    return $status;
}

function questionnaire_delete_response($rid) {

    $status = true;

    /// Delete all of the survey response data:
    delete_records('questionnaire_response_bool', 'response_id', $rid);
    delete_records('questionnaire_response_date', 'response_id', $rid);
    delete_records('questionnaire_resp_multiple', 'response_id', $rid);
    delete_records('questionnaire_response_other', 'response_id', $rid);
    delete_records('questionnaire_response_rank', 'response_id', $rid);
    delete_records('questionnaire_resp_single', 'response_id', $rid);
    delete_records('questionnaire_response_text', 'response_id', $rid);

    $status = $status && delete_records('questionnaire_response', 'id', $rid);
    $status = $status && delete_records('questionnaire_attempts', 'rid', $rid);

    return $status;
}

function questionnaire_get_active_surveys_menu() {
    global $QUESTIONNAIRE_ACTIVE1;
    global $QUESTIONNAIRE_ACTIVE2;

    $select = "status in ($QUESTIONNAIRE_ACTIVE1,$QUESTIONNAIRE_ACTIVE2)";
    return get_records_select_menu('questionnaire_survey', $select);
}

function questionnaire_get_surveys_menu($status=NULL) {

    $field = ($status) ? 'status' : $status;
    return get_records_menu('questionnaire_survey', $field, $status);
}

/// Functions to call directly into phpESP.
/// Make sure a "require_once('phpESP/admin/phpESP.ini.php')" line is included.
/// Don't need to include this for all library functions, so don't.
function questionnaire_get_survey_list($courseid=0, $type='') {
    global $QUESTIONNAIRE_EDITING, $QUESTIONNAIRE_ACTIVE1, $QUESTIONNAIRE_ENDED,
           $QUESTIONNAIRE_ARCHIVED, $QUESTIONNAIRE_TESTING, $QUESTIONNAIRE_ACTIVE2;

    if ($courseid == 0) {
        if (isadmin()) {
            $select = '';
            $fields = 'id,name,owner,realm,status';
        } else {
            return false;
        }
    } else if (!empty($type)) {
        if ($type == 'public') {
            $select = 'status != '.$QUESTIONNAIRE_ARCHIVED.' AND realm = \''.$type.'\' ';
    /// Any survey owned by the user or typed as 'template' can be copied.
        } else if ($type == 'template') {
            $select = 'status != '.$QUESTIONNAIRE_ARCHIVED.' AND '
                      .'(realm = \''.$type.'\' OR owner = \''.$courseid.'\') ';
        }
        $fields = 'id,name,owner,realm,status,title';
    } else {
        $select = 'status != '.$QUESTIONNAIRE_ARCHIVED.' AND owner = \''.$courseid.'\' ';
        $fields = 'id,name,owner,realm,status';
    }
    return get_records_select('questionnaire_survey', $select, 'realm,name', $fields);
}

function questionnaire_survey_has_questions($sid) {
    return record_exists('questionnaire_question', 'survey_id', $sid, 'deleted', 'n');
}

function questionnaire_survey_exists($sid) {
    return record_exists('questionnaire_survey', 'id', $sid);
}
/// JR deprecated function
/*function questionnaire_print_survey_select($instance, $courseid=0, $sid=0, $fname='sid', $usenone=false, $type='') {
    global $CFG;

    if ($surveys = questionnaire_get_survey_list($courseid, $type)) {
        $table->head[] = get_string('select');
        $table->head[] = get_string('name');
        $table->head[] = get_string('type', 'questionnaire');
        $table->align = array('center', 'left', 'center');
        $table->size = array('*', '100%', '*');
        $table->wrap = array('', '', 'nowrap');

        if ($usenone) {
            $select = '<input type="radio" name="sid" value="0"'.(($sid == 0)?' checked':'').' />';
            $stat = '';
            $table->data[] = array($select, 'none', $stat);
        }

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
            } else {
                $select = '<input type="radio" name="'.$fname.'" value="'.$type.'-'.$survey->id.'"'.
                          (($survey->id == $sid)?' checked':'').' />';

                $view = link_to_popup_window('/mod/questionnaire/manage_survey.php?course='.$courseid.
                                             '&amp;qact=preview&amp;instance='.$instance.'&amp;sid='.$survey->id,
                                             $strpreview, $survey->title,
                                             '', '', $strpreview, '', true);
                $table->data[] = array($select, $view, $stat);
            }
        }
        print_table($table);
        return true;
    } else {
        return false;
    }
}*/

function questionnaire_get_survey_select($instance, $courseid=0, $sid=0, $type='') {
    global $CFG;

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

                $label = link_to_popup_window ($CFG->wwwroot.'/mod/questionnaire/preview.php?sid='.$survey->id.'&popup=1',
                                               null, $survey->title, 400, 500, $strpreview, null, true);
                $surveylist[$type.'-'.$survey->id] = $label;
/// JR deprecated - waiting for preview function to be restored?
/*                    link_to_popup_window('/mod/questionnaire/manage_survey.php?course='.$courseid.
                                         '&amp;qact=preview&amp;instance='.$instance.'&amp;sid='.$survey->id,
                                         $strpreview, $survey->title.' ('.$stat.')',
                                             '', '', $strpreview, '', true);*/
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
    $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED = 0;
    $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED = 1;
    $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED = 2;
    $QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS = 3;

    $LIKE = sql_ilike();

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$questionnaires = get_all_instances_in_courses('questionnaire',$courses)) {
        return;
    }

    // get all questionnaire logs in ONE query (much better!)
    $sql = "SELECT instance,cmid,l.course,COUNT(l.id) as count FROM {$CFG->prefix}log l "
        ." JOIN {$CFG->prefix}course_modules cm ON cm.id = cmid "
        ." JOIN {$CFG->prefix}questionnaire q ON cm.instance = q.id "
        ." WHERE (";
    foreach ($courses as $course) {
        $sql .= '(l.course = '.$course->id.' AND l.time > '.$course->lastaccess.') OR ';
    }
    $sql = substr($sql,0,-3); // take off the last OR

    $sql .= ") AND l.module = 'questionnaire' AND action = 'submit' "
        ." AND userid != ".$USER->id
        ." AND q.resp_view <> ".$QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED
        ." GROUP BY cmid,l.course,instance";

    if (!$new = get_records_sql($sql)) {
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
                $questionnaire->resp_view == $QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                ($questionnaire->resp_view == $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $is_closed) ||
                ($questionnaire->resp_view == $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED  && $answered)
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
    delete_records('event', 'modulename', 'questionnaire', 'instance', $questionnaire->id);
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

?>