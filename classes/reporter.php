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

use mod_questionnaire\local\question\choice;
use mod_questionnaire\local\question\question;
use mod_questionnaire\local\question\rate;
use stdClass;

/**
 * Reporting and data-export operations for a questionnaire.
 *
 * Owns all logic for generating CSV exports, response analysis, and result
 * display that was previously spread across the legacy questionnaire class.
 * Receives a questionnaire instance at construction and reads survey, question,
 * context, and response data through it.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class reporter {
    /** @var questionnaire The questionnaire being reported on. */
    private questionnaire $questionnaire;

    /**
     * Construct a reporter for the given questionnaire.
     *
     * @param questionnaire $questionnaire The questionnaire being reported on.
     */
    public function __construct(questionnaire $questionnaire) {
        $this->questionnaire = $questionnaire;
    }

    /**
     * Generate CSV export data for all (or filtered) responses.
     *
     * Returns a 2-D array where row 0 is the column header row and subsequent
     * rows are individual response data rows.
     *
     * @param int $currentgroupid Group id filter (0 = all groups).
     * @param string $rid Response id filter ('' = all).
     * @param int|string $userid User id filter ('' = all).
     * @param int|null $choicecodes Include numeric choice codes in output.
     * @param int $choicetext Include choice text in output.
     * @param int $showincompletes Include incomplete responses.
     * @param int $rankaverages Append a row of rank-question averages.
     * @return array Rows of CSV data; row 0 is the header row.
     */
    public function generate_csv(
        int $currentgroupid = 0,
        string $rid = '',
        $userid = '',
        ?int $choicecodes = null,
        int $choicetext = 1,
        int $showincompletes = 0,
        int $rankaverages = 0
    ): array {
        global $DB;

        raise_memory_limit('1G');

        $questions = $this->questionnaire->questions();
        $output = [];
        $stringother = get_string('other', 'questionnaire');

        $config = get_config('questionnaire', 'downloadoptions');
        $options = empty($config) ? [] : explode(',', $config);
        if ($showincompletes == 1) {
            $options[] = 'complete';
        }
        $columns = [];
        $types = [];
        foreach ($options as $option) {
            if (in_array($option, ['response', 'submitted', 'id', 'useridnumber'])) {
                $columns[] = get_string($option, 'questionnaire');
                $types[] = 0;
            } else if ($option == 'useridentityfields') {
                // Ignore option.
                continue;
            } else {
                $columns[] = get_string($option);
                $types[] = 1;
            }
        }
        $identityfields = $this->get_identity_fields($options);
        foreach ($identityfields as $field) {
            $columns[] = \core_user\fields::get_display_name($field);
        }
        $nbinfocols = count($columns);

        $idtocsvmap = [
            '0', // 0: unused
            '0', // 1: bool -> boolean
            '1', // 2: text -> string
            '1', // 3: essay -> string
            '0', // 4: radio -> string
            '0', // 5: check -> string
            '0', // 6: dropdn -> string
            '0', // 7: rating -> number
            '0', // 8: rate -> number
            '1', // 9: date -> string
            '0', // 10: numeric -> number.
            '0', // 11: slider -> number.
        ];

        $sid = $this->questionnaire->surveyid();
        if (!$DB->get_record('questionnaire_survey', ['id' => $sid])) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        }

        // Get all responses for this survey in one go.
        $allresponsesrs = $this->questionnaire->responses()->get_survey_all_responses(
            $rid,
            $userid,
            $currentgroupid,
            $showincompletes
        );

        // Do we have any questions of type RADIO, DROP, CHECKBOX OR RATE?
        $choicetypes = question::typeids_with_choices();

        // Get unique list of question types used in this survey.
        $uniquetypes = $this->get_survey_questiontypes();

        if (count(array_intersect($choicetypes, $uniquetypes)) > 0) {
            $choiceparams = [$sid];
            $choicesql = "
                SELECT DISTINCT c.id as cid, q.id as qid, q.precise AS precise, q.name, c.content
                  FROM {questionnaire_question} q
                  JOIN {questionnaire_quest_choice} c ON questionid = q.id
                 WHERE q.surveyid = ? ORDER BY cid ASC
            ";
            $choicerecords = $DB->get_records_sql($choicesql, $choiceparams);
            $choicesbyqid = [];
            if (!empty($choicerecords)) {
                foreach ($choicerecords as $choicerecord) {
                    if (!isset($choicesbyqid[$choicerecord->qid])) {
                        $choicesbyqid[$choicerecord->qid] = [];
                    }
                    $choicesbyqid[$choicerecord->qid][$choicerecord->cid] = $choicerecord;
                }
            }
        }

        $num = 1;

        $questionidcols = [];

        foreach ($questions as $question) {
            // Skip questions that aren't response capable.
            if (!isset($question->responsetype)) {
                continue;
            }
            $qid = $question->id();
            $qpos = $question->position();
            $col = $question->name();
            $type = $question->typeid();
            if (in_array($type, $choicetypes)) {
                if (!isset($choicesbyqid[$qid])) {
                    throw new \coding_exception('Choice question has no choices!', 'question id ' . $qid . ' of type ' . $type);
                }
                $choices = $choicesbyqid[$qid];

                switch ($type) {
                    case QUESRADIO:
                    case QUESDROP:
                        $columns[][$qpos] = $col;
                        $questionidcols[][$qpos] = $qid;
                        array_push($types, $idtocsvmap[$type]);
                        $thisnum = 1;
                        foreach ($choices as $choicerecord) {
                            $content = $choicerecord->content;
                            if (choice::content_is_other_choice($content)) {
                                $col = $choicerecord->name . '_' . $stringother;
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = null;
                                array_push($types, '0');
                            }
                        }
                        break;

                    case QUESCHECK:
                        $thisnum = 1;
                        foreach ($choices as $choicerecord) {
                            $content = $choicerecord->content;
                            $modality = '';
                            $contents = question::parse_choice_content($content);
                            if ($contents->modname) {
                                $modality = $contents->modname;
                            } else if ($contents->title) {
                                $modality = $contents->title;
                            } else {
                                $modality = strip_tags($contents->text);
                            }
                            $col = $choicerecord->name . '->' . $modality;
                            $columns[][$qpos] = $col;
                            $questionidcols[][$qpos] = $qid . '_' . $choicerecord->cid;
                            array_push($types, '0');
                            if (choice::content_is_other_choice($content)) {
                                $content = $stringother;
                                $col = $choicerecord->name . '->[' . $content . ']';
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = null;
                                array_push($types, '0');
                            }
                        }
                        break;

                    case QUESRATE:
                        foreach ($choices as $choicerecord) {
                            $nameddegrees = 0;
                            $modality = '';
                            $content = $choicerecord->content;
                            $osgood = false;
                            if (rate::type_is_osgood_rate_scale($choicerecord->precise)) {
                                $osgood = true;
                            }
                            if (preg_match("/^[0-9]{1,3}=/", $content, $ndd)) {
                                $nameddegrees++;
                            } else {
                                if ($osgood) {
                                    [$contentleft, $contentright] = array_merge(preg_split('/[|]/', $content), [' ']);
                                    $contents = question::parse_choice_content($contentleft);
                                    if ($contents->title) {
                                        $contentleft = $contents->title;
                                    }
                                    $contents = question::parse_choice_content($contentright);
                                    if ($contents->title) {
                                        $contentright = $contents->title;
                                    }
                                    $modality = strip_tags($contentleft . '|' . $contentright);
                                    $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                } else {
                                    $contents = question::parse_choice_content($content);
                                    if ($contents->modname) {
                                        $modality = $contents->modname;
                                    } else if ($contents->title) {
                                        $modality = $contents->title;
                                    } else {
                                        $modality = strip_tags($contents->text);
                                        $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                    }
                                }
                                $col = $choicerecord->name . '->' . $modality;
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = $qid . '_' . $choicerecord->cid;
                                array_push($types, $idtocsvmap[$type]);
                            }
                        }
                        break;
                }
            } else {
                $columns[][$qpos] = $col;
                $questionidcols[][$qpos] = $qid;
                array_push($types, $idtocsvmap[$type]);
            }
            $num++;
        }

        array_push($output, $columns);
        $numrespcols = count($output[0]);

        // Flatten questionidcols.
        $tmparr = [];
        for ($c = 0; $c < $nbinfocols; $c++) {
            $tmparr[] = null;
        }
        foreach ($questionidcols as $i => $positions) {
            foreach ($positions as $position => $qid) {
                $tmparr[] = $qid;
            }
        }
        $questionidcols = $tmparr;

        // Create array of question positions hashed by question / question + choiceid.
        $questionpositions = [];
        $questionsbyposition = [];
        $p = 0;
        foreach ($questionidcols as $qid) {
            if ($qid === null) {
                $p++;
                continue;
            }
            $questionpositions[$qid] = $p;
            if (strpos($qid, '_') !== false) {
                $tmparr = explode('_', $qid);
                $questionid = $tmparr[0];
            } else {
                $questionid = $qid;
            }
            $questionsbyposition[$p] = $questions[$questionid];
            $p++;
        }

        $formatoptions = new stdClass();
        $formatoptions->filter = false;

        if ($rankaverages) {
            $averages = [];
            $rids = [];
            $allresponsesrs2 = $this->questionnaire->responses()->get_survey_all_responses($rid, $userid, $currentgroupid);
            foreach ($allresponsesrs2 as $responserow) {
                if (!isset($rids[$responserow->rid])) {
                    $rids[$responserow->rid] = $responserow->rid;
                }
            }
        }

        $prevresprow = false;
        $row = [];
        if ($rankaverages) {
            $averagerow = [];
        }
        $useridentityfields = [];
        foreach ($allresponsesrs as $responserow) {
            $rid = $responserow->rid;
            $qid = $responserow->questionid;

            // Ignore responses for deleted questions.
            if (!isset($questions[$qid])) {
                continue;
            }

            if (!empty($identityfields)) {
                if (isset($useridentityfields[$responserow->userid])) {
                    $customfields = $useridentityfields[$responserow->userid];
                } else {
                    $customfields = self::get_user_identity_fields($this->questionnaire->context(), $responserow->userid);
                    $useridentityfields[$responserow->userid] = $customfields;
                }
                foreach ($identityfields as $field) {
                    $responserow->{$field} = $customfields->{$field};
                }
            }

            $question = $questions[$qid];
            $qtype = intval($question->typeid());
            if ($rankaverages) {
                if ($qtype === QUESRATE) {
                    if (empty($averages[$qid])) {
                        $results = $questions[$qid]->responsetype->get_results($rids);
                        foreach ($results as $qresult) {
                            $averages[$qid][$qresult->id] = $qresult->average;
                        }
                    }
                }
            }
            $questionobj = $questions[$qid];

            if ($prevresprow !== false && $prevresprow->rid !== $rid) {
                $output[] = $this->process_csv_row(
                    $row,
                    $prevresprow,
                    $currentgroupid,
                    $questionsbyposition,
                    $nbinfocols,
                    $numrespcols,
                    $options,
                    $identityfields
                );
                $row = [];
            }

            if ($qtype === QUESRATE || $qtype === QUESCHECK) {
                $key = $qid . '_' . $responserow->choiceid;
                $position = $questionpositions[$key];
                if ($qtype === QUESRATE) {
                    $choicetxt = $responserow->rankvalue;
                    if ($rankaverages) {
                        $averagerow[$position] = $averages[$qid][$responserow->choiceid];
                    }
                } else {
                    $content = $choicesbyqid[$qid][$responserow->choiceid]->content;
                    if (choice::content_is_other_choice($content)) {
                        $row[$position + 1] = $responserow->response;
                        $choicetxt = empty($responserow->choiceid) ? '0' : '1';
                    } else if (!empty($responserow->choiceid)) {
                        $choicetxt = '1';
                    } else {
                        $choicetxt = '0';
                    }
                }
                $responsetxt = $choicetxt;
                $row[$position] = $responsetxt;
            } else {
                $position = $questionpositions[$qid];
                if ($questionobj->has_choices()) {
                    $c = 0;
                    if (in_array(intval($question->typeid()), $choicetypes)) {
                        $choices = $choicesbyqid[$qid];
                        foreach ($choices as $choicerecord) {
                            $c++;
                            if ($responserow->choiceid === $choicerecord->cid) {
                                break;
                            }
                        }
                    }

                    $content = $choicesbyqid[$qid][$responserow->choiceid]->content;
                    if (choice::content_is_other_choice($content)) {
                        $responsetxt = choice::content_other_choice_display($content);
                        $responsetxt1 = $responserow->response;
                    } else if (($choicecodes == 1) && ($choicetext == 1)) {
                        $responsetxt = $c . ' : ' . $content;
                    } else if ($choicecodes == 1) {
                        $responsetxt = $c;
                    } else {
                        $responsetxt = $content;
                    }
                } else if (intval($qtype) === QUESYESNO) {
                    $responsetxt = $responserow->response === 'y' ? "1" : "0";
                } else {
                    $responsetxt = $responserow->response;
                    if (!empty($responsetxt)) {
                        $responsetxt = strip_tags($responsetxt);
                        $responsetxt = preg_replace("/[\r\n\t]/", ' ', $responsetxt);
                    }
                }
                $row[$position] = $responsetxt;
                if (!empty($responsetxt1)) {
                    $responsetxt1 = preg_replace("/[\r\n\t]/", ' ', $responsetxt1);
                    $row[$position + 1] = $responsetxt1;
                    unset($responsetxt1);
                }
            }

            $prevresprow = $responserow;
        }

        if ($prevresprow !== false) {
            $output[] = $this->process_csv_row(
                $row,
                $prevresprow,
                $currentgroupid,
                $questionsbyposition,
                $nbinfocols,
                $numrespcols,
                $options,
                $identityfields
            );
        }

        if ($rankaverages) {
            $summaryrow = [];
            $summaryrow[0] = get_string('averagesrow', 'questionnaire');
            for ($i = 1; $i < $nbinfocols; $i++) {
                $summaryrow[$i] = '';
            }
            for ($i = $nbinfocols; $i < $numrespcols; $i++) {
                $summaryrow[$i] = isset($averagerow[$i]) ? $averagerow[$i] : '';
            }
            $output[] = $summaryrow;
        }

        // Rewrite column headers to include question numbers.
        $numquestion = 0;
        $oldkey = 0;
        for ($i = $nbinfocols; $i < $numrespcols; $i++) {
            $sep = '';
            $thisoutput = current($output[0][$i]);
            $thiskey = key($output[0][$i]);
            if (strstr($thisoutput, '->.')) {
                $thisoutput = str_replace('->.', '', $thisoutput);
            }
            if (
                $thisoutput == '' ||
                strstr($thisoutput, '->.') ||
                substr($thisoutput, 0, 2) == '->' ||
                substr($thisoutput, 0, 1) == '_'
            ) {
                $sep = '';
            } else {
                $sep = '_';
            }
            if ($thiskey > $oldkey) {
                $oldkey = $thiskey;
                $numquestion++;
            }
            $pos = strpos($thisoutput, '=');
            if ($pos) {
                $thisoutput = substr($thisoutput, 0, $pos);
            }
            $out = 'Q' . sprintf("%02d", $numquestion) . $sep . $thisoutput;
            $output[0][$i] = $out;
        }
        return $output;
    }

    // Private helpers.

    /**
     * Process one response row into a positioned CSV row array.
     *
     * @param array $row Sparse response data array keyed by column position.
     * @param stdClass $resprow Raw response resultset row.
     * @param int $currentgroupid Active group id (0 = all).
     * @param array $questionsbyposition Questions keyed by column position.
     * @param int $nbinfocols Number of leading info columns.
     * @param int $numrespcols Total number of columns.
     * @param array $options Enabled download option strings.
     * @param array $identityfields Identity field names.
     * @return array Flat positioned row for CSV output.
     */
    private function process_csv_row(
        array &$row,
        stdClass $resprow,
        int $currentgroupid,
        array &$questionsbyposition,
        int $nbinfocols,
        int $numrespcols,
        array $options,
        array $identityfields
    ): array {
        global $DB;

        static $anonumap = [];

        $positioned = [];
        $user = new stdClass();
        foreach ($this->user_fields() as $userfield) {
            $user->$userfield = $resprow->$userfield;
        }
        $user->id = $resprow->userid;
        $isanonymous = ($this->questionnaire->respondenttype() == 'anonymous');

        $course = $this->questionnaire->course();
        if (!$this->questionnaire->survey_is_public()) {
            $courseid = $course->id;
            $coursename = $course->fullname;
        } else {
            $sql = 'SELECT q.id, q.course, c.fullname ' .
                   'FROM {questionnaire_response} qr ' .
                   'INNER JOIN {questionnaire} q ON qr.questionnaireid = q.id ' .
                   'INNER JOIN {course} c ON q.course = c.id ' .
                   'WHERE qr.id = ? AND qr.complete = ? ';
            if ($record = $DB->get_record_sql($sql, [$resprow->rid, 'y'])) {
                $courseid = $record->course;
                $coursename = $record->fullname;
            } else {
                $courseid = $course->id;
                $coursename = $course->fullname;
            }
        }

        $cm = $this->questionnaire->coursemodule();
        $groupname = '';
        if (groups_get_activity_groupmode($cm, $course)) {
            if ($currentgroupid > 0) {
                $groupname = groups_get_group_name($currentgroupid);
            } else {
                if ($user->id) {
                    if ($groups = groups_get_all_groups($courseid, $user->id)) {
                        foreach ($groups as $group) {
                            $groupname .= $group->name . ', ';
                        }
                        $groupname = substr($groupname, 0, strlen($groupname) - 2);
                    } else {
                        $groupname = ' (' . get_string('groupnonmembers') . ')';
                    }
                }
            }
        }

        if ($isanonymous) {
            if (!isset($anonumap[$user->id])) {
                $anonumap[$user->id] = count($anonumap) + 1;
            }
            $fullname = get_string('anonymous', 'questionnaire') . $anonumap[$user->id];
            $username = '';
            $uid = '';
        } else {
            $uid = $user->id;
            $fullname = fullname($user);
            $username = $user->username;
        }

        if (in_array('response', $options)) {
            array_push($positioned, $resprow->rid);
        }
        if (in_array('submitted', $options)) {
            $submitted = date(get_string('strfdateformatcsv', 'questionnaire'), $resprow->submitted);
            array_push($positioned, $submitted);
        }
        if (in_array('institution', $options)) {
            array_push($positioned, $user->institution);
        }
        if (in_array('department', $options)) {
            array_push($positioned, $user->department);
        }
        if (in_array('course', $options)) {
            array_push($positioned, $coursename);
        }
        if (in_array('group', $options)) {
            array_push($positioned, $groupname);
        }
        if (in_array('id', $options)) {
            array_push($positioned, $uid);
        }
        if (in_array('useridnumber', $options)) {
            array_push($positioned, $user->idnumber);
        }
        if (in_array('fullname', $options)) {
            array_push($positioned, $fullname);
        }
        if (in_array('username', $options)) {
            array_push($positioned, $username);
        }
        if (in_array('complete', $options)) {
            array_push($positioned, $resprow->complete);
        }
        foreach ($identityfields as $field) {
            array_push($positioned, $resprow->$field);
        }

        for ($c = $nbinfocols; $c < $numrespcols; $c++) {
            if (isset($row[$c])) {
                $positioned[] = $row[$c];
            } else if (isset($questionsbyposition[$c])) {
                $question = $questionsbyposition[$c];
                $qtype = intval($question->typeid());
                if ($qtype === QUESCHECK) {
                    $positioned[] = '0';
                } else {
                    $positioned[] = null;
                }
            } else {
                $positioned[] = null;
            }
        }
        return $positioned;
    }

    /**
     * Return the unique question type ids used across this survey's questions.
     *
     * @param bool $uniquebytable When true, also de-duplicates by response table.
     * @return array
     */
    private function get_survey_questiontypes(bool $uniquebytable = false): array {
        $uniquetypes = [];
        $uniquetables = [];
        foreach ($this->questionnaire->questions() as $question) {
            $type = $question->typeid();
            $responsetable = $question->responsetable();
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

    /**
     * Return the identity fields to include for each user in the CSV.
     *
     * Returns an empty array when the 'useridentityfields' option is not set or
     * when the questionnaire is anonymous.
     *
     * @param array $options Enabled download option strings.
     * @return array Field names.
     */
    private function get_identity_fields(array $options): array {
        if (!in_array('useridentityfields', $options) || $this->questionnaire->respondenttype() == 'anonymous') {
            return [];
        }
        return \core_user\fields::get_identity_fields($this->questionnaire->context());
    }

    /**
     * Return a stdClass of identity field values for the given user.
     *
     * @param \context $context
     * @param int $userid
     * @return stdClass|false
     */
    private static function get_user_identity_fields(\context $context, int $userid) {
        global $DB;

        $fields = \core_user\fields::for_identity($context);
        [
            'selects' => $selects,
            'joins' => $joins,
            'params' => $params,
        ] = (array)$fields->get_sql('u', false, '', '', false);
        $sql = "SELECT $selects FROM {user} u $joins WHERE u.id = ?";
        return $DB->get_record_sql($sql, array_merge($params, [$userid]));
    }

    /**
     * Return the user field names used when building CSV rows.
     *
     * @return array
     */
    private function user_fields(): array {
        $userfieldsarr = \core_user\fields::get_name_fields();
        $userfieldsarr = array_merge($userfieldsarr, ['username', 'department', 'institution', 'idnumber']);
        return $userfieldsarr;
    }
}
