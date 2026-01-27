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

namespace mod_questionnaire\local;

use mod_questionnaire\local\db\question_type_record;

/**
 * The main class for question type definitions.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class question_type {
    /** @var string The type name. */
    public $type;

    /** @var string Whether the type has choices. */
    public $haschoices;

    /** @var string The response table name. */
    public $responsetable;

    /**
     * Summary of __construct
     * @param question_type_record $qtyperecord
     */
    public function __construct(question_type_record $qtyperecord) {
        $this->type = $qtyperecord->get('type');
        $this->haschoices = $qtyperecord->get('haschoices');
        $this->responsetable = $qtyperecord->get('responsetable');
    }

    /**
     * Return a question_type_record instance from typeid.
     * @param int $typeid
     * @return bool|question_type_record|null
     */
    public static function from_typeid(int $typeid): ?self {
        return new self(question_type_record::from_typeid($typeid));
    }
}