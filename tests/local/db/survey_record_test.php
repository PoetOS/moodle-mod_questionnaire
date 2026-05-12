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
 * Unit tests for mod_questionnaire\local\db\survey_record.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for mod_questionnaire\local\db\survey_record.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\db\survey_record
 */
final class survey_record_test extends \advanced_testcase {
    /**
     * Insert a survey row via $DB and return its id.
     *
     * @param int $courseid
     * @param string $name
     * @param string $realm
     * @return int
     */
    private function make_survey(int $courseid, string $name, string $realm = 'private'): int {
        global $DB;
        return $DB->insert_record('questionnaire_survey', (object)[
            'name' => $name,
            'courseid' => $courseid,
            'realm' => $realm,
            'status' => 0,
            'title' => $name,
        ]);
    }

    /**
     * Insert a questionnaire row that points to the given survey.
     *
     * @param int $courseid
     * @param int $sid
     * @return int
     */
    private function make_questionnaire(int $courseid, int $sid): int {
        global $DB;
        return $DB->insert_record('questionnaire', (object)[
            'course' => $courseid,
            'name' => 'Q' . $sid,
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'qtype' => 0,
            'respondenttype' => 'fullname',
            'respeligible' => 'all',
            'respview' => 0,
            'sid' => $sid,
            'timemodified' => time(),
        ]);
    }

    /**
     * record_exists() reports presence of a survey row by id.
     */
    public function test_record_exists(): void {
        $this->resetAfterTest();
        $sid = $this->make_survey(1, 'Existing survey');

        $this->assertTrue(survey_record::record_exists($sid));
        $this->assertFalse(survey_record::record_exists(99999));
    }

    /**
     * get_by_realm_in_course() returns surveys matching realm + course.
     */
    public function test_get_by_realm_in_course(): void {
        $this->resetAfterTest();
        $course = 11;
        $this->make_survey($course, 'A', 'private');
        $this->make_survey($course, 'B', 'private');
        $this->make_survey($course, 'C', 'public');
        $this->make_survey(99, 'D', 'private');

        $this->assertCount(2, survey_record::get_by_realm_in_course('private', $course));
        $this->assertCount(1, survey_record::get_by_realm_in_course('public', $course));
        $this->assertCount(0, survey_record::get_by_realm_in_course('template', $course));
    }

    /**
     * get_by_realm() returns surveys of the realm across all courses.
     */
    public function test_get_by_realm(): void {
        $this->resetAfterTest();
        $this->make_survey(1, 'A', 'public');
        $this->make_survey(2, 'B', 'public');
        $this->make_survey(3, 'C', 'template');

        $this->assertCount(2, survey_record::get_by_realm('public'));
        $this->assertCount(1, survey_record::get_by_realm('template'));
    }

    /**
     * get_orphaned() returns surveys with no linked questionnaire row.
     */
    public function test_get_orphaned(): void {
        $this->resetAfterTest();
        $linked = $this->make_survey(1, 'linked');
        $orphan1 = $this->make_survey(1, 'orphan1');
        $orphan2 = $this->make_survey(1, 'orphan2');
        $this->make_questionnaire(1, $linked);

        $orphans = survey_record::get_orphaned();
        $ids = array_map(fn($r) => $r->get('id'), $orphans);

        $this->assertContains($orphan1, $ids);
        $this->assertContains($orphan2, $ids);
        $this->assertNotContains($linked, $ids);
    }

    /**
     * create_from_sdata() inserts a row and returns the persistent.
     */
    public function test_create_from_sdata(): void {
        global $DB;
        $this->resetAfterTest();
        $sdata = (object)[
            'name' => 'Created via sdata',
            'courseid' => 7,
            'realm' => 'private',
            'status' => 0,
            'title' => 'Created via sdata',
        ];
        $record = survey_record::create_from_sdata($sdata);

        $this->assertGreaterThan(0, $record->get('id'));
        $this->assertTrue($DB->record_exists('questionnaire_survey', ['id' => $record->get('id')]));
        $this->assertSame('Created via sdata', $record->get('name'));
    }
}
