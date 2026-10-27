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
 * Unit tests for mod_questionnaire\output\question_manager.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;
use mod_questionnaire\local\db\question_record;

/**
 * Unit tests for mod_questionnaire\output\question_manager.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\question_manager
 */
final class question_manager_test extends \advanced_testcase {
    /**
     * Fetch the plugin renderer.
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('mod_questionnaire');
    }

    /**
     * Build an empty questionnaire (no questions yet).
     *
     * @return questionnaire
     */
    private function build_empty_questionnaire(): questionnaire {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        return questionnaire::from_instanceid($questionnaire->id());
    }

    /**
     * Build a questionnaire with a yes/no question at position 1.
     *
     * @return array{0: questionnaire, 1: int} [questionnaire, question id]
     */
    private function build_questionnaire_with_question(): array {
        $questionnaire = $this->build_empty_questionnaire();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $question = $generator->create_question($questionnaire, [
            'surveyid' => $questionnaire->surveyid(),
            'name' => 'Q1',
            'typeid' => QUESYESNO,
            'content' => 'Do you like Moodle?',
        ]);
        question_record::update_position($question->id(), 1);
        return [questionnaire::from_instanceid($questionnaire->id()), $question->id()];
    }

    /**
     * export_for_template() carries the course module id and the sesskey used by every
     * mutation link, and suppresses the page-break option in the add bar when the survey
     * has no questions yet (a page break cannot be the first item).
     */
    public function test_export_suppresses_pagebreak_option_when_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_empty_questionnaire();

        $manager = new question_manager($questionnaire);
        $data = $manager->export_for_template($this->renderer());

