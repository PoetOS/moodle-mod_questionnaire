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
 * Tabbed-bar rendering for mod_questionnaire staff and learner views.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;
use moodle_url;
use tabobject;

/**
 * Builds the questionnaire activity's tabbed navigation and adds it to the page.
 *
 * Each call site supplies the literal tab name it owns.
 */
class tabs {
    /** @var array<int, tabobject> Primary tab row. */
    private array $row = [];

    /** @var array<int, tabobject> Optional second tab row. */
    private array $row2 = [];

    /** @var array<int, tabobject> Optional third tab row. */
    private array $row3 = [];

    /** @var array<int, string> Names of tabs to render inactive. */
    private array $inactive = [];

    /** @var array<int, string> Names of tabs to render activated. */
    private array $activated = [];

    /** @var string Effective tab-name passed to print_tabs; may drift from $this->currenttab. */
    private string $activetab = '';

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire Active questionnaire domain object.
     * @param string $currenttab Literal name of the tab owned by the current entry point.
     * @param int|null $currentgroupid Active group filter (defaults to 0 when not supplied).
     * @param int|null $rid Response id for deleteresp tab (when applicable).
     */
    public function __construct(
        /** @var questionnaire Active questionnaire domain object. */
        private readonly questionnaire $questionnaire,
        /** @var string Literal name of the tab owned by the current entry point. */
        private readonly string $currenttab,
        /** @var int|null Active group filter (defaults to 0 when not supplied). */
        private readonly ?int $currentgroupid = null,
        /** @var int|null Response id for deleteresp tab (when applicable). */
        private readonly ?int $rid = null,
    ) {
    }

    /**
     * Build the tab rows and append the rendered HTML to the page's tabsarea slot.
     *
     * @param object $page Templatable page that exposes add_to_page('tabsarea', $html).
     */
    public function render(object $page): void {
        global $USER;

        $this->row = [];
        $this->row2 = [];
        $this->row3 = [];
        $this->inactive = [];
        $this->activated = [];
        $this->activetab = $this->currenttab;

        $this->add_settings_tab();
        $this->add_questions_tab();
        $this->add_feedback_tab();
        $this->add_preview_tab();

        $usernumresp = $this->questionnaire->count_submissions($USER->id);
        $this->add_myreport_tabs($usernumresp);

        [$canviewallgroups, $canviewgroups] = $this->resolve_group_visibility();
        $grouplogic = $canviewallgroups || $canviewgroups;
        $resplogic = ($this->questionnaire->count_submissions() > 0);

        $this->add_allreport_tabs($usernumresp, $grouplogic, $resplogic);
        $this->add_nonrespondents_tab($canviewallgroups, $canviewgroups);

        $this->emit_tabs($page);
    }

    /**
     * Build a wwwroot-prefixed, entity-encoded querystring URL for a plugin script.
     *
     * @param string $script Script name relative to /mod/questionnaire/ without .php.
     * @param string $qs Querystring without leading '?'.
     * @return string
     */
    private function tab_url(string $script, string $qs): string {
        global $CFG;
        return $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/' . $script . '.php?' . $qs);
    }

    /**
     * Determine whether the current user can view all-group responses and their own group.
     *
     * @return array{0: bool, 1: bool} [canviewallgroups, canviewgroups]
     */
    private function resolve_group_visibility(): array {
        global $USER;
        $canviewgroups = true;
        $groupmode = groups_get_activity_groupmode(
            $this->questionnaire->coursemodule(),
            $this->questionnaire->course()
        );
        if ($groupmode == 1) {
            $canviewgroups = groups_has_membership($this->questionnaire->coursemodule(), $USER->id);
        }
        $canviewallgroups = has_capability('moodle/site:accessallgroups', $this->questionnaire->context());
        return [$canviewallgroups, $canviewgroups];
    }

    /**
     * Add the "Advanced settings" tab if the user owns the survey and can manage it.
     */
    private function add_settings_tab(): void {
        if (!$this->questionnaire->capabilities()->can_manage_questionnaire() || !$this->questionnaire->is_survey_owner()) {
            return;
        }
        $this->row[] = new tabobject(
            'settings',
            $this->tab_url('qsettings', 'id=' . $this->questionnaire->coursemodule()->id),
            get_string('advancedsettings')
        );
    }

