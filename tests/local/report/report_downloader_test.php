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

use mod_questionnaire\output\page;

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
            ->show_download_options(page::report(), 0, 0, [], 0);
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
            ->download_responses(page::report(), 0, 0, new download_options());
    }

    /**
     * In an anonymous questionnaire, neither download page accepts a filter on another user's responses.
     */
    public function test_anonymous_questionnaire_refuses_other_user_filter(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $questionnaire = $this->anonymous_questionnaire();
        $student = $this->getDataGenerator()->create_and_enrol($questionnaire->course(), 'student');
        $downloader = new report_downloader($questionnaire, $PAGE->get_renderer('mod_questionnaire'));

        foreach (['show_download_options', 'download_responses'] as $method) {
            try {
                if ($method === 'show_download_options') {
                    $downloader->show_download_options(page::report(), 0, 0, [], $student->id);
                } else {
                    $downloader->download_responses(page::report(), 0, $student->id, new download_options('csv'));
                }
                $this->fail("{$method} should refuse another user's filter.");
            } catch (\moodle_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode, $method);
            }
        }
    }

    /**
     * In an anonymous questionnaire, a user may still download their own responses.
     */
    public function test_anonymous_questionnaire_allows_own_user_filter(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('allowemailreporting', 0, 'questionnaire');

        $questionnaire = $this->anonymous_questionnaire();
        try {
            (new report_downloader($questionnaire, $PAGE->get_renderer('mod_questionnaire')))
                ->download_responses(page::report(), 0, $USER->id, new download_options('csv', emailreport: true));
            $this->fail('Expected a redirect back to the download options.');
        } catch (\moodle_exception $e) {
            // The redirect for having no email recipients comes after the filter check.
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
    }

    /**
     * In a named questionnaire, the user filter is not restricted.
     */
    public function test_named_questionnaire_allows_other_user_filter(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('allowemailreporting', 0, 'questionnaire');

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id, 'respondenttype' => 'fullname']);
        $PAGE->set_context($questionnaire->context());
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        try {
            (new report_downloader($questionnaire, $PAGE->get_renderer('mod_questionnaire')))
                ->download_responses(page::report(), 0, $student->id, new download_options('csv', emailreport: true));
            $this->fail('Expected a redirect back to the download options.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
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
        set_config('allowemailreporting', 0, 'questionnaire');

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $this->expectException(\moodle_exception::class);
        (new report_downloader($questionnaire, $renderer))
            ->download_responses(page::report(), 0, 0, new download_options('csv', emailreport: true));
    }

    /**
     * download_responses redirects to dwnpg when the roles option finds nobody to notify and no extra
     * addresses are given, rather than emailing a blank address.
     */
    public function test_download_responses_redirects_when_roles_find_no_one(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $qdg = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $qdg->create_and_fully_populate(1, 1, 1, 1);
        $questionnaires = $qdg->questionnaires();
        $questionnaire = reset($questionnaires);
        $PAGE->set_context($questionnaire->context());
        set_config('allowemailreporting', 1, 'questionnaire');
        $sink = $this->redirectEmails();

        $renderer = $PAGE->get_renderer('mod_questionnaire');
        try {
            (new report_downloader($questionnaire, $renderer))->download_responses(
                page::report(),
                0,
                0,
                new download_options('csv', emailreport: true, emailroles: true, emailextra: ' ')
            );
            $this->fail('Expected a redirect back to the download options.');
        } catch (\moodle_exception $e) {
            // Under PHPUnit, redirect() throws; before the fix this path called exit() instead.
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
        $this->assertSame(0, $sink->count());
    }

    /**
     * stream_and_email() emails the dataformat file to each user, redirects, and cleans up
     * the temp file. Invoked via reflection because the method is private.
     *
     * Runs in a separate process so the test runner's prior output doesn't trip the
     * header_remove() / send_http_headers() calls.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_stream_and_email_dispatches_attachment_and_redirects(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()
            ->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id]);
        $PAGE->set_context($questionnaire->context());
        $renderer = $PAGE->get_renderer('mod_questionnaire');
        $downloader = new report_downloader($questionnaire, $renderer);

        $user = $this->getDataGenerator()->create_user(['email' => 'rcpt@example.org']);

        $sink = $this->redirectEmails();

        // Another export's file at the old shared path must not be overwritten or removed.
        $sharedpath = make_temp_directory('mod_questionnaire') . '/qreport.csv';
        file_put_contents($sharedpath, 'another export');

        // A plain array, as download_responses() passes from generate_csv().
        $rows = [
            ['col1' => 'row1a', 'col2' => 'row1b'],
            ['col1' => 'row2a', 'col2' => 'row2b'],
        ];

        $method = new \ReflectionMethod($downloader, 'stream_and_email');
        $method->setAccessible(true);

        ob_start();
        try {
            $method->invoke(
                $downloader,
                'qreport',
                'csv',
                ['col1' => 'Col 1', 'col2' => 'Col 2'],
                $rows,
                [$user],
                ['extra@example.org'],
                new \moodle_url('/mod/questionnaire/report.php', ['instance' => $questionnaire->id()])
            );
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $messages = $sink->get_messages();
        $sink->close();

        // One email per enrolled user + one per extra address.
        $this->assertCount(2, $messages);
        $recipients = array_map(fn($m) => $m->to, $messages);
        $this->assertContains('rcpt@example.org', $recipients);
        $this->assertContains('extra@example.org', $recipients);

        // Redirect message rendered (containing the configured "emailssent" lang string).
        $this->assertStringContainsString(get_string('emailssent', 'questionnaire'), $output);

        // The export was written elsewhere, leaving the shared temp directory alone.
        $this->assertSame('another export', file_get_contents($sharedpath));
        unlink($sharedpath);
    }

    /**
     * An anonymous questionnaire in a new course, with the page context set to it.
     *
     * @return \mod_questionnaire\questionnaire
     */
    private function anonymous_questionnaire(): \mod_questionnaire\questionnaire {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')
            ->create_instance(['course' => $course->id, 'respondenttype' => 'anonymous']);
        $PAGE->set_context($questionnaire->context());
        return $questionnaire;
    }
}