        $this->assertEquals($questionnaire->coursemodule()->id, $data->cmid);
        $this->assertFalse($data->hasquestions);
        $this->assertEmpty($data->rows);
        foreach ($data->addbar->typeoptions as $option) {
            $this->assertNotEquals(QUESPAGEBREAK, $option['value']);
        }
    }

    /**
     * A sticky lasttypeid from the previous save is marked selected in the add bar, and the
     * page-break option is offered once the survey is no longer empty.
     */
    public function test_export_marks_sticky_typeid_selected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire] = $this->build_questionnaire_with_question();

        $manager = new question_manager($questionnaire, 0, QUESYESNO, 'y');
        $data = $manager->export_for_template($this->renderer());

        $selected = array_filter($data->addbar->typeoptions, fn($option) => $option['selected']);
        $this->assertCount(1, $selected);
        $this->assertEquals(QUESYESNO, reset($selected)['value']);
        $this->assertEquals('y', $data->addbar->lastrequired);

        $haspagebreak = array_filter($data->addbar->typeoptions, fn($option) => $option['value'] == QUESPAGEBREAK);
        $this->assertCount(1, $haspagebreak);
    }

    /**
     * A regular question row is flagged non-pagebreak, shows the required toggle, carries a
     * sesskey on every mutation URL, and is not marked as restored.
     */
    public function test_export_rows_shape_for_regular_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire, $qid] = $this->build_questionnaire_with_question();

        $manager = new question_manager($questionnaire);
        $data = $manager->export_for_template($this->renderer());

        $this->assertCount(1, $data->rows);
        $row = $data->rows[0];
        $this->assertEquals($qid, $row['id']);
        $this->assertFalse($row['ispagebreak']);
        $this->assertEquals(1, $row['position']);
        $this->assertEquals(1, $row['number']);
        $this->assertStringContainsString('Do you like Moodle?', $row['contenthtml']);
        $this->assertTrue($row['showrequired']);
        $this->assertFalse($row['restored']);
        $this->assertStringContainsString('sesskey=', $row['requiredurl']);
        $this->assertStringContainsString('action=confirmdelquestion', $row['deleteurl']);
        $this->assertStringContainsString('action=question', $row['editurl']);
    }

    /**
     * A page-break row has no content, no number bubble, no edit link, no required toggle,
     * and its delete link goes straight to delq (no confirmation needed) with a sesskey.
     */
    public function test_export_rows_shape_for_pagebreak(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire, $qid] = $this->build_questionnaire_with_question();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $pagebreak = $generator->create_question($questionnaire, [
            'surveyid' => $questionnaire->surveyid(), 'name' => '', 'typeid' => QUESPAGEBREAK,
        ]);
        question_record::update_position($pagebreak->id(), 2);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $manager = new question_manager($questionnaire);
        $data = $manager->export_for_template($this->renderer());

        $this->assertCount(2, $data->rows);
        $row = $data->rows[1];
        $this->assertEquals($pagebreak->id(), $row['id']);
        $this->assertTrue($row['ispagebreak']);
        $this->assertEquals('', $row['contenthtml']);
        $this->assertEquals('', $row['number']);
        $this->assertFalse($row['showrequired']);
        $this->assertNull($row['editurl']);
        $this->assertStringContainsString('delq=' . $pagebreak->id(), $row['deleteurl']);
        $this->assertStringContainsString('sesskey=', $row['deleteurl']);
    }

    /**
     * A question restored from the recycle bin (matching $restoredqid) is flagged for the
     * highlight; other rows are not.
     */
    public function test_export_flags_restored_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire, $qid] = $this->build_questionnaire_with_question();

        $manager = new question_manager($questionnaire, $qid);
        $data = $manager->export_for_template($this->renderer());

        $this->assertTrue($data->rows[0]['restored']);
    }

    /**
     * The recycle bin is empty for a fresh questionnaire and has no checkbreaksurl offered
     * when the survey has no dependency-capable settings enabled.
     */
    public function test_export_recyclebin_empty_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire] = $this->build_questionnaire_with_question();

        $manager = new question_manager($questionnaire);
        $data = $manager->export_for_template($this->renderer());

        $this->assertFalse($data->recyclebin->hasrows);
        $this->assertEmpty($data->recyclebin->rows);
    }

    /**
     * A soft-deleted question appears in the recycle bin with its type/name, a restore URL,
     * a permanent-delete confirmation URL, and the disabled-bin message when the deletion
     * subplugin's duration is set to 0.
     */
    public function test_export_recyclebin_rows_for_deleted_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('duration', 0, 'questionnaire_questiondeletion');
        [$questionnaire, $qid] = $this->build_questionnaire_with_question();
        $questionnaire->survey()->soft_delete_question($qid, $questionnaire->id());
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $manager = new question_manager($questionnaire);
        $data = $manager->export_for_template($this->renderer());

        $this->assertTrue($data->recyclebin->hasrows);
        $this->assertCount(1, $data->recyclebin->rows);
        $row = $data->recyclebin->rows[0];
        $this->assertEquals($qid, $row['id']);
        $this->assertStringContainsString('Q1', $row['typeandname']);
        $this->assertStringContainsString('restoreq=' . $qid, $row['restoreurl']);
        $this->assertStringContainsString('sesskey=', $row['restoreurl']);
        $this->assertStringContainsString('action=confirmdelpermanentlyq', $row['deleteurl']);
        $this->assertStringContainsString(get_string('recylebindisabled', 'questionnaire'), $row['timedeletedhtml']);
    }

    /**
     * With a non-zero deletion duration configured, the recycle bin row shows the
     * time-of-permanent-deletion countdown message instead of the disabled-bin message.
     */
    public function test_export_recyclebin_shows_countdown_when_duration_set(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('duration', WEEKSECS, 'questionnaire_questiondeletion');
        [$questionnaire, $qid] = $this->build_questionnaire_with_question();
        $questionnaire->survey()->soft_delete_question($qid, $questionnaire->id());
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $manager = new question_manager($questionnaire);
        $data = $manager->export_for_template($this->renderer());

        $row = $data->recyclebin->rows[0];
        $this->assertStringNotContainsString(get_string('recylebindisabled', 'questionnaire'), $row['timedeletedhtml']);
        $this->assertStringContainsString('timedeletednext7days', $row['timedeletedhtml']);
    }

    /**
     * render_question_manager() renders the export through the mustache template without error.
     */
    public function test_renders_through_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire] = $this->build_questionnaire_with_question();

        $manager = new question_manager($questionnaire);
        $html = $this->renderer()->render($manager);

        $this->assertStringContainsString('id_typeid', $html);
        $this->assertStringContainsString('questionnaire-questionlist', $html);
        $this->assertStringContainsString('questionnaire-recyclebin', $html);
    }
}
