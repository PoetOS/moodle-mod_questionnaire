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
 * Unit tests for the mod_questionnaire form_options helper.
 *
 * @package   mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

declare(strict_types=1);

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit tests for {@see form_options}.
 *
 * @covers \mod_questionnaire\form_options
 */
final class form_options_test extends \advanced_testcase {
    public function test_response_frequency_returns_five_options(): void {
        $this->resetAfterTest();
        $options = form_options::response_frequency();
        $this->assertIsArray($options);
        $this->assertCount(5, $options);
    }

    public function test_response_frequency_has_expected_keys(): void {
        $this->resetAfterTest();
        $options = form_options::response_frequency();
        $this->assertArrayHasKey(questionnaire::QTYPE_UNLIMITED, $options);
        $this->assertArrayHasKey(questionnaire::QTYPE_ONCE, $options);
        $this->assertArrayHasKey(questionnaire::QTYPE_DAILY, $options);
        $this->assertArrayHasKey(questionnaire::QTYPE_WEEKLY, $options);
        $this->assertArrayHasKey(questionnaire::QTYPE_MONTHLY, $options);
    }

    public function test_response_viewer_returns_four_options(): void {
        $this->resetAfterTest();
        $options = form_options::response_viewer();
        $this->assertIsArray($options);
        $this->assertCount(4, $options);
    }

    public function test_response_viewer_has_expected_keys(): void {
        $this->resetAfterTest();
        $options = form_options::response_viewer();
        $this->assertArrayHasKey(questionnaire::RESPVIEW_NEVER, $options);
        $this->assertArrayHasKey(questionnaire::RESPVIEW_WHENANSWERED, $options);
        $this->assertArrayHasKey(questionnaire::RESPVIEW_WHENCLOSED, $options);
        $this->assertArrayHasKey(questionnaire::RESPVIEW_ALWAYS, $options);
    }

    public function test_respondent_type_has_fullname_and_anonymous(): void {
        $options = form_options::respondent_type();
        $this->assertArrayHasKey('fullname', $options);
        $this->assertArrayHasKey('anonymous', $options);
    }

    public function test_realm_has_three_options(): void {
        $options = form_options::realm();
        $this->assertCount(3, $options);
        $this->assertArrayHasKey('private', $options);
        $this->assertArrayHasKey('public', $options);
        $this->assertArrayHasKey('template', $options);
    }

    public function test_auto_numbering_has_four_options(): void {
        $options = form_options::auto_numbering();
        $this->assertCount(4, $options);
        $this->assertArrayHasKey(0, $options);
        $this->assertArrayHasKey(3, $options);
    }

    public function test_editor_returns_expected_keys(): void {
        $context = \context_system::instance();
        $options = form_options::editor($context);
        $this->assertIsArray($options);
        $this->assertArrayHasKey('subdirs', $options);
        $this->assertArrayHasKey('maxbytes', $options);
        $this->assertArrayHasKey('maxfiles', $options);
        $this->assertArrayHasKey('context', $options);
        $this->assertArrayHasKey('noclean', $options);
        $this->assertArrayHasKey('trusttext', $options);
        $this->assertSame($context, $options['context']);
    }

    public function test_response_removal_returns_37_entries(): void {
        $this->resetAfterTest();
        $options = form_options::response_removal();
        $this->assertIsArray($options);
        $this->assertCount(37, $options);
        $this->assertArrayHasKey(0, $options);
    }
}
