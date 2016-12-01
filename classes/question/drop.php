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
 * This file contains the parent class for drop question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();
use \html_writer;

class drop extends base {

    protected function responseclass() {
        return '\\mod_questionnaire\\response\\single';
    }

    public function helpname() {
        return 'dropdown';
    }

    /**
     * Return true if the question has choices.
     */
    public function has_choices() {
        return true;
    }

    protected function question_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        global $OUTPUT;

        // Drop.
        $output = '';
        $options = array();

        // To display or hide dependent questions on Preview page.
        if ($descendantsdata) {
            $qdropid = 'q'.$this->id;
            $descendants = implode(',', $descendantsdata['descendants']);
            foreach ($descendantsdata['choices'] as $key => $choice) {
                $choices[$key] = implode(',', $choice);
            }
            foreach ($this->choices as $key => $choice) {
                if ($pos = strpos($choice->content, '=')) {
                    $choice->content = substr($choice->content, $pos + 1);
                }
                if (isset($choices[$key])) {
                    $value = $choices[$key];
                } else {
                    $value = $key;
                }
                $options[$value] = $choice->content;
            }
            $dependdrop = "dependdrop('$qdropid', '$descendants')";
            $output .= html_writer::select($options, $qdropid, (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : ''),
                            array('' => 'choosedots'), array('id' => $qdropid, 'onchange' => $dependdrop));
            // End dependents.
        } else {
            foreach ($this->choices as $key => $choice) {
                if ($pos = strpos($choice->content, '=')) {
                    $choice->content = substr($choice->content, $pos + 1);
                }
                $options[$key] = $choice->content;
            }
            $output .= html_writer::select($options, 'q'.$this->id,
                (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : ''),
                array('' => 'choosedots'), array('id' => $this->type . $this->id));
        }

        return $output;
    }

    protected function response_survey_display($data) {
        global $OUTPUT;
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $output = '';

        $options = array();
        foreach ($this->choices as $id => $choice) {
            $contents = questionnaire_choice_values($choice->content);
            $options[$id] = format_text($contents->text, FORMAT_HTML);
        }
        $output .= '<div class="response drop">';
        $output .= html_writer::select($options, 'q'.$this->id.$uniquetag++,
            (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : ''));
        if (isset($data->{'q'.$this->id}) ) {
            $output .= ': <span class="selected">'.$options[$data->{'q'.$this->id}].'</span></div>';
        }

        return $output;
    }

    protected function form_length(\MoodleQuickForm $mform, $helpname = '') {
        return base::form_length_hidden($mform);
    }

    protected function form_precise(\MoodleQuickForm $mform, $helpname = '') {
        return base::form_precise_hidden($mform);
    }
}