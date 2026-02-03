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
 * Quest choice record class for Questionnaire.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class quest_choice_record extends \core\persistent {
    /** Table name for the persistent. */
    const TABLE = 'questionnaire_quest_choice';

    /** @var int The choice id. */
    protected $id;

    /** @var int The question id this choice belongs to. */
    protected $questionid;

    /** @var string The choice content. */
    protected $content;

    /** @var string|null Optional value/score for the choice. */
    protected $value;

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
                'description' => 'The choice id.',
            ],
            'questionid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The question id this choice belongs to.',
            ],
            'content' => [
                'type' => PARAM_TEXT,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The choice content.',
            ],
            'value' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Optional value/score for the choice.',
            ],
        ];
    }
}