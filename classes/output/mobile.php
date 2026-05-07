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

namespace mod_questionnaire\output;

use mod_questionnaire\local\response\response;
use mod_questionnaire\questionnaire as questionnaire_class;

/**
 * Mobile output class for mod_questionnaire.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the initial page when viewing the activity for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_view_activity($args) {
        global $OUTPUT, $USER;

        $args = (object) $args;

        $versionname = $args->appversioncode >= 44000 ? 'latest' : 'ionic5';
        $cmid = $args->cmid;
        $rid = isset($args->rid) ? $args->rid : 0;
        $action = isset($args->action) ? $args->action : 'index';
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 1;
        $userid = isset($args->userid) ? $args->userid : $USER->id;
        $submit = isset($args->submit) ? $args->submit : false;
        $completed = isset($args->completed) ? $args->completed : false;

        $questionnaire = questionnaire_class::from_cmid($cmid);
        $cm = $questionnaire->coursemodule();

        $data = [];
        $data['cmid'] = $cmid;
        $data['userid'] = $userid;
        $data['intro'] = $questionnaire->intro();
        $data['autonumquestions'] = $questionnaire->questions_autonumbered();
        $data['id'] = $questionnaire->id();
        $data['rid'] = $rid;
        $data['surveyid'] = $questionnaire->surveyid();
        $data['pagenum'] = $pagenum;
        $data['prevpage'] = 0;
        $data['nextpage'] = 0;

        // Capabilities check.
        $context = $questionnaire->context();
        self::require_capability($cm, $context, 'mod/questionnaire:view');

        // Any notifications will be displayed on top of main page, and prevent questionnaire from being completed. This also checks
        // appropriate capabilities.
        $data['notifications'] = $questionnaire->user_access_messages($userid);
        $responses = [];
        $result = '';

        $data['emptypage'] = 1;
        $template = "mod_questionnaire/local/mobile/$versionname/main_index_page";

        switch ($action) {
            case 'index':
                self::add_index_data($questionnaire, $data, $userid);
                $template = "mod_questionnaire/local/mobile/$versionname/main_index_page";
                break;

            case 'submit':
            case 'nextpage':
            case 'previouspage':
                // Check for notifications.
                if (!$data['notifications']) {
                    $result = $questionnaire->save_mobile_data($userid, $pagenum, $completed, $rid, $submit, $action, (array)$args);
                }
                // Fall through.
            case 'respond':
            case 'resume':
                // Completing a questionnaire.
                if (!$data['notifications']) {
                    if ($questionnaire->user_has_saved_response($userid)) {
                        if (empty($rid)) {
                            $rid = $questionnaire->get_latest_responseid($userid);
                        }
                        $questionnaire->add_response($rid);
                        $data['rid'] = $rid;
                    }
                    $loadedresponses = $questionnaire->responses()->get_loaded_responses();
                    $response = !empty($loadedresponses) ?
                        end($loadedresponses) :
                        \mod_questionnaire\local\response\response::create_from_data([]);
                    $response->sec = $pagenum;
                    if (isset($result['warnings'])) {
                        if ($action == 'submit') {
                            $response = $result['response'];
                        }
                        $data['notifications'] = $result['warnings'];
                    } else if ($action == 'nextpage') {
                        $pageresult = $result['nextpagenum'];
                        if ($pageresult === false) {
                            $pagenum = count($questionnaire->questions_by_section_all());
                        } else if (is_string($pageresult)) {
                            $data['notifications'] .= !empty($data['notifications']) ? "\n<br />$pageresult" : $pageresult;
                        } else {
                            $pagenum = $pageresult;
                        }
                    } else if ($action == 'previouspage') {
                        $prevpage = $result['nextpagenum'];
                        if ($prevpage === false) {
                            $pagenum = 1;
                        } else {
                            $pagenum = $prevpage;
                        }
                    } else if ($action == 'submit') {
                        self::add_index_data($questionnaire, $data, $userid);
                        $data['action'] = 'index';
                        $template = "mod_questionnaire/local/mobile/$versionname/main_index_page";
                        break;
                    }
                    $pagequestiondata = self::add_pagequestion_data($questionnaire, $pagenum, $response);
                    $data['pagequestions'] = $pagequestiondata['pagequestions'];
                    $responses = $pagequestiondata['responses'];
                    $questionsbysec = $questionnaire->questions_by_section_all();
                    $numpages = count($questionsbysec);
                    // Set some variables we are going to be using.
                    if (!empty($questionsbysec) && ($numpages > 1)) {
                        if ($pagenum > 1) {
                            $data['prevpage'] = true;
                        }
                        if ($pagenum < $numpages) {
                            $data['nextpage'] = true;
                        }
                    }
                    $data['pagenum'] = $pagenum;
                    $data['completed'] = 0;
                    $data['emptypage'] = 0;
                    $template = "mod_questionnaire/local/mobile/$versionname/view_activity_page";
                }
                break;

            case 'review':
                // If reviewing a submission.
                if (
                    $questionnaire->capabilities()->can_read_own_responses() &&
                    isset($args->submissionid) && !empty($args->submissionid)
                ) {
                    $questionnaire->add_response($args->submissionid);
                    $response = $questionnaire->responses()->get_response($args->submissionid);
                    $qnum = 1;
                    $pagequestions = [];
                    foreach ($questionnaire->questions() as $question) {
                        if ($question->supports_mobile()) {
                            $pagequestions[] = $question->mobile_question_display(
                                $qnum,
                                $questionnaire->questions_autonumbered()
                            );
                            $responses = array_merge($responses, $question->get_mobile_response_data($response));
                            if ($question->is_numbered()) {
                                $qnum++;
                            }
                        }
                    }
                    $data['prevpage'] = 0;
                    $data['nextpage'] = 0;
                    $data['pagequestions'] = $pagequestions;
                    $data['completed'] = 1;
                    $data['emptypage'] = 0;
                    $template = "mod_questionnaire/local/mobile/$versionname/view_activity_page";
                }
                break;
        }

        $data['hasmorepages'] = $data['prevpage'] || $data['nextpage'];

        $return = [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template($template, $data),
                ],
            ],
            'javascript' => file_get_contents($CFG->dirroot . '/mod/questionnaire/appjs/uncheckother.js'),
            'otherdata' => $responses,
            'files' => null,
        ];
        return $return;
    }

    /**
     * Confirms the user is logged in and has the specified capability.
     *
     * @param \stdClass $cm
     * @param \context $context
     * @param string $cap
     */
    protected static function require_capability(\stdClass $cm, \context $context, string $cap) {
        require_login($cm->course, false, $cm, true, true);
        require_capability($cap, $context);
    }

    /**
     * Add the submissions.
     * @param questionnaire_class $questionnaire
     * @param array $data
     * @param int $userid
     */
    protected static function add_index_data(questionnaire_class $questionnaire, array &$data, int $userid): void {
        // List any existing submissions, if user is allowed to review them.
        if ($questionnaire->capabilities()->can_read_own_responses()) {
            $questionnaire->add_user_responses();
            $submissions = [];
            foreach ($questionnaire->responses()->get_loaded_responses() as $response) {
                $submissions[] = ['submissiondate' => userdate($response->submitted_at()), 'submissionid' => $response->id()];
            }
            if (!empty($submissions)) {
                $data['submissions'] = $submissions;
            } else {
                $data['emptypage'] = 1;
            }
            if ($questionnaire->user_has_saved_response($userid)) {
                $data['resume'] = 1;
            }
            $data['emptypage'] = 0;
        }
    }

    /**
     * Add the questions for the page.
     * @param questionnaire_class $questionnaire
     * @param int $pagenum
     * @param response|null $response
     * @return array
     */
    protected static function add_pagequestion_data(
        questionnaire_class $questionnaire,
        int $pagenum,
        ?response $response = null
    ): array {
        $qnum = 1;
        $pagequestions = [];
        $responses = [];
        $questionsbysec = $questionnaire->questions_by_section_all();
        $autonumbered = $questionnaire->questions_autonumbered();

        // Find out what question number we are on — new fix for question numbering.
        $i = 0;
        if ($pagenum > 1) {
            for ($j = 2; $j <= $pagenum; $j++) {
                foreach ($questionsbysec[$j - 1] as $question) {
                    if ($question->typeid() < QUESPAGEBREAK) {
                        $i++;
                    }
                }
            }
        }
        $qnum = $i + 1;

        foreach ($questionsbysec[$pagenum] as $question) {
            if ($question->supports_mobile()) {
                $pagequestions[] = $question->mobile_question_display($qnum, $autonumbered);
                $mobileotherdata = $question->mobile_otherdata();
                if (!empty($mobileotherdata)) {
                    $responses = array_merge($responses, $mobileotherdata);
                }
                if (($response !== null) && isset($response->answers[$question->id()])) {
                    $responses = array_merge($responses, $question->get_mobile_response_data($response));
                }
                if ($question->is_numbered()) {
                    $qnum++;
                }
            }
        }

        return ['pagequestions' => $pagequestions, 'responses' => $responses];
    }
}
