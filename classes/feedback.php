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
 * Feedback domain object.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use html_table;
use html_writer;
use mod_questionnaire\feedback\scoreboard;
use mod_questionnaire\local\feedback\section;
use stdClass;

/**
 * Feedback domain object.
 *
 * Wraps the feedback-related state that lives on the `questionnaire_survey`
 * table (feedbacknotes, feedbacksections, feedbackscores, charttype) plus
 * the `questionnaire_fb_sections` collection. The `update_settings` write
 * path owns its own allowlist; the `response_analysis` extraction is
 * deferred to a later phase.
 *
 * @see project-feedback-domain-class-plan
 */
class feedback {
    /** @var string[] Survey persistent fields owned by the feedback concern. */
    public const UPDATABLE_FIELDS = [
        'feedbacknotes', 'feedbacksections', 'feedbackscores', 'charttype',
    ];

    /**
     * Constructor.
     *
     * @param questionnaire $questionnaire The owning questionnaire.
     */
    public function __construct(
        /** @var questionnaire The owning questionnaire. */
        private readonly questionnaire $questionnaire,
    ) {
    }

    /**
     * True if feedback is enabled (any sections configured).
     *
     * @return bool
     */
    public function enabled(): bool {
        return $this->mode() > 0;
    }

    /**
     * Number of feedback sections — 0 off, 1 single global, N sectioned.
     *
     * Stored under feedbacksections on questionnaire_survey.
     *
     * @return int
     */
    public function mode(): int {
        return $this->questionnaire->survey()->feedbacksections();
    }

    /**
     * True if the per-section feedback score table should be shown.
     *
     * Stored under feedbackscores on questionnaire_survey.
     *
     * @return bool
     */
    public function show_scores(): bool {
        return $this->questionnaire->survey()->feedbackscores();
    }

    /**
     * Raw HTML for the feedback notes editor.
     *
     * @return string
     */
    public function notes(): string {
        return $this->questionnaire->survey()->feedbacknotes();
    }

    /**
     * Feedback notes with pluginfile URLs rewritten for display.
     *
     * @return string Empty string when notes() is empty; the rewritten HTML otherwise.
     */
    public function rendered_notes(): string {
        $notes = $this->notes();
        if ($notes === '') {
            return '';
        }
        return file_rewrite_pluginfile_urls(
            $notes,
            'pluginfile.php',
            $this->questionnaire->context()->id,
            'mod_questionnaire',
            'feedbacknotes',
            $this->questionnaire->surveyid()
        );
    }

    /**
     * Chart type for per-section feedback rendering — '' when off.
     *
     * Stored under charttype on questionnaire_survey. Empty string when
     * the user disabled the chart or when the site usergraph setting is
     * off.
     *
     * @return string
     */
    public function chart_type(): string {
        return $this->questionnaire->survey()->charttype();
    }