    /**
     * Add the "Questions" tab for editors who own the survey.
     */
    private function add_questions_tab(): void {
        if (!$this->questionnaire->capabilities()->can_edit_questions() || !$this->questionnaire->is_survey_owner()) {
            return;
        }
        $this->row[] = new tabobject(
            'questions',
            $this->tab_url('questions', 'id=' . $this->questionnaire->coursemodule()->id),
            get_string('questions', 'questionnaire')
        );
    }

    /**
     * Add the "Feedback" tab for editors who own the survey.
     */
    private function add_feedback_tab(): void {
        if (!$this->questionnaire->capabilities()->can_edit_questions() || !$this->questionnaire->is_survey_owner()) {
            return;
        }
        $this->row[] = new tabobject(
            'feedback',
            $this->tab_url('feedback', 'id=' . $this->questionnaire->coursemodule()->id),
            get_string('feedback')
        );
    }

    /**
     * Add the "Preview" tab if the user owns the survey, can preview, and there are questions.
     */
    private function add_preview_tab(): void {
        if (!$this->questionnaire->capabilities()->can_preview() || !$this->questionnaire->is_survey_owner()) {
            return;
        }
        if (empty($this->questionnaire->questions())) {
            return;
        }
        $this->row[] = new tabobject(
            'preview',
            $this->tab_url('preview', 'id=' . $this->questionnaire->coursemodule()->id),
            get_string('preview_label', 'questionnaire')
        );
    }

    /**
     * Add "Your response(s)" tab and, when appropriate, the myreport sub-row.
     *
     * @param int $usernumresp Number of the current user's completed responses.
     */
    private function add_myreport_tabs(int $usernumresp): void {
        global $USER;
        if (!$this->questionnaire->capabilities()->can_read_own_responses() || $usernumresp <= 0) {
            return;
        }
        $currentgroupid = $this->currentgroupid ?? 0;
        $argstr = 'instance=' . $this->questionnaire->id()
            . '&user=' . $USER->id . '&group=' . $currentgroupid;
        if ($usernumresp == 1) {
            $argstr .= '&byresponse=1&action=vresp';
            $yourrespstring = get_string('yourresponse', 'questionnaire');
        } else {
            $yourrespstring = get_string('yourresponses', 'questionnaire');
        }
        $this->row[] = new tabobject(
            'myreport',
            $this->tab_url('myreport', $argstr),
            $yourrespstring
        );

        $subtabs = ['mysummary', 'mybyresponse', 'myvall', 'mydownloadcsv'];
        if ($usernumresp > 1 && in_array($this->currenttab, $subtabs)) {
            $this->build_myreport_subrow($argstr);
        } else if (in_array($this->currenttab, ['mybyresponse', 'mysummary'])) {
            $this->inactive[] = 'myreport';
            $this->activated[] = 'myreport';
        }
    }

    /**
     * Populate $this->row2 with the myreport sub-row (summary/byresponse/all/download).
     *
     * @param string $argstr Base querystring shared by all sub-tabs.
     */
    private function build_myreport_subrow(string $argstr): void {
        $this->inactive[] = 'myreport';
        $this->activated[] = 'myreport';
        $currentgroupid = $this->currentgroupid ?? 0;
        $this->row2[] = new tabobject(
            'mysummary',
            $this->tab_url('myreport', $argstr . '&action=summary'),
            get_string('summary', 'questionnaire')
        );
        $this->row2[] = new tabobject(
            'mybyresponse',
            $this->tab_url('myreport', $argstr . '&byresponse=1&action=vresp'),
            get_string('viewindividualresponse', 'questionnaire')
        );
        $this->row2[] = new tabobject(
            'myvall',
            $this->tab_url('myreport', $argstr . '&byresponse=0&action=vall&group=' . $currentgroupid),
            get_string('myresponses', 'questionnaire')
        );
        if ($this->questionnaire->capabilities()->can_download_responses()) {
            $this->row2[] = new tabobject(
                'mydownloadcsv',
                $this->tab_url('report', $argstr . '&action=dwnpg'),
                get_string('downloadtextformat', 'questionnaire')
            );
        }
    }

    /**
     * Add the staff "All responses" tab and its subrows, dispatching to unrestricted vs restricted view.
     *
     * @param int $usernumresp Current user's own submission count (used by the restricted-view capability check).
     * @param bool $grouplogic Whether the user has any group visibility.
     * @param bool $resplogic Whether there are any responses at all.
     */
    private function add_allreport_tabs(int $usernumresp, bool $grouplogic, bool $resplogic): void {
        if ($this->questionnaire->capabilities()->can_view_all_responses_anytime($grouplogic, $resplogic)) {
            $this->add_unrestricted_allreport_tabs();
            return;
        }
        if (
            $this->questionnaire->capabilities()
                ->can_view_all_responses_with_restrictions($usernumresp, $grouplogic, $resplogic)
        ) {
            $this->add_restricted_allreport_tabs();
        }
    }

