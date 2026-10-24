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
use moodle_url;
use renderer_base;
use stdClass;

/**
 * The questionnaire landing page (view.php).
 *
 * Decides what the viewing user is told and which actions they are offered: the access
 * message, the answer/resume and add-questions buttons, links to their own and to all
 * responses, the open/close information lines, and the log-in prompt for guests.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class viewpage implements \renderable, \templatable {
    /** @var questionnaire The questionnaire being viewed. */
    protected questionnaire $questionnaire;

    /** @var int The viewing user's id. */
    protected int $userid;

    /** @var int The viewing user's current activity group, or 0. */
    protected int $groupid;

    /** @var moodle_url|null Where a guest returns to if they decline to log in; null when not a guest. */
    protected ?moodle_url $guestcancelurl;

    /**
     * Construct the renderable.
     *
     * @param questionnaire $questionnaire
     * @param int $userid The viewing user.
     * @param int $groupid The viewing user's current activity group, or 0.
     * @param moodle_url|null $guestcancelurl Set only when the viewer is a guest; enables the log-in prompt.
     */
    public function __construct(questionnaire $questionnaire, int $userid, int $groupid, ?moodle_url $guestcancelurl = null) {
        $this->questionnaire = $questionnaire;
        $this->userid = $userid;
        $this->groupid = $groupid;
        $this->guestcancelurl = $guestcancelurl;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $accessmessage = $this->questionnaire->user_access_messages($this->userid);

        $data = new stdClass();
        $data->message = $accessmessage ?? $this->empty_survey_message();
        $data->actions = $this->actions($accessmessage);
        $data->hasactions = !empty($data->actions);
        $data->info = $this->info_lines($accessmessage);
        $data->hasinfo = !empty($data->info);
        $data->guestconfirm = $this->guest_confirm($output);
        return $data;
    }

    /**
     * The "no questions" notice, shown to a user who could take the questionnaire if it had any.
     *
     * @return string|null
     */
    private function empty_survey_message(): ?string {
        if ($this->questionnaire->capabilities()->user_can_take($this->userid) && !$this->questionnaire->questions()) {
            return get_string('noneinuse', 'questionnaire');
        }
        return null;
    }

    /**
     * The action buttons, in display order.
     *
     * @param string|null $accessmessage The user's access message; any message withholds the answer button.
     * @return array[] Each entry is ['url' => string, 'label' => string].
     */
    private function actions(?string $accessmessage): array {
        $actions = [];
        $cmid = $this->questionnaire->coursemodule()->id;
        $capabilities = $this->questionnaire->capabilities();

        if ($accessmessage === null && $capabilities->user_can_take($this->userid) && $this->questionnaire->questions()) {
            if ($this->questionnaire->user_has_saved_response($this->userid)) {
                $actions[] = $this->action(
                    new moodle_url('/mod/questionnaire/complete.php', ['id' => $cmid, 'resume' => 1]),
                    get_string('resumesurvey', 'questionnaire')
                );
            } else {
                $actions[] = $this->action(
                    new moodle_url('/mod/questionnaire/complete.php', ['id' => $cmid]),
                    get_string('answerquestions', 'questionnaire')
                );
            }
        }

        if ($capabilities->can_edit_questions() && !$this->questionnaire->questions() && $this->questionnaire->is_active()) {
            $actions[] = $this->action(
                new moodle_url('/mod/questionnaire/questions.php', ['id' => $cmid]),
                get_string('addquestions', 'questionnaire')
            );
        }

        $usernumresp = $this->questionnaire->count_submissions($this->userid);
        if ($capabilities->can_read_own_responses() && $usernumresp > 0) {
            $params = ['instance' => $this->questionnaire->id(), 'user' => $this->userid];
            if ($usernumresp > 1) {
                $label = get_string('viewyourresponses', 'questionnaire', $usernumresp);
            } else {
                $label = get_string('yourresponse', 'questionnaire');
                $params += ['byresponse' => 1, 'action' => 'vresp'];
            }
            $actions[] = $this->action(new moodle_url('/mod/questionnaire/myreport.php', $params), $label);
        }

        if ($capabilities->can_view_all_responses($usernumresp)) {
            $actions[] = $this->action(
                new moodle_url('/mod/questionnaire/report.php', [
                    'instance' => $this->questionnaire->id(),
                    'group' => $this->groupid,
                ]),
                get_string('viewallresponses', 'questionnaire')
            );
        }

        return $actions;
    }

    /**
     * One action button's template data.
     *
     * @param moodle_url $url
     * @param string $label
     * @return array
     */
    private function action(moodle_url $url, string $label): array {
        return ['url' => $url->out(false), 'label' => $label];
    }

    /**
     * The attempts/open/close information lines, shown only while the questionnaire is open.
     *
     * @param string|null $accessmessage The user's access message; any message suppresses the lines.
     * @return string[]
     */
    private function info_lines(?string $accessmessage): array {
        if ($accessmessage !== null || !$this->questionnaire->is_open() || $this->questionnaire->is_closed()) {
            return [];
        }
        return $this->questionnaire->view_information();
    }

    /**
     * The log-in prompt for a guest, or null for everyone else.
     *
     * @param renderer_base $output
     * @return string|null
     */
    private function guest_confirm(renderer_base $output): ?string {
        if ($this->guestcancelurl === null) {
            return null;
        }
        $message = \html_writer::tag('p', get_string('noteligible', 'questionnaire')) .
            \html_writer::tag('p', get_string('liketologin'));
        return $output->confirm($message, get_login_url(), $this->guestcancelurl);
    }
}
