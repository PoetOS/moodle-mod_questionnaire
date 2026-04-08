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
 * Replaces the scattered define() constants throughout the plugin with typed
 * class constants and a DB-backed factory. All code referencing QUESCHOOSE etc.
 * should migrate to question_type::QUESCHOOSE.
 *
 * @package    mod_questionnaire
 * @copyright  2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class question_type {

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
    /** @var int Define file question type. */
    const QUESFILE = 12;
    /** @var int Define page break question type. */
    const QUESPAGEBREAK = 99;
    /** @var int Define section text question type. */
    const QUESSECTIONTEXT = 100;

    /** @var string The type name. */
    public string $type;

    /** @var string Whether the type has choices ('y' or 'n'). */
    public string $haschoices;

    /** @var string|null The response table name. */
    public ?string $responsetable;

    /** @var array Per-request cache of question_type instances keyed by typeid. */
    private static array $typecache = [];

    /** @var array Maps typeid integer → short name string. */
    private static array $qtypenames = [
        self::QUESYESNO      => 'yesno',
        self::QUESTEXT       => 'text',
        self::QUESESSAY      => 'essay',
        self::QUESRADIO      => 'radio',
        self::QUESCHECK      => 'check',
        self::QUESDROP       => 'drop',
        self::QUESRATE       => 'rate',
        self::QUESDATE       => 'date',
        self::QUESFILE       => 'file',
        self::QUESNUMERIC    => 'numerical',
        self::QUESPAGEBREAK  => 'pagebreak',
        self::QUESSECTIONTEXT => 'sectiontext',
        self::QUESSLIDER     => 'slider',
    ];

    /**
     * Construct from a question_type_record persistent.
     *
     * @param question_type_record $qtyperecord
     */
    public function __construct(question_type_record $qtyperecord) {
        $this->type = $qtyperecord->get('type');
        $this->haschoices = $qtyperecord->get('haschoices');
        $this->responsetable = $qtyperecord->get('responsetable');
    }

    /**
     * Return a question_type instance for the given typeid, or null if the typeid
     * does not exist in the questionnaire_question_type table.
     *
     * Results are cached per request so the DB is queried at most once per typeid.
     *
     * @param int $typeid
     * @return question_type|null
     */
    public static function from_typeid(int $typeid): ?self {
        if (!array_key_exists($typeid, self::$typecache)) {
            $record = question_type_record::from_typeid($typeid);
            self::$typecache[$typeid] = $record !== null ? new self($record) : null;
        }
        return self::$typecache[$typeid];
    }

    /**
     * Return the short name for a question type integer.
     *
     * @param int $qtype
     * @return string Empty string if the typeid is not known.
     */
    public static function qtypename(int $qtype): string {
        return self::$qtypenames[$qtype] ?? '';
    }

    /**
     * Return the full map of typeid => short name.
     *
     * @return array
     */
    public static function qtypenames(): array {
        return self::$qtypenames;
    }
}
