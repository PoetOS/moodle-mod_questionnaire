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
 * This file contains the parent class for essay question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();
use \html_writer;

class essay extends text {

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
        return 'essaybox';
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function question_template() {
        return false;
    }

    /**
     * Override and return a response template if provided. Output of response_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function response_template() {
        return false;
    }

    /**
     * @param \mod_questionnaire\responsetype\response\response $response
     * @param $descendantsdata
     * @param bool $blankquestionnaire
     * @return object|string
     */
    protected function question_survey_display($response, $descendantsdata, $blankquestionnaire=false) {
        $output = '';

        // Essay.
        // Columns and rows default values.
        $cols = 80;
        $rows = 15;
        // Use HTML editor or not?
        if ($this->precise == 0) {
            $canusehtmleditor = true;
            $rows = $this->length == 0 ? $rows : $this->length;
        } else {
            $canusehtmleditor = false;
            // Prior to version 2.6, "precise" was used for rows number.
            $rows = $this->precise > 1 ? $this->precise : $this->length;
        }
        $name = 'q'.$this->id;
        if (isset($response->answers[$this->id][0])) {
            $value = $response->answers[$this->id][0]->value;
        } else {
            $value = '';
        }
        if ($canusehtmleditor) {
            $editor = editors_get_preferred_editor();
            $editor->use_editor($name, questionnaire_get_editor_options($this->context));
            $texteditor = html_writer::tag('textarea', $value,
                            array('id' => $name, 'name' => $name, 'rows' => $rows, 'cols' => $cols));
        } else {
            $editor = FORMAT_PLAIN;
            $texteditor = html_writer::tag('textarea', $value,
                            array('id' => $name, 'name' => $name, 'rows' => $rows, 'cols' => $cols));
        }
        $output .= $texteditor;

        return $output;
    }

    /**
     * @param \mod_questionnaire\responsetype\response\response $response
     * @return object|string
     */
    protected function response_survey_display($response) {
        if (isset($response->answers[$this->id])) {
            $answer = reset($response->answers[$this->id]);
            $answer = format_text($answer->value, FORMAT_HTML);
        } else {
            $answer = '&nbsp;';
        }
        $output = '';
        $output .= '<div class="response text">';
        $output .= $answer;
        $output .= '</div>';
        return $output;
    }

    // Note - intentianally returning 'precise' for length and 'length' for precise.
    /**
     * @param \MoodleQuickForm $mform
     * @param string $helptext
     * @return \MoodleQuickForm|void
     * @throws \coding_exception
     */
    protected function form_length(\MoodleQuickForm $mform, $helptext = '') {
        $responseformats = array(
                        "0" => get_string('formateditor', 'questionnaire'),
                        "1" => get_string('formatplain', 'questionnaire'));
        $mform->addElement('select', 'precise', get_string('responseformat', 'questionnaire'), $responseformats);
        $mform->setType('precise', PARAM_INT);
        return $mform;
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
     * @param \MoodleQuickForm $mform
     * @param string $helptext
     * @return \MoodleQuickForm|void
     * @throws \coding_exception
     */
    protected function form_precise(\MoodleQuickForm $mform, $helptext = '') {
        $choices = array();
        for ($lines = 5; $lines <= 40; $lines += 5) {
            $choices[$lines] = get_string('nlines', 'questionnaire', $lines);
        }
        $mform->addElement('select', 'length', get_string('responsefieldlines', 'questionnaire'), $choices);
        $mform->setType('length', PARAM_INT);
        return $mform;
    }
}
