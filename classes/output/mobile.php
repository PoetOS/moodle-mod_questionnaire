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
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 0;
        $prevpage = 0;

        list($cm, $course, $questionnaire) = questionnaire_get_standard_page_items($cmid);
        $questionnaire = new \questionnaire(0, $questionnaire, $course, $cm);
        $questionnaire->add_user_responses();

        // Capabilities check.
        $context = \context_module::instance($cmid);
        self::require_capability($cm, $context, 'mod/questionnaire:view');

        // Set some variables we are going to be using.
//        $questionnairedata = $questionnaire->get_mobile_data($USER->id);
        if (!empty($questionnaire->questionsbysec) && (count($questionnaire->questionsbysec) > 1) && ($pagenum > 0)) {
            $prevpage = $pagenum - 1;
        }

        $data = [];
        $data['cmid'] = $cmid;
        $data['userid'] = $USER->id;
        $data['intro'] = $questionnaire->intro;
        $data['autonumquestions'] = $questionnaire->autonum;
        $data['id'] = $questionnaire->id;
        $data['surveyid'] = $questionnaire->survey->id;
        $data['pagenum'] = $pagenum;
        $data['prevpage'] = $prevpage;
        $data['nextpage'] = 0;
        $latestresponse = end($questionnaire->responses);
        if (!empty($latestresponse) && ($latestresponse->complete == 'y')) {
            $data['completed'] = 1;
            $data['complete_userdate'] = userdate($latestresponse->submitted);
        } else {
            $data['completed'] = 0;
            $data['complete_userdate'] = '';
        }
        $pagequestions = [];
        $data['pagequestions'] = [];
        $qnum = 1;
        foreach ($questionnaire->questionsbysec[$pagenum] as $questionid) {
            $pagequestion['qnum'] = $qnum;
            $qnum++;
        }

        foreach ($questionnaire->questionsbysec[$pagenum] as $questionid) {
//        foreach ($questionnairedata['questions'][$pagenum] as $questionid => $choices) {

            $qnum++;
            if ($questionnaire->questions[$questionid]->supports_mobile() &&
                ($mobiledata =
                    $questionnaire->questions[$questionid]->get_mobile_question_data($qnum, $ret['questionnaire']['autonumquestions']))) {
                $ret['questionsinfo'][$pagenum][$question->id] = $mobiledata->questionsinfo;
                $ret['fields'][$mobiledata->questionsinfo['fieldkey']] = $mobiledata->fields;
                $ret['questions'][$pagenum][$question->id] = $mobiledata->questions;
                $ret['responses']['response_' . $question->type_id . '_' . $question->id] = $mobiledata->responses;
            }



            $pagequestion = $questionnairedata['questionsinfo'][$pagenum][$questionid];
            // Do an array_merge to reindex choices with standard numerical indexing.
            $pagequestion['choices'] = array_merge([], $choices);
            $pagequestions[] = $pagequestion;
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
                'fields' => json_encode($questionnairedata['fields']),
                'questionsinfo' => json_encode($questionnairedata['questionsinfo']),
                'questions' => json_encode($questionnairedata['questions']),
                'pagequestions' => json_encode($data['pagequestions']),
                'responses' => json_encode($questionnairedata['responses']),
                'pagenum' => $pagenum,
                'nextpage' => $data['nextpage'],
                'prevpage' => $data['prevpage'],
                'completed' => $data['completed'],
                'intro' => $questionnairedata['questionnaire']['intro'],
                'string_required' => get_string('required'),
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