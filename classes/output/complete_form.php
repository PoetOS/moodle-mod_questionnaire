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

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;
use moodleform;

/**
 * Contains class mod_questionnaire\output\complete_form
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class complete_form extends moodleform {

    /** @var int */
    const MODE_COMPLETE = 1;
    /** @var int */
    const MODE_PRINT = 2;
    /** @var int */
    const MODE_VIEW_RESPONSE = 3;

    /** @var int */
    protected $mode;
    /** @var questionnaire */
    protected $questionnaire;

    /**
     * Constructor
     *
     * @param questionnaire $questionnaire
     * @param int $mode
     * @param array $customdata
     */
    public function __construct(questionnaire $questionnaire, ?int $mode = self::MODE_COMPLETE, ?array $customdata = null) {
        $this->mode = $mode;
        $this->questionnaire = $questionnaire;
        // TODO: redefine the id attribute.
        parent::__construct(
            customdata: $customdata,
            attributes: ['id' => 'phpesp_response'],
        );
    }

    /**
     * Define the form elements.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'a', $this->questionnaire->id());
        $mform->setType('a', PARAM_INT);
        $mform->addElement('hidden', 'sid', $this->questionnaire->surveyid());
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'rid', 0);
        $mform->setType('rid', PARAM_INT);
        $mform->addElement('hidden', 'sec', 0);
        $mform->setType('sec', PARAM_INT);

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Optional: custom validation.
     *
     * @param array $data
     * @param array $files
     * @return array of errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // add your own checks here
        // if ($data['completed'] < time()) {
        //     $errors['completed'] = get_string('invaliddate', 'questionnaire');
        // }

        return $errors;
    }
}