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

use mod_questionnaire\local\db\module_record;
use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\question\question;

/**
 * The main class used to access and manage the questionnaire module.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class questionnaire {
    /** @var \mod_questionnaire\local\db\module_record The module record instance. */
    public $modulerecord;

    /** @var \mod_questionnaire\local\db\survey_record The survey record instance. */
    public $surveyrecord;

    /** @var \mod_questionnaire\local\question\questionold[] The list of question objects. */
    public $questions = [];

    /**
     * Summary of __construct
     * @param int $mid
     * @param module_record|null $modulerecord
     */
    public function __construct(int $mid = 0, ?module_record $modulerecord = null) {
        if (!empty($mid)) {
            $this->modulerecord = new module_record($mid);
        } else if (!empty($modulerecord)) {
            $this->modulerecord = $modulerecord;
        } else {
            throw new \coding_exception('Either mid or modulerecord must be provided to construct a questionnaire object.');
        }

        try {
            $this->surveyrecord = new survey_record($this->modulerecord->get('sid'));
        } catch (\dml_missing_record_exception $e) {
            $this->surveyrecord = new survey_record();
        }
        $this->load_questions();
    }

    /**
     * Create a questionnaire instance from a course module id.
     * @param int $cmid
     * @return self
     * @throws \dml_exception
     */
    public static function create_from_cmid(int $cmid) {
        return new self(0, module_record::create_from_cmid($cmid));
    }

    /**
     * Load all questions for this questionnaire into the questions array.
     * @return void
     */
    protected function load_questions() {
        $sid = $this->surveyrecord->get('id');
        if (!empty($sid)) {
            $questionrecs = \mod_questionnaire\local\db\question_record::questions_for_survey($sid);
            foreach ($questionrecs as $questionrec) {
                $this->questions[$questionrec->get('id')] = question::question_builder($questionrec->get('typeid'), $questionrec);
            }
        }
    }
}
