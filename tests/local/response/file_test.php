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
 * Unit tests for saving file question answers.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\response;

use mod_questionnaire\local\db\response_file_record;
use mod_questionnaire\questionnaire;

/**
 * Unit tests for mod_questionnaire\local\response\file.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\response\file
 */
final class file_test extends \advanced_testcase {
    /**
     * An uploaded draft file is stored under the new response file record's id.
     */
    public function test_insert_response_stores_upload_under_record_id(): void {
        $this->resetAfterTest();
        [$questionnaire, $question] = $this->make_file_questionnaire();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $rid = $this->make_response($questionnaire, $user->id);

        $recordid = $this->upload_answer($questionnaire, $question, $rid, 'mine.txt');

        $this->assertIsInt($recordid);
        $file = $this->stored_file($questionnaire, $recordid);
        $this->assertSame('mine.txt', $file->get_filename());
        $this->assertEquals($file->get_id(), (new response_file_record($recordid))->get('fileid'));
    }

    /**
     * Uploading again replaces the earlier file; posting the field with no file removes it.
     */
    public function test_insert_response_replaces_and_removes_own_file(): void {
        $this->resetAfterTest();
        [$questionnaire, $question] = $this->make_file_questionnaire();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $rid = $this->make_response($questionnaire, $user->id);

        $first = $this->upload_answer($questionnaire, $question, $rid, 'first.txt');
        $second = $this->upload_answer($questionnaire, $question, $rid, 'second.txt');

        $this->assertNull($this->stored_file($questionnaire, $first));
        $this->assertSame('second.txt', $this->stored_file($questionnaire, $second)->get_filename());

        $emptydraft = file_get_unused_draft_itemid();
        $this->assertFalse($question->insert_response((object)['rid' => $rid, 'q' . $question->id() => $emptydraft]));
        $this->assertNull($this->stored_file($questionnaire, $second));
        $this->assertEmpty(response_file_record::get_for_response($rid));
    }

    /**
     * Posting another respondent's response file id as the draft id leaves their file alone.
     */
    public function test_forged_draft_id_cannot_delete_another_users_file(): void {
        $this->resetAfterTest();
        [$questionnaire, $question] = $this->make_file_questionnaire();
        $victim = $this->getDataGenerator()->create_user();
        $this->setUser($victim);
        $victimrecordid = $this->upload_answer(
            $questionnaire,
            $question,
            $this->make_response($questionnaire, $victim->id),
            'victim.txt'
        );

        $attacker = $this->getDataGenerator()->create_user();
        $this->setUser($attacker);
        $rid = $this->make_response($questionnaire, $attacker->id);
        $formdata = (object)['rid' => $rid, 'q' . $question->id() => $victimrecordid];
        response::response_from_webform($formdata, [$question->id() => $question]);
        $question->insert_response($formdata);

        $this->assertSame('victim.txt', $this->stored_file($questionnaire, $victimrecordid)->get_filename());
    }

    /**
     * A draft whose item id matches another questionnaire's response file id cannot re-key that questionnaire's file.
     */
    public function test_draft_id_matching_other_record_cannot_rekey_its_file(): void {
        $this->resetAfterTest();
        [$other, $otherquestion] = $this->make_file_questionnaire();
        $victim = $this->getDataGenerator()->create_user();
        $this->setUser($victim);
        $victimrecordid = $this->upload_answer(
            $other,
            $otherquestion,
            $this->make_response($other, $victim->id),
            'victim.txt'
        );

        [$questionnaire, $question] = $this->make_file_questionnaire();
        $attacker = $this->getDataGenerator()->create_user();
        $this->setUser($attacker);
        $rid = $this->make_response($questionnaire, $attacker->id);
        $recordid = $this->upload_answer($questionnaire, $question, $rid, 'attacker.txt', $victimrecordid);

        $this->assertSame('victim.txt', $this->stored_file($other, $victimrecordid)->get_filename());
        $this->assertSame('attacker.txt', $this->stored_file($questionnaire, $recordid)->get_filename());
    }

