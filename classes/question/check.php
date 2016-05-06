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
 * This file contains the parent class for check question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();
use \html_writer;

class check extends base {

    protected function responseclass() {
        return '\\mod_questionnaire\\response\\multiple';
    }

    public function helpname() {
        return 'checkboxes';
    }

    /**
     * Return true if the question has choices.
     */
    public function has_choices() {
        return true;
    }

    protected function question_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        // Check boxes.
        $otherempty = false;
        if (!empty($data) ) {
            if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
                $data->{'q'.$this->id} = array();
            }
            // Verify that number of checked boxes (nbboxes) is within set limits (length = min; precision = max).
            if ( $data->{'q'.$this->id} ) {
                $otherempty = false;
                $boxes = $data->{'q'.$this->id};
                $nbboxes = count($boxes);
                foreach ($boxes as $box) {
                    $pos = strpos($box, 'other_');
                    if (is_int($pos) == true) {
                        $otherchoice = substr($box, 6);
                        $resp = 'q'.$this->id.''.substr($box, 5);
                        if (!$data->$resp) {
                            $otherempty = true;
                        }
                    }
                }
                $nbchoices = count($this->choices);
                $min = $this->length;
                $max = $this->precise;
                if ($max == 0) {
                    $max = $nbchoices;
                }
                if ($min > $max) {
                    $min = $max; // Sanity check.
                }
                $min = min($nbchoices, $min);
                $msg = '';
                if ($nbboxes < $min || $nbboxes > $max) {
                    $msg = get_string('boxesnbreq', 'questionnaire');
                    if ($min == $max) {
                        $msg .= '&nbsp;'.get_string('boxesnbexact', 'questionnaire', $min);
                    } else {
                        if ($min && ($nbboxes < $min)) {
                            $msg .= get_string('boxesnbmin', 'questionnaire', $min);
                            if ($nbboxes > $max) {
                                $msg .= ' & ' .get_string('boxesnbmax', 'questionnaire', $max);
                            }
                        } else {
                            if ($nbboxes > $max ) {
                                $msg .= get_string('boxesnbmax', 'questionnaire', $max);
                            }
                        }
                    }
                    questionnaire_notify($msg);
                }
            }
        }

        foreach ($this->choices as $id => $choice) {

            $other = strpos($choice->content, '!other');
            if ($other !== 0) { // This is a normal check box.
                $contents = questionnaire_choice_values($choice->content);
                $checked = false;
                if (!empty($data) ) {
                    $checked = in_array($id, $data->{'q'.$this->id});
                }
                echo html_writer::checkbox('q'.$this->id.'[]', $id, $checked,
                                               format_text($contents->text, FORMAT_HTML).$contents->image);
                echo '<br />';
            } else {             // Check box with associated !other text field.
                // In case length field has been used to enter max number of choices, set it to 20.
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;
                if (!empty($data) && !empty($data->$cid)) {
                    $checked = true;
                } else {
                    $checked = false;
                }
                $name = 'q'.$this->id.'[]';
                $value = 'other_'.$id;

                echo html_writer::checkbox($name, $value, $checked, format_text($othertext.'', FORMAT_HTML));
                $othertext = '&nbsp;<input type="text" size="25" name="'.$cid.'" onclick="other_check(name)"';
                if ($cid) {
                    $othertext .= ' value="'. (!empty($data->$cid) ? stripslashes($data->$cid) : '') .'"';
                }
                $othertext .= ' />';
                echo $othertext.'<br />';
            }
        }
        if ($otherempty) {
            questionnaire_notify (get_string('otherempty', 'questionnaire'));
        }
    }

    protected function response_survey_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        if (!isset($data->{'q'.$this->id}) || !is_array($data->{'q'.$this->id})) {
            $data->{'q'.$this->id} = array();
        }

        echo '<div class="response check">';
        foreach ($this->choices as $id => $choice) {
            if (strpos($choice->content, '!other') !== 0) {
                $contents = questionnaire_choice_values($choice->content);
                $choice->content = $contents->text.$contents->image;

                if (in_array($id, $data->{'q'.$this->id})) {
                    echo '<span class="selected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" checked="checked" onclick="this.checked=true;" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span><br />';
                } else {
                    echo '<span class="unselected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span><br />';
                }
            } else {
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;

                if (isset($data->$cid)) {
                    echo '<span class="selected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" checked="checked" onclick="this.checked=true;" /> '.
                         ($othertext === '' ? $id : $othertext).' ';
                    echo '<span class="response text">';
                    echo (!empty($data->$cid) ? htmlspecialchars($data->$cid) : '&nbsp;');
                    echo '</span></span><br />';
                } else {
                    echo '<span class="unselected">'.
                         '<input type="checkbox" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                         ($othertext === '' ? $id : $othertext).'</span><br />';
                }
            }
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
        $valid = true;
        if (isset($responsedata->{'q'.$this->id})) {
            $nbrespchoices = 0;
            foreach ($responsedata->{'q'.$this->id} as $resp) {
                if (strpos($resp, 'other_') !== false) {
                    // ..."other" choice is checked but text box is empty.
                    $othercontent = "q".$this->id.substr($resp, 5);
                    if (empty($responsedata->$othercontent)) {
                        $valid = false;
                        break;
                    }
                    $nbrespchoices++;
                } else if (is_numeric($resp)) {
                    $nbrespchoices++;
                }
            }
            $nbquestchoices = count($this->choices);
            $min = $this->length;
            $max = $this->precise;
            if ($max == 0) {
                $max = $nbquestchoices;
            }
            if ($min > $max) {
                $min = $max;     // Sanity check.
            }
            $min = min($nbquestchoices, $min);
            if ( $nbrespchoices && ($nbrespchoices < $min || $nbrespchoices > $max) ) {
                // Number of ticked boxes is not within min and max set limits.
                $valid = false;
            }
        } else {
            $valid = parent::response_valid($responsedata);
        }

        return $valid;
    }

    protected function form_length(\MoodleQuickForm $mform, $helptext = '') {
        return parent::form_length($mform, 'minforcedresponses');
    }

    protected function form_precise(\MoodleQuickForm $mform, $helptext = '') {
        return parent::form_precise($mform, 'maxforcedresponses');
    }

    /**
     * Preprocess choice data.
     */
    protected function form_preprocess_choicedata($formdata) {
        if (empty($formdata->allchoices)) {
            error (get_string('enterpossibleanswers', 'questionnaire'));
        } else {
            // Sanity checks for min and max checked boxes.
            $allchoices = $formdata->allchoices;
            $allchoices = explode("\n", $allchoices);
            $nbvalues = count($allchoices);

            if ($formdata->length > $nbvalues) {
                $formdata->length = $nbvalues;
            }
            if ($formdata->precise > $nbvalues) {
                $formdata->precise = $nbvalues;
            }
            $formdata->precise = max($formdata->length, $formdata->precise);
        }
        return true;
    }
}