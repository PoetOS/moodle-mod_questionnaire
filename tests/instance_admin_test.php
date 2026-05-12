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
 * Unit tests for mod_questionnaire\instance_admin.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\instance_admin.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\instance_admin
 */
final class instance_admin_test extends \advanced_testcase {
    /**
     * Build the formdata stdClass that mod_form normally produces for create.
     *
     * @param int $courseid
     * @param string $name
     * @return \stdClass
     */
    private function build_add_formdata(int $courseid, string $name = 'New survey'): \stdClass {
        $cm = $this->getDataGenerator()->create_module('questionnaire', [
            'course' => $courseid,
            'name' => 'Placeholder for cm',
        ]);
        // We only use the placeholder to extract a valid coursemodule id.
        $formdata = new \stdClass();
        $formdata->coursemodule = $cm->cmid;
        $formdata->course = $courseid;
        $formdata->name = $name;
        $formdata->intro = '';
        $formdata->introformat = FORMAT_HTML;
        $formdata->qtype = 0;
        $formdata->respondenttype = 'fullname';
        $formdata->respeligible = 'all';
        $formdata->respview = 0;
        $formdata->notifications = 0;
        $formdata->opendate = 0;
        $formdata->closedate = 0;
        $formdata->resume = 0;
        $formdata->navigate = 0;
        $formdata->grade = 0;
        $formdata->sid = 0;
        $formdata->create = 'new-0';
        $formdata->autonum = 3;
        $formdata->progressbar = 0;
        $formdata->removeafter = 0;
        $formdata->completionsubmit = 0;
        return $formdata;
    }

    /**
     * add_instance() creates a survey row, a questionnaire row, and links them.
     */
    public function test_add_instance_creates_survey_and_questionnaire(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $formdata = $this->build_add_formdata($course->id, 'My survey');

        $id = instance_admin::add_instance($formdata);
        $this->assertNotFalse($id);

        $row = $DB->get_record('questionnaire', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('My survey', $row->name);
        $this->assertGreaterThan(0, (int) $row->sid);
        $this->assertTrue($DB->record_exists('questionnaire_survey', ['id' => $row->sid]));
    }

    /**
     * add_instance() with opendate + closedate set creates a calendar event for the instance.
     */
    public function test_add_instance_creates_calendar_event(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $formdata = $this->build_add_formdata($course->id, 'With dates');
        $formdata->opendate = time() + 3600;
        $formdata->closedate = time() + (2 * HOURSECS);

        $id = instance_admin::add_instance($formdata);

        $events = $DB->get_records('event', [
            'modulename' => 'questionnaire',
            'instance' => $id,
        ]);
        $this->assertNotEmpty($events);
    }

    /**
     * update_instance() renames the questionnaire and syncs the survey realm.
     */
    public function test_update_instance_renames(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id, 'name' => 'Original']);

        $data = new \stdClass();
        $data->instance = $questionnaire->id();
        $data->coursemodule = $questionnaire->coursemodule()->id;
        $data->sid = $questionnaire->surveyid();
        $data->realm = 'private';
        $data->name = 'Renamed';
        $data->intro = '';
        $data->introformat = FORMAT_HTML;
        $data->qtype = 0;
        $data->respondenttype = 'fullname';
        $data->respeligible = 'all';
        $data->respview = 0;
        $data->notifications = 0;
        $data->opendate = 0;
        $data->closedate = 0;
        $data->resume = 0;
        $data->navigate = 0;
        $data->grade = 0;
        $data->completionsubmit = 0;
        $data->autonum = 3;
        $data->progressbar = 0;
        $data->removeafter = 0;

        $this->assertTrue(instance_admin::update_instance($data));

        $this->assertSame('Renamed', $DB->get_field('questionnaire', 'name', ['id' => $questionnaire->id()]));
    }

    /**
     * delete_instance() removes the row, its responses, and the survey content when owned by the course.
     */
    public function test_delete_instance_removes_owned_survey(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        // Insert a stray response.
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $surveyid = $questionnaire->surveyid();
        $this->assertTrue(instance_admin::delete_instance($questionnaire->id()));

        $this->assertFalse($DB->record_exists('questionnaire', ['id' => $questionnaire->id()]));
        $this->assertFalse($DB->record_exists('questionnaire_survey', ['id' => $surveyid]));
        $this->assertEquals(0, $DB->count_records('questionnaire_response', ['questionnaireid' => $questionnaire->id()]));
    }

    /**
     * delete_instance() returns false when the id does not match any row.
     */
    public function test_delete_instance_missing_returns_false(): void {
        $this->resetAfterTest();
        $this->assertFalse(instance_admin::delete_instance(99999));
    }

    /**
     * set_events() replaces existing calendar events for the instance.
     */
    public function test_set_events_replaces_existing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $data = new \stdClass();
        $data->id = $questionnaire->id();
        $data->course = $course->id;
        $data->name = 'q';
        $data->opendate = time() + 3600;
        $data->closedate = time() + (2 * HOURSECS);
        $data->visible = 1;

        instance_admin::set_events($data);
        $first = $DB->count_records('event', ['modulename' => 'questionnaire', 'instance' => $questionnaire->id()]);
        $this->assertGreaterThan(0, $first);

        // Calling again should not double the count — old events get deleted first.
        instance_admin::set_events($data);
        $second = $DB->count_records('event', ['modulename' => 'questionnaire', 'instance' => $questionnaire->id()]);
        $this->assertSame($first, $second);
    }

    /**
     * reset_userdata() deletes all responses for course questionnaires when reset is requested.
     */
    public function test_reset_userdata_deletes_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $data = (object)[
            'courseid' => $course->id,
            'reset_questionnaire' => 1,
        ];
        $status = instance_admin::reset_userdata($data);
        $this->assertNotEmpty($status);
        $this->assertEquals(0, $DB->count_records('questionnaire_response', ['questionnaireid' => $questionnaire->id()]));
    }

    /**
     * reset_userdata() with the flag off does nothing and returns an empty status array.
     */
    public function test_reset_userdata_no_op_when_flag_off(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $questionnaire->id(),
            'userid' => 2,
            'submitted' => time(),
            'complete' => 'y',
            'grade' => 0,
        ]);

        $status = instance_admin::reset_userdata((object)['courseid' => $course->id]);
        $this->assertSame([], $status);
        $this->assertEquals(1, $DB->count_records('questionnaire_response', ['questionnaireid' => $questionnaire->id()]));
    }
}
