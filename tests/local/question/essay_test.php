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
 * Unit tests for essay::question_survey_display.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\question;

use mod_questionnaire\local\response\response;

/**
 * Unit tests for mod_questionnaire\local\question\essay rendering.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\essay::question_survey_display
 */
final class essay_test extends \advanced_testcase {
    /** @var string An answer that tries to close the textarea and run script. */
    private const PAYLOAD = '</textarea><script>alert(1)</script>';

    /**
     * Data provider: the HTML editor and plain textarea variants of the essay question.
     *
     * @return array
     */
    public static function variants_provider(): array {
        return [
            'html editor' => [0],
            'plain text' => [1],
        ];
    }

    /**
     * The answer is escaped inside the textarea, so it cannot close the element.
     *
     * @dataProvider variants_provider
     * @param int $precise 0 for the HTML editor, otherwise plain text.
     */
    public function test_question_survey_display_escapes_answer(int $precise): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->build_essay_question($precise);
        $response = response::response_from_webform(
            (object)['rid' => 0, 'q' . $question->id() => self::PAYLOAD],
            [$question->id() => $question]
        );

        $method = new \ReflectionMethod($question, 'question_survey_display');
        $html = $method->invoke($question, $response, [], false);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString(s(self::PAYLOAD), $html);
        $this->assertSame(1, substr_count($html, '</textarea>'));
    }

    /**
     * Build an essay question.
     *
     * @param int $precise 0 for the HTML editor, otherwise plain text.
     * @return essay
     */
    private function build_essay_question(int $precise): essay {
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            QUESESSAY,
            ['content' => 'Write something', 'length' => 5, 'precise' => $precise],
            []
        );
        $questions = $questionnaire->questions();
        return reset($questions);
    }
}
