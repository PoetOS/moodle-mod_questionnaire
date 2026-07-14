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
 * Unit tests for mod_questionnaire\external\question_reorder.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\external;

use core_external\external_api;
use mod_questionnaire\local\db\question_record;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\external\question_reorder.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\external\question_reorder
 */
final class question_reorder_test extends \advanced_testcase {
    /**
     * Build a real questionnaire with three questions at known positions (1, 2, 3).
     *
     * @return array{0: questionnaire, 1: int, 2: int, 3: int} [questionnaire, q1id, q2id, q3id]
     */
    private function build_questionnaire_with_questions(): array {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $ids = [];
        foreach (['Q1', 'Q2', 'Q3'] as $index => $name) {
            $question = $generator->create_question($questionnaire, [
                'surveyid' => $questionnaire->surveyid(),
                'name' => $name,
                'typeid' => QUESYESNO,
            ]);
            question_record::update_position($question->id(), $index + 1);
            $ids[] = $question->id();
        }

        return [$questionnaire, $ids[0], $ids[1], $ids[2]];
    }

    /**
     * execute() persists a new order and reports reload = false when nothing needed
     * repairing (check_page_breaks() always returns a human-readable message, even when
     * it did nothing, so "reload" — not an empty warnings string — is the real signal).
     */
    public function test_execute_persists_order(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire, $q1, $q2, $q3] = $this->build_questionnaire_with_questions();

        $result = question_reorder::execute($questionnaire->coursemodule()->id, "$q3,$q1,$q2");
        $result = external_api::clean_returnvalue(question_reorder::execute_returns(), $result);

        $this->assertFalse($result['reload']);
        $this->assertEquals(1, $DB->get_field('questionnaire_question', 'position', ['id' => $q3]));
        $this->assertEquals(2, $DB->get_field('questionnaire_question', 'position', ['id' => $q1]));
        $this->assertEquals(3, $DB->get_field('questionnaire_question', 'position', ['id' => $q2]));
    }

    /**
     * execute() rejects a caller without mod/questionnaire:editquestions.
     */
    public function test_execute_requires_capability(): void {
        $this->resetAfterTest();
        [$questionnaire] = $this->build_questionnaire_with_questions();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        question_reorder::execute($questionnaire->coursemodule()->id, '1,2,3');
    }

    /**
     * execute() lets a dependency-violation exception from reorder_questions() propagate,
     * so the AJAX caller sees the real validation error rather than a silent failure.
     */
    public function test_execute_propagates_dependency_violation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$questionnaire, $q1, $q2, $q3] = $this->build_questionnaire_with_questions();

        $dependency = new \mod_questionnaire\local\db\dependency_record(0, (object)[
            'questionid' => $q2,
            'surveyid' => $questionnaire->surveyid(),
            'dependquestionid' => $q1,
        ]);
        $dependency->create();

        // Full valid set of ids, but the dependent question (q2) is placed before its
        // parent (q1) — this must fail on the dependency check, not the set-mismatch check.
        $this->expectException(\moodle_exception::class);
        question_reorder::execute($questionnaire->coursemodule()->id, "$q2,$q1,$q3");
    }

    /**
     * execute() reports reload = true and passes through check_page_breaks()'s message when
     * the reorder triggers a page-break repair — the client's row list is stale and must
     * re-fetch rather than patch itself from the drag event.
     */
    public function test_execute_reports_reload_when_pagebreak_repaired(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(['course' => $course->id]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $q1 = $generator->create_question($questionnaire, [
            'surveyid' => $questionnaire->surveyid(), 'name' => 'Q1', 'typeid' => QUESYESNO,
        ]);
        $pb1 = $generator->create_question($questionnaire, [
            'surveyid' => $questionnaire->surveyid(), 'name' => 'break1', 'typeid' => QUESPAGEBREAK,
        ]);
        $pb2 = $generator->create_question($questionnaire, [
            'surveyid' => $questionnaire->surveyid(), 'name' => 'break2', 'typeid' => QUESPAGEBREAK,
        ]);
        $q2 = $generator->create_question($questionnaire, [
            'surveyid' => $questionnaire->surveyid(), 'name' => 'Q2', 'typeid' => QUESYESNO,
        ]);
        question_record::update_position($q1->id(), 1);
        question_record::update_position($pb1->id(), 2);
        question_record::update_position($pb2->id(), 3);
        question_record::update_position($q2->id(), 4);

        // Same relative order as today — the redundant consecutive page break is the trigger.
        $itemorder = $q1->id() . ',' . $pb1->id() . ',' . $pb2->id() . ',' . $q2->id();
        $result = question_reorder::execute($questionnaire->coursemodule()->id, $itemorder);
        $result = external_api::clean_returnvalue(question_reorder::execute_returns(), $result);

        $this->assertTrue($result['reload']);
        $this->assertNotSame('', $result['warnings']);
    }
}
