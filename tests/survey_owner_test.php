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
 * Tests that only the course that owns a survey can edit it.
 *
 * The plugin caches capabilities for the whole request, so each test runs on its own.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class survey_owner_test extends \advanced_testcase {
    /**
     * Make a public survey in one course and a questionnaire in another course that uses it, with the given user enrolled
     * in both courses.
     *
     * @param string $role The user's role in both courses.
     * @return \questionnaire[] [the owning questionnaire, the questionnaire that uses the survey]
     */
    private function make_shared_public_survey(string $role): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $ownercourse = $generator->create_course();
        $usercourse = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $ownercourse->id, $role);
        $generator->enrol_user($user->id, $usercourse->id, $role);
        $this->setUser($user);

        $qgenerator = $generator->get_plugin_generator('mod_questionnaire');
        $owner = $qgenerator->create_instance(['course' => $ownercourse->id]);
        $DB->set_field('questionnaire_survey', 'realm', 'public', ['id' => $owner->sid]);
        $created = $qgenerator->create_instance(['course' => $usercourse->id]);
        $DB->set_field('questionnaire', 'sid', $owner->sid, ['id' => $created->id]);

        return [
            new \questionnaire($ownercourse, $owner->cm, $owner->id),
            new \questionnaire($usercourse, $created->cm, $created->id),
        ];
    }

    /**
     * A teacher can edit a public survey's questions and settings in the course that owns it, but not in a course using it.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::can_edit_survey_questions
     * @covers \questionnaire::can_edit_survey_settings
     */
    public function test_can_edit_survey_as_teacher(): void {
        $this->resetAfterTest();
        [$owner, $user] = $this->make_shared_public_survey('editingteacher');

        $this->assertTrue($owner->can_edit_survey_questions());
        $this->assertTrue($owner->can_edit_survey_settings());
        $this->assertFalse($user->can_edit_survey_questions());
        $this->assertFalse($user->can_edit_survey_settings());
    }

    /**
     * Without the capabilities, owning the survey is not enough.
     *
     * @runInSeparateProcess
     * @covers \questionnaire::can_edit_survey_questions
     * @covers \questionnaire::can_edit_survey_settings
     */
    public function test_can_edit_survey_as_student(): void {
        $this->resetAfterTest();
        [$owner] = $this->make_shared_public_survey('student');

        $this->assertFalse($owner->can_edit_survey_questions());
        $this->assertFalse($owner->can_edit_survey_settings());
    }
}
