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

use mod_questionnaire\db\bulk_sql_config;
use moodle_url;

/**
 * Class for text response types.
 *
 * @author Laurent David
 * @author Martin Cornu-Mansuy
 * @copyright 2023 onward CALL Learning <martin@call-learning.fr>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questionnaire
 */
class file extends responsetype {
    /**
     * Provide an array of answer objects from web form data for the question.
     *
     * The posted value is the current user's draft item id. Only that user's own draft area is read, and nothing is
     * saved here; the answer value is the id of the uploaded draft file, which insert_response() stores.
     *
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param \mod_questionnaire\local\question\question $question
     * @return array \mod_questionnaire\local\response\answer\answer An array of answer objects.
     */
    public static function answers_from_webform($responsedata, $question) {
        $answers = [];
        $draftfile = self::draft_file((int)($responsedata->{'q' . $question->id()} ?? 0));
        if ($draftfile !== null) {
            $record = new \stdClass();
            $record->responseid = $responsedata->rid;
            $record->questionid = $question->id();
            $record->value = $draftfile->get_id();
            $answers[] = answer\answer::create_from_data($record);
        }
        return $answers;
    }

    /**
     * Return the uploaded file in the current user's draft area with the given item id, if there is one.
     *
     * @param int $draftitemid
     * @return \stored_file|null
     */
    protected static function draft_file(int $draftitemid): ?\stored_file {
        global $USER;
        if ($draftitemid <= 0) {
            return null;
        }
        $files = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'itemid, filepath, filename',
            false
        );
        return empty($files) ? null : reset($files);
    }

    /**
     * Return an array of answers by question/choice for the given response. Must be implemented by the subclass.
     *
     * @param int $rid The response id.
     * @return array
     */
    public static function response_select($rid) {
        global $DB;

        $values = [];
        $sql = 'SELECT q.id, q.content, a.fileid as aresponse ' .
            'FROM {' . static::response_table() . '} a, {questionnaire_question} q ' .
            'WHERE a.responseid=? AND a.questionid=q.id ';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $qid => $row) {
            unset($row->id);
            $row = (array) $row;
            $newrow = [];
            foreach ($row as $key => $val) {
                if (!is_numeric($key)) {
                    $newrow[] = $val;
                }
            }
            $values[$qid] = $newrow;
            $val = array_pop($values[$qid]);
            array_push($values[$qid], $val, $val);
        }

        return $values;
    }

    /**
     * Return an array of answer objects by question for the given response id.
     * THIS SHOULD REPLACE response_select.
     *
     * @param int $rid The response id.
     * @return array array answer
     * @throws \dml_exception
     */
    public static function response_answers_by_question($rid) {
        global $DB;

        $answers = [];
        $sql = 'SELECT id, responseid as responseid, questionid as questionid, 0 as choiceid, fileid as value ' .
            'FROM {' . static::response_table() . '} ' .
            'WHERE responseid = ? ';
        $records = $DB->get_records_sql($sql, [$rid]);
        foreach ($records as $record) {
            $answers[$record->questionid][] = answer\answer::create_from_data($record);
        }

        return $answers;
    }

    /**
     * Delete old entries from the questionnaire_response_file table and also their stored files.
     *
     * @param int $questionid
     * @param int $responseid
     * @param int $contextid The questionnaire's module context id.
     * @return void
     */
    public static function delete_old_response(int $questionid, int $responseid, int $contextid) {
        $fs = get_file_storage();
        $existing = \mod_questionnaire\local\db\response_file_record::get_records([
            'responseid' => $responseid,
            'questionid' => $questionid,
        ]);
        foreach ($existing as $rec) {
            $fs->delete_area_files($contextid, 'mod_questionnaire', 'response_file', $rec->get('id'));
            $rec->delete();
        }
    }

    /**
     * Insert a provided response to the question.
     *
     * The uploaded draft file is saved under the id of the new questionnaire_response_file record. A posted
     * file field with no uploaded file removes any earlier file answer.
     *
     * @param \mod_questionnaire\local\response\response|\stdClass $responsedata
     * @return bool|int
     */
    public function insert_response($responsedata) {
        $qid = (int)$this->question->id();
        if (!$responsedata instanceof \mod_questionnaire\local\response\response) {
            $response = \mod_questionnaire\local\response\response::response_from_webform(
                $responsedata,
                [$this->question]
            );
            $posted = isset($responsedata->{'q' . $qid});
        } else {
            $response = $responsedata;
            $posted = false;
        }
        if (empty($response)) {
            return false;
        }

        $contextid = $this->question->context->id;
        $answer = $response->answers[$qid][0] ?? null;
        $draftfile = $answer ? get_file_storage()->get_file_by_id((int)$answer->value) : false;
        if (!$draftfile || !self::draft_file($draftfile->get_itemid())) {
            if ($posted) {
                // The user removed their file.
                self::delete_old_response($qid, (int)$response->id(), $contextid);
            }
            return false;
        }

        // Replace any previous attempt.
        self::delete_old_response($qid, (int)$response->id(), $contextid);

        $rec = new \mod_questionnaire\local\db\response_file_record();
        $rec->set('responseid', $response->id());
        $rec->set('questionid', $qid);
        $rec->create();
        $recordid = (int)$rec->get('id');

        file_save_draft_area_files(
            $draftfile->get_itemid(),
            $contextid,
            'mod_questionnaire',
            'response_file',
            $recordid,
            \mod_questionnaire\local\question\file::get_file_manager_option()
        );
        $files = get_file_storage()->get_area_files($contextid, 'mod_questionnaire', 'response_file', $recordid, 'id', false);
        if (empty($files)) {
            $rec->delete();
            return false;
        }
        $rec->set('fileid', reset($files)->get_id());
        $rec->update();
        return $recordid;
    }

    /**
     * Provide the necessary response data table name. Should probably always be used with late static binding 'static::' form
     * rather than 'self::' form to allow for class extending.
     *
     * @return string response table name.
     */
    public static function response_table() {
        return 'questionnaire_response_file';
    }

    /**
     * Provide a template for results screen if defined.
     *
     * @param bool $pdf
     * @return mixed The template string or false/
     */
    public function results_template($pdf = false) {
        if ($pdf) {
            return 'mod_questionnaire/resultspdf_text';
        } else {
            return 'mod_questionnaire/results_text';
        }
    }

    /**
     * Provide the result information for the specified result records.
     *
     * @param int|array $rids - A single response id, or array.
     * @param string $sort - Optional display sort.
     * @param boolean $anonymous - Whether or not responses are anonymous.
     * @param int|null $currentgroupid Active group filter id for staff respondent-link URLs.
     * @return string - Display output.
     */
    public function display_results($rids = false, $sort = '', $anonymous = false, ?int $currentgroupid = null) {
        if (is_array($rids)) {
            $prtotal = 1;
        } else if (is_int($rids)) {
            $prtotal = 0;
        }
        if ($rows = $this->get_results($rids, $anonymous)) {
            $numrespondents = count($rids);
            $numresponses = count($rows);
            $pagetags = $this->get_results_tags($rows, $numrespondents, $numresponses, $prtotal, '', $currentgroupid);
        } else {
            $pagetags = "";
        }
        return $pagetags;
    }

    /**
     * Provide the result information for the specified result records.
     *
     * @param int|array $rids - A single response id, or array.
     * @param boolean $anonymous - Whether or not responses are anonymous.
     * @return array - Array of data records.
     */
    public function get_results($rids = false, $anonymous = false) {
        global $DB;

        $rsql = '';
        $params = [$this->question->id()];
        if (!empty($rids)) {
            [$rsql, $rparams] = $DB->get_in_or_equal($rids);
            $rsql = ' AND responseid ' . $rsql;
            $params = array_merge($params, $rparams);
        }

        if ($anonymous) {
            $sql = 'SELECT t.id, t.fileid, r.submitted AS submitted, ' .
                'r.questionnaireid, r.id AS rid ' .
                'FROM {' . static::response_table() . '} t, ' .
                '{questionnaire_response} r ' .
                'WHERE questionid = ?' . $rsql .
                ' AND t.responseid = r.id ' .
                'ORDER BY r.submitted DESC';
        } else {
            $sql = 'SELECT t.id, t.fileid, r.submitted AS submitted, r.userid, u.username AS username, ' .
                'u.id as usrid, ' .
                'r.questionnaireid, r.id AS rid ' .
                'FROM {' . static::response_table() . '} t, ' .
                '{questionnaire_response} r, ' .
                '{user} u ' .
                'WHERE questionid = ?' . $rsql .
                ' AND t.responseid = r.id' .
                ' AND u.id = r.userid ' .
                'ORDER BY u.lastname, u.firstname, r.submitted';
        }
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Override the results tags function for templates for questions with dates.
     *
     * @param array $weights
     * @param int $participants Number of questionnaire participants.
     * @param int $respondents Number of question respondents.
     * @param bool $showtotals
     * @param string $sort
     * @param int|null $currentgroupid Active group filter id for staff respondent-link URLs.
     * @return \stdClass
     */
    public function get_results_tags(
        $weights,
        $participants,
        $respondents,
        $showtotals = 1,
        $sort = '',
        ?int $currentgroupid = null
    ) {
        $pagetags = new \stdClass();
        if ($respondents == 0) {
            return $pagetags;
        }

        // If array element is an object, outputting non-numeric responses.
        if (is_object(reset($weights))) {
            global $CFG, $DB;
            $viewsingleresponse = $this->canviewsingleresponse;
            $nonanonymous = $this->displayrespondenttype !== 'anonymous';
            if ($viewsingleresponse && $nonanonymous) {
                $url = $CFG->wwwroot . '/mod/questionnaire/report.php?action=vresp&amp;sid=' . $this->displaysurveysid .
                    '&currentgroupid=' . (int) $currentgroupid;
            }
            $users = [];
            $evencolor = false;
            foreach ($weights as $row) {
                $response = new \stdClass();
                $fs = get_file_storage();
                $file = $fs->get_file_by_id($row->fileid);

                if ($file) {
                    // There is a file.
                    $imageurl = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    );

                    $response->text = \html_writer::link($imageurl, $file->get_filename());
                    if ($viewsingleresponse && $nonanonymous) {
                        $rurl = $url . '&amp;rid=' . $row->rid . '&amp;individualresponse=1';
                        $title = userdate($row->submitted);
                        if (!isset($users[$row->userid])) {
                            $users[$row->userid] = $DB->get_record('user', ['id' => $row->userid]);
                        }
                        $response->respondent =
                            '<a href="' . $rurl . '" title="' . $title . '">' . fullname($users[$row->userid]) . '</a>';
                    }
                } else {
                    $response->respondent = '';
                }
                // The 'evencolor' attribute is used by the PDF template.
                $response->evencolor = $evencolor;
                $pagetags->responses[] = (object) ['response' => $response];
                $evencolor = !$evencolor;
            }

            if ($showtotals == 1) {
                $pagetags->total = new \stdClass();
                $pagetags->total->total = "$respondents/$participants";
            }
        } else {
            $nbresponses = 0;
            $sum = 0;
            $strtotal = get_string('totalofnumbers', 'questionnaire');
            $straverage = get_string('average', 'questionnaire');

            if (!empty($weights) && is_array($weights)) {
                ksort($weights);
                $evencolor = false;
                foreach ($weights as $text => $num) {
                    $response = new \stdClass();
                    $response->text = $text;
                    $response->respondent = $num;
                    // The 'evencolor' attribute is used by the PDF template.
                    $response->evencolor = $evencolor;
                    $nbresponses += $num;
                    $sum += $text * $num;
                    $evencolor = !$evencolor;
                    $pagetags->responses[] = (object) ['response' => $response];
                }

                $response = new \stdClass();
                $response->text = $sum;
                $response->respondent = $strtotal;
                $response->evencolor = $evencolor;
                $pagetags->responses[] = (object) ['response' => $response];
                $evencolor = !$evencolor;

                $response = new \stdClass();
                $response->respondent = $straverage;
                $avg = $sum / $nbresponses;
                $response->text = sprintf('%.' . $this->question->precise() . 'f', $avg);
                $response->evencolor = $evencolor;
                $pagetags->responses[] = (object) ['response' => $response];
                $evencolor = !$evencolor;

                if ($showtotals == 1) {
                    $pagetags->total = new \stdClass();
                    $pagetags->total->total = "$respondents/$participants";
                    $pagetags->total->evencolor = $evencolor;
                }
            }
        }

        return $pagetags;
    }

    /**
     * Configure bulk sql
     *
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config(static::response_table(), 'qrt', false, false, false);
    }
}
