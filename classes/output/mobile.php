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
 * Mobile output class for mod_questionnaire.
 *
 * @copyright 2018 Igor Sazonov <sovletig@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_questionnaire\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    /**
     * Returns the initial page when viewing the activity for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_view_activity($args) {
        global $OUTPUT, $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

        $args = (object) $args;
        $cmid = $args->cmid;
        $rid = isset($args->rid) ? $args->rid : 0;
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 1;
        $prevpage = 0;
        $nextpage = 0;

        list($cm, $course, $questionnaire) = questionnaire_get_standard_page_items($cmid);
        $questionnaire = new \questionnaire(0, $questionnaire, $course, $cm);
        if ($questionnaire->user_has_saved_response($USER->id) && empty($rid)) {
            $rid = $questionnaire->get_latest_responseid($USER->id);
        } else {
            $questionnaire->add_user_responses();
        }

        // Capabilities check.
        $context = \context_module::instance($cmid);
        self::require_capability($cm, $context, 'mod/questionnaire:view');

        $numpages = count($questionnaire->questionsbysec);
        // Set some variables we are going to be using.
        if (!empty($questionnaire->questionsbysec) && ($numpages > 1)) {
            if ($pagenum > 1) {
                $prevpage = $pagenum - 1;
            }
            if ($pagenum < $numpages) {
                $nextpage = $pagenum + 1;
            }
        }

        $data = [];
        $data['cmid'] = $cmid;
        $data['userid'] = $USER->id;
        $data['intro'] = $questionnaire->intro;
        $data['autonumquestions'] = $questionnaire->autonum;
        $data['id'] = $questionnaire->id;
        $data['rid'] = $rid;
        $data['surveyid'] = $questionnaire->survey->id;
        $data['pagenum'] = $pagenum;
        $data['prevpage'] = $prevpage;
        $data['nextpage'] = $nextpage;
        $latestresponse = end($questionnaire->responses);
        if (!empty($latestresponse) && ($latestresponse->complete == 'y')) {
            $data['completed'] = 1;
            $data['complete_userdate'] = userdate($latestresponse->submitted);
        } else {
            $data['completed'] = 0;
            $data['complete_userdate'] = '';
        }

        $response = null;
        if (!empty($questionnaire->responses)) {
            $response = end($questionnaire->responses);
        }
        $pagequestions = [];
        $data['pagequestions'] = [];
        $qnum = 1;
        $responses = [];
        foreach ($questionnaire->questionsbysec[$pagenum] as $questionid) {
            $question = $questionnaire->questions[$questionid];
            if ($question->supports_mobile()) {
                $pagequestions[] = $question->mobile_question_display($qnum, $questionnaire->autonum, $response);
                if ($response !== null) {
                    $responses = array_merge($responses, $question->get_mobile_response_data($response));
                }
            }
            $qnum++;
        }
        $data['pagequestions'] = $pagequestions;

        $return = [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_questionnaire/mobile_view_activity_page', $data)
                ],
            ],
            'otherdata' => [
                'responses' => json_encode($responses),
            ],
            'files' => null
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
}