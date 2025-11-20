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

use stdClass;

/**
 * Main module instance class for Questionnaire.
 * Does all of the heavy lifting for course module code for the standard module callbacks in lib.php.
 *
 * @package mod_questionnaire
 * @copyright  2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class instance {
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
     * The class constructor
     * @param stdClass|null $moddata The data from the module instance (from the course_modules table)
     */
    public function __construct(?stdClass $moddata = null) {
        if (is_null($moddata)) {
            $moddata = new stdClass();
        }
        $this->id = $moddata->instance ?? ($moddata->id ?? 0);
        $this->course = $moddata->course ?? 0;
        $this->name = $moddata->name ?? '';
        $this->intro = $moddata->intro ?? '';
        $this->introformat = $moddata->introformat ?? 0;
        $this->qtype = $moddata->qtype ?? '';
        $this->respondenttype = $moddata->respondenttype ?? '';
        $this->respeligible = $moddata->respeligible ?? '';
        $this->respview = $moddata->respview ?? 0;
        $this->notifications = $moddata->notifications ?? 0;
        $this->opendate = $moddata->opendate ?? 0;
        $this->closedate = $moddata->closedate ?? 0;
        $this->resume = $moddata->resume ?? 0;
        $this->navigate = $moddata->navigate ?? 0;
        $this->grade = $moddata->grade ?? 0;
        $this->sid = $moddata->sid ?? 0;
        $this->timemodified = $moddata->timemodified ?? 0;
        $this->completionsubmit = $moddata->completionsubmit ?? 0;
        $this->autonum = $moddata->autonum ?? 0;
        $this->progressbar = $moddata->progressbar ?? 0;
        $this->removeafter = $moddata->removeafter ?? 0;
    }
}
