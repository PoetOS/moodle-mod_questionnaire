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
use url_select;

/**
 * Tertiary action bar for the response report and my-report pages.
 *
 * Replaces the legacy print_tabs() sub-rows: view switching (Summary / List of responses),
 * response ordering, download, and the destructive delete actions, rendered as a single
 * toolbar row via templates/report_action_bar.mustache. Destructive actions are emitted
 * as danger-styled anchor action links, not navigation.
 *
 * Construct via the named factories; parameters are threaded in from the entry scripts.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_action_bar implements \renderable, \templatable {
    /**
     * Left-aligned toolbar items, each ['actionlink' => \action_link] or ['urlselect' => \url_select].
     * @var array
     */
    private $leftitems = [];

    /**
     * Optional danger-styled action link rendered on the right of the bar.
     * @var \action_link|null
     */
    private $dangerlink = null;

    /**
     * Use the named for_*() factories instead.
     */
    private function __construct() {
    }

    /**
     * Bar for the all-responses summary views (report.php actions vall / vallasort / vallarsort).
     *
     * @param questionnaire $questionnaire The current questionnaire.
     * @param int $currentgroupid Resolved activity group id (0 for all).
     * @param string $currentaction The active report action: 'vall', 'vallasort' or 'vallarsort'.
     * @return self
     */
    public static function for_summary(questionnaire $questionnaire, int $currentgroupid, string $currentaction): self {
        $bar = new self();
        $bar->add_view_switcher($questionnaire, $currentgroupid, 'summary');
        $orderactions = ['vall' => 'order_default', 'vallasort' => 'order_ascending', 'vallarsort' => 'order_descending'];
        $orderurls = [];
        foreach ($orderactions as $action => $stringkey) {
            $url = self::report_url($questionnaire, ['action' => $action], $currentgroupid);
            $orderurls[$url->out(false)] = get_string($stringkey, 'questionnaire');
        }
        $selected = self::report_url($questionnaire, ['action' => $currentaction], $currentgroupid);
        $orderselect = new url_select($orderurls, $selected->out(false), null);
        $orderselect->set_label(get_string('orderresponses', 'questionnaire'));
        $orderselect->set_help_icon('orderresponses', 'mod_questionnaire');
        $bar->leftitems[] = ['urlselect' => $orderselect];
        if ($questionnaire->capabilities()->can_download_responses()) {
            $url = self::report_url($questionnaire, ['action' => 'dwnpg'], $currentgroupid);
            $bar->leftitems[] = ['actionlink' => new \action_link($url, get_string('downloadtextformat', 'questionnaire'))];
        }
        if ($questionnaire->capabilities()->can_delete_responses()) {
            $url = self::report_url($questionnaire, ['action' => 'delallresp'], $currentgroupid);
            $bar->dangerlink = new \action_link(
                $url,
                get_string('deleteallresponses', 'questionnaire'),
                null,
                ['class' => 'btn btn-outline-danger']
            );
        }
        return $bar;
    }

    /**
     * Bar for the list-of-responses view (report.php action vresp with byresponse).
     *
     * @param questionnaire $questionnaire The current questionnaire.
     * @param int $currentgroupid Resolved activity group id (0 for all).
     * @return self
     */
    public static function for_response_list(questionnaire $questionnaire, int $currentgroupid): self {
        $bar = new self();
        $bar->add_view_switcher($questionnaire, $currentgroupid, 'list');
        return $bar;
    }

    /**
     * Bar for an individual response view (report.php action vresp with individualresponse).
     *
     * @param questionnaire $questionnaire The current questionnaire.
     * @param int $currentgroupid Resolved activity group id (0 for all).
     * @param int|null $rid The response id being viewed; threads into the delete action.
     * @return self
     */
    public static function for_individual_response(questionnaire $questionnaire, int $currentgroupid, ?int $rid): self {
        $bar = new self();
        $bar->add_view_switcher($questionnaire, $currentgroupid, 'individual');
        if ($rid && $questionnaire->capabilities()->can_delete_responses()) {
            $url = self::report_url(
                $questionnaire,
                ['action' => 'dresp', 'rid' => $rid, 'individualresponse' => 1],
                $currentgroupid
            );
            $bar->dangerlink = new \action_link(
                $url,
                get_string('deleteresp', 'questionnaire'),
                null,
                ['class' => 'btn btn-outline-danger']
            );
        }
        return $bar;
    }

    /**
     * Bar for the download-options page (report.php action dwnpg, staff variant).
     *
     * @param questionnaire $questionnaire The current questionnaire.
     * @param int $currentgroupid Resolved activity group id (0 for all).
     * @return self
     */
    public static function for_download(questionnaire $questionnaire, int $currentgroupid): self {
        $bar = new self();
        $bar->add_view_switcher($questionnaire, $currentgroupid, 'download');
        return $bar;
    }

    /**
     * Bar for the my-report pages (myreport.php actions summary / vresp / vall).
     *
     * Empty (has_content() === false) when the user has at most one response — mirrors the
     * legacy behaviour where no sub-row rendered for a single response.
     *
     * @param questionnaire $questionnaire The current questionnaire.
     * @param int $userid The user whose responses are shown.
     * @param int $currentgroupid Resolved activity group id (0 for all).
     * @param int $usernumresp Number of responses the user has submitted.
     * @param string $currentaction The active myreport action: 'summary', 'vresp' or 'vall'.
     * @return self
     */
    public static function for_myreport(
        questionnaire $questionnaire,
        int $userid,
        int $currentgroupid,
        int $usernumresp,
        string $currentaction,
    ): self {
        $bar = new self();
        if ($usernumresp <= 1) {
            return $bar;
        }
        $baseparams = ['instance' => $questionnaire->id(), 'user' => $userid];
        $views = [
            'summary' => [
                ['action' => 'summary'],
                get_string('summary', 'questionnaire'),
            ],
            'vresp' => [
                ['action' => 'vresp', 'byresponse' => 1],
                get_string('viewindividualresponse', 'questionnaire'),
            ],
            'vall' => [
                ['action' => 'vall', 'byresponse' => 0],
                get_string('myresponses', 'questionnaire'),
            ],
        ];
        foreach ($views as $action => [$params, $label]) {
            $url = new moodle_url('/mod/questionnaire/myreport.php', $baseparams + $params);
            if ($currentgroupid) {
                $url->param('group', $currentgroupid);
            }
            $bar->add_view_link($url, $label, $action === $currentaction);
        }
        if ($questionnaire->capabilities()->can_download_responses()) {
            $url = new moodle_url('/mod/questionnaire/report.php', $baseparams + ['action' => 'dwnpg']);
            if ($currentgroupid) {
                $url->param('group', $currentgroupid);
            }
            $bar->leftitems[] = ['actionlink' => new \action_link($url, get_string('downloadtextformat', 'questionnaire'))];
        }
        return $bar;
    }

    /**
     * Whether the bar has anything to render.
     *
     * @return bool
     */
    public function has_content(): bool {
        return !empty($this->leftitems) || ($this->dangerlink !== null);
    }

    /**
     * Export the toolbar for templates/report_action_bar.mustache.
     *
     * @param renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(renderer_base $output): \stdClass {
        $data = new \stdClass();
        $data->left = [];
        foreach ($this->leftitems as $item) {
            $exported = [];
            foreach ($item as $type => $widget) {
                $exported[$type] = $widget->export_for_template($output);
            }
            $data->left[] = $exported;
        }
        $data->danger = $this->dangerlink ? $this->dangerlink->export_for_template($output) : null;
        return $data;
    }

    /**
     * Add the Summary / List of responses view-switch links for the report.php views.
     *
     * The list link is gated on the viewsingleresponse capability, which also reproduces the
     * legacy restricted branch (students granted view-all via respview never saw the list tab).
     *
     * @param questionnaire $questionnaire The current questionnaire.
     * @param int $currentgroupid Resolved activity group id (0 for all).
     * @param string $currentview Which view is active: 'summary', 'list', 'individual' or 'download'.
     */
    private function add_view_switcher(questionnaire $questionnaire, int $currentgroupid, string $currentview): void {
        $summaryurl = self::report_url($questionnaire, ['action' => 'vall'], $currentgroupid);
        $this->add_view_link($summaryurl, get_string('summary', 'questionnaire'), $currentview === 'summary');
        if ($questionnaire->capabilities()->can_view_single_response()) {
            $listurl = self::report_url($questionnaire, ['action' => 'vresp', 'byresponse' => 1], $currentgroupid);
            $this->add_view_link($listurl, get_string('viewbyresponse', 'questionnaire'), $currentview === 'list');
        }
    }

    /**
     * Add one view link to the left items, marking the active view for styling and assistive tech.
     *
     * @param moodle_url $url The link target.
     * @param string $label The link text.
     * @param bool $active Whether this view is the one being displayed.
     */
    private function add_view_link(moodle_url $url, string $label, bool $active): void {
        $attributes = ['class' => 'btn btn-link px-2' . ($active ? ' fw-bold' : '')];
        if ($active) {
            $attributes['aria-current'] = 'page';
        }
        $this->leftitems[] = ['actionlink' => new \action_link($url, $label, null, $attributes)];
    }

    /**
     * Build a report.php URL with the instance and group parameters threaded in.
     *
     * @param questionnaire $questionnaire The current questionnaire.
     * @param array $params Action-specific URL parameters.
     * @param int $currentgroupid Resolved activity group id; only added when non-zero.
     * @return moodle_url
     */
    private static function report_url(questionnaire $questionnaire, array $params, int $currentgroupid): moodle_url {
        $url = new moodle_url('/mod/questionnaire/report.php', ['instance' => $questionnaire->id()] + $params);
        if ($currentgroupid) {
            $url->param('group', $currentgroupid);
        }
        return $url;
    }
}
