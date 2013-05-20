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
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * @return array all other caps used in module
 */
function questionnaire_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

function questionnaire_add_instance($questionnaire) {
/// Given an object containing all the necessary data,
/// (defined by the form in mod.html) this function
/// will create a new instance and return the id number
/// of the new instance.
    global $COURSE, $DB;
    require_once('questionnaire.class.php');
    require_once('locallib.php');

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

/// Given an object containing all the necessary data,
/// (defined by the form in mod.html) this function
/// will update an existing instance with new data.
function questionnaire_update_instance($questionnaire) {
    global $DB;
    require_once('locallib.php');

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
    // JR removed line because triggers error in moodle 2.3 STRICT STANDARDS mode ???
	//questionnaire_update_grades($questionnaire);
    return $DB->update_record("questionnaire", $questionnaire);
}

/// Given an ID of an instance of this module,
/// this function will permanently delete the instance
/// and any data that depends on it.
function questionnaire_delete_instance($id) {
    global $DB;
    require_once('locallib.php');

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

/// Return a small object with summary information about what a
/// user has done with a given particular instance of this module
/// Used for user activity reports.
/// $return->time = the time they did it
/// $return->info = a short text description
function questionnaire_user_outline($course, $user, $mod, $questionnaire) {
    require_once('locallib.php');

    $result = new stdClass();
    if ($responses = questionnaire_get_user_responses($questionnaire->sid, $user->id, $complete=true)) {
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

/// Print a detailed representation of what a  user has done with
/// a given particular instance of this module, for user activity reports.
function questionnaire_user_complete($course, $user, $mod, $questionnaire) {
    require_once('locallib.php');

    if ($responses = questionnaire_get_user_responses($questionnaire->sid, $user->id, $complete=false)) {
        foreach ($responses as $response) {
            if ($response->complete == 'y') {
                echo get_string('submitted', 'questionnaire').' '.userdate($response->submitted).'<br />';
            } else {
                echo get_string('attemptstillinprogress', 'questionnaire').' '.userdate($response->submitted).'<br />';
            }
        }
    } else {
       print_string('noresponses', 'questionnaire');
    }

    return true;
}

/// Given a course and a time, this module should find recent activity
/// that has occurred in questionnaire activities and print it out.
/// Return true if there was output, or false is there was none.
function questionnaire_print_recent_activity($course, $isteacher, $timestart) {
    global $CFG;

    return false;  //  True if anything was printed, otherwise false
}

/// Function to be run periodically according to the moodle cron
/// This function searches for things that need to be done, such
/// as sending out mail, toggling flags etc ...
function questionnaire_cron () {
    require_once('locallib.php');
    global $CFG;

    return questionnaire_cleanup();
}

/// Must return an array of grades for a given instance of this module,
/// indexed by user.  It also returns a maximum allowed grade.
function questionnaire_grades($questionnaireid) {
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
                    $grades[$v->userid] = new stdClass();
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

//////////////////////////////////////////////////////////////////////////////////////
/// Any other questionnaire functions go here.  Each of them must have a name that
/// starts with questionnaire_

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
    require_once('locallib.php');

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
    require_once('questionnaire.class.php');
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