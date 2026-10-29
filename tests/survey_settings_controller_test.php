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
 * Unit tests for mod_questionnaire\survey_settings_controller.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

/**
 * Unit tests for mod_questionnaire\survey_settings_controller.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\survey_settings_controller
 */
final class survey_settings_controller_test extends \advanced_testcase {
    /**
     * Build a course + questionnaire fixture.
     *
     * @return questionnaire
     */
    private function setup_questionnaire(): questionnaire {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        return $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
    }

    /**
     * Build the minimal formdata stdClass that the qsettings form returns from get_data().
     *
     * @param questionnaire $questionnaire
     * @param array $overrides
     * @return \stdClass
     */
    private function formdata(questionnaire $questionnaire, array $overrides = []): \stdClass {
        $defaults = [
            'name'       => $questionnaire->survey()->name(),
            'realm'      => $questionnaire->survey()->realm(),
            'title'      => $questionnaire->survey()->title(),
            'subtitle'   => 'Sub',
            'info'       => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => 'About this survey'],
            'thankspage' => 'https://example.org/done',
            'thankhead'  => 'Thanks!',
            'thankbody'  => ['itemid' => 0, 'format' => FORMAT_HTML, 'text' => 'Bye'],
            'email'      => 'alerts@example.org',
        ];
        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Asserts save() persists each settings field onto the survey row.
     */
    public function test_save_persists_form_fields(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        set_config('allowemailreporting', 1, 'questionnaire');
        $result = (new survey_settings_controller($questionnaire))->save($this->formdata($questionnaire, [
            'subtitle'  => 'New subtitle',
            'thankhead' => 'New thank head',
            'email'     => 'sup@example.org',
        ]));

        $this->assertSame($questionnaire->surveyid(), $result);
        $row = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->surveyid()]);
        $this->assertEquals('New subtitle', $row->subtitle);
        $this->assertEquals('New thank head', $row->thankhead);
        $this->assertEquals('sup@example.org', $row->email);
        $this->assertStringContainsString('About this survey', $row->info);
        $this->assertStringContainsString('Bye', $row->thankbody);
    }

    /**
     * Asserts save() throws when underlying update rejects (e.g. duplicate name).
     */
    public function test_save_throws_when_update_rejected(): void {
        $questionnaire = $this->setup_questionnaire();

        // Empty title is rejected by survey::update_settings().
        $this->expectException(\moodle_exception::class);
        (new survey_settings_controller($questionnaire))->save($this->formdata($questionnaire, [
            'title' => '',
        ]));
    }

    /**
     * Asserts save() leaves the survey row id untouched even when the form would otherwise
     * carry a misleading 'id' value. Hardened against the historical feedback.php bug.
     */
    public function test_save_does_not_corrupt_survey_id(): void {
        global $DB;

        $questionnaire = $this->setup_questionnaire();
        $surveyid = $questionnaire->surveyid();

        $formdata = $this->formdata($questionnaire);
        // Smuggle a stray id like the legacy form payload used to. survey_settings_controller
        // builds an explicit allowlist, so this must be ignored.
        $formdata->id = 999999;

        (new survey_settings_controller($questionnaire))->save($formdata);

        $this->assertTrue($DB->record_exists('questionnaire_survey', ['id' => $surveyid]));
    }

    /**
     * Log in as a new teacher of the questionnaire's course, optionally with the public/template capabilities.
     *
     * @param questionnaire $questionnaire
     * @param bool $cancreate Whether the teacher may create public and template surveys.
     */
    private function log_in_teacher(questionnaire $questionnaire, bool $cancreate): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($questionnaire->course(), 'editingteacher');
        if ($cancreate) {
            $roleid = $this->getDataGenerator()->create_role();
            assign_capability('mod/questionnaire:createpublic', CAP_ALLOW, $roleid, $questionnaire->context()->id);
            assign_capability('mod/questionnaire:createtemplates', CAP_ALLOW, $roleid, $questionnaire->context()->id);
            role_assign($roleid, $teacher->id, $questionnaire->context()->id);
        }
        $this->setUser($teacher);
    }

    /**
     * Data provider for the realm test: current realm, posted realm, whether the user may create shared surveys,
     * and the realm expected afterwards.
     *
     * @return array
     */
    public static function realm_provider(): array {
        return [
            'teacher cannot publish' => ['private', 'public', false, 'private'],
            'teacher cannot make a template' => ['private', 'template', false, 'private'],
            'teacher keeps an existing public realm' => ['public', 'public', false, 'public'],
            'teacher can make a survey private' => ['public', 'private', false, 'private'],
            'creator can publish' => ['private', 'public', true, 'public'],
            'creator can make a template' => ['private', 'template', true, 'template'],
            'unknown realm is ignored' => ['private', 'bogus', true, 'private'],
        ];
    }

    /**
     * save() only accepts a posted realm the user may set; anything else keeps the current realm.
     *
     * @dataProvider realm_provider
     * @param string $current
     * @param string $posted
     * @param bool $cancreate
     * @param string $expected
     */
    public function test_save_only_accepts_allowed_realm(string $current, string $posted, bool $cancreate, string $expected): void {
        global $DB;
        $questionnaire = $this->setup_questionnaire();
        $DB->set_field('questionnaire_survey', 'realm', $current, ['id' => $questionnaire->surveyid()]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $this->log_in_teacher($questionnaire, $cancreate);

        (new survey_settings_controller($questionnaire))->save($this->formdata($questionnaire, ['realm' => $posted]));

        $realm = $DB->get_field('questionnaire_survey', 'realm', ['id' => $questionnaire->surveyid()]);
        $this->assertSame($expected, $realm);
    }

    /**
     * save() never changes the survey's name or owning course, whatever is posted.
     */
    public function test_save_ignores_posted_name_and_courseid(): void {
        global $DB;
        $questionnaire = $this->setup_questionnaire();
        $before = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->surveyid()]);

        (new survey_settings_controller($questionnaire))->save($this->formdata($questionnaire, [
            'name' => 'Renamed',
            'courseid' => $this->getDataGenerator()->create_course()->id,
        ]));

        $after = $DB->get_record('questionnaire_survey', ['id' => $questionnaire->surveyid()]);
        $this->assertEquals($before->name, $after->name);
        $this->assertEquals($before->courseid, $after->courseid);
    }

    /**
     * save() ignores a posted email address when the site does not allow email reporting.
     */
    public function test_save_ignores_email_when_email_reporting_disabled(): void {
        global $DB;
        $questionnaire = $this->setup_questionnaire();
        set_config('allowemailreporting', 0, 'questionnaire');
        $DB->set_field('questionnaire_survey', 'email', 'kept@example.org', ['id' => $questionnaire->surveyid()]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $formdata = $this->formdata($questionnaire, ['email' => 'outside@example.com']);
        (new survey_settings_controller($questionnaire))->save($formdata);

        $email = $DB->get_field('questionnaire_survey', 'email', ['id' => $questionnaire->surveyid()]);
        $this->assertEquals('kept@example.org', $email);
    }
}
