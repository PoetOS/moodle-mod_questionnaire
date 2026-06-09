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
            $rid = $q->submission()->existing_response_action($viewform, $userid);
            $q->responses()->commit_submission_response($rid, $userid);
            (new submission_notifier($q))->notify($rid);
            $this->goto_thankyou($q);
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
            $submitresult = $this->handle_submit_action($q, $formdata, $userid);
            if ($submitresult === null) {
                return null;
            }
            $msg = $submitresult;
        }

        if (!empty($formdata->resume) && ($q->resume())) {
            $this->handle_resume_action($q, $formdata, $quser);
            return null;
        }

        if (!empty($formdata->next)) {
            $msg = $this->handle_next_action($q, $formdata, $userid, $numsections);
        }

        if (!empty($formdata->prev)) {
            $msg = $this->handle_prev_action($q, $formdata, $userid);
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
     * Process the "Submit Survey" button press.
     *
     * Returns null when the caller should short-circuit (already past the end,
     * or no validation errors so the response is now final). Returns a non-empty
     * error string when validation failed; in that case formdata->rid is also
     * updated to the in-progress response id so the form re-renders with errors.
     *
     * @param questionnaire $q
     * @param \stdClass $formdata
     * @param int|null $userid Owner of the response (anonymous = null).
     * @return string|null null = short-circuit, string = error message to render.
     */
    protected function handle_submit_action(questionnaire $q, \stdClass $formdata, ?int $userid): ?string {
        global $SESSION;

        if (isset($SESSION->questionnaire->end) && $SESSION->questionnaire->end == true) {
            return null;
        }
        $msg = $q->responses()->response_check_format($formdata->sec, $formdata);
        if (empty($msg)) {
            return null;
        }
        $formdata->rid = $q->submission()->existing_response_action($formdata, $userid);
        return $msg;
    }

    /**
     * Process the "Save and exit" button press: persist the in-progress response and show the saved page.
     *
     * Caller must return null after this; the saved page replaces the form.
     *
     * @param questionnaire $q
     * @param \stdClass $formdata
     * @param int $quser User id the response belongs to.
     * @return void
     */
    protected function handle_resume_action(questionnaire $q, \stdClass $formdata, int $quser): void {
        $q->responses()->response_delete($formdata->rid, $formdata->sec);
        $formdata->rid = $q->responses()->response_insert($formdata, $quser, true);
        $this->goto_saved($q);
    }

    /**
     * Process the "Next page" button press.
     *
     * Returns an empty string on success (formdata->sec is advanced, possibly to numsections+1 if
     * this was the final page). On validation failure returns the error message and re-stages the
     * current section by clearing formdata->next and refreshing formdata->rid.
     *
     * @param questionnaire $q
     * @param \stdClass $formdata
     * @param int|null $userid
     * @param int $numsections Total section count, used to compute the post-last-page sec.
     * @return string Error message ('' on success).
     */
    protected function handle_next_action(
        questionnaire $q,
        \stdClass $formdata,
        ?int $userid,
        int $numsections
    ): string {
        global $SESSION;

        $msg = $q->responses()->response_check_format($formdata->sec, $formdata);
        if ($msg) {
            $formdata->next = '';
            $formdata->rid = $q->submission()->existing_response_action($formdata, $userid);
            return $msg;
        }
        $nextsec = $q->submission()->next_page_action($formdata, $userid);
        if ($nextsec === false) {
            $SESSION->questionnaire->end = true;
            $formdata->sec = $numsections + 1;
        } else {
            $formdata->sec = $nextsec;
        }
        return '';
    }

    /**
     * Process the "Previous page" button press.
     *
     * If the user just returned from the (virtual) past-end summary page, walk back one section
     * before validating. Returns an empty string on success (formdata->sec is decremented, possibly
     * to 0 if this was the first page). On validation failure returns the error message and
     * re-stages the current section by clearing formdata->prev and refreshing formdata->rid.
     *
     * @param questionnaire $q
     * @param \stdClass $formdata
     * @param int|null $userid
     * @return string Error message ('' on success).
     */
    protected function handle_prev_action(questionnaire $q, \stdClass $formdata, ?int $userid): string {
        global $SESSION;

        if (isset($SESSION->questionnaire->end) && ($SESSION->questionnaire->end == true)) {
            $SESSION->questionnaire->end = false;
            $formdata->sec--;
        }
        $msg = $q->responses()->response_check_format($formdata->sec, $formdata, false, true);
        if ($msg) {
            $formdata->prev = '';
            $formdata->rid = $q->submission()->existing_response_action($formdata, $userid);
            return $msg;
        }
        $prevsec = $q->submission()->previous_page_action($formdata, $userid);
        if ($prevsec === false) {
            $formdata->sec = 0;
        } else {
            $formdata->sec = $prevsec;
        }
        return '';
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
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $userid = 0;
        $resp = null;
        $groupname = '';
        $currentgroupid = 0;
        $timesubmitted = '';
        if ($rid) {
            $courseid = $q->courseid();
            if ($resp = \mod_questionnaire\local\db\response_record::get_or_null((int)$rid)) {
                if ($q->respondenttype() == 'fullname') {
                    $userid = (int)$resp->get('userid');
                    if (groups_get_activity_groupmode($q->coursemodule(), $q->course())) {
                        if ($groups = groups_get_all_groups($courseid, $userid)) {
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
                if ($user = \core_user::get_user($userid)) {
                    $ruser = fullname($user);
                }
            }
            if ($q->respondenttype() == 'anonymous') {
                $ruser = '- ' . get_string('anonymous', 'questionnaire') . ' -';
            } else {
                $submitted = (int)$resp->get('submitted');
                if ($submitted) {
                    $timesubmitted = '&nbsp;' . get_string('submitted', 'questionnaire') .
                        '&nbsp;' . userdate($submitted);
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
                $coursename = \mod_questionnaire\local\db\response_record::get_course_fullname((int)$rid) ?? '';
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

    /**
     * Fill the page with the save-progress confirmation, including a resume link when applicable.
     *
     * @param questionnaire $q
     * @return void
     */
    public function goto_saved(questionnaire $q): void {
        global $CFG, $USER;

        $resumesurvey = get_string('resumesurvey', 'questionnaire');
        $savedprogress = get_string('savedprogress', 'questionnaire', '<strong>' . $resumesurvey . '</strong>');

        $this->page->add_to_page(
            'notifications',
            $this->renderer->notification($savedprogress, \core\output\notification::NOTIFY_SUCCESS)
        );
        $this->page->add_to_page(
            'respondentinfo',
            $this->renderer->homelink(
                $CFG->wwwroot . '/course/view.php?id=' . $q->courseid(),
                get_string('backto', 'moodle', $q->course()->fullname)
            )
        );

        if (
            $q->resume()
            && $q->user_access_messages($USER->id, true) === null
            && $q->capabilities()->user_can_take($USER->id)
            && $q->questions()
            && $q->user_has_saved_response($USER->id)
        ) {
            $this->page->add_to_page(
                'respondentinfo',
                $this->renderer->homelink(
                    $CFG->wwwroot . '/mod/questionnaire/complete.php?' .
                        'id=' . $q->coursemodule()->id . '&resume=1',
                    $resumesurvey
                )
            );
        }
    }

    /**
     * Render or redirect to the thank-you screen after a submission.
     *
     * @param questionnaire $q
     * @return void
     */
    public function goto_thankyou(questionnaire $q): void {
        global $USER;

        $thankurl = $q->survey()->thankspage();
        $thankhead = $q->survey()->thankhead();
        $thankbody = $q->survey()->thankbody();

        if (!empty($thankurl)) {
            if (!headers_sent()) {
                header("Location: $thankurl");
                exit;
            }
            echo '
                <script language="JavaScript" type="text/javascript">
                <!--
                window.location="' . $thankurl . '"
                //-->
                </script>
                <noscript>
                <h2 class="thankhead">Thank You for completing this survey.</h2>
                <blockquote class="thankbody">Please click
                <a href="' . $thankurl . '">here</a> to continue.</blockquote>
                </noscript>
            ';
            exit;
        }
        if (empty($thankhead)) {
            $thankhead = get_string('thank_head', 'questionnaire');
        }
        $questionsbysec = $q->questions_by_section_all();
        if ($q->use_progressbar() && count($questionsbysec) > 1) {
            $this->page->add_to_page(
                'progressbar',
                $this->renderer->render_progress_bar(count($questionsbysec) + 1, $questionsbysec)
            );
        }
        $this->page->add_to_page('title', format_string($thankhead));
        $this->page->add_to_page(
            'addinfo',
            format_text(
                file_rewrite_pluginfile_urls(
                    $thankbody,
                    'pluginfile.php',
                    $q->context()->id,
                    'mod_questionnaire',
                    'thankbody',
                    $q->surveyid()
                ),
                FORMAT_HTML,
                ['noclean' => true]
            )
        );
        if ($q->capabilities()->can_read_own_responses()) {
            $url = new \moodle_url(
                'myreport.php',
                [
                    'id' => $q->coursemodule()->id,
                    'instance' => $q->coursemodule()->instance,
                    'user' => $USER->id,
                    'byresponse' => 0,
                    'action' => 'vresp',
                ]
            );
            $this->page->add_to_page('continue', $this->renderer->single_button($url, get_string('continue')));
        } else {
            $url = new \moodle_url('/course/view.php', ['id' => $q->courseid()]);
            $this->page->add_to_page('continue', $this->renderer->single_button($url, get_string('continue')));
        }
    }
}
