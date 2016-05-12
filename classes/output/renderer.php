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
 * Contains class mod_questionnaire\output\renderer
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
namespace mod_questionnaire\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {

    /**
     * Render a questionnaire index page.
     *
     * @param \templatable $indexpage
     * @return string|boolean
     */
    public function render_indexpage(\templatable $indexpage) {
        $data = $indexpage->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/indexpage', $data);
    }

    /**
     * Renders the HTML for the index page.
     * @param array $headings Headings for the display columns.
     * @param array $align Alignment for each column.
     * @param array $data All of the table data.
     * @return string
     */
/*    public function render_index($headings, $align, $data) {
        $table = new \html_table();
        $table->head = $headings;
        $table->align = $align;
        $table->data = $data;

        return \html_writer::table($table);
    } */
}