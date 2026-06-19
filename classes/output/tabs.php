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
 * Replaces the legacy procedural tabs.php include. Each call site supplies the
 * literal tab name it owns, so the current tab is no longer carried between
 * requests via $SESSION->questionnaire->current_tab.
 */
class tabs {
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
        global $CFG, $USER;

        $questionnaire = $this->questionnaire;
        $currenttab = $this->currenttab;
        $currentgroupid = $this->currentgroupid ?? 0;
        $rid = $this->rid;

        $tabs = [];
        $row = [];
        $inactive = [];
        $activated = [];

        $owner = $questionnaire->is_survey_owner();
        if ($questionnaire->capabilities()->can_manage_questionnaire() && $owner) {
            $row[] = new tabobject(
                'settings',
                $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/qsettings.php?' . 'id=' . $questionnaire->coursemodule()->id),
                get_string('advancedsettings')
            );
        }

        if ($questionnaire->capabilities()->can_edit_questions() && $owner) {
            $row[] = new tabobject(
                'questions',
                $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/questions.php?' . 'id=' . $questionnaire->coursemodule()->id),
                get_string('questions', 'questionnaire')
            );
        }

        if ($questionnaire->capabilities()->can_edit_questions() && $owner) {
            $row[] = new tabobject(
                'feedback',
                $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/feedback.php?' . 'id=' . $questionnaire->coursemodule()->id),
                get_string('feedback')
            );
        }

        if ($questionnaire->capabilities()->can_preview() && $owner) {
            if (!empty($questionnaire->questions())) {
                $previewurl = $CFG->wwwroot . htmlspecialchars(
                    '/mod/questionnaire/preview.php?id=' . $questionnaire->coursemodule()->id
                );
                $row[] = new tabobject('preview', $previewurl, get_string('preview_label', 'questionnaire'));
            }
        }

        $usernumresp = $questionnaire->count_submissions($USER->id);

