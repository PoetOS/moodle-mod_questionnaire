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
 * Library of functions and constants for module questionnaire.
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** This may no longer be needed. */
define('QUESTIONNAIRE_RESETFORM_RESET', 'questionnaire_reset_data_');

/** This may no longer be needed. */
define('QUESTIONNAIRE_RESETFORM_DROP', 'questionnaire_drop_questionnaire_');

/**
 * Library supports implementation.
 * @param string $feature
 * @return bool|null
 */
function questionnaire_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;
        default:
            return null;
    }
}

/**
 * Return any extra capabilities.
 * @return array all other caps used in module
 */
function questionnaire_get_extra_capabilities() {
    return ['moodle/site:accessallgroups'];
}

/**
 * Implementation of add_instance.
 * @param stdClass $questionnaire
 * @return bool|int
 */
function questionnaire_add_instance($questionnaire) {
    return \mod_questionnaire\questionnaire::add_instance($questionnaire);
}

/**
 * Given an object containing all the necessary data, (defined by the form in mod.html) this function will update an existing
 * instance with new data.
 * @param stdClass $questionnaire
 * @return bool
 */
function questionnaire_update_instance($questionnaire) {
    // Grade item update is a Moodle gradebook API concern, kept in lib.php.
    $questionnaire->id = $questionnaire->instance;
    questionnaire_grade_item_update($questionnaire);
    return \mod_questionnaire\questionnaire::update_instance($questionnaire);
}

/**
 * Given an ID of an instance of this module, this function will permanently delete the instance and any data that depends on it.
 * @param int $id
 * @return bool
 */
function questionnaire_delete_instance($id) {
    return \mod_questionnaire\questionnaire::delete_instance($id);
}

/**
 * Add a get_coursemodule_info function in case any questionnaire type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function questionnaire_get_coursemodule_info($coursemodule) {
    global $DB;

    $questionnaire = $DB->get_record(
        'questionnaire',
        ['id' => $coursemodule->instance],
        'id,
        name,
        intro,
        introformat,
        opendate,
        closedate,
        completionsubmit',
    );

    if (!$questionnaire) {
        return null;
    }

    $result = new cached_cm_info();
    $result->name = $questionnaire->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        // Based on the function quiz_get_coursemodule_info() in the quiz module.
        $result->content = format_module_intro('questionnaire', $questionnaire, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $questionnaire->completionsubmit;
    }

    // Populate some other values that can be used in calendar or on dashboard.
    if ($questionnaire->opendate) {
        $result->customdata['timeopen'] = $questionnaire->opendate;
    }
    if ($questionnaire->closedate) {
        $result->customdata['timeclose'] = $questionnaire->closedate;
    }

    return $result;
}

/**
 * Return a small object with summary information about what a user has done with a given particular instance of this module.
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description.
 * $course and $mod are unused, but API requires them. Suppress PHPMD warning.
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $questionnaire
 * @return stdClass
 */
function questionnaire_user_outline($course, $user, $mod, $questionnaire) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

    $result = new stdClass();
    $responses = \mod_questionnaire\local\response\questionnaire_responses::get_user_responses_for_instance(
        ($questionnaire instanceof \mod_questionnaire\questionnaire) ? $questionnaire->id() : $questionnaire->id,
        $user->id,
        true
    );
    if ($responses) {
        $n = count($responses);
        if ($n == 1) {
            $result->info = $n . ' ' . get_string("response", "questionnaire");
        } else {
            $result->info = $n . ' ' . get_string("responses", "questionnaire");
        }
        $lastresponse = array_pop($responses);
        $result->time = $lastresponse->submitted;
    } else {
        $result->info = get_string("noresponses", "questionnaire");
    }
    return $result;
}

/**
 * Print a detailed representation of what a  user has done with a given particular instance of this module, for user
 * activity reports.
 * $course and $mod are unused, but API requires them. Suppress PHPMD warning.
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $questionnaire
 * @return bool
 */
function questionnaire_user_complete($course, $user, $mod, $questionnaire) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

    $responses = \mod_questionnaire\local\response\questionnaire_responses::get_user_responses_for_instance(
        ($questionnaire instanceof \mod_questionnaire\questionnaire) ? $questionnaire->id() : $questionnaire->id,
        $user->id,
        false
    );
    if ($responses) {
        foreach ($responses as $response) {
            if ($response->complete == 'y') {
                echo get_string('submitted', 'questionnaire') . ' ' . userdate($response->submitted) . '<br />';
            } else {
                echo get_string('attemptstillinprogress', 'questionnaire') . ' ' . userdate($response->submitted) . '<br />';
            }
        }
    } else {
        print_string('noresponses', 'questionnaire');
    }

    return true;
}

/**
 * Given a course and a time, this module should find recent activity that has occurred in questionnaire activities and print it
 * out.
 * Return true if there was output, or false is there was none.
 * $course, $isteacher and $timestart are unused, but API requires them. Suppress PHPMD warning.
 * @param stdClass $course
 * @param bool $isteacher
 * @param int $timestart
 * @return false
 */
function questionnaire_print_recent_activity($course, $isteacher, $timestart) {
    return false;  // True if anything was printed, otherwise false.
}

/**
 * Must return an array of grades for a given instance of this module, indexed by user.  It also returns a maximum allowed grade.
 * $questionnaireid is unused, but API requires it. Suppress PHPMD warning.
 * @param int $questionnaireid
 * @return null
 */
