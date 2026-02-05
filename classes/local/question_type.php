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
    // Constants.
    /** @var int Define choose question type. */
    const QUESCHOOSE = 0;
    /** @var int Define Yes/No question type. */
    const QUESYESNO = 1;
    /** @var int Define text question type. */
    const QUESTEXT = 2;
    /** @var int Define essay question type. */
    const QUESESSAY = 3;
    /** @var int Define radio question type. */
    const QUESRADIO = 4;
    /** @var int Define check question type. */
    const QUESCHECK = 5;
    /** @var int Define drop question type. */
    const QUESDROP = 6;
    /** @var int Define rate question type. */
    const QUESRATE = 8;
    /** @var int Define date question type. */
    const QUESDATE = 9;
    /** @var int Define numeric question type. */
    const QUESNUMERIC = 10;
    /** @var int Define slider question type. */
    const QUESSLIDER = 11;
    /** @var int Define page break question type. */
    const QUESFILE = 12;
    /** @var int Define page break question type. */
    public const QUESPAGEBREAK = 99;
    /** @var int Define section text question type. */
    const QUESSECTIONTEXT = 100;

    /** @var string The type name. */
    public $type;

    /** @var string Whether the type has choices. */
    public $haschoices;

    /** @var string The response table name. */
    public $responsetable;

    /** @var array $qtypenames List of all question names. */
    private static $qtypenames = [
        self::QUESYESNO => 'yesno',
        self::QUESTEXT => 'text',
        self::QUESESSAY => 'essay',
        self::QUESRADIO => 'radio',
        self::QUESCHECK => 'check',
        self::QUESDROP => 'drop',
        self::QUESRATE => 'rate',
        self::QUESDATE => 'date',
        self::QUESFILE => 'file',
        self::QUESNUMERIC => 'numerical',
        self::QUESPAGEBREAK => 'pagebreak',
        self::QUESSECTIONTEXT => 'sectiontext',
        self::QUESSLIDER => 'slider',
    ];

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

    /**
     * Return the different question type names.
     * @param int $qtype
     * @return string
     */
    public static function qtypename(int $qtype): string {
        if (array_key_exists($qtype, self::$qtypenames)) {
            return self::$qtypenames[$qtype];
        } else {
            return('');
        }
    }

    /**
     * Return all of the different question type names.
     * @return array
     */
    public static function qtypenames(): array {
        return self::$qtypenames;
    }
}
