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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use mod_questionnaire\questionnaire;
use moodle_url;
use stdClass;

/**
 * The sortable, pageable list of non-respondents for a named ("fullname") questionnaire.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nonrespondents_table extends \flexible_table {
    /** @var questionnaire */
    protected questionnaire $questionnaire;

    /** @var bool Whether the viewer may message non-respondents (adds the select column). */
    protected bool $canmessage;

    /** @var bool Whether the questionnaire allows save-and-resume (adds the status column). */
    protected bool $showstatus;

    /** @var array|null Country code => name, loaded on first use. */
    protected ?array $countries = null;

    /** @var array[] Rows added so far; flexible_table echoes rows as they are added, so they wait for render(). */
    protected array $rows = [];

    /**
     * Define the columns and table behaviour.
     *
     * @param questionnaire $questionnaire
     * @param moodle_url $baseurl
     */
    public function __construct(questionnaire $questionnaire, moodle_url $baseurl) {
        parent::__construct('questionnaire-shownonrespondents-' . $questionnaire->courseid());
        $this->questionnaire = $questionnaire;
        $context = $questionnaire->context();
        $this->canmessage = has_capability('mod/questionnaire:message', $context);
        $this->showstatus = $questionnaire->resume();

        $columns = ['userpic' => get_string('userpic'), 'fullname' => get_string('fullnameuser')];
        $identityfields = \core_user\fields::get_identity_fields($context, false);
        if (in_array('email', $identityfields) || has_capability('moodle/course:viewhiddenuserfields', $context)) {
            $columns['email'] = get_string('email');
        }
        $columns['city'] = get_string('city');
        $columns['country'] = get_string('country');
        $columns['lastaccess'] = get_string('lastaccess');
        if ($this->showstatus) {
            $columns['status'] = get_string('status');
        }
        if ($this->canmessage) {
            $columns['select'] = get_string('select');
        }

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));
        $this->define_baseurl($baseurl);
        $this->sortable(true, 'lastname', SORT_DESC);
        $this->no_sorting('userpic');
        $this->no_sorting('status');
        $this->no_sorting('select');
        $this->initialbars(false);
        $this->set_attribute('id', 'showentrytable');
        $this->set_attribute('class', 'flexible generaltable generalbox');
        $this->set_control_variables([
            TABLE_VAR_SORT => 'ssort',
            TABLE_VAR_IFIRST => 'sifirst',
            TABLE_VAR_ILAST => 'silast',
            TABLE_VAR_PAGE => 'spage',
        ]);
    }

    /**
     * Add one non-respondent's row.
     *
     * @param stdClass $user Full user record.
     * @param bool $started Whether the user has a saved, unsubmitted response.
     */
    public function add_user(stdClass $user, bool $started): void {
        global $OUTPUT;
        $courseid = $this->questionnaire->courseid();
        $row = [];
        foreach ($this->columns as $column => $unused) {
            switch ($column) {
                case 'userpic':
                    $row[] = $OUTPUT->user_picture($user, ['courseid' => $courseid]);
                    break;
                case 'fullname':
                    $row[] = \html_writer::link(
                        new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $courseid]),
                        fullname($user)
                    );
                    break;
                case 'email':
                    $row[] = s($user->email);
                    break;
                case 'city':
                    $row[] = s($user->city);
                    break;
                case 'country':
                    $row[] = $this->country_name($user->country);
                    break;
                case 'lastaccess':
                    $row[] = $user->lastaccess ? format_time(time() - $user->lastaccess) : get_string('never');
                    break;
                case 'status':
                    $row[] = get_string($started ? 'started' : 'not_started', 'questionnaire');
                    break;
                case 'select':
                    $row[] = $this->select_checkbox($user, $started);
                    break;
            }
        }
        $this->rows[] = $row;
    }

    /**
     * The table's rendered HTML, with every row added so far.
     *
     * @return string
     */
    public function render(): string {
        ob_start();
        foreach ($this->rows as $row) {
            $this->add_data($row);
        }
        $this->rows = [];
        $this->finish_output();
        return ob_get_clean();
    }

    /**
     * The display name of a country code.
     *
     * @param string $code
     * @return string
     */
    private function country_name(string $code): string {
        if ($code === '') {
            return '';
        }
        if ($this->countries === null) {
            $this->countries = get_string_manager()->get_list_of_countries();
        }
        return $this->countries[$code] ?? '';
    }

    /**
     * The labelled select-this-user checkbox; data-started drives the "Select started/not started" buttons.
     *
     * @param stdClass $user
     * @param bool $started
     * @return string
     */
    private function select_checkbox(stdClass $user, bool $started): string {
        $id = 'messageuser_' . $user->id;
        return \html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'usercheckbox',
            'name' => 'messageuser[]',
            'value' => $user->id,
            'id' => $id,
            'data-started' => $started ? 1 : 0,
        ]) . \html_writer::label(get_string('selectitem', 'moodle', fullname($user)), $id, false, ['class' => 'accesshide']);
    }
}
