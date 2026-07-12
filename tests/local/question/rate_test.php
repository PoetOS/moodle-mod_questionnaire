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
 * Shape tests for rate::question_survey_display / response_survey_display.
 *
 * Originally the Phase E refactor canaries; now they pin the rebuilt template
 * context shape (UI step 3), including the removal of the legacy hidden
 * "-999" unanswered radios. They assert on the populated $choicetags /
 * $resptags objects (via reflection since both methods are protected),
 * not the eventual mustache HTML.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\question;

use mod_questionnaire\local\response\response;

/**
 * Unit tests for mod_questionnaire\local\question\rate rendering methods.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\rate::question_survey_display
 * @covers \mod_questionnaire\local\question\rate::response_survey_display
 */
final class rate_test extends \advanced_testcase {
    /**
     * Build a rate question with the given precise value + choices + optional extradata (named degrees json).
     *
     * @param int $precise Rate scale type: 0 normal, 1 na, 2 no-dup, 3 osgood.
     * @param array $choicecontents Plain-string content per choice.
     * @param string $extradata JSON extradata for named degrees (empty for none).
     * @return rate
     */
    private function build_rate_question(int $precise, array $choicecontents, string $extradata = ''): rate {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $plugingen = $generator->get_plugin_generator('mod_questionnaire');
        $choicedata = [];
        $i = 1;
        foreach ($choicecontents as $content) {
            $choicedata[] = (object)['content' => $content, 'value' => $i++];
        }
        $questiondata = [
            'content' => 'Rate these',
            'length' => 5,
            'precise' => $precise,
            'extradata' => $extradata,
        ];
        $questionnaire = $plugingen->create_test_questionnaire($course, QUESRATE, $questiondata, $choicedata);
        $questions = $questionnaire->questions();
        /** @var rate $question */
        $question = reset($questions);
        return $question;
    }

    /**
     * Invoke a protected method by name via reflection.
     *
     * @param object $obj
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function invoke(object $obj, string $method, array $args) {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }

    /**
     * A normal-scale rate question renders a header column per rating degree and one
     * row per choice, each with a radio cell per degree.
     */
    public function test_question_survey_display_normal_scale_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_rate_question(0, ['One', 'Two', 'Three']);
        $response = response::create_from_data([]);

        // Null mirrors the runtime callers (the legacy untyped signature tolerated it).
        $choicetags = $this->invoke($question, 'question_survey_display', [$response, '', null]);

        $this->assertIsObject($choicetags);
        $this->assertSame('Rate these', $choicetags->qelements['caption']);
        $this->assertCount(5, $choicetags->qelements['headercols']);
        $this->assertSame(5, $choicetags->qelements['numratecols']);
        $this->assertCount(3, $choicetags->qelements['rows']);
        foreach ($choicetags->qelements['rows'] as $row) {
            $this->assertCount(5, $row['cells']);
        }
    }

    /**
     * The legacy hidden "unanswered" radio (value -999) is gone: every rendered cell
     * carries a real rating value and nothing is pre-checked on a fresh response.
     */
    public function test_question_survey_display_has_no_unanswered_radios(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_rate_question(0, ['One', 'Two', 'Three']);
        $response = response::create_from_data([]);

        $choicetags = $this->invoke($question, 'question_survey_display', [$response, '', false]);

        foreach ($choicetags->qelements['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                $this->assertNotEquals(-999, $cell['value']);
                $this->assertArrayNotHasKey('checked', $cell);
                $this->assertNotSame('', $cell['id']);
            }
        }
    }

    /**
     * An N/A rate question appends an N/A column to the header and a -1 cell per row.
     */
    public function test_question_survey_display_na_column_added_to_header(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_rate_question(1, ['One', 'Two', 'Three']);
        $response = response::create_from_data([]);

        $choicetags = $this->invoke($question, 'question_survey_display', [$response, '', false]);

        // 5 rating columns + 1 N/A column.
        $this->assertCount(6, $choicetags->qelements['headercols']);
        $natext = get_string('notapplicable', 'questionnaire');
        $lastcol = end($choicetags->qelements['headercols']);
        $this->assertSame($natext, $lastcol['text']);
        $lastcell = end($choicetags->qelements['rows'][0]['cells']);
        $this->assertSame(-1, $lastcell['value']);
    }

    /**
     * An Osgood question sets the osgood flag and provides the right-side item label.
     */
    public function test_question_survey_display_osgood_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_rate_question(3, ['Cold|Hot', 'Wet|Dry']);
        $response = response::create_from_data([]);

        $choicetags = $this->invoke($question, 'question_survey_display', [$response, '', false]);

        $this->assertTrue($choicetags->qelements['osgood']);
        $this->assertSame('45%', $choicetags->qelements['itemcolwidth']);
        $this->assertStringContainsString('Hot', $choicetags->qelements['rows'][0]['osgoodright']);
        $this->assertStringContainsString('Cold', $choicetags->qelements['rows'][0]['itemtext']);
    }

    /**
     * response_survey_display shape mirrors the question shape: one header per length + one row per choice.
     */
    public function test_response_survey_display_normal_scale_shape(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_rate_question(0, ['One', 'Two', 'Three']);
        $response = response::create_from_data([]);

        $resptags = $this->invoke($question, 'response_survey_display', [$response]);

        $this->assertIsObject($resptags);
        $this->assertCount(5, $resptags->headers);
        $this->assertCount(3, $resptags->rows);
    }

    /**
     * With an N/A column, response_survey_display appends the same N/A header.
     */
    public function test_response_survey_display_na_column_added_to_header(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_rate_question(1, ['One', 'Two', 'Three']);
        $response = response::create_from_data([]);

        $resptags = $this->invoke($question, 'response_survey_display', [$response]);

        $this->assertCount(6, $resptags->headers);
        $natext = get_string('notapplicable', 'questionnaire');
        $this->assertSame($natext, end($resptags->headers)->str);
    }

    /**
     * Osgood scale sets $resptags->osgood = 1 and picks the correct sidecolwidth default (45%).
     * The sidecolwidth branch depends on $maxndlen which is currently never mutated —
     * this test locks in the "first branch" behavior in effect today.
     */
    public function test_response_survey_display_osgood_sets_flag_and_sidecolwidth(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_rate_question(3, ['Cold|Hot', 'Wet|Dry']);
        $response = response::create_from_data([]);

        $resptags = $this->invoke($question, 'response_survey_display', [$response]);

        $this->assertSame(1, $resptags->osgood);
        $this->assertSame('45%', $resptags->sidecolwidth);
    }
}
