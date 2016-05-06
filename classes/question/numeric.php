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

class numeric extends base {

    /**
     * Constructor. Use to set any default properties.
     *
     */
    public function __construct($id = 0, $question = null, $context = null, $params = array()) {
        $this->length = 10;
        return parent::__construct($id, $question, $context, $params);
    }

    protected function responseclass() {
        return '\\mod_questionnaire\\response\\text';
    }

    public function helpname() {
        return 'numeric';
    }

    protected function question_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        // Numeric.
        $precision = $this->precise;
        $a = '';
        if (isset($data->{'q'.$this->id})) {
            $mynumber = $data->{'q'.$this->id};
            if ($mynumber != '') {
                $mynumber0 = $mynumber;
                if (!is_numeric($mynumber) ) {
                    $msg = get_string('notanumber', 'questionnaire', $mynumber);
                    questionnaire_notify ($msg);
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
                        questionnaire_notify ($msg);
                    }
                }
            }
            if ($mynumber != '') {
                $data->{'q'.$this->id} = $mynumber;
            }
        }

        echo '<input onkeypress="return event.keyCode != 13;" type="text" size="'.
            $this->length.'" name="q'.$this->id.'" maxlength="'.$this->length.
             '" value="'.(isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '').
            '" id="' . $this->type . $this->id . '" />';
    }

    protected function response_survey_display($data) {
        $this->length++; // For sign.
        if ($this->precise) {
            $this->length += 1 + $this->precise;
        }
        echo '<div class="response numeric">';
        if (isset($data->{'q'.$this->id})) {
            echo('<span class="selected">'.$data->{'q'.$this->id}.'</span>');
        }
        echo '</div>';
    }

    /**
     * Check question's form data for valid response. Override this is type has specific format requirements.
     *
     * @param object $responsedata The data entered into the response.
     * @return boolean
     */
    public function response_valid($responsedata) {
        if (isset($responsedata->{'q'.$this->id})) {
            return (($responsedata->{'q'.$this->id} == '') || is_numeric($responsedata->{'q'.$this->id}));
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
}