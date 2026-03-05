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

use mod_questionnaire\local\question\question;
use mod_questionnaire\questionnaire;
use moodleform;
use renderer_base;

/**
 * Contains class mod_questionnaire\output\complete_form
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class complete_form {

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
    /** @var renderer_base */
    protected $output;
    /** @var array  Mustache context */
    protected $context;
    /** @var string */
    protected $renderedhtml;

    /**
     * Constructor
     *
     * @param questionnaire $questionnaire
     * @param renderer_base $output
     * @param int $mode
     * @param array $customdata
     */
    public function __construct(questionnaire $questionnaire, renderer_base $output, ?int $mode = self::MODE_COMPLETE, ?array $customdata = null) {
        $this->mode = $mode;
        $this->questionnaire = $questionnaire;
        $this->output = $output;
        $this->context = $this->form_start();
    }

    /**
     * Override the form start to set the form attributes.
     * @return array
     */
    protected function form_start(): array {
        return [
            'action' => '',
            'referer' => '',
            'a' => $this->questionnaire->id(),
            'sid' => $this->questionnaire->surveyid(),
            'rid' => 0,
            'sec' => 0,
            'sesskey' => sesskey(),
        ];
    }

    /**
     * Summary of render
     * @return string
     */
    public function render(): string {
        return $this->output->render_from_template('mod_questionnaire/surveyform', $this->context);
    }
}
