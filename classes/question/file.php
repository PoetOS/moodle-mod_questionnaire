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
 * This file contains the parent class for text question types.
 *
 * @author Laurent David
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\question;

use context_module;
use core_media_manager;
use form_filemanager;
use mod_questionnaire\file_storage;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class file extends question {

    /**
     * @return string
     */
    public function helpname() {
        return 'file';
    }

    /**
     * Override and return a form template if provided. Output of question_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function question_template() {
        return false;
    }

    /**
     * Override and return a response template if provided. Output of response_survey_display is iterpreted based on this.
     *
     * @return boolean | string
     */
    public function response_template() {
        return false;
    }

    /**
     * @return object|string
     */
    protected function responseclass() {
        return '\\mod_questionnaire\\responsetype\\file';
    }

    /**
     * @param \mod_questionnaire\responsetype\response\response $response
     * @param $descendantsdata
     * @param bool $blankquestionnaire
     * @return object|string
     */
    protected function question_survey_display($response, $descendantsdata, $blankquestionnaire = false) {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/filelib.php');
        $elname = 'q' . $this->id;
        $draftitemid = file_get_submitted_draft_itemid($elname);
        $component = 'mod_questionnaire';
        $options = $this->get_file_manager_option();
        file_prepare_draft_area($draftitemid, $this->context->id, $component, 'file', $this->id, $options);
        // Filemanager form element implementation is far from optimal, we need to rework this if we ever fix it...
        require_once("$CFG->dirroot/lib/form/filemanager.php");

        $fmoptions = array_merge(
            $options,
            [
                'client_id' => uniqid(),
                'itemid' => $draftitemid,
                'target' => $this->id,
                'name' => $elname
            ]
        );
        $fm = new form_filemanager((object) $fmoptions);
        $output = $PAGE->get_renderer('core', 'files');
        $html = $output->render($fm);

        $html .= '<input value="' . $draftitemid . '" name="' . $elname . '" type="hidden" />';
        $html .= '<input value="" id="' . $this->id . '" type="hidden" />';

        return $html;
    }

    private function get_file_manager_option() {
        return [
            'mainfile' => '',
            'subdirs' => false,
            'accepted_types' => array('image', '.pdf')
        ];
    }

    /**
     * @param \mod_questionnaire\responsetype\response\response $response
     * @return object|string
     */
    protected function response_survey_display($response) {
        global $PAGE, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/resourcelib.php');
        if (isset($response->answers[$this->id])) {
            $answer = reset($response->answers[$this->id]);
        } else {
            return '';
        }
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($answer->value);

        $moodleurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename());

        $mimetype = $file->get_mimetype();
        $title = '';

        $extension = resourcelib_get_extension($file->get_filename());

        $mediamanager = core_media_manager::instance($PAGE);
        $embedoptions = array(
            core_media_manager::OPTION_TRUSTED => true,
            core_media_manager::OPTION_BLOCK => true,
        );

        if (file_mimetype_in_typegroup($mimetype, 'web_image')) {  // It's an image
            $code = resourcelib_embed_image($moodleurl->out(), $title);

        } else if ($mimetype === 'application/pdf') {
            // PDF document
            $code = resourcelib_embed_pdf($moodleurl->out(), $title, get_string('view'));

        } else if ($mediamanager->can_embed_url($moodleurl, $embedoptions)) {
            // Media (audio/video) file.
            $code = $mediamanager->embed_url($moodleurl, $title, 0, 0, $embedoptions);

        } else {
            // We need a way to discover if we are loading remote docs inside an iframe.
            $moodleurl->param('embed', 1);

            // anything else - just try object tag enlarged as much as possible
            $code = resourcelib_embed_general($moodleurl, $title, get_string('view'), $mimetype);
        }

        $output = '';
        $output .= '<div class="response text">';
        $output .= $code;
        $output .= '</div>';
        return $output;
    }

    protected function form_length(\MoodleQuickForm $mform, $helpname = '') {
        return question::form_length_hidden($mform);
    }

    protected function form_precise(\MoodleQuickForm $mform, $helpname = '') {
        return question::form_precise_hidden($mform);
    }

}
