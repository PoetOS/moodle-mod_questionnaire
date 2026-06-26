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

namespace mod_questionnaire\local\report;

use mod_questionnaire\output\pdf_factory;
use mod_questionnaire\questionnaire;

/**
 * Controller for the staff-side view arms of report.php.
 *
 * Owns the two render arms:
 *  - vall (and the two sort variants vallasort/vallarsort): summary view of all responses.
 *  - vresp (and the default action): per-respondent view, including PDF/print branches.
 *
 * Each method handles its own tab/scope setup, group filter UI, response collection,
 * and the html / pdf rendering branches. Methods echo the page directly and return void
 * (matching the original action arms).
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_viewer {
    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The active questionnaire.
     * @param \plugin_renderer_base $renderer Plugin renderer.
     */
    public function __construct(
        /** @var questionnaire The active questionnaire. */
        private readonly questionnaire $questionnaire,
        /** @var \plugin_renderer_base Plugin renderer. */
        private readonly \plugin_renderer_base $renderer,
    ) {
    }

    /**
     * Render the "view all responses" page (vall / vallasort / vallarsort arms).
     *
     * @param object $page Templatable report page.
     * @param string $action One of 'vall', 'vallasort', 'vallarsort'.
     * @param string $outputtarget 'html', 'pdf', or 'print'.
     * @param \moodle_url $url Base URL for the current page (used by group select and action links).
     * @param int $groupmode Activity group mode (0 = none, 1 = separate, 2 = visible).
     * @param int $currentgroupid Active group filter id.
     * @param array $questionnairegroups Available groups (id => obj).
     * @param array $respsallparticipants All-participant responses (pre-fetched).
     * @param string $sort 'ascending', 'descending', or 'default'.
     * @param string $userview The 'responsestats' filter ('y', '0', or 'n').
     * @param array $responsestatus Map of response-status labels keyed by 'y' / '0' / 'n'.
     * @return void
     */
    public function view_all_responses(
        object $page,
        string $action,
        string $outputtarget,
        \moodle_url $url,
        int $groupmode,
        int $currentgroupid,
        array $questionnairegroups,
        array $respsallparticipants,
        string $sort,
        string $userview,
        array $responsestatus
    ): void {
        global $PAGE;

        $course = $this->questionnaire->course();
        $context = $this->questionnaire->context();
        $cm = $this->questionnaire->coursemodule();
        $instance = $this->questionnaire->id();

        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        $canviewallresponses = has_capability('mod/questionnaire:readallresponses', $context);
        $canviewallresponsesanytime = has_capability('mod/questionnaire:readallresponseanytime', $context);
        if (!$canviewallresponses && !$canviewallresponsesanytime) {
            echo $this->renderer->header();
            throw new \moodle_exception('nopermissions', 'mod_questionnaire');
        }

        $currenttab = match ($action) {
            'vallasort' => 'vallasort',
            'vallarsort' => 'vallarsort',
            default => 'valldefault',
        };
        if ($outputtarget != 'print') {
            (new \mod_questionnaire\output\tabs($this->questionnaire, $currenttab, $currentgroupid))->render($page);
        }

        $respinfo = '';
        $resps = [];
        // Enable choose_group if there are questionnaire groups and groupmode is not set to "no groups"
        // and if there are more groups than 1 (or if user can view all groups).
        if (is_array($questionnairegroups) && $groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            // Count number of responses in each group.
            foreach ($questionnairegroups as $group) {
                $respscount = $this->questionnaire->count_submissions(false, $group->id);
                $thisgroupname = groups_get_group_name($group->id);
                $escapedgroupname = preg_quote($thisgroupname, '/');
                if (!empty($respscount)) {
                    $groupselect = preg_replace(
                        '/\<option value="' . $group->id . '">' . $escapedgroupname . '<\/option>/',
                        '<option value="' . $group->id . '">' . $thisgroupname . ' (' . $respscount . ')</option>',
                        $groupselect
                    );
                } else {
                    $groupselect = preg_replace(
                        '/\<option value="' . $group->id . '">' . $escapedgroupname . '<\/option>/',
                        '',
                        $groupselect
                    );
                }
            }
            $respinfo .= isset($groupselect) ? ($groupselect . ' ') : '';
            $currentgroupid = groups_get_activity_group($cm);
        }
        if ($currentgroupid > 0) {
            $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
        } else {
            $groupname = '<strong>' . $responsestatus[$userview] . '</strong>';
        }

        if ($groupmode > 0) {
            if ($currentgroupid == 0) {
                $resps = $respsallparticipants;
            } else {
                if (!($resps = $this->questionnaire->get_responses(false, $currentgroupid))) {
                    $resps = '';
                }
            }
        } else {
            $resps = $respsallparticipants;
        }
        if (!empty($resps)) {
            $feedbackmessages = $this->questionnaire->reporter($this->renderer, $page)
                ->response_analysis(0, $resps, false, false, true, $currentgroupid);
            if ($feedbackmessages) {
                $msgout = '';
                foreach ($feedbackmessages as $msg) {
                    $msgout .= $msg;
                }
                $page->add_to_page('feedbackmessages', $msgout);
            }
        }

        $params = [
            'objectid' => $instance,
            'context' => $context,
            'courseid' => $course->id,
            'other' => ['action' => $action, 'instance' => $instance, 'groupid' => $currentgroupid],
        ];

        if ($outputtarget == 'pdf') {
            $pdf = pdf_factory::create();
            if ($currentgroupid > 0) {
                $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
            } else {
                $groupname = '<strong>' . $responsestatus[$userview] . '</strong>';
            }
            $respinfo = get_string('view') . ' ' . $groupname;
            $strsort = get_string('order_' . $sort, 'questionnaire');
            $respinfo .= $strsort;
            $page->add_to_page('respondentinfo', $respinfo);
            $this->questionnaire->reporter($this->renderer, $page)
                ->survey_results('', false, true, $currentgroupid, $sort);
            $html = $this->renderer->render($page);

            // Suppress warnings: TCPF library bug at line 16749 where 'text-align' is not an array.
            $errorreporting = error_reporting(0);
            $pdf->writeHTML($html);
            @$pdf->Output(clean_param($this->questionnaire->name(), PARAM_FILE) . '.pdf', 'D');
            error_reporting($errorreporting);
            return;
        }

        // Default to HTML.
        $event = \mod_questionnaire\event\all_responses_viewed::create($params);
        $event->trigger();

        if ($outputtarget != 'print') {
            $linkname = get_string('downloadpdf', 'mod_questionnaire');
            $link = new \moodle_url(
                '/mod/questionnaire/report.php',
                [
                    'action' => 'vall',
                    'instance' => $instance,
                    'group' => $currentgroupid,
                    'target' => 'pdf',
                    'responsestats' => $userview,
                ]
            );
            $downpdficon = new \pix_icon('f/pdf', $linkname);
            $respinfo .= $this->renderer->action_link($link, null, null, null, $downpdficon);

            $linkname = get_string('print', 'mod_questionnaire');
            $link = new \moodle_url(
                '/mod/questionnaire/report.php',
                [
                    'action' => 'vall',
                    'instance' => $instance,
                    'group' => $currentgroupid,
                    'target' => 'print',
                    'responsestats' => $userview,
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
            $popupaction = new \popup_action('click', $link, 'popup', $options);
            $respinfo .= $this->renderer->action_link(
                $link,
                null,
                $popupaction,
                ['class' => '', 'title' => $linkname],
                $htmlicon
            ) . '&nbsp;';

            $respinfo .= $this->renderer->viewresponse_print_menu($url->out(), $responsestatus, $userview);
            $strsort = get_string('order_' . $sort, 'questionnaire');
            $respinfo .= $strsort;
            $respinfo .= $this->renderer->help_icon('orderresponses', 'questionnaire');
            $page->add_to_page('respondentinfo', $respinfo);
        }

        $this->questionnaire->reporter($this->renderer, $page)
            ->survey_results('', false, false, $currentgroupid, $sort);

        echo $this->renderer->header();
        echo $this->renderer->render($page);
        echo $this->renderer->footer($course);
    }

    /**
     * Render the "view individual response" page (vresp / default arm).
     *
     * @param object $page Templatable report page.
     * @param string $outputtarget 'html' or 'pdf'.
     * @param \moodle_url $url Base URL for the current page.
     * @param int $groupmode Activity group mode.
     * @param int $currentgroupid Active group filter id.
     * @param array $respsallparticipants All-participant responses (pre-fetched).
     * @param int|false $rid Active response id, or false when not specified.
     * @param bool $byresponse True if browsing the response summary tab.
     * @param bool $individualresponse True if viewing a single response.
     * @param string $userview The 'responsestats' filter.
     * @param array $responsestatus Map of response-status labels.
     * @return void
     */
    public function view_individual_response(
        object $page,
        string $outputtarget,
        \moodle_url $url,
        int $groupmode,
        int $currentgroupid,
        array $respsallparticipants,
        $rid,
        bool $byresponse,
        bool $individualresponse,
        string $userview,
        array $responsestatus
    ): void {
        global $PAGE;

        $course = $this->questionnaire->course();
        $cm = $this->questionnaire->coursemodule();

        if (!$this->questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        } else if ($this->questionnaire->survey()->owning_courseid() != $course->id) {
            throw new \moodle_exception('surveyowner', 'mod_questionnaire');
        }
        $noresponses = false;

        if ($groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            $page->add_to_page('respondentinfo', $groupselect);
            $currentgroupid = groups_get_activity_group($cm);
        }

        $resps = [];
        if ($byresponse || $rid) {
            if ($groupmode > 0) {
                if ($currentgroupid == 0) {
                    $resps = $respsallparticipants;
                } else {
                    $resps = $this->questionnaire->get_responses(false, $currentgroupid);
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else if ($rid === false) {
                    $rid = current($resps)->id;
                }
            } else {
                $resps = $respsallparticipants;
            }
        }
        $rids = array_keys($resps);
        if (!$rid && !$noresponses) {
            $rid = $rids[0];
        }

        if ($outputtarget == 'pdf') {
            $pdf = pdf_factory::create();
            if (!$byresponse) {
                $this->questionnaire->reporter($this->renderer, $page)
                    ->view_response($rid, '', $resps, true, true, false, $currentgroupid, $outputtarget);
            }
            $html = $this->renderer->render($page);

            // Suppress warnings: TCPF library bug at line 16749 where 'text-align' is not an array.
            $errorreporting = error_reporting(0);
            $pdf->writeHTML($html);
            @$pdf->Output(clean_param($this->questionnaire->name(), PARAM_FILE), 'D');
            error_reporting($errorreporting);
            return;
        }

        // Default to HTML.
        if ($noresponses) {
            $page->add_to_page(
                'respondentinfo',
                get_string('group') . ' <strong>' .
                    groups_get_group_name($currentgroupid) . '</strong>: ' . get_string('noresponses', 'questionnaire')
            );
        }

        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));

        $currenttab = $individualresponse ? 'individualresp' : ($byresponse ? 'vrespsummary' : 'vresp');
        if ($outputtarget == 'html') {
            (new \mod_questionnaire\output\tabs(
                $this->questionnaire,
                $currenttab,
                $currentgroupid,
                is_int($rid) ? $rid : null
            ))->render($page);
        }

        $groupname = get_string('group') . ': <strong>' . groups_get_group_name($currentgroupid) . '</strong>';
        if ($currentgroupid == 0) {
            $groupname = $responsestatus[$userview];
        }
        if ($byresponse) {
            $respinfo = '';
            $respinfo .= $this->renderer->box_start();
            $respinfo .= $this->renderer->help_icon('viewindividualresponse', 'questionnaire') . '&nbsp;';
            $respinfo .= get_string('viewindividualresponse', 'questionnaire') . ' <strong> : ' . $groupname . '</strong>';
            $respinfo .= $this->renderer->box_end();
            $page->add_to_page('respondentinfo', $respinfo);
        }
        if ($outputtarget == 'html') {
            $this->questionnaire->reporter($this->renderer, $page)
                ->survey_results_navbar_alpha($rid, $currentgroupid, $byresponse);
        }
        if (!$byresponse) {
            $this->questionnaire->reporter($this->renderer, $page)
                ->view_response($rid, '', $resps, true, true, false, $currentgroupid, $outputtarget);
        }
        echo $this->renderer->header();
        echo $this->renderer->render($page);
        echo $this->renderer->footer($course);
    }
}
