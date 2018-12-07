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
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 1;
        $prevpage = 0;

        list($cm, $course, $questionnaire) = questionnaire_get_standard_page_items($cmid);
        $questionnaire = new \questionnaire(0, $questionnaire, $course, $cm);

        // Capabilities check.
        $context = \context_module::instance($cmid);
        self::require_capability($cm, $context, 'mod/questionnaire:view');

        // Set some variables we are going to be using.
        $questionnairedata = $questionnaire->get_mobile_data($USER->id);
        if (isset($questionnairedata['questions'][$pagenum - 1]) && !empty($questionnairedata['questions'][$pagenum - 1])) {
            $prevpage = $pagenum - 1;
        }

        $data = [
            'questionnaire' => $questionnairedata,
            'cmid' => $cmid,
            'courseid' => intval($cm->course),
            'pagenum' => $pagenum,
            'userid' => $USER->id,
            'nextpage' => 0,
            'prevpage' => 0,
            'emptypage' => false
        ];
        // Check for required fields filled.
        $break = false;
        if (($pagenum - 1) > 0 && isset($questionnairedata['questions'][$pagenum - 1]) &&
            !empty($questionnairedata['questions'][$pagenum - 1])) {
            $prepn = $pagenum - 1;
            $cnt = 0;
            while (($prepn) > 0 && isset($questionnairedata['questions'][$prepn]) &&
                !empty($questionnairedata['questions'][$prepn])) {
                if (($prepn) > 0 && isset($questionnairedata['questions'][$prepn]) &&
                    !empty($questionnairedata['questions'][$prepn])) {
                    $keys = array_keys($questionnairedata['questions'][$prepn]);
                    foreach ($keys as $questionid) {
                        if (isset($questionnairedata['questionsinfo'][$prepn][$questionid]) &&
                            $questionnairedata['questionsinfo'][$prepn][$questionid]['required'] === 'y' &&
                            (!isset($questionnairedata['answered'][$questionid]) ||
                                empty($questionnairedata['answered'][$questionid]))) {
                            $pagenum = $prepn;
                            $prepn = 0;
                            $break = true;
                            break;
                        } else {
                            $cnt++;
                            if (count($keys) == $cnt) {
                                $break = true;
                            }
                        }
                    }
                    if ($break) {
                        break;
                    }
                }
                if ($break) {
                    break;
                }
            }
        }
        if (intval($args->pagenum) == $pagenum) {
            if (isset($questionnairedata['questions'][$pagenum - 1]) && !empty($questionnairedata['questions'][$pagenum - 1])) {
                $prevpage = $pagenum - 1;
            }
            $rid = $DB->get_field('questionnaire_response', 'id',
                [
                    'questionnaireid' => $questionnairedata['questionnaire']['questionnaireid'],
                    'complete' => 'n',
                    'userid' => $USER->id
                ]);
            if (isset($questionnairedata['questions'][$pagenum]) && !empty($questionnairedata['questions'][$pagenum])) {
                // Search for the next page to output.
                while (!$questionnaire->eligible_questions_on_page($pagenum, $rid)) {
                    if (isset($questionnairedata['questions'][$pagenum]) && !empty($questionnairedata['questions'][$pagenum])) {
                        $pagenum++;
                    } else {
                        $cmid = 0;
                        break;
                    }
                }
            }
            if ($prevpage > 0 && isset($questionnairedata['questions'][$prevpage]) &&
                !empty($questionnairedata['questions'][$prevpage])) {
                while (!$questionnaire->eligible_questions_on_page($prevpage, $rid)) {
                    if ($prevpage > 0 && isset($questionnairedata['questions'][$prevpage]) &&
                        !empty($questionnairedata['questions'][$prevpage])) {
                        $prevpage--;
                    } else {
                        break;
                    }
                }
            }
        }
        if ($cmid) {
            $data['completed'] = (isset($questionnairedata['response']['complete']) &&
                $questionnairedata['response']['complete'] == 'y') ? 1 : 0;
            $data['complete_userdate'] = (isset($questionnairedata['response']['complete']) &&
                $questionnairedata['response']['complete'] == 'y') ? userdate($questionnairedata['response']['submitted']) : '';
            if (isset($questionnairedata['questions'][$pagenum])) {
                $i = 0;
                foreach ($questionnairedata['questions'][$pagenum] as $questionid => $choices) {
                    if (isset($questionnairedata['questionsinfo'][$pagenum][$questionid]) &&
                        !empty($questionnairedata['questionsinfo'][$pagenum][$questionid])) {
                        $data['questions'][$pagenum][$i]['info'] = $questionnairedata['questionsinfo'][$pagenum][$questionid];
                        if ($data['questions'][$pagenum][$i]['info']['required'] == 'n') {
                            unset($data['questions'][$pagenum][$i]['info']['required']);
                        }
                        $ii = 0;
                        foreach ($choices as $k => $v) {
                            $data['questions'][$pagenum][$i]['choices'][$ii] = (array) $v;
                            $ii++;
                        }
                        if (count($choices) == 1) {
                            $data['questions'][$pagenum][$i]['value'] = $data['questions'][$pagenum][$i]['choices'][0]['value'];
                        }
                        $i++;
                    }
                }
                if (isset($data['questions'][$pagenum]) && !empty($data['questions'][$pagenum])) {
                    $i = 0;
                    foreach ($data['questions'][$pagenum] as $arr) {
                        $data['pagequestions'][$i] = $arr;
                        $i++;
                    }
                }
                if (isset($questionnairedata['questions'][$pagenum + 1]) && !empty($questionnairedata['questions'][$pagenum + 1])) {
                    $data['nextpage'] = $pagenum + 1;
                }
                if ($prevpage) {
                    $data['prevpage'] = $prevpage;
                }
            }
        } else {
            $data['emptypage'] = true;
            $data['emptypage_content'] = get_string('questionnaire:submit', 'questionnaire');
        }
        return [
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
                'string_required' => get_string('required')
            ],
            'files' => null
        ];
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