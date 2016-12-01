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
 * This file contains the parent class for yesno question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;
defined('MOODLE_INTERNAL') || die();

class yesno extends base {

    protected function responseclass() {
        return '\\mod_questionnaire\\response\\boolean';
    }

    public function helpname() {
        return 'yesno';
    }

    protected function question_survey_display($data, $descendantsdata, $blankquestionnaire=false) {
        // Moved choose_from_radio() here to fix unwanted selection of yesno buttons and radio buttons with identical ID.

        // To display or hide dependent questions on Preview page.
        $onclickdepend = array();
        if ($descendantsdata) {
            $descendants = implode(',', $descendantsdata['descendants']);
            if (isset($descendantsdata['choices'][0])) {
                $choices['y'] = implode(',', $descendantsdata['choices'][0]);
            } else {
                $choices['y'] = '';
            }
            if (isset($descendantsdata['choices'][1])) {
                $choices['n'] = implode(',', $descendantsdata['choices'][1]);
            } else {
                $choices['n'] = '';
            }
            $onclickdepend['y'] = ' onclick="depend(\''.$descendants.'\', \''.$choices['y'].'\')"';
            $onclickdepend['n'] = ' onclick="depend(\''.$descendants.'\', \''.$choices['n'].'\')"';
        }
        global $idcounter;  // To make sure all radio buttons have unique ids. // JR 20 NOV 2007.

        $stryes = get_string('yes');
        $strno = get_string('no');

        $val1 = 'y';
        $val2 = 'n';

        if ($blankquestionnaire) {
            $stryes = ' (1) '.$stryes;
            $strno = ' (0) '.$strno;
        }

        $options = array($val1 => $stryes, $val2 => $strno);
        $name = 'q'.$this->id;
        $checked = (isset($data->{'q'.$this->id}) ? $data->{'q'.$this->id} : '');
        $output = '';
        $ischecked = false;

        foreach ($options as $value => $label) {
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            $output .= '<input name="'.$name.'" id="'.$htmlid.'" type="radio" value="'.$value.'"';
            if ($value == $checked) {
                $output .= ' checked="checked"';
                $ischecked = true;
            }
            if ($blankquestionnaire) {
                $output .= ' disabled="disabled"';
            }
            if (isset($onclickdepend[$value])) {
                $output .= $onclickdepend[$value];
            }
            $output .= ' /><label for="'.$htmlid.'">'. $label .'</label>' . "\n";
        }
        // CONTRIB-846.
        if ($this->required == 'n') {
            $id = '';
            $htmlid = 'auto-rb'.sprintf('%04d', ++$idcounter);
            $output .= '<input name="q'.$this->id.'" id="'.$htmlid.'" type="radio" value="'.$id.'"';
            if (!$ischecked && !$blankquestionnaire) {
                $output .= ' checked="checked"';
            }
            if ($onclickdepend) {
                $output .= ' onclick="depend(\''.$descendants.'\', \'\')"';
            }
            $content = get_string('noanswer', 'questionnaire');
            $output .= ' /><label for="'.$htmlid.'" >'.
                format_text($content, FORMAT_HTML).'</label>';
        }
        // End CONTRIB-846.

        $output .= '</span>' . "\n";
        return $output;
    }

    protected function response_survey_display($data) {
        static $stryes = null;
        static $strno = null;
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $output = '';

        if ($stryes === null) {
             $stryes = get_string('yes');
             $strno = get_string('no');
        }

        $val1 = 'y';
        $val2 = 'n';

        $output .= '<div class="response yesno">';
        if (isset($data->{'q'.$this->id}) && ($data->{'q'.$this->id} == $val1)) {
            $output .= '<span class="selected">' .
                '<input type="radio" name="q'.$this->id.$uniquetag++.'y" checked="checked" /> '.$stryes.'</span>';
        } else {
            $output .= '<span class="unselected">' .
                '<input type="radio" name="q'.$this->id.$uniquetag++.'y" onclick="this.checked=false;" /> '.$stryes.'</span>';
        }
        if (isset($data->{'q'.$this->id}) && ($data->{'q'.$this->id} == $val2)) {
            $output .= ' <span class="selected">' .
                '<input type="radio" name="q'.$this->id.$uniquetag++.'n" checked="checked" /> '.$strno.'</span>';
        } else {
            $output .= ' <span class="unselected">' .
                '<input type="radio" name="q'.$this->id.$uniquetag++.'n" onclick="this.checked=false;" /> '.$strno.'</span>';
        }
        $output .= '</div>';

        return $output;
    }

    protected function form_length(\MoodleQuickForm $mform, $helpname = '') {
        return base::form_length_hidden($mform);
    }

    protected function form_precise(\MoodleQuickForm $mform, $helpname = '') {
        return base::form_precise_hidden($mform);
    }
}