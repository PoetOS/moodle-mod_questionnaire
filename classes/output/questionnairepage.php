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

namespace mod_questionnaire\output;

use html_writer;
use mod_questionnaire\questionnaire;

/**
 * Contains class mod_questionnaire\output\viewpage
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class questionnairepage implements \renderable, \templatable {
    /**
     * The data to be exported.
     * @var array
     */
    protected $data;

    /**
     * The template to use for rendering.
     * @var string
     */
    protected $template = 'MUST BE OVERRIDDEN IN SUBCLASS';

    /**
     * Construct the renderable.
     * @param questionnaire $questionnaire The questionnaire object.
     */
    public function __construct(questionnaire $questionnaire) {
        throw new \coding_exception('Constructor must be implemented in subclass');
    }

    /**
     * Export the data for template.
     * @param \renderer_base $output
     * @return array The data to be used in the template.
     */
    public function export_for_template(\renderer_base $output): array {
        return $this->data;
    }

    /**
     * Get the template name.
     * @return string The template name.
     */
    public function template(): string {
        return $this->template;
    }

    /**
     * Add a message to the page.
     * @param string $message The message to add.
     */
    public function add_message(string $message) {
        $this->data['message'] = empty($this->data['message']) ? $message : ($this->data['message'] . $message);
    }

    /**
     * Add a message to the page.
     * @param string $message The message to add.
     */
    public function add_notification(string $message) {
        $this->data['notifications'][] = $message;
    }

    /**
     * Add info messages to the page.
     * @param string[] $info The info messages to add.
     */
    public function add_info(array $info) {
        $data = [];
        foreach ($info as $message) {
            $data[] = ['message' => $message];
        }
        $this->data['info'] = $data;
    }

    /**
     * Add intro information to the page.
     * @param questionnaire $questionnaire The questionnaire object.
     */
    public function add_intro_information(questionnaire $questionnaire) {
        if (!empty($questionnaire->surveytitle())) {
            $this->data['title'] = format_string($questionnaire->surveytitle());
        }
        if (!empty($questionnaire->surveysubtitle())) {
            $this->data['subtitle'] = format_string($questionnaire->surveysubtitle());
        }
        if ($questionnaire->surveyinfo()) {
            $infotext = file_rewrite_pluginfile_urls(
                $questionnaire->surveyinfo(),
                'pluginfile.php',
                $questionnaire->context()->id,
                'mod_questionnaire',
                'info',
                $questionnaire->surveyid()
            );
            $this->data['addinfo'] = format_text($infotext, FORMAT_HTML, ['noclean' => true]);
        }
    }
}
