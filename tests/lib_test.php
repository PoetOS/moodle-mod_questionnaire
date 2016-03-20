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
 * PHPUnit questionnaire generator tests
 *
 * @package    mod_questionnaire
 * @copyright  2015 Mike Churchward (mike@churchward.ca)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

/**
 * Unit tests for {@link questionnaire_lib_testcase}.
 * @group mod_questionnaire
 */
class mod_questionnaire_lib_testcase extends advanced_testcase {
    public function test_questionnaire_supports() {
        $this->assertTrue(questionnaire_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertFalse(questionnaire_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertTrue(questionnaire_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertFalse(questionnaire_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(questionnaire_supports(FEATURE_GRADE_OUTCOMES));
        $this->assertTrue(questionnaire_supports(FEATURE_GROUPINGS));
        $this->assertTrue(questionnaire_supports(FEATURE_GROUPMEMBERSONLY));
        $this->assertTrue(questionnaire_supports(FEATURE_GROUPS));
        $this->assertTrue(questionnaire_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(questionnaire_supports(FEATURE_SHOW_DESCRIPTION));
    }

    public function test_questionnaire_get_extra_capabilities() {
        $caps = questionnaire_get_extra_capabilities();
        $this->assertInternalType('array', $caps);
        $this->assertEquals(1, count($caps));
        $this->assertEquals('moodle/site:accessallgroups', reset($caps));
    }

    public function test_add_instance() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');

        // Change all the default values.
        $questdata = new stdClass();
        $questdata->course = $course->id;
        $questdata->name = 'Test questionnaire';
        $questdata->intro = 'Intro to test questionnaire.';
        $questdata->introformat = FORMAT_HTML;
        $questdata->qtype = 1;
        $questdata->respondenttype = 'anonymous';
        $questdata->resp_eligible = 'none';
        $questdata->resp_view = 2;
        $questdata->opendate = 99;
        $questdata->closedate = 50;
        $questdata->resume = 1;
        $questdata->navigate = 1;
        $questdata->grade = 100;
        $questdata->sid = 1;
        $questdata->timemodified = 3;
        $questdata->completionsubmit = 1;
        $questdata->autonum = 1;

        // mod::add_instance is called from the generator->create_instance function
        // (if not overridden) and doesn't need to be called on its own.
        $questionnaire = $generator->create_instance(clone $questdata);
        $this->assertNotEmpty($questionnaire);
        $this->assertTrue($questionnaire->id > 0);

        // Verify that all the specified data was added, and not the defaults.
        $questrecord = $DB->get_record('questionnaire', array('id' => $questionnaire->id));
        foreach ($questdata as $property => $value) {
            // 'timemodified' is set to the value of current time when added.
            if ($property == 'timemodified') {
                $this->assertTrue(($questrecord->$property > 3) && ($questrecord->$property <= time()));
            } else {
                $this->assertEquals($value, $questrecord->$property);
            }
        }
    }

    public function test_update_instance() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));

        $questionnaire->sid = 1;
        $qid = questionnaire_add_instance($questionnaire);
        $this->assertTrue($qid > 0);

        // Change all the default values.
        $questionnaire->qtype = 1;
        $questionnaire->respondenttype = 'anonymous';
        $questionnaire->resp_eligible = 'none';
        $questionnaire->resp_view = 2;
        $questionnaire->useopendate = true;
        $questionnaire->opendate = 99;
        $questionnaire->useclosedate = true;
        $questionnaire->closedate = 50;
        $questionnaire->resume = 1;
        $questionnaire->navigate = 1;
        $questionnaire->grade = 100;
        $questionnaire->timemodified = 3;
        $questionnaire->completionsubmit = 1;
        $questionnaire->autonum = 1;

        // Moodle update form passes "instance" instead of "id" to [mod]_update_instance.
        $questionnaire->instance = $qid;
        // Grade function needs the "cm" "idnumber" field.
        $questionnaire->cmidnumber = '';

        $this->assertTrue(questionnaire_update_instance($questionnaire));

        $questrecord = $DB->get_record('questionnaire', array('id' => $qid));
        $this->assertNotEmpty($questrecord);
        $this->assertEquals($questionnaire->qtype, $questrecord->qtype);
        $this->assertEquals($questionnaire->respondenttype, $questrecord->respondenttype);
        $this->assertEquals($questionnaire->resp_eligible, $questrecord->resp_eligible);
        $this->assertEquals($questionnaire->resp_view, $questrecord->resp_view);
        $this->assertEquals($questionnaire->opendate, $questrecord->opendate);
        $this->assertEquals($questionnaire->closedate, $questrecord->closedate);
        $this->assertEquals($questionnaire->resume, $questrecord->resume);
        $this->assertEquals($questionnaire->navigate, $questrecord->navigate);
        $this->assertEquals($questionnaire->grade, $questrecord->grade);
        $this->assertEquals($questionnaire->sid, $questrecord->sid);
        $this->assertEquals($questionnaire->timemodified, $questrecord->timemodified);
        $this->assertEquals($questionnaire->completionsubmit, $questrecord->completionsubmit);
        $this->assertEquals($questionnaire->autonum, $questrecord->autonum);
    }

    /*
     * Need to verify that delete_instance deletes all data associated with a questionnaire.
     *
     */
    public function test_delete_instance() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set up a new questionnaire.
        $questiondata = array();
        $questiondata['content'] = 'Enter yes or no';
        $questionnaire = $this->create_test_questionnaire(QUESYESNO, $questiondata);

        $question = reset($questionnaire->questions);

        // Add a response for the question.
        $userid = 1;
        $section = 1;
        $currentrid = 0;
        $_POST['q'.$question->id] = 'y';
        $responseid = $questionnaire->response_insert($question->survey_id, $section, $currentrid, $userid);
        questionnaire_record_submission($questionnaire, $userid, $responseid);

        // Confirm that expected records are in the database.
        $questionnaire = $DB->get_record('questionnaire', array('id' => $questionnaire->id));
        $this->assertInstanceOf('stdClass', $questionnaire);
        $survey = $DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid));
        $this->assertInstanceOf('stdClass', $survey);
        $questions = $DB->get_records('questionnaire_question', array('survey_id' => $survey->id));
        $this->assertCount(1, $questions);
        $responses = $DB->get_records('questionnaire_response', array('survey_id' => $survey->id));
        $this->assertCount(1, $responses);
        $attempts = $DB->get_records('questionnaire_attempts', array('qid' => $questionnaire->id));
        $this->assertCount(1, $attempts);
        $response = reset($responses);
        $this->assertCount(1, $DB->get_records('questionnaire_response_bool', array('response_id' => $response->id)));
        $this->assertTrue($DB->get_records('event', array("modulename" => 'questionnaire', "instance" => $questionnaire->id)) > 0);

        // Now delete it all.
        $this->assertTrue(questionnaire_delete_instance($questionnaire->id));
        $this->assertEmpty($DB->get_record('questionnaire', array('id' => $questionnaire->id)));
        $this->assertEmpty($DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid)));
        $this->assertEmpty($DB->get_records('questionnaire_question', array('survey_id' => $survey->id)));
        $this->assertEmpty($DB->get_records('questionnaire_response', array('survey_id' => $survey->id)));
        $this->assertEmpty($DB->get_records('questionnaire_attempts', array('qid' => $questionnaire->id)));
        $this->assertEmpty($DB->get_records('questionnaire_response_bool', array('response_id' => $response->id)));
        $this->assertEmpty($DB->get_records('event', array("modulename" => 'questionnaire', "instance" => $questionnaire->id)));
    }

    public function test_questionnaire_user_outline() {

    }

    public function test_questionnaire_user_complete() {

    }

    public function test_questionnaire_print_recent_activity() {

    }

    public function test_questionnaire_grades() {

    }

    public function test_questionnaire_get_user_grades() {

    }

    public function test_questionnaire_update_grades() {

    }

    public function test_questionnaire_grade_item_update() {

    }

    /**
     * Create a questionnaire with questions and response data for use in other tests.
     */
    public function create_test_questionnaire($qtype, $questiondata = array(), $choicedata = null) {
        $course = $this->getDataGenerator()->create_course();
        /** @var $generator mod_questionnaire_generator*/
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata['survey_id'] = $questionnaire->sid;
        $questiondata['name'] = isset($questiondata['name']) ? $questiondata['name'] : 'Q1';
        $questiondata['content'] = isset($questiondata['content']) ? $questiondata['content'] : 'Test content';
        $questiondata['type_id'] = $qtype;

        $generator->create_question($questionnaire, $questiondata, $choicedata);

        return $questionnaire;
    }
}
