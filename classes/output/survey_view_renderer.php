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

use mod_questionnaire\questionnaire;
use mod_questionnaire\submission_notifier;

/**
 * Builds the student-facing survey-completion page.
 *
 * Takes the Moodle plugin renderer and a templatable page object, and fills the
 * page in place from data on the supplied questionnaire. Replaces the
 * page-filling responsibilities that used to live on the questionnaire class
 * itself (view(), print_survey(), and their private helpers).
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class survey_view_renderer {
    /**
     * Constructor.
     *
     * @param \plugin_renderer_base $renderer The plugin's Moodle renderer.
     * @param object $page A templatable page object (viewpage, completepage, etc.) with an add_to_page() method.
     */
    public function __construct(
        /** @var \plugin_renderer_base The plugin's Moodle renderer. */
        protected readonly \plugin_renderer_base $renderer,
        /** @var object A templatable page object (viewpage, completepage, etc.). */
        protected readonly object $page,
    ) {
    }

    /**
     * Build the main view page for a user taking the survey.
     *
     * Mirrors the legacy questionnaire::view(): writes an access-denied notification
     * when access is blocked, otherwise renders the survey form and processes a
     * "Submit Survey" POST when one is present.
     *
     * @param questionnaire $q
     * @param int $userid The user viewing the page.
     * @return void
     */
    public function build_view(questionnaire $q, int $userid): void {
        $message = $q->user_access_messages($userid, true);
        if ($message !== null) {
            $this->page->add_to_page('notifications', $message);
            return;
        }
        $msg = $this->build_survey_form($q, $userid, $userid);

        $viewform = data_submitted();
        if (
            $viewform && confirm_sesskey() &&
            isset($viewform->submit) && isset($viewform->submittype) &&
            ($viewform->submittype == "Submit Survey") && empty($msg)
        ) {
            if (!empty($viewform->rid)) {
                $viewform->rid = (int)$viewform->rid;
            }
            if (!empty($viewform->sec)) {
                $viewform->sec = (int)$viewform->sec;
            }
            $rid = $q->existing_response_action($viewform, $userid);
            $q->responses()->commit_submission_response($rid, $userid);
            (new submission_notifier($q))->notify($rid);
            $q->response_goto_thankyou();
        }
    }

    /**
     * Render the survey form for completion.
     *
     * Processes navigation form actions (next/prev/resume/submit) and fills the page
     * with the current section's questions and control buttons.
     *
     * @param questionnaire $q
     * @param int $quser
     * @param int|null $userid
     * @return string|null Error message string, or null on success.
     */
    public function build_survey_form(questionnaire $q, int $quser, ?int $userid = null): ?string {
        global $SESSION, $CFG;

        if (!($formdata = data_submitted()) || !confirm_sesskey()) {
            $formdata = new \stdClass();
        }

        $formdata->rid = $q->get_latest_responseid($quser);
        if (($formdata->rid != 0) && (empty($formdata->sec) || intval($formdata->sec) < 1)) {
            $formdata->sec = $q->responses()->response_select_max_sec($formdata->rid);
        }
        if (empty($formdata->sec)) {
            $formdata->sec = 1;
        } else {
            $formdata->sec = (intval($formdata->sec) > 0) ? intval($formdata->sec) : 1;
        }

        $questionsbysec = $q->questions_by_section_all();
        $numsections = count($questionsbysec);
        $msg = '';
        $action = $CFG->wwwroot . '/mod/questionnaire/complete.php?id=' . $q->coursemodule()->id;

        if ($formdata->sec == 1) {
            $SESSION->questionnaire->end = false;
        }

        if (!empty($formdata->submit)) {
            if (isset($SESSION->questionnaire->end) && $SESSION->questionnaire->end == true) {
                return null;
            }
            $msg = $q->responses()->response_check_format($formdata->sec, $formdata);
            if (empty($msg)) {
                return null;
            }
            $formdata->rid = $q->existing_response_action($formdata, $userid);
        }

        if (!empty($formdata->resume) && ($q->resume())) {
            $q->responses()->response_delete($formdata->rid, $formdata->sec);
            $formdata->rid = $q->responses()->response_insert($formdata, $quser, true);
            $q->response_goto_saved($action);
            return null;
        }

        if (!empty($formdata->next)) {
            $msg = $q->responses()->response_check_format($formdata->sec, $formdata);
            if ($msg) {
                $formdata->next = '';
                $formdata->rid = $q->existing_response_action($formdata, $userid);
            } else {
                $nextsec = $q->next_page_action($formdata, $userid);
                if ($nextsec === false) {
                    $SESSION->questionnaire->end = true;
                    $formdata->sec = $numsections + 1;
                } else {
                    $formdata->sec = $nextsec;
                }
            }
        }

        if (!empty($formdata->prev)) {
            if (isset($SESSION->questionnaire->end) && ($SESSION->questionnaire->end == true)) {
                $SESSION->questionnaire->end = false;
                $formdata->sec--;
            }
            $msg = $q->responses()->response_check_format($formdata->sec, $formdata, false, true);
            if ($msg) {
                $formdata->prev = '';
                $formdata->rid = $q->existing_response_action($formdata, $userid);
            } else {
                $prevsec = $q->previous_page_action($formdata, $userid);
                if ($prevsec === false) {
                    $formdata->sec = 0;
                } else {
                    $formdata->sec = $prevsec;
                }
            }
        }

        if (!empty($formdata->rid)) {
            $q->add_response($formdata->rid);
        }

        $formdatareferer = !empty($formdata->referer) ? htmlspecialchars($formdata->referer) : '';
        $formdatarid = isset($formdata->rid) ? $formdata->rid : '0';
        $this->page->add_to_page(
            'formstart',
            $this->renderer->complete_formstart(
                $action,
                [
                    'referer' => $formdatareferer,
                    'a' => $q->id(),
                    'sid' => $q->surveyid(),
                    'rid' => $formdatarid,
                    'sec' => $formdata->sec,
                    'sesskey' => sesskey(),
                ]
            )
        );
        if ($q->questions() && $numsections) {
            $this->survey_render($q, $formdata, $formdata->sec, $msg);
            $controlbuttons = [];
            if ($formdata->sec > 1) {
                $controlbuttons['prev'] = [
                    'type' => 'submit',
                    'class' => 'btn btn-secondary control-button-prev',
                    'value' => '<< ' . get_string('previouspage', 'questionnaire'),
                ];
            }
            if ($q->resume()) {
                $controlbuttons['resume'] = [
                    'type' => 'submit',
                    'class' => 'btn btn-secondary control-button-save',
                    'value' => get_string('save_and_exit', 'questionnaire'),
                ];
            }
            if ($formdata->sec == $numsections) {
                $controlbuttons['submittype'] = ['type' => 'hidden', 'value' => 'Submit Survey'];
                $controlbuttons['submit'] = [
                    'type' => 'submit',
                    'class' => 'btn btn-primary control-button-submit',
                    'value' => get_string('submitsurvey', 'questionnaire'),
                ];
            } else {
                $controlbuttons['next'] = [
                    'type' => 'submit',
                    'class' => 'btn btn-secondary control-button-next',
                    'value' => get_string('nextpage', 'questionnaire') . ' >>',
                ];
            }
            $this->page->add_to_page('controlbuttons', $this->renderer->complete_controlbuttons($controlbuttons));
        } else {
            $this->page->add_to_page(
                'controlbuttons',
                $this->renderer->complete_controlbuttons(get_string('noneinuse', 'questionnaire'))
            );
        }
        $this->page->add_to_page('formend', $this->renderer->complete_formend());

        return $msg ?: null;
    }

    /**
     * Render the title, intro, group/respondent info, and print-blank link at the top of a survey page.
     *
     * @param questionnaire $q
     * @param string $message Error message to display above the form, or empty.
     * @param int $section Current section number (1-based).
     * @param int $numsections Total number of sections in the survey.
     * @param bool $hasrequired Whether any question in this section is required.
     * @param int|string $rid Response id when viewing a specific response, or '' / 0 in completion mode.
     * @param bool $blankquestionnaire True when rendering a blank questionnaire (print/preview).
     * @param string $outputtarget 'html' or 'pdf'.
     * @return void
     */
    public function print_survey_start(
        questionnaire $q,
        $message,
        $section,
        $numsections,
        $hasrequired,
        $rid = '',
        $blankquestionnaire = false,
        $outputtarget = 'html'
    ): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        $userid = '';
        $resp = '';
        $groupname = '';
        $currentgroupid = 0;
        $timesubmitted = '';
        if ($rid) {
            $courseid = $q->courseid();
            if ($resp = $DB->get_record('questionnaire_response', ['id' => $rid])) {
                if ($q->respondenttype() == 'fullname') {
                    $userid = $resp->userid;
                    if (groups_get_activity_groupmode($q->coursemodule(), $q->course())) {
                        if ($groups = groups_get_all_groups($courseid, $resp->userid)) {
                            if (count($groups) == 1) {
                                $group = current($groups);
                                $currentgroupid = $group->id;
                                $groupname = ' (' . get_string('group') . ': ' . $group->name . ')';
                            } else {
                                $groupname = ' (' . get_string('groups') . ': ';
                                foreach ($groups as $group) {
                                    $groupname .= $group->name . ', ';
                                }
                                $groupname = substr($groupname, 0, strlen($groupname) - 2) . ')';
                            }
                        } else {
                            $groupname = ' (' . get_string('groupnonmembers') . ')';
                        }
                    }

                    $params = [
                        'objectid' => $q->surveyid(),
                        'context' => $q->context(),
                        'courseid' => $q->courseid(),
                        'relateduserid' => $userid,
                        'other' => ['action' => 'vresp', 'currentgroupid' => $currentgroupid, 'rid' => $rid],
                    ];
                    $event = \mod_questionnaire\event\response_viewed::create($params);
                    $event->trigger();
                }
            }
        }
        $ruser = '';
        if ($resp && !$blankquestionnaire) {
            if ($userid) {
                if ($user = $DB->get_record('user', ['id' => $userid])) {
                    $ruser = fullname($user);
                }
            }
            if ($q->respondenttype() == 'anonymous') {
                $ruser = '- ' . get_string('anonymous', 'questionnaire') . ' -';
            } else {
                if ($resp->submitted) {
                    $timesubmitted = '&nbsp;' . get_string('submitted', 'questionnaire') .
                        '&nbsp;' . userdate($resp->submitted);
                }
            }
        }
        if ($ruser) {
            $respinfo = '';
            if ($outputtarget == 'html') {
                $linkname = get_string('print', 'mod_questionnaire');
                $link = new \moodle_url(
                    '/mod/questionnaire/report.php',
                    [
                        'action' => 'vresp',
                        'instance' => $q->id(),
                        'target' => 'print',
                        'individualresponse' => 1,
                        'rid' => $rid,
                    ]
                );
                $htmlicon = new \pix_icon('t/print', $linkname);
                $options = [
                    'menubar' => true,
                    'location' => false,
                    'scrollbars' => true,
                    'resizable' => true,
                    'height' => 600,
                    'width' => 800,
                    'title' => $linkname,
                ];
                $name = 'popup';
                $action = new \popup_action('click', $link, $name, $options);
                $respinfo .= $this->renderer->action_link(
                    $link,
                    null,
                    $action,
                    ['title' => $linkname],
                    $htmlicon
                ) . '&nbsp;';
            }
            $respinfo .= get_string('respondent', 'questionnaire') . ': <strong>' . $ruser . '</strong>';
            if ($q->survey_is_public()) {
                $coursename = '';
                $sql = 'SELECT q.id, q.course, c.fullname ' .
                       'FROM {questionnaire_response} qr ' .
                       'INNER JOIN {questionnaire} q ON qr.questionnaireid = q.id ' .
                       'INNER JOIN {course} c ON q.course = c.id ' .
                       'WHERE qr.id = ? AND qr.complete = ? ';
                if ($record = $DB->get_record_sql($sql, [$rid, 'y'])) {
                    $coursename = $record->fullname;
                }
                $respinfo .= ' ' . get_string('course') . ': ' . $coursename;
            }
            $respinfo .= $groupname;
            $respinfo .= $timesubmitted;
            $this->page->add_to_page('respondentinfo', $this->renderer->respondent_info($respinfo));
        }

        if ($q->capabilities()->can_print_blank() && $blankquestionnaire && $section == 1) {
            $linkname = '&nbsp;' . get_string('printblank', 'questionnaire');
            $title = get_string('printblanktooltip', 'questionnaire');
            $url = '/mod/questionnaire/print.php?qid=' . $q->id() . '&amp;rid=0&amp;' .
                'courseid=' . $q->courseid() . '&amp;sec=1';
            $options = [
                'menubar' => true,
                'location' => false,
                'scrollbars' => true,
                'resizable' => true,
                'height' => 600,
                'width' => 800,
                'title' => $title,
            ];
            $name = 'popup';
            $link = new \moodle_url($url);
            $action = new \popup_action('click', $link, $name, $options);
            $class = "floatprinticon";
            $this->page->add_to_page(
                'printblank',
                $this->renderer->action_link(
                    $link,
                    $linkname,
                    $action,
                    ['class' => $class, 'title' => $title],
                    new \pix_icon('t/print', $title)
                )
            );
        }
        if ($section == 1) {
            $title = $q->surveytitle();
            if (!empty($title)) {
                $this->page->add_to_page('title', format_string($title));
            }
            $subtitle = $q->surveysubtitle();
            if (!empty($subtitle)) {
                $this->page->add_to_page('subtitle', format_string($subtitle));
            }
            $info = $q->surveyinfo();
            if ($info) {
                $infotext = file_rewrite_pluginfile_urls(
                    $info,
                    'pluginfile.php',
                    $q->context()->id,
                    'mod_questionnaire',
                    'info',
                    $q->surveyid()
                );
                $this->page->add_to_page('addinfo', format_text($infotext, FORMAT_HTML, ['noclean' => true]));
            }
        }

        if ($message) {
            $this->page->add_to_page(
                'message',
                $this->renderer->notification($message, \core\output\notification::NOTIFY_ERROR)
            );
        }
    }

    /**
     * Render the page-number footer for paginated surveys.
     *
     * @param questionnaire $q
     * @param int $section
     * @param int $numsections
     * @return void
     */
    public function print_survey_end(questionnaire $q, $section, $numsections): void {
        if (!$q->pages_autonumbered()) {
            return;
        }
        if ($numsections > 1) {
            $a = new \stdClass();
            $a->page = $section;
            $a->totpages = $numsections;
            $this->page->add_to_page(
                'pageinfo',
                $this->renderer->container(
                    get_string('pageof', 'questionnaire', $a) . '&nbsp;&nbsp;',
                    'surveyPage'
                )
            );
        }
    }

    /**
     * Render the current section's questions for survey completion.
     *
     * @param questionnaire $q
     * @param \stdClass $formdata
     * @param int $section
     * @param string $message
     * @return bool|void
     */
    public function survey_render(questionnaire $q, &$formdata, $section = 1, $message = '') {
        if (empty($section)) {
            $section = 1;
        }
        $questionsbysec = $q->questions_by_section_all();
        $numsections = count($questionsbysec);
        if ($section > $numsections) {
            $formdata->sec = $numsections;
            $this->page->add_to_page(
                'notifications',
                $this->renderer->notification(get_string('finished', 'questionnaire'), \core\output\notification::NOTIFY_WARNING)
            );
            return false;
        }

        $hasrequired = $q->survey()->has_required($section);

        $i = 0;
        if ($section > 1) {
            for ($j = 2; $j <= $section; $j++) {
                foreach ($questionsbysec[$j - 1] as $question) {
                    if ($question->typeid() < QUESPAGEBREAK) {
                        $i++;
                    }
                }
            }
        }

        $this->print_survey_start($q, $message, $section, $numsections, $hasrequired, '', true);
        if ($q->use_progressbar() && count($questionsbysec) > 1) {
            $this->page->add_to_page(
                'progressbar',
                $this->renderer->render_progress_bar($section, $questionsbysec)
            );
        }
        if (key_exists($section, $questionsbysec)) {
            foreach ($questionsbysec[$section] as $question) {
                if ($question->is_numbered()) {
                    $i++;
                }
                $formdata->questionnaire_id = $q->id();
                if (isset($formdata->rid) && !empty($formdata->rid)) {
                    $q->add_response($formdata->rid);
                } else {
                    $q->responses()->add_response_from_formdata($formdata);
                }
                $this->page->add_to_page(
                    'questions',
                    $this->renderer->question_output(
                        $question,
                        ($q->responses()->get_response($formdata->rid) ?? []),
                        $i,
                        null,
                        [],
                        $q
                    )
                );
            }
        }

        $this->print_survey_end($q, $section, $numsections);
    }
}
