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
 * This defines a structured class to hold responses.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package response
 * @copyright 2019, onwards Poet
 */

namespace mod_questionnaire\responsetype\response;
defined('MOODLE_INTERNAL') || die();

class response {

    // Class properties.

    /** @var int $rid The id of the response this applies to. */
    public $rid;

    /** @var int $questionid The id of the question this response applies to. */
    public $questionid;

    /** @var string $content The choiceid of this response (if applicable). */
    public $choiceid;

    /** @var string $value The value of this response (if applicable). */
    public $value;

    /**
     * Choice constructor.
     * @param null $rid
     * @param null $questionid
     * @param null $choiceid
     * @param null $value
     */
    public function __construct($rid = null, $questionid = null, $choiceid = null, $value = null) {
        $this->rid = $rid;
        $this->questionid = $questionid;
        $this->choiceid = $choiceid;
        $this->value = $value;
    }

    /**
     * Create and return a choice object from data.
     *
     * @param object | array $responsedata The data to load.
     * @return response
     */
    public static function create_from_data($responsedata) {
        if (!is_array($responsedata)) {
            $responsedata = (array)$responsedata;
        }

        $properties = array_keys(get_class_vars(__CLASS__));
        foreach ($properties as $property) {
            if (!isset($responsedata[$property])) {
                $choicedata[$property] = null;
            }
        }

        return new response($choicedata['rid'], $choicedata['questionid'], $choicedata['choiceid'], $choicedata['value']);
    }
}