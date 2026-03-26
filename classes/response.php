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
 * The response domain object — a single user's submission to a questionnaire instance.
 *
 * Owns the questionnaire_response header record and provides operations on it
 * (creation, completion, timestamp). The questionnaire_responses collection
 * class handles bulk queries and answer-level CRUD.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class response {
    /** @var response_record The response header record. */
    protected response_record $record;

    /**
     * Construct from a response_record.
     *
     * @param response_record $record
     */
    public function __construct(response_record $record) {
        $this->record = $record;
    }

    // Factories.

    /**
     * Return the most recent incomplete response for the given user and questionnaire, or null if none.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return self|null
     */
    public static function latest_incomplete(int $questionnaireid, int $userid): ?self {
        global $DB;
        $params = ['questionnaireid' => $questionnaireid, 'userid' => $userid, 'complete' => 'n'];
        $records = $DB->get_records('questionnaire_response', $params, 'submitted DESC', 'id', 0, 1);
        if (empty($records)) {
            return null;
        }
        return new self(new response_record((int) reset($records)->id));
    }

    /**
     * Create a new incomplete response header for the given questionnaire and user.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return self
     */
    public static function create(int $questionnaireid, int $userid): self {
        $record = new response_record();
        $record->set('questionnaireid', $questionnaireid);
        $record->set('userid', $userid);
        $record->set('submitted', time());
        $record->set('complete', 'n');
        $record->create();
        return new self($record);
    }

    // Accessors.

    /**
     * Get the response id.
     *
     * @return int
     */
    public function id(): int {
        return (int) $this->record->get('id');
    }

    /**
     * Get the user id.
     *
     * @return int
     */
    public function userid(): int {
        return (int) $this->record->get('userid');
    }

    /**
     * Get the questionnaire id this response belongs to.
     *
     * @return int
     */
    public function questionnaireid(): int {
        return (int) $this->record->get('questionnaireid');
    }

    /**
     * True if this response has been marked as complete.
     *
     * @return bool
     */
    public function is_complete(): bool {
        return $this->record->get('complete') === 'y';
    }

    /**
     * Get the submitted timestamp.
     *
     * @return int
     */
    public function submitted_at(): int {
        return (int) $this->record->get('submitted');
    }

    // Operations.

    /**
     * Update the submitted timestamp on this response header.
     *
     * @return void
     */
    public function touch(): void {
        $this->record->set('submitted', time());
        $this->record->update();
    }

    /**
     * Mark this response as complete and set its grade.
     *
     * @param int $grade
     * @return void
     */
    public function commit(int $grade = 0): void {
        $this->record->set('complete', 'y');
        $this->record->set('submitted', time());
        $this->record->set('grade', $grade);
        $this->record->update();
    }
}
