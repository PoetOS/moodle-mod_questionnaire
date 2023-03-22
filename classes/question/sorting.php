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

namespace mod_questionnaire\question;

use html_writer;
use mod_questionnaire\edit_question_form;
use \questionnaire;

/**
 * Class for sorting question types.
 *
 * @author The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questionnaire
 *
 * @property \mod_questionnaire\responsetype\sorting $responsetype
 */
class sorting extends question {
    /**
     * Number of answers in question by default.
     */
    const NUM_ITEMS_DEFAULT = 3;

    /**
     * Minimum number of answers to show.
     */
    const NUM_ITEMS_MIN = 2;

    /**
     * Number of answers to add on demand.
     */
    const NUM_ITEMS_ADD = 1;

    /**
     * Rows count in answer field.
     */
    const TEXTFIELD_ROWS = 2;

    /**
     * Cols count in answer field.
     */
    const TEXTFIELD_COLS = 60;

    /**
     * Sorting data.
     * @var \stdClass|null
     */
    public ?\stdClass $sortingdata;

    /**
     * Constructor.
     * @param int $id
     * @param \stdClass $question
     * @param \context $context
     * @param array $params
     */
    public function __construct($id = 0, $question = null, $context = null, $params = []) {
        parent::__construct($id, $question, $context, $params);
        $this->sortingdata = json_decode($this->extradata);
    }

    /**
     * Name of table <questionnaire_response_sort>
     */
    public function table_name() {
        return "questionnaire_response_sort";
    }

    /**
     * Get response class name.
     *
     * @return string
     */
    protected function responseclass(): string {
        return '\\mod_questionnaire\\responsetype\\sorting';
    }

    /**
     * Get help name.
     *
     * @return string
     */
    public function helpname(): string {
        return 'sorting';
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     * @return string
     */
    public function question_template() {
        return 'mod_questionnaire/question_sorting';
    }

    /**
     * Override and return a response template if provided. Output of response_survey_display is iterpreted based on this.
     *
     * @return string
     */
    public function response_template() {
        return 'mod_questionnaire/response_sorting';
    }

    /**
     * Question specific display method.
     *
     * @param \stdClass $formdata
     * @param array $descendantsdata
     * @param bool $blankquestionnaire
     */
    protected function question_survey_display($formdata, $descendantsdata, $blankquestionnaire) {
        $questiontags = new \stdClass();
        $questiontags->qelements = new \stdClass();
        // Display list of sorting answers.
        $questiontags->qelements->sortinglist = $this->prepare_answers();
        $questiontags->qelements->qid = 'q' . $this->id;
        // Display type of sorting layout.
        $layout = $this->responsetype->questionnaire_sort_type_layout()[$this->get_layout()];
        $questiontags->qelements->sortingdirection = strtolower($layout);
        return $questiontags;
    }

    /**
     * Get layout sorting.
     * @return string
     */
    protected function get_layout(): string {
        if ($this->sortingdata instanceof \stdClass) {
            if (property_exists($this->sortingdata, 'sortingdirection')) {
                return $this->sortingdata->sortingdirection;
            }
        }
        return QUESTIONNAIRE_LAYOUT_VERTICAL;
    }

    /**
     * Return the consorting tags for the sorting response template.
     *
     * @param object $response
     * @return object The sorting question response tags.
     */
    protected function response_survey_display($response) {
        $resptags = new \stdClass();
        $res = isset($response->answers[$this->id]) ? reset($response->answers[$this->id]) : '';
        if (!empty($res) && $value = $res->value) {
            $resptags->content = new \stdClass();
            $resptags->content->qelements = new \stdClass();
            $resptags->content->qelements->sortinglist = $this->prepare_answers($value);
            $resptags->content->qelements->qid = 'q' . $this->id;
            $resptags->content->qelements->isresponse = true;
            $layout = $this->responsetype->questionnaire_sort_type_layout()[$this->get_layout()];
            $resptags->content->qelements->sortingdirection = strtolower($layout);
        }
        return $resptags;
    }

    /**
     * Return the length form element.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_length(\MoodleQuickForm $mform, $helpname = '') {
        return parent::form_length_hidden($mform);
    }

    /**
     * Return the precision form element.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_precise(\MoodleQuickForm $mform, $helpname = '') {
        return question::form_precise_hidden($mform);
    }

    /**
     * Add the form required field.
     *
     * @param \MoodleQuickForm $mform
     * @return \MoodleQuickForm
     */
    protected function form_required(\MoodleQuickForm $mform) {
        return $mform;
    }

    /**
     * Add the form name field.
     *
     * @param \MoodleQuickForm $mform
     * @return \MoodleQuickForm
     */
    protected function form_name(\MoodleQuickForm $mform) {
        $form = parent::form_name($mform);
        $form = self::form_direction($mform);
        return $form;
    }

    /**
     * Override if the question uses the extradata field.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_extradata(\MoodleQuickForm $mform, $helpname = "") {
        $form = parent::form_extradata($mform);
        $form = self::form_drap_drop_items($mform);
        return $form;
    }

    /**
     * Returns editor attributes.
     *
     * @return array
     */
    protected function get_editor_attributes(): array {
        return [
            'rows' => self::TEXTFIELD_ROWS,
            'cols' => self::TEXTFIELD_COLS,
        ];
    }

    /**
     * Returns editor options.
     *
     * @return array
     */
    protected function get_editor_options(): array {
        return [
            'context' => $this->context,
            'noclean' => true,
        ];
    }

    /**
     * Returns editor options.
     *
     * @return int
     */
    protected function get_answer_repeats(): int {
        $repeats = self::NUM_ITEMS_DEFAULT;
        if ($this->surveyid != 0) {
            $repeats = count($this->_customdata['answers']);
        } else if ($repeats < self::NUM_ITEMS_MIN) {
            $repeats = self::NUM_ITEMS_MIN;
        }
        return $repeats;
    }

    /**
     * Returns editor options.
     *
     * @param string $type
     * @param int $max
     * @return array
     */
    protected function get_addcount_options(string $type, int $max = 10): array {
        // Generate options.
        $options = [];
        for ($i = 1; $i <= $max; $i++) {
            if ($i == 1) {
                $options[$i] = get_string("sortingaddsingle{$type}", 'mod_questionnaire');
            } else {
                $options[$i] = get_string("sortingaddmultiple{$type}s", 'mod_questionnaire', $i);
            }
        }
        return $options;
    }

    /**
     * Adjust HTML editor and removal buttons.
     *
     * @param object $mform
     * @param string $name
     */
    protected function adjust_html_editors($mform, string $name): void {

        // Cache the number of formats supported
        // by the preferred editor for each format.
        $count = [];
        $ids = [];

        $answers = [];

        if (!empty($_POST['answer'])) {
            $answers = $_POST['answer'];
        } else if (!empty($extradata['answers'])) {
            $answers = $extradata['answers'];
        }

        if (!empty($answers)) {
            $ids = array_keys($answers);
        }

        $defaultanswerformat = FORMAT_MOODLE;

        $repeats = "count{$name}s"; // E.g. countanswers.
        if ($mform->elementExists($repeats)) {
            // Use mform element to get number of repeats.
            $repeats = $mform->getElement($repeats)->getValue();
        } else {
            // Determine number of repeats by object sniffing.
            $repeats = 0;
            while ($mform->elementExists($name . "[$repeats]")) {
                $repeats++;
            }
        }

        for ($i = 0; $i < $repeats; $i++) {
            $editor = $mform->getElement($name . "[$i]");

            $id = null;

            if (isset($ids[$i])) {
                $id = $ids[$i];
            }

            // The old/new name of the button to remove the HTML editor
            // old : the name of the button when added by repeat_elements
            // new : the simplified name of the button to satisfy "no_submit_button_pressed()" in lib/formslib.php.
            $oldname = $name . "removeeditor[{$i}]";
            $newname = $name . "removeeditor_{$i}";

            // Remove HTML editor, if necessary.
            if (optional_param($newname, 0, PARAM_RAW)) {
                $format = $this->reset_editor_format($editor, FORMAT_MOODLE);
                $_POST['answer'][$i]['format'] = $format; // Overwrite incoming data.
            } else if (!is_null($id)) {
                $format = $answers[$id]['format'];
            } else {
                $format = $this->reset_editor_format($editor, $defaultanswerformat);
            }

            // Check we have a submit button - it should always be there !!
            if ($mform->elementExists($oldname)) {
                if (! isset($count[$format])) {
                    $editor = editors_get_preferred_editor($format);
                    $count[$format] = $editor->get_supported_formats();
                    $count[$format] = count($count[$format]);
                }
                if ($count[$format] > 1) {
                    $mform->removeElement($oldname);
                } else {
                    $submit = $mform->getElement($oldname);
                    $submit->setName($newname);
                }
                $mform->registerNoSubmitButton($newname);
            }
        }
    }
    /**
     * Reset editor format.
     *
     * @param object $editor
     * @param string $format
     * @return string
     */
    protected function reset_editor_format($editor, string $format): string {
        $value = $editor->getValue();
        $value['format'] = $format;
        $editor->setValue($value);
        return $format;
    }

    /**
     * Add new form direction for edit question sorting.
     *
     * @param \MoodleQuickForm $mform
     * @return \MoodleQuickForm
     */
    protected function form_direction(\MoodleQuickForm $mform): \MoodleQuickForm {
        $helpname = 'sortingdirection';
        $mform->addElement('select', $helpname,
            get_string($helpname, 'mod_questionnaire'), $this->responsetype->questionnaire_sort_type_layout());
        $mform->addHelpButton($helpname, $helpname, 'mod_questionnaire');
        $mform->setDefault($helpname, $this->_customdata['sortingdirection'] ?? QUESTIONNAIRE_LAYOUT_VERTICAL);
        return $mform;
    }

    /**
     * Add new form drag and drop items.
     *
     * @param \MoodleQuickForm $mform
     * @return \MoodleQuickForm
     */
    protected function form_drap_drop_items(\MoodleQuickForm $mform): \MoodleQuickForm {
        global $OUTPUT;
        $type = 'answer';
        $types = $type . 's';
        $addtypes = 'add' . $types;
        $counttypes = 'count' . $types;
        $addtypescount = $addtypes . 'count';
        $addtypesgroup = $addtypes . 'group';
        $options = $this->get_addcount_options($type);

        $repeatnum = $this->get_answer_repeats();

        $count = optional_param($addtypescount, self::NUM_ITEMS_ADD, PARAM_INT);

        $name = 'sortingdraggableitem';
        $label = get_string($name, 'mod_questionnaire');

        $repeatarray = [];
        $repeatarray[] = $mform->createElement('header', $name, $label);
        $repeatarray[] = $mform->createElement('editor', 'answer', get_string($name, 'mod_questionnaire'),
            $this->get_editor_attributes(), $this->get_editor_options());
        $repeatarray[] = $mform->createElement('submit', 'answerremoveeditor',
            get_string('sortingremoveeditor', 'mod_questionnaire'),
            ['onclick' => 'skipClientValidation = true;']);
        $repeatoptions = [];
        $repeatoptions[$name] = ['expanded' => true];

        $possibleanswerslabel = get_string('possibleanswerssorting', 'questionnaire');
        $possibleanswers = html_writer::tag('b', $possibleanswerslabel);
        $required = html_writer::empty_tag('img', [
                'class' => 'req',
                'title' => get_string('required', 'questionnaire'),
                'alt' => get_string('required', 'questionnaire'),
                'src' => $OUTPUT->image_url('req'),
        ]);
        $helpicon = $OUTPUT->help_icon('possibleanswerssorting', 'questionnaire');
        $possibleanswershtml = $possibleanswers . ' ' . $required . ' ' . $helpicon;
        $mform->addElement('html', $possibleanswershtml);

        $this->_form->repeat_elements(
            $repeatarray, $repeatnum, $repeatoptions, $counttypes, $addtypes, $count, $label, true);
        $mform->removeElement($addtypes);
        $mform->addGroup([
            $mform->createElement('submit', $addtypes, get_string('add')),
            $mform->createElement('select', $addtypescount, '', $options),
        ], $addtypesgroup, '', ' ', false);

        if (!empty($this->_customdata) && $data = $this->_customdata['answers']) {
            $mform->setDefault('answer', $data);
        }

        // Adjust HTML editor and removal buttons.
        $this->adjust_html_editors($mform, 'answer');

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
     * Any preprocessing of general data.
     *
     * @param \stdClass $formdata
     * @return bool
     */
    protected function form_preprocess_data($formdata) {
        // Remove empty answers.
        $answers = array_filter($formdata->answer, [$this, 'is_not_blank']);
        $result = [];
        foreach (array_values($answers) as $index => $answer) {
            $result[] = (object) [
                    'index' => $index,
                    'text' => $answer['text'],
                    'format' => $answer['format'],
            ];
        }
        $sortingdata = (object) [
                'answers' => $result,
                'sortingdirection' => $formdata->sortingdirection,
        ];
        $formdata->extradata = json_encode($sortingdata);
        return parent::form_preprocess_data($formdata);
    }

    /**
     * Callback function for filtering answers with array_filter
     *
     * @param mixed $value
     * @return bool If true, this item should be saved.
     */
    public function is_not_blank($value): bool {
        if (is_array($value)) {
            $value = $value['text'];
        }
        $value = clean_param($value, PARAM_RAW_TRIMMED);
        return ($value || $value === '0');
    }

    /**
     * Custom edit form of questionnaire.
     *
     * @param edit_question_form $form The main moodleform object.
     * @param questionnaire $questionnaire The questionnaire being edited.
     * @return bool
     */
    public function edit_form(edit_question_form $form, questionnaire $questionnaire): bool {
        $this->_form =& $form;
        if (!empty($this->qid)) {
            $question = $questionnaire->questions[$this->qid];
            $data = $this->sortingdata;
            $this->_customdata['sortingdirection'] = $data->sortingdirection ?? QUESTIONNAIRE_LAYOUT_VERTICAL;
            if (!empty($data->answers)) {
                foreach ($data->answers as $index => $answer) {
                    $this->_customdata['answers'][$index] = $answer;
                }
            }
        }
        return parent::edit_form($form, $questionnaire);
    }

    /**
     * Override and return false if not supporting mobile app.
     *
     * @param int $qnum
     * @param bool $autonum
     * @return \stdClass
     */
    public function mobile_question_display($qnum, $autonum = false) {
        $mobiledata = parent::mobile_question_display($qnum, $autonum);
        $mobiledata->issorting = true;
        return $mobiledata;
    }

    /**
     * Override and return false if not supporting mobile app.
     *
     * @return array
     */
    public function mobile_question_choices_display() {
        $choices = [];
        $data = $this->sortingdata;
        if (!empty($data->answers)) {
            foreach ($data->answers as $index => $answer) {
                $choices[$index] = new \stdClass();
                $choices[$index]->content = format_text($answer->text, $answer->format ?? FORMAT_MOODLE);
                $choices[$index]->index = $index;
            }
        }
        return $choices;
    }

    /**
     * Return the mobile response data.
     * @param \stdClass $response
     * @return array
     */
    public function get_mobile_response_data($response) {
        $resultdata = [];
        if (isset($response->answers[$this->id])) {
            foreach ($response->answers[$this->id] as $answer) {
                $resultdata[$this->mobile_fieldkey()] = $answer->value;
            }
        }
        return $resultdata;
    }

    /**
     * Prepare answers before pass to response type.
     * @param string|null $value
     * @param bool $istext
     * @return array|string
     */
    public function prepare_answers(?string $value = null, bool $istext = false) {
        return $this->responsetype->prepare_answers($this->sortingdata->answers, $value, $istext);
    }
}
