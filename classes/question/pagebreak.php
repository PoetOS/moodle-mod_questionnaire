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
 * This file contains the parent class for pagebreak question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
use mod_questionnaire\edit_question_form;
use \questionnaire;
defined('MOODLE_INTERNAL') || die();

class pagebreak extends question {

    /**
     * @return object|string
     */
    protected function responseclass() {
        return '';
    }

    /**
     * @return string
     */
    public function helpname() {
        return '';
    }

    /**
     * @param int $qnum
     * @param null $response
     * @return \stdClass|string
     */
    public function questionstart_survey_display($qnum, $response=null) {
        return '';
    }

    /**
     * @param object $data
     * @param $descendantsdata
     * @param bool $blankquestionnaire
     * @return string
     */
    protected function question_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        return '';
    }

    /**
     * @param object $data
     * @return string
     */
    protected function response_survey_display($data) {
        return '';
    }

    /**
     * @param edit_question_form $form
     * @param questionnaire $questionnaire
     * @return bool
     */
    public function edit_form(edit_question_form $form, questionnaire $questionnaire) {
        return false;
    }

    /**
     * True if question provides mobile support.
     *
     * @return bool
     */
    public function supports_mobile() {
        return false;
    }

    /**
     * Override and return false if not supporting mobile app.
     *
     * @param $qnum
     * @param $fieldkey
     * @param bool $autonum
     * @return \stdClass
     * @throws \coding_exception
     */
    public function mobile_question_display($qnum, $autonum = false) {
        return false;
    }
}