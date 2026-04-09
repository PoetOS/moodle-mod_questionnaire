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

namespace mod_questionnaire\local\response;

use mod_questionnaire\local\db\response_record;

/**
 * The response domain object — a single user's submission to a questionnaire instance.
 *
 * Merges the former mod_questionnaire\local\response\response\response (a plain DTO
 * used throughout the processing pipeline) with the former mod_questionnaire\response
 * (a persistent-backed domain object). The unified class is persistent-backed and
 * carries the full interface of both predecessors.
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
     * Array of answers by question id, each an array of answer objects.
     * Populated by add_questions_answers() or response_from_webform().
     *
     * @var array
     */
    public array $answers = [];

    // TEMPORARY magic property accessors (Phase 24).
    // These __get / __set / __isset methods allow callers that use plain property
    // access ($response->id, $response->complete, etc.) to continue working while
    // the codebase is migrated to the typed accessor methods. They will be removed
    // in a future phase once all callers use the named accessor API.

    /**
     * TEMPORARY: Proxy read access to DB-backed response fields.
     *
     * Handles: id, questionnaireid, userid, submitted, complete, grade.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed {
        $dbfields = ['questionnaireid', 'userid', 'submitted', 'complete', 'grade'];
        if ($name === 'id') {
            return $this->record->get('id');
        }
        if (in_array($name, $dbfields, true)) {
            return $this->record->get($name);
        }
        return null;
    }

    /**
     * TEMPORARY: Proxy write access to DB-backed response fields.
     *
     * @param string $name
     * @param mixed  $value
     * @return void
     */
    public function __set(string $name, mixed $value): void {
        $dbfields = ['questionnaireid', 'userid', 'submitted', 'complete', 'grade'];
        if ($name === 'id') {
            $stub = new \stdClass();
            $stub->id = (int)$value;
            $this->record->from_record($stub);
            return;
        }
        if (in_array($name, $dbfields, true)) {
            $this->record->set($name, $value);
            return;
        }
    }

    /**
     * TEMPORARY: Allow isset() checks on proxied DB-backed response fields.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool {
        $proxied = ['id', 'questionnaireid', 'userid', 'submitted', 'complete', 'grade'];
        return in_array($name, $proxied, true);
    }

    // End TEMPORARY magic property accessors.

    /**
     * Construct from a response_record.
     *
     * @param response_record|null $record  Null creates an empty record.
     * @param bool $addanswers  When true, load all question answers from the DB.
     */
    public function __construct(?response_record $record = null, bool $addanswers = false) {
        $this->record = $record ?? new response_record();
        if ($addanswers) {
            $this->add_questions_answers();
        }
    }

    // Factories.

    /**
     * Build a response from an array or stdClass of raw DB field values.
     *
     * Used when constructing a response object from a record already fetched
     * from the DB (e.g. by questionnaire_responses::add_user_responses).
     *
     * @param array|\stdClass $responsedata
     * @return self
     */
    public static function create_from_data($responsedata): self {
        if (!is_array($responsedata)) {
            $responsedata = (array)$responsedata;
        }
        $data = new \stdClass();
        foreach (['id', 'questionnaireid', 'userid', 'submitted', 'complete', 'grade'] as $field) {
            if (isset($responsedata[$field])) {
                $data->$field = $responsedata[$field];
            }
        }
        return new self(new response_record(0, $data), true);
    }

    /**
     * Build a response from web form submission data, including all question answers.
     *
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param array $questions Array of question objects.
     * @return self
     */
    public static function response_from_webform(\stdClass $responsedata, array $questions): self {
        global $USER;

        $questionnaireid = isset($responsedata->questionnaire_id) ? $responsedata->questionnaire_id :
            (isset($responsedata->a) ? $responsedata->a : 0);

        $data = new \stdClass();
        $data->id = (int)$responsedata->rid;
        $data->questionnaireid = (int)$questionnaireid;
        $data->userid = (int)$USER->id;
        $response = new self(new response_record(0, $data), false);

        foreach ($questions as $question) {
            if ($question->supports_responses()) {
                $response->answers[$question->id] = $question->responsetype::answers_from_webform(
                    $responsedata,
                    $question
                );
            }
        }
        return $response;
    }

    /**
     * Build a response from mobile app submission data, including all question answers.
     *
     * @param int $questionnaireid
     * @param int $responseid
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param array $questions Array of question objects.
     * @return self
     */
    public static function response_from_appdata(
        int $questionnaireid,
        int $responseid,
        \stdClass $responsedata,
        array $questions
    ): self {
        global $USER;

        $data = new \stdClass();
        $data->id = $responseid;
        $data->questionnaireid = $questionnaireid;
        $data->userid = (int)$USER->id;
        $response = new self(new response_record(0, $data), false);

        // Process app data by question and choice into a webform-compatible structure.
        $processedresponses = new \stdClass();
        $processedresponses->rid = $responseid;
        foreach ($responsedata as $answerid => $value) {
            $parts = explode('_', $answerid);
            if ($parts[0] == 'response') {
                $qid = 'q' . $parts[2];
                if (!isset($processedresponses->{$qid})) {
                    $processedresponses->{$qid} = [];
                }
                $cid = isset($parts[3]) ? $parts[3] : 0;
                $processedresponses->{$qid}[$cid] = $value;
            }
        }

        foreach ($questions as $question) {
            if ($question->supports_responses() && isset($processedresponses->{'q' . $question->id})) {
                $response->answers[$question->id] = $question->responsetype::answers_from_appdata(
                    $processedresponses,
                    $question
                );
            }
        }
        return $response;
    }

    /**
     * Return the most recent incomplete response for the given user and questionnaire, or null if none.
     *
     * Delegates to response_record::get_latest_incomplete().
     *
     * @param int $questionnaireid
     * @param int $userid
     * @return self|null
     */
    public static function latest_incomplete(int $questionnaireid, int $userid): ?self {
        $record = response_record::get_latest_incomplete($questionnaireid, $userid);
        return $record === null ? null : new self($record);
    }

    /**
     * Create and persist a new incomplete response header for the given questionnaire and user.
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

    // Typed accessors.

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

    /**
     * Get the grade value.
     *
     * @return int
     */
    public function grade(): int {
        return (int) $this->record->get('grade');
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

    /**
     * Load and populate $this->answers with all question answers for this response.
     *
     * @return void
     */
    public function add_questions_answers(): void {
        $this->answers = [];
        $this->answers += \mod_questionnaire\local\response\multiple::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\local\response\single::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\local\response\rank::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\local\response\boolean::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\local\response\date::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\local\response\text::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\local\response\file::response_answers_by_question($this->id);
    }
}
