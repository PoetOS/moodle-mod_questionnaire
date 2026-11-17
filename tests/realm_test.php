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

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

/**
 * Tests which questionnaire types a user may set in the questionnaire settings.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class realm_test extends \advanced_testcase {
    /**
     * Requested types, with the survey's current type and the user's create capabilities.
     *
     * @return array
     */
    public static function realm_provider(): array {
        return [
            'private, no capabilities' => ['private', 'private', false, false, 'private'],
            'public without the capability' => ['private', 'public', false, true, 'private'],
            'public with the capability' => ['private', 'public', true, false, 'public'],
            'template without the capability' => ['private', 'template', true, false, 'private'],
            'template with the capability' => ['private', 'template', false, true, 'template'],
            'unknown type' => ['private', 'other', true, true, 'private'],
            'keep the current public type' => ['public', 'public', false, false, 'public'],
            'keep the current template type' => ['template', 'template', false, false, 'template'],
            'make a public survey private' => ['public', 'private', false, false, 'private'],
            'public survey to template without the capability' => ['public', 'template', true, false, 'public'],
        ];
    }

    /**
     * Test allowed_realm.
     *
     * @dataProvider realm_provider
     * @covers \questionnaire::allowed_realm
     * @param string $current The survey's current type.
     * @param string $requested The type in the settings form.
     * @param bool $createpublic Whether the user may create public questionnaires.
     * @param bool $createtemplates Whether the user may create templates.
     * @param string $expected
     */
    public function test_allowed_realm(
        string $current,
        string $requested,
        bool $createpublic,
        bool $createtemplates,
        string $expected
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $questionnaire->survey->realm = $current;
        $questionnaire->capabilities = (object)['createpublic' => $createpublic, 'createtemplates' => $createtemplates];

        $this->assertSame($expected, $questionnaire->allowed_realm($requested));
    }
}
