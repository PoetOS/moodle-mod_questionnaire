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
 * Backup and restore tests for file question answers.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for file question answers.
 *
 * @group mod_questionnaire
 * @covers \restore_questionnaire_activity_structure_step
 */
final class restore_response_file_test extends \advanced_testcase {
    /**
     * A restored file answer is stored under its restored record's id and the record points at that file.
     */
    public function test_restore_keys_response_file_to_new_record(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESFILE,
            ['content' => 'Upload a file']
        );
        $questions = $questionnaire->questions();
        $question = reset($questions);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $rid = \mod_questionnaire\local\response\response::create($questionnaire->id(), $student->id)->id();
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($student->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'answer.txt',
        ], 'answer');
        $oldrecordid = $question->insert_response((object)['rid' => $rid, 'q' . $question->id() => $draftitemid]);
        $this->setAdminUser();

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'R1', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $newinstance = $DB->get_record('questionnaire', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('questionnaire', $newinstance->id, $newcourseid);
        $newcontext = \context_module::instance($newcm->id);
        $newrid = $DB->get_field('questionnaire_response', 'id', ['questionnaireid' => $newinstance->id], MUST_EXIST);
        $newrecord = $DB->get_record('questionnaire_response_file', ['responseid' => $newrid], '*', MUST_EXIST);

        $this->assertNotEquals($oldrecordid, $newrecord->id);
        $file = get_file_storage()->get_file_by_id($newrecord->fileid);
        $this->assertNotFalse($file);
        $this->assertEquals($newcontext->id, $file->get_contextid());
        $this->assertEquals('response_file', $file->get_filearea());
        $this->assertEquals($newrecord->id, $file->get_itemid());
        $this->assertSame('answer.txt', $file->get_filename());
    }
}
