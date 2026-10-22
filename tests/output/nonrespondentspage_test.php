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
 * Unit tests for mod_questionnaire\output\nonrespondentspage.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

use mod_questionnaire\local\report\nonrespondent_messenger;
use mod_questionnaire\questionnaire;
use moodle_url;

/**
 * Unit tests for mod_questionnaire\output\nonrespondentspage.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\nonrespondentspage
 */
final class nonrespondentspage_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

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
     * Create a course, a questionnaire and an editing teacher who is the current user.
     *
     * @param string $respondenttype 'fullname' or 'anonymous'.
     * @param bool $resume
     * @return questionnaire
     */
    private function build_questionnaire(string $respondenttype = 'fullname', bool $resume = false): questionnaire {
        global $DB, $PAGE;
        $this->course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = $generator->create_instance(['course' => $this->course->id]);
        $DB->update_record('questionnaire', (object)[
            'id' => $instance->id(),
            'respondenttype' => $respondenttype,
            'resume' => $resume ? 1 : 0,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $questionnaire = questionnaire::from_instanceid($instance->id());
        $PAGE->set_url('/mod/questionnaire/show_nonrespondents.php', ['id' => $questionnaire->coursemodule()->id]);
        return $questionnaire;
    }

    /**
     * With no non-respondents the page is just the empty state: no form.
     */
    public function test_empty_state(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();

        $data = (new nonrespondentspage($questionnaire, 0))->export_for_template($this->renderer());

        $this->assertTrue($data->empty);
        $this->assertFalse($data->canmessage);
        $this->assertNull($data->table);
    }

    /**
     * A named questionnaire shows the table, the select buttons, and the paging toggle.
     */
    public function test_named_questionnaire_with_table(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire('fullname', true);
        $table = new nonrespondents_table($questionnaire, new moodle_url('/mod/questionnaire/show_nonrespondents.php'));
        $table->setup();
        $toggle = new moodle_url('/mod/questionnaire/show_nonrespondents.php', ['showall' => 1]);

        $page = new nonrespondentspage($questionnaire, 3);
        $page->set_table($table, true, $toggle, 'Show all 3');
        $data = $page->export_for_template($this->renderer());

        $this->assertFalse($data->anonymous);
        $this->assertNotEmpty($data->table);
        $this->assertTrue($data->canmessage);
        $this->assertTrue($data->selectbuttons);
        $this->assertTrue($data->statusbuttons);
        $this->assertEquals(['url' => $toggle->out(false), 'label' => 'Show all 3'], $data->pagingtoggle);
        $this->assertEmpty($data->recipients);
    }

    /**
     * Status buttons need every non-respondent on the page.
     */
    public function test_status_buttons_hidden_when_paged(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire('fullname', true);
        $table = new nonrespondents_table($questionnaire, new moodle_url('/mod/questionnaire/show_nonrespondents.php'));
        $table->setup();

        $page = new nonrespondentspage($questionnaire, 30);
        $page->set_table($table, false);
        $data = $page->export_for_template($this->renderer());

        $this->assertTrue($data->selectbuttons);
        $this->assertFalse($data->statusbuttons);
        $this->assertNull($data->pagingtoggle);
    }

    /**
     * An anonymous questionnaire offers recipient radios with matching ids, status counts, and the redisplayed choice.
     */
    public function test_anonymous_recipient_options(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire('anonymous', true);

        $page = new nonrespondentspage($questionnaire, 5);
        $page->set_started_count(2);
        $page->set_submission('', '', nonrespondent_messenger::SELECT_ALL);
        $data = $page->export_for_template($this->renderer());

        $this->assertTrue($data->anonymous);
        $this->assertNull($data->table);
        $this->assertFalse($data->selectbuttons);
        $this->assertTrue($data->hasrecipients);
        $this->assertEquals(['none', 'all', 'started', 'notstarted'], array_column($data->recipients, 'value'));
        foreach ($data->recipients as $recipient) {
            $this->assertEquals('questionnaire_recipients_' . $recipient['value'], $recipient['id']);
            $this->assertEquals($recipient['value'] === 'all', $recipient['checked']);
        }
        $this->assertStringContainsString('(2)', $data->recipients[2]['label']);
        $this->assertStringContainsString('(3)', $data->recipients[3]['label']);
    }

    /**
     * Without save-and-resume, anonymous recipients are just none / all, and none is the default.
     */
    public function test_anonymous_without_resume(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire('anonymous', false);

        $data = (new nonrespondentspage($questionnaire, 5))->export_for_template($this->renderer());

        $this->assertEquals(['none', 'all'], array_column($data->recipients, 'value'));
        $this->assertTrue($data->recipients[0]['checked']);
    }

    /**
     * set_submission() redisplays the subject, message and error.
     */
    public function test_submission_is_redisplayed(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire();

        $page = new nonrespondentspage($questionnaire, 1);
        $page->set_submission('Reminder', '<p>Please</p>', '', 'Something is missing');
        $data = $page->export_for_template($this->renderer());

        $this->assertEquals('Reminder', $data->subject);
        $this->assertEquals('<p>Please</p>', $data->message);
        $this->assertEquals('Something is missing', $data->error);
    }

    /**
     * The rendered page labels each recipient radio correctly, so clicking a label selects its radio.
     */
    public function test_template_labels_match_radios(): void {
        $this->resetAfterTest();
        $questionnaire = $this->build_questionnaire('anonymous');

        $html = $this->renderer()->render(new nonrespondentspage($questionnaire, 2));

        $this->assertStringContainsString('id="questionnaire_recipients_all"', $html);
        $this->assertStringContainsString('for="questionnaire_recipients_all"', $html);
        $this->assertStringContainsString('<label for="questionnaire_subject"', $html);
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
    }
}
