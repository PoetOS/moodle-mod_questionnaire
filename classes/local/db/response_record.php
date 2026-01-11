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

/**
 * Response record class for Questionnaire.
 * Handles all operations for questionnaire responses.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class response_record extends \core\persistent {
    /** Table name for the persistent. */
    const TABLE = 'questionnaire_response';

    /** @var int The response id. */
    protected $id;

    /** @var int The questionnaire instance id. */
    protected $questionnaireid;

    /** @var int Submission timestamp. */
    protected $submitted;

    /** @var string Completed flag. */
    protected $complete;

    /** @var int Grade if any. */
    protected $grade;

    /** @var int The user id who submitted the response. */
    protected $userid;

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
                'description' => 'The response id.',
            ],
            'questionnaireid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The questionnaire instance id.',
            ],
            'submitted' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Submission timestamp.',
            ],
            'complete' => [
                'type' => PARAM_TEXT,
                'default' => 'n',
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Completed flag.',
            ],
            'grade' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_ALLOWED,
                'description' => 'Grade if any.',
            ],
            'userid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'The user id who submitted the response.',
            ],
        ];
    }
}
