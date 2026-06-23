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

use mod_questionnaire\questionnaire;

/**
 * Controller for the staff-side download arms of report.php.
 *
 * Owns the two CSV / dataformat arms:
 *  - dwnpg: render the "download options" page (format selector + email options).
 *  - dfs:   execute the download (stream it, email it, or redirect back with an error).
 *
 * Both methods end with exit() to match the original action-arm behaviour
 * (so headers sent by dataformat::download_data are not followed by more
 * output). Under PHPUnit, redirect() throws a moodle_exception, which is
 * what the tests catch.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_downloader {
    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The active questionnaire.
     * @param \plugin_renderer_base $renderer Plugin renderer used for the options page.
     */
    public function __construct(
        /** @var questionnaire The active questionnaire. */
        private readonly questionnaire $questionnaire,
        /** @var \plugin_renderer_base Plugin renderer for the options page. */
        private readonly \plugin_renderer_base $renderer,
    ) {
    }

    /**
     * Render the "download options" page (dwnpg action arm).
     *
     * Side effects: sets $PAGE title/heading, echoes header / page / footer,
     * triggers all_responses_saved_as_text, and exit()s.
     *
     * @param object $page Templatable report page.
     * @param int $groupmode Activity groupmode (0 = none, 1 = separate, 2 = visible).
     * @param int $currentgroupid Active group filter.
     * @param array $questionnairegroups Available groups (id => obj) — used to label the selected group.
     * @param int $user User filter applied to the download (0 = all users).
     * @return void
     */
    public function show_download_options(
        object $page,
        int $groupmode,
        int $currentgroupid,
        array $questionnairegroups,
        int $user
    ): void {
        global $PAGE;

        require_capability('mod/questionnaire:downloadresponses', $this->questionnaire->context());

        $course = $this->questionnaire->course();
        $instance = $this->questionnaire->id();

        $PAGE->set_title(get_string('questionnairereport', 'questionnaire'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $this->renderer->header();

        $tabname = empty($user) ? 'downloadcsv' : 'mydownloadcsv';
        (new \mod_questionnaire\output\tabs($this->questionnaire, $tabname, $currentgroupid))->render($page);

        $groupname = '';
        if ($groupmode > 0) {
            if ($currentgroupid == 0) {
                $groupname = get_string('allparticipants');
            } else {
                $groupname = get_string('membersofselectedgroup', 'group') . ' ' .
                    get_string('group') . ' ' . $questionnairegroups[$currentgroupid]->name;
            }
        }

        $output = '';
        $output .= "<br /><br />\n";
        $output .= \html_writer::tag('h2', get_string('downloadtextformat', 'questionnaire')
            . ':&nbsp;' . get_string('responses', 'questionnaire') . '&nbsp;' .
            $groupname . $this->renderer->help_icon('downloadtextformat', 'questionnaire'));
        $output .= $this->renderer->heading(get_string('textdownloadoptions', 'questionnaire'), 3);
        $output .= $this->renderer->box_start();
        $downloadparams = [
            'instance' => $instance,
            'user' => $user,
            'sid' => $this->questionnaire->surveyid(),
            'action' => 'dfs',
            'group' => $currentgroupid,
        ];
        $extrafields = $this->renderer->render_from_template('mod_questionnaire/extrafields', []);
        $output .= $this->renderer->download_dataformat_selector(
            get_string('downloadtypes', 'questionnaire'),
            'report.php',
            'downloadformat',
            $downloadparams,
            $extrafields
        );
        $output .= $this->renderer->box_end();

        $page->add_to_page('respondentinfo', $output);
        echo $this->renderer->render($page);

        echo $this->renderer->footer('none');

        $event = \mod_questionnaire\event\all_responses_saved_as_text::create([
            'objectid' => $instance,
            'context' => $this->questionnaire->context(),
            'courseid' => $this->questionnaire->courseid(),
            'other' => ['action' => 'dwnpg', 'instance' => $instance, 'currentgroupid' => $currentgroupid],
        ]);
        $event->trigger();

        exit();
    }

    /**
     * Generate and deliver a dataformat export (dfs action arm).
     *
     * Reads dataformat-related parameters directly from the request, since they
     * are tightly coupled to the download form posted from show_download_options().
     *
     * Three terminal paths:
     *  - Streams the file via core\dataformat::download_data (then exit()).
     *  - Emails the file via save_as_dataformat (then exit()).
     *  - Redirects back to the options page with an error if email was selected
     *    but no recipients were specified.
     *
     * @param object $page Templatable report page (the reporter needs it).
     * @param int $currentgroupid Active group filter (echoed in redirect URLs).
     * @param int $user User filter (passed through to generate_csv).
     * @return void
     */
    public function download_responses(object $page, int $currentgroupid, int $user): void {
        global $CFG, $USER;

        require_capability('mod/questionnaire:downloadresponses', $this->questionnaire->context());

        // Use the questionnaire name as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($this->questionnaire->name(), PARAM_FILE);
        $name = preg_replace('/[^A-Z0-9]+/i', '_', trim($name));

        $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
        $choicetext = optional_param('choicetext', '0', PARAM_INT);
        $showincompletes = optional_param('complete', '0', PARAM_INT);
        $rankaverages = optional_param('rankaverages', '0', PARAM_INT);
        $dataformat = optional_param('downloadformat', '', PARAM_ALPHA);
        $emailroles = optional_param('emailroles', 0, PARAM_INT);
        $emailextra = optional_param('emailextra', '', PARAM_RAW);

        $output = $this->questionnaire->reporter($this->renderer, $page)->generate_csv(
            $currentgroupid,
            '',
            $user,
            $choicecodes,
            $choicetext,
            $showincompletes,
            $rankaverages
        );

        $columns = $output[0];
        unset($output[0]);

        $instance = $this->questionnaire->id();
        $emailreport = optional_param('emailreport', '', PARAM_ALPHA);
        if (empty($emailreport)) {
            \core\dataformat::download_data($name, $dataformat, $columns, $output);
            exit();
        }

        if (get_config('questionnaire', 'allowemailreporting') && (!empty($emailroles) || !empty($emailextra))) {
            require_once($CFG->dirroot . '/mod/questionnaire/savefileformat.php');
            $users = !empty($emailroles)
                ? (new submission_notifier($this->questionnaire))->get_notifiable_users($USER->id)
                : [];
            $otheremails = explode(',', $emailextra);
            if (!empty($users) || !empty($otheremails)) {
                $thisurl = new \moodle_url(
                    'report.php',
                    ['instance' => $instance, 'action' => 'dwnpg', 'group' => $currentgroupid]
                );
                save_as_dataformat($name, $dataformat, $columns, $output, $users, $otheremails, $thisurl);
            }
            exit();
        }

        redirect(
            new \moodle_url(
                'report.php',
                ['instance' => $instance, 'action' => 'dwnpg', 'group' => $currentgroupid]
            ),
            get_string('emailsnotspecified', 'questionnaire')
        );
    }
}
