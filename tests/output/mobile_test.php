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
 * Unit tests for the mobile WS response builder.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

/**
 * Tests for mod_questionnaire\output\mobile::mobile_view_activity().
 *
 * Asserts the shape of the WS response payload (templates / javascript /
 * otherdata / files). Acts as a guard against accidental changes to the
 * payload contract that the Moodle Mobile app consumes.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\mobile::mobile_view_activity
 */
final class mobile_test extends \advanced_testcase {
    /**
     * Build a questionnaire with a single yes/no question owned by the current user.
     *
     * @return \mod_questionnaire\questionnaire
     */
    private function build_questionnaire(): \mod_questionnaire\questionnaire {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        return $generator->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESYESNO,
            ['content' => 'Yes or no?'],
            []
        );
    }

    /**
     * Assert the invariant payload shape emitted by mobile_view_activity().
     *
     * @param array $result
     * @return void
     */
    private function assert_payload_shape(array $result): void {
        $this->assertArrayHasKey('templates', $result);
        $this->assertArrayHasKey('javascript', $result);
        $this->assertArrayHasKey('otherdata', $result);
        $this->assertArrayHasKey('files', $result);
        $this->assertIsArray($result['templates']);
        $this->assertCount(1, $result['templates']);
        $this->assertSame('main', $result['templates'][0]['id']);
        $this->assertIsString($result['templates'][0]['html']);
        $this->assertIsString($result['javascript']);
        $this->assertIsArray($result['otherdata']);
        $this->assertNull($result['files']);
    }

    /**
     * The default 'index' action returns a payload with the documented shape.
     */
    public function test_mobile_view_activity_index_returns_expected_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $questionnaire = $generator->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'index',
            'appversioncode' => 44000,
        ]);

        $this->assertIsArray($result);
        $this->assert_payload_shape($result);
    }

    /**
     * The 'respond' action returns the same payload shape when there is no in-progress response.
     */
    public function test_mobile_view_activity_respond_returns_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'respond',
            'pagenum'        => 1,
            'appversioncode' => 44000,
        ]);

        $this->assert_payload_shape($result);
    }

    /**
     * The 'review' action for a submitted response returns the expected payload shape.
     */
    public function test_mobile_view_activity_review_returns_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->course()->id, 'student');
        $submission = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->generate_response(
            $questionnaire,
            $questionnaire->questions(),
            (int) $student->id,
            true
        );
        $this->setUser($student);

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'review',
            'submissionid'   => $submission['id'],
            'appversioncode' => 44000,
        ]);

        $this->assert_payload_shape($result);
    }

    /**
     * An unknown action still returns the invariant payload shape (falls through the switch).
     */
    public function test_mobile_view_activity_unknown_action_returns_payload_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->build_questionnaire();

        $result = mobile::mobile_view_activity([
            'cmid'           => $questionnaire->coursemodule()->id,
            'action'         => 'no-such-action',
            'appversioncode' => 44000,
        ]);

        $this->assert_payload_shape($result);
    }
}
