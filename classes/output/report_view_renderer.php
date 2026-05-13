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

/**
 * Builds the printable and preview survey views.
 *
 * Companion to {@see survey_view_renderer} for the read-only paths: the
 * print and preview pages (build_print_view) and individual response views
 * (render_response). Re-uses survey_view_renderer for the shared
 * title/respondent header.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class report_view_renderer {
    /**
     * Constructor.
     *
     * @param \plugin_renderer_base $renderer The plugin's Moodle renderer.
     * @param object $page A templatable page object (previewpage, reportpage, etc.).
     */
    public function __construct(
        /** @var \plugin_renderer_base The plugin's Moodle renderer. */
        protected readonly \plugin_renderer_base $renderer,
        /** @var object A templatable page object (previewpage, reportpage, etc.). */
        protected readonly object $page,
    ) {
    }

    /**
     * Build the print / preview page for the survey.
     *
     * Delegates to render_response when a specific response id is supplied;
     * otherwise renders every question in section order with optional
     * preview-mode dependency hints and submit form.
     *
     * @param questionnaire $q
     * @param int $courseid Unused legacy parameter retained for caller compatibility.
     * @param string $message Error message displayed at the top of the form.
     * @param string $referer 'preview', 'print', or empty.
     * @param int $rid Response id to render, or 0 for blank/all.
     * @param bool $blankquestionnaire True when rendering a blank questionnaire.
     * @return false|void
     */
    public function build_print_view(
        questionnaire $q,
        $courseid,
        $message = '',
        $referer = '',
        $rid = 0,
        $blankquestionnaire = false
    ) {
        global $CFG;

        if (!empty($rid)) {
            $this->render_response($q, $rid, $referer);
            return;
        }

        $section = 1;
        $questionsbysec = $q->questions_by_section_all();
        $numsections = count($questionsbysec);

        if ($section > $numsections) {
            return false;
        }

        $hasrequired = $q->survey()->has_required();

        $i = 1;
        for ($j = 2; $j <= $section; $j++) {
            $i += count($questionsbysec[$j - 1]);
        }

        $action = $CFG->wwwroot . '/mod/questionnaire/preview.php?' .
            ($q->coursemodule() ? 'id=' . $q->coursemodule()->id : 'sid=' . $q->surveyid());
        $this->page->add_to_page('formstart', $this->renderer->complete_formstart($action));

        $formdata = new \stdClass();
        $errors = 1;
        if (data_submitted()) {
            $formdata = data_submitted();
            $formdata->rid = $formdata->rid ?? 0;
            $q->responses()->add_response_from_formdata($formdata);
            $pageerror = '';
            $s = 1;
            $errors = 0;
            foreach ($questionsbysec as $sec) {
                $errormessage = $q->responses()->response_check_format($s, $formdata);
                if ($errormessage) {
                    if ($numsections > 1) {
                        $pageerror = get_string('page', 'questionnaire') . ' ' . $s . ' : ';
                    }
                    $this->page->add_to_page(
                        'notifications',
                        $this->renderer->notification(
                            $pageerror . $errormessage,
                            \core\output\notification::NOTIFY_ERROR
                        )
                    );
                    $errors++;
                }
                $s++;
            }
        }

        $this->build_header($q, $message, 1, 1, $hasrequired, '');

        if (($referer == 'preview') && $q->has_dependencies()) {
            $allqdependants = $q->navigator()->get_dependants_and_choices();
        } else {
            $allqdependants = [];
        }
        if ($errors == 0) {
            $this->page->add_to_page(
                'message',
                $this->renderer->notification(
                    get_string('submitpreviewcorrect', 'questionnaire'),
                    \core\output\notification::NOTIFY_SUCCESS
                )
            );
        }

        $page = 1;
        foreach ($questionsbysec as $sec) {
            $output = '';
            if ($numsections > 1) {
                $output .= $this->renderer->print_preview_pagenumber(
                    get_string('page', 'questionnaire') . ' ' . $page
                );
                $page++;
            }
            foreach ($sec as $question) {
                if (!$question->is_numbered()) {
                    $i--;
                }
                $dependants = $allqdependants[$question->id()] ?? [];
                $question->set_isprint($referer === 'print');
                $output .= $this->renderer->question_output(
                    $question,
                    ($q->responses()->get_response(0) ?? new \mod_questionnaire\local\response\response()),
                    $i++,
                    null,
                    $dependants,
                    $q
                );
                $this->page->add_to_page('questions', $output);
                $output = '';
            }
        }

        if ($referer == 'preview' && !$blankquestionnaire) {
            $url = $CFG->wwwroot . '/mod/questionnaire/preview.php?' .
                ($q->coursemodule() ? 'id=' . $q->coursemodule()->id : 'sid=' . $q->surveyid());
            $this->page->add_to_page(
                'formend',
                $this->renderer->print_preview_formend(
                    $url,
                    get_string('submitpreview', 'questionnaire'),
                    get_string('reset')
                )
            );
        }
    }

    /**
     * Render a single response for viewing (report, print, or preview context).
     *
     * @param questionnaire $q
     * @param int $rid
     * @param string $referer 'print' suppresses feedback messages.
     * @param mixed $resps
     * @param bool $compare
     * @param bool $isgroupmember
     * @param bool $allresponses
     * @param int $currentgroupid
     * @param string $outputtarget 'html' or 'pdf'.
     * @return void
     */
    public function render_response(
        questionnaire $q,
        $rid,
        $referer = '',
        $resps = '',
        $compare = false,
        $isgroupmember = false,
        $allresponses = false,
        $currentgroupid = 0,
        $outputtarget = 'html'
    ): void {
        $this->build_header($q, '', 1, 1, 0, $rid, false, $outputtarget);

        $i = 0;
        $q->add_response($rid);
        if ($referer != 'print') {
            $feedbackmessages = $q->reporter()->response_analysis(
                $rid,
                $resps,
                $compare,
                $isgroupmember,
                $allresponses,
                $currentgroupid
            );
            if ($feedbackmessages) {
                $msgout = '';
                foreach ($feedbackmessages as $msg) {
                    $msgout .= $msg;
                }
                $this->page->add_to_page('feedbackmessages', $msgout);
            }

            $feedbacknotes = $q->survey()->feedbacknotes();
            if ($feedbacknotes) {
                $text = file_rewrite_pluginfile_urls(
                    $feedbacknotes,
                    'pluginfile.php',
                    $q->context()->id,
                    'mod_questionnaire',
                    'feedbacknotes',
                    $q->surveyid()
                );
                $this->page->add_to_page('feedbacknotes', $this->renderer->box(format_text($text, FORMAT_HTML)));
            }
        }
        $pdf = ($outputtarget == 'pdf');
        foreach ($q->questions() as $question) {
            if (!$question->dependency_fulfilled($rid, $q->questions())) {
                continue;
            }
            if ($question->typeid() < QUESPAGEBREAK) {
                $i++;
            }
            if ($question->typeid() != QUESPAGEBREAK) {
                $this->page->add_to_page(
                    'responses',
                    $this->renderer->response_output(
                        $question,
                        $q->responses()->get_response($rid),
                        $i,
                        $pdf,
                        $q
                    )
                );
            }
        }
    }

    /**
     * Delegate the title / respondent / print-blank header to survey_view_renderer so
     * the two render paths share a single implementation.
     *
     * @param questionnaire $q
     * @param string $message
     * @param int $section
     * @param int $numsections
     * @param bool $hasrequired
     * @param int|string $rid
     * @param bool $blankquestionnaire
     * @param string $outputtarget
     */
    private function build_header(
        questionnaire $q,
        $message,
        $section,
        $numsections,
        $hasrequired,
        $rid = '',
        $blankquestionnaire = false,
        $outputtarget = 'html'
    ): void {
        (new survey_view_renderer($this->renderer, $this->page))
            ->print_survey_start($q, $message, $section, $numsections, $hasrequired, $rid, $blankquestionnaire, $outputtarget);
    }
}
