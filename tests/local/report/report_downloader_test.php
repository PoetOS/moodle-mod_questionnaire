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
 * Unit tests for mod_questionnaire\local\report\report_downloader.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\report;

use mod_questionnaire\output\reportpage;

/**
 * Unit tests for mod_questionnaire\local\report\report_downloader.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\report\report_downloader
 */
final class report_downloader_test extends \advanced_testcase {
    /**
     * show_download_options enforces the downloadresponses capability.
     */
    public function test_show_download_options_requires_capability(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());

        // A freshly-created student has no downloadresponses cap.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\required_capability_exception::class);
        (new report_downloader($questionnaire, $renderer))
            ->show_download_options(new reportpage(), 0, 0, [], 0);
    }

    /**
     * download_responses enforces the downloadresponses capability.
     */
    public function test_download_responses_requires_capability(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\required_capability_exception::class);
        (new report_downloader($questionnaire, $renderer))
            ->download_responses(new reportpage(), 0, 0);
    }

    /**
     * download_responses redirects to dwnpg when "email report" is selected but no
     * recipients are specified (allowemailreporting off, no roles, no extras).
     */
    public function test_download_responses_redirects_when_no_emails(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $qdg = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $qdg->create_and_fully_populate(1, 1, 1, 1);
        $questionnaires = $qdg->questionnaires();
        $questionnaire = reset($questionnaires);
        $PAGE->set_context($questionnaire->context());

        // Force the email branch with no recipients and no allowemailreporting config.
        $_POST['emailreport'] = 'send';
        set_config('allowemailreporting', 0, 'questionnaire');

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        try {
            (new report_downloader($questionnaire, $renderer))
                ->download_responses(new reportpage(), 0, 0);
        } finally {
            unset($_POST['emailreport']);
        }
    }
}
