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
 * Contains class mod_questionnaire\output\indexpage
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
namespace mod_questionnaire\output;

defined('MOODLE_INTERNAL') || die();

class indexrow implements \renderable, \templatable {

    /**
     * The title
     *
     * @var string
     */
    protected $topic;

    /**
     * The alignment
     *
     * @var string
     */
    protected $name;

    /**
     * The alignment
     *
     * @var string
     */
    protected $responses;

    /**
     * The alignment
     *
     * @var string
     */
    protected $type;

    /**
     * Construct
     *
     * @param array $headings An array of renderable headings
     */
    public function __construct($row, $alignment) {
        list($this->topic, $this->name, $this->responses, $this->type) = $row;
        $this->alignment = $alignment;
    }

    /**
     * Prepare data for use in a template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $data = array('topic' => $this->topic, 'name' => $this->name,
            'responses' => $this->responses, 'type' => $this->type);
        return $data;
    }
}