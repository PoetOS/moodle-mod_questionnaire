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

namespace mod_questionnaire\local\survey;

use mod_questionnaire\local\db\response_record;
use mod_questionnaire\output\survey_header;
use mod_questionnaire\questionnaire;
use mod_questionnaire\submission_notifier;

/**
 * Builds the student-facing survey-completion page.
 *
 * Takes the Moodle plugin renderer and a templatable page object, and fills
 * the page in place from data on the supplied questionnaire. Replaces the
 * page-filling responsibilities that used to live on the questionnaire class
 * itself (view(), print_survey(), and their private helpers). This is a
 * builder, not a renderer — it populates a templatable page via composition
 * rather than extending plugin_renderer_base.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class survey_view_builder {
    /**
     * Constructor.
     *
     * @param \plugin_renderer_base $renderer The plugin's Moodle renderer.
     * @param \mod_questionnaire\output\page $page The page that output is added to.
     */
    public function __construct(
        /** @var \plugin_renderer_base The plugin's Moodle renderer. */
        protected readonly \plugin_renderer_base $renderer,
        /** @var \mod_questionnaire\output\page The page that output is added to. */
        protected readonly \mod_questionnaire\output\page $page,
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
            // Never trust the posted response id; continue the user's own in-progress response.
            $viewform->rid = $q->get_latest_responseid($userid);
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
        global $CFG;

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

        $this->page->add_to_page('form', self::form_data($action, [
            'referer' => $formdata->referer ?? '',
            'a' => $q->id(),
            'sid' => $q->surveyid(),
            'rid' => $formdata->rid ?? 0,
            'sec' => $formdata->sec,
            'end' => !empty($formdata->end) ? 1 : 0,
            'sesskey' => sesskey(),
        ]));
        if ($q->questions() && $numsections) {
            $this->survey_render($q, $formdata, $formdata->sec, $msg);
            $this->page->add_to_page('controlbuttons', $this->control_buttons($q, $formdata->sec, $numsections));
        } else {
            $this->page->add_to_page('noquestions', get_string('noneinuse', 'questionnaire'));
        }

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
        if (!empty($formdata->end)) {
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
        $msg = $q->responses()->response_check_format($formdata->sec, $formdata);
        if ($msg) {
            $formdata->next = '';
            $formdata->rid = $q->submission()->existing_response_action($formdata, $userid);
            return $msg;
        }
        $nextsec = $q->submission()->next_page_action($formdata, $userid);
        if ($nextsec === false) {
            $formdata->end = 1;
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
        if (!empty($formdata->end)) {
            $formdata->end = 0;
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
     * Add the survey header (respondent line, title, print-blank link, subtitle, additional
     * information) and any error message to the top of a survey page.
     *
     * Viewing a specific response of a "fullname" questionnaire also logs a response_viewed event.
     *
     * @param questionnaire $q
     * @param string $message Error message to display above the form, or empty.
     * @param int $section Current section number (1-based); title and information show on section 1 only.
     * @param int|string $rid Response id when viewing a specific response, or '' / 0 in completion mode.
     * @param bool $blankquestionnaire True when rendering a blank questionnaire (print/preview).
     * @param string $outputtarget 'html' or 'pdf'.
     * @return void
     */
    public function print_survey_start(
        questionnaire $q,
        $message,
        $section,
        $rid = '',
        $blankquestionnaire = false,
        $outputtarget = 'html'
    ): void {
        $header = ($section == 1) ? survey_header::for_survey($q) : new survey_header();

        $resp = $rid ? response_record::get_or_null((int)$rid) : null;
        if ($resp) {
            $groups = $this->respondent_groups($q, $resp);
            if ($q->respondenttype() == 'fullname') {
                $this->log_response_viewed($q, $resp, $groups['currentgroupid']);
            }
            if (!$blankquestionnaire) {
                $this->add_respondent($header, $q, $resp, $groups['label'], $outputtarget);
            }
        }

        if ($q->capabilities()->can_print_blank() && $blankquestionnaire && $section == 1) {
            $header->set_print_blank(new \moodle_url('/mod/questionnaire/print.php', [
                'qid' => $q->id(),
                'rid' => 0,
                'courseid' => $q->courseid(),
                'sec' => 1,
            ]));
        }

        $this->page->add_to_page('surveyheader', $header->export_for_template($this->renderer));

        if ($message) {
            $this->page->add_to_page(
                'message',
                $this->renderer->notification($message, \core\output\notification::NOTIFY_ERROR)
            );
        }
    }

    /**
     * The respondent's group label and current group, for a "fullname" questionnaire in group mode.
     *
     * @param questionnaire $q
     * @param response_record $resp
     * @return array{label: ?string, currentgroupid: int}
     */
    private function respondent_groups(questionnaire $q, response_record $resp): array {
        $result = ['label' => null, 'currentgroupid' => 0];
        if ($q->respondenttype() != 'fullname' || !groups_get_activity_groupmode($q->coursemodule(), $q->course())) {
            return $result;
        }
        $groups = groups_get_all_groups($q->courseid(), (int)$resp->get('userid'));
        if (!$groups) {
            $result['label'] = get_string('groupnonmembers');
        } else if (count($groups) == 1) {
            $group = current($groups);
            $result['currentgroupid'] = (int)$group->id;
            $result['label'] = get_string('group') . ': ' . format_string($group->name, true, ['escape' => false]);
        } else {
            $names = array_map(fn($group) => format_string($group->name, true, ['escape' => false]), $groups);
            $result['label'] = get_string('groups') . ': ' . implode(', ', $names);
        }
        return $result;
    }

    /**
     * Log that a named respondent's response was viewed.
     *
     * @param questionnaire $q
     * @param response_record $resp
     * @param int $currentgroupid
     */
    private function log_response_viewed(questionnaire $q, response_record $resp, int $currentgroupid): void {
        \mod_questionnaire\event\response_viewed::create([
            'objectid' => $q->surveyid(),
            'context' => $q->context(),
            'courseid' => $q->courseid(),
            'relateduserid' => (int)$resp->get('userid'),
            'other' => ['action' => 'vresp', 'currentgroupid' => $currentgroupid, 'rid' => $resp->get('id')],
        ])->trigger();
    }

    /**
     * Put the respondent line on the header: name (or the anonymous marker), source course for
     * public surveys, group, submission time and, on HTML pages, a print-this-response icon.
     *
     * @param survey_header $header
     * @param questionnaire $q
     * @param response_record $resp
     * @param string|null $groups
     * @param string $outputtarget 'html' or 'pdf'.
     */
    private function add_respondent(
        survey_header $header,
        questionnaire $q,
        response_record $resp,
        ?string $groups,
        string $outputtarget
    ): void {
        $anonymous = $q->respondenttype() == 'anonymous';
        if ($anonymous) {
            $name = '- ' . get_string('anonymous', 'questionnaire') . ' -';
        } else if ($q->respondenttype() == 'fullname' && ($user = \core_user::get_user((int)$resp->get('userid')))) {
            $name = fullname($user);
        } else {
            return;
        }

        $rid = (int)$resp->get('id');
        $course = $q->survey_is_public() ? (response_record::get_course_fullname($rid) ?? '') : null;
        $submitted = $anonymous ? null : ((int)$resp->get('submitted') ?: null);
        $printurl = null;
        if ($outputtarget == 'html') {
            $printurl = new \moodle_url('/mod/questionnaire/report.php', [
                'action' => 'vresp',
                'instance' => $q->id(),
                'target' => 'print',
                'individualresponse' => 1,
                'rid' => $rid,
            ]);
        }
        $header->set_respondent($name, $course, $groups, $submitted, $printurl);
    }

    /**
     * The response form's action and hidden fields, for the page template.
     *
     * @param string $action
     * @param array $hidden Name => value.
     * @return array
     */
    private static function form_data(string $action, array $hidden): array {
        return [
            'action' => $action,
            'hidden' => array_map(
                fn($name, $value) => ['name' => $name, 'value' => $value],
                array_keys($hidden),
                array_values($hidden)
            ),
        ];
    }

    /**
     * The response form's buttons for this section: previous, save and exit, and next or submit.
     *
     * @param questionnaire $q
     * @param int $section
     * @param int $numsections
     * @return array[] Each with type, name, class and value.
     */
    private function control_buttons(questionnaire $q, int $section, int $numsections): array {
        $buttons = [];
        if ($section > 1) {
            $buttons[] = ['prev', 'btn btn-secondary control-button-prev', '<< ' . get_string('previouspage', 'questionnaire')];
        }
        if ($q->resume()) {
            $buttons[] = ['resume', 'btn btn-secondary control-button-save', get_string('save_and_exit', 'questionnaire')];
        }
        if ($section == $numsections) {
            $buttons[] = ['submit', 'btn btn-primary control-button-submit', get_string('submitsurvey', 'questionnaire')];
        } else {
            $buttons[] = ['next', 'btn btn-secondary control-button-next', get_string('nextpage', 'questionnaire') . ' >>'];
        }
        $buttons = array_map(fn($button) => [
            'type' => 'submit',
            'name' => $button[0],
            'class' => $button[1],
            'value' => $button[2],
        ], $buttons);
        if ($section == $numsections) {
            $buttons[] = ['type' => 'hidden', 'name' => 'submittype', 'class' => '', 'value' => 'Submit Survey'];
        }
        return $buttons;
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

        $this->print_survey_start($q, $message, $section, '', true);
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
        global $USER;

        $resumesurvey = get_string('resumesurvey', 'questionnaire');
        $savedprogress = get_string('savedprogress', 'questionnaire', '<strong>' . $resumesurvey . '</strong>');

        $this->page->add_to_page(
            'notifications',
            $this->renderer->notification($savedprogress, \core\output\notification::NOTIFY_SUCCESS)
        );
        $coursename = format_string($q->course()->fullname, true, ['context' => \context_course::instance($q->courseid())]);
        $this->page->add_to_page('links', [
            'url' => (new \moodle_url('/course/view.php', ['id' => $q->courseid()]))->out(false),
            'label' => get_string('backto', 'moodle', $coursename),
        ]);

        if (
            $q->resume()
            && $q->user_access_messages($USER->id, true) === null
            && $q->capabilities()->user_can_take($USER->id)
            && $q->questions()
            && $q->user_has_saved_response($USER->id)
        ) {
            $this->page->add_to_page('links', [
                'url' => (new \moodle_url('/mod/questionnaire/complete.php', [
                    'id' => $q->coursemodule()->id,
                    'resume' => 1,
                ]))->out(false),
                'label' => $resumesurvey,
            ]);
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

        // The survey record returns thankspage cleaned as PARAM_URL, so an invalid stored value comes back empty.
        $thankurl = $q->survey()->thankspage();
        $thankhead = $q->survey()->thankhead();
        $thankbody = $q->survey()->thankbody();

        if ($thankurl !== '') {
            // A string keeps relative URLs relative to this script, as before.
            redirect($thankurl);
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
        $header = new survey_header(
            format_string($thankhead),
            '',
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
        $this->page->add_to_page('surveyheader', $header->export_for_template($this->renderer));
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
