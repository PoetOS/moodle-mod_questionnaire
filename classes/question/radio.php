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
 * This file contains the parent class for radio question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();

class radio extends base {

    protected function responseclass() {
        return '\\mod_questionnaire\\response\\single';
    }

    public function helpname() {
        return 'radiobuttons';
    }

    /**
     * Return true if the question has choices.
     */
    public function has_choices() {
        return true;
    }

    protected function question_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        // Radio buttons
        global $idcounter;  // To make sure all radio buttons have unique ids. // JR 20 NOV 2007.

        $otherempty = false;
        $output = '';
        // Find out which radio button is checked (if any); yields choice ID.
        if (isset($data->{'q'.$this->id})) {
            $checked = $data->{'q'.$this->id};
        } else {
            $checked = '';
        }
        $horizontal = $this->length;
        $ischecked = false;

        // To display or hide dependent questions on Preview page.
        $onclickdepend = array();
        if ($descendantsdata) {
            $descendants = implode(',', $descendantsdata['descendants']);
            foreach ($descendantsdata['choices'] as $key => $choice) {
                $choices[$key] = implode(',', $choice);
                $onclickdepend[$key] = ' onclick="depend(\''.$descendants.'\', \''.$choices[$key].'\')"';
            }
        } // End dependents.

        foreach ($this->choices as $id => $choice) {
            $other = strpos($choice->content, '!other');
            if ($horizontal) {
                $output .= ' <span style="white-space:nowrap;">';
            }

            // To display or hide dependent questions on Preview page.
            $onclick = '';
            if ($onclickdepend) {
                if (isset($onclickdepend[$id])) {
                    $onclick = $onclickdepend[$id];
                } else {
                    // In case this dependchoice is not used by any child question.
                    $onclick = ' onclick="depend(\''.$descendants.'\', \'\')"';
                }

            } else {
                $onclick = ' onclick="other_check_empty(name, value)"';
            } // End dependents.

            if ($other !== 0) { // This is a normal radio button.
                $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);

                $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"'.$onclick;
                if ($id == $checked) {
                    $output .= ' checked="checked"';
                    $ischecked = true;
                }
                $value = '';
                if ($blankquestionnaire) {
                    $output .= ' disabled="disabled"';
                    $value = ' ('.$choice->value.') ';
                }
                $content = $choice->content;
                $contents = questionnaire_choice_values($choice->content);
                $output .= ' /><label for="'.$htmlid.'" >'.$value.
                    format_text($contents->text, FORMAT_HTML).$contents->image.'</label>';
            } else {             // Radio button with associated !other text field.
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;
                $otherempty = false;
                $otherid = 'q'.$this->id.'_'.$checked;
                if (substr($checked, 0, 6) == 'other_') { // Fix bug CONTRIB-222.
                    $checked = substr($checked, 6);
                }
                $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);

                $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="other_'.$id.'"'.$onclick;
                if (($id == $checked) || !empty($data->$cid)) {
                    $output .= ' checked="checked"';
                    $ischecked = true;
                    if (!$data->$cid) {
                        $otherempty = true;
                    }
                }
                $output .= ' /><label for="'.$htmlid.'" >'.format_text($othertext, FORMAT_HTML).'</label>';

