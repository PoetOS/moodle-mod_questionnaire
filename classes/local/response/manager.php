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

/**
 * Response manager — centralises all response CRUD and query operations.
 *
 * @package    mod_questionnaire
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var \questionnaire The questionnaire instance. */
    private readonly \questionnaire $questionnaire;

    /**
     * Constructor.
     * @param \questionnaire $questionnaire
     */
    public function __construct(\questionnaire $questionnaire) {
        $this->questionnaire = $questionnaire;
    }

    // Response loading / factory methods.

    /**
     * Load all response information for the given user into $questionnaire->responses.
     *
     * @param int|null $userid
     */
    public function add_user_responses($userid = null) {
        global $USER;

        if (empty($this->questionnaire->id)) {
            return;
        }

        if ($userid === null) {
            $userid = $USER->id;
        }

        $responses = $this->get_responses($userid);
        foreach ($responses as $response) {
            $this->questionnaire->responses[$response->id] =
                \mod_questionnaire\local\responsetype\response\response::create_from_data($response);
        }
    }

    /**
     * Load the specified response into $questionnaire->responses.
     *
     * @param int $responseid
     */
    public function add_response(int $responseid) {
        global $DB;

        if (empty($this->questionnaire->id)) {
            return;
        }

        $response = $DB->get_record('questionnaire_response', ['id' => $responseid]);
        $this->questionnaire->responses[$response->id] =
            \mod_questionnaire\local\responsetype\response\response::create_from_data($response);
    }

    /**
     * Load the response information from a submitted web form.
     *
     * @param \stdClass $formdata
     */
    public function add_response_from_formdata(\stdClass $formdata) {
        $this->questionnaire->responses[0] =
            \mod_questionnaire\local\responsetype\response\response::response_from_webform(
                $formdata,
                $this->questionnaire->questions
            );
    }

    /**
     * Return a response object from a submitted mobile app form.
     *
     * @param \stdClass $appdata
     * @param int $sec
     * @return bool|\mod_questionnaire\local\responsetype\response\response
     */
    public function build_response_from_appdata(\stdClass $appdata, $sec = 0) {
        $questions = [];
        if ($sec == 0) {
            $questions = $this->questionnaire->questions;
        } else {
            foreach ($this->questionnaire->questionsbysec[$sec] as $questionid) {
                $questions[$questionid] = $this->questionnaire->questions[$questionid];
            }
        }
        return \mod_questionnaire\local\responsetype\response\response::response_from_appdata(
            $this->questionnaire->id,
            0,
            $appdata,
            $questions
        );
    }

    // Response querying.

    /**
     * Get the requested responses for this questionnaire.
     *
     * @param int|bool $userid
     * @param int $groupid
     * @return array
     */
    public function get_responses($userid = false, $groupid = 0) {
        global $DB;

        $params = [];
        $groupsql = '';
        $groupcnd = '';
        if ($groupid != 0) {
            $groupsql = 'INNER JOIN {groups_members} gm ON r.userid = gm.userid ';
            $groupcnd = ' AND gm.groupid = :groupid ';
            $params['groupid'] = $groupid;
        }

        if ($this->questionnaire->survey_is_public_master()) {
            $sql = 'SELECT r.* ' .
                'FROM {questionnaire_response} r ' .
                'INNER JOIN {questionnaire} q ON r.questionnaireid = q.id ' .
                'INNER JOIN {questionnaire_survey} s ON q.sid = s.id ' .
                $groupsql .
                'WHERE s.id = :surveyid AND r.complete = :status' . $groupcnd;
            $params['surveyid'] = $this->questionnaire->sid;
            $params['status'] = 'y';
        } else {
            $sql = 'SELECT r.* ' .
                'FROM {questionnaire_response} r ' .
                $groupsql .
                'WHERE r.questionnaireid = :questionnaireid' . $groupcnd;
            $params['questionnaireid'] = $this->questionnaire->id;
        }
        if ($userid) {
            $sql .= ' AND r.userid = :userid';
            $params['userid'] = $userid;
        }

        $status = optional_param('responsestats', '0', PARAM_ALPHANUM);
        if ($status) {
            $status = ($status === 'n') ? 'n' : 'y';
            $sql .= ' AND r.complete = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.id';
        return $DB->get_records_sql($sql, $params) ?? [];
    }

    /**
     * True if the specified user has a saved (incomplete) response.
     * @param int $userid
     * @return bool
     */
    public function user_has_saved_response($userid) {
        global $DB;

        return $DB->record_exists(
            'questionnaire_response',
            ['questionnaireid' => $this->questionnaire->id, 'userid' => $userid, 'complete' => 'n']
        );
    }

    /**
     * Get all survey responses in one go (UNION ALL across all question types).
     *
     * @param string $rid
     * @param string $userid
     * @param bool $groupid
     * @param int $showincompletes
     * @return array
     */
    public function get_survey_all_responses($rid = '', $userid = '', $groupid = false, $showincompletes = 0) {
        global $DB;
        $uniquetypes = $this->survey_questiontypes(true);
        $allresponsessql = "";
        $allresponsesparams = [];

        if ($this->questionnaire->survey_is_public_master()) {
            $qids = array_keys($DB->get_records('questionnaire', ['sid' => $this->questionnaire->sid], 'id') ?? []);
        } else {
            $qids = $this->questionnaire->id;
        }

        foreach ($uniquetypes as $type) {
            $question = \mod_questionnaire\local\question\question::question_builder($type);
            if (!isset($question->responsetype)) {
                continue;
            }
            $allresponsessql .= $allresponsessql == '' ? '' : ' UNION ALL ';
            [$sql, $params] = $question->responsetype->get_bulk_sql($qids, $rid, $userid, $groupid, $showincompletes);
            $allresponsesparams = array_merge($allresponsesparams, $params);
            $allresponsessql .= $sql;
        }

        if (empty($allresponsessql)) {
            return [];
        }
        $allresponsessql .= " ORDER BY usrid, id";
        $allresponses = $DB->get_recordset_sql($allresponsessql, $allresponsesparams);
        return $allresponses ?? [];
    }

    /**
     * Get the answers for all response types for a given response id.
     * @param int $rid
     * @return array
     */
    public function response_select($rid) {
        $values = \mod_questionnaire\local\responsetype\boolean::response_select($rid);
        $values += \mod_questionnaire\local\responsetype\single::response_select($rid);
        $values += \mod_questionnaire\local\responsetype\multiple::response_select($rid);
        $values += \mod_questionnaire\local\responsetype\rank::response_select($rid);
        $values += \mod_questionnaire\local\responsetype\text::response_select($rid);
        $values += \mod_questionnaire\local\responsetype\date::response_select($rid);
        return $values;
    }

    /**
     * Construct the response data for a given response and return a structured export.
     * @param int $rid
     * @return array
     */
    public function get_structured_response($rid) {
        $this->add_response($rid);
        return $this->get_full_submission_for_export($rid);
    }

    /**
     * Return an array containing all the questions and answers for a specific submission.
     * @param int $rid
     * @return array
     */
    public function get_full_submission_for_export($rid) {
        if (!isset($this->questionnaire->responses[$rid])) {
            $this->add_response($rid);
        }

        $exportstructure = [];
        foreach ($this->questionnaire->questions as $question) {
            $rqid = 'q' . $question->id;
            $response = new \stdClass();
            $response->questionname = $question->position . '. ' . $question->name;
            $response->questiontext = $question->content;
            $response->answers = [];
            if ($question->typeid == 8) {
                $choices = [];
                $cids = [];
                foreach ($question->choices as $cid => $choice) {
                    if (!empty($choice->value) && (strpos($choice->content, '=') !== false)) {
                        $choices[$choice->value] = substr($choice->content, (strpos($choice->content, '=') + 1));
                    } else {
                        $cids[$rqid . '_' . $cid] = $choice->content;
                    }
                }
                if (isset($this->questionnaire->responses[$rid]->answers[$question->id])) {
                    foreach ($cids as $rqid => $choice) {
                        $cid = substr($rqid, (strpos($rqid, '_') + 1));
                        if (isset($this->questionnaire->responses[$rid]->answers[$question->id][$cid])) {
                            if (
                                isset($question->choices[$cid]) &&
                                isset($choices[$this->questionnaire->responses[$rid]->answers[$question->id][$cid]->value])
                            ) {
                                $rating = $choices[$this->questionnaire->responses[$rid]->answers[$question->id][$cid]->value];
                            } else {
                                $rating = $this->questionnaire->responses[$rid]->answers[$question->id][$cid]->value;
                            }
                            $response->answers[] = $question->choices[$cid]->content . ' = ' . $rating;
                        }
                    }
                }
            } else if ($question->has_choices()) {
                $answertext = '';
                if (isset($this->questionnaire->responses[$rid]->answers[$question->id])) {
                    $i = 0;
                    foreach ($this->questionnaire->responses[$rid]->answers[$question->id] as $answer) {
                        if ($i > 0) {
                            $answertext .= '; ';
                        }
                        if ($question->choices[$answer->choiceid]->is_other_choice()) {
                            $answertext .= $answer->value;
                        } else {
                            $answertext .= $question->choices[$answer->choiceid]->content;
                        }
                        $i++;
                    }
                }
                $response->answers[] = $answertext;
            } else if (isset($this->questionnaire->responses[$rid]->answers[$question->id])) {
                $response->answers[] = $this->questionnaire->responses[$rid]->answers[$question->id][0]->value;
            }
            $exportstructure[] = $response;
        }

        return $exportstructure;
    }

    // Response saving / committing.

    /**
     * Insert the provided response.
     * @param object $responsedata
     * @param int $userid
     * @param bool $resume
     * @return bool|int
     */
    public function response_insert($responsedata, $userid, $resume = false) {
        global $DB;

        $record = new \stdClass();
        $record->submitted = time();

        if (empty($responsedata->rid)) {
            $record->questionnaireid = $this->questionnaire->id;
            $record->userid = $userid;
            $responsedata->rid = $DB->insert_record('questionnaire_response', $record);
            $responsedata->id = $responsedata->rid;
        } else {
            $record->id = $responsedata->rid;
            $DB->update_record('questionnaire_response', $record);
        }
        if ($resume) {
            $context = \context_module::instance($this->questionnaire->cm->id);
            $anonymous = $this->questionnaire->respondenttype == 'anonymous';
            $params = [
                'context' => $context,
                'courseid' => $this->questionnaire->course->id,
                'relateduserid' => $userid,
                'anonymous' => $anonymous,
                'other' => ['questionnaireid' => $this->questionnaire->id],
            ];
            $event = \mod_questionnaire\event\attempt_saved::create($params);
            $event->trigger();
        }

        if (!isset($responsedata->sec)) {
            $responsedata->sec = 1;
        }
        if (!empty($this->questionnaire->questionsbysec[$responsedata->sec])) {
            foreach ($this->questionnaire->questionsbysec[$responsedata->sec] as $questionid) {
                $this->questionnaire->questions[$questionid]->insert_response($responsedata);
            }
        }
        return $responsedata->rid;
    }

    /**
     * Commit the specified response (mark it complete and record submission time).
     * @param int $rid
     * @return bool
     */
    public function response_commit($rid) {
        global $DB;

        $record = new \stdClass();
        $record->id = $rid;
        $record->complete = 'y';
        $record->submitted = time();

        if ($this->questionnaire->grade < 0) {
            $record->grade = 1;
        } else {
            $record->grade = $this->questionnaire->grade;
        }
        return $DB->update_record('questionnaire_response', $record);
    }

    /**
     * Delete the specified response and insert a new one.
     * @param int $rid
     * @param int $sec
     * @param int $quser
     * @return bool|int
     */
    public function delete_insert_response($rid, $sec, $quser) {
        $this->response_delete($rid, $sec);
        $this->questionnaire->rid = $this->response_insert((object)['sec' => $sec, 'rid' => $rid], $quser);
        return $this->questionnaire->rid;
    }

    /**
     * Commit the response and update grades and completion state.
     * @param int $rid
     * @param int $quser
     */
    public function commit_submission_response($rid, $quser) {
        $this->response_commit($rid);
        if (!empty($rid) && is_numeric($rid)) {
            $rid = $rid;
        } else {
            $rid = $this->questionnaire->rid;
        }

        $this->questionnaire->update_grades($quser);

        $completion = new \completion_info($this->questionnaire->course);
        if ($completion->is_enabled($this->questionnaire->cm) && $this->questionnaire->completionsubmit) {
            $completion->update_state($this->questionnaire->cm, COMPLETION_COMPLETE);
        }
        $context = \context_module::instance($this->questionnaire->cm->id);
        $anonymous = $this->questionnaire->respondenttype == 'anonymous';
        $params = [
            'context' => $context,
            'courseid' => $this->questionnaire->course->id,
            'relateduserid' => $quser,
            'anonymous' => $anonymous,
            'other' => ['questionnaireid' => $this->questionnaire->id],
        ];
        $event = \mod_questionnaire\event\attempt_submitted::create($params);
        $event->trigger();
    }

    // Response deletion.

    /**
     * Delete the specified response's answer data.
     * @param int $rid
     * @param int|null $sec If provided, only delete answers in that section.
     */
    public function response_delete($rid, $sec = null) {
        global $DB;

        if (empty($rid)) {
            return;
        }

        if ($sec != null) {
            if ($sec < 1) {
                return;
            }

            $numsections = isset($this->questionnaire->questionsbysec) ? count($this->questionnaire->questionsbysec) : 0;
            $sec = min($numsections, $sec);

            $qids = [];
            foreach ($this->questionnaire->questionsbysec[$sec] as $questionid) {
                $qids[] = $questionid;
            }
            if (empty($qids)) {
                return;
            } else {
                [$qsql, $params] = $DB->get_in_or_equal($qids);
                $qsql = ' AND questionid ' . $qsql;
            }
        } else {
            $qsql = '';
            $params = [];
        }

        $select = 'responseid = \'' . $rid . '\' ' . $qsql;
        foreach (
            [
                'response_bool',
                'resp_single',
                'resp_multiple',
                'response_rank',
                'response_text',
                'response_other',
                'response_date',
            ] as $tbl
        ) {
            $DB->delete_records_select('questionnaire_' . $tbl, $select, $params);
        }
    }

    // Response validation.

    /**
     * Check that the response is complete and correctly formatted.
     * @param int $section
     * @param object $formdata
     * @param bool $checkmissing
     * @param bool $checkwrongformat
     * @return string Error message, or empty string if valid.
     */
    public function response_check_format($section, $formdata, $checkmissing = true, $checkwrongformat = true) {
        $missing = 0;
        $strmissing = '';
        $wrongformat = 0;
        $strwrongformat = '';
        $i = 1;
        for ($j = 2; $j <= $section; $j++) {
            foreach ($this->questionnaire->questionsbysec[$j - 1] as $questionid) {
                $tid = $this->questionnaire->questions[$questionid]->typeid;
                if ($tid < QUESPAGEBREAK) {
                    $i++;
                }
            }
        }
        $qnum = $i - 1;

        if (key_exists($section, $this->questionnaire->questionsbysec)) {
            foreach ($this->questionnaire->questionsbysec[$section] as $questionid) {
                if ($this->questionnaire->questions[$questionid]->is_numbered()) {
                    $qnum++;
                }
                if (!$this->questionnaire->questions[$questionid]->response_complete($formdata)) {
                    $missing++;
                    $strnum = get_string('num', 'questionnaire') . $qnum . '. ';
                    $strmissing .= $strnum;
                    $strnoti = get_string('missingquestion', 'questionnaire') . $strnum;
                    $this->questionnaire->questions[$questionid]->add_notification($strnoti);
                }
                if (!$this->questionnaire->questions[$questionid]->response_valid($formdata)) {
                    $wrongformat++;
                    $strwrongformat .= get_string('num', 'questionnaire') . $qnum . '. ';
                }
            }
        }
        $message = '';
        $nonumbering = false;
        if (!$this->questionnaire->questions_autonumbered()) {
            $nonumbering = true;
        }
        if ($checkmissing && $missing) {
            if ($nonumbering) {
                $strmissing = '';
            }
            if ($missing == 1) {
                $message = get_string('missingquestion', 'questionnaire') . $strmissing;
            } else {
                $message = get_string('missingquestions', 'questionnaire') . $strmissing;
            }
            if ($wrongformat) {
                $message .= '<br />';
            }
        }
        if ($checkwrongformat && $wrongformat) {
            if ($nonumbering) {
                $message .= get_string('wronganswers', 'questionnaire');
            } else {
                if ($wrongformat == 1) {
                    $message .= get_string('wrongformat', 'questionnaire') . $strwrongformat;
                } else {
                    $message .= get_string('wrongformats', 'questionnaire') . $strwrongformat;
                }
            }
        }
        return $message;
    }

    // Static helpers — usable without a questionnaire instance.

    /**
     * Get all responses for a given questionnaire instance id and user, without needing an instance.
     * @param int $instanceid The questionnaire.id value.
     * @param int $userid
     * @param bool $complete True = only complete responses, false = all responses.
     * @return array
     */
    public static function get_user_responses_for_instance(int $instanceid, int $userid, bool $complete = true): array {
        global $DB;
        $andcomplete = $complete ? " AND complete = 'y' " : '';
        return $DB->get_records_sql(
            "SELECT * FROM {questionnaire_response}
              WHERE questionnaireid = ?
                AND userid = ?
             " . $andcomplete . "
             ORDER BY submitted ASC",
            [$instanceid, $userid]
        ) ?? [];
    }

    /**
     * Delete all response data for a given question id, without needing a questionnaire instance.
     * @param int $qid
     * @return bool
     */
    public static function delete_responses_for_question(int $qid): bool {
        global $DB;
        $DB->delete_records('questionnaire_response_bool', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_date', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_resp_multiple', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_other', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_rank', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_resp_single', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_text', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_file', ['questionid' => $qid]);
        return true;
    }

    // Functions migrated from locallib.php.

    /**
     * Get all responses for the given user and questionnaire.
     * @param int $userid
     * @param bool $complete True = only complete responses, false = all responses.
     * @return array
     */
    public function get_user_responses($userid, $complete = true) {
        global $DB;
        $andcomplete = '';
        if ($complete) {
            $andcomplete = " AND complete = 'y' ";
        }
        return $DB->get_records_sql("SELECT *
            FROM {questionnaire_response}
            WHERE questionnaireid = ?
            AND userid = ?
            " . $andcomplete . "
            ORDER BY submitted ASC ", [$this->questionnaire->id, $userid]) ?? [];
    }

    /**
     * Delete a single response and update completion state.
     * @param \stdClass $response The response record (must have ->id and ->userid).
     * @return bool
     */
    public function delete_response(\stdClass $response) {
        global $DB;
        $status = true;
        $rid = $response->id;

        $DB->delete_records('questionnaire_response_bool', ['responseid' => $rid]);
        $DB->delete_records('questionnaire_response_date', ['responseid' => $rid]);
        $DB->delete_records('questionnaire_resp_multiple', ['responseid' => $rid]);
        $DB->delete_records('questionnaire_response_other', ['responseid' => $rid]);
        $DB->delete_records('questionnaire_response_rank', ['responseid' => $rid]);
        $DB->delete_records('questionnaire_resp_single', ['responseid' => $rid]);
        $DB->delete_records('questionnaire_response_text', ['responseid' => $rid]);
        $DB->delete_records('questionnaire_response_file', ['responseid' => $rid]);

        $status = $status && $DB->delete_records('questionnaire_response', ['id' => $rid]);

        if ($status && !empty($this->questionnaire->cm)) {
            $completion = new \completion_info($this->questionnaire->course);
            if (
                $completion->is_enabled($this->questionnaire->cm) == COMPLETION_TRACKING_AUTOMATIC &&
                $this->questionnaire->completionsubmit
            ) {
                $completion->update_state($this->questionnaire->cm, COMPLETION_INCOMPLETE, $response->userid);
            }
        }

        return $status;
    }

    /**
     * Delete all response data for a given question id.
     * @param int $qid
     * @return bool
     */
    public function delete_responses($qid) {
        global $DB;

        $DB->delete_records('questionnaire_response_bool', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_date', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_resp_multiple', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_other', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_rank', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_resp_single', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_text', ['questionid' => $qid]);
        $DB->delete_records('questionnaire_response_file', ['questionid' => $qid]);

        return true;
    }

    // Private helpers.

    /**
     * Return the unique question type IDs (and optionally deduplicated by response table)
     * for the questions in this questionnaire.
     *
     * @param bool $uniquebytable
     * @return array
     */
    private function survey_questiontypes($uniquebytable = false) {
        $uniquetypes = [];
        $uniquetables = [];

        foreach ($this->questionnaire->questions as $question) {
            $type = $question->typeid;
            $responsetable = $question->responsetable;
            if (!$uniquebytable || !in_array($responsetable, $uniquetables)) {
                if (!in_array($type, $uniquetypes)) {
                    $uniquetypes[] = $type;
                }
                if (!in_array($responsetable, $uniquetables)) {
                    $uniquetables[] = $responsetable;
                }
            }
        }

        return $uniquetypes;
    }
}
