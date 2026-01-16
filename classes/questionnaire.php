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

        $this->surveyrecord = new survey_record($this->modulerecord->get('sid'));
    }
}
