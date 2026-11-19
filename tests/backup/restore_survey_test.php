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
 * Backup and restore tests for the survey a restored questionnaire uses.
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
 * Tests which survey a restored questionnaire uses.
 *
 * @group mod_questionnaire
 * @covers \restore_questionnaire_activity_structure_step
 */
final class restore_survey_test extends \advanced_testcase {
    /**
     * A copy of a questionnaire using another course's public survey keeps using that survey.
     */
    public function test_restore_keeps_public_survey(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$publicsid, $course, $cm] = $this->make_questionnaire_using_public_survey();
        $this->assertEquals($publicsid, $this->backup_and_restore($course, $cm));
    }

    /**
     * A restored questionnaire whose backup has no survey only points at an existing survey when that survey is public;
     * otherwise it gets an empty survey of its own.
     */
    public function test_restore_does_not_take_over_private_survey(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$sid, $course, $cm] = $this->make_questionnaire_using_public_survey();
        $newsid = $this->backup_and_restore($course, $cm, function () use ($DB, $sid) {
            $DB->set_field('questionnaire_survey', 'realm', 'private', ['id' => $sid]);
        });
        $this->assertNotEquals($sid, $newsid);
        $survey = $DB->get_record('questionnaire_survey', ['id' => $newsid], '*', MUST_EXIST);
        $this->assertEquals($course->id, $survey->courseid);
        $this->assertEquals('private', $survey->realm);
        $this->assertEquals(0, $DB->count_records('questionnaire_question', ['surveyid' => $newsid]));
    }

    /**
     * Make a public survey in one course and a questionnaire in another course that uses it.
     *
     * @return array [the public survey id, the course using it, the course module of the questionnaire using it]
     */
    private function make_questionnaire_using_public_survey(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $qgenerator = $generator->get_plugin_generator('mod_questionnaire');
        $owner = $qgenerator->create_instance(['course' => $generator->create_course()->id]);
        $publicsid = $owner->surveyid();
        $DB->set_field('questionnaire_survey', 'realm', 'public', ['id' => $publicsid]);

        $course = $generator->create_course();
        $created = $qgenerator->create_instance(['course' => $course->id]);
        $DB->set_field('questionnaire', 'sid', $publicsid, ['id' => $created->id()]);

        return [$publicsid, $course, get_coursemodule_from_instance('questionnaire', $created->id())];
    }

    /**
     * Back up a questionnaire, run a change between backup and restore, and restore it into its course.
     *
     * @param \stdClass $course
     * @param \stdClass $cm
     * @param callable|null $beforerestore
     * @return int the restored questionnaire's survey id
     */
    private function backup_and_restore(\stdClass $course, \stdClass $cm, ?callable $beforerestore = null): int {
        global $DB, $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $cm->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        if ($beforerestore) {
            $beforerestore();
        }

        $rc = new \restore_controller(
            $backupid,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_CURRENT_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $newid = $DB->get_field_sql('SELECT MAX(id) FROM {questionnaire} WHERE course = ?', [$course->id]);
        return (int)$DB->get_field('questionnaire', 'sid', ['id' => $newid]);
    }
}
