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
use mod_questionnaire\local\question_type;

/**
 * Renderable for the manage-questions page: the add-question bar, the drag-and-drop
 * question list, and the recycle bin.
 *
 * Replaces the mform-rendered classes\questions_form. Reordering is handled client-side
 * (core/sortable_list + the mod_questionnaire_question_reorder AJAX external); this class
 * only ever renders the current server-side state, so it carries no "moving" mode.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_manager implements \renderable, \templatable {
    /** @var questionnaire The questionnaire being managed. */
    private questionnaire $questionnaire;

    /** @var int Question id just restored from the recycle bin, for the row highlight. */
    private int $restoredqid;

    /** @var int|null Sticky add-question type from the last save, for the add-bar's selection. */
    private ?int $lasttypeid;

    /** @var string Sticky required flag ('y'/'n'/'') from the last save, carried into the add-bar. */
    private string $lastrequired;

    /**
     * Constructor. All state is threaded in from questions.php — this class never reads
     * request parameters itself.
     *
     * @param questionnaire $questionnaire The questionnaire being managed.
     * @param int $restoredqid Question id just restored from the recycle bin (0 if none).
     * @param int|null $lasttypeid Sticky add-question type from the last save.
     * @param string $lastrequired Sticky required flag from the last save.
     */
    public function __construct(
        questionnaire $questionnaire,
        int $restoredqid = 0,
        ?int $lasttypeid = null,
        string $lastrequired = ''
    ) {
        $this->questionnaire = $questionnaire;
        $this->restoredqid = $restoredqid;
        $this->lasttypeid = $lasttypeid;
        $this->lastrequired = $lastrequired;
    }

    /**
     * Export the page for templates/question_manager.mustache.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $cmid = $this->questionnaire->coursemodule()->id;
        $questions = $this->questionnaire->questions();
        $hasdependencies = $this->questionnaire->navigator()->has_dependencies();

        $data = new \stdClass();
        $data->cmid = $cmid;
        $data->addbar = $this->export_addbar($cmid, $questions);
        $data->hasquestions = !empty($questions);
        $data->rows = $this->export_rows($output, $cmid, $questions, $hasdependencies);
        $data->recyclebin = $this->export_recyclebin($output, $cmid);
        $data->checkbreaksurl = $hasdependencies
            ? (new \moodle_url('/mod/questionnaire/questions.php', [
                'id' => $cmid, 'validate' => 1, 'sesskey' => sesskey(),
            ]))->out(false)
            : null;

        return $data;
    }

    /**
     * Export the add-question bar: the type select and its sticky-default hidden fields.
     *
     * @param int $cmid
     * @param array $questions Active questions, keyed by id.
     * @return \stdClass
     */
    private function export_addbar(int $cmid, array $questions): \stdClass {
        global $DB;

        $addbar = new \stdClass();
        $addbar->actionurl = (new \moodle_url('/mod/questionnaire/questions.php', ['id' => $cmid]))->out(false);
        $addbar->sesskey = sesskey();
        $addbar->lastrequired = $this->lastrequired;

        $qtypes = $DB->get_records_select_menu('questionnaire_question_type', '', null, '', 'typeid,type');
        $qtypes = $qtypes ?: [];
        foreach ($qtypes as $key => $qtype) {
            // Do not allow "Page Break" to be selected as the first element of a questionnaire.
            if (empty($questions) && $qtype == 'Page Break') {
                unset($qtypes[$key]);
                continue;
            }
            $qtypes[$key] = question_type::display_name($key);
        }
        natsort($qtypes);

        $addbar->typeoptions = [];
        foreach ($qtypes as $typeid => $name) {
            $addbar->typeoptions[] = [
                'value' => $typeid,
                'name' => $name,
                'selected' => ($this->lasttypeid !== null && $this->lasttypeid == $typeid),
            ];
        }

        return $addbar;
    }

    /**
     * Export the question list rows.
     *
     * @param \renderer_base $output
     * @param int $cmid
     * @param array $questions Active questions, keyed by id.
     * @param bool $hasdependencies Whether the questionnaire has any dependency rules at all.
     * @return array
     */
    private function export_rows(\renderer_base $output, int $cmid, array $questions, bool $hasdependencies): array {
        $rows = [];
        $qnum = 0;
        foreach ($questions as $question) {
            $qid = $question->id();
            $typeid = $question->typeid();
            $ispagebreak = ($typeid == QUESPAGEBREAK);

            $dependencyhtml = '';
            if ($hasdependencies) {
                $this->questionnaire->navigator()->load_parents($question);
                if (!empty($question->dependencies)) {
                    $dependencyhtml = $output->get_dependency_html($qid, $question->dependencies);
                }
            }

            $typename = '[' . question_type::display_name($typeid) . ']';
            $qname = $question->name() ? '(' . $question->name() . ')' : '';
            $label = trim($typename . ' ' . $qname);

            $contenthtml = '';
            $number = '';
            if (!$ispagebreak) {
                $displaycontent = $question->content();
                if ($displaycontent == '<p>  </p>') {
                    $displaycontent = '';
                }
                $contenthtml = format_text(
                    file_rewrite_pluginfile_urls(
                        $displaycontent,
                        'pluginfile.php',
                        $question->context->id,
                        'mod_questionnaire',
                        'question',
                        $qid
                    ),
                    FORMAT_HTML,
                    ['noclean' => true]
                );
                if ($question->is_numbered()) {
                    $qnum++;
                    $number = $qnum;
                }
            }

            $showrequired = !$ispagebreak && $typeid != QUESSECTIONTEXT && $typeid != QUESSLIDER;
            $required = $question->required();

            $rows[] = [
                'id' => $qid,
                'dataname' => $label,
                'ispagebreak' => $ispagebreak,
                'position' => $question->position(),
                'number' => $number,
                'label' => $label,
                'movetitle' => get_string('move') . ' ' . $label,
                'contenthtml' => $contenthtml,
                'dependencyhtml' => $dependencyhtml,
                'indent' => !empty($dependencyhtml),
                'restored' => ($qid == $this->restoredqid),
                'editurl' => $ispagebreak ? null : (new \moodle_url('/mod/questionnaire/questions.php', [
                    'id' => $cmid, 'action' => 'question', 'qid' => $qid,
                ]))->out(false),
                'deleteurl' => $ispagebreak
                    ? (new \moodle_url('/mod/questionnaire/questions.php', [
                        'id' => $cmid, 'delq' => $qid, 'sesskey' => sesskey(),
                    ]))->out(false)
                    : (new \moodle_url('/mod/questionnaire/questions.php', [
                        'id' => $cmid, 'action' => 'confirmdelquestion', 'qid' => $qid,
                    ]))->out(false),
                'showrequired' => $showrequired,
                'required' => $required,
                'requiredlabel' => $required
                    ? get_string('required', 'questionnaire') . ' ' . get_string('clicktoswitch', 'questionnaire')
                    : get_string('notrequired', 'questionnaire') . ' ' . get_string('clicktoswitch', 'questionnaire'),
                'requiredurl' => (new \moodle_url('/mod/questionnaire/questions.php', [
                    'id' => $cmid, 'togglerequired' => $qid, 'sesskey' => sesskey(),
                ]))->out(false),
            ];
        }
        return $rows;
    }

    /**
     * Export the recycle bin (soft-deleted questions).
     *
     * @param \renderer_base $output
     * @param int $cmid
     * @return \stdClass
     */
    private function export_recyclebin(\renderer_base $output, int $cmid): \stdClass {
        $deletequestions = $this->questionnaire->survey()->get_delete_questions();
        $duration = \mod_questionnaire\local\survey\survey::question_deletion_duration();

        $rows = [];
        foreach ($deletequestions as $qid => $question) {
            $typeandname = '[' . question_type::display_name($question->typeid()) . ']';
            $typeandname .= $question->name() ? ' (' . $question->name() . ')' : '';

            $displaycontent = $question->content();
            if ($displaycontent == '<p>  </p>') {
                $displaycontent = '';
            }
            $contenthtml = format_text(
                file_rewrite_pluginfile_urls(
                    $displaycontent,
                    'pluginfile.php',
                    $question->context->id,
                    'mod_questionnaire',
                    'question',
                    $qid
                ),
                FORMAT_HTML,
                ['noclean' => true]
            );

            if (empty($duration)) {
                $timedeletedhtml = get_string('recylebindisabled', 'questionnaire');
            } else {
                $timedeletedhtml = get_string('timedeletednext7days', 'questionnaire', $duration);
            }

            $rows[] = [
                'id' => $qid,
                'typeandname' => $typeandname,
                'contenthtml' => $contenthtml,
                'timedeletedhtml' => $timedeletedhtml,
                'restoreurl' => (new \moodle_url('/mod/questionnaire/questions.php', [
                    'id' => $cmid, 'restoreq' => $qid, 'sesskey' => sesskey(),
                ]))->out(false),
                'deleteurl' => (new \moodle_url('/mod/questionnaire/questions.php', [
                    'id' => $cmid, 'action' => 'confirmdelpermanentlyq', 'qid' => $qid,
                ]))->out(false),
            ];
        }

        $recyclebin = new \stdClass();
        $recyclebin->hasrows = !empty($rows);
        $recyclebin->rows = $rows;
        return $recyclebin;
    }
}
