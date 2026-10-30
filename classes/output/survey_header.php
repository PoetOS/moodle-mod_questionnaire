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
use moodle_url;
use renderer_base;
use stdClass;

/**
 * The block above a survey's questions or responses: respondent line, title, print-blank link,
 * subtitle and additional information.
 *
 * Shared by the complete, preview, print, report and response pages (HTML and PDF) through the
 * mod_questionnaire/survey_header partial, and by the thank-you page with its own title and body.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class survey_header implements \renderable, \templatable {
    /** @var string Formatted title HTML, or '' for none. */
    protected string $title;

    /** @var string Formatted subtitle HTML, or '' for none. */
    protected string $subtitle;

    /** @var string Formatted additional-information HTML, or '' for none. */
    protected string $info;

    /** @var stdClass|null Respondent details: name, course, groups, submitted, printurl (see set_respondent()). */
    protected ?stdClass $respondent = null;

    /** @var moodle_url|null Where the print-blank-questionnaire link opens, or null for no link. */
    protected ?moodle_url $printblankurl = null;

    /**
     * Construct the header from already-formatted strings.
     *
     * @param string $title Formatted title HTML.
     * @param string $subtitle Formatted subtitle HTML.
     * @param string $info Formatted additional-information HTML.
     */
    public function __construct(string $title = '', string $subtitle = '', string $info = '') {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->info = $info;
    }

    /**
     * A header carrying the survey's own title, subtitle and additional information.
     *
     * @param questionnaire $questionnaire
     * @return self
     */
    public static function for_survey(questionnaire $questionnaire): self {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $survey = $questionnaire->survey();
        $info = '';
        if ($survey->info() !== '') {
            $info = \mod_questionnaire\local\trusttext::format(
                file_rewrite_pluginfile_urls(
                    $survey->info(),
                    'pluginfile.php',
                    $questionnaire->context()->id,
                    'mod_questionnaire',
                    'info',
                    $questionnaire->surveyid()
                ),
                $survey->info_trust()
            );
        }
        return new self(
            $survey->title() !== '' ? format_string($survey->title()) : '',
            $survey->subtitle() !== '' ? format_string($survey->subtitle()) : '',
            $info
        );
    }

    /**
     * Show whose response this is.
     *
     * @param string $name The respondent's full name, or the anonymous marker.
     * @param string|null $course The course the response came from (public surveys only).
     * @param string|null $groups The respondent's group(s), e.g. "Group: A", or null when groups are off.
     * @param int|null $submitted When the response was submitted, or null to omit.
     * @param moodle_url|null $printurl Where the print-this-response popup opens, or null for no icon.
     */
    public function set_respondent(
        string $name,
        ?string $course = null,
        ?string $groups = null,
        ?int $submitted = null,
        ?moodle_url $printurl = null
    ): void {
        $this->respondent = (object)[
            'name' => $name,
            'course' => $course,
            'groups' => $groups,
            'submitted' => $submitted,
            'printurl' => $printurl,
        ];
    }

    /**
     * Offer the print-blank-questionnaire link.
     *
     * @param moodle_url $url
     */
    public function set_print_blank(moodle_url $url): void {
        $this->printblankurl = $url;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->title = $this->title !== '' ? $this->title : null;
        $data->subtitle = $this->subtitle !== '' ? $this->subtitle : null;
        $data->info = $this->info !== '' ? $this->info : null;
        $data->respondent = $this->export_respondent($output);
        $data->printblank = $this->printblankurl ? $this->print_blank_link($output) : null;
        return $data;
    }

    /**
     * The respondent line's template data, or null when no respondent is shown.
     *
     * @param renderer_base $output
     * @return stdClass|null
     */
    private function export_respondent(renderer_base $output): ?stdClass {
        if ($this->respondent === null) {
            return null;
        }
        $respondent = clone $this->respondent;
        $respondent->submitted = $respondent->submitted ? userdate($respondent->submitted) : null;
        $respondent->printlink = null;
        if ($respondent->printurl) {
            $label = get_string('print', 'mod_questionnaire');
            $respondent->printlink = $output->action_link(
                $respondent->printurl,
                null,
                $this->popup_action($respondent->printurl, $label),
                ['title' => $label],
                new \pix_icon('t/print', $label)
            );
        }
        unset($respondent->printurl);
        return $respondent;
    }

    /**
     * The rendered print-blank link (a core action link opening a popup).
     *
     * @param renderer_base $output
     * @return string
     */
    private function print_blank_link(renderer_base $output): string {
        $title = get_string('printblanktooltip', 'questionnaire');
        return $output->action_link(
            $this->printblankurl,
            get_string('printblank', 'questionnaire'),
            $this->popup_action($this->printblankurl, $title),
            ['class' => 'floatprinticon', 'title' => $title],
            new \pix_icon('t/print', $title)
        );
    }

    /**
     * The popup used by both print links.
     *
     * @param moodle_url $url
     * @param string $title
     * @return \popup_action
     */
    private function popup_action(moodle_url $url, string $title): \popup_action {
        return new \popup_action('click', $url, 'popup', [
            'menubar' => true,
            'location' => false,
            'scrollbars' => true,
            'resizable' => true,
            'height' => 600,
            'width' => 800,
            'title' => $title,
        ]);
    }
}