    /**
     * Full staff view: add the allreport tab and any active sub-rows.
     */
    private function add_unrestricted_allreport_tabs(): void {
        $argstr = 'instance=' . $this->questionnaire->id();
        $this->row[] = new tabobject(
            'allreport',
            $this->tab_url('report', $argstr . '&action=vall'),
            get_string('viewallresponses', 'questionnaire')
        );

        $reportsubtabs = [
            'vall', 'vresp', 'valldefault', 'vallasort', 'vallarsort',
            'deleteall', 'downloadcsv', 'vrespsummary', 'individualresp',
            'printresp', 'deleteresp',
        ];
        if (in_array($this->currenttab, $reportsubtabs)) {
            $this->build_allreport_subrow($argstr);
        }
        if (in_array($this->currenttab, ['valldefault', 'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'])) {
            $this->build_vall_sort_row($argstr);
        }
        if (in_array($this->currenttab, ['individualresp', 'deleteresp'])) {
            $this->add_deleteresp_subtab($argstr);
        }
    }

    /**
     * Populate the report sub-row (row2) under the allreport tab.
     *
     * @param string $argstr Base querystring (instance=X).
     */
    private function build_allreport_subrow(string $argstr): void {
        $currentgroupid = $this->currentgroupid ?? 0;
        $this->inactive[] = 'allreport';
        $this->activated[] = 'allreport';
        if ($this->currenttab == 'vrespsummary' || $this->currenttab == 'valldefault') {
            $this->inactive[] = 'vresp';
        }
        $this->row2[] = new tabobject(
            'vall',
            $this->tab_url('report', $argstr . '&action=vall&group=' . $currentgroupid),
            get_string('summary', 'questionnaire')
        );
        if (!$this->questionnaire->capabilities()->can_view_single_response()) {
            return;
        }
        $this->row2[] = new tabobject(
            'vrespsummary',
            $this->tab_url('report', $argstr . '&byresponse=1&action=vresp&group=' . $currentgroupid),
            get_string('viewbyresponse', 'questionnaire')
        );
        if ($this->currenttab == 'individualresp' || $this->currenttab == 'deleteresp') {
            $this->row2[] = new tabobject(
                'vresp',
                $this->tab_url('report', $argstr . '&byresponse=1&action=vresp'),
                get_string('viewindividualresponse', 'questionnaire')
            );
        }
    }

    /**
     * Populate the sort-order sub-row (row3) for the vall / sort variants.
     *
     * @param string $argstr Base querystring (instance=X).
     */
    private function build_vall_sort_row(string $argstr): void {
        $currentgroupid = $this->currentgroupid ?? 0;
        $this->activated[] = 'vall';

        $this->row3[] = new tabobject(
            'valldefault',
            $this->tab_url('report', $argstr . '&action=vall&group=' . $currentgroupid),
            get_string('order_default', 'questionnaire')
        );
        if ($this->currenttab != 'downloadcsv' && $this->currenttab != 'deleteall') {
            $this->row3[] = new tabobject(
                'vallasort',
                $this->tab_url('report', $argstr . '&action=vallasort&group=' . $currentgroupid),
                get_string('order_ascending', 'questionnaire')
            );
            $this->row3[] = new tabobject(
                'vallarsort',
                $this->tab_url('report', $argstr . '&action=vallarsort&group=' . $currentgroupid),
                get_string('order_descending', 'questionnaire')
            );
        }
        if ($this->questionnaire->capabilities()->can_delete_responses()) {
            $this->row3[] = new tabobject(
                'deleteall',
                $this->tab_url('report', $argstr . '&action=delallresp&group=' . $currentgroupid),
                get_string('deleteallresponses', 'questionnaire')
            );
        }
        if ($this->questionnaire->capabilities()->can_download_responses()) {
            $this->row3[] = new tabobject(
                'downloadcsv',
                $this->tab_url('report', $argstr . '&action=dwnpg&group=' . $currentgroupid),
                get_string('downloadtextformat', 'questionnaire')
            );
        }
    }