        if ($questionnaire->capabilities()->can_read_own_responses() && ($usernumresp > 0)) {
            $argstr = 'instance=' . $questionnaire->id()
                . '&user=' . $USER->id . '&group=' . $currentgroupid;
            if ($usernumresp == 1) {
                $argstr .= '&byresponse=1&action=vresp';
                $yourrespstring = get_string('yourresponse', 'questionnaire');
            } else {
                $yourrespstring = get_string('yourresponses', 'questionnaire');
            }
            $row[] = new tabobject(
                'myreport',
                $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/myreport.php?' . $argstr),
                $yourrespstring
            );

            if ($usernumresp > 1 && in_array($currenttab, ['mysummary', 'mybyresponse', 'myvall', 'mydownloadcsv'])) {
                $inactive[] = 'myreport';
                $activated[] = 'myreport';
                $row2 = [];
                $argstr2 = $argstr . '&action=summary';
                $row2[] = new tabobject(
                    'mysummary',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/myreport.php?' . $argstr2),
                    get_string('summary', 'questionnaire')
                );
                $argstr2 = $argstr . '&byresponse=1&action=vresp';
                $row2[] = new tabobject(
                    'mybyresponse',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/myreport.php?' . $argstr2),
                    get_string('viewindividualresponse', 'questionnaire')
                );
                $argstr2 = $argstr . '&byresponse=0&action=vall&group=' . $currentgroupid;
                $row2[] = new tabobject(
                    'myvall',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/myreport.php?' . $argstr2),
                    get_string('myresponses', 'questionnaire')
                );
                if ($questionnaire->capabilities()->can_download_responses()) {
                    $argstr2 = $argstr . '&action=dwnpg';
                    $link = $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2);
                    $row2[] = new tabobject('mydownloadcsv', $link, get_string('downloadtextformat', 'questionnaire'));
                }
            } else if (in_array($currenttab, ['mybyresponse', 'mysummary'])) {
                $inactive[] = 'myreport';
                $activated[] = 'myreport';
            }
        }

        $numresp = $questionnaire->count_submissions();

        // If questionnaire is set to separate groups, prevent user who is not member of any group
        // to view All responses.
        $canviewgroups = true;
        $groupmode = groups_get_activity_groupmode($questionnaire->coursemodule(), $questionnaire->course());
        if ($groupmode == 1) {
            $canviewgroups = groups_has_membership($questionnaire->coursemodule(), $USER->id);
        }
        $canviewallgroups = has_capability('moodle/site:accessallgroups', $questionnaire->context());
        $grouplogic = $canviewallgroups || $canviewgroups;
        $resplogic = ($numresp > 0);

        if ($questionnaire->capabilities()->can_view_all_responses_anytime($grouplogic, $resplogic)) {
            $argstr = 'instance=' . $questionnaire->id();
            $row[] = new tabobject(
                'allreport',
                $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr . '&action=vall'),
                get_string('viewallresponses', 'questionnaire')
            );
            if (
                in_array(
                    $currenttab,
                    [
                        'vall',
                        'vresp',
                        'valldefault',
                        'vallasort',
                        'vallarsort',
                        'deleteall',
                        'downloadcsv',
                        'vrespsummary',
                        'individualresp',
                        'printresp',
                        'deleteresp',
                    ]
                )
            ) {
                $inactive[] = 'allreport';
                $activated[] = 'allreport';
                if ($currenttab == 'vrespsummary' || $currenttab == 'valldefault') {
                    $inactive[] = 'vresp';
                }
                $row2 = [];
                $argstr2 = $argstr . '&action=vall&group=' . $currentgroupid;
                $row2[] = new tabobject(
                    'vall',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                    get_string('summary', 'questionnaire')
                );
                if ($questionnaire->capabilities()->can_view_single_response()) {
                    $argstr2 = $argstr . '&byresponse=1&action=vresp&group=' . $currentgroupid;
                    $row2[] = new tabobject(
                        'vrespsummary',
                        $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                        get_string('viewbyresponse', 'questionnaire')
                    );
                    if ($currenttab == 'individualresp' || $currenttab == 'deleteresp') {
                        $argstr2 = $argstr . '&byresponse=1&action=vresp';
                        $row2[] = new tabobject(
                            'vresp',
                            $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                            get_string('viewindividualresponse', 'questionnaire')
                        );
                    }
                }
            }
            if (in_array($currenttab, ['valldefault', 'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'])) {
                $activated[] = 'vall';
                $row3 = [];

                $argstr2 = $argstr . '&action=vall&group=' . $currentgroupid;
                $row3[] = new tabobject(
                    'valldefault',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                    get_string('order_default', 'questionnaire')
                );
                if ($currenttab != 'downloadcsv' && $currenttab != 'deleteall') {
                    $argstr2 = $argstr . '&action=vallasort&group=' . $currentgroupid;
                    $row3[] = new tabobject(
                        'vallasort',
                        $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                        get_string('order_ascending', 'questionnaire')
                    );
                    $argstr2 = $argstr . '&action=vallarsort&group=' . $currentgroupid;
                    $row3[] = new tabobject(
                        'vallarsort',
                        $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                        get_string('order_descending', 'questionnaire')
                    );
                }
                if ($questionnaire->capabilities()->can_delete_responses()) {
                    $argstr2 = $argstr . '&action=delallresp&group=' . $currentgroupid;
                    $row3[] = new tabobject(
                        'deleteall',
                        $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                        get_string('deleteallresponses', 'questionnaire')
                    );
                }

                if ($questionnaire->capabilities()->can_download_responses()) {
                    $argstr2 = $argstr . '&action=dwnpg&group=' . $currentgroupid;
                    $link = $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2);
                    $row3[] = new tabobject('downloadcsv', $link, get_string('downloadtextformat', 'questionnaire'));
                }
            }

            if (in_array($currenttab, ['individualresp', 'deleteresp'])) {
                $inactive[] = 'vresp';
                if ($currenttab != 'deleteresp') {
                    $activated[] = 'vresp';
                }
                if ($questionnaire->capabilities()->can_delete_responses()) {
                    $argstr2 = $argstr . '&action=dresp&rid=' . $rid . '&individualresponse=1';
                    $row2[] = new tabobject(
                        'deleteresp',
                        $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                        get_string('deleteresp', 'questionnaire')
                    );
                }
            }
        } else if (
            $questionnaire->capabilities()
                ->can_view_all_responses_with_restrictions($usernumresp, $grouplogic, $resplogic)
        ) {
            $argstr = 'instance=' . $questionnaire->id() . '&sid=' . $questionnaire->surveyid();
            $allreporturl = $CFG->wwwroot . htmlspecialchars(
                '/mod/questionnaire/report.php?' . $argstr . '&action=vall&group=' . $currentgroupid
            );
            $row[] = new tabobject(
                'allreport',
                $allreporturl,
                get_string('viewallresponses', 'questionnaire')
            );
            if (in_array($currenttab, ['valldefault', 'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'])) {
                $inactive[] = 'vall';
                $activated[] = 'vall';
                $row2 = [];
                $argstr2 = $argstr . '&action=vall&group=' . $currentgroupid;
                $row2[] = new tabobject(
                    'valldefault',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                    get_string('summary', 'questionnaire')
                );
                $inactive[] = $currenttab;
                $activated[] = $currenttab;
                $row3 = [];
                $argstr2 = $argstr . '&action=vall&group=' . $currentgroupid;
                $row3[] = new tabobject(
                    'valldefault',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                    get_string('order_default', 'questionnaire')
                );
                $argstr2 = $argstr . '&action=vallasort&group=' . $currentgroupid;
                $row3[] = new tabobject(
                    'vallasort',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                    get_string('order_ascending', 'questionnaire')
                );
                $argstr2 = $argstr . '&action=vallarsort&group=' . $currentgroupid;
                $row3[] = new tabobject(
                    'vallarsort',
                    $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                    get_string('order_descending', 'questionnaire')
                );
                if ($questionnaire->capabilities()->can_delete_responses()) {
                    $argstr2 = $argstr . '&action=delallresp';
                    $row2[] = new tabobject(
                        'deleteall',
                        $CFG->wwwroot . htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2),
                        get_string('deleteallresponses', 'questionnaire')
                    );
                }

                if ($questionnaire->capabilities()->can_download_responses()) {
                    $argstr2 = $argstr . '&action=dwnpg';
                    $link = htmlspecialchars('/mod/questionnaire/report.php?' . $argstr2);
                    $row2[] = new tabobject('downloadcsv', $link, get_string('downloadtextformat', 'questionnaire'));
                }
                if (count($row2) <= 1) {
                    $currenttab = 'allreport';
                }
            }
        }

        if ($questionnaire->capabilities()->can_view_single_response() && ($canviewallgroups || $canviewgroups)) {
            $nonrespondenturl = new moodle_url(
                '/mod/questionnaire/show_nonrespondents.php',
                ['id' => $questionnaire->coursemodule()->id]
            );
            $row[] = new tabobject(
                'nonrespondents',
                $nonrespondenturl->out(),
                get_string('show_nonrespondents', 'questionnaire')
            );
        }

        if ((count($row) > 1) || (!empty($row2) && (count($row2) > 1))) {
            $tabs[] = $row;

            if (!empty($row2) && (count($row2) > 1)) {
                $tabs[] = $row2;
            }

            if (!empty($row3) && (count($row3) > 1)) {
                $tabs[] = $row3;
            }

            $page->add_to_page('tabsarea', print_tabs($tabs, $currenttab, $inactive, $activated, true));
        }
    }
}
