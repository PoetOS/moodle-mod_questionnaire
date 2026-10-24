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
 * Unit tests for mod_questionnaire\local\report\report_actions.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\report;

/**
 * Unit tests for mod_questionnaire\local\report\report_actions.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\report\report_actions
 */
final class report_actions_test extends \advanced_testcase {
    /**
     * delete_response removes the response record and fires response_deleted.
     */
    public function test_delete_response_removes_record_and_redirects(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());

        $rid = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $sink = $this->redirectEvents();
        try {
            (new report_actions($questionnaire, $renderer))->delete_response($rid);
            $this->fail('redirect() should have terminated the call');
        } catch (\moodle_exception $e) {
            // Under PHPUnit, redirect() throws; the response should still have been deleted first.
            $this->assertFalse($DB->record_exists('questionnaire_response', ['id' => $rid]));
        }

        $events = array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof \mod_questionnaire\event\response_deleted
        );
        $this->assertCount(1, $events);
    }

    /**
     * delete_response throws invalidresponserecord when the rid does not exist.
     */
    public function test_delete_response_throws_for_missing_rid(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/invalidresponserecord|Invalid response record/i');
        (new report_actions($questionnaire, $renderer))->delete_response(99999999);
    }

    /**
     * delete_all_responses removes every passed response and fires all_responses_deleted.
     */
    public function test_delete_all_responses_removes_records_and_redirects(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());

        $ridone = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);
        $ridtwo = $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 3,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $resps = $questionnaire->get_responses();
        $this->assertCount(2, $resps);

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $sink = $this->redirectEvents();
        try {
            (new report_actions($questionnaire, $renderer))
                ->delete_all_responses(0, 0, $resps);
            $this->fail('redirect() should have terminated the call');
        } catch (\moodle_exception $e) {
            $this->assertFalse($DB->record_exists('questionnaire_response', ['id' => $ridone]));
            $this->assertFalse($DB->record_exists('questionnaire_response', ['id' => $ridtwo]));
        }

        $events = array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof \mod_questionnaire\event\all_responses_deleted
        );
        $this->assertCount(1, $events);
    }

    /**
     * delete_all_responses throws couldnotdelresp when there are no responses to delete.
     */
    public function test_delete_all_responses_throws_when_empty(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        (new report_actions($questionnaire, $renderer))
            ->delete_all_responses(0, 0, []);
    }
}
