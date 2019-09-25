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
 * This file contains the parent class for text question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();

class text extends question {

    /**
     * Constructor. Use to set any default properties.
     *
     */
    public function __construct($id = 0, $question = null, $context = null, $params = []) {
        $this->length = 20;
        $this->precise = 25;
        return parent::__construct($id, $question, $context, $params);
    }

    /**
     * @return object|string
     */
    protected function responseclass() {
        return '\\mod_questionnaire\\responsetype\\text';
    }

    /**
     * @return string
     */
    public function helpname() {
        return 'textbox';
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function question_template() {
        return 'mod_questionnaire/question_text';
    }

    /**
     * Override and return a response template if provided. Output of response_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function response_template() {
        return 'mod_questionnaire/response_text';
    }

    /**
     * Return the context tags for the check question template.
     * @param \mod_questionnaire\responsetype\response\response $response
     * @param $descendantsdata
     * @param boolean $blankquestionnaire
     * @return object The check question context tags.
     */
    protected function question_survey_display($response, $descendantsdata, $blankquestionnaire=false) {
        // Text Box.
        $questiontags = new \stdClass();
        $questiontags->qelements = new \stdClass();
        $choice = new \stdClass();
        $choice->onkeypress = 'return event.keyCode != 13;';
        $choice->size = $this->length;
        $choice->name = 'q'.$this->id;
        if ($this->precise > 0) {
            $choice->maxlength = $this->precise;
        }
        $choice->value = (isset($response->answers[$this->id][0]) ? stripslashes($response->answers[$this->id][0]->value) : '');
        $choice->id = self::qtypename($this->type_id) . $this->id;
        $questiontags->qelements->choice = $choice;
        return $questiontags;
    }

    /**
     * Return the context tags for the text response template.
     * @param object $data
     * @return object The radio question response context tags.
     */
    protected function response_survey_display($response) {
        $resptags = new \stdClass();
        if (isset($response->answers[$this->id])) {
            $answer = reset($response->answers[$this->id]);
            $resptags->content = format_text($answer->value, FORMAT_HTML);
        }
        return $resptags;
    }

    /**
     * @param \MoodleQuickForm $mform
     * @param string $helptext
     */
    protected function form_length(\MoodleQuickForm $mform, $helptext = '') {
        return parent::form_length($mform, 'fieldlength');
    }

    /**
     * @param \MoodleQuickForm $mform
     * @param string $helptext
     */
    protected function form_precise(\MoodleQuickForm $mform, $helptext = '') {
        return parent::form_precise($mform, 'maxtextlength');
    }

    /**
     * True if question provides mobile support.
     *
     * @return bool
     */
    public function supports_mobile() {
        return true;
    }

    /**
     * @param $qnum
     * @param $fieldkey
     * @param bool $autonum
     * @return \stdClass
     * @throws \coding_exception
     */
    public function mobile_question_display($qnum, $autonum = false) {
        $mobiledata = parent::mobile_question_display($qnum, $autonum);
        $mobiledata->istextessay = true;
        return $mobiledata;
    }

    /**
     * @param $mobiledata
     * @return mixed
     */
    public function mobile_question_choices_display() {
        $choices = [];
        $choices[0] = new \stdClass();
        $choices[0]->id = 0;
        $choices[0]->choice_id = 0;
        $choices[0]->question_id = $this->id;
        $choices[0]->content = '';
        $choices[0]->value = null;
        return $choices;
    }
}