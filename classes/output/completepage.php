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

use mod_questionnaire\questionnaire;

/**
 * Contains class mod_questionnaire\output\completepage
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completepage extends questionnairepage {
    /**
     * The data to be exported.
     * @var array
     */
    protected $data;

    /**
     * The template to use for rendering.
     * @var string
     */
    protected $template = 'mod_questionnaire/completepage';

    /**
     * Construct the complete page.
     * @param questionnaire $questionnaire The questionnaire object.
     * @param object|null $formdata The form data.
     */
    public function __construct(questionnaire $questionnaire, ?object $formdata = null) {
        global $USER;
        $message = $questionnaire->user_access_messages($USER->id);
        if (!empty($message)) {
            $this->add_message($message);
            return;
        }

        // Handle the main questionnaire completion page.
        $quser = $USER->id;

        // Respondent information? May not be valid for complete page.

        // Title, subtitle, print control, progress bar, additional info, and additional messages.
        $this->add_intro_information($questionnaire);
        $questionsbysec = $questionnaire->questions_by_section();
        if ($questionnaire->use_progressbar() && isset($questionsbysec) && count($questionsbysec) > 1) {
            $this->add_progress_bar(1, count($questionsbysec));
        }

        $this->data['surveyform'] = (new complete_form($questionnaire))->render();

return;
        // Survey form, page by page.
// TODO - currently, print_survey is called from view. print_survey does a lot more than display. view and print_survey share work.
// TODO - refactor print_survey to separate display from processing, and call from here.
        $msg = $questionnaire->print_survey($quser, $USER->id);

        // If Questionnaire was submitted with all required fields completed ($msg is empty),
        // then record the submittal.
        if (
            $formdata && confirm_sesskey() && isset($formdata->submit) && isset($formdata->submittype) &&
            ($formdata->submittype == "Submit Survey") && empty($msg)
        ) {
            if (!empty($formdata->rid)) {
                $formdata->rid = (int)$formdata->rid;
            }
            if (!empty($formdata->sec)) {
                $formdata->sec = (int)$formdata->sec;
            }
            $questionnaire->response_delete($formdata->rid, $formdata->sec);
            $questionnaire->rid = $questionnaire->response_insert($formdata, $quser);
            $questionnaire->response_commit($questionnaire->rid);

            $questionnaire->update_grades($quser);

            // Update completion state.
            $completion = new \completion_info($questionnaire->course);
            if ($completion->is_enabled($questionnaire->cm) && $questionnaire->completionsubmit) {
                $completion->update_state($questionnaire->cm, COMPLETION_COMPLETE);
            }

            // Log this submitted response. Note this removes the anonymity in the logged event.
            $context = context_module::instance($questionnaire->cm->id);
            $anonymous = $questionnaire->respondenttype == 'anonymous';
            $params = [
                'context' => $context,
                'courseid' => $questionnaire->course->id,
                'relateduserid' => $USER->id,
                'anonymous' => $anonymous,
                'other' => ['questionnaireid' => $questionnaire->id],
            ];
            $event = \mod_questionnaire\event\attempt_submitted::create($params);
            $event->trigger();

            $questionnaire->submission_notify($this->rid);
            $this->response_goto_thankyou();
        }
    }

    /**
     * Add a progress bar to the page.
     *
     * @param int $current The current step number.
     * @param int $total The total number of steps.
     */
    public function add_progress_bar(int $current, int $total) {
        global $PAGE;

        // $templatecontext['percent'] = $this->calculate_progress($section, $questionsbysec);
        $templatecontext['percent'] = '90';
        $helpicon = new \help_icon('progresshelp', 'mod_questionnaire');
        $templatecontext['progresshelp'] = $helpicon->export_for_template($PAGE->get_renderer('mod_questionnaire'));
        $this->data['progressbar'] = $templatecontext;
    }

    /**
     * Render the completion form start HTML.
     * @param string $action The action URL.
     * @param array $hiddeninputs Name/value pairs of hidden inputs used by the form.
     * @return string The output for the page.
     */
    public function complete_formstart($action, $hiddeninputs = []) {
        $output = '';
        $output .= \html_writer::start_tag('form', ['id' => 'phpesp_response', 'method' => 'post', 'action' => $action]) . "\n";
        foreach ($hiddeninputs as $name => $value) {
            $output .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]) . "\n";
        }
        return $output;
    }

    /**
     * Render the completion form end HTML.
     * @param array $inputs Type/attribute array of inputs and values used by the form.
     * @return string The output for the page.
     */
    public function complete_formend($inputs = []) {
        $output = '';
        foreach ($inputs as $type => $attributes) {
            $output .= \html_writer::empty_tag('input', array_merge(['type' => $type], $attributes)) . "\n";
        }
        $output .= \html_writer::end_tag('form') . "\n";
        return $output;
    }
}