    /**
     * Deleting a response removes its uploaded file as well as the response file record.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::delete_response
     */
    public function test_delete_response_removes_stored_file(): void {
        $this->resetAfterTest();
        [$questionnaire, $question] = $this->make_file_questionnaire();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $rid = $this->make_response($questionnaire, $user->id);
        $recordid = $this->upload_answer($questionnaire, $question, $rid, 'mine.txt');

        $questionnaire->responses()->delete_response((object)['id' => $rid, 'userid' => $user->id]);

        $this->assertNull($this->stored_file($questionnaire, $recordid));
        $this->assertFalse(response_file_record::record_exists($recordid));
    }

    /**
     * Purging a question's answers removes the uploaded files from every questionnaire using the survey.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::delete_responses_for_question
     */
    public function test_delete_responses_for_question_removes_stored_files(): void {
        $this->resetAfterTest();
        [$questionnaire, $question] = $this->make_file_questionnaire();
        $other = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_instance([
            'course' => $this->getDataGenerator()->create_course()->id,
            'sid' => $questionnaire->surveyid(),
        ]);
        $otherquestions = $other->questions();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $first = $this->upload_answer($questionnaire, $question, $this->make_response($questionnaire, $user->id), 'one.txt');
        $second = $this->upload_answer($other, reset($otherquestions), $this->make_response($other, $user->id), 'two.txt');
        $this->assertNotNull($this->stored_file($other, $second));

        questionnaire_responses::delete_responses_for_question($question->id());

        $this->assertNull($this->stored_file($questionnaire, $first));
        $this->assertNull($this->stored_file($other, $second));
        $this->assertFalse(response_file_record::record_exists($first));
        $this->assertFalse(response_file_record::record_exists($second));
    }

    /**
     * Responses past the retention period lose their uploaded files and response file records.
     *
     * @covers \mod_questionnaire\local\response\questionnaire_responses::delete_old_responses
     */
    public function test_delete_old_responses_removes_stored_files(): void {
        global $DB;
        $this->resetAfterTest();
        [$questionnaire, $question] = $this->make_file_questionnaire();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $rid = $this->make_response($questionnaire, $user->id);
        $recordid = $this->upload_answer($questionnaire, $question, $rid, 'old.txt');
        $DB->set_field('questionnaire', 'removeafter', HOURSECS, ['id' => $questionnaire->id()]);
        $DB->update_record('questionnaire_response', (object)[
            'id' => $rid,
            'complete' => 'y',
            'submitted' => time() - (2 * HOURSECS),
        ]);

        questionnaire_responses::delete_old_responses();

        $this->assertNull($this->stored_file($questionnaire, $recordid));
        $this->assertFalse(response_file_record::record_exists($recordid));
    }

    /**
     * Build a questionnaire with one file question.
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
     * Create an in-progress response.
     *
     * @param questionnaire $questionnaire
     * @param int $userid
     * @return int The response id.
     */
    private function make_response(questionnaire $questionnaire, int $userid): int {
        return response::create($questionnaire->id(), $userid)->id();
    }

    /**
     * Upload a file to the current user's draft area and save it as the answer, as the survey form does.
     *
     * @param questionnaire $questionnaire
     * @param \mod_questionnaire\local\question\question $question
     * @param int $rid
     * @param string $filename
     * @param int|null $draftitemid Draft item id to use; a fresh one by default.
     * @return int|bool The new response file record id, or false.
     */
    private function upload_answer(
        questionnaire $questionnaire,
        $question,
        int $rid,
        string $filename,
        ?int $draftitemid = null
    ) {
        global $USER;
        $draftitemid = $draftitemid ?? file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $filename);
        return $question->insert_response((object)['rid' => $rid, 'q' . $question->id() => $draftitemid]);
    }

    /**
     * Return the file stored for a response file record, or null.
     *
     * @param questionnaire $questionnaire
     * @param int $recordid
     * @return \stored_file|null
     */
    private function stored_file(questionnaire $questionnaire, int $recordid): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            $questionnaire->context()->id,
            'mod_questionnaire',
            'response_file',
            $recordid,
            'id',
            false
        );
        return empty($files) ? null : reset($files);
    }
}
