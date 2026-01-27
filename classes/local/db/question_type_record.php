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
 * Question type record class for Questionnaire.
 * Handles all operations for questionnaire question types.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class question_type_record extends \core\persistent {
    /** Table name for the persistent. */
    const TABLE = 'questionnaire_question_type';

    /** @var int The question type id. */
    protected $id;

    /** @var int The code for the type. */
    protected $typeid;

    /** @var string The type name. */
    protected $type;

    /** @var string Whether the type has choices. */
    protected $haschoices;

    /** @var string The response table name. */
    protected $responsetable;

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
                'description' => 'The question type id.',
            ],
            'typeid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The code for the type.',
            ],
            'type' => [
                'type' => PARAM_TEXT,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'The type name.',
            ],
            'haschoices' => [
                'type' => PARAM_TEXT,
                'default' => 'y',
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Whether the type has choices.',
            ],
            'responsetable' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'The response table name.',
            ],
        ];
    }

    /**
     * Return a question_type_record instance from typeid.
     * @param int $typeid
     * @return bool|question_type_record|null
     */
    public static function from_typeid(int $typeid): ?self {
        $record = self::get_record(['typeid' => $typeid]);
        if ($record !== false) {
            return $record;
        } else {
            return null;
        }
    }
}
