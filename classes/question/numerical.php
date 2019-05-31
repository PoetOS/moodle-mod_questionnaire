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
 * This file contains the parent class for numeric question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();

class numerical extends question {

    /**
     * Constructor. Use to set any default properties.
     *
     */
    public function __construct($id = 0, $question = null, $context = null, $params = []) {
        $this->length = 10;
        return parent::__construct($id, $question, $context, $params);
    }

    protected function responseclass() {
        return '\\mod_questionnaire\\responsetype\\text';
    }

    public function helpname() {
        return 'numeric';
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function question_template() {
        return 'mod_questionnaire/question_numeric';
    }

    /**
     * Override and return a response template if provided. Output of response_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function response_template() {
        return 'mod_questionnaire/response_numeric';
    }

    /**
     * Return the context tags for the check question template.
     * @param \mod_questionnaire\responsetype\response\response $response
     * @param $descendantsdata
     * @param boolean $blankquestionnaire
     * @return object The check question context tags.
     * @throws \coding_exception
     */
    protected function question_survey_display($response, $descendantsdata, $blankquestionnaire=false) {
        // Numeric.
        $questiontags = new \stdClass();
        $precision = $this->precise;
        $a = '';
        if (isset($response->answers[$this->id][0])) {
            $mynumber = $response->answers[$this->id][0]->value;
            if ($mynumber != '') {
                $mynumber0 = $mynumber;
                if (!is_numeric($mynumber) ) {
                    $msg = get_string('notanumber', 'questionnaire', $mynumber);
                    $this->add_notification($msg);
                } else {
                    if ($precision) {
                        $pos = strpos($mynumber, '.');
                        if (!$pos) {
                            if (strlen($mynumber) > $this->length) {
                                $mynumber = substr($mynumber, 0 , $this->length);
                            }
                        }
                        $this->length += (1 + $precision); // To allow for n numbers after decimal point.
                    }
                    $mynumber = number_format($mynumber, $precision , '.', '');
                    if ( $mynumber != $mynumber0) {
                        $a->number = $mynumber0;
                        $a->precision = $precision;
                        $msg = get_string('numberfloat', 'questionnaire', $a);
                        $this->add_notification($msg);
                    }
                }
            }
            if ($mynumber != '') {
                $response->answers[$this->id][0]->value = $mynumber;
            }
        }

        $choice = new \stdClass();
        $choice->onkeypress = 'return event.keyCode != 13;';
        $choice->size = $this->length;
        $choice->name = 'q'.$this->id;
        $choice->maxlength = $this->length;
        $choice->value = (isset($response->answers[$this->id][0]) ? $response->answers[$this->id][0]->value : '');
        $choice->id = self::qtypename($this->type_id) . $this->id;
        $questiontags->qelements = new \stdClass();
        $questiontags->qelements->choice = $choice;
        return $questiontags;
    }

    /**
     * Return the context tags for the numeric response template.
     * @param object $data
     * @return object The numeric question response context tags.
     *
     */
    protected function response_survey_display($response) {
        $resptags = new \stdClass();
        if (isset($response->answers[$this->id])) {
            $answer = reset($response->answers[$this->id]);
            $resptags->content = $answer->value;
        }
        return $resptags;
    }

    /**
     * Check question's form data for valid response. Override this is type has specific format requirements.
     *
     * @param object $responsedata The data entered into the response.
     * @return boolean
     */
    public function response_valid($responsedata) {
        if (isset($responsedata->{'q'.$this->id})) {
            // If commas are present, replace them with periods, in case that was meant as the European decimal place.
            $responseval = str_replace(',', '.', $responsedata->{'q'.$this->id});
            return (($responseval == '') || is_numeric($responseval));
        } else {
            return parent::response_valid($responsedata);
        }
    }

    protected function form_length(\MoodleQuickForm $mform, $helptext = '') {
        $this->length = isset($this->length) ? $this->length : 10;
        return parent::form_length($mform, 'maxdigitsallowed');
    }

    protected function form_precise(\MoodleQuickForm $mform, $helptext = '') {
        return parent::form_precise($mform, 'numberofdecimaldigits');
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
    public function get_mobile_question_data($qnum, $autonum = false) {
        $mobiledata = parent::get_mobile_question_data($qnum, $autonum = false);
        $mobiledata->questionsinfo['isnumeric'] = true;
        return $mobiledata;
    }

    /**
     * @param $mobiledata
     * @return mixed
     */
    public function add_mobile_question_choice_data($mobiledata) {
        $mobiledata->questions = [];
        $mobiledata->questions[0] = new \stdClass();
        $mobiledata->questions[0]->id = 0;
        $mobiledata->questions[0]->choice_id = 0;
        $mobiledata->questions[0]->question_id = $this->id;
        $mobiledata->questions[0]->content = '';
        $mobiledata->questions[0]->value = null;
        return $mobiledata;
    }
}