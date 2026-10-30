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
 * Backup and restore tests for the trust flags of staff-authored text.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for trust flags.
 *
 * @group mod_questionnaire
 * @covers \restore_questionnaire_activity_structure_step
 * @covers \backup_questionnaire_activity_structure_step
 */
final class restore_trust_test extends \advanced_testcase {
    /**
     * Trust flags survive a restore by a trusted user.
     */
    public function test_restore_keeps_trust_for_trusted_user(): void {
        $this->resetAfterTest();
        set_config('enabletrusttext', 1);
        $this->assertSame([1, 1, 1, 1, 1, 1], $this->backup_and_restore_trusted_survey());
    }

    /**
     * Trust flags are dropped when the restoring user is not trusted (here, because trusted content is off).
     */
    public function test_restore_drops_trust_for_untrusted_user(): void {
        $this->resetAfterTest();
        $this->assertSame([0, 0, 0, 0, 0, 0], $this->backup_and_restore_trusted_survey());
    }

    /**
     * Back up a course whose survey texts are all trusted, restore it, and return the restored trust flags.
     *
     * @return int[] infotrust, thankbodytrust, feedbacknotestrust, contenttrust, sectionheadingtrust, feedbacktexttrust.
     */
    private function backup_and_restore_trusted_survey(): array {
        global $CFG, $DB, $USER;
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESTEXT
        );
        $sid = $questionnaire->surveyid();
        $section = \mod_questionnaire\local\feedback\section::new_section($sid, 'Section');
        \mod_questionnaire\local\feedback\sectionfeedback::new_sectionfeedback((object)['sectionid' => $section->id,
            'feedbacklabel' => '', 'feedbacktext' => 'Text', 'feedbacktextformat' => FORMAT_HTML, 'minscore' => 0,
            'maxscore' => 100]);
        foreach (['infotrust', 'thankbodytrust', 'feedbacknotestrust'] as $field) {
            $DB->set_field('questionnaire_survey', $field, 1, ['id' => $sid]);
        }
        $DB->set_field('questionnaire_question', 'contenttrust', 1, ['surveyid' => $sid]);
        $DB->set_field('questionnaire_fb_sections', 'sectionheadingtrust', 1, ['surveyid' => $sid]);
        $DB->set_field('questionnaire_feedback', 'feedbacktexttrust', 1, ['sectionid' => $section->id]);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'R1', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $newsid = $DB->get_field('questionnaire', 'sid', ['course' => $newcourseid], MUST_EXIST);
        $survey = $DB->get_record('questionnaire_survey', ['id' => $newsid], '*', MUST_EXIST);
        $newsection = $DB->get_record('questionnaire_fb_sections', ['surveyid' => $newsid], '*', MUST_EXIST);
        return array_map('intval', [
            $survey->infotrust,
            $survey->thankbodytrust,
            $survey->feedbacknotestrust,
            $DB->get_field('questionnaire_question', 'contenttrust', ['surveyid' => $newsid], MUST_EXIST),
            $newsection->sectionheadingtrust,
            $DB->get_field('questionnaire_feedback', 'feedbacktexttrust', ['sectionid' => $newsection->id], MUST_EXIST),
        ]);
    }
}
