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
 * Survey class - represents a questionnaire_survey record.
 *
 * @package mod_questionnaire
 * @copyright 2025 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class survey_record extends \core\persistent {
    /** Table name for the persistent. */
    const TABLE = 'questionnaire_survey';

    /** @var int Primary key. */
    protected $id;

    /** @var string Survey name. */
    protected $name;

    /** @var int|null Course ID. */
    protected $courseid;

    /** @var string Survey realm/scope. */
    protected $realm;

    /** @var int Survey status. */
    protected $status;

    /** @var string Survey title. */
    protected $title;

    /** @var string|null Contact email. */
    protected $email;

    /** @var string|null Survey subtitle. */
    protected $subtitle;

    /** @var string|null Survey information/description. */
    protected $info;

    /** @var string|null Survey theme. */
    protected $theme;

    /** @var string|null Thank you page URL. */
    protected $thankspage;

    /** @var string|null Thank you page heading. */
    protected $thankhead;

    /** @var string|null Thank you page body content. */
    protected $thankbody;

    /** @var int|null Number of feedback sections. */
    protected $feedbacksections;

    /** @var string|null Feedback notes. */
    protected $feedbacknotes;

    /** @var int|null Whether to show feedback scores. */
    protected $feedbackscores;

    /** @var string|null Chart type for results display. */
    protected $charttype;

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
                'description' => 'Primary key.',
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Survey name.',
            ],
            'courseid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Course ID.',
            ],
            'realm' => [
                'type' => PARAM_TEXT,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Survey realm/scope.',
            ],
            'status' => [
                'type' => PARAM_INT,
                'null' => NULL_NOT_ALLOWED,
                'default' => 0,
                'description' => 'Survey status.',
            ],
            'title' => [
                'type' => PARAM_TEXT,
                'null' => NULL_NOT_ALLOWED,
                'description' => 'Survey title.',
            ],
            'email' => [
                'type' => PARAM_EMAIL,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Contact email.',
            ],
            'subtitle' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Survey subtitle.',
            ],
            'info' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Survey information/description.',
            ],
            'theme' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Survey theme.',
            ],
            'thankspage' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Thank you page URL.',
            ],
            'thankhead' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Thank you page heading.',
            ],
            'thankbody' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Thank you page body content.',
            ],
            'feedbacksections' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => 0,
                'description' => 'Number of feedback sections.',
            ],
            'feedbacknotes' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Feedback notes.',
            ],
            'feedbackscores' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => 0,
                'description' => 'Whether to show feedback scores.',
            ],
            'charttype' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'description' => 'Chart type for results display.',
            ],
        ];
    }
}