    /**
     * Add the "Delete response" sub-tab (row2) when individualresp / deleteresp is active.
     *
     * @param string $argstr Base querystring (instance=X).
     */
    private function add_deleteresp_subtab(string $argstr): void {
        $this->inactive[] = 'vresp';
        if ($this->currenttab != 'deleteresp') {
            $this->activated[] = 'vresp';
        }
        if (!$this->questionnaire->capabilities()->can_delete_responses()) {
            return;
        }
        $this->row2[] = new tabobject(
            'deleteresp',
            $this->tab_url('report', $argstr . '&action=dresp&rid=' . $this->rid . '&individualresponse=1'),
            get_string('deleteresp', 'questionnaire')
        );
    }

    /**
     * Restricted staff view: allreport tab plus a limited set of sub-rows.
     */
    private function add_restricted_allreport_tabs(): void {
        $currentgroupid = $this->currentgroupid ?? 0;
        $argstr = 'instance=' . $this->questionnaire->id() . '&sid=' . $this->questionnaire->surveyid();
        $this->row[] = new tabobject(
            'allreport',
            $this->tab_url('report', $argstr . '&action=vall&group=' . $currentgroupid),
            get_string('viewallresponses', 'questionnaire')
        );

        if (!in_array($this->currenttab, ['valldefault', 'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'])) {
            return;
        }
        $this->inactive[] = 'vall';
        $this->activated[] = 'vall';
        $this->row2[] = new tabobject(
            'valldefault',
            $this->tab_url('report', $argstr . '&action=vall&group=' . $currentgroupid),
            get_string('summary', 'questionnaire')
        );
        $this->inactive[] = $this->currenttab;
        $this->activated[] = $this->currenttab;

        $this->row3[] = new tabobject(
            'valldefault',
            $this->tab_url('report', $argstr . '&action=vall&group=' . $currentgroupid),
            get_string('order_default', 'questionnaire')
        );
        $this->row3[] = new tabobject(
            'vallasort',
            $this->tab_url('report', $argstr . '&action=vallasort&group=' . $currentgroupid),
            get_string('order_ascending', 'questionnaire')
        );
        $this->row3[] = new tabobject(
            'vallarsort',
            $this->tab_url('report', $argstr . '&action=vallarsort&group=' . $currentgroupid),
            get_string('order_descending', 'questionnaire')
        );
        if ($this->questionnaire->capabilities()->can_delete_responses()) {
            $this->row2[] = new tabobject(
                'deleteall',
                $this->tab_url('report', $argstr . '&action=delallresp'),
                get_string('deleteallresponses', 'questionnaire')
            );
        }
        if ($this->questionnaire->capabilities()->can_download_responses()) {
            // Preserved verbatim: the original path emits a relative URL here (no wwwroot prefix).
            $link = htmlspecialchars('/mod/questionnaire/report.php?' . $argstr . '&action=dwnpg');
            $this->row2[] = new tabobject('downloadcsv', $link, get_string('downloadtextformat', 'questionnaire'));
        }
        if (count($this->row2) <= 1) {
            $this->activetab = 'allreport';
        }
    }

    /**
     * Add the "Show non-respondents" tab if the user can view individual responses and any group.
     *
     * @param bool $canviewallgroups
     * @param bool $canviewgroups
     */
    private function add_nonrespondents_tab(bool $canviewallgroups, bool $canviewgroups): void {
        if (!$this->questionnaire->capabilities()->can_view_single_response()) {
            return;
        }
        if (!($canviewallgroups || $canviewgroups)) {
            return;
        }
        $nonrespondenturl = new moodle_url(
            '/mod/questionnaire/show_nonrespondents.php',
            ['id' => $this->questionnaire->coursemodule()->id]
        );
        $this->row[] = new tabobject(
            'nonrespondents',
            $nonrespondenturl->out(),
            get_string('show_nonrespondents', 'questionnaire')
        );
    }

    /**
     * Assemble the non-empty tab rows and stamp them into the page's tabsarea.
     *
     * @param object $page Templatable page that exposes add_to_page('tabsarea', $html).
     */
    private function emit_tabs(object $page): void {
        $tabs = [];
        if ((count($this->row) > 1) || (!empty($this->row2) && count($this->row2) > 1)) {
            $tabs[] = $this->row;
            if (!empty($this->row2) && count($this->row2) > 1) {
                $tabs[] = $this->row2;
            }
            if (!empty($this->row3) && count($this->row3) > 1) {
                $tabs[] = $this->row3;
            }
            $page->add_to_page('tabsarea', print_tabs($tabs, $this->activetab, $this->inactive, $this->activated, true));
        }
    }
}
