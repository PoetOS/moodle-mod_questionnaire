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

/**
 * This defines a structured class to hold responses.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package response
 * @copyright 2019, onwards Poet
 */

namespace mod_questionnaire\responsetype\response;
use mod_questionnaire\responsetype\answer\answer;

defined('MOODLE_INTERNAL') || die();

class response {

    // Class properties.

    /** @var int $id The id of the response this applies to. */
    public $id;

    /** @var int $questionnaireid The id of the questionnaire this response applies to. */
    public $questionnaireid;

    /** @var int $userid The id of the user for this response. */
    public $userid;

    /** @var int $submitted The most recent submission date of this response. */
    public $submitted;

    /** @var boolean $complete Flag for final submission of this response. */
    public $complete;

    /** @var int $grade Numeric grade for this response (if applicable). */
    public $grade;

    /** @var array $answers Array by question of array of answer objects. */
    public $answers;

    /**
     * Choice constructor.
     * @param null $id
     * @param null $questionnaireid
     * @param null $userid
     * @param null $submitted
     * @param null $complete
     * @param null $grade
     */
    public function __construct($id = null, $questionnaireid = null, $userid = null, $submitted = null, $complete = null,
                                $grade = null) {
        global $DB;

        $this->id = $id;
        $this->questionnaireid = $questionnaireid;
        $this->userid = $userid;
        $this->submitted = $submitted;
        $this->complete = $complete;
        $this->grade = $grade;

        // Add answers by questions that exist.
        $this->add_questions_answers();
    }

    /**
     * Create and return a response object from data.
     *
     * @param object | array $responsedata The data to load.
     * @return response
     */
    public static function create_from_data($responsedata) {
        if (!is_array($responsedata)) {
            $responsedata = (array)$responsedata;
        }

        $properties = array_keys(get_class_vars(__CLASS__));
        foreach ($properties as $property) {
            if (!isset($responsedata[$property])) {
                $responsedata[$property] = null;
            }
        }

        return new response($responsedata['id'], $responsedata['questionnaireid'], $responsedata['userid'],
            $responsedata['submitted'], $responsedata['complete'], $responsedata['grade']);
    }

    /**
     * Add the answers to each question in a question array of answers structure.
     */
    public function add_questions_answers() {
        global $DB;

        $this->answers = [];

        $sql = 'SELECT ' . $DB->sql_concat("'b'", 'id') . ' AS id, response_id as responseid, question_id as questionid, '.
            'choice_id as choiceid, null as value ' .
            'FROM {questionnaire_response_bool} ' .
            'WHERE response_id = ? ';
        $sql .= 'UNION ALL ' .
            'SELECT ' . $DB->sql_concat("'d'", 'id') . ' AS id, response_id as responseid, question_id as questionid, '.
            'null as choiceid, response as value ' .
            'FROM {questionnaire_response_date} ' .
            'WHERE response_id = ? ';
        $sql .= 'UNION ALL ' .
            'SELECT ' . $DB->sql_concat("'r'", 'id') . ' AS id, response_id as responseid, question_id as questionid, '.
            'choice_id as choiceid, rankvalue as value ' .
            'FROM {questionnaire_response_rank} ' .
            'WHERE response_id = ? ';
        $sql .= 'UNION ALL ' .
            'SELECT ' . $DB->sql_concat("'t'", 'id') . ' AS id, response_id as responseid, question_id as questionid, '.
            'null as choiceid, response as value ' .
            'FROM {questionnaire_response_text} ' .
            'WHERE response_id = ? ';
        $sql .= 'UNION ALL ' .
            'SELECT ' . $DB->sql_concat("'m'", 'm.id') . ' AS id, m.response_id as responseid, m.question_id as questionid, '.
            'm.choice_id as choiceid, o.response as value ' .
            'FROM {questionnaire_resp_multiple} m ' .
            'LEFT JOIN {questionnaire_response_other} o ON o.response_id = m.response_id AND o.question_id = m.question_id AND ' .
            'o.choice_id = m.choice_id ' .
            'WHERE m.response_id = ? ';
        $sql .= 'UNION ALL ' .
            'SELECT ' . $DB->sql_concat("'s'", 's.id') . ' AS id, s.response_id as responseid, s.question_id as questionid, '.
            's.choice_id as choiceid, o.response as value ' .
            'FROM {questionnaire_resp_single} s ' .
            'LEFT JOIN {questionnaire_response_other} o ON o.response_id = s.response_id AND o.question_id = s.question_id AND ' .
            'o.choice_id = s.choice_id ' .
            'WHERE s.response_id = ? ';

        $records = $DB->get_records_sql($sql, [$this->id, $this->id, $this->id, $this->id, $this->id, $this->id, $this->id]);
        foreach ($records as $record) {
            $this->answers[$record->questionid][] = answer::create_from_data($record);
        }
    }
}