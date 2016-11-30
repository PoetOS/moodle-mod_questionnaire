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
 */

namespace mod_questionnaire\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {
    /**
     * Main view page.
     * @param \templateable $page
     * @return string | boolean
     */
    public function render_viewpage($page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/viewpage', $data);
    }

    /**
     * Fill out the questionnaire (complete) page.
     * @param \templateable $page
     * @return string | boolean
     */
    public function render_completepage($page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/completepage', $data);
    }

    /**
     * Fill out the report page.
     * @param \templateable $page
     * @return string | boolean
     */
    public function render_reportpage($page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/reportpage', $data);
    }

    /**
     * Fill out the qsettings page.
     * @param \templateable $page
     * @return string | boolean
     */
    public function render_qsettingspage($page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/qsettingspage', $data);
    }

    /**
     * Fill out the questions page.
     * @param \templateable $page
     * @return string | boolean
     */
    public function render_questionspage($page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/questionspage', $data);
    }

    /**
     * Fill out the preview page.
     * @param \templateable $page
     * @return string | boolean
     */
    public function render_previewpage($page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/previewpage', $data);
    }

    /**
     * Fill out the non-respondents page.
     * @param \templateable $page
     * @return string | boolean
     */
    public function render_nonrespondentspage($page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_questionnaire/nonrespondentspage', $data);
    }

    /**
     * Render the respondent information line.
     * @param string $text The respondent information.
     */
    public function respondent_info($text) {
        return \html_writer::tag('span', $text, ['class' => 'respondentinfo']);
    }

    /**
     * Render a question for a survey.
     * @param mod_questionnaire\question\base $question The question object.
     * @param array $formdata Any returned form data.
     * @param array $descendantsdata Question dependency data.
     * @param int $qnum The question number.
     * @param boolean $blankquestionnaire Used for printing a blank one.
     * @return string The output for the page.
     */
    public function question_output($question, $formdata, $descendantsdata, $qnum, $blankquestionnaire) {
        // Calling "survey_display" may generate per question notifications. If present, add them to the question output.
        $qoutput = $question->survey_display($formdata, $descendantsdata, $qnum, $blankquestionnaire);
        if (($notifications = $question->get_notifications()) !== false) {
            foreach ($notifications as $notification) {
                $qoutput .= $this->notification($notification, \core\output\notification::NOTIFY_ERROR);
            }
        }
        return $qoutput;
    }

    /**
     * Render a question response.
     * @param mod_questionnaire\question\base $question The question object.
     * @param stdClass $data All of the response data.
     * @param int $qnum The question number.
     * @return string The output for the page.
     */
    public function response_output($question, $data, $qnum=null) {
        return $question->response_display($data, $qnum);
    }

    /**
     * Render all responses for a question.
     * @param stdClass $data All of the response data.
     * @return string The output for the page.
     */
    public function all_response_output($data=null) {
        $output = '';
        if (is_string($data)) {
            $output .= $data;
        } else {
            foreach ($data as $qnum => $responses) {
                $question = $responses['question'];
                $output .= $this->box_start('individualresp');
                $output .= $question->questionstart_survey_display($qnum);
                foreach ($responses as $item => $response) {
                    if ($item != 'question') {
                        $output .= $this->container($response['respdate'], 'respdate');
                        $output .= $question->response_display($response['respdata']);
                    }
                }
                $output .= $question->questionend_survey_display($qnum);
                $output .= $this->box_end();
            }
        }
        return $output;
    }

    /**
     * Render a question results summary.
     * @param mod_questionnaire\question\base $question The question object.
     * @param array $rids The response ids.
     * @param string $sort The sort order being used.
     * @param string $anonymous The value of the anonymous setting.
     * @return string The output for the page.
     */
    public function results_output($question, $rids, $sort, $anonymous) {
        return $question->display_results($rids, $sort, $anonymous);
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of flexible_table
     *
     * @param flexible_table $table
     * @return string HTML
     */
    public function flexible_table(\flexible_table $table) {

        $o = '';
        ob_start();
        $table->print_html();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }
}