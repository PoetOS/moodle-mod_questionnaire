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

use mod_questionnaire\local\feedback\section;
use mod_questionnaire\questionnaire;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * The part of the feedback page below the settings form: the list of feedback sections with edit,
 * add and delete controls, or the empty state when no question can be used for feedback.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_overview implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The questionnaire whose feedback is being managed.
     */
    public function __construct(
        /** @var questionnaire The questionnaire whose feedback is being managed. */
        protected readonly questionnaire $questionnaire,
    ) {
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $feedback = $this->questionnaire->feedback();
        $cmid = $this->questionnaire->coursemodule()->id;

        $data = new stdClass();
        $data->noeligible = !$feedback->has_any_feedback_questions();
        $data->eligiblehelp = null;
        $data->questionsurl = null;
        $data->showsections = false;
        if ($data->noeligible) {
            $data->eligiblehelp = format_text(get_string('feedbackoptions_help', 'questionnaire'), FORMAT_MOODLE);
            $data->questionsurl = (new moodle_url('/mod/questionnaire/questions.php', ['id' => $cmid]))->out(false);
            return $data;
        }

        $mode = $feedback->mode();
        if ($mode === 0) {
            return $data;
        }

        $isglobal = ($mode === 1);
        $sections = array_values($feedback->sections());
        $listed = $isglobal ? array_slice($sections, 0, 1) : $sections;

        $data->showsections = true;
        $data->isglobal = $isglobal;
        $data->sections = array_map(fn(section $section) => $this->export_section($section, $isglobal, $cmid), $listed);
        $data->hassections = !empty($listed);
        $data->canadd = !$isglobal || empty($sections);
        $data->addurl = (new moodle_url('/mod/questionnaire/feedback.php'))->out(false);
        $data->cmid = $cmid;
        $data->sesskey = sesskey();
        $data->defaultlabel = $isglobal ? get_string('feedbackglobal', 'questionnaire') : '';
        $unused = count($sections) - count($listed);
        $data->unusednotice = $unused > 0 ? get_string('feedbackunusedsections', 'questionnaire', $unused) : null;
        return $data;
    }

    /**
     * One section's row in the list.
     *
     * @param section $section
     * @param bool $isglobal True in global feedback mode, where every eligible question counts and sections can't be deleted.
     * @param int $cmid The course module id.
     * @return stdClass
     */
    private function export_section(section $section, bool $isglobal, int $cmid): stdClass {
        $messages = array_filter(
            $section->sectionfeedback,
            fn($feedback) => trim(strip_tags((string) $feedback->feedbacktext)) !== ''
        );
        $deleteurl = new moodle_url(
            '/mod/questionnaire/feedback.php',
            ['id' => $cmid, 'action' => 'confirmdeletesection', 'sectionid' => $section->id]
        );
        return (object)[
            'label' => format_string($section->sectionlabel),
            'questions' => $isglobal ? get_string('feedbackallquestions', 'questionnaire') : count($section->scorecalculation),
            'messages' => count($messages),
            'editurl' => (new moodle_url('/mod/questionnaire/fbsections.php', ['id' => $cmid, 'sectionid' => $section->id]))
                ->out(false),
            'deleteurl' => $isglobal ? null : $deleteurl->out(false),
        ];
    }
}
