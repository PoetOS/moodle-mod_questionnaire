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

class check extends question {

    protected function responseclass() {
        return '\\mod_questionnaire\\responsetype\\multiple';
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

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function question_template() {
        return 'mod_questionnaire/question_check';
    }

    /**
     * Override and return a form template if provided. Output of response_survey_display is iterpreted based on this.
     * @return boolean | string
     */
    public function response_template() {
        return 'mod_questionnaire/response_check';
    }

    /**
     * Override this and return true if the question type allows dependent questions.
     * @return boolean
     */
    public function allows_dependents() {
        return true;
    }

    /**
     * Return the context tags for the check question template.
     * @param \mod_questionnaire\responsetype\response\response $response
     * @param array $dependants Array of all questions/choices depending on this question.
     * @param boolean $blankquestionnaire
     * @return object The check question context tags.
     *
     */
    protected function question_survey_display($response, $dependants, $blankquestionnaire=false) {
        // Check boxes.
        $otherempty = false;
        if (!empty($response)) {
            // Verify that number of checked boxes (nbboxes) is within set limits (length = min; precision = max).
            if (!empty($response->answers[$this->id])) {
                $otherempty = false;
                $nbboxes = count($response->answers[$this->id]);
                foreach ($response->answers[$this->id] as $answer) {
                    $choice = $this->choices[$answer->choiceid];
                    if ($choice->is_other_choice()) {
                        $otherempty = empty($answer->value);
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
                if ($nbboxes < $min || $nbboxes > $max) {
                    $msg = get_string('boxesnbreq', 'questionnaire');
                    if ($min == $max) {
                        $msg .= get_string('boxesnbexact', 'questionnaire', $min);
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
                    $this->add_notification($msg);
                }
            }
        }

        $choicetags = new \stdClass();
        $choicetags->qelements = [];
        foreach ($this->choices as $id => $choice) {
            $checkbox = new \stdClass();
            $contents = questionnaire_choice_values($choice->content);
            $checked = false;
            if (!empty($response->answers[$this->id]) ) {
                $checked = isset($response->answers[$this->id][$id]);
            }
            $checkbox->name = 'q'.$this->id.'['.$id.']';
            $checkbox->value = $id;
            $checkbox->id = 'checkbox_'.$id;
            $checkbox->label = format_text($contents->text, FORMAT_HTML, ['noclean' => true]).$contents->image;
            if ($checked) {
                $checkbox->checked = $checked;
            }
            if ($choice->is_other_choice()) {
                $checkbox->oname = 'q'.$this->id.'['.$choice->other_choice_name().']';
                $checkbox->ovalue = (isset($response->answers[$this->id][$id]) && !empty($response->answers[$this->id][$id]) ?
                    stripslashes($response->answers[$this->id][$id]->value) : '');
                $checkbox->label = format_text($choice->other_choice_display().'', FORMAT_HTML, ['noclean' => true]);
            }
            $choicetags->qelements[] = (object)['choice' => $checkbox];
        }
        if ($otherempty) {
            $this->add_notification(get_string('otherempty', 'questionnaire'));
        }
        return $choicetags;
    }

    /**
     * Return the context tags for the check response template.
     * @param \mod_questionnaire\responsetype\response\response $response
     * @return object The check question response context tags.
     */
    protected function response_survey_display($response) {
        static $uniquetag = 0;  // To make sure all radios have unique names.

        $resptags = new \stdClass();
        $resptags->choices = [];

        if (!isset($response->answers[$this->id])) {
            $response->answers[$this->id][] = new \mod_questionnaire\responsetype\answer\answer();
        }

        foreach ($this->choices as $id => $choice) {
            $chobj = new \stdClass();
            if (!$choice->is_other_choice()) {
                $contents = questionnaire_choice_values($choice->content);
                $choice->content = $contents->text.$contents->image;
                if (isset($response->answers[$this->id][$id])) {
                    $chobj->selected = 1;
                }
                $chobj->name = $id.$uniquetag++;
                $chobj->content = (($choice->content === '') ? $id : format_text($choice->content, FORMAT_HTML,
                    ['noclean' => true]));
            } else {
                $othertext = $choice->other_choice_display();
                if (isset($response->answers[$this->id][$id])) {
                    $oresp = $response->answers[$this->id][$id]->value;
                    $chobj->selected = 1;
                    $chobj->othercontent = (!empty($oresp) ? htmlspecialchars($oresp) : '&nbsp;');
                }
                $chobj->name = $id.$uniquetag++;
                $chobj->content = (($othertext === '') ? $id : $othertext);
            }
            $resptags->choices[] = $chobj;
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
        $nbrespchoices = 0;
        $valid = true;
        if (is_a($responsedata, 'mod_questionnaire\responsetype\response\response')) {
            // If $responsedata is a response object, look through the answers.
            if (isset($responsedata->answers[$this->id]) && !empty($responsedata->answers[$this->id])) {
                foreach ($responsedata->answers[$this->id] as $answer) {
                    if (isset($this->choices[$answer->choiceid]) && $this->choices[$answer->choiceid]->is_other_choice()) {
                        $valid = !empty($answer->value);
                    } else {
                        $nbrespchoices++;
                    }
                }
            }
        } else if (isset($responsedata->{'q'.$this->id})) {
            foreach ($responsedata->{'q'.$this->id} as $answer) {
                if (strpos($answer, 'other_') !== false) {
                    // ..."other" choice is checked but text box is empty.
                    $othercontent = "q".$this->id.substr($answer, 5);
                    if (trim($responsedata->$othercontent) == false) {
                        $valid = false;
                        break;
                    }
                    $nbrespchoices++;
                } else if (is_numeric($answer)) {
                    $nbrespchoices++;
                }
            }
        } else {
            return parent::response_valid($responsedata);
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
        if ($nbrespchoices && (($nbrespchoices < $min) || ($nbrespchoices > $max))) {
            // Number of ticked boxes is not within min and max set limits.
            $valid = false;
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
     * True if question provides mobile support.
     *
     * @return bool
     */
    public function supports_mobile() {
        return true;
    }

    /**
     * Preprocess choice data.
     */
    protected function form_preprocess_choicedata($formdata) {
        if (empty($formdata->allchoices)) {
            print_error(get_string('enterpossibleanswers', 'questionnaire'));
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
            if ($formdata->precise != 0) {
                $formdata->precise = max($formdata->length, $formdata->precise);
            }
        }
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
        $mobiledata->ischeckbox = true;
        return $mobiledata;
    }

    /**
     * @param $mobiledata
     * @return mixed
     */
    public function mobile_question_choices_display() {
        $choices = parent::mobile_question_choices_display();
        foreach ($choices as $choicenum => $choice) {
            // Add a fieldkey for each choice.
            $choices[$choicenum]->choicefieldkey = $this->mobile_fieldkey($choice->id);
            if ($choice->is_other_choice()) {
                $choices[$choicenum]->otherchoicekey = $this->mobile_fieldkey($choice->other_choice_name());
                $choices[$choicenum]->content = format_text($choice->other_choice_display(), FORMAT_HTML, ['noclean' => true]);
            }
        }
        return $choices;
    }

    /**
     * @param $response
     * @return array
     */
    public function get_mobile_response_data($response) {
        $resultdata = [];
        if (isset($response->answers[$this->id])) {
            foreach ($response->answers[$this->id] as $answer) {
                if (isset($this->choices[$answer->choiceid])) {
                    // Add a fieldkey for each choice.
                    $resultdata[$this->mobile_fieldkey($answer->choiceid)] = 1;
                    if ($this->choices[$answer->choiceid]->is_other_choice()) {
                        $resultdata[$this->mobile_fieldkey($this->choices[$answer->choiceid]->other_choice_name())] =
                            $answer->value;
                    }
                }
            }
        }
        return $resultdata;
    }
}