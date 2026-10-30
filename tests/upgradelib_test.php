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
 * Unit tests for the questionnaire upgrade helper functions.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/db/upgradelib.php');

/**
 * Unit tests for db/upgradelib.php.
 *
 * @group mod_questionnaire
 * @covers ::questionnaire_upgrade_move_question_deletion_duration
 */
final class upgradelib_test extends \advanced_testcase {
    /**
     * An existing recycle-bin duration moves to the plugin's own config and the old setting is removed.
     */
    public function test_move_question_deletion_duration_moves_value(): void {
        $this->resetAfterTest();
        unset_config('questiondeletionduration', 'questionnaire');
        set_config('duration', 3 * DAYSECS, 'questionnaire_questiondeletion');

        questionnaire_upgrade_move_question_deletion_duration();

        $this->assertEquals(3 * DAYSECS, get_config('questionnaire', 'questiondeletionduration'));
        $this->assertFalse(get_config('questionnaire_questiondeletion', 'duration'));
    }

    /**
     * Without an old recycle-bin duration, the current setting is left alone.
     */
    public function test_move_question_deletion_duration_without_old_value(): void {
        $this->resetAfterTest();
        unset_config('duration', 'questionnaire_questiondeletion');
        set_config('questiondeletionduration', 5 * DAYSECS, 'questionnaire');

        questionnaire_upgrade_move_question_deletion_duration();

        $this->assertEquals(5 * DAYSECS, get_config('questionnaire', 'questiondeletionduration'));
    }

    /**
     * Existing staff-authored text is marked trusted, keeping how it was shown before trust flags.
     *
     * @covers ::questionnaire_upgrade_trust_existing_content
     */
    public function test_trust_existing_content(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $this->getDataGenerator()->create_course(),
            QUESTEXT
        );
        $section = \mod_questionnaire\local\feedback\section::new_section($questionnaire->surveyid(), 'Section');
        \mod_questionnaire\local\feedback\sectionfeedback::new_sectionfeedback((object)['sectionid' => $section->id,
            'feedbacklabel' => '', 'feedbacktext' => 'Text', 'feedbacktextformat' => FORMAT_HTML, 'minscore' => 0,
            'maxscore' => 100]);

        questionnaire_upgrade_trust_existing_content();

        foreach (questionnaire_upgrade_trust_fields() as $table => $fields) {
            foreach ($fields as $trustfield) {
                $this->assertGreaterThan(0, $DB->count_records($table), $table);
                $this->assertSame(0, $DB->count_records($table, [$trustfield => 0]), "$table.$trustfield");
            }
        }
    }
}
