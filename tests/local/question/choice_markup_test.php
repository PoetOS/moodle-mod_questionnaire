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
 * Unit tests for how check and radio questions show choice markup from untrusted authors.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\question;

use mod_questionnaire\local\response\response;

/**
 * Choice text, embedded images and "other" labels are shown with their author's markup.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question\check
 * @covers \mod_questionnaire\local\question\radio
 * @covers \mod_questionnaire\local\question\question::mobile_question_choices_display
 */
final class choice_markup_test extends \advanced_testcase {
    /** @var string A choice with an embedded image. */
    private const IMAGE_CHOICE = 'Yes <img src="https://example.com/yes.png">';

    /** @var string An "other" choice with a formatted label. */
    private const OTHER_CHOICE = '!other=<b>Something else</b>';

    /**
     * Data provider: the two question types with image and "other" choices.
     *
     * @return array
     */
    public static function types_provider(): array {
        return [
            'check boxes' => [QUESCHECK],
            'radio buttons' => [QUESRADIO],
        ];
    }

    /**
     * The form shows the choice image and the formatted "other" label.
     *
     * @dataProvider types_provider
     * @param int $typeid
     */
    public function test_question_survey_display_keeps_choice_markup(int $typeid): void {
        $this->resetAfterTest();
        $question = $this->build_question($typeid);

        $choicetags = $this->invoke($question, 'question_survey_display', [new response(), [], false]);
        $labels = implode("\n", array_map(fn($element) => $element->choice->label ?? '', $choicetags->qelements));

        $this->assert_markup_kept($labels);
    }

    /**
     * The radio "other" text box is labelled from a language string around the formatted label.
     */
    public function test_radio_other_text_label_uses_language_string(): void {
        $this->resetAfterTest();
        $question = $this->build_question(QUESRADIO);

        $choicetags = $this->invoke($question, 'question_survey_display', [new response(), [], false]);
        $olabels = array_values(array_filter(array_map(fn($element) => $element->choice->olabel ?? null, $choicetags->qelements)));

        $this->assertCount(1, $olabels);
        $this->assertSame(
            get_string('othertextfor', 'questionnaire', $question->format_content('<b>Something else</b>')),
            $olabels[0]
        );
    }

    /**
     * Response views show the choice image and the formatted "other" label.
     *
     * @dataProvider types_provider
     * @param int $typeid
     */
    public function test_response_survey_display_keeps_choice_markup(int $typeid): void {
        $this->resetAfterTest();
        $question = $this->build_question($typeid);

        $resptags = $this->invoke($question, 'response_survey_display', [new response()]);
        $contents = implode("\n", array_map(fn($choice) => $choice->content, $resptags->choices));

        $this->assert_markup_kept($contents);
    }

    /**
     * The mobile app's choices show the choice image.
     *
     * @dataProvider types_provider
     * @param int $typeid
     */
    public function test_mobile_question_choices_display_keeps_choice_image(int $typeid): void {
        $this->resetAfterTest();
        $question = $this->build_question($typeid);

        $contents = implode("\n", array_map(fn($choice) => $choice->content, $question->mobile_question_choices_display()));

        $this->assertStringContainsString('<img src="https://example.com/yes.png"', $contents);
    }

    /**
     * Assert that the choice image and the formatted "other" label are both shown.
     *
     * @param string $html
     */
    private function assert_markup_kept(string $html): void {
        $this->assertStringContainsString('<img src="https://example.com/yes.png"', $html);
        $this->assertStringContainsString('<b>Something else</b>', $html);
    }

    /**
     * Build a question of the given type, saved without trust, with an image choice and an "other" choice.
     *
     * @param int $typeid QUESCHECK or QUESRADIO.
     * @return question
     */
    private function build_question(int $typeid): question {
        $course = $this->getDataGenerator()->create_course();
        $questionnaire = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire')->create_test_questionnaire(
            $course,
            $typeid,
            ['content' => 'Pick one'],
            [(object)['content' => self::IMAGE_CHOICE, 'value' => 1], (object)['content' => self::OTHER_CHOICE, 'value' => 2]]
        );
        $questions = $questionnaire->questions();
        $question = reset($questions);
        $this->assertSame(0, $question->content_trust());
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
        return (new \ReflectionMethod($obj, $method))->invokeArgs($obj, $args);
    }
}
