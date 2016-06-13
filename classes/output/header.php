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
 * Contains class mod_questionnaire\output\indexpage
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
namespace mod_questionnaire\output;

defined('MOODLE_INTERNAL') || die();

class header implements \renderable {

    /** @var stdClass the assign record  */
    public $course = null;
    /** @var mixed context|null the context record  */
    public $context = null;
    /** @var int coursemoduleid - The course module id */
    public $coursemoduleid = 0;

    /**
     * Constructor
     *
     * @param stdClass $questionnaire  - the questionnaire object
     * @param mixed $context context|null the course module context
     * @param int $coursemoduleid  - the course module id
     */
    public function __construct(\stdClass $course,
                                $context,
                                $coursemoduleid) {
        $this->course = $course;
        $this->context = $context;
        $this->coursemoduleid = $coursemoduleid;
    }
}