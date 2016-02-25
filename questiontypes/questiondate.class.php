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
 * This file contains the parent class for questionnaire question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

class questionnaire_question_date extends questionnaire_question_base {

    protected function responseclass() {
        return 'questionnaire_response_date';
    }

    protected function helpname() {
        return 'date';
    }

    protected function question_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        // Date.

        $datemess = html_writer::start_tag('div', array('class' => 'qn-datemsg'));
        $datemess .= get_string('dateformatting', 'questionnaire');
        $datemess .= html_writer::end_tag('div');
        if (!empty($data->{'q'.$this->id})) {
            $dateentered = $data->{'q'.$this->id};
            $setdate = questionnaire_check_date ($dateentered, false);
            if ($setdate == 'wrongdateformat') {
                $msg = get_string('wrongdateformat', 'questionnaire', $dateentered);
                questionnaire_notify($msg);
            } else if ($setdate == 'wrongdaterange') {
                $msg = get_string('wrongdaterange', 'questionnaire');
                questionnaire_notify($msg);
            } else {
                $data->{'q'.$this->id} = $setdate;
            }
        }
        echo $datemess;
        echo html_writer::start_tag('div', array('class' => 'qn-date'));
        echo '<input onkeypress="return event.keyCode != 13;" type="text" size="12" name="q'.$this->id.'" maxlength="10" value="'.
             (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '').'" />';
        echo html_writer::end_tag('div');
    }

    protected function response_survey_display($data) {
        if (isset($data->{'q'.$this->id})) {
            echo '<div class="response date">';
            echo('<span class="selected">'.$data->{'q'.$this->id}.'</span>');
            echo '</div>';
        }
    }

    /**
     * Check question's form data for valid response. Override this is type has specific format requirements.
     *
     * @param object $responsedata The data entered into the response.
     * @return boolean
     */
    public function response_valid($responsedata) {
        if (isset($responsedata->{'q'.$this->id})) {
            $checkdateresult = '';
            if ($responsedata->{'q'.$this->id} != '') {
                $checkdateresult = questionnaire_check_date($responsedata->{'q'.$this->id});
            }
            return (substr($checkdateresult, 0, 5) != 'wrong');
        } else {
            return parent::response_valid($responsedata);
        }
    }

    protected function form_length(MoodleQuickForm $mform, $helpname = '') {
        return questionnaire_question_base::form_length_hidden($mform);
    }

    protected function form_precise(MoodleQuickForm $mform, $helpname = '') {
        return questionnaire_question_base::form_precise_hidden($mform);
    }

    /**
     * Configure bulk sql
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config('questionnaire_response_date', 'qrd', false, true, false);
    }
}