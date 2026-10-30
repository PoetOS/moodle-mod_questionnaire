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

namespace mod_questionnaire\local\feedback;

use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/questionnaire/lib.php');

/**
 * The feedback section editor: the section's label and heading, which questions count towards it
 * and with what weight (sections mode only), and its messages as score ranges.
 *
 * Expects $customdata['questionnaire'] (the questionnaire) and $customdata['section'] (the section being edited).
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author Joseph Rezeau (based on Quiz by Tim Hunt)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class feedback_section_form extends \moodleform {
    /** @var \context The questionnaire's context. */
    public $context;

    /**
     * Form definition.
     */
    public function definition() {
        $questionnaire = $this->_customdata['questionnaire'];
        $section = $this->_customdata['section'];
        $this->context = $questionnaire->context();
        $mform = $this->_form;

        if ($questionnaire->feedback()->mode() == 1) {
            $heading = get_string('feedbackglobalheading', 'questionnaire');
            $messagesheading = get_string('feedbackglobalmessages', 'questionnaire');
        } else {
            $heading = get_string('feedbacksectionheading', 'questionnaire', format_string($section->sectionlabel));
            $messagesheading = get_string('feedbackmessages', 'questionnaire', format_string($section->sectionlabel));
        }

        $mform->addElement('header', 'contenthdr', $heading);
        $mform->addElement(
            'text',
            'sectionlabel',
            get_string('feedbacksectionlabel', 'questionnaire'),
            ['size' => '50', 'maxlength' => '50']
        );
        $mform->setType('sectionlabel', PARAM_TEXT);
        $mform->addRule('sectionlabel', null, 'required', null, 'client');
        $mform->addHelpButton('sectionlabel', 'feedbacksectionlabel', 'questionnaire');

        $mform->addElement(
            'editor',
            'sectionheading',
            get_string('feedbacksectionheadingtext', 'questionnaire'),
            null,
            ['maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true]
        );
        $mform->setType('sectionheading', PARAM_RAW);
        $mform->addHelpButton('sectionheading', 'feedbackheading', 'questionnaire');

        if ($questionnaire->feedback()->mode() > 1) {
            $this->add_question_fields($questionnaire, $section);
        }

        $mform->addElement('header', 'feedbackhdr', $messagesheading);
        $mform->addHelpButton('feedbackhdr', 'feedback', 'questionnaire');
        $mform->setExpanded('feedbackhdr');
        $this->add_range_fields($questionnaire, count($section->sectionfeedback));

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sectionid', 0);
        $mform->setType('sectionid', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Fill in each range's "from" value from the boundary of the range above it.
     */
    public function definition_after_data() {
        $mform = $this->_form;
        $from = '100%';
        foreach ($this->range_indexes() as $i) {
            $mform->getElement("rangestart[$i]")->setText($this->range_start_html($from));
            $boundary = self::parse_boundary((string) $mform->getElementValue("feedbackboundaries[$i]"));
            $from = ($boundary === null) ? '…' : format_float($boundary, -1) . '%';
        }
    }

    /**
     * Load the section's existing data, including each range's message draft area and boundary.
     *
     * @param array|\stdClass $defaultvalues
     */
    public function set_data($defaultvalues) {
        $defaultvalues = (array) $defaultvalues;
        $key = 0;
        foreach ($this->_customdata['section']->sectionfeedback as $feedback) {
            $draftid = file_get_submitted_draft_itemid("feedbacktext[$key]");
            $defaultvalues["feedbacktext[$key]"] = [
                'text' => file_prepare_draft_area(
                    $draftid,
                    $this->context->id,
                    'mod_questionnaire',
                    'feedback',
                    (int) $feedback->id,
                    null,
                    \mod_questionnaire\local\trusttext::for_edit(
                        $feedback->feedbacktext,
                        (int)$feedback->feedbacktexttrust,
                        $this->context
                    )
                ),
                'format' => FORMAT_HTML,
                'itemid' => $draftid,
            ];
            $defaultvalues["feedbackboundaries[$key]"] = ($feedback->minscore > 0) ? format_float($feedback->minscore, -1) : '';
            $key++;
        }
        parent::set_data($defaultvalues);
    }

    /**
     * Form validation: weights, and range boundaries that step down from 100% in order.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach ($data['include'] ?? [] as $qid => $included) {
            $weight = $data['weight'][$qid] ?? null;
            if ($included && (!is_numeric($weight) || $weight <= 0 || $weight > 1)) {
                $errors["questiongroup_$qid"] = get_string('feedbackweighterror', 'questionnaire');
            }
        }

        // Ranges below the last one with a boundary or a message are unused and ignored.
        $rows = [];
        foreach (array_keys($data['feedbacktext'] ?? []) as $i) {
            $text = trim(strip_tags($data['feedbacktext'][$i]['text'] ?? ''));
            $boundary = trim($data['feedbackboundaries'][$i] ?? '');
            $rows[$i] = ($text !== '' || $boundary !== '');
        }
        while (!empty($rows) && !end($rows)) {
            array_pop($rows);
        }

        $previous = 100.0;
        $last = array_key_last($rows);
        foreach (array_keys($rows) as $i) {
            $value = trim($data['feedbackboundaries'][$i] ?? '');
            if ($value === '') {
                if ($i !== $last) {
                    $errors["feedbackboundaries[$i]"] = get_string('feedbackerrorboundarymissing', 'questionnaire');
                }
                continue;
            }
            $boundary = self::parse_boundary($value);
            if ($boundary === null) {
                $errors["feedbackboundaries[$i]"] = get_string('feedbackerrorboundaryformat', 'questionnaire');
            } else if ($boundary <= 0 || $boundary >= 100) {
                $errors["feedbackboundaries[$i]"] = get_string('feedbackerrorboundaryoutofrange', 'questionnaire');
            } else if ($boundary >= $previous) {
                $errors["feedbackboundaries[$i]"] = get_string('feedbackerrororder', 'questionnaire');
            } else {
                $previous = $boundary;
            }
        }
        return $errors;
    }

    /**
     * Read a range boundary as a percentage: a number, optionally followed by '%'.
     *
     * @param string $value
     * @return float|null The percentage, or null when the value is empty or not a number.
     */
    public static function parse_boundary(string $value): ?float {
        $value = trim($value);
        if (str_ends_with($value, '%')) {
            $value = trim(substr($value, 0, -1));
        }
        $value = unformat_float($value, true);
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * The indexes of the range rows on the form, in order (deleted rows have no elements).
     *
     * @return int[]
     */
    protected function range_indexes(): array {
        $mform = $this->_form;
        $indexes = [];
        $repeats = (int) $mform->getElementValue('range_repeats');
        for ($i = 0; $i < $repeats; $i++) {
            if ($mform->elementExists("rangestart[$i]")) {
                $indexes[] = $i;
            }
        }
        return $indexes;
    }

    /**
     * The include checkbox and weight for each question that can be used for feedback.
     *
     * @param \mod_questionnaire\questionnaire $questionnaire
     * @param section $section
     */
    protected function add_question_fields($questionnaire, section $section): void {
        $mform = $this->_form;
        $mform->addElement(
            'header',
            'questionshdr',
            get_string('feedbacksectionquestions', 'questionnaire', format_string($section->sectionlabel))
        );
        $mform->setExpanded('questionshdr');
        $mform->addElement('static', 'questionsintro', '', get_string('feedbackquestionsintro', 'questionnaire'));

        foreach ($questionnaire->questions() as $question) {
            if (!$question->valid_feedback()) {
                continue;
            }
            $qid = $question->id();
            $name = format_string($question->name());
            $weight = $section->scorecalculation[$qid] ?? null;
            $include = get_string('feedbackincludequestion', 'questionnaire');
            $weightlabel = html_writer::span(get_string('feedbackweight', 'questionnaire'), 'ms-3');
            $weightfor = get_string('feedbackweightfor', 'questionnaire', $name);
            $group = [
                $mform->createElement('advcheckbox', "include[$qid]", '', $include),
                $mform->createElement('static', "weightlabel[$qid]", '', $weightlabel),
                $mform->createElement('text', "weight[$qid]", $weightfor, ['size' => 4]),
            ];
            $mform->addGroup($group, "questiongroup_$qid", $name, ' ', false);
            $mform->setType("weight[$qid]", PARAM_RAW_TRIMMED);
            $mform->setDefault("include[$qid]", $weight !== null ? 1 : 0);
            // Scoring treats an empty or zero weight as 1, so show that.
            $mform->setDefault("weight[$qid]", empty($weight) || $weight == section::NOSCORE ? '1' : format_float($weight, -1));
            $mform->hideIf("weightlabel[$qid]", "include[$qid]", 'notchecked');
            $mform->hideIf("weight[$qid]", "include[$qid]", 'notchecked');
        }
    }

    /**
     * The repeated range rows: where the range starts, where it ends, its message and a remove button.
     *
     * @param \mod_questionnaire\questionnaire $questionnaire
     * @param int $existing The number of ranges the section already has.
     */
    protected function add_range_fields($questionnaire, int $existing): void {
        global $PAGE;
        $mform = $this->_form;
        $mform->addElement('static', 'rangesintro', '', get_string('feedbackrangesintro', 'questionnaire'));

        $rangerow = [
            $mform->createElement('static', 'rangestart', get_string('feedbackrange', 'questionnaire', '{no}'), ''),
            $mform->createElement('text', 'feedbackboundaries', get_string('feedbackrangeend', 'questionnaire'), ['size' => 6]),
            $mform->createElement(
                'editor',
                'feedbacktext',
                get_string('feedbackrangemessage', 'questionnaire'),
                null,
                ['maxfiles' => EDITOR_UNLIMITED_FILES, 'context' => $questionnaire->context()]
            ),
            $mform->createElement(
                'submit',
                'removerange',
                get_string('feedbackremoverange', 'questionnaire'),
                [],
                false,
                ['customclassoverride' => 'btn-outline-danger']
            ),
        ];
        $options = [
            'feedbacktext' => ['type' => PARAM_RAW],
            'feedbackboundaries' => ['type' => PARAM_RAW_TRIMMED],
        ];
        $this->repeat_elements(
            $rangerow,
            max($existing, 3),
            $options,
            'range_repeats',
            'addrange',
            1,
            get_string('feedbackaddrange', 'questionnaire'),
            true,
            'removerange'
        );
        $PAGE->requires->js_call_amd('mod_questionnaire/feedback_ranges', 'init', [$mform->getAttribute('id')]);
    }

    /**
     * The "From X%" text shown at the start of a range.
     *
     * @param string $from The formatted percentage the range starts at, or '…' when not known yet.
     * @return string
     */
    protected function range_start_html(string $from): string {
        return get_string('feedbackrangefrom', 'questionnaire', '<span data-region="rangefrom">' . s($from) . '</span>');
    }
}
