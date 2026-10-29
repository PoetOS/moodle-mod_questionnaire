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
 * Unit tests for file::question_survey_display.
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
 * Unit tests for mod_questionnaire\local\question\file rendering.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\file::question_survey_display
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
            'filename' => 'mine.txt',
        ], 'mine');
        $question->insert_response((object)['rid' => $rid, 'q' . $question->id() => $draftitemid]);

        $questionnaire = questionnaire::from_instanceid($questionnaire->id());
        $questionnaire->add_response($rid);
        $PAGE->set_url('/mod/questionnaire/complete.php', ['id' => $questionnaire->coursemodule()->id]);
        $output = $question->question_output($questionnaire->responses()->get_response($rid), false, [], 1, $questionnaire);

        $this->assertMatchesRegularExpression('/name="q' . $question->id() . '" value="(\d+)"/', $output->qformelement);
        preg_match('/name="q' . $question->id() . '" value="(\d+)"/', $output->qformelement, $matches);
        $files = get_file_storage()->get_area_files($usercontextid, 'user', 'draft', (int)$matches[1], 'id', false);
        $this->assertSame(['mine.txt'], array_values(array_map(fn($file) => $file->get_filename(), $files)));
    }
}
