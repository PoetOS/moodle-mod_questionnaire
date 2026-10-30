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
 * Unit tests for the file question type.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\question;

use mod_questionnaire\local\response\response;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\question\file.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\file
 */
final class file_test extends \advanced_testcase {
    /**
     * Showing the question again puts the earlier upload in the user's draft area, without any request parameters.
     */
    public function test_survey_display_loads_earlier_upload(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESFILE,
            ['content' => 'Upload a file']
        );
        $questions = $questionnaire->questions();
        $question = reset($questions);

        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);
        $usercontextid = \context_user::instance($user->id)->id;
        $rid = response::create($questionnaire->id(), $user->id)->id();
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => $usercontextid,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'mine.pdf',
        ], 'mine');
        $question->insert_response((object)['rid' => $rid, 'q' . $question->id() => $draftitemid]);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $questionnaire->add_response($rid);
        $PAGE->set_url('/mod/questionnaire/complete.php', ['id' => $questionnaire->coursemodule()->id]);
        $output = $question->question_output($questionnaire->responses()->get_response($rid), false, [], 1, $questionnaire);

        $this->assertMatchesRegularExpression('/name="q' . $question->id() . '" value="(\d+)"/', $output->qformelement);
        preg_match('/name="q' . $question->id() . '" value="(\d+)"/', $output->qformelement, $matches);
        $files = get_file_storage()->get_area_files($usercontextid, 'user', 'draft', (int)$matches[1], 'id', false);
        $this->assertSame(['mine.pdf'], array_values(array_map(fn($file) => $file->get_filename(), $files)));
    }

    /**
     * Only image and document file names are accepted.
     */
    public function test_allowed_file_name(): void {
        $this->assertTrue(file::allowed_file_name('answer.pdf'));
        $this->assertTrue(file::allowed_file_name('photo.PNG'));
        $this->assertTrue(file::allowed_file_name('essay.docx'));
        $this->assertFalse(file::allowed_file_name('page.html'));
        $this->assertFalse(file::allowed_file_name('script.php'));
        $this->assertFalse(file::allowed_file_name('notes.txt'));
        $this->assertFalse(file::allowed_file_name('noextension'));
    }

    /**
     * A draft area holding a file of a type the question does not accept is not a valid response.
     */
    public function test_response_valid_checks_uploaded_file_type(): void {
        $this->resetAfterTest();
        [, $question] = $this->make_file_questionnaire();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertTrue($question->response_valid((object)['q' . $question->id() => $this->draft_with(['ok.pdf'])]));
        $this->assertFalse($question->response_valid((object)['q' . $question->id() => $this->draft_with(['x.html'])]));
        $this->assertFalse(
            $question->response_valid((object)['q' . $question->id() => $this->draft_with(['ok.pdf', 'x.html'])])
        );
        $this->assertTrue($question->response_valid((object)['q' . $question->id() => file_get_unused_draft_itemid()]));
        $this->assertTrue($question->response_valid(new \stdClass()));
    }

    /**
     * A submitted image is shown in the page; any other file is a download link, never embedded.
     */
    public function test_response_output_embeds_images_and_links_other_files(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$questionnaire, $question] = $this->make_file_questionnaire();
        $PAGE->set_url('/mod/questionnaire/report.php', ['instance' => $questionnaire->id()]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $output = $this->submitted_output($questionnaire, $question, $user->id, 'answer.pdf');
        $this->assertStringContainsString('answer.pdf', $output);
        $this->assertStringContainsString('forcedownload=1', $output);
        $this->assertStringNotContainsString('<iframe', $output);
        $this->assertStringNotContainsString('<object', $output);

        $output = $this->submitted_output($questionnaire, $question, $user->id, 'photo.png');
        $this->assertMatchesRegularExpression('/<img[^>]+photo\.png/', $output);
    }

    /**
     * Create a questionnaire with one file question.
     *
     * @return array [questionnaire, question]
     */
    private function make_file_questionnaire(): array {
        $this->setAdminUser();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $this->getDataGenerator()->create_course(),
            QUESFILE,
            ['content' => 'Upload a file']
        );
        $questions = $questionnaire->questions();
        return [$questionnaire, reset($questions)];
    }

    /**
     * Put files in a new draft area of the current user.
     *
     * @param string[] $filenames
     * @return int The draft item id.
     */
    private function draft_with(array $filenames): int {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        foreach ($filenames as $filename) {
            get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $filename,
            ], 'content');
        }
        return $draftitemid;
    }

    /**
     * Save a response uploading the named file and return the rendered answer.
     *
     * @param questionnaire $questionnaire
     * @param file $question
     * @param int $userid
     * @param string $filename
     * @return string
     */
    private function submitted_output(questionnaire $questionnaire, file $question, int $userid, string $filename): string {
        $rid = response::create($questionnaire->id(), $userid)->id();
        $question->insert_response((object)['rid' => $rid, 'q' . $question->id() => $this->draft_with([$filename])]);
        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $questionnaire->add_response($rid);
        return $question->response_output($questionnaire->responses()->get_response($rid))->qformelement;
    }
}
