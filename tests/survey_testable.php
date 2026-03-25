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

namespace mod_questionnaire;

use mod_questionnaire\local\db\survey_record;

/**
 * Testable subclass of survey that bypasses the real constructor.
 *
 * Allows injecting a survey_record and question arrays directly so
 * business-logic methods can be unit tested without a real course module.
 *
 * @package    mod_questionnaire
 * @copyright  2025 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class survey_testable extends survey {
    /**
     * Construct with injected record, bypassing the real survey constructor.
     *
     * @param survey_record $surveyrecord
     */
    public function __construct(survey_record $surveyrecord) {
        $this->surveyrecord = $surveyrecord;
        $this->context = null;
        $this->questions = [];
        $this->questionsbysec = [];
    }

    /**
     * Inject question objects for unit tests (bypasses DB loading).
     *
     * @param array $questions Keyed by question id.
     */
    public function set_questions(array $questions): void {
        $this->questions = $questions;
    }

    /**
     * Inject the questions-by-section map for unit tests.
     *
     * @param array $questionsbysec Array of question object arrays, keyed by 1-based section number.
     */
    public function set_questions_by_sec(array $questionsbysec): void {
        $this->questionsbysec = $questionsbysec;
    }
}
