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
 * Unit tests for mod_questionnaire\gradebook.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\gradebook.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\gradebook
 */
final class gradebook_test extends \advanced_testcase {
    /**
     * Stand up a course + questionnaire instance and return both.
     *
     * @param int $grade Grade max for the questionnaire instance.
     * @return array [course, questionnaire]
     */
    private function make_instance(int $grade = 100): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $generator->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id, 'grade' => $grade]);
        return [$course, $questionnaire];
    }

    /**
     * Insert a completed response row.
     *
     * @param int $questionnaireid
     * @param int $userid
     * @param int $grade
     * @return int
     */
    private function insert_response(int $questionnaireid, int $userid, int $grade): int {
        global $DB;
        return $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaireid,
            'userid' => $userid,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => $grade,
        ]);
    }

    /**
     * get_user_grades() returns rows keyed by response id with rawgrade/dategraded for one user.
     */
    public function test_get_user_grades_user_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        [, $questionnaire] = $this->make_instance(100);

        $u1 = $generator->create_user();
        $u2 = $generator->create_user();
        $r1 = $this->insert_response($questionnaire->id(), $u1->id, 80);
        $this->insert_response($questionnaire->id(), $u2->id, 50);

        $rows = gradebook::get_user_grades($questionnaire, $u1->id);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals($u1->id, $row->userid);
        $this->assertEquals(80, $row->rawgrade);
    }

    /**
     * get_user_grades() with userid 0 returns rows for every user.
     */
    public function test_get_user_grades_all_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        [, $questionnaire] = $this->make_instance(100);

        $u1 = $generator->create_user();
        $u2 = $generator->create_user();
        $this->insert_response($questionnaire->id(), $u1->id, 80);
        $this->insert_response($questionnaire->id(), $u2->id, 50);

        $rows = gradebook::get_user_grades($questionnaire);
        $this->assertCount(2, $rows);
    }

    /**
     * get_user_grades() only counts complete responses.
     */
    public function test_get_user_grades_skips_incomplete(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        [, $questionnaire] = $this->make_instance(100);
        $user = $generator->create_user();
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => $user->id,
            'submitted' => time(),
            'complete' => 'n',
            'grade' => 80,
        ]);

        $this->assertCount(0, gradebook::get_user_grades($questionnaire));
    }

    /**
     * grade_item_update() creates a grade item record for the questionnaire.
     */
    public function test_grade_item_update_creates_grade_item(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $questionnaire] = $this->make_instance(100);

        $this->assertSame(GRADE_UPDATE_OK, gradebook::grade_item_update($questionnaire));

        $this->assertTrue($DB->record_exists('grade_items', [
            'itemmodule' => 'questionnaire',
            'iteminstance' => $questionnaire->id(),
        ]));
    }

    /**
     * grade_item_update() with grade = 0 deletes the grade item.
     */
    public function test_grade_item_update_with_zero_grade_deletes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $questionnaire] = $this->make_instance(100);
        // Create the item first.
        gradebook::grade_item_update($questionnaire);
        $this->assertTrue($DB->record_exists('grade_items', [
            'itemmodule' => 'questionnaire',
            'iteminstance' => $questionnaire->id(),
        ]));

        // Zero-grade instances should remove the grade item.
        $DB->set_field('questionnaire', 'grade', 0, ['id' => $questionnaire->id()]);
        $zerorec = $DB->get_record('questionnaire', ['id' => $questionnaire->id()]);
        $zerorec->cmidnumber = '';
        gradebook::grade_item_update($zerorec);

        $this->assertFalse($DB->record_exists('grade_items', [
            'itemmodule' => 'questionnaire',
            'iteminstance' => $questionnaire->id(),
        ]));
    }

    /**
     * update_grades() pushes user grades to the gradebook for a specific questionnaire.
     */
    public function test_update_grades_pushes_user_grades(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        [, $questionnaire] = $this->make_instance(100);
        $user = $generator->create_user();
        $this->insert_response($questionnaire->id(), $user->id, 80);

        gradebook::update_grades($questionnaire, $user->id);

        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'questionnaire',
            'iteminstance' => $questionnaire->id(),
        ]);
        $this->assertNotEmpty($itemid);
        $this->assertTrue($DB->record_exists('grade_grades', [
            'itemid' => $itemid,
            'userid' => $user->id,
        ]));
    }
}
