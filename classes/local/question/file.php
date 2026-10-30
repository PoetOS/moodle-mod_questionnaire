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

namespace mod_questionnaire\local\question;
use form_filemanager;
use mod_questionnaire\local\response\response;
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
        return '\\mod_questionnaire\\local\\response\\file';
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

        $elname = 'q' . $this->id();
        // Load an earlier upload into the draft area, so it is kept unless the user removes it.
        $itemid = !empty($response->answers[$this->id()]) ? (int)$response->answers[$this->id()][0]->id : 0;
        // Prepare the draft area.
        $draftitemid = file_get_submitted_draft_itemid($elname);
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_questionnaire',
            'response_file',
            $itemid,
            self::get_file_manager_option()
        );
        // Filemanager form element implementation is far from optimal, we need to rework this if we ever fix it...
        require_once("$CFG->dirroot/lib/form/filemanager.php");
        $fm = new form_filemanager((object)self::get_file_manager_option($draftitemid));
        $output = $PAGE->get_renderer('core', 'files');
        $html = '<div class="form-filemanager" data-fieldtype="filemanager">' .
            $output->render($fm) .
            '<input type="hidden" name="' . $elname . '" value="' . $draftitemid . '" />' .
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
        if (
            is_a($responsedata, 'mod_questionnaire\local\response\response') &&
            isset($responsedata->answers[$this->id()]) &&
            !empty($responsedata->answers[$this->id()])
        ) {
            $answer = reset($responsedata->answers[$this->id()]);
            $answered = ((int)$answer->value > 0);
        } else if (isset($responsedata->{'q' . $this->id()})) { // If $responsedata is webform data, check that it is not empty.
            $draftitemid = (int)$responsedata->{'q' . $this->id()};
            if ($draftitemid > 0) {
                $info = file_get_draft_area_info($draftitemid);
                $answered = $info['filecount'] > 0;
            }
        }
        return !($this->required() && ($this->deleted() == 'n') && !$answered);
    }

    /**
     * Check question's form data for valid response: every uploaded file must be one of the accepted types.
     *
     * The file manager only limits the types in the browser, so this is the server-side check.
     *
     * @param \stdClass $responsedata The data entered into the response.
     * @return bool
     */
    public function response_valid($responsedata) {
        global $USER;
        if (is_a($responsedata, response::class) || empty($responsedata->{'q' . $this->id()})) {
            return parent::response_valid($responsedata);
        }
        $files = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            (int)$responsedata->{'q' . $this->id()},
            'id',
            false
        );
        foreach ($files as $file) {
            if (!self::allowed_file_name($file->get_filename())) {
                return false;
            }
        }
        return true;
    }

    /**
     * True if a file with this name is one of the types the question accepts.
     *
     * @param string $filename
     * @return bool
     */
    public static function allowed_file_name(string $filename): bool {
        return (new \core_form\filetypes_util())->is_allowed_file_type(
            $filename,
            self::get_file_manager_option()['accepted_types']
        );
    }

    /**
     * Get file manager options, here allow only one image or pdf file, without
     * creating subdirectories.
     *
     * @param ?int $draftitemid
     * @return array
     */
    public static function get_file_manager_option(?int $draftitemid = 0) {
        return [
            'subdirs' => false,
            'accepted_types' => ['image', 'document'],
            'maxfiles' => 1,
            'itemid' => $draftitemid,
        ];
    }

    /**
     * Response display output: show an uploaded image, or a link to download any other file.
     *
     * @param \stdClass $data
     * @return string
     */
    protected function response_survey_display($data) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/resourcelib.php');
        if (isset($data->answers[$this->id()])) {
            $answer = reset($data->answers[$this->id()]);
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

            // Response files are always downloaded, so only images can be shown in the page. Anything else is
            // offered as a download link rather than embedded.
            if (file_mimetype_in_typegroup($file->get_mimetype(), 'web_image')) {
                $code = resourcelib_embed_image($moodleurl->out(), '');
            } else {
                $code = \html_writer::link(new moodle_url($moodleurl, ['forcedownload' => 1]), s($file->get_filename()));
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
