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

use stdClass;

/**
 * Notification dispatcher for questionnaire submissions.
 *
 * Encapsulates the post-submit fan-out: emailing the survey-defined address,
 * messaging users with the submissionnotification capability, and formatting
 * the submission body for both delivery channels.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class submission_notifier {
    /** @var questionnaire The questionnaire whose submissions this notifier handles. */
    private questionnaire $questionnaire;

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire
     */
    public function __construct(questionnaire $questionnaire) {
        $this->questionnaire = $questionnaire;
    }

    /**
     * Notify subscribers that a response has been submitted.
     *
     * Sends the survey-configured email and, when the questionnaire's notifications
     * setting is non-zero, dispatches a Moodle message to each capable user.
     *
     * @param int $rid The response id.
     * @return bool True if all delivery attempts succeeded.
     */
    public function notify(int $rid): bool {
        $success = true;
        $email = $this->questionnaire->survey()->email();
        if (!empty($email)) {
            $success = $this->send_email($rid, $email);
        }
        if ($this->questionnaire->notifications()) {
            $success = $this->send_notifications($rid) && $success;
        }
        return $success;
    }

    /**
     * Return users who should be notified of a new submission by $userid.
     *
     * In SEPARATEGROUPS mode only users who share a group with $userid are notified.
     * Users with no group are notified only if the submitter also has no group.
     *
     * @param int $userid Submitting user id.
     * @return array Indexed by user id.
     */
    public function get_notifiable_users(int $userid): array {
        $potentialusers = get_enrolled_users(
            $this->questionnaire->context(),
            'mod/questionnaire:submissionnotification',
            null,
            'u.*',
            null,
            null,
            null,
            true
        );

        $cm = $this->questionnaire->coursemodule();
        $notifiableusers = [];
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            if ($groups = groups_get_all_groups($this->questionnaire->courseid(), $userid, $cm->groupingid)) {
                foreach ($groups as $group) {
                    foreach ($potentialusers as $potentialuser) {
                        if ($potentialuser->id == $userid) {
                            continue;
                        }
                        if (groups_is_member($group->id, $potentialuser->id)) {
                            $notifiableusers[$potentialuser->id] = $potentialuser;
                        }
                    }
                }
            } else {
                foreach ($potentialusers as $potentialuser) {
                    if ($potentialuser->id == $userid) {
                        continue;
                    }
                    if (!groups_has_membership($cm, $potentialuser->id)) {
                        $notifiableusers[$potentialuser->id] = $potentialuser;
                    }
                }
            }
        } else {
            foreach ($potentialusers as $potentialuser) {
                if ($potentialuser->id == $userid) {
                    continue;
                }
                $notifiableusers[$potentialuser->id] = $potentialuser;
            }
        }
        return $notifiableusers;
    }

    /**
     * Send a Moodle message to each user with the submissionnotification capability.
     *
     * @param int $rid The response id.
     * @return bool
     */
    private function send_notifications(int $rid): bool {
        global $CFG, $USER;

        $this->questionnaire->add_response($rid);
        $message = '';
        if ($this->questionnaire->notifications() == 2) {
            $message .= $this->build_full_submission_message($rid);
        }

        $success = true;
        if ($notifyusers = $this->get_notifiable_users($USER->id)) {
            $info = new stdClass();
            if ($this->questionnaire->respondenttype() != 'anonymous') {
                $info->userfrom = $USER;
                $info->username = fullname($info->userfrom, true);
                $info->profileurl = $CFG->wwwroot . '/user/view.php?id=' . $info->userfrom->id .
                    '&course=' . $this->questionnaire->courseid();
                $langstringtext = 'submissionnotificationtextuser';
                $langstringhtml = 'submissionnotificationhtmluser';
            } else {
                $info->userfrom = \core_user::get_noreply_user();
                $info->username = '';
                $info->profileurl = '';
                $langstringtext = 'submissionnotificationtextanon';
                $langstringhtml = 'submissionnotificationhtmlanon';
            }
            $info->name = format_string($this->questionnaire->name());
            $info->submissionurl = $CFG->wwwroot . '/mod/questionnaire/report.php?action=vresp&sid=' .
                $this->questionnaire->surveyid() . '&rid=' . $rid . '&instance=' . $this->questionnaire->id();
            $info->coursename = format_string($this->questionnaire->course()->fullname);
            $info->postsubject = get_string('submissionnotificationsubject', 'questionnaire');
            $info->posttext = get_string($langstringtext, 'questionnaire', $info);
            $info->posthtml = '<p>' . get_string($langstringhtml, 'questionnaire', $info) . '</p>';
            if (!empty($message)) {
                $info->posttext .= html_to_text($message);
                $info->posthtml .= $message;
            }
            foreach ($notifyusers as $notifyuser) {
                $info->userto = $notifyuser;
                $this->dispatch_message($info, 'notification');
            }
        }
        return $success;
    }

    /**
     * Format every question and answer for a single submission as HTML for inline message bodies.
     *
     * @param int $rid The response id.
     * @return string
     */
    private function build_full_submission_message(int $rid): string {
        $responses = $this->questionnaire->responses()->get_full_submission_for_export($rid);
        $message = '';
        foreach ($responses as $response) {
            $message .= html_to_text(format_string($response->questionname)) . "<br />\n";
            $message .= get_string('question') . ': ' . html_to_text(format_string($response->questiontext)) . "<br />\n";
            $message .= get_string('answers', 'questionnaire') . ":<br />\n";
            foreach ($response->answers as $answer) {
                $message .= html_to_text($answer) . "<br />\n";
            }
            $message .= "<br />\n";
        }
        return $message;
    }

    /**
     * Send the full response submission to the survey-configured email recipient list.
     *
     * @param int $rid The response id.
     * @param string $email Comma- or semicolon-separated list of addresses.
     * @return bool
     */
    private function send_email(int $rid, string $email): bool {
        global $CFG;

        $submission = $this->questionnaire->reporter()->generate_csv(0, (string)$rid, '', null, 1);
        if (!empty($submission)) {
            $answers = $this->format_answers_for_email($submission);
        } else {
            $answers = ['html' => '', 'plaintext' => ''];
        }

        $name = s($this->questionnaire->name());
        if (empty($email)) {
            return false;
        }

        $endhtml = "\r\n<br>";
        $endplaintext = "\r\n";

        $subject = get_string('surveyresponse', 'questionnaire') . ": $name [$rid]";
        $url = $CFG->wwwroot . '/mod/questionnaire/report.php?action=vresp&amp;sid=' .
            $this->questionnaire->surveyid() . '&amp;rid=' . $rid . '&amp;instance=' . $this->questionnaire->id();

        $bodyhtml = '<a href="' . $url . '">' . $url . '</a>' . $endhtml;
        $bodyplaintext = $url . $endplaintext;
        $bodyhtml .= get_string('surveyresponse', 'questionnaire') . ' "' . $name . '"' . $endhtml;
        $bodyplaintext .= get_string('surveyresponse', 'questionnaire') . ' "' . $name . '"' . $endplaintext;
        $bodyhtml .= $answers['html'];
        $bodyplaintext .= $answers['plaintext'];

        $altbody = "\n$bodyplaintext\n";
        $return = true;
        $userfrom = \core_user::get_noreply_user();
        foreach (preg_split('/,|;/', $email) as $addr) {
            $userto = new stdClass();
            $userto->email = trim($addr);
            $userto->mailformat = 1;
            $userto->id = -10;
            if (!email_to_user($userto, $userfrom, $subject, $altbody, $bodyhtml)) {
                $return = false;
            }
        }
        return $return;
    }

    /**
     * Format submission answers for email delivery.
     *
     * @param array $answers Raw answers array from generate_csv.
     * @return array Keys 'plaintext' and 'html'.
     */
    private function format_answers_for_email(array $answers): array {
        global $USER;

        $formatted = ['plaintext' => '', 'html' => ''];
        // The generate_csv result is an array of rows; we need at least a header row (0) and
        // a data row (1). Anything less means there is nothing to format.
        if (count($answers) < 2) {
            return $formatted;
        }

        $endhtml = "\r\n<br />";
        $endplaintext = "\r\n";
        reset($answers);

        for ($i = 0; $i < count($answers[0]); $i++) {
            $sep = ' : ';
            switch ($i) {
                case 1:
                    $sep = ' ';
                    break;
                case 4:
                    $formatted['plaintext'] .= get_string('user') . ' ';
                    $formatted['html'] .= get_string('user') . ' ';
                    break;
                case 6:
                    if ($this->questionnaire->respondenttype() != 'anonymous') {
                        $formatted['html'] .= get_string('email') . $sep . $USER->email . $endhtml;
                        $formatted['plaintext'] .= get_string('email') . $sep . $USER->email . $endplaintext;
                    }
            }
            $formatted['html'] .= $answers[0][$i] . $sep . $answers[1][$i] . $endhtml;
            $formatted['plaintext'] .= $answers[0][$i] . $sep . $answers[1][$i] . $endplaintext;
        }
        return $formatted;
    }

    /**
     * Send a message via Moodle messaging.
     *
     * @param object $info Message details (userfrom, userto, postsubject, posttext, posthtml, submissionurl, name).
     * @param string $eventtype Message type identifier.
     */
    private function dispatch_message(object $info, string $eventtype): void {
        $eventdata = new \core\message\message();
        $eventdata->courseid = $this->questionnaire->courseid();
        $eventdata->modulename = 'questionnaire';
        $eventdata->userfrom = $info->userfrom;
        $eventdata->userto = $info->userto;
        $eventdata->subject = $info->postsubject;
        $eventdata->fullmessage = $info->posttext;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $info->posthtml;
        $eventdata->smallmessage = $info->postsubject;
        $eventdata->name = $eventtype;
        $eventdata->component = 'mod_questionnaire';
        $eventdata->notification = 1;
        $eventdata->contexturl = $info->submissionurl;
        $eventdata->contexturlname = $info->name;
        message_send($eventdata);
    }
}
