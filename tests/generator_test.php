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

class mod_questionnaire_generator_testcase extends advanced_testcase {
    public function test_create_instance() {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($DB->record_exists('questionnaire', array('course' => $course->id)));

        /** @var mod_questionnaire_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $this->assertInstanceOf('mod_questionnaire_generator', $generator);
        $this->assertEquals('questionnaire', $generator->get_modulename());

        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $this->assertEquals(1, $DB->count_records('questionnaire'));

        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);
        $this->assertEquals($questionnaire->id, $cm->instance);
        $this->assertEquals('questionnaire', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($questionnaire->cmid, $context->instanceid);

        $survey = $DB->get_record('questionnaire_survey', array('id' => $questionnaire->sid));
        $this->assertEquals($survey->id, $questionnaire->sid);
        $this->assertEquals($questionnaire->name, $survey->name);
        $this->assertEquals($questionnaire->name, $survey->title);

        // Should test creating a public questionnaire, template questionnaire and creating one from a template.

        // Should test event creation if open dates and close dates are specified?
    }

    public function test_create_content() {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, false);

        $newcontent = array(
            'title'         => 'New title',
            'email'         => 'test@email.com',
            'subtitle'      => 'New subtitle',
            'info'          => 'New info',
            'thanks_page'   => 'http://thankurl.com',
            'thank_head'    => 'New thank header',
            'thank_body'    => 'New thank body',
        );
        $sid = $generator->create_content($questionnaire, $newcontent);
        $this->assertEquals($sid, $questionnaire->sid);
        $survey = $DB->get_record('questionnaire_survey', array('id' => $sid));
        foreach ($newcontent as $name => $value) {
            $this->assertEquals($survey->{$name}, $value);
        }
    }

    public function test_create_question_checkbox() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Check one');
        $choicedata = array('One' => 1, 'Two' => 2, 'Three' => 3);
        $question = $generator->create_question_checkbox($questionnaire->sid, $questiondata, $choicedata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESCHECK);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);
        $this->assertEquals('array', gettype($question->choices));
        $this->assertEquals(3, count($question->choices));
        reset($choicedata);
        foreach ($question->choices as $cid => $choice) {
            $this->assertTrue($DB->record_exists('questionnaire_quest_choice', array('id' => $cid)));
            list($content, $value) = each($choicedata);
            $this->assertEquals($choice->content, $content);
            $this->assertEquals($choice->value, $value);
        }

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
        $this->assertEquals(3, count($questionnaire->questions[$question->id]->choices));
    }

    public function test_create_question_date() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Enter a date');
        $question = $generator->create_question_date($questionnaire->sid, $questiondata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESDATE);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
    }

    public function test_create_question_dropdown() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Select one');
        $choicedata = array('One' => 1, 'Two' => 2, 'Three' => 3);
        $question = $generator->create_question_dropdown($questionnaire->sid, $questiondata, $choicedata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESDROP);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);
        $this->assertEquals('array', gettype($question->choices));
        $this->assertEquals(3, count($question->choices));
        reset($choicedata);
        foreach ($question->choices as $cid => $choice) {
            $this->assertTrue($DB->record_exists('questionnaire_quest_choice', array('id' => $cid)));
            list($content, $value) = each($choicedata);
            $this->assertEquals($choice->content, $content);
            $this->assertEquals($choice->value, $value);
        }

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
        $this->assertEquals(3, count($questionnaire->questions[$question->id]->choices));
    }

    public function test_create_question_essay() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Enter an essay');
        $question = $generator->create_question_essay($questionnaire->sid, $questiondata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESESSAY);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
    }

    public function test_create_question_sectiontext() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('content' => 'This a section label.');
        $question = $generator->create_question_sectiontext($questionnaire->sid, $questiondata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->type_id, QUESSECTIONTEXT);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->content, $questiondata['content']);

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
    }

    public function test_create_question_numeric() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Enter a number');
        $question = $generator->create_question_numeric($questionnaire->sid, $questiondata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESNUMERIC);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
    }

    public function test_create_question_radiobuttons() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Choose one');
        $choicedata = array('One' => 1, 'Two' => 2, 'Three' => 3);
        $question = $generator->create_question_radiobuttons($questionnaire->sid, $questiondata, $choicedata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESRADIO);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);
        $this->assertEquals('array', gettype($question->choices));
        $this->assertEquals(3, count($question->choices));
        reset($choicedata);
        foreach ($question->choices as $cid => $choice) {
            $this->assertTrue($DB->record_exists('questionnaire_quest_choice', array('id' => $cid)));
            list($content, $value) = each($choicedata);
            $this->assertEquals($choice->content, $content);
            $this->assertEquals($choice->value, $value);
        }

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
        $this->assertEquals(3, count($questionnaire->questions[$question->id]->choices));
    }

    public function test_create_question_ratescale() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Rate these');
        $choicedata = array('One' => 1, 'Two' => 2, 'Three' => 3);
        $question = $generator->create_question_ratescale($questionnaire->sid, $questiondata, $choicedata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESRATE);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);
        $this->assertEquals('array', gettype($question->choices));
        $this->assertEquals(3, count($question->choices));
        reset($choicedata);
        foreach ($question->choices as $cid => $choice) {
            $this->assertTrue($DB->record_exists('questionnaire_quest_choice', array('id' => $cid)));
            list($content, $value) = each($choicedata);
            $this->assertEquals($choice->content, $content);
            $this->assertEquals($choice->value, $value);
        }

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
        $this->assertEquals(3, count($questionnaire->questions[$question->id]->choices));
    }

    public function test_create_question_textbox() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Enter some text.');
        $question = $generator->create_question_textbox($questionnaire->sid, $questiondata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESTEXT);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
    }

    public function test_create_question_yesno() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);

        $questiondata = array('name' => 'Q1', 'content' => 'Enter yes or no.');
        $question = $generator->create_question_yesno($questionnaire->sid, $questiondata);
        $this->assertInstanceOf('questionnaire_question', $question);
        $this->assertTrue($question->id > 0);
        $this->assertEquals($question->survey_id, $questionnaire->sid);
        $this->assertEquals($question->type_id, QUESYESNO);
        $this->assertEquals($question->name, $questiondata['name']);
        $this->assertEquals($question->content, $questiondata['content']);

        // Questionnaire object should now have question record(s).
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm, true);
        $this->assertTrue($DB->record_exists('questionnaire_question', array('id' => $question->id)));
        $this->assertEquals('array', gettype($questionnaire->questions));
        $this->assertTrue(array_key_exists($question->id, $questionnaire->questions));
        $this->assertEquals(1, count($questionnaire->questions));
    }
}