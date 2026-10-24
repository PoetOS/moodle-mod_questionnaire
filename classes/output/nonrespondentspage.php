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

use mod_questionnaire\local\report\nonrespondent_messenger;
use mod_questionnaire\questionnaire;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * The non-respondents page (show_nonrespondents.php): who has not submitted, and the form for messaging them.
 *
 * Named questionnaires list the non-respondents in a nonrespondents_table with a select checkbox per user;
 * anonymous ones only show counts and offer a recipient choice (all / started / not started).
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nonrespondentspage implements \renderable, \templatable {
    /** @var string Id of the message body textarea (the editor attaches to it). */
    public const MESSAGE_ID = 'questionnaire_message';

    /** @var questionnaire */
    protected questionnaire $questionnaire;

    /** @var int Total number of non-respondents (all pages). */
    protected int $count;

    /** @var string Rendered group selector, or ''. */
    protected string $groupselect = '';

    /** @var nonrespondents_table|null The user list (named questionnaires only). */
    protected ?nonrespondents_table $table = null;

    /** @var array|null Show-all / show-per-page link: ['url' => moodle_url, 'label' => string]. */
    protected ?array $pagingtoggle = null;

    /** @var bool Whether every non-respondent is on this page, so status-based selection is meaningful. */
    protected bool $allonpage = false;

    /** @var int Anonymous questionnaires: non-respondents with a saved response. */
    protected int $started = 0;

    /** @var string Message subject to redisplay. */
    protected string $subject = '';

    /** @var string Message body to redisplay. */
    protected string $message = '';

    /** @var string Anonymous recipient choice to redisplay. */
    protected string $selected = nonrespondent_messenger::SELECT_NONE;

    /** @var string|null Validation error to show above the form. */
    protected ?string $error = null;

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire
     * @param int $count Total number of non-respondents (all pages).
     * @param string $groupselect Rendered group selector, or ''.
     */
    public function __construct(questionnaire $questionnaire, int $count, string $groupselect = '') {
        $this->questionnaire = $questionnaire;
        $this->count = $count;
        $this->groupselect = $groupselect;
    }

    /**
     * Show the user list (named questionnaires).
     *
     * @param nonrespondents_table $table
     * @param bool $allonpage Whether every non-respondent is on this page.
     * @param moodle_url|null $toggleurl Show-all / show-per-page link, or null.
     * @param string $togglelabel
     */
    public function set_table(
        nonrespondents_table $table,
        bool $allonpage,
        ?moodle_url $toggleurl = null,
        string $togglelabel = ''
    ): void {
        $this->table = $table;
        $this->allonpage = $allonpage;
        $this->pagingtoggle = $toggleurl ? ['url' => $toggleurl, 'label' => $togglelabel] : null;
    }

    /**
     * Set how many anonymous non-respondents have a saved response.
     *
     * @param int $started
     */
    public function set_started_count(int $started): void {
        $this->started = $started;
    }

    /**
     * Redisplay what was submitted, with an optional validation error.
     *
     * @param string $subject
     * @param string $message
     * @param string $selected Anonymous recipient choice.
     * @param string|null $error
     */
    public function set_submission(string $subject, string $message, string $selected, ?string $error = null): void {
        $this->subject = $subject;
        $this->message = $message;
        $this->selected = $selected ?: nonrespondent_messenger::SELECT_NONE;
        $this->error = $error;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $anonymous = $this->questionnaire->respondenttype() != 'fullname';
        $canmessage = has_capability('mod/questionnaire:message', $this->questionnaire->context());

        $data = new stdClass();
        $data->groupselect = $this->groupselect;
        $data->empty = $this->count == 0;
        $data->count = $this->count;
        $data->anonymous = $anonymous;
        $data->table = $this->table ? $this->table->render() : null;
        $data->pagingtoggle = $this->pagingtoggle
            ? ['url' => $this->pagingtoggle['url']->out(false), 'label' => $this->pagingtoggle['label']]
            : null;
        $data->canmessage = $canmessage && !$data->empty;
        $data->selectbuttons = $data->canmessage && $this->table !== null;
        $data->statusbuttons = $data->selectbuttons && $this->allonpage && $this->questionnaire->resume();
        $data->recipients = ($data->canmessage && $anonymous) ? $this->recipient_options() : [];
        $data->hasrecipients = !empty($data->recipients);
        $data->error = $this->error;
        $data->formaction = (new moodle_url('/mod/questionnaire/show_nonrespondents.php'))->out(false);
        $data->cmid = $this->questionnaire->coursemodule()->id;
        $data->sesskey = sesskey();
        $data->subject = $this->subject;
        $data->message = $this->message;
        $data->messageid = self::MESSAGE_ID;
        $data->messageformat = FORMAT_HTML;

        if ($data->canmessage) {
            // A reminder has no file area to carry attachments, so the editor offers none.
            $options = ['maxfiles' => 0] + \mod_questionnaire\form_options::editor($this->questionnaire->context());
            editors_get_preferred_editor(FORMAT_HTML)->use_editor(self::MESSAGE_ID, $options);
        }
        return $data;
    }

    /**
     * The anonymous recipient radio options, with counts on the status options.
     *
     * @return array[]
     */
    private function recipient_options(): array {
        $options = [
            [nonrespondent_messenger::SELECT_NONE, get_string('none')],
            [nonrespondent_messenger::SELECT_ALL, get_string('all', 'questionnaire')],
        ];
        $notstarted = $this->count - $this->started;
        if ($this->questionnaire->resume()) {
            if ($this->started > 0) {
                $options[] = [
                    nonrespondent_messenger::SELECT_STARTED,
                    get_string('status') . ': ' . get_string('started', 'questionnaire') . ' (' . $this->started . ')',
                ];
            }
            if ($notstarted > 0) {
                $options[] = [
                    nonrespondent_messenger::SELECT_NOTSTARTED,
                    get_string('status') . ': ' . get_string('not_started', 'questionnaire') . ' (' . $notstarted . ')',
                ];
            }
        }
        return array_map(fn($option) => [
            'value' => $option[0],
            'id' => 'questionnaire_recipients_' . $option[0],
            'label' => $option[1],
            'checked' => $option[0] === $this->selected,
        ], $options);
    }
}
