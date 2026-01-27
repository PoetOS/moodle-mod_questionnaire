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

namespace mod_questionnaire\local\db;

use mod_questionnaire\local\questionnairelib;
use stdClass;

/**
 * Main module instance class for Questionnaire.
 * Does all of the heavy lifting for course module code for the standard module callbacks in lib.php.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class module_record extends \core\persistent {
    /** Table name for the persistent. */
    const TABLE = 'questionnaire';

    /** @var int The module instance id. */
    protected $id;

    /** @var int The course id that the instance is in */
    protected $course;

    /** @var string Name */
    protected $name;

    /** @var string Introduction */
    protected $intro;

    /** @var int Introduction format */
    protected $introformat;

    /** @var int Questionnaire type */
    protected $qtype;

    /** @var string Respondent type */
    protected $respondenttype;

    /** @var string Respondent eligibility */
    protected $respeligible;

    /** @var int Respondent view */
    protected $respview;

    /** @var int Notifications flag */
    protected $notifications;

    /** @var int Opening date */
    protected $opendate;

    /** @var int Closing date */
    protected $closedate;

    /** @var int Save / resume flag */
    protected $resume;

    /** @var int Navigate flag */
    protected $navigate;

    /** @var int Grade setting */
    protected $grade;

    /** @var int Survey id */
    protected $sid;

    /** @var int Modified timestamp */
    protected $timemodified;

    /** @var int Submission flag */
    protected $completionsubmit;

    /** @var int Auto numbering flag */
    protected $autonum;

     /** @var int Progress bar flag */
    protected $progressbar;

    /** @var int Deletion duration */
    protected $removeafter;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'id' => [
                'type' => PARAM_INT,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The module instance id.',
            ],
            'course' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The course id that the instance is in.',
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Name.',
            ],
            'intro' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'description' => 'Introduction.',
            ],
            'introformat' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Introduction format.',
            ],
            'qtype' => [
                'type' => PARAM_TEXT,
                'default' => '0',
                'null' => NULL_ALLOWED,
                'description' => 'Questionnaire type.',
            ],
            'respondenttype' => [
                'type' => PARAM_TEXT,
                'default' => 'fullname',
                'null' => NULL_ALLOWED,
                'description' => 'Respondent type.',
            ],
            'respeligible' => [
                'type' => PARAM_TEXT,
                'default' => 'all',
                'null' => NULL_ALLOWED,
                'description' => 'Respondent eligibility.',
            ],
            'respview' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Respondent view.',
            ],
            'notifications' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Notifications flag.',
            ],
            'opendate' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Opening date.',
            ],
            'closedate' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Closing date.',
            ],
            'resume' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Save / resume flag.',
            ],
            'navigate' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Navigate flag.',
            ],
            'grade' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Grade setting.',
            ],
            'sid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Survey id.',
            ],
            'timemodified' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Modified timestamp.',
            ],
            'completionsubmit' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Submission flag.',
            ],
            'autonum' => [
                'type' => PARAM_INT,
                'default' => 3,
                'null' => NULL_ALLOWED,
                'description' => 'Auto numbering flag.',
            ],
            'progressbar' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Progress bar flag.',
            ],
            'removeafter' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Deletion duration.',
            ],
        ];
    }

    /**
     * Given an object containing all the necessary data, (defined by the form in mod.html) this function will create and return
     * a new instance.
     * @param stdClass $formdata
     * @throws \moodle_exception
     * @return module_record
     */
    public static function create_from_formdata(stdClass $formdata): self {
        global $DB;

        return (new self(0, $formdata))->create();
    }
    /**
     * Create a module_record instance from a course module id.
     * @param int $cmid
     * @return self
     * @throws \dml_exception
     */
    public static function from_cmid(int $cmid): self {
        $cm = get_coursemodule_from_id(static::TABLE, $cmid, 0, false, MUST_EXIST);
        return new self($cm->instance);
    }
}
