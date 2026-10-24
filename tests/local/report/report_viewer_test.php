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
 * Unit tests for mod_questionnaire\local\report\report_viewer.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\report;

use mod_questionnaire\output\page;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\report\report_viewer.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\report\report_viewer
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

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/permission|nopermissions/i');
        ob_start();
        try {
            (new report_viewer($questionnaire, $renderer))->view_all_responses(
                page::report(),
                'vall',
                'html',
                $url,
                0,
                0,
                [],
                [],
                'default',
                '0',
                $this->default_responsestatus()
            );
        } finally {
            ob_end_clean();
        }
    }

    /**
     * view_individual_response() refuses access when the survey is owned by a
     * different course than the questionnaire instance. Both view-all paths
     * require survey ownership, so the permission gate fires before the
     * surveyowner check.
     */
    public function test_view_individual_response_denied_when_survey_not_owned_by_course(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $url] = $this->setup_fixture();

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
        $this->expectExceptionMessageMatches('/permission|nopermissions/i');
        (new report_viewer($questionnaire, $renderer))->view_individual_response(
            page::report(),
            'html',
            $url,
            0,
            0,
            [],
            false,
            false,
            false,
            '0',
            $this->default_responsestatus()
        );
    }

    /**
     * view_individual_response() throws nopermissions for a user who can view
     * their own response but not all responses — report.php is not the
     * own-response path.
     */
    public function test_view_individual_response_requires_view_all_capability(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $url] = $this->setup_fixture();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $questionnaire->courseid(), 'student');
        $rid = $DB->insert_record('questionnaire_response', [
            'questionnaireid' => $questionnaire->id(),
            'submitted' => time(),
            'complete' => 'y',
            'userid' => $student->id,
        ]);

        $this->setUser($student);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/permission|nopermissions/i');
        (new report_viewer($questionnaire, $renderer))->view_individual_response(
            page::report(),
            'html',
            $url,
            0,
            0,
            [],
            $rid,
            false,
            true,
            '0',
            $this->default_responsestatus()
        );
    }

    /**
     * view_individual_response() throws nopermissions when the requested
     * response belongs to a different questionnaire, even for an admin.
     */
    public function test_view_individual_response_rejects_response_from_other_questionnaire(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$questionnaire, $url] = $this->setup_fixture();
        $otherquestionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $questionnaire->courseid()]);
        $foreignrid = $DB->insert_record('questionnaire_response', [
            'questionnaireid' => $otherquestionnaire->id(),
            'submitted' => time(),
            'complete' => 'y',
            'userid' => $this->getDataGenerator()->create_user()->id,
        ]);

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/permission|nopermissions/i');
        (new report_viewer($questionnaire, $renderer))->view_individual_response(
            page::report(),
            'html',
            $url,
            0,
            0,
            [],
            $foreignrid,
            false,
            true,
            '0',
            $this->default_responsestatus()
        );
    }
}