    /**
     * True if any question on the survey is a valid feedback question
     * (rate, yesno, slider — anything where valid_feedback() returns true).
     *
     * @return bool
     */
    public function has_any_feedback_questions(): bool {
        foreach ($this->questionnaire->questions() as $question) {
            if ($question->valid_feedback()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update feedback-domain fields on the survey row.
     *
     * Only keys in self::UPDATABLE_FIELDS are accepted; passing anything else
     * throws a coding_exception so form metadata cannot be smuggled onto the
     * persistent. The actual write goes through {@see survey::update_settings()}
     * with the feedback allowlist as the package-private override.
     *
     * @param array $fields Map of feedback field name => value.
     * @return int|false Survey id on success, false on validation failure.
     */
    public function update_settings(array $fields): int|false {
        foreach (array_keys($fields) as $key) {
            if (!in_array($key, self::UPDATABLE_FIELDS, true)) {
                throw new \coding_exception("feedback::update_settings: field '{$key}' is not updatable");
            }
        }
        return $this->questionnaire->survey()->update_settings($fields, self::UPDATABLE_FIELDS);
    }

    /**
     * Compute the per-section / per-question feedback scoreboard for a response set.
     *
     * Returns null when feedback is not configured (no sections, or no
     * questions contribute to the max score). Otherwise returns a value
     * object carrying the rendered message HTML chunks, the optional score
     * table HTML, and the optional chart HTML. Callers are responsible for
     * applying the table/chart strings to the page; messages are returned
     * raw so callers can compose them however they need.
     *
     * Logic is a verbatim move of the former reporter::response_analysis;
     * the math has not been rewritten. The boxed-message HTML is built from
     * $renderer->box_start() / box_end() so the wrapper styling stays
     * identical to the legacy output.
     *
     * @param int $rid Response id of the individual response being viewed (0 for all).
     * @param array $resps All response objects keyed by response id.
     * @param bool $compare True when comparing individual response against group.
     * @param bool $isgroupmember True when the viewing user is a group member.
     * @param bool $allresponses True when all responses are included (not just one).
     * @param int $currentgroupid Active group filter id (0 = all).
     * @param string $sortaction Report action triggering sort ('vallasort' / 'vallarsort' / other).
     * @param \plugin_renderer_base $renderer Plugin renderer for box wrappers.
     * @param array|null $filteredsections Restrict output to these section numbers (null = all).
     * @return scoreboard|null Scoreboard value object, or null when no feedback is configured.
     */
    public function build_scoreboard(
        int $rid,
        array $resps,
        bool $compare,
        bool $isgroupmember,
        bool $allresponses,
        int $currentgroupid,
        string $sortaction,
        \plugin_renderer_base $renderer,
        ?array $filteredsections = null,
    ): ?scoreboard {
        global $DB, $CFG;
        require_once($CFG->libdir . '/tablelib.php');
        require_once($CFG->dirroot . '/mod/questionnaire/drawchart.php');

        $sql = "SELECT * FROM {questionnaire_fb_sections} WHERE surveyid = ? AND section IS NOT NULL";
        if (!$fbsections = $DB->get_records_sql($sql, [$this->questionnaire->surveyid()])) {
            return null;
        }

        $resp = $DB->get_record('questionnaire_response', ['id' => $rid]);
        $ruser = '';
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

        $table = null;
        if ($this->show_scores()) {
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
            return null;
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

        if ($this->mode() == 1) {
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
            $feedbackmessages[] = $renderer->box_start();
            $feedbackmessages[] = format_text($sectionheading, FORMAT_HTML, ['noclean' => true]);
            $feedbackmessages[] = $renderer->box_end();

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
                $feedbackmessages[] = $renderer->box_start();
                $feedbackmessages[] = $feedbacktext;
                $feedbackmessages[] = $renderer->box_end();
            }
            $score = [$scorepercent, 100 - $scorepercent];
            $allscore = null;
            if ($compare || $allresponses) {
                $allscore = [$allscorepercent, 100 - $allscorepercent];
            }
            $usergraph = get_config('questionnaire', 'usergraph');
            $charthtml = null;
            if ($usergraph && $this->chart_type()) {
                $charthtml = draw_chart(
                    $feedbacktype = 'global',
                    $labels,
                    $groupname,
                    $allresponses,
                    $this->chart_type(),
                    $score,
                    $allscore,
                    $sectionlabel
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
            $tablehtml = null;
            if ($this->show_scores()) {
                $table = $table ?? new html_table();
                if ($compare) {
                    $table->data[] = [$sectionlabel, $score[0] . '%' . $oppositescore, $allscore[0] . '%' . $oppositeallscore];
                } else {
                    $table->data[] = [$sectionlabel, $allscore[0] . '%' . $oppositeallscore];
                }
                $tablehtml = html_writer::table($table);
            }

            return new scoreboard($feedbackmessages, $tablehtml, $charthtml);
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

        $sectionlabel = '';
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
                $feedbackmessages[] = $renderer->box_start('reportQuestionTitle');
                $feedbackmessages[] = format_text($sectionheading, FORMAT_HTML, $formatoptions);
                $feedback = $DB->get_record_select(
                    'questionnaire_feedback',
                    'sectionid = ? AND minscore <= ? AND ? < maxscore',
                    [$feedbacksectionid, $scorepercent[$section], $scorepercent[$section]],
                    'id,feedbacktext,feedbacktextformat'
                );
                $feedbackmessages[] = $renderer->box_end();
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
                    $feedbackmessages[] = $renderer->box_start('feedbacktext');
                    $feedbackmessages[] = $feedbacktext;
                    $feedbackmessages[] = $renderer->box_end();
                }
            }
        }

        switch ($sortaction) {
            case 'vallasort':
                asort($allscore);
                break;
            case 'vallarsort':
                arsort($allscore);
                break;
            default:
        }

        $tablehtml = null;
        if ($this->show_scores()) {
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
            $tablehtml = html_writer::table($table);
        }
        $usergraph = get_config('questionnaire', 'usergraph');

        foreach ($nanscores as $val) {
            unset($chartlabels[$val]);
            unset($scorepercent[$val]);
            unset($allscorepercent[$val]);
        }

        $charthtml = null;
        if ($usergraph && $this->chart_type()) {
            $charthtml = draw_chart(
                'sections',
                array_values($chartlabels),
                $groupname,
                $allresponses,
                $this->chart_type(),
                array_values($scorepercent),
                array_values($allscorepercent),
                $sectionlabel
            );
        }

        return new scoreboard($feedbackmessages, $tablehtml, $charthtml);
    }

    /**
     * Ensure a first feedback section exists for the survey.
     *
     * Used by the "Save settings and edit Feedback Sections" path so the
     * user always lands on a real section editor. Returns the id of the
     * section to redirect to (existing first section, or newly created one).
     *
     * @return int Section id (0 if no sections exist and no feedback is configured).
     */
    public function ensure_first_section(): int {
        global $DB;
        $surveyid = $this->questionnaire->surveyid();
        $firstsection = (int) ($DB->get_field(
            'questionnaire_fb_sections',
            'MIN(section)',
            ['surveyid' => $surveyid]
        ) ?: 0);

        $mode = $this->mode();
        if ($mode > 0 && $firstsection === 0) {
            $label = ($mode === 1)
                ? get_string('feedbackglobal', 'questionnaire')
                : get_string('feedbackdefaultlabel', 'questionnaire');
            section::new_section($surveyid, $label);
        }
        return $firstsection;
    }
}
