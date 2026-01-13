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
 * Question record class for Questionnaire.
 * Handles all operations for questionnaire questions.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class question_record extends \core\persistent {
    /** Table name for the persistent. */
    const TABLE = 'questionnaire_question';

    /** @var int The question id. */
    protected $id;

    /** @var int The survey id. */
    protected $surveyid;

    /** @var string Question name. */
    protected $name;

    /** @var int Question type id. */
    protected $typeid;

    /** @var int Result id. */
    protected $resultid;

    /** @var int Question length. */
    protected $length;

    /** @var int Precision. */
    protected $precise;

    /** @var int Question position. */
    protected $position;

    /** @var string Question content. */
    protected $content;

    /** @var string Required flag. */
    protected $required;

    /** @var int Deletion timestamp. */
    protected $deleted;

    /** @var string Extra data for additional fields. */
    protected $extradata;

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
                'description' => 'The question id.',
            ],
            'surveyid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The survey id.',
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Question name.',
            ],
            'typeid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Question type id.',
            ],
            'resultid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Result id.',
            ],
            'length' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Question length.',
            ],
            'precise' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Precision.',
            ],
            'position' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Question position.',
            ],
            'content' => [
                'type' => PARAM_RAW,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Question content.',
            ],
            'required' => [
                'type' => PARAM_TEXT,
                'default' => 'n',
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Required flag.',
            ],
            'deleted' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'The timestamp record last deleted.',
            ],
            'extradata' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Extra data for additional fields.',
            ],
        ];
    }
}
