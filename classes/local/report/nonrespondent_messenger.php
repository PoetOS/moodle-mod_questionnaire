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
use stdClass;

/**
 * Works out who a non-respondent reminder goes to and sends it.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nonrespondent_messenger {
    /** @var string Anonymous recipient choice: nobody. */
    public const SELECT_NONE = 'none';

    /** @var string Anonymous recipient choice: every non-respondent. */
    public const SELECT_ALL = 'all';

    /** @var string Anonymous recipient choice: non-respondents with a saved, unsubmitted response. */
    public const SELECT_STARTED = 'started';

    /** @var string Anonymous recipient choice: non-respondents who have not started. */
    public const SELECT_NOTSTARTED = 'notstarted';

    /** @var questionnaire */
    protected questionnaire $questionnaire;

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire
     */
    public function __construct(questionnaire $questionnaire) {
        $this->questionnaire = $questionnaire;
    }

    /**
     * Which of the given non-respondents have a saved, unsubmitted response.
     *
     * @param int[] $nonrespondents
     * @return int[]
     */
    public function started_userids(array $nonrespondents): array {
        global $DB;
        $started = $DB->get_fieldset_select(
            'questionnaire_response',
            'DISTINCT userid',
            'questionnaireid = :questionnaireid AND complete = :complete',
            ['questionnaireid' => $this->questionnaire->id(), 'complete' => 'n']
        );
        return array_values(array_intersect($nonrespondents, array_map('intval', $started)));
    }

    /**
     * The recipients for an anonymous questionnaire's recipient choice.
     *
     * @param string $selection One of the SELECT_* constants.
     * @param int[] $nonrespondents
     * @return int[]
     */
    public function recipients_for_selection(string $selection, array $nonrespondents): array {
        switch ($selection) {
            case self::SELECT_ALL:
                return array_values($nonrespondents);
            case self::SELECT_STARTED:
                return $this->started_userids($nonrespondents);
            case self::SELECT_NOTSTARTED:
                return array_values(array_diff($nonrespondents, $this->started_userids($nonrespondents)));
            default:
                return [];
        }
    }

    /**
     * The HTML message body: a link to the questionnaire followed by the teacher's message.
     *
     * @param \renderer_base $output
     * @param string $message The teacher's message (cleaned HTML).
     * @return string
     */
    public function message_body(\renderer_base $output, string $message): string {
        return $output->render_from_template('mod_questionnaire/nonrespondent_message', [
            'url' => (new \moodle_url('/mod/questionnaire/view.php', ['id' => $this->questionnaire->coursemodule()->id]))
                ->out(false),
            'name' => format_string($this->questionnaire->name(), true, ['context' => $this->questionnaire->context()]),
            'message' => $message,
        ]);
    }

    /**
     * Send the message to each recipient.
     *
     * @param int[] $userids
     * @param string $subject
     * @param string $htmlbody The full HTML body, from message_body().
     * @param stdClass $from The sending user.
     * @return bool True if every message was sent.
     */
    public function send(array $userids, string $subject, string $htmlbody, stdClass $from): bool {
        $allsent = true;
        foreach ($userids as $userid) {
            $message = new \core\message\message();
            $message->courseid = $this->questionnaire->courseid();
            $message->name = 'message';
            $message->component = 'mod_questionnaire';
            $message->userfrom = $from;
            $message->userto = \core_user::get_user($userid, '*', MUST_EXIST);
            $message->subject = $subject;
            $message->fullmessage = html_to_text($htmlbody);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $htmlbody;
            $message->smallmessage = '';
            $allsent = (message_send($message) !== false) && $allsent;
        }
        return $allsent;
    }
}
