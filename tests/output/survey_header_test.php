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
 * Unit tests for mod_questionnaire\output\survey_header.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\questionnaire;
use moodle_url;

/**
 * Unit tests for mod_questionnaire\output\survey_header.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\survey_header
 */
final class survey_header_test extends \advanced_testcase {
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
     * An empty header exports every field as null.
     */
    public function test_empty_header_exports_nulls(): void {
        $data = (new survey_header())->export_for_template($this->renderer());

        $this->assertNull($data->title);
        $this->assertNull($data->subtitle);
        $this->assertNull($data->info);
        $this->assertNull($data->respondent);
        $this->assertNull($data->printblank);
    }

    /**
     * Strings passed to the constructor are exported unchanged.
     */
    public function test_constructor_strings_are_exported(): void {
        $data = (new survey_header('Title', 'Subtitle', '<p>Info</p>'))->export_for_template($this->renderer());

        $this->assertEquals('Title', $data->title);
        $this->assertEquals('Subtitle', $data->subtitle);
        $this->assertEquals('<p>Info</p>', $data->info);
    }

    /**
     * for_survey() formats the survey's own title, subtitle and information.
     */
    public function test_for_survey_uses_survey_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = $generator->create_instance(['course' => $course->id]);
        $DB->update_record('questionnaire_survey', (object)[
            'id' => $instance->surveyid(),
            'title' => 'Tom & Jerry',
            'subtitle' => 'Episode one',
            'info' => '<p>Read carefully</p>',
        ]);
        $questionnaire = questionnaire::from_instanceid($instance->id());

        $data = survey_header::for_survey($questionnaire)->export_for_template($this->renderer());

        $this->assertEquals(format_string('Tom & Jerry'), $data->title);
        $this->assertEquals('Episode one', $data->subtitle);
        $this->assertStringContainsString('Read carefully', $data->info);
    }

    /**
     * for_survey() leaves blank survey fields out.
     */
    public function test_for_survey_omits_blank_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = $generator->create_instance(['course' => $course->id]);
        $DB->update_record('questionnaire_survey', (object)['id' => $instance->surveyid(), 'title' => '', 'subtitle' => '',
            'info' => '']);
        $questionnaire = questionnaire::from_instanceid($instance->id());

        $data = survey_header::for_survey($questionnaire)->export_for_template($this->renderer());

        $this->assertNull($data->title);
        $this->assertNull($data->subtitle);
        $this->assertNull($data->info);
    }

    /**
     * set_respondent() exports the respondent details, formats the submission time, and renders the print icon.
     */
    public function test_set_respondent_exports_details(): void {
        $this->resetAfterTest();
        $header = new survey_header();
        $submitted = 1790000000;
        $header->set_respondent(
            'Student One',
            'Course 1',
            'Group: A',
            $submitted,
            new moodle_url('/mod/questionnaire/report.php', ['rid' => 5, 'target' => 'print'])
        );

        $respondent = $header->export_for_template($this->renderer())->respondent;

        $this->assertEquals('Student One', $respondent->name);
        $this->assertEquals('Course 1', $respondent->course);
        $this->assertEquals('Group: A', $respondent->groups);
        $this->assertEquals(userdate($submitted), $respondent->submitted);
        $this->assertStringContainsString('target=print', $respondent->printlink);
        $this->assertObjectNotHasProperty('printurl', $respondent);
    }

    /**
     * set_respondent() with only a name leaves the optional details and print icon out.
     */
    public function test_set_respondent_name_only(): void {
        $header = new survey_header();
        $header->set_respondent('- Anonymous -');

        $respondent = $header->export_for_template($this->renderer())->respondent;

        $this->assertEquals('- Anonymous -', $respondent->name);
        $this->assertNull($respondent->course);
        $this->assertNull($respondent->groups);
        $this->assertNull($respondent->submitted);
        $this->assertNull($respondent->printlink);
    }

    /**
     * set_print_blank() exports a rendered link to the given URL.
     */
    public function test_set_print_blank_renders_link(): void {
        $this->resetAfterTest();
        $header = new survey_header();
        $header->set_print_blank(new moodle_url('/mod/questionnaire/print.php', ['qid' => 3]));

        $printblank = $header->export_for_template($this->renderer())->printblank;

        $this->assertStringContainsString('/mod/questionnaire/print.php?qid=3', $printblank);
        $this->assertStringContainsString('floatprinticon', $printblank);
        $this->assertStringContainsString(get_string('printblank', 'questionnaire'), $printblank);
    }

    /**
     * The partial renders the title, subtitle and information, and escapes the respondent's name.
     */
    public function test_template_renders_header(): void {
        $this->resetAfterTest();
        $header = new survey_header('Title', 'Subtitle', '<p>Info</p>');
        $header->set_respondent('<b>Bold</b> Name');
        $output = $this->renderer();

        $html = $output->render_from_template('mod_questionnaire/survey_header', $header->export_for_template($output));

        $this->assertStringContainsString('<h3 class="surveyTitle">Title</h3>', $html);
        $this->assertStringContainsString('<h4 class="surveySubTitle">Subtitle</h4>', $html);
        $this->assertStringContainsString('<div class="addInfo"><p>Info</p></div>', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt; Name', $html);
    }
}
