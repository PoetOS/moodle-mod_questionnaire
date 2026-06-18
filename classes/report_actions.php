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

namespace mod_questionnaire;

use mod_questionnaire\local\db\response_record;

/**
 * Controller for staff-side report actions on report.php.
 *
 * Each method owns one of the four deletion-related action arms:
 * confirm-delete-single, delete-single, confirm-delete-all, delete-all.
 *
 * Confirm methods render their own confirmation page (capability check,
 * tabs, confirm dialog) and return; delete methods either redirect on
 * success or throw a plugin moodle_exception on failure.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_actions {
    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The active questionnaire.
     * @param \plugin_renderer_base $renderer Plugin renderer for confirmation pages.
     */
    public function __construct(
        /** @var questionnaire The active questionnaire. */
        private readonly questionnaire $questionnaire,
        /** @var \plugin_renderer_base Plugin renderer for confirmation pages. */
        private readonly \plugin_renderer_base $renderer,
    ) {
    }

    /**
     * Render the "delete this response?" confirmation page.
     *
     * Side effects: sets $PAGE title/heading, echoes header/page/footer.
     *
     * @param object $page Templatable page object (reportpage).
     * @param int $rid Response id to delete.
     * @param int $currentgroupid Group filter context.
     * @return void
     */
    public function confirm_delete_response(object $page, int $rid, int $currentgroupid): void {
        global $PAGE;

        require_capability('mod/questionnaire:deleteresponses', $this->questionnaire->context());

        if (!$this->questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        } else if ($this->questionnaire->survey()->owning_courseid() != $this->questionnaire->course()->id) {
            throw new \moodle_exception('surveyowner', 'mod_questionnaire');
        } else if (!$rid) {
            throw new \moodle_exception('invalidresponse', 'mod_questionnaire');
        } else if (!($resp = response_record::get_or_null($rid))) {
            throw new \moodle_exception('invalidresponserecord', 'mod_questionnaire');
        }

        $ruser = $this->describe_respondent((int)$resp->get('userid'));
        $timesubmitted = '<br />' . get_string('submitted', 'questionnaire') . '&nbsp;' . userdate($resp->get('submitted'));
        if ($this->questionnaire->respondenttype() == 'anonymous') {
            $ruser = '- ' . get_string('anonymous', 'questionnaire') . ' -';
            $timesubmitted = '';
        }

        $PAGE->set_title(get_string('deletingresp', 'questionnaire'));
        $PAGE->set_heading(format_string($this->questionnaire->course()->fullname));
        echo $this->renderer->header();

        (new \mod_questionnaire\output\tabs($this->questionnaire, 'deleteresp', $currentgroupid, $rid))->render($page);

        $instance = $this->questionnaire->id();
        $msg = '<div class="warning centerpara">';
        $msg .= get_string('confirmdelresp', 'questionnaire', $ruser . $timesubmitted);
        $msg .= '</div>';
        $urlyes = new \moodle_url('report.php', [
            'action' => 'dvresp',
            'rid' => $rid,
            'individualresponse' => 1,
            'instance' => $instance,
            'group' => $currentgroupid,
        ]);
        $urlno = new \moodle_url('report.php', [
            'action' => 'vresp',
            'instance' => $instance,
            'rid' => $rid,
            'individualresponse' => 1,
            'group' => $currentgroupid,
        ]);
        $buttonyes = new \single_button($urlyes, get_string('delete'), 'post');
        $buttonno = new \single_button($urlno, get_string('cancel'), 'get');
        $page->add_to_page('notifications', $this->renderer->confirm($msg, $buttonyes, $buttonno));
        echo $this->renderer->render($page);
        echo $this->renderer->footer($this->questionnaire->course());
    }

    /**
     * Execute the delete of a single response. Redirects on success, throws on failure.
     *
     * @param int $rid Response id to delete.
     * @return void
     */
    public function delete_response(int $rid): void {
        global $CFG;

        require_capability('mod/questionnaire:deleteresponses', $this->questionnaire->context());

        if (!$this->questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        } else if ($this->questionnaire->survey()->owning_courseid() != $this->questionnaire->course()->id) {
            throw new \moodle_exception('surveyowner', 'mod_questionnaire');
        } else if (!$rid) {
            throw new \moodle_exception('invalidresponse', 'mod_questionnaire');
        } else if (!($response = response_record::get_or_null($rid))) {
            throw new \moodle_exception('invalidresponserecord', 'mod_questionnaire');
        }

        $responseuserid = (int)$response->get('userid');
        $instance = $this->questionnaire->id();

        if ($this->questionnaire->responses()->delete_response($response->to_record())) {
            if (!response_record::count_complete_for_questionnaire($instance)) {
                $redirection = $CFG->wwwroot . '/mod/questionnaire/view.php?id=' .
                    $this->questionnaire->coursemodule()->id;
            } else {
                $redirection = $CFG->wwwroot . '/mod/questionnaire/report.php?action=vresp&instance=' .
                    $instance . '&byresponse=1';
            }

            $event = \mod_questionnaire\event\response_deleted::create([
                'objectid' => $this->questionnaire->surveyid(),
                'context' => $this->questionnaire->context(),
                'courseid' => $this->questionnaire->courseid(),
                'relateduserid' => $responseuserid,
            ]);
            $event->trigger();

            redirect($redirection);
        }

        $ruser = ($this->questionnaire->respondenttype() == 'anonymous')
            ? '- ' . get_string('anonymous', 'questionnaire') . ' -'
            : $this->describe_respondent($responseuserid);
        $link = new \moodle_url('/mod/questionnaire/report.php', [
            'action' => 'vresp',
            'sid' => $this->questionnaire->surveyid(),
            'instance' => $instance,
            'byresponse' => '1',
        ]);
        throw new \moodle_exception(
            'couldnotdelrespby',
            'mod_questionnaire',
            $link,
            ['rid' => $rid, 'user' => $ruser]
        );
    }

    /**
     * Render the "delete all responses?" confirmation page.
     *
     * Renders nothing if there are no participants to delete (mirrors original arm behaviour).
     *
     * @param object $page Templatable page object (reportpage).
     * @param int $groupmode Activity group mode (0/1/2).
     * @param int $currentgroupid Active group filter.
     * @param string $groupname Pre-formatted group label for the confirm message.
     * @param array $respsallparticipants Responses to participants in scope.
     * @return void
     */
    public function confirm_delete_all_responses(
        object $page,
        int $groupmode,
        int $currentgroupid,
        string $groupname,
        array $respsallparticipants
    ): void {
        global $PAGE;

        require_capability('mod/questionnaire:deleteresponses', $this->questionnaire->context());

        if (empty($respsallparticipants)) {
            return;
        }

        $PAGE->set_title(get_string('deletingresp', 'questionnaire'));
        $PAGE->set_heading(format_string($this->questionnaire->course()->fullname));
        echo $this->renderer->header();

        (new \mod_questionnaire\output\tabs($this->questionnaire, 'deleteall', $currentgroupid))->render($page);

        $instance = $this->questionnaire->id();
        $msg = '<div class="warning centerpara">';
        if ($groupmode == 0) {
            $msg .= get_string('confirmdelallresp', 'questionnaire');
        } else {
            $msg .= get_string('confirmdelgroupresp', 'questionnaire', $groupname);
        }
        $msg .= '</div>';

        $urlyes = new \moodle_url('report.php', [
            'action' => 'dvallresp',
            'sid' => $this->questionnaire->surveyid(),
            'instance' => $instance,
            'group' => $currentgroupid,
        ]);
        $urlno = new \moodle_url('report.php', ['instance' => $instance, 'group' => $currentgroupid]);
        $buttonyes = new \single_button($urlyes, get_string('delete'), 'post');
        $buttonno = new \single_button($urlno, get_string('cancel'), 'get');

        $page->add_to_page('notifications', $this->renderer->confirm($msg, $buttonyes, $buttonno));
        echo $this->renderer->render($page);
        echo $this->renderer->footer($this->questionnaire->course());
    }

    /**
     * Execute the delete of all responses (optionally group-scoped). Redirects on success.
     *
     * @param int $groupmode Activity group mode.
     * @param int $currentgroupid Active group filter.
     * @param array $respsallparticipants Pre-computed all-participants responses.
     * @return void
     */
    public function delete_all_responses(
        int $groupmode,
        int $currentgroupid,
        array $respsallparticipants
    ): void {
        global $CFG;

        require_capability('mod/questionnaire:deleteresponses', $this->questionnaire->context());

        if (!$this->questionnaire->survey()) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        } else if ($this->questionnaire->survey()->owning_courseid() != $this->questionnaire->course()->id) {
            throw new \moodle_exception('surveyowner', 'mod_questionnaire');
        }

        if ($groupmode > 0 && $currentgroupid > 0) {
            $resps = $this->questionnaire->get_responses(false, $currentgroupid) ?: [];
        } else {
            $resps = $respsallparticipants;
        }

        $instance = $this->questionnaire->id();
        if (!empty($resps)) {
            foreach ($resps as $response) {
                $this->questionnaire->responses()->delete_response($response);
            }
            $sid = $this->questionnaire->surveyid();
            if (!$this->questionnaire->count_submissions()) {
                $redirection = $CFG->wwwroot . '/mod/questionnaire/view.php?id=' .
                    $this->questionnaire->coursemodule()->id;
            } else {
                $redirection = $CFG->wwwroot . '/mod/questionnaire/report.php?action=vall&sid=' .
                    $sid . '&instance=' . $instance;
            }

            $event = \mod_questionnaire\event\all_responses_deleted::create([
                'objectid' => $instance,
                'anonymous' => $this->questionnaire->respondenttype() == 'anonymous',
                'context' => $this->questionnaire->context(),
            ]);
            $event->trigger();

            redirect($redirection);
        }

        $link = new \moodle_url('/mod/questionnaire/report.php', [
            'action' => 'vall',
            'sid' => $this->questionnaire->surveyid(),
            'instance' => $instance,
        ]);
        throw new \moodle_exception('couldnotdelresp', 'mod_questionnaire', $link);
    }

    /**
     * Return a display string for a respondent (fullname, "unknown", or the empty marker).
     *
     * @param int $userid
     * @return string
     */
    private function describe_respondent(int $userid): string {
        if (empty($userid)) {
            return (string)$userid;
        }
        if ($user = \core_user::get_user($userid)) {
            return fullname($user);
        }
        return '- ' . get_string('unknown', 'questionnaire') . ' -';
    }
}
