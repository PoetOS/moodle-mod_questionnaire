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

/**
 * Controller for in-progress submission flow: page navigation and section saves.
 *
 * Coordinates the responses handler and question navigator when a user is
 * working through a multi-page questionnaire: replacing the answers for the
 * current section, then deciding which page to go to next.
 *
 * @package    mod_questionnaire
 * @copyright  2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_controller {
    /** @var questionnaire The questionnaire instance this controller operates on. */
    private readonly questionnaire $questionnaire;

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire
     */
    public function __construct(questionnaire $questionnaire) {
        $this->questionnaire = $questionnaire;
    }

    /**
     * Delete the current response section and insert a fresh one; return the new response id.
     *
     * @param object $response Response data object (must have ->rid and ->sec).
     * @param int $userid
     * @return int New response id.
     */
    public function existing_response_action(object $response, int $userid): int {
        $responses = $this->questionnaire->responses();
        $responses->response_delete($response->rid, $response->sec);
        return $responses->response_insert($response, $userid);
    }

    /**
     * Validate and save the current page, then return the next page number (or an error string).
     *
     * @param object $response Response data object.
     * @param int $userid
     * @return int|bool|string Next section number, false if no next page, or error message string.
     */
    public function next_page_action(object $response, int $userid): int|bool|string {
        $msg = $this->questionnaire->responses()->response_check_format(
            $response->sec,
            $response,
            true,
            true,
            $this->questionnaire->questions()
        );
        if (!empty($msg)) {
            return $msg;
        }
        $response->rid = $this->existing_response_action($response, $userid);
        return $this->questionnaire->navigator()->next_page($response->sec, $response->rid);
    }

    /**
     * Save the current page and return the previous page number.
     *
     * @param object $response Response data object.
     * @param int $userid
     * @return int|bool Previous section number, or false if none.
     */
    public function previous_page_action(object $response, int $userid): int|bool {
        $response->rid = $this->existing_response_action($response, $userid);
        return $this->questionnaire->navigator()->prev_page($response->sec, $response->rid);
    }
}
