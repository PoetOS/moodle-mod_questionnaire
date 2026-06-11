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
 * Unit tests for mod_questionnaire\report_viewer.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\output\reportpage;

/**
 * Unit tests for mod_questionnaire\report_viewer.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\report_viewer
 */
final class report_viewer_test extends \advanced_testcase {
    /**
     * Build a course + plain questionnaire and return both the domain object
     * and a moodle_url pointing at the report page.
     *
     * @return array [questionnaire, moodle_url]
     */
    private function setup_fixture(): array {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());
        $url = new \moodle_url('/mod/questionnaire/report.php');

        return [$questionnaire, $url];
    }

    /**
     * Initialise the SESSION->questionnaire bucket that callers (report.php) set
     * up before invoking the controller. Call this AFTER setUser() — switching
     * users resets the session.
     */
    private function init_session(): void {
        global $SESSION;
        if (!isset($SESSION->questionnaire) || !is_object($SESSION->questionnaire)) {
            $SESSION->questionnaire = new \stdClass();
        }
        $SESSION->questionnaire->current_tab = '';
    }

    /**
     * Default response-status map used by the report.php caller (mirrors what the
     * production entry script supplies).
     *
     * @return array
     */
    private function default_responsestatus(): array {
        return [
            'y' => get_string('fullsubmissions', 'questionnaire'),
            '0' => get_string('allresponses', 'questionnaire'),
            'n' => get_string('responsesnotsubmitted', 'questionnaire'),
        ];
    }

    /**
     * view_all_responses() throws nopermissions when the user has neither
     * readallresponses nor readallresponseanytime.
     */
    public function test_view_all_responses_requires_capability(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $url] = $this->setup_fixture();

        // Use an unenrolled user — no role assignments means no caps.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->init_session();

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/permission|nopermissions/i');
        ob_start();
        try {
            (new report_viewer($questionnaire, $renderer))->view_all_responses(
                new reportpage(),
                'vall',
                'html',
                $url,
                0,
                0,
                [],
                [],
                'default',
                '0',
                $this->default_responsestatus(),
                false
            );
        } finally {
            ob_end_clean();
        }
    }

    /**
     * view_individual_response() throws surveyowner when the survey is owned by
     * a different course than the questionnaire instance.
     */
    public function test_view_individual_response_throws_when_survey_not_owned_by_course(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $url] = $this->setup_fixture();
        $this->init_session();

        // Point the survey at a different course so owning_courseid() != questionnaire->course->id.
        $othercourse = $this->getDataGenerator()->create_course();
        $DB->set_field(
            'questionnaire_survey',
            'courseid',
            $othercourse->id,
            ['id' => $questionnaire->surveyid()]
        );
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/owner|surveyowner/i');
        (new report_viewer($questionnaire, $renderer))->view_individual_response(
            new reportpage(),
            'html',
            $url,
            0,
            0,
            [],
            false,
            false,
            false,
            false,
            '0',
            $this->default_responsestatus()
        );
    }
}