                $choices['other_'.$cid] = $othertext;
                $output .= '<input type="text" size="25" name="'.$cid.'" id="'.$htmlid.'-other" onclick="other_check(name)"';
                if (isset($data->$cid)) {
                    $output .= ' value="'.stripslashes($data->$cid) .'"';
                }
                $output .= ' />';
                $output .= '<label for="'.$htmlid.'-other" class="accesshide">Text for '.
                    format_text($othertext, FORMAT_HTML).'</label>&nbsp;';
            }
            if ($horizontal) {
                // Added a zero-width space character to make MSIE happy!
                $output .= '</span>&#8203;';
            } else {
                $output .= '<br />';
            }
        }

        // CONTRIB-846.
        if ($this->required == 'n') {
            $id = '';
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            if ($horizontal) {
                $output .= ' <span style="white-space:nowrap;">';
            }

            // To display or hide dependent questions on Preview page.
            $onclick = '';
            if ($onclickdepend) {
                $onclick = ' onclick="depend(\''.$descendants.'\', \'\')"';
            } else {
                $onclick = ' onclick="other_check_empty(name, value)"';
            } // End dependents.
            $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"'.$onclick;
            if (!$ischecked && !$blankquestionnaire) {
                $output .= ' checked="checked"';
            }
            $content = get_string('noanswer', 'questionnaire');
            $output .= ' /><label for="'.$htmlid.'" >'.
                format_text($content, FORMAT_HTML).'</label>';

            if ($horizontal) {
                $output .= '</span>&nbsp;&nbsp;';
            } else {
                $output .= '<br />';
            }
        }
        // End CONTRIB-846.

        if ($otherempty) {
            $this->add_notification(get_string('otherempty', 'questionnaire'));
        }
        return $output;
    }

    protected function response_survey_display($data) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $output = '';

        $horizontal = $this->length;
        $checked = (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '');
        foreach ($this->choices as $id => $choice) {
            if ($horizontal) {
                $output .= ' <span style="white-space:nowrap;">';
            }
            if (strpos($choice->content, '!other') !== 0) {
                $contents = questionnaire_choice_values($choice->content);
                $choice->content = $contents->text.$contents->image;
                if ($id == $checked) {
                    $output .= '<span class="selected">'.
                        '<input type="radio" name="'.$id.$uniquetag++.'" checked="checked" /> '.
                        ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span>&nbsp;';
                } else {
                    $output .= '<span class="unselected">'.
                        '<input type="radio" disabled="disabled" name="'.$id.$uniquetag++.'" onclick="this.checked=false;" /> '.
                        ($choice->content === '' ? $id : format_text($choice->content, FORMAT_HTML)).'</span>&nbsp;';
                }

            } else {
                $othertext = preg_replace(
                        array("/^!other=/", "/^!other/"),
                        array('', get_string('other', 'questionnaire')),
                        $choice->content);
                $cid = 'q'.$this->id.'_'.$id;

                if (isset($data->{'q'.$this->id.'_'.$id})) {
                    $output .= '<span class="selected">'.
                        '<input type="radio" name="'.$id.$uniquetag++.'" checked="checked" /> '.$othertext.' ';
                    $output .= '<span class="response text">';
                    $output .= !empty($data->$cid) ? htmlspecialchars($data->$cid) : '&nbsp;';
                    $output .= '</span></span>';
                } else {
                    $output .= '<span class="unselected"><input type="radio" name="'.$id.$uniquetag++.
                        '" onclick="this.checked=false;" /> '.
                        $othertext.'</span>';
                }
            }
            if ($horizontal) {
                $output .= '</span>';
            } else {
                $output .= '<br />';
            }
        }

        return $output;
    }

    /**
     * Check question's form data for complete response.
     *
     * @param object $responsedata The data entered into the response.
     * @return boolean
     */
    public function response_complete($responsedata) {
        if (isset($responsedata->{'q'.$this->id}) && ($this->required == 'y') &&
                (strpos($responsedata->{'q'.$this->id}, 'other_') !== false)) {
            return !empty($responsedata->{'q'.$this->id.''.substr($responsedata->{'q'.$this->id}, 5)});
        } else {
            return parent::response_complete($responsedata);
        }
    }

    /**
     * Check question's form data for valid response. Override this is type has specific format requirements.
     *
     * @param object $responsedata The data entered into the response.
     * @return boolean
     */
    public function response_valid($responsedata) {
        if (isset($responsedata->{'q'.$this->id}) && (strpos($responsedata->{'q'.$this->id}, 'other_') !== false)) {
            // False if "other" choice is checked but text box is empty.
            return !empty($responsedata->{'q'.$this->id.''.substr($responsedata->{'q'.$this->id}, 5)});
        } else {
            return parent::response_valid($responsedata);
        }
    }

    protected function form_length(\MoodleQuickForm $mform, $helptext = '') {
        $lengroup = array();
        $lengroup[] =& $mform->createElement('radio', 'length', '', get_string('vertical', 'questionnaire'), '0');
        $lengroup[] =& $mform->createElement('radio', 'length', '', get_string('horizontal', 'questionnaire'), '1');
        $mform->addGroup($lengroup, 'lengroup', get_string('alignment', 'questionnaire'), ' ', false);
        $mform->addHelpButton('lengroup', 'alignment', 'questionnaire');
        $mform->setType('length', PARAM_INT);

        return $mform;
    }

    protected function form_precise(\MoodleQuickForm $mform, $helptext = '') {
        return base::form_precise_hidden($mform);
    }
}