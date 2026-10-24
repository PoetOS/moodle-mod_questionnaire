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

namespace mod_questionnaire\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use mod_questionnaire\questionnaire;

/**
 * Contains class mod_questionnaire\privacy\provider
 *
 * @package    mod_questionnaire
 * @copyright  2018 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The collection to add metadata to.
     * @return  collection  The array of metadata
     */
    public static function get_metadata(collection $collection): collection {

        // Add all of the relevant tables and fields to the collection.
        $collection->add_database_table('questionnaire_response', [
            'userid' => 'privacy:metadata:questionnaire_response:userid',
            'questionnaireid' => 'privacy:metadata:questionnaire_response:questionnaireid',
            'complete' => 'privacy:metadata:questionnaire_response:complete',
            'grade' => 'privacy:metadata:questionnaire_response:grade',
            'submitted' => 'privacy:metadata:questionnaire_response:submitted',
        ], 'privacy:metadata:questionnaire_response');

        $collection->add_database_table('questionnaire_response_bool', [
            'responseid' => 'privacy:metadata:questionnaire_response_bool:response_id',
            'questionid' => 'privacy:metadata:questionnaire_response_bool:question_id',
            'choiceid' => 'privacy:metadata:questionnaire_response_bool:choice_id',
        ], 'privacy:metadata:questionnaire_response_bool');

        $collection->add_database_table('questionnaire_response_date', [
            'responseid' => 'privacy:metadata:questionnaire_response_date:response_id',
            'questionid' => 'privacy:metadata:questionnaire_response_date:question_id',
            'response' => 'privacy:metadata:questionnaire_response_date:response',
        ], 'privacy:metadata:questionnaire_response_date');

        $collection->add_database_table('questionnaire_response_other', [
            'responseid' => 'privacy:metadata:questionnaire_response_other:response_id',
            'questionid' => 'privacy:metadata:questionnaire_response_other:question_id',
            'choiceid' => 'privacy:metadata:questionnaire_response_other:choice_id',
            'response' => 'privacy:metadata:questionnaire_response_other:response',
        ], 'privacy:metadata:questionnaire_response_other');

        $collection->add_database_table('questionnaire_response_rank', [
            'responseid' => 'privacy:metadata:questionnaire_response_rank:response_id',
            'questionid' => 'privacy:metadata:questionnaire_response_rank:question_id',
            'choiceid' => 'privacy:metadata:questionnaire_response_rank:choice_id',
            'rankvalue' => 'privacy:metadata:questionnaire_response_rank:rankvalue',
        ], 'privacy:metadata:questionnaire_response_rank');

        $collection->add_database_table('questionnaire_response_text', [
            'responseid' => 'privacy:metadata:questionnaire_response_text:response_id',
            'questionid' => 'privacy:metadata:questionnaire_response_text:question_id',
            'response' => 'privacy:metadata:questionnaire_response_text:response',
        ], 'privacy:metadata:questionnaire_response_text');

        $collection->add_database_table('questionnaire_resp_multiple', [
            'responseid' => 'privacy:metadata:questionnaire_resp_multiple:response_id',
            'questionid' => 'privacy:metadata:questionnaire_resp_multiple:question_id',
            'choiceid' => 'privacy:metadata:questionnaire_resp_multiple:choice_id',
        ], 'privacy:metadata:questionnaire_resp_multiple');

        $collection->add_database_table('questionnaire_resp_single', [
            'responseid' => 'privacy:metadata:questionnaire_resp_single:response_id',
            'questionid' => 'privacy:metadata:questionnaire_resp_single:question_id',
            'choiceid' => 'privacy:metadata:questionnaire_resp_single:choice_id',
        ], 'privacy:metadata:questionnaire_resp_single');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
             FROM {context} c
       INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
       INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
       INNER JOIN {questionnaire} q ON q.id = cm.instance
       INNER JOIN {questionnaire_response} qr ON qr.questionnaireid = q.id
            WHERE qr.userid = :attemptuserid
       ";

        $params = [
            'modname' => 'questionnaire',
            'contextlevel' => CONTEXT_MODULE,
            'attemptuserid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist containing the list of users who have data in this
     * context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['modulename' => 'questionnaire', 'instanceid' => $context->instanceid];

        // Questionnaire respondents.
        $sql = "SELECT qr.userid
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
              JOIN {questionnaire} q ON q.id = cm.instance
              JOIN {questionnaire_response} qr ON qr.questionnaireid = q.id
             WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                   q.id AS qid, q.course AS qcourse,
                   qr.id AS responseid, qr.submitted AS lastsaved, qr.complete AS complete
              FROM {context} c
        INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
        INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
        INNER JOIN {questionnaire} q ON q.id = cm.instance
        INNER JOIN {questionnaire_response} qr ON qr.questionnaireid = q.id
             WHERE c.id {$contextsql}
                   AND qr.userid = :userid
          ORDER BY cm.id, qr.id ASC";

        $params = ['modname' => 'questionnaire', 'contextlevel' => CONTEXT_MODULE, 'userid' => $user->id] + $contextparams;

        // There can be more than one attempt per instance, so we'll gather them by cmid.
        $lastcmid = 0;
        $responsedata = [];
        $responses = $DB->get_recordset_sql($sql, $params);
        foreach ($responses as $response) {
            // If we've moved to a new instance, write the previous instance's data and reinitialise.
            if ($lastcmid != $response->cmid) {
                if (!empty($responsedata)) {
                    $context = \context_module::instance($lastcmid);
                    // Fetch the generic module data for the questionnaire.
                    $contextdata = \core_privacy\local\request\helper::get_context_data($context, $user);
                    // Merge with attempt data and write it.
                    $contextdata = (object)array_merge((array)$contextdata, $responsedata);
                    \core_privacy\local\request\writer::with_context($context)->export_data([], $contextdata);
                }
                $responsedata = [];
                $lastcmid = $response->cmid;
                $questionnaire = questionnaire::from_instanceid($response->qid);
            }
            $responsedata['responses'][] = [
                'complete' => (($response->complete == 'y') ? get_string('yes') : get_string('no')),
                'lastsaved' => \core_privacy\local\request\transform::datetime($response->lastsaved),
                'questions' => $questionnaire->get_structured_response($response->responseid),
            ];
        }
        $responses->close();

        // The data for the last activity won't have been written yet, so make sure to write it now!
        if (!empty($responsedata)) {
            $context = \context_module::instance($lastcmid);
            // Fetch the generic module data for the questionnaire.
            $contextdata = \core_privacy\local\request\helper::get_context_data($context, $user);
            // Merge with attempt data and write it.
            $contextdata = (object)array_merge((array)$contextdata, $responsedata);
            \core_privacy\local\request\writer::with_context($context)->export_data([], $contextdata);
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!($context instanceof \context_module)) {
            return;
        }

        if (!$cmrecord = get_coursemodule_from_id('questionnaire', $context->instanceid)) {
            return;
        }

        $questionnaire = questionnaire::from_cm(\cm_info::create($cmrecord));
        $qresponses = $questionnaire->responses();
        $responses = $qresponses->get_responses();
        foreach ($responses as $response) {
            $qresponses->delete_response($response);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_module)) {
                continue;
            }
            if (!$cmrecord = get_coursemodule_from_id('questionnaire', $context->instanceid)) {
                continue;
            }

            $questionnaire = questionnaire::from_cm(\cm_info::create($cmrecord));
            $qresponses = $questionnaire->responses();
            $responses = $qresponses->get_responses($userid);
            foreach ($responses as $response) {
                $qresponses->delete_response($response);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The approved context and user information to delete
     * information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$cmrecord = get_coursemodule_from_id('questionnaire', $context->instanceid)) {
            return;
        }

        $questionnaire = questionnaire::from_cm(\cm_info::create($cmrecord));
        $qresponses = $questionnaire->responses();
        foreach ($userlist->get_userids() as $userid) {
            $responses = $qresponses->get_responses($userid);
            foreach ($responses as $response) {
                $qresponses->delete_response($response);
            }
        }
    }
}
