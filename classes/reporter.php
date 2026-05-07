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

namespace mod_questionnaire;

use mod_questionnaire\local\feedback\section;
use mod_questionnaire\local\question\choice;
use mod_questionnaire\local\question\question;
use mod_questionnaire\local\question\rate;
use mod_questionnaire\local\response\questionnaire_responses;
use html_writer;
use html_table;
use stdClass;

/**
 * Reporting and data-export operations for a questionnaire.
 *
 * Owns all logic for generating CSV exports, response analysis, and result
 * display that was previously spread across the legacy questionnaire class.
 * Receives a questionnaire instance at construction and reads survey, question,
 * context, and response data through it.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class reporter {
    /** @var questionnaire The questionnaire being reported on. */
    private questionnaire $questionnaire;

    /** @var questionnaire_responses|null Cached response collection shared across methods. */
    private ?questionnaire_responses $responsecollection = null;

    /**
     * Construct a reporter for the given questionnaire.
     *
     * @param questionnaire $questionnaire The questionnaire being reported on.
     */
    public function __construct(questionnaire $questionnaire) {
        $this->questionnaire = $questionnaire;
    }

    /**
     * Load all responses for the given user into the shared response store.
     *
     * Must be called before view_all_responses() when using the two-step pattern.
     *
     * @param int|null $userid Load for this user, or null for the current user.
     * @return void
     */
    public function add_user_responses(?int $userid = null): void {
        $this->responsecollection()->add_user_responses($userid);
    }

    /**
     * Render all loaded responses for display.
     *
     * Uses the response collection populated by add_user_responses().
     *
     * @return void
     */
    public function view_all_responses(): void {
        $this->print_survey_start('', 1, 1, 0);

        $loadedresponses = $this->responsecollection()->get_loaded_responses();
        if (!empty($loadedresponses)) {
            $this->questionnaire->page->add_to_page(
                'responses',
                $this->questionnaire->renderer->all_response_output(
                    $loadedresponses,
                    $this->questionnaire->questions(),
                    $this->questionnaire
                )
            );
        } else {
            $this->questionnaire->page->add_to_page(
                'responses',
                $this->questionnaire->renderer->all_response_output(get_string('noresponses', 'questionnaire'))
            );
        }

        $this->print_survey_end(1, 1);
    }

    /**
     * Render aggregate survey results into the page object.
     *
     * @param array|string $rids Response id(s) to show ('' or [] = all responses).
     * @param int|false $uid User id filter (false = all users).
     * @param bool $pdf True when rendering to PDF.
     * @param string $currentgroupid Active group filter ('0' or a group id).
     * @param string $sort Column sort direction for results tables.
     * @return void
     */
    public function survey_results(
        $rids = '',
        $uid = false,
        bool $pdf = false,
        string $currentgroupid = '',
        string $sort = ''
    ): void {
        global $SESSION, $DB;

        $SESSION->questionnaire->noresponses = false;

        $survey = $this->questionnaire->survey();
        $questions = $this->questionnaire->questions();

        if (empty($survey) || empty($questions)) {
            return;
        }

        if (!empty($rids)) {
            $ridlist = $rids;
        } else {
            $userview = optional_param('responsestats', 0, PARAM_ALPHA);
            if ($uid !== false) {
                $rows = $this->questionnaire->get_responses($uid);
            } else if ($currentgroupid == 0) {
                $rows = $this->questionnaire->get_responses();
            } else {
                $rows = $this->questionnaire->get_responses(false, (int)$currentgroupid);
            }
            if (!$rows) {
                $this->questionnaire->page->add_to_page(
                    'respondentinfo',
                    $this->questionnaire->renderer->notification(
                        get_string('noresponses', 'questionnaire'),
                        \core\output\notification::NOTIFY_ERROR
                    )
                );
                $SESSION->questionnaire->noresponses = true;
                return;
            }
            $numresps = count($rows);
            $respondentstring = get_string('responses', 'questionnaire');
            if ($userview === 'y') {
                $respondentstring = get_string('submissions', 'questionnaire');
            }
            if (!$userview) {
                $completedcount = 0;
                $inprogresscount = 0;
                foreach ($rows as $row) {
                    if ($row->complete === 'y') {
                        $completedcount++;
                    } else if ($row->complete === 'n') {
                        $inprogresscount++;
                    }
                }
                $numresps .= ' ' . get_string(
                    'responses_breakdown',
                    'questionnaire',
                    [
                        'responses'  => $completedcount,
                        'incomplete' => $inprogresscount,
                    ]
                );
            }
            $respondentinfo = ' ' . $respondentstring . ': <strong>' . $numresps . '</strong>';
            $this->questionnaire->page->add_to_page('respondentinfo', $respondentinfo);
            if (empty($rows)) {
                return;
            }
            $ridlist = [];
            foreach ($rows as $row) {
                $ridlist[] = $row->id;
            }
        }

        if ($survey->title() !== '') {
            $this->questionnaire->page->add_to_page('title', format_string($survey->title()));
        }
        if ($survey->subtitle() !== '') {
            $this->questionnaire->page->add_to_page('subtitle', format_string($survey->subtitle()));
        }
        if ($survey->info() !== '') {
            $infotext = file_rewrite_pluginfile_urls(
                $survey->info(),
                'pluginfile.php',
                $this->questionnaire->context()->id,
                'mod_questionnaire',
                'info',
                $this->questionnaire->surveyid()
            );
            $this->questionnaire->page->add_to_page('addinfo', format_text($infotext, FORMAT_HTML, ['noclean' => true]));
        }

        $qnum = 0;
        $anonymous = $this->questionnaire->respondenttype() == 'anonymous';

        $viewsingleresponse = $this->questionnaire->capabilities()->can_view_single_response();
        $respondenttype = $this->questionnaire->respondenttype();
        $surveyid = $this->questionnaire->surveyid();
        foreach ($questions as $question) {
            if (isset($question->responsetype) && is_object($question->responsetype)) {
                $question->responsetype->set_display_context($viewsingleresponse, $respondenttype, $surveyid);
            }
            if ($question->typeid() == QUESPAGEBREAK) {
                continue;
            }
            if ($question->is_numbered()) {
                $qnum++;
            }
            $displaycontent = $question->content();
            if ($displaycontent == '<p>  </p>') {
                $displaycontent = '';
            }
            if ($pdf) {
                $response = new stdClass();
                if ($this->questionnaire->questions_autonumbered() && $question->is_numbered()) {
                    $response->qnum = $qnum;
                }
                $response->qcontent = format_text(
                    file_rewrite_pluginfile_urls(
                        $displaycontent,
                        'pluginfile.php',
                        $question->context->id,
                        'mod_questionnaire',
                        'question',
                        $question->id()
                    ),
                    FORMAT_HTML,
                    ['noclean' => true]
                );
                $response->results = $this->questionnaire->renderer->results_output(
                    $question,
                    $ridlist,
                    $sort,
                    $anonymous,
                    $pdf
                );
                $this->questionnaire->page->add_to_page('responses', $response);
            } else {
                $this->questionnaire->page->add_to_page(
                    'responses',
                    $this->questionnaire->renderer->container_start('qn-container')
                );
                if ($this->questionnaire->questions_autonumbered() && $question->is_numbered()) {
                    $this->questionnaire->page->add_to_page(
                        'responses',
                        $this->questionnaire->renderer->container_start('qn-info')
                    );
                    $this->questionnaire->page->add_to_page(
                        'responses',
                        $this->questionnaire->renderer->heading($qnum, 2, 'qn-number')
                    );
                    $this->questionnaire->page->add_to_page(
                        'responses',
                        $this->questionnaire->renderer->container_end()
                    );
                }
                $this->questionnaire->page->add_to_page(
                    'responses',
                    $this->questionnaire->renderer->container_start('qn-content')
                );
                $this->questionnaire->page->add_to_page(
                    'responses',
                    $this->questionnaire->renderer->container(
                        format_text(
                            file_rewrite_pluginfile_urls(
                                $displaycontent,
                                'pluginfile.php',
                                $question->context->id,
                                'mod_questionnaire',
                                'question',
                                $question->id()
                            ),
                            FORMAT_HTML,
                            ['noclean' => true]
                        ),
                        'qn-question'
                    )
                );
                $this->questionnaire->page->add_to_page(
                    'responses',
                    $this->questionnaire->renderer->results_output($question, $ridlist, $sort, $anonymous)
                );
                $this->questionnaire->page->add_to_page(
                    'responses',
                    $this->questionnaire->renderer->container_end()
                );
                $this->questionnaire->page->add_to_page(
                    'responses',
                    $this->questionnaire->renderer->container_end()
                );
            }
        }
    }

    /**
     * Render the alphabetical/by-response navigation bar for the report page.
     *
     * @param int $currrid Currently displayed response id.
     * @param int $currentgroupid Active group id (0 = all).
     * @param bool $byresponse True when showing respondents list rather than nav arrows.
     * @return void
     */
    public function survey_results_navbar_alpha(
        int $currrid,
        int $currentgroupid,
        bool $byresponse
    ): void {
        global $CFG, $DB;

        $isfullname = $this->questionnaire->respondenttype() !== 'anonymous';
        if ($isfullname) {
            $responses = $this->questionnaire->get_responses(false, $currentgroupid);
        } else {
            $responses = $this->questionnaire->get_responses();
        }
        if (!$responses) {
            return;
        }
        $total = count($responses);
        if ($total === 0) {
            return;
        }

        $rids = [];
        $ridssub = [];
        $ridsuserfullname = [];
        $ridsuserid = [];
        $i = 0;
        $currpos = -1;
        foreach ($responses as $response) {
            $rids[] = $response->id;
            if ($isfullname) {
                $user = $DB->get_record('user', ['id' => $response->userid]);
                $ridssub[] = $response->submitted;
                $ridsuserfullname[] = fullname($user);
                $ridsuserid[] = $response->userid;
            }
            if ($response->id == $currrid) {
                $currpos = $i;
            }
            $i++;
        }

        $url = $CFG->wwwroot . '/mod/questionnaire/report.php?action=vresp&group=' .
            $currentgroupid . '&individualresponse=1';

        if (!$byresponse) {
            $navbar = new stdClass();
            $prevrid = ($currpos > 0) ? $rids[$currpos - 1] : null;
            $nextrid = ($currpos < $total - 1) ? $rids[$currpos + 1] : null;
            $firstrid = $rids[0];
            $lastrid = $rids[$total - 1];
            if ($prevrid != null) {
                $pos = $currpos - 1;
                $firstuserfullname = '';
                $navbar->firstrespondent = ['url' => ($url . '&rid=' . $firstrid)];
                $navbar->previous = ['url' => ($url . '&rid=' . $prevrid)];
                if ($isfullname) {
                    $responsedate = userdate($ridssub[$pos]);
                    $title = $ridsuserfullname[$pos];
                    if ($ridsuserid[$pos] == $ridsuserid[$currpos]) {
                        $title .= ' | ' . $responsedate;
                    }
                    $firstuserfullname = $ridsuserfullname[0];
                } else {
                    $title = '';
                }
                $navbar->firstrespondent['title'] = $firstuserfullname;
                $navbar->previous['title'] = $title;
            }
            $navbar->respnumber = ['currpos' => ($currpos + 1), 'total' => $total];
            if ($nextrid != null) {
                $pos = $currpos + 1;
                $lastuserfullname = '';
                $navbar->lastrespondent = ['url' => ($url . '&rid=' . $lastrid)];
                $navbar->next = ['url' => ($url . '&rid=' . $nextrid)];
                if ($isfullname) {
                    $responsedate = userdate($ridssub[$pos]);
                    $title = $ridsuserfullname[$pos];
                    if ($ridsuserid[$pos] == $ridsuserid[$currpos]) {
                        $title .= ' | ' . $responsedate;
                    }
                    $lastuserfullname = $ridsuserfullname[$total - 1];
                } else {
                    $title = '';
                }
                $navbar->lastrespondent['title'] = $lastuserfullname;
                $navbar->next['title'] = $title;
            }
            $url = $CFG->wwwroot . '/mod/questionnaire/report.php?action=vresp&byresponse=1&group=' . $currentgroupid;
            $navbar->listlink = $url;

            $linkname = '&nbsp;' . get_string('print', 'questionnaire');
            $printurl = '/mod/questionnaire/print.php?qid=' . $this->questionnaire->id() .
                '&rid=' . $currrid . '&courseid=' . $this->questionnaire->courseid() . '&sec=1';
            $title = get_string('printtooltip', 'questionnaire');
            $options = [
                'menubar'   => true,
                'location'  => false,
                'scrollbars' => true,
                'resizable' => true,
                'height'    => 600,
                'width'     => 800,
            ];
            $action = new \popup_action('click', new \moodle_url($printurl), 'popup', $options);
            $navbar->printaction = $this->questionnaire->renderer->action_link(
                new \moodle_url($printurl),
                $linkname,
                $action,
                ['title' => $title],
                new \pix_icon('t/print', $title)
            );
            $this->questionnaire->page->add_to_page(
                'navigationbar',
                $this->questionnaire->renderer->navigationbar($navbar)
            );
        } else {
            $resparr = [];
            for ($i = 0; $i < $total; $i++) {
                if ($isfullname) {
                    $responsedate = userdate($ridssub[$i]);
                    $resparr[] = '<a title = "' . $responsedate . '" href="' . $url . '&amp;rid=' .
                        $rids[$i] . '&amp;individualresponse=1" >' . $ridsuserfullname[$i] . '</a> ';
                } else {
                    $resparr[] = '<a title = "" href="' . $url . '&amp;rid=' .
                        $rids[$i] . '&amp;individualresponse=1" >' .
                        get_string('response', 'questionnaire') . ($i + 1) . '</a> ';
                }
            }
            $entries = count($resparr);
            $maxlines = 20;
            $maxcols = 3;
            if ($entries >= $maxlines) {
                $colnumber = min(intval($entries / $maxlines), $maxcols);
            } else {
                $colnumber = 1;
            }
            $lines = 0;
            $a = 0;
            while ($entries / $colnumber > 1) {
                $lines++;
                $entries = $entries - $colnumber;
            }
            $respcols = new stdClass();
            for ($i = 0; $i < $colnumber; $i++) {
                $colname = 'respondentscolumn' . $i;
                $respcols->$colname = (object)['respondentlink' => []];
                for ($j = 0; $j < $lines; $j++) {
                    $respcols->{$colname}->respondentlink[] = $resparr[$a];
                    $a++;
                }
                if ($entries) {
                    $respcols->{$colname}->respondentlink[] = $resparr[$a];
                    $entries--;
                    $a++;
                }
            }
            $this->questionnaire->page->add_to_page(
                'responses',
                $this->questionnaire->renderer->responselist($respcols)
            );
        }
    }

    /**
     * Render the student response navigation bar for myreport/report pages.
     *
     * @param int $currrid Currently displayed response id.
     * @param int $userid User whose responses are being navigated.
     * @param int $instance Questionnaire instance id (for URL construction).
     * @param array $resps All responses to navigate across.
     * @param string $reporttype 'myreport' or 'report'.
     * @param string $sid Survey id (used in report mode URLs).
     * @return void
     */
    public function survey_results_navbar_student(
        int $currrid,
        int $userid,
        int $instance,
        array $resps,
        string $reporttype = 'myreport',
        string $sid = ''
    ): void {
        global $DB;

        $stranonymous = get_string('anonymous', 'questionnaire');
        $total = count($resps);
        $rids = [];
        $ridssub = [];
        $ridsusers = [];
        $i = 0;
        $currpos = -1;
        foreach ($resps as $response) {
            $rids[] = $response->id;
            $ridssub[] = $response->submitted;
            $ruser = '';
            if ($reporttype === 'report') {
                if ($this->questionnaire->respondenttype() !== 'anonymous') {
                    if ($user = $DB->get_record('user', ['id' => $response->userid])) {
                        $ruser = ' | ' . fullname($user);
                    }
                } else {
                    $ruser = ' | ' . $stranonymous;
                }
            }
            $ridsusers[] = $ruser;
            if ($response->id == $currrid) {
                $currpos = $i;
            }
            $i++;
        }

        $prevrid = ($currpos > 0) ? $rids[$currpos - 1] : null;
        $nextrid = ($currpos < $total - 1) ? $rids[$currpos + 1] : null;

        if ($reporttype === 'myreport') {
            $url = 'myreport.php?instance=' . $instance . '&user=' . $userid .
                '&action=vresp&byresponse=1&individualresponse=1';
        } else {
            $url = 'report.php?instance=' . $instance . '&user=' . $userid .
                '&action=vresp&byresponse=1&individualresponse=1&sid=' . $sid;
        }

        $navbar = new stdClass();
        $displaypos = 1;
        if ($prevrid !== null) {
            $title = userdate($ridssub[$currpos - 1]) . $ridsusers[$currpos - 1];
            $navbar->previous = ['url' => ($url . '&rid=' . $prevrid), 'title' => $title];
        }
        for ($i = 0; $i < $currpos; $i++) {
            $title = userdate($ridssub[$i]) . $ridsusers[$i];
            $navbar->prevrespnumbers[] = [
                'url' => ($url . '&rid=' . $rids[$i]),
                'title' => $title,
                'respnumber' => $displaypos,
            ];
            $displaypos++;
        }
        $navbar->currrespnumber = $displaypos;
        for (++$i; $i < $total; $i++) {
            $displaypos++;
            $title = userdate($ridssub[$i]) . $ridsusers[$i];
            $navbar->nextrespnumbers[] = [
                'url' => ($url . '&rid=' . $rids[$i]),
                'title' => $title,
                'respnumber' => $displaypos,
            ];
        }
        if ($nextrid !== null) {
            $title = userdate($ridssub[$currpos + 1]) . $ridsusers[$currpos + 1];
            $navbar->next = ['url' => ($url . '&rid=' . $nextrid), 'title' => $title];
        }
        $this->questionnaire->page->add_to_page(
            'navigationbar',
            $this->questionnaire->renderer->usernavigationbar($navbar)
        );
        $this->questionnaire->page->add_to_page(
            'bottomnavigationbar',
            $this->questionnaire->renderer->usernavigationbar($navbar)
        );
    }

    /**
     * Generate CSV export data for all (or filtered) responses.
     *
     * Returns a 2-D array where row 0 is the column header row and subsequent
     * rows are individual response data rows.
     *
     * @param int $currentgroupid Group id filter (0 = all groups).
     * @param string $rid Response id filter ('' = all).
     * @param int|string $userid User id filter ('' = all).
     * @param int|null $choicecodes Include numeric choice codes in output.
     * @param int $choicetext Include choice text in output.
     * @param int $showincompletes Include incomplete responses.
     * @param int $rankaverages Append a row of rank-question averages.
     * @return array Rows of CSV data; row 0 is the header row.
     */
    public function generate_csv(
        int $currentgroupid = 0,
        string $rid = '',
        $userid = '',
        ?int $choicecodes = null,
        int $choicetext = 1,
        int $showincompletes = 0,
        int $rankaverages = 0
    ): array {
        global $DB;

        raise_memory_limit('1G');

        $questions = $this->questionnaire->questions();
        $output = [];
        $stringother = get_string('other', 'questionnaire');

        $config = get_config('questionnaire', 'downloadoptions');
        $options = empty($config) ? [] : explode(',', $config);
        if ($showincompletes == 1) {
            $options[] = 'complete';
        }
        $columns = [];
        $types = [];
        foreach ($options as $option) {
            if (in_array($option, ['response', 'submitted', 'id', 'useridnumber'])) {
                $columns[] = get_string($option, 'questionnaire');
                $types[] = 0;
            } else if ($option == 'useridentityfields') {
                // Ignore option.
                continue;
            } else {
                $columns[] = get_string($option);
                $types[] = 1;
            }
        }
        $identityfields = $this->get_identity_fields($options);
        foreach ($identityfields as $field) {
            $columns[] = \core_user\fields::get_display_name($field);
        }
        $nbinfocols = count($columns);

        $idtocsvmap = [
            '0', // 0: unused
            '0', // 1: bool -> boolean
            '1', // 2: text -> string
            '1', // 3: essay -> string
            '0', // 4: radio -> string
            '0', // 5: check -> string
            '0', // 6: dropdn -> string
            '0', // 7: rating -> number
            '0', // 8: rate -> number
            '1', // 9: date -> string
            '0', // 10: numeric -> number.
            '0', // 11: slider -> number.
        ];

        $sid = $this->questionnaire->surveyid();
        if (!$DB->get_record('questionnaire_survey', ['id' => $sid])) {
            throw new \moodle_exception('surveynotexists', 'mod_questionnaire');
        }

        // Get all responses for this survey in one go.
        $allresponsesrs = $this->questionnaire->responses()->get_survey_all_responses(
            $rid,
            $userid,
            $currentgroupid,
            $showincompletes
        );

        // Do we have any questions of type RADIO, DROP, CHECKBOX OR RATE?
        $choicetypes = question::typeids_with_choices();

        // Get unique list of question types used in this survey.
        $uniquetypes = $this->get_survey_questiontypes();

        if (count(array_intersect($choicetypes, $uniquetypes)) > 0) {
            $choiceparams = [$sid];
            $choicesql = "
                SELECT DISTINCT c.id as cid, q.id as qid, q.precise AS precise, q.name, c.content
                  FROM {questionnaire_question} q
                  JOIN {questionnaire_quest_choice} c ON questionid = q.id
                 WHERE q.surveyid = ? ORDER BY cid ASC
            ";
            $choicerecords = $DB->get_records_sql($choicesql, $choiceparams);
            $choicesbyqid = [];
            if (!empty($choicerecords)) {
                foreach ($choicerecords as $choicerecord) {
                    if (!isset($choicesbyqid[$choicerecord->qid])) {
                        $choicesbyqid[$choicerecord->qid] = [];
                    }
                    $choicesbyqid[$choicerecord->qid][$choicerecord->cid] = $choicerecord;
                }
            }
        }

        $num = 1;

        $questionidcols = [];

        foreach ($questions as $question) {
            // Skip questions that aren't response capable.
            if (!isset($question->responsetype)) {
                continue;
            }
            $qid = $question->id();
            $qpos = $question->position();
            $col = $question->name();
            $type = $question->typeid();
            if (in_array($type, $choicetypes)) {
                if (!isset($choicesbyqid[$qid])) {
                    throw new \coding_exception('Choice question has no choices!', 'question id ' . $qid . ' of type ' . $type);
                }
                $choices = $choicesbyqid[$qid];

                switch ($type) {
                    case QUESRADIO:
                    case QUESDROP:
                        $columns[][$qpos] = $col;
                        $questionidcols[][$qpos] = $qid;
                        array_push($types, $idtocsvmap[$type]);
                        $thisnum = 1;
                        foreach ($choices as $choicerecord) {
                            $content = $choicerecord->content;
                            if (choice::content_is_other_choice($content)) {
                                $col = $choicerecord->name . '_' . $stringother;
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = null;
                                array_push($types, '0');
                            }
                        }
                        break;

                    case QUESCHECK:
                        $thisnum = 1;
                        foreach ($choices as $choicerecord) {
                            $content = $choicerecord->content;
                            $modality = '';
                            $contents = question::parse_choice_content($content);
                            if ($contents->modname) {
                                $modality = $contents->modname;
                            } else if ($contents->title) {
                                $modality = $contents->title;
                            } else {
                                $modality = strip_tags($contents->text);
                            }
                            $col = $choicerecord->name . '->' . $modality;
                            $columns[][$qpos] = $col;
                            $questionidcols[][$qpos] = $qid . '_' . $choicerecord->cid;
                            array_push($types, '0');
                            if (choice::content_is_other_choice($content)) {
                                $content = $stringother;
                                $col = $choicerecord->name . '->[' . $content . ']';
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = null;
                                array_push($types, '0');
                            }
                        }
                        break;

                    case QUESRATE:
                        foreach ($choices as $choicerecord) {
                            $nameddegrees = 0;
                            $modality = '';
                            $content = $choicerecord->content;
                            $osgood = false;
                            if (rate::type_is_osgood_rate_scale($choicerecord->precise)) {
                                $osgood = true;
                            }
                            if (preg_match("/^[0-9]{1,3}=/", $content, $ndd)) {
                                $nameddegrees++;
                            } else {
                                if ($osgood) {
                                    [$contentleft, $contentright] = array_merge(preg_split('/[|]/', $content), [' ']);
                                    $contents = question::parse_choice_content($contentleft);
                                    if ($contents->title) {
                                        $contentleft = $contents->title;
                                    }
                                    $contents = question::parse_choice_content($contentright);
                                    if ($contents->title) {
                                        $contentright = $contents->title;
                                    }
                                    $modality = strip_tags($contentleft . '|' . $contentright);
                                    $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                } else {
                                    $contents = question::parse_choice_content($content);
                                    if ($contents->modname) {
                                        $modality = $contents->modname;
                                    } else if ($contents->title) {
                                        $modality = $contents->title;
                                    } else {
                                        $modality = strip_tags($contents->text);
                                        $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                    }
                                }
                                $col = $choicerecord->name . '->' . $modality;
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = $qid . '_' . $choicerecord->cid;
                                array_push($types, $idtocsvmap[$type]);
                            }
                        }
                        break;
                }
            } else {
                $columns[][$qpos] = $col;
                $questionidcols[][$qpos] = $qid;
                array_push($types, $idtocsvmap[$type]);
            }
            $num++;
        }

        array_push($output, $columns);
        $numrespcols = count($output[0]);

        // Flatten questionidcols.
        $tmparr = [];
        for ($c = 0; $c < $nbinfocols; $c++) {
            $tmparr[] = null;
        }
        foreach ($questionidcols as $i => $positions) {
            foreach ($positions as $position => $qid) {
                $tmparr[] = $qid;
            }
        }
        $questionidcols = $tmparr;

        // Create array of question positions hashed by question / question + choiceid.
        $questionpositions = [];
        $questionsbyposition = [];
        $p = 0;
        foreach ($questionidcols as $qid) {
            if ($qid === null) {
                $p++;
                continue;
            }
            $questionpositions[$qid] = $p;
            if (strpos($qid, '_') !== false) {
                $tmparr = explode('_', $qid);
                $questionid = $tmparr[0];
            } else {
                $questionid = $qid;
            }
            $questionsbyposition[$p] = $questions[$questionid];
            $p++;
        }

        $formatoptions = new stdClass();
        $formatoptions->filter = false;

        if ($rankaverages) {
            $averages = [];
            $rids = [];
            $allresponsesrs2 = $this->questionnaire->responses()->get_survey_all_responses($rid, $userid, $currentgroupid);
            foreach ($allresponsesrs2 as $responserow) {
                if (!isset($rids[$responserow->rid])) {
                    $rids[$responserow->rid] = $responserow->rid;
                }
            }
        }

        $prevresprow = false;
        $row = [];
        if ($rankaverages) {
            $averagerow = [];
        }
        $useridentityfields = [];
        foreach ($allresponsesrs as $responserow) {
            $rid = $responserow->rid;
            $qid = $responserow->questionid;

            // Ignore responses for deleted questions.
            if (!isset($questions[$qid])) {
                continue;
            }

            if (!empty($identityfields)) {
                if (isset($useridentityfields[$responserow->userid])) {
                    $customfields = $useridentityfields[$responserow->userid];
                } else {
                    $customfields = self::get_user_identity_fields($this->questionnaire->context(), $responserow->userid);
                    $useridentityfields[$responserow->userid] = $customfields;
                }
                foreach ($identityfields as $field) {
                    $responserow->{$field} = $customfields->{$field};
                }
            }

            $question = $questions[$qid];
            $qtype = intval($question->typeid());
            if ($rankaverages) {
                if ($qtype === QUESRATE) {
                    if (empty($averages[$qid])) {
                        $results = $questions[$qid]->responsetype->get_results($rids);
                        foreach ($results as $qresult) {
                            $averages[$qid][$qresult->id] = $qresult->average;
                        }
                    }
                }
            }
            $questionobj = $questions[$qid];

            if ($prevresprow !== false && $prevresprow->rid !== $rid) {
                $output[] = $this->process_csv_row(
                    $row,
                    $prevresprow,
                    $currentgroupid,
                    $questionsbyposition,
                    $nbinfocols,
                    $numrespcols,
                    $options,
                    $identityfields
                );
                $row = [];
            }

            if ($qtype === QUESRATE || $qtype === QUESCHECK) {
                $key = $qid . '_' . $responserow->choiceid;
                $position = $questionpositions[$key];
                if ($qtype === QUESRATE) {
                    $choicetxt = $responserow->rankvalue;
                    if ($rankaverages) {
                        $averagerow[$position] = $averages[$qid][$responserow->choiceid];
                    }
                } else {
                    $content = $choicesbyqid[$qid][$responserow->choiceid]->content;
                    if (choice::content_is_other_choice($content)) {
                        $row[$position + 1] = $responserow->response;
                        $choicetxt = empty($responserow->choiceid) ? '0' : '1';
                    } else if (!empty($responserow->choiceid)) {
                        $choicetxt = '1';
                    } else {
                        $choicetxt = '0';
                    }
                }
                $responsetxt = $choicetxt;
                $row[$position] = $responsetxt;
            } else {
                $position = $questionpositions[$qid];
                if ($questionobj->has_choices()) {
                    $c = 0;
                    if (in_array(intval($question->typeid()), $choicetypes)) {
                        $choices = $choicesbyqid[$qid];
                        foreach ($choices as $choicerecord) {
                            $c++;
                            if ($responserow->choiceid === $choicerecord->cid) {
                                break;
                            }
                        }
                    }

                    $content = $choicesbyqid[$qid][$responserow->choiceid]->content;
                    if (choice::content_is_other_choice($content)) {
                        $responsetxt = choice::content_other_choice_display($content);
                        $responsetxt1 = $responserow->response;
                    } else if (($choicecodes == 1) && ($choicetext == 1)) {
                        $responsetxt = $c . ' : ' . $content;
                    } else if ($choicecodes == 1) {
                        $responsetxt = $c;
                    } else {
                        $responsetxt = $content;
                    }
                } else if (intval($qtype) === QUESYESNO) {
                    $responsetxt = $responserow->response === 'y' ? "1" : "0";
                } else {
                    $responsetxt = $responserow->response;
                    if (!empty($responsetxt)) {
                        $responsetxt = strip_tags($responsetxt);
                        $responsetxt = preg_replace("/[\r\n\t]/", ' ', $responsetxt);
                    }
                }
                $row[$position] = $responsetxt;
                if (!empty($responsetxt1)) {
                    $responsetxt1 = preg_replace("/[\r\n\t]/", ' ', $responsetxt1);
                    $row[$position + 1] = $responsetxt1;
                    unset($responsetxt1);
                }
            }

            $prevresprow = $responserow;
        }

        if ($prevresprow !== false) {
            $output[] = $this->process_csv_row(
                $row,
                $prevresprow,
                $currentgroupid,
                $questionsbyposition,
                $nbinfocols,
                $numrespcols,
                $options,
                $identityfields
            );
        }

        if ($rankaverages) {
            $summaryrow = [];
            $summaryrow[0] = get_string('averagesrow', 'questionnaire');
            for ($i = 1; $i < $nbinfocols; $i++) {
                $summaryrow[$i] = '';
            }
            for ($i = $nbinfocols; $i < $numrespcols; $i++) {
                $summaryrow[$i] = isset($averagerow[$i]) ? $averagerow[$i] : '';
            }
            $output[] = $summaryrow;
        }

        // Rewrite column headers to include question numbers.
        $numquestion = 0;
        $oldkey = 0;
        for ($i = $nbinfocols; $i < $numrespcols; $i++) {
            $sep = '';
            $thisoutput = current($output[0][$i]);
            $thiskey = key($output[0][$i]);
            if (strstr($thisoutput, '->.')) {
                $thisoutput = str_replace('->.', '', $thisoutput);
            }
            if (
                $thisoutput == '' ||
                strstr($thisoutput, '->.') ||
                substr($thisoutput, 0, 2) == '->' ||
                substr($thisoutput, 0, 1) == '_'
            ) {
                $sep = '';
            } else {
                $sep = '_';
            }
            if ($thiskey > $oldkey) {
                $oldkey = $thiskey;
                $numquestion++;
            }
            $pos = strpos($thisoutput, '=');
            if ($pos) {
                $thisoutput = substr($thisoutput, 0, $pos);
            }
            $out = 'Q' . sprintf("%02d", $numquestion) . $sep . $thisoutput;
            $output[0][$i] = $out;
        }
        return $output;
    }

    /**
     * Render all question responses for a single response to the page.
     *
     * Writes respondent info, feedback messages, feedback notes, and per-question
     * response output into the page object.
     *
     * @param int $rid Response id to display.
     * @param string $referer 'print' suppresses feedback; other values allow it.
     * @param array|string $resps Responses used for feedback comparison.
     * @param bool $compare True when comparing individual response against group.
     * @param bool $isgroupmember True when the viewing user is a group member.
     * @param bool $allresponses True when all responses are included.
     * @param int $currentgroupid Active group filter id (0 = all).
     * @param string $outputtarget 'html' or 'pdf'.
     * @return void
     */
    public function view_response(
        int $rid,
        string $referer = '',
        $resps = '',
        bool $compare = false,
        bool $isgroupmember = false,
        bool $allresponses = false,
        int $currentgroupid = 0,
        string $outputtarget = 'html'
    ): void {
        $this->print_survey_start('', 1, 1, 0, $rid, false, $outputtarget);

        $i = 0;
        $responses = $this->responsecollection();
        $responses->add_response($rid);
        if ($referer != 'print') {
            $feedbackmessages = $this->response_analysis($rid, $resps, $compare, $isgroupmember, $allresponses, $currentgroupid);

            if ($feedbackmessages) {
                $msgout = '';
                foreach ($feedbackmessages as $msg) {
                    $msgout .= $msg;
                }
                $this->questionnaire->page->add_to_page('feedbackmessages', $msgout);
            }

            $survey = $this->questionnaire->survey();
            if ($survey->feedbacknotes()) {
                $text = file_rewrite_pluginfile_urls(
                    $survey->feedbacknotes(),
                    'pluginfile.php',
                    $this->questionnaire->context()->id,
                    'mod_questionnaire',
                    'feedbacknotes',
                    $this->questionnaire->surveyid()
                );
                $this->questionnaire->page->add_to_page(
                    'feedbacknotes',
                    $this->questionnaire->renderer->box(format_text($text, FORMAT_HTML))
                );
            }
        }
        $pdf = ($outputtarget == 'pdf') ? true : false;
        $questions = $this->questionnaire->questions();
        foreach ($questions as $question) {
            if (!$question->dependency_fulfilled($rid, $questions)) {
                continue;
            }
            if ($question->typeid() < QUESPAGEBREAK) {
                $i++;
            }
            if ($question->typeid() != QUESPAGEBREAK) {
                $this->questionnaire->page->add_to_page(
                    'responses',
                    $this->questionnaire->renderer->response_output(
                        $question,
                        $responses->get_response($rid),
                        $i,
                        $pdf,
                        $this->questionnaire
                    )
                );
            }
        }
    }

    /**
     * Analyse responses and return feedback messages for this questionnaire.
     *
     * Returns an empty string when the survey has no feedback sections configured,
     * or an array of HTML fragment strings when feedback data exists.
     *
     * @param int $rid Response id of the individual response being viewed (0 for all).
     * @param array $resps All response objects keyed by response id.
     * @param bool $compare True when comparing individual response against group.
     * @param bool $isgroupmember True when the viewing user is a group member.
     * @param bool $allresponses True when all responses are included (not just one).
     * @param int $currentgroupid Active group filter id (0 = all).
     * @param array|null $filteredsections Restrict output to these section numbers (null = all).
     * @return array|string Feedback message strings, or '' when no feedback is configured.
     */
    public function response_analysis(
        int $rid,
        array $resps,
        bool $compare,
        bool $isgroupmember,
        bool $allresponses,
        int $currentgroupid,
        ?array $filteredsections = null
    ) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/tablelib.php');
        require_once($CFG->dirroot . '/mod/questionnaire/drawchart.php');

        $sql = "SELECT * FROM {questionnaire_fb_sections} WHERE surveyid = ? AND section IS NOT NULL";
        if (!$fbsections = $DB->get_records_sql($sql, [$this->questionnaire->surveyid()])) {
            return '';
        }

        $action = optional_param('action', 'vall', PARAM_ALPHA);

        $resp = $DB->get_record('questionnaire_response', ['id' => $rid]);
        if (!empty($resp)) {
            $userid = $resp->userid;
            $user = $DB->get_record('user', ['id' => $userid]);
            if (!empty($user)) {
                if ($this->questionnaire->respondenttype() == 'anonymous') {
                    $ruser = '- ' . get_string('anonymous', 'questionnaire') . ' -';
                } else {
                    $ruser = fullname($user);
                }
            }
        }

        $groupmode = groups_get_activity_groupmode($this->questionnaire->coursemodule(), $this->questionnaire->course());
        $groupname = get_string('allparticipants');
        if ($groupmode > 0) {
            if ($currentgroupid > 0) {
                $groupname = groups_get_group_name($currentgroupid);
            } else {
                $groupname = get_string('allparticipants');
            }
        }

        $survey = $this->questionnaire->survey();
        if ($survey->feedbackscores()) {
            $table = new html_table();
            $table->size = [null, null];
            $table->align = ['left', 'right', 'right'];
            $table->head = [];
            $table->wrap = [];
            if ($compare) {
                $table->head = [get_string('feedbacksection', 'questionnaire'), $ruser, $groupname];
            } else {
                $table->head = [get_string('feedbacksection', 'questionnaire'), $groupname];
            }
        }

        $fbsectionsnb = array_keys($fbsections);
        $numsections = count($fbsections);

        $rids = [];
        foreach ($resps as $key => $resp) {
            $rids[] = $key;
        }
        $nbparticipants = count($rids);
        $responsescores = [];

        $qmax = [];
        $maxtotalscore = 0;
        $questions = $this->questionnaire->questions();
        foreach ($questions as $question) {
            $qid = $question->id();
            if ($question->valid_feedback()) {
                $qmax[$qid] = $question->get_feedback_maxscore();
                $maxtotalscore += $qmax[$qid];
                $responsescores[$qid] = $question->get_feedback_scores($rids);
            }
        }
        if ($maxtotalscore === 0) {
            return '';
        }
        $feedbackmessages = [];

        $qscore = [];
        $allqscore = [];

        if (!$allresponses && $groupmode != 0) {
            $nbparticipants = max(1, $nbparticipants - !$isgroupmember);
        }
        foreach ($responsescores as $qid => $responsescore) {
            if (!empty($responsescore)) {
                foreach ($responsescore as $rrid => $response) {
                    if ($rrid == $rid || $allresponses) {
                        if (!isset($qscore[$qid])) {
                            $qscore[$qid] = 0;
                        }
                        $qscore[$qid] = $response->score;
                    }
                    if (!isset($allqscore[$qid])) {
                        $allqscore[$qid] = 0;
                    }
                    if ($groupmode == 0 || $isgroupmember || (!$isgroupmember && $rrid != $rid) || $allresponses) {
                        $allqscore[$qid] += $response->score;
                    }
                }
            }
        }
        $totalscore = array_sum($qscore);
        $scorepercent = round($totalscore / $maxtotalscore * 100);
        $oppositescorepercent = 100 - $scorepercent;
        $alltotalscore = array_sum($allqscore);
        $allscorepercent = round($alltotalscore / $nbparticipants / $maxtotalscore * 100);

        if ($survey->feedbacksections() == 1) {
            $sectionid = $fbsectionsnb[0];
            $sectionlabel = $fbsections[$sectionid]->sectionlabel;
            $sectionheading = $fbsections[$sectionid]->sectionheading;
            $labels = [];
            if ($feedbacks = $DB->get_records('questionnaire_feedback', ['sectionid' => $sectionid])) {
                foreach ($feedbacks as $feedback) {
                    if ($feedback->feedbacklabel != '') {
                        $labels[] = $feedback->feedbacklabel;
                    }
                }
            }
            $feedback = $DB->get_record_select(
                'questionnaire_feedback',
                'sectionid = ? AND minscore <= ? AND ? < maxscore',
                [$sectionid, $scorepercent, $scorepercent]
            );

            $sectionheading = str_replace('%', '', $sectionheading);
            $original = ['$scorepercent', '$oppositescorepercent'];
            $result = ['%s%%', '%s%%'];
            $sectionheading = str_replace($original, $result, $sectionheading);
            $sectionheading = sprintf($sectionheading, $scorepercent, $oppositescorepercent);
            $sectionheading = file_rewrite_pluginfile_urls(
                $sectionheading,
                'pluginfile.php',
                $this->questionnaire->context()->id,
                'mod_questionnaire',
                'sectionheading',
                $sectionid
            );
            $feedbackmessages[] = $this->questionnaire->renderer->box_start();
            $feedbackmessages[] = format_text($sectionheading, FORMAT_HTML, ['noclean' => true]);
            $feedbackmessages[] = $this->questionnaire->renderer->box_end();

            if (!empty($feedback->feedbacktext)) {
                $formatoptions = new stdClass();
                $formatoptions->noclean = true;
                $feedbacktext = file_rewrite_pluginfile_urls(
                    $feedback->feedbacktext,
                    'pluginfile.php',
                    $this->questionnaire->context()->id,
                    'mod_questionnaire',
                    'feedback',
                    $feedback->id
                );
                $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);
                $feedbackmessages[] = $this->questionnaire->renderer->box_start();
                $feedbackmessages[] = $feedbacktext;
                $feedbackmessages[] = $this->questionnaire->renderer->box_end();
            }
            $score = [$scorepercent, 100 - $scorepercent];
            $allscore = null;
            if ($compare || $allresponses) {
                $allscore = [$allscorepercent, 100 - $allscorepercent];
            }
            $usergraph = get_config('questionnaire', 'usergraph');
            if ($usergraph && $survey->charttype()) {
                $this->questionnaire->page->add_to_page(
                    'feedbackcharts',
                    draw_chart(
                        $feedbacktype = 'global',
                        $labels,
                        $groupname,
                        $allresponses,
                        $survey->charttype(),
                        $score,
                        $allscore,
                        $sectionlabel
                    )
                );
            }
            $lb = explode("|", $sectionlabel);
            $oppositescore = '';
            $oppositeallscore = '';
            if (count($lb) > 1) {
                $sectionlabel = $lb[0] . ' | ' . $lb[1];
                $oppositescore = ' | ' . $score[1] . '%';
                $oppositeallscore = ' | ' . $allscore[1] . '%';
            }
            if ($survey->feedbackscores()) {
                $table = $table ?? new html_table();
                if ($compare) {
                    $table->data[] = [$sectionlabel, $score[0] . '%' . $oppositescore, $allscore[0] . '%' . $oppositeallscore];
                } else {
                    $table->data[] = [$sectionlabel, $allscore[0] . '%' . $oppositeallscore];
                }
                $this->questionnaire->page->add_to_page('feedbackscores', html_writer::table($table));
            }

            return $feedbackmessages;
        }

        // Now process scores for more than one section.

        $score = [];
        $allscore = [];
        $maxscore = [];
        $scorepercent = [];
        $allscorepercent = [];
        $oppositescorepercent = [];
        $alloppositescorepercent = [];
        $chartlabels = [];
        $nanscores = [];

        for ($i = 1; $i <= $numsections; $i++) {
            $score[$i] = 0;
            $allscore[$i] = 0;
            $maxscore[$i] = 0;
            $scorepercent[$i] = 0;
        }

        for ($section = 1; $section <= $numsections; $section++) {
            if (($filteredsections != null) && !in_array($section, $filteredsections)) {
                continue;
            }
            foreach ($fbsections as $key => $fbsection) {
                if ($fbsection->section == $section) {
                    $feedbacksectionid = $key;
                    $scorecalculation = section::decode_scorecalculation($fbsection->scorecalculation);
                    if (empty($scorecalculation) && !is_array($scorecalculation)) {
                        $scorecalculation = [];
                    }
                    $sectionheading = $fbsection->sectionheading;
                    $imageid = $fbsection->id;
                    $chartlabels[$section] = $fbsection->sectionlabel;
                }
            }
            foreach ($scorecalculation as $qid => $key) {
                if (isset($qscore[$qid])) {
                    $key = empty($key) ? 1 : $key;
                    $score[$section] += round($qscore[$qid] * $key);
                    $maxscore[$section] += round($qmax[$qid] * $key);
                    if ($compare || $allresponses) {
                        $allscore[$section] += round($allqscore[$qid] * $key);
                    }
                }
            }

            if ($maxscore[$section] == 0) {
                array_push($nanscores, $section);
            }

            $scorepercent[$section] = ($maxscore[$section] > 0) ? (round($score[$section] / $maxscore[$section] * 100)) : 0;
            $oppositescorepercent[$section] = 100 - $scorepercent[$section];

            if (($compare || $allresponses) && $nbparticipants != 0) {
                $allscorepercent[$section] = ($maxscore[$section] > 0)
                    ? (round(($allscore[$section] / $nbparticipants) / $maxscore[$section] * 100))
                    : 0;
                $alloppositescorepercent[$section] = 100 - $allscorepercent[$section];
            }

            if (!$allresponses) {
                if (is_nan($scorepercent[$section])) {
                    continue;
                }
                $sectionheading = str_replace('%', '', $sectionheading);
                $original = ['$scorepercent', '$oppositescorepercent'];
                $result = ["$scorepercent[$section]%", "$oppositescorepercent[$section]%"];
                $sectionheading = str_replace($original, $result, $sectionheading);
                $formatoptions = new stdClass();
                $formatoptions->noclean = true;
                $sectionheading = file_rewrite_pluginfile_urls(
                    $sectionheading,
                    'pluginfile.php',
                    $this->questionnaire->context()->id,
                    'mod_questionnaire',
                    'sectionheading',
                    $imageid
                );
                $sectionheading = format_text($sectionheading, 1, $formatoptions);
                $feedbackmessages[] = $this->questionnaire->renderer->box_start('reportQuestionTitle');
                $feedbackmessages[] = format_text($sectionheading, FORMAT_HTML, $formatoptions);
                $feedback = $DB->get_record_select(
                    'questionnaire_feedback',
                    'sectionid = ? AND minscore <= ? AND ? < maxscore',
                    [$feedbacksectionid, $scorepercent[$section], $scorepercent[$section]],
                    'id,feedbacktext,feedbacktextformat'
                );
                $feedbackmessages[] = $this->questionnaire->renderer->box_end();
                if (!empty($feedback->feedbacktext)) {
                    $formatoptions = new stdClass();
                    $formatoptions->noclean = true;
                    $feedbacktext = file_rewrite_pluginfile_urls(
                        $feedback->feedbacktext,
                        'pluginfile.php',
                        $this->questionnaire->context()->id,
                        'mod_questionnaire',
                        'feedback',
                        $feedback->id
                    );
                    $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);
                    $feedbackmessages[] = $this->questionnaire->renderer->box_start('feedbacktext');
                    $feedbackmessages[] = $feedbacktext;
                    $feedbackmessages[] = $this->questionnaire->renderer->box_end();
                }
            }
        }

        switch ($action) {
            case 'vallasort':
                asort($allscore);
                break;
            case 'vallarsort':
                arsort($allscore);
                break;
            default:
        }

        if ($survey->feedbackscores()) {
            foreach ($allscore as $key => $sc) {
                if (isset($chartlabels[$key])) {
                    $lb = explode("|", $chartlabels[$key]);
                    $oppositescore = '';
                    $oppositeallscore = '';
                    if (count($lb) > 1) {
                        $sectionlabel = $lb[0] . ' | ' . $lb[1];
                        $oppositescore = ' | ' . $oppositescorepercent[$key] . '%';
                        $oppositeallscore = ' | ' . $alloppositescorepercent[$key] . '%';
                    } else {
                        $sectionlabel = $chartlabels[$key];
                    }
                    if ($compare && !is_nan($scorepercent[$key])) {
                        $table = $table ?? new html_table();
                        $table->data[] = [
                            $sectionlabel,
                            $scorepercent[$key] . '%' . $oppositescore,
                            $allscorepercent[$key] . '%' . $oppositeallscore,
                        ];
                    } else if (isset($allscorepercent[$key]) && !is_nan($allscorepercent[$key])) {
                        $table = $table ?? new html_table();
                        $table->data[] = [$sectionlabel, $allscorepercent[$key] . '%' . $oppositeallscore];
                    }
                }
            }
        }
        $usergraph = get_config('questionnaire', 'usergraph');

        foreach ($nanscores as $val) {
            unset($chartlabels[$val]);
            unset($scorepercent[$val]);
            unset($allscorepercent[$val]);
        }

        if ($usergraph && $survey->charttype()) {
            $this->questionnaire->page->add_to_page(
                'feedbackcharts',
                draw_chart(
                    'sections',
                    array_values($chartlabels),
                    $groupname,
                    $allresponses,
                    $survey->charttype(),
                    array_values($scorepercent),
                    array_values($allscorepercent),
                    $sectionlabel
                )
            );
        }
        if ($survey->feedbackscores()) {
            $this->questionnaire->page->add_to_page('feedbackscores', html_writer::table($table));
        }

        return $feedbackmessages;
    }

    // Private helpers.

    /**
     * Return the shared response collection, creating it on first access.
     *
     * @return questionnaire_responses
     */
    private function responsecollection(): questionnaire_responses {
        $this->responsecollection ??= $this->questionnaire->responses();
        return $this->responsecollection;
    }

    /**
     * Render the page/section count footer into the page (no-op when autonumbering is off).
     *
     * @param int $section Current section number.
     * @param int $numsections Total number of sections.
     * @return void
     */
    private function print_survey_end(int $section, int $numsections): void {
        if (!$this->questionnaire->pages_autonumbered()) {
            return;
        }
        if ($numsections > 1) {
            $a = new stdClass();
            $a->page = $section;
            $a->totpages = $numsections;
            $this->questionnaire->page->add_to_page(
                'pageinfo',
                $this->questionnaire->renderer->container(
                    get_string('pageof', 'questionnaire', $a) . '&nbsp;&nbsp;',
                    'surveyPage'
                )
            );
        }
    }

    /**
     * Render the survey header (respondent info, title, subtitle, description) into the page.
     *
     * Triggers a response_viewed event when the respondenttype is 'fullname'.
     *
     * @param string $message Error message to display (empty string = none).
     * @param int $section Current page/section number.
     * @param int $numsections Total number of pages/sections.
     * @param int $hasrequired Whether the survey has required questions (unused; kept for signature compat).
     * @param int|string $rid Response id ('' for blank/preview display).
     * @param bool $blankquestionnaire True when displaying a blank preview.
     * @param string $outputtarget 'html' or 'pdf'.
     * @return void
     */
    private function print_survey_start(
        string $message,
        int $section,
        int $numsections,
        int $hasrequired,
        $rid = '',
        bool $blankquestionnaire = false,
        string $outputtarget = 'html'
    ): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        $userid = '';
        $resp = '';
        $groupname = '';
        $currentgroupid = 0;
        $timesubmitted = '';
        if ($rid) {
            $courseid = $this->questionnaire->course()->id;
            if ($resp = $DB->get_record('questionnaire_response', ['id' => $rid])) {
                if ($this->questionnaire->respondenttype() == 'fullname') {
                    $userid = $resp->userid;
                    if (groups_get_activity_groupmode($this->questionnaire->coursemodule(), $this->questionnaire->course())) {
                        if ($groups = groups_get_all_groups($courseid, $resp->userid)) {
                            if (count($groups) == 1) {
                                $group = current($groups);
                                $currentgroupid = $group->id;
                                $groupname = ' (' . get_string('group') . ': ' . $group->name . ')';
                            } else {
                                $groupname = ' (' . get_string('groups') . ': ';
                                foreach ($groups as $group) {
                                    $groupname .= $group->name . ', ';
                                }
                                $groupname = substr($groupname, 0, strlen($groupname) - 2) . ')';
                            }
                        } else {
                            $groupname = ' (' . get_string('groupnonmembers') . ')';
                        }
                    }

                    $params = [
                        'objectid' => $this->questionnaire->surveyid(),
                        'context' => $this->questionnaire->context(),
                        'courseid' => $this->questionnaire->course()->id,
                        'relateduserid' => $userid,
                        'other' => ['action' => 'vresp', 'currentgroupid' => $currentgroupid, 'rid' => $rid],
                    ];
                    $event = \mod_questionnaire\event\response_viewed::create($params);
                    $event->trigger();
                }
            }
        }
        $ruser = '';
        if ($resp && !$blankquestionnaire) {
            if ($userid) {
                if ($user = $DB->get_record('user', ['id' => $userid])) {
                    $ruser = fullname($user);
                }
            }
            if ($this->questionnaire->respondenttype() == 'anonymous') {
                $ruser = '- ' . get_string('anonymous', 'questionnaire') . ' -';
            } else {
                if ($resp->submitted) {
                    $timesubmitted = '&nbsp;' . get_string('submitted', 'questionnaire') . '&nbsp;' . userdate($resp->submitted);
                }
            }
        }
        if ($ruser) {
            $respinfo = '';
            if ($outputtarget == 'html') {
                $linkname = get_string('print', 'mod_questionnaire');
                $link = new \moodle_url(
                    '/mod/questionnaire/report.php',
                    [
                        'action'             => 'vresp',
                        'instance'           => $this->questionnaire->id(),
                        'target'             => 'print',
                        'individualresponse' => 1,
                        'rid'                => $rid,
                    ]
                );
                $htmlicon = new \pix_icon('t/print', $linkname);
                $options = [
                    'menubar'    => true,
                    'location'   => false,
                    'scrollbars' => true,
                    'resizable'  => true,
                    'height'     => 600,
                    'width'      => 800,
                    'title'      => $linkname,
                ];
                $name = 'popup';
                $action = new \popup_action('click', $link, $name, $options);
                $respinfo .= $this->questionnaire->renderer->action_link(
                    $link,
                    null,
                    $action,
                    ['title' => $linkname],
                    $htmlicon
                ) . '&nbsp;';
            }
            $respinfo .= get_string('respondent', 'questionnaire') . ': <strong>' . $ruser . '</strong>';
            if ($this->questionnaire->survey_is_public()) {
                $coursename = '';
                $sql = 'SELECT q.id, q.course, c.fullname ' .
                       'FROM {questionnaire_response} qr ' .
                       'INNER JOIN {questionnaire} q ON qr.questionnaireid = q.id ' .
                       'INNER JOIN {course} c ON q.course = c.id ' .
                       'WHERE qr.id = ? AND qr.complete = ? ';
                if ($record = $DB->get_record_sql($sql, [$rid, 'y'])) {
                    $coursename = $record->fullname;
                }
                $respinfo .= ' ' . get_string('course') . ': ' . $coursename;
            }
            $respinfo .= $groupname;
            $respinfo .= $timesubmitted;
            $this->questionnaire->page->add_to_page(
                'respondentinfo',
                $this->questionnaire->renderer->respondent_info($respinfo)
            );
        }

        if ($this->questionnaire->capabilities()->can_print_blank() && $blankquestionnaire && $section == 1) {
            $linkname = '&nbsp;' . get_string('printblank', 'questionnaire');
            $title = get_string('printblanktooltip', 'questionnaire');
            $url = '/mod/questionnaire/print.php?qid=' . $this->questionnaire->id() .
                   '&amp;rid=0&amp;courseid=' . $this->questionnaire->course()->id . '&amp;sec=1';
            $options = [
                'menubar'    => true,
                'location'   => false,
                'scrollbars' => true,
                'resizable'  => true,
                'height'     => 600,
                'width'      => 800,
                'title'      => $title,
            ];
            $name = 'popup';
            $link = new \moodle_url($url);
            $action = new \popup_action('click', $link, $name, $options);
            $class = "floatprinticon";
            $this->questionnaire->page->add_to_page(
                'printblank',
                $this->questionnaire->renderer->action_link(
                    $link,
                    $linkname,
                    $action,
                    ['class' => $class, 'title' => $title],
                    new \pix_icon('t/print', $title)
                )
            );
        }
        if ($section == 1) {
            $survey = $this->questionnaire->survey();
            if ($survey->title() !== '') {
                $this->questionnaire->page->add_to_page('title', format_string($survey->title()));
            }
            if ($survey->subtitle() !== '') {
                $this->questionnaire->page->add_to_page('subtitle', format_string($survey->subtitle()));
            }
            if ($survey->info() !== '') {
                $infotext = file_rewrite_pluginfile_urls(
                    $survey->info(),
                    'pluginfile.php',
                    $this->questionnaire->context()->id,
                    'mod_questionnaire',
                    'info',
                    $this->questionnaire->surveyid()
                );
                $this->questionnaire->page->add_to_page('addinfo', format_text($infotext, FORMAT_HTML, ['noclean' => true]));
            }
        }

        if ($message) {
            $this->questionnaire->page->add_to_page(
                'message',
                $this->questionnaire->renderer->notification($message, \core\output\notification::NOTIFY_ERROR)
            );
        }
    }

    /**
     * Process one response row into a positioned CSV row array.
     *
     * @param array $row Sparse response data array keyed by column position.
     * @param stdClass $resprow Raw response resultset row.
     * @param int $currentgroupid Active group id (0 = all).
     * @param array $questionsbyposition Questions keyed by column position.
     * @param int $nbinfocols Number of leading info columns.
     * @param int $numrespcols Total number of columns.
     * @param array $options Enabled download option strings.
     * @param array $identityfields Identity field names.
     * @return array Flat positioned row for CSV output.
     */
    private function process_csv_row(
        array &$row,
        stdClass $resprow,
        int $currentgroupid,
        array &$questionsbyposition,
        int $nbinfocols,
        int $numrespcols,
        array $options,
        array $identityfields
    ): array {
        global $DB;

        static $anonumap = [];

        $positioned = [];
        $user = new stdClass();
        foreach ($this->user_fields() as $userfield) {
            $user->$userfield = $resprow->$userfield;
        }
        $user->id = $resprow->userid;
        $isanonymous = ($this->questionnaire->respondenttype() == 'anonymous');

        $course = $this->questionnaire->course();
        if (!$this->questionnaire->survey_is_public()) {
            $courseid = $course->id;
            $coursename = $course->fullname;
        } else {
            $sql = 'SELECT q.id, q.course, c.fullname ' .
                   'FROM {questionnaire_response} qr ' .
                   'INNER JOIN {questionnaire} q ON qr.questionnaireid = q.id ' .
                   'INNER JOIN {course} c ON q.course = c.id ' .
                   'WHERE qr.id = ? AND qr.complete = ? ';
            if ($record = $DB->get_record_sql($sql, [$resprow->rid, 'y'])) {
                $courseid = $record->course;
                $coursename = $record->fullname;
            } else {
                $courseid = $course->id;
                $coursename = $course->fullname;
            }
        }

        $cm = $this->questionnaire->coursemodule();
        $groupname = '';
        if (groups_get_activity_groupmode($cm, $course)) {
            if ($currentgroupid > 0) {
                $groupname = groups_get_group_name($currentgroupid);
            } else {
                if ($user->id) {
                    if ($groups = groups_get_all_groups($courseid, $user->id)) {
                        foreach ($groups as $group) {
                            $groupname .= $group->name . ', ';
                        }
                        $groupname = substr($groupname, 0, strlen($groupname) - 2);
                    } else {
                        $groupname = ' (' . get_string('groupnonmembers') . ')';
                    }
                }
            }
        }

        if ($isanonymous) {
            if (!isset($anonumap[$user->id])) {
                $anonumap[$user->id] = count($anonumap) + 1;
            }
            $fullname = get_string('anonymous', 'questionnaire') . $anonumap[$user->id];
            $username = '';
            $uid = '';
        } else {
            $uid = $user->id;
            $fullname = fullname($user);
            $username = $user->username;
        }

        if (in_array('response', $options)) {
            array_push($positioned, $resprow->rid);
        }
        if (in_array('submitted', $options)) {
            $submitted = date(get_string('strfdateformatcsv', 'questionnaire'), $resprow->submitted);
            array_push($positioned, $submitted);
        }
        if (in_array('institution', $options)) {
            array_push($positioned, $user->institution);
        }
        if (in_array('department', $options)) {
            array_push($positioned, $user->department);
        }
        if (in_array('course', $options)) {
            array_push($positioned, $coursename);
        }
        if (in_array('group', $options)) {
            array_push($positioned, $groupname);
        }
        if (in_array('id', $options)) {
            array_push($positioned, $uid);
        }
        if (in_array('useridnumber', $options)) {
            array_push($positioned, $user->idnumber);
        }
        if (in_array('fullname', $options)) {
            array_push($positioned, $fullname);
        }
        if (in_array('username', $options)) {
            array_push($positioned, $username);
        }
        if (in_array('complete', $options)) {
            array_push($positioned, $resprow->complete);
        }
        foreach ($identityfields as $field) {
            array_push($positioned, $resprow->$field);
        }

        for ($c = $nbinfocols; $c < $numrespcols; $c++) {
            if (isset($row[$c])) {
                $positioned[] = $row[$c];
            } else if (isset($questionsbyposition[$c])) {
                $question = $questionsbyposition[$c];
                $qtype = intval($question->typeid());
                if ($qtype === QUESCHECK) {
                    $positioned[] = '0';
                } else {
                    $positioned[] = null;
                }
            } else {
                $positioned[] = null;
            }
        }
        return $positioned;
    }

    /**
     * Return the unique question type ids used across this survey's questions.
     *
     * @param bool $uniquebytable When true, also de-duplicates by response table.
     * @return array
     */
    private function get_survey_questiontypes(bool $uniquebytable = false): array {
        $uniquetypes = [];
        $uniquetables = [];
        foreach ($this->questionnaire->questions() as $question) {
            $type = $question->typeid();
            $responsetable = $question->responsetable();
            if (!$uniquebytable || !in_array($responsetable, $uniquetables)) {
                if (!in_array($type, $uniquetypes)) {
                    $uniquetypes[] = $type;
                }
                if (!in_array($responsetable, $uniquetables)) {
                    $uniquetables[] = $responsetable;
                }
            }
        }
        return $uniquetypes;
    }

    /**
     * Return the identity fields to include for each user in the CSV.
     *
     * Returns an empty array when the 'useridentityfields' option is not set or
     * when the questionnaire is anonymous.
     *
     * @param array $options Enabled download option strings.
     * @return array Field names.
     */
    private function get_identity_fields(array $options): array {
        if (!in_array('useridentityfields', $options) || $this->questionnaire->respondenttype() == 'anonymous') {
            return [];
        }
        return \core_user\fields::get_identity_fields($this->questionnaire->context());
    }

    /**
     * Return a stdClass of identity field values for the given user.
     *
     * @param \context $context
     * @param int $userid
     * @return stdClass|false
     */
    private static function get_user_identity_fields(\context $context, int $userid) {
        global $DB;

        $fields = \core_user\fields::for_identity($context);
        [
            'selects' => $selects,
            'joins' => $joins,
            'params' => $params,
        ] = (array)$fields->get_sql('u', false, '', '', false);
        $sql = "SELECT $selects FROM {user} u $joins WHERE u.id = ?";
        return $DB->get_record_sql($sql, array_merge($params, [$userid]));
    }

    /**
     * Return the user field names used when building CSV rows.
     *
     * @return array
     */
    private function user_fields(): array {
        $userfieldsarr = \core_user\fields::get_name_fields();
        $userfieldsarr = array_merge($userfieldsarr, ['username', 'department', 'institution', 'idnumber']);
        return $userfieldsarr;
    }
}