function questionnaire_grades($questionnaireid) {
    return null;
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $questionnaire
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function questionnaire_get_user_grades($questionnaire, $userid = 0) {
    return \mod_questionnaire\questionnaire::get_user_grades($questionnaire, $userid);
}

/**
 * Update grades by firing grade_updated event.
 * $nullifnone is unused, but API requires it. Suppress PHPMD warning.
 * @param stdClass $questionnaire
 * @param int $userid
 * @param bool $nullifnone
 */
function questionnaire_update_grades($questionnaire = null, $userid = 0, $nullifnone = true) {
    \mod_questionnaire\questionnaire::update_grades($questionnaire, $userid, $nullifnone);
}

/**
 * Create grade item for given questionnaire
 *
 * @param stdClass $questionnaire object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function questionnaire_grade_item_update($questionnaire, $grades = null) {
    return \mod_questionnaire\questionnaire::grade_item_update($questionnaire, $grades);
}

/**
 * This function returns if a scale is being used by one questionnaire
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 * @param int $questionnaireid
 * @param int $scaleid
 * @return boolean True if the scale is used by any questionnaire
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 */
function questionnaire_scale_used($questionnaireid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of questionnaire
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid
 * @return boolean True if the scale is used by any questionnaire
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 */
function questionnaire_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Serves the questionnaire attachments. Implements needed access control ;-)
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param mixed $options
 * @return bool false if file not found, does not return if found - justsend the file
 *
 * $forcedownload is unused, but API requires it. Suppress PHPMD warning.
 */
function questionnaire_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = ['intro', 'info', 'thankbody', 'question', 'feedbacknotes', 'sectionheading', 'feedback', 'response_file'];
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $componentid = (int)array_shift($args);

    if ($filearea == 'question') {
        if (!$DB->record_exists('questionnaire_question', ['id' => $componentid])) {
            return false;
        }
    } else if ($filearea == 'sectionheading') {
        if (!$DB->record_exists('questionnaire_fb_sections', ['id' => $componentid])) {
            return false;
        }
    } else if ($filearea == 'feedback') {
        if (!$DB->record_exists('questionnaire_feedback', ['id' => $componentid])) {
            return false;
        }
    } else if ($filearea == 'response_file') {
        if (!$DB->record_exists('questionnaire_response_file', ['id' => $componentid])) {
            return false;
        }
    } else {
        if (!$DB->record_exists('questionnaire_survey', ['id' => $componentid])) {
            return false;
        }
    }

    if (!$DB->record_exists('questionnaire', ['id' => $cm->instance])) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_questionnaire/$filearea/$componentid/$relativepath";
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    // Finally send the file.
    send_stored_file($file, null, 0, $forcedownload, $options); // Download MUST be forced - security!
}
/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $questionnairenode The node to add module settings to
 */
function questionnaire_extend_settings_navigation(settings_navigation $settings, navigation_node $questionnairenode) {
    \mod_questionnaire\questionnaire::from_cm($settings->get_page()->cm)->extend_settings_navigation($settings, $questionnairenode);
}

// Any other questionnaire functions go here.  Each of them must have a name that
// starts with questionnaire_.

/**
 * Return the view actions.
 * @return string[]
 */
function questionnaire_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * Return the post actions.
 * @return string[]
 */
function questionnaire_get_post_actions() {
    return ['submit', 'update'];
}

/**
 * Return the recent activity.
 * @param array $activities
 * @param int $index
 * @param int $timestart
 * @param int $courseid
 * @param int $cmid
 * @param int $userid
 * @param int $groupid
 * @return mixed|void
 */
function questionnaire_get_recent_mod_activity(
    &$activities,
    &$index,
    $timestart,
    $courseid,
    $cmid,
    $userid = 0,
    $groupid = 0
) {
    \mod_questionnaire\questionnaire::get_recent_mod_activity($activities, $index, $timestart, $courseid, $cmid, $userid, $groupid);
}

/**
 * Prints all users who have completed a specified questionnaire since a given time
 *
 * @param stdClass $activity
 * @param int $courseid
 * @param string $detail not used but needed for compability
 * @param array $modnames
 * @return void Output is echo'd
 *
 * $details and $modenames are unused, but API requires them. Suppress PHPMD warning.
 */
function questionnaire_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    \mod_questionnaire\questionnaire::print_recent_mod_activity($activity, $courseid, $detail, $modnames);
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the questionnaire.
 *
 * @param stdClass $mform the course reset form that is being built.
 */
function questionnaire_reset_course_form_definition($mform) {
    $mform->addElement('header', 'questionnaireheader', get_string('modulenameplural', 'questionnaire'));
    $mform->addElement(
        'advcheckbox',
        'reset_questionnaire',
        get_string('removeallquestionnaireattempts', 'questionnaire')
    );
}

/**
 * Course reset form defaults.
 * @param stdClass $course
 * @return array the defaults.
 *
 * Function parameters are unused, but API requires them. Suppress PHPMD warning.
 */
function questionnaire_reset_course_form_defaults($course) {
    return ['reset_questionnaire' => 1];
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * questionnaire responses for course $data->courseid.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array status array
 */
function questionnaire_reset_userdata($data) {
    return \mod_questionnaire\questionnaire::reset_userdata($data);
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_questionnaire_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory
) {
    $cm = get_fast_modinfo($event->courseid)->instances['questionnaire'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/questionnaire/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Called after the activity and module have been created. Use this to copy any images if the questionnaire was created from another
 * questionnaire survey.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @throws coding_exception
 */
function mod_questionnaire_coursemodule_edit_post_actions($data, $course) {
    return \mod_questionnaire\questionnaire::coursemodule_edit_post_actions($data, $course);
}
