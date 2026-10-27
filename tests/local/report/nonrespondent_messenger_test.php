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
 * Unit tests for mod_questionnaire\local\report\nonrespondent_messenger.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\report;

use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\report\nonrespondent_messenger.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\report\nonrespondent_messenger
 */
final class nonrespondent_messenger_test extends \advanced_testcase {
    /** @var questionnaire */
    private questionnaire $questionnaire;

    /** @var int[] Student ids: [0] submitted, [1] started (saved), [2] not started, [3] started then submitted. */
    private array $students;

    /**
     * Build a questionnaire with four students in different states.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $instance = $generator->create_test_questionnaire($course, QUESYESNO, ['content' => 'Yes or no?'], []);
        $this->questionnaire = questionnaire::from_instanceid($instance->id());
        $this->students = [];
        for ($i = 0; $i < 4; $i++) {
            $this->students[] = (int)$this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        }
        $questions = $this->questionnaire->questions();
        $generator->generate_response($this->questionnaire, $questions, $this->students[0], true);
        $generator->generate_response($this->questionnaire, $questions, $this->students[1], false);
        $generator->generate_response($this->questionnaire, $questions, $this->students[3], false);
        $generator->generate_response($this->questionnaire, $questions, $this->students[3], true);
    }

    /**
     * The non-respondents of the fixture: everyone but the two who submitted.
     *
     * @return int[]
     */
    private function nonrespondents(): array {
        return [$this->students[1], $this->students[2]];
    }

    /**
     * started_userids() returns only non-respondents with a saved response, ignoring users who went on to submit.
     */
    public function test_started_userids_limited_to_nonrespondents(): void {
        $messenger = new nonrespondent_messenger($this->questionnaire);

        $this->assertEquals([$this->students[1]], $messenger->started_userids($this->nonrespondents()));
        $this->assertEquals([], $messenger->started_userids([]));
    }

    /**
     * recipients_for_selection() maps each anonymous choice to the right users.
     */
    public function test_recipients_for_selection(): void {
        $messenger = new nonrespondent_messenger($this->questionnaire);
        $nonrespondents = $this->nonrespondents();

        $this->assertEquals([], $messenger->recipients_for_selection(nonrespondent_messenger::SELECT_NONE, $nonrespondents));
        $this->assertEquals(
            $nonrespondents,
            $messenger->recipients_for_selection(nonrespondent_messenger::SELECT_ALL, $nonrespondents)
        );
        $this->assertEquals(
            [$this->students[1]],
            $messenger->recipients_for_selection(nonrespondent_messenger::SELECT_STARTED, $nonrespondents)
        );
        $this->assertEquals(
            [$this->students[2]],
            $messenger->recipients_for_selection(nonrespondent_messenger::SELECT_NOTSTARTED, $nonrespondents)
        );
        $this->assertEquals([], $messenger->recipients_for_selection('bogus', $nonrespondents));
    }

    /**
     * message_body() links to the questionnaire (with no stray space in the URL) and includes the message.
     */
    public function test_message_body_links_to_questionnaire(): void {
        global $PAGE;
        $messenger = new nonrespondent_messenger($this->questionnaire);

        $body = $messenger->message_body($PAGE->get_renderer('mod_questionnaire'), '<p>Please answer</p>');

        $url = new \moodle_url('/mod/questionnaire/view.php', ['id' => $this->questionnaire->coursemodule()->id]);
        $this->assertStringContainsString('href="' . $url->out() . '"', $body);
        $this->assertStringContainsString(format_string($this->questionnaire->name()), $body);
        $this->assertStringContainsString('<p>Please answer</p>', $body);
    }

    /**
     * send() delivers one message per recipient with the given subject and body.
     */
    public function test_send_delivers_to_each_recipient(): void {
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        $messenger = new nonrespondent_messenger($this->questionnaire);
        $teacher = $this->getDataGenerator()->create_user();

        $result = $messenger->send($this->nonrespondents(), 'Reminder', '<p>Body</p>', $teacher);

        $messages = $sink->get_messages();
        $sink->close();
        $this->assertTrue($result);
        $this->assertCount(2, $messages);
        $this->assertEqualsCanonicalizing($this->nonrespondents(), array_map(fn($m) => (int)$m->useridto, $messages));
        foreach ($messages as $message) {
            $this->assertEquals('Reminder', $message->subject);
            $this->assertEquals('<p>Body</p>', $message->fullmessagehtml);
            $this->assertEquals($teacher->id, $message->useridfrom);
        }
    }
}
