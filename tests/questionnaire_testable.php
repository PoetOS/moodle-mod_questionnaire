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

use mod_questionnaire\local\db\questionnaire_record;
use mod_questionnaire\local\db\survey_record;

/**
 * Testable subclass of questionnaire that bypasses the real constructor.
 *
 * Allows injecting questionnaire_record and survey_record directly so
 * business-logic methods can be unit tested without a real course module.
 *
 * @package    mod_questionnaire
 * @copyright  2025 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_testable extends questionnaire {
    /**
     * Construct with injected records, bypassing the real questionnaire constructor.
     *
     * @param questionnaire_record $modulerecord
     * @param survey_record $surveyrecord
     */
    public function __construct(questionnaire_record $modulerecord, survey_record $surveyrecord) {
        $this->modulerecord = $modulerecord;
        $this->surveyrecord = $surveyrecord;
        $this->questions = [];
        $this->questionsbysec = [];
    }
}
