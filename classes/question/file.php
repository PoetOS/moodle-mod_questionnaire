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
namespace mod_questionnaire\question;
use core_media_manager;
use form_filemanager;
use mod_questionnaire\responsetype\response\response;
use moodle_url;
use MoodleQuickForm;

/**
 * This file contains the parent class for text question types.
 *
 * @author Laurent David
 * @author Martin Cornu-Mansuy
 * @copyright 2023 onward CALL Learning <martin@call-learning.fr>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_questionnaire
 */
class file extends question {

    /**
     * Get name.
     *
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
     * Get response class.
     *
     * @return object|string
     */
    protected function responseclass() {
        return '\\mod_questionnaire\\responsetype\\file';
    }

    /**
     * Survey display output.
     *
     * @param response $response
     * @param object $descendantsdata
     * @param bool $blankquestionnaire
     * @return string
     */
    protected function question_survey_display($response, $descendantsdata, $blankquestionnaire = false) {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/filelib.php');

        $elname = 'q' . $this->id;
        // Make sure there is a response, fetch the draft id from the original request.
        if (isset($response->answers[$this->id]) && !empty($response->answers[$this->id]) && isset($_REQUEST[$elname . 'draft'])) {
            $draftitemid = (int)$_REQUEST[$elname . 'draft'];
        } else {
            $draftitemid = file_get_submitted_draft_itemid($elname);
        }
        $options = self::get_file_manager_option();
        if ($draftitemid > 0) {
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_questionnaire', 'file', $this->id, $options);
        } else {
            $draftitemid = file_get_unused_draft_itemid();
        }
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

        $html = '<div class="form-filemanager" data-fieldtype="filemanager">' .
            $output->render($fm) .
            '<input type="hidden" name="' . $elname . '" value="' . $draftitemid . '" />' .
            '<input type="hidden" name="' . $elname . 'draft" value="' . $draftitemid . '" />' .
            '</div>';

        return $html;
    }

    /**
     * Check question's form data for complete response.
     * @param \stdClass $responsedata The data entered into the response.
     * @return bool
     */
    public function response_complete($responsedata) {
        $answered = false;
        // If $responsedata is a response object, look through the answers.
        if (is_a($responsedata, 'mod_questionnaire\responsetype\response\response') &&
            isset($responsedata->answers[$this->id]) && !empty($responsedata->answers[$this->id])
        ) {
            $answer = reset($responsedata->answers[$this->id]);
            $answered = ((int)$answer->value > 0);
        } else if (isset($responsedata->{'q'.$this->id})) {
            // If $responsedata is webform data, check that it is not empty.
            $draftitemid = (int)$responsedata->{'q' . $this->id};
            if ($draftitemid > 0) {
                $info = file_get_draft_area_info($draftitemid);
                $answered = $info['filecount'] > 0;
            }
        }
        return !($this->required() && ($this->deleted == 'n') && !$answered);
    }

    /**
     * Get file manager options
     *
     * @return array
     */
    public static function get_file_manager_option() {
        return [
            'mainfile' => '',
            'subdirs' => false,
            'accepted_types' => array('image', '.pdf'),
            'maxfiles' => 1,
        ];
    }

    /**
     * Response display output.
     *
     * @param \stdClass $data
     * @return string
     */
    protected function response_survey_display($data) {
        global $PAGE, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/resourcelib.php');
        if (isset($data->answers[$this->id])) {
            $answer = reset($data->answers[$this->id]);
        } else {
            return '';
        }
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($answer->value);
        $code = '';

        if ($file) {
            // There is a file.
            $moodleurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            $mimetype = $file->get_mimetype();
            $title = '';

            $mediamanager = core_media_manager::instance($PAGE);
            $embedoptions = array(
                core_media_manager::OPTION_TRUSTED => true,
                core_media_manager::OPTION_BLOCK => true,
            );

            if (file_mimetype_in_typegroup($mimetype, 'web_image')) {  // It's an image.
                $code = resourcelib_embed_image($moodleurl->out(), $title);

            } else if ($mimetype === 'application/pdf') {
                // PDF document.
                $code = resourcelib_embed_pdf($moodleurl->out(), $title, get_string('view'));

            } else if ($mediamanager->can_embed_url($moodleurl, $embedoptions)) {
                // Media (audio/video) file.
                $code = $mediamanager->embed_url($moodleurl, $title, 0, 0, $embedoptions);

            } else {
                // We need a way to discover if we are loading remote docs inside an iframe.
                $moodleurl->param('embed', 1);

                // Anything else - just try object tag enlarged as much as possible.
                $code = resourcelib_embed_general($moodleurl, $title, get_string('view'), $mimetype);
            }
        }
        return '<div class="response text">' . $code . '</div>';
    }

    /**
     * Add the length element as hidden.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_length(MoodleQuickForm $mform, $helpname = '') {
        return question::form_length_hidden($mform);
    }

    /**
     * Add the precise element as hidden.
     *
     * @param \MoodleQuickForm $mform
     * @param string $helpname
     * @return \MoodleQuickForm
     */
    protected function form_precise(MoodleQuickForm $mform, $helpname = '') {
        return question::form_precise_hidden($mform);
    }

}
