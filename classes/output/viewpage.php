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

use html_writer;
use mod_questionnaire\questionnaire;

/**
 * Contains class mod_questionnaire\output\viewpage
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class viewpage extends questionnairepage {
    /**
     * The data to be exported.
     * @var array
     */
    protected $data;

    /**
     * The template to use for rendering.
     * @var string
     */
    protected $template = 'mod_questionnaire/viewpage';
    /**
     * Construct the view page.
     * @param questionnaire $questionnaire The questionnaire object.
     */
    public function __construct(questionnaire $questionnaire) {
        global $USER;

        $currentgroupid = groups_get_activity_group($questionnaire->coursemodule());
        if (!groups_is_member($currentgroupid)) {
            $currentgroupid = 0;
        }

        $message = $questionnaire->user_access_messages($USER->id);
        if (!empty($message)) {
            $this->add_message($message);
        } else if ($questionnaire->user_can_take($USER->id)) {
            if ($questionnaire->questions()) { // Sanity check.
                if (!$questionnaire->user_has_saved_response($USER->id)) {
                    $this->add_complete_button($questionnaire);
                } else {
                    $this->add_resume_button($questionnaire);
                }
            } else {
                $this->add_message(get_string('noneinuse', 'questionnaire'));
            }
        }

        if ($questionnaire->can_edit_questions() && !$questionnaire->questions() && $questionnaire->is_active()) {
            $this->add_addquestions_button($questionnaire);
        }

        // Time zone message (if required).
        if (!$message && $questionnaire->is_open() && !$questionnaire->is_closed()) {
            $this->add_info($questionnaire->view_information());
        }

        if (isguestuser()) {
            $this->add_guestuser_options();
        }

        $usernumresp = $questionnaire->count_submissions($USER->id);

        if ($questionnaire->can_read_own_responses() && ($usernumresp > 0)) {
            $this->add_myresponses($questionnaire, $usernumresp);
        }

        if ($questionnaire->can_view_all_responses($usernumresp)) {
            $this->add_allresponses($questionnaire, $currentgroupid);
        }
    }

    /**
     * Add a main button to the page.
     * @param \moodle_url $url The URL for the button.
     * @param string $text The text for the button.
     */
    protected function add_main_button(\moodle_url $url, string $text) {
        $this->data['completeurl'] = $url->out(true);
        $this->data['completetext'] = $text;
    }

    /**
     * Add the complete button to the page.
     * @param questionnaire $questionnaire The questionnaire object.
     */
    public function add_complete_button(questionnaire $questionnaire) {
        $url = new \moodle_url('/mod/questionnaire/complete.php');
        $url->param('id', $questionnaire->coursemodule()->id);
        $this->add_main_button(
            $url,
            get_string('answerquestions', 'questionnaire')
        );
    }

    /**
     * Add the resume button to the page.
     * @param questionnaire $questionnaire The questionnaire object.
     */
    public function add_resume_button(questionnaire $questionnaire) {
        $url = new \moodle_url('/mod/questionnaire/complete.php');
        $url->param('id', $questionnaire->coursemodule()->id);
        $url->param('resume', 1);
        $this->add_main_button(
            $url,
            get_string('resumequestionnaire', 'questionnaire')
        );
    }

    /**
     * Add the add questions button to the page.
     * @param questionnaire $questionnaire The questionnaire object.
     */
    public function add_addquestions_button(questionnaire $questionnaire) {
        $url = new \moodle_url('/mod/questionnaire/questions.php');
        $url->param('id', $questionnaire->coursemodule()->id);
        $this->add_main_button(
            $url,
            get_string('addquestions', 'questionnaire')
        );
    }

    /**
     * Add guest user options to the page.
     */
    public function add_guestuser_options() {
        $guestno = html_writer::tag('p', get_string('noteligible', 'questionnaire'));
        $liketologin = html_writer::tag('p', get_string('liketologin'));
        $data['questuser'] = $this->confirm(
            $guestno . "\n\n" . $liketologin . "\n",
            get_login_url(),
            get_local_referer(false)
        );
    }

    /**
     * Add the 'view your responses' link to the page.
     * @param questionnaire $questionnaire The questionnaire object.
     * @param int $numresponses The number of responses by the user.
     */
    public function add_myresponses(questionnaire $questionnaire, int $numresponses) {
        global $USER;

        $url = new \moodle_url('/mod/questionnaire/myreport.php');
        $url->param('instance', $questionnaire->id());
        $url->param('user', $USER->id);

        if ($numresponses > 1) {
            $titletext = get_string('viewyourresponses', 'questionnaire', $numresponses);
        } else {
            $titletext = get_string('yourresponse', 'questionnaire');
            $url->param('byresponse', 1);
            $url->param('action', 'vresp');
        }
        $this->data['yourresponse'] = [
            'url' => $url->out(true),
            'title' => $titletext,
        ];
    }

    /**
     * Add the 'view all responses' link to the page.
     * @param questionnaire $questionnaire The questionnaire object.
     * @param int $currentgroup The current group id.
     */
    public function add_allresponses(questionnaire $questionnaire, int $currentgroup) {
        $url = new \moodle_url('/mod/questionnaire/report.php');
        $url->param('instance', $questionnaire->id());
        $url->param('group', $currentgroup);
        $this->data['allresponses'] = [
            'url' => $url->out(true),
            'title' => get_string('viewallresponses', 'questionnaire'),
        ];
    }
}
