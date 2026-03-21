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
    $responses = \mod_questionnaire\local\response\manager::get_user_responses_for_instance(
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

    $responses = \mod_questionnaire\local\response\manager::get_user_responses_for_instance(
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
 *
 * $settings is unused, but API requires it. Suppress PHPMD warning.
 */
function questionnaire_extend_settings_navigation(settings_navigation $settings, navigation_node $questionnairenode) {
    global $DB, $USER, $CFG;

    $individualresponse = optional_param('individualresponse', false, PARAM_INT);
    $rid = optional_param('rid', false, PARAM_INT); // Response id.
    $currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

    require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

    $cm = $settings->get_page()->cm;
    $context = $cm->context;
    $cmid = $cm->id;
    $course = $settings->get_page()->course;

    if (! $questionnaire = $DB->get_record("questionnaire", ["id" => $cm->instance])) {
        throw new \moodle_exception('invalidcoursemodule', 'mod_questionnaire');
    }

    $courseid = $course->id;
    $questionnaire = new questionnaire($course, $cm, 0, $questionnaire);

    if ($owner = $DB->get_field('questionnaire_survey', 'courseid', ['id' => $questionnaire->sid])) {
        $owner = (trim($owner) == trim($courseid));
    } else {
        $owner = true;
    }

    // On view page, currentgroupid is not yet sent as an optional_param, so get it.
    $groupmode = groups_get_activity_groupmode($cm, $course);
    if ($groupmode > 0 && $currentgroupid == 0) {
        $currentgroupid = groups_get_activity_group($questionnaire->cm);
        if (!groups_is_member($currentgroupid, $USER->id)) {
            $currentgroupid = 0;
        }
    }

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $questionnairenode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if (($i === false) && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/questionnaire:manage', $context) && $owner) {
        $url = '/mod/questionnaire/qsettings.php';
        $node = navigation_node::create(
            get_string('advancedsettings'),
            new moodle_url($url, ['id' => $cmid]),
            navigation_node::TYPE_SETTING,
            null,
            'advancedsettings',
            new pix_icon('t/edit', '')
        );
        $questionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/questionnaire:editquestions', $context) && $owner) {
        $url = '/mod/questionnaire/questions.php';
        $node = navigation_node::create(
            get_string('questions', 'questionnaire'),
            new moodle_url($url, ['id' => $cmid]),
            navigation_node::TYPE_SETTING,
            null,
            'questions',
            new pix_icon('t/edit', '')
        );
        $questionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/questionnaire:editquestions', $context) && $owner) {
        $url = '/mod/questionnaire/feedback.php';
        $node = navigation_node::create(
            get_string('feedback', 'questionnaire'),
            new moodle_url($url, ['id' => $cmid]),
            navigation_node::TYPE_SETTING,
            null,
            'feedback',
            new pix_icon('t/edit', '')
        );
        $questionnairenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/questionnaire:preview', $context)) {
        $url = '/mod/questionnaire/preview.php';
        $node = navigation_node::create(
            get_string('preview_label', 'questionnaire'),
            new moodle_url($url, ['id' => $cmid]),
            navigation_node::TYPE_SETTING,
            null,
            'preview',
            new pix_icon('t/preview', '')
        );
        $questionnairenode->add_node($node, $beforekey);
    }

    if ($questionnaire->user_can_take($USER->id)) {
        $url = '/mod/questionnaire/complete.php';
        if ($questionnaire->user_has_saved_response($USER->id)) {
            $args = ['id' => $cmid, 'resume' => 1];
            $text = get_string('resumesurvey', 'questionnaire');
        } else {
            $args = ['id' => $cmid];
            $text = get_string('answerquestions', 'questionnaire');
        }
        $node = navigation_node::create(
            $text,
            new moodle_url($url, $args),
            navigation_node::TYPE_SETTING,
            null,
            '',
            new pix_icon('i/info', 'answerquestions')
        );
        $questionnairenode->add_node($node, $beforekey);
    }
    $usernumresp = $questionnaire->count_submissions($USER->id);

    if ($questionnaire->capabilities->readownresponses && ($usernumresp > 0)) {
        $url = '/mod/questionnaire/myreport.php';

        if ($usernumresp > 1) {
            $urlargs = [
                'instance' => $questionnaire->id,
                'userid' => $USER->id,
                'byresponse' => 0,
                'action' => 'summary',
                'group' => $currentgroupid,
            ];
            $node = navigation_node::create(
                get_string('yourresponses', 'questionnaire'),
                new moodle_url($url, $urlargs),
                navigation_node::TYPE_SETTING,
                null,
                'yourresponses'
            );
            $myreportnode = $questionnairenode->add_node($node, $beforekey);

            $urlargs = [
                'instance' => $questionnaire->id,
                'userid' => $USER->id,
                'byresponse' => 0,
                'action' => 'summary',
                'group' => $currentgroupid,
            ];
            $myreportnode->add(get_string('summary', 'questionnaire'), new moodle_url($url, $urlargs));

            $urlargs = [
                'instance' => $questionnaire->id,
                'userid' => $USER->id,
                'byresponse' => 1,
                'action' => 'vresp',
                'group' => $currentgroupid,
            ];
            $byresponsenode = $myreportnode->add(
                get_string('viewindividualresponse', 'questionnaire'),
                new moodle_url($url, $urlargs)
            );

            $urlargs = [
                'instance' => $questionnaire->id,
                'userid' => $USER->id,
                'byresponse' => 0,
                'action' => 'vall',
                'group' => $currentgroupid,
            ];
            $myreportnode->add(get_string('myresponses', 'questionnaire'), new moodle_url($url, $urlargs));
            if ($questionnaire->capabilities->downloadresponses) {
                $urlargs = [
                    'instance' => $questionnaire->id,
                    'user' => $USER->id,
                    'action' => 'dwnpg',
                    'group' => $currentgroupid,
                ];
                $myreportnode->add(
                    get_string('downloadtextformat', 'questionnaire'),
                    new moodle_url(
                        '/mod/questionnaire/report.php',
                        $urlargs
                    )
                );
            }
        } else {
            $urlargs = [
                'instance' => $questionnaire->id,
                'userid' => $USER->id,
                'byresponse' => 1,
                'action' => 'vresp',
                'group' => $currentgroupid,
            ];
            $node = navigation_node::create(
                get_string('yourresponse', 'questionnaire'),
                new moodle_url(
                    $url,
                    $urlargs
                ),
                navigation_node::TYPE_SETTING,
                null,
                'yourresponse'
            );
            $myreportnode = $questionnairenode->add_node($node, $beforekey);
        }
    }

    // If questionnaire is set to separate groups, prevent user who is not member of any group
    // and is not a non-editing teacher to view All responses.
    if ($questionnaire->can_view_all_responses($usernumresp)) {
        $url = '/mod/questionnaire/report.php';
        $node = navigation_node::create(
            get_string('viewallresponses', 'questionnaire'),
            new moodle_url(
                $url,
                ['instance' => $questionnaire->id, 'action' => 'vall']
            ),
            navigation_node::TYPE_SETTING,
            null,
            'vall'
        );
        $reportnode = $questionnairenode->add_node($node, $beforekey);

        if ($questionnaire->capabilities->viewsingleresponse) {
            $summarynode = $reportnode->add(
                get_string('summary', 'questionnaire'),
                new moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'vall']
                )
            );
        } else {
            $summarynode = $reportnode;
        }
        $summarynode->add(
            get_string('order_default', 'questionnaire'),
            new moodle_url(
                '/mod/questionnaire/report.php',
                ['instance' => $questionnaire->id, 'action' => 'vall', 'group' => $currentgroupid]
            )
        );
        $summarynode->add(
            get_string('order_ascending', 'questionnaire'),
            new moodle_url(
                '/mod/questionnaire/report.php',
                ['instance' => $questionnaire->id, 'action' => 'vallasort', 'group' => $currentgroupid]
            )
        );
        $summarynode->add(
            get_string('order_descending', 'questionnaire'),
            new moodle_url(
                '/mod/questionnaire/report.php',
                ['instance' => $questionnaire->id, 'action' => 'vallarsort', 'group' => $currentgroupid]
            )
        );

        if ($questionnaire->capabilities->deleteresponses) {
            $summarynode->add(
                get_string('deleteallresponses', 'questionnaire'),
                new moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'delallresp', 'group' => $currentgroupid]
                )
            );
        }

        if ($questionnaire->capabilities->downloadresponses) {
            $summarynode->add(
                get_string('downloadtextformat', 'questionnaire'),
                new moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'dwnpg', 'group' => $currentgroupid]
                )
            );
        }
        if ($questionnaire->capabilities->viewsingleresponse) {
            $byresponsenode = $reportnode->add(
                get_string('viewbyresponse', 'questionnaire'),
                new moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid]
                )
            );

            $byresponsenode->add(
                get_string('view', 'questionnaire'),
                new moodle_url(
                    '/mod/questionnaire/report.php',
                    ['instance' => $questionnaire->id, 'action' => 'vresp', 'byresponse' => 1, 'group' => $currentgroupid]
                )
            );

            if ($individualresponse) {
                $byresponsenode->add(
                    get_string('deleteresp', 'questionnaire'),
                    new moodle_url(
                        '/mod/questionnaire/report.php',
                        [
                            'instance' => $questionnaire->id,
                            'action' => 'dresp',
                            'byresponse' => 1,
                            'rid' => $rid,
                            'group' => $currentgroupid,
                            'individualresponse' => 1,
                        ]
                    )
                );
            }
        }
    }

    $canviewgroups = true;
    $groupmode = groups_get_activity_groupmode($cm, $course);
    if ($groupmode == 1) {
        $canviewgroups = groups_has_membership($cm, $USER->id);
    }
    $canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
    if ($questionnaire->capabilities->viewsingleresponse && ($canviewallgroups || $canviewgroups)) {
        $url = '/mod/questionnaire/show_nonrespondents.php';
        $node = navigation_node::create(
            get_string('show_nonrespondents', 'questionnaire'),
            new moodle_url($url, ['id' => $cmid]),
            navigation_node::TYPE_SETTING,
            null,
            'nonrespondents'
        );
        $questionnairenode->add_node($node, $beforekey);
    }
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

    global $CFG, $COURSE, $USER, $DB;
    require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
    require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', ['id' => $courseid]);
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $questionnaire = $DB->get_record('questionnaire', ['id' => $cm->instance]);
    $questionnaire = new questionnaire($course, $cm, 0, $questionnaire);

    $context = context_module::instance($cm->id);
    $grader = has_capability('mod/questionnaire:viewsingleresponse', $context);

    // If this is a copy of a public questionnaire whose original is located in another course,
    // current user (teacher) cannot view responses.
    if ($grader) {
        // For a public questionnaire, look for the original public questionnaire that it is based on.
        if (!$questionnaire->survey_is_public_master()) {
            // For a public questionnaire, look for the original public questionnaire that it is based on.
            $originalquestionnaire = $DB->get_record(
                'questionnaire',
                ['sid' => $questionnaire->survey->id, 'course' => $questionnaire->survey->courseid]
            );
            $cmoriginal = get_coursemodule_from_instance(
                "questionnaire",
                $originalquestionnaire->id,
                $questionnaire->survey->courseid
            );
            $contextoriginal = context_course::instance($questionnaire->survey->courseid, MUST_EXIST);
            if (!has_capability('mod/questionnaire:viewsingleresponse', $contextoriginal)) {
                $tmpactivity = new stdClass();
                $tmpactivity->type = 'questionnaire';
                $tmpactivity->cmid = $cm->id;
                $tmpactivity->cannotview = true;
                $tmpactivity->anonymous = false;
                $activities[$index++] = $tmpactivity;
                return $activities;
            }
        }
    }

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin = '';
    }

    $params['timestart'] = $timestart;
    $params['questionnaireid'] = $questionnaire->id;

    $userfieldsapi = \core_user\fields::for_userpic();
    $ufields = $userfieldsapi->get_sql('u', false, '', 'useridagain', false)->selects;
    if (
        !$attempts = $DB->get_records_sql(
            "
                    SELECT qr.*,
                    {$ufields}
                    FROM {questionnaire_response} qr
                    JOIN {user} u ON u.id = qr.userid
                    $groupjoin
                    WHERE qr.submitted > :timestart
                    AND qr.questionnaireid = :questionnaireid
                    $userselect
                    $groupselect
                    ORDER BY qr.submitted ASC",
            $params
        )
    ) {
        return;
    }

    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
    $groupmode = groups_get_activity_groupmode($cm, $course);

    $usersgroups = null;
    $aname = format_string($cm->name, true);
    $userattempts = [];
    foreach ($attempts as $attempt) {
        if ($questionnaire->respondenttype != 'anonymous') {
            if (!isset($userattempts[$attempt->lastname])) {
                $userattempts[$attempt->lastname] = 1;
            } else {
                $userattempts[$attempt->lastname]++;
            }
        }
        if ($attempt->userid != $USER->id) {
            if (!$grader) {
                // View complete individual responses permission required.
                continue;
            }

            if (($groupmode == SEPARATEGROUPS) && !$accessallgroups) {
                if ($usersgroups === null) {
                    $usersgroups = groups_get_all_groups(
                        $course->id,
                        $attempt->userid,
                        $cm->groupingid
                    );
                    if (is_array($usersgroups)) {
                        $usersgroups = array_keys($usersgroups);
                    } else {
                         $usersgroups = [];
                    }
                }
                if (!array_intersect($usersgroups, $modinfo->groups[$cm->id])) {
                    continue;
                }
            }
        }

        $tmpactivity = new stdClass();

        $tmpactivity->type = 'questionnaire';
        $tmpactivity->cmid = $cm->id;
        $tmpactivity->cminstance = $cm->instance;
        // Current user is admin - or teacher enrolled in original public course.
        if (isset($cmoriginal)) {
            $tmpactivity->cminstance = $cmoriginal->instance;
        }
        $tmpactivity->cannotview = false;
        $tmpactivity->anonymous = false;
        $tmpactivity->name = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp = $attempt->submitted;
        $tmpactivity->groupid = $groupid;
        if (isset($userattempts[$attempt->lastname])) {
            $tmpactivity->nbattempts = $userattempts[$attempt->lastname];
        }

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->attemptid = $attempt->id;

        $userfieldsapi = \core_user\fields::for_userpic();
        $allnamefields = $userfieldsapi->get_sql('', false, '', '', false)->selects;
        $selects = str_replace(', ', ',', $allnamefields);
        $userfields = explode(',', $selects);
        $tmpactivity->user = new stdClass();
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                $tmpactivity->user->{$userfield} = $attempt->userid;
            } else {
                if (!empty($attempt->{$userfield})) {
                    $tmpactivity->user->{$userfield} = $attempt->{$userfield};
                } else {
                    $tmpactivity->user->{$userfield} = null;
                }
            }
        }
        if ($questionnaire->respondenttype != 'anonymous') {
            $tmpactivity->user->fullname = fullname($attempt, $viewfullnames);
        } else {
            $tmpactivity->user = '';
            unset($tmpactivity->user);
            $tmpactivity->anonymous = true;
        }
        $activities[$index++] = $tmpactivity;
    }
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
    global $OUTPUT;

    // If the questionnaire is "anonymous", then $activity->user won't have been set, so do not display respondent info.
    if ($activity->anonymous) {
        $stranonymous = ' (' . get_string('anonymous', 'questionnaire') . ')';
        $activity->nbattempts = '';
    } else {
        $stranonymous = '';
    }
    // Current user cannot view responses to public questionnaire.
    if ($activity->cannotview) {
        $strcannotview = get_string('cannotviewpublicresponses', 'questionnaire');
    }
    echo html_writer::start_tag('div');
    echo html_writer::start_tag(
        'span',
        [
            'class' => 'clearfix',
            'style' => 'margin-top:0px; background-color: white; display: inline-block;',
        ]
    );

    if (!$activity->anonymous && !$activity->cannotview) {
        echo html_writer::tag(
            'div',
            $OUTPUT->user_picture($activity->user, ['courseid' => $courseid]),
            ['style' => 'float: left; padding-right: 10px;']
        );
    }
    if (!$activity->cannotview) {
        echo html_writer::start_tag('div');
        echo html_writer::start_tag('div');

        $urlparams = [
            'action' => 'vresp',
            'instance' => $activity->cminstance,
            'group' => $activity->groupid,
            'rid' => $activity->content->attemptid,
            'individualresponse' => 1,
        ];

        $context = context_module::instance($activity->cmid);
        if (has_capability('mod/questionnaire:viewsingleresponse', $context)) {
            $report = 'report.php';
        } else {
            $report = 'myreport.php';
        }
        echo html_writer::tag(
            'a',
            get_string('response', 'questionnaire') .  ' ' . $activity->nbattempts . $stranonymous,
            ['href' => new moodle_url('/mod/questionnaire/' . $report, $urlparams)]
        );
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::start_tag('div');
        echo html_writer::start_tag('div');
        echo html_writer::tag('div', $strcannotview);
        echo html_writer::end_tag('div');
    }
    if (!$activity->anonymous  && !$activity->cannotview) {
        $url = new moodle_url('/user/view.php', ['course' => $courseid, 'id' => $activity->user->id]);
        $name = $activity->user->fullname;
        $link = html_writer::link($url, $name);
        echo html_writer::start_tag('div', ['class' => 'user']);
        echo $link . ' - ' . userdate($activity->timestamp);
        echo html_writer::end_tag('div');
    }

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('span');
    echo html_writer::end_tag('div');

    return;
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
    global $DB;

    if (!empty($data->copyid)) {
        $cm = (object)['id' => $data->coursemodule];
        $questionnaire = new questionnaire($course, $cm, 0, $data);
        $oldquestionnaireid = $DB->get_field('questionnaire', 'id', ['sid' => $data->copyid]);
        $oldcm = get_coursemodule_from_instance('questionnaire', $oldquestionnaireid);
        $oldquestionnaire = new questionnaire($course, $oldcm, $oldquestionnaireid, null);
        $oldcontext = context_module::instance($oldcm->id);
        $newcontext = context_module::instance($data->coursemodule);
        $areas = $questionnaire->get_all_file_areas();
        $oldareas = $oldquestionnaire->get_all_file_areas();
        $fs = new \mod_questionnaire\file_storage();
        foreach ($areas as $area => $ids) {
            if (is_array($ids)) {
                $oldid = current($oldareas[$area]);
                foreach ($ids as $id) {
                    $fs->copy_area_files_to_new_context(
                        $oldcontext->id,
                        $newcontext->id,
                        'mod_questionnaire',
                        $area,
                        $oldid,
                        $id
                    );
                    $oldid = next($oldareas[$area]);
                }
            } else {
                $fs->copy_area_files_to_new_context(
                    $oldcontext->id,
                    $newcontext->id,
                    'mod_questionnaire',
                    $area,
                    $oldareas[$area],
                    $ids
                );
            }
        }
    }

    return $data;
}
