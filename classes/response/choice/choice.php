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
 * This defines a structured class to hold response choice answers.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package response
 * @copyright 2019, onwards Poet
 */

namespace mod_questionnaire\response\choice;
defined('MOODLE_INTERNAL') || die();

class choice {

    // Class properties.
    /** @var int $choiceid The id of the question choice this applies to. */
    public $choiceid;

    /** @var string $answer Any entered text portion of this response. */
    public $answer;

    /**
     * Choice constructor.
     * @param null $choiceid
     * @param null $answer
     */
    public function __construct($choiceid = null, $answer = null) {
        $this->choiceid = $choiceid;
        $this->answer = $answer;
    }
}