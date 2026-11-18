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

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');

/**
 * Tests for the "other" text answered on a rate question.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 */
class rate_other_test extends \advanced_testcase {
    /** @var string Answer text with markup that is not allowed. */
    const UNCLEAN = 'Left|Mine<script>x()</script>';

    /**
     * Create a rate question with an "other" choice, and answer it.
     *
     * @param int $precise The rate question's display type.
     * @param string $choice The content of the ordinary choice.
     * @return array [question, response id, "other" choice id]
     */
    private function answer_rate_question(int $precise, string $choice): array {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $choicedata = [(object)['content' => $choice, 'value' => 1], (object)['content' => '!other', 'value' => 2]];
        $questiondata = ['content' => 'Rate these', 'length' => 5, 'precise' => $precise];
        $questionnaire = $generator->create_test_questionnaire($course, QUESRATE, $questiondata, $choicedata);
        $question = reset($questionnaire->questions);

        $vals = [];
        foreach ($question->choices as $cid => $choiceobj) {
            $vals['q' . $question->id . '_' . $cid] = 2;
            if ($choiceobj->content == '!other') {
                $othercid = $cid;
                $vals[$cid . '_qother'] = self::UNCLEAN;
            }
        }
        $response = $generator->create_question_response($questionnaire, $question, $vals);

        return [$question, $response->id, $othercid];
    }

    /**
     * Saved "other" text is cleaned.
     *
     * @covers \mod_questionnaire\responsetype\rank::insert_response
     */
    public function test_insert_response_cleans_other_text(): void {
        global $DB;
        $this->resetAfterTest();

        [, $rid, $othercid] = $this->answer_rate_question(0, 'One');

        $saved = $DB->get_field('questionnaire_response_other', 'response', ['response_id' => $rid, 'choice_id' => $othercid]);
        $this->assertStringContainsString('Mine', $saved);
        $this->assertStringNotContainsString('<script', $saved);
    }

    /**
     * Provide the rate question display types to check.
     *
     * @return array
     */
    public static function display_types(): array {
        return [
            'normal' => [0, 'One'],
            'osgood' => [3, 'Cold|Hot'],
        ];
    }

    /**
     * "Other" text saved before it was cleaned is cleaned in the results summary.
     *
     * @dataProvider display_types
     * @covers \mod_questionnaire\responsetype\rank::display_results
     * @param int $precise The rate question's display type.
     * @param string $choice The content of the ordinary choice.
     */
    public function test_display_results_cleans_other_text(int $precise, string $choice): void {
        global $DB;
        $this->resetAfterTest();

        [$question, $rid, $othercid] = $this->answer_rate_question($precise, $choice);
        $other = ['response_id' => $rid, 'choice_id' => $othercid];
        $DB->set_field('questionnaire_response_other', 'response', self::UNCLEAN, $other);

        $results = json_encode($question->responsetype->display_results([$rid], 'default'));
        $this->assertStringContainsString('Mine', $results);
        $this->assertStringNotContainsString('<script', $results);
    }
}
