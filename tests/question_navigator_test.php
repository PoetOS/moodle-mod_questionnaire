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
 * Unit tests for mod_questionnaire\local\question_navigator.
 *
 * All tests use in-memory stubs (no DB) via survey_testable and anonymous
 * question stubs — the same pattern used in questionnaire_test.php.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire;

use mod_questionnaire\local\db\survey_record;
use mod_questionnaire\local\question_navigator;
use mod_questionnaire\local\survey\survey_testable;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/questionnaire/tests/local/survey/survey_testable.php');

/**
 * Unit tests for question_navigator.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\question_navigator
 */
final class question_navigator_test extends \advanced_testcase {
    // Helpers.

    /**
     * Build an in-memory survey_record with sensible defaults.
     *
     * @param array $fields
     * @return survey_record
     */
    private function make_survey_record(array $fields = []): survey_record {
        return new survey_record(0, (object)array_merge([
            'id'               => 1,
            'name'             => 'Test survey',
            'courseid'         => 1,
            'realm'            => 'private',
            'status'           => 0,
            'title'            => '',
            'email'            => null,
            'subtitle'         => null,
            'info'             => null,
            'theme'            => null,
            'thankspage'       => null,
            'thankhead'        => null,
            'thankbody'        => null,
            'feedbacksections' => 0,
            'feedbacknotes'    => null,
            'feedbackscores'   => 0,
            'charttype'        => null,
        ], $fields));
    }

    /**
     * Build a survey_testable with injected questions.
     *
     * @param array $questions Keyed by question id.
     * @param array $questionsbysec Keyed by 1-based section number.
     * @return survey_testable
     */
    private function make_survey(array $questions = [], array $questionsbysec = []): survey_testable {
        $survey = new survey_testable($this->make_survey_record());
        $survey->set_questions($questions);
        $survey->set_questions_by_sec($questionsbysec);
        return $survey;
    }

    /**
     * Build a question_navigator from a survey and a skip-logic flag.
     *
     * @param survey_testable $survey
     * @param bool $skiplogicenabled
     * @return question_navigator
     */
    private function make_navigator(survey_testable $survey, bool $skiplogicenabled = false): question_navigator {
        return new question_navigator($survey, $skiplogicenabled);
    }

    /**
     * Build an anonymous question stub with the given id and dependency list.
     *
     * The stub satisfies the interface expected by question_navigator:
     * has_dependencies(), id(), dependency_fulfilled().
     *
     * @param int $id
     * @param array $dependencies
     * @return object
     */
    private function make_stub_question(int $id, array $dependencies): object {
        return new class ($id, $dependencies) {
            /** @var int */
            public int $id;
            /** @var array */
            public array $dependencies;
            /** @var int */
            public int $position = 0;
            /** @var string */
            public string $name = '';
            /** @var string */
            public string $content = '';
            /** @var int */
            public int $typeid = 0;

            /**
             * Constructor.
             * @param int $id
             * @param array $dependencies
             */
            public function __construct(int $id, array $dependencies) {
                $this->id = $id;
                $this->dependencies = $dependencies;
            }

            /**
             * True if dependencies are non-empty.
             * @return bool
             */
            public function has_dependencies(): bool {
                return !empty($this->dependencies);
            }

            /**
             * Return the question id.
             * @return int
             */
            public function id(): int {
                return $this->id;
            }

            /**
             * Always fulfilled when no dependencies are set (used in navigation tests).
             * @param mixed $rid
             * @param mixed $questions
             * @return bool
             */
            public function dependency_fulfilled($rid, $questions): bool {
                return !$this->has_dependencies();
            }
        };
    }

    // Tests for has_dependencies().

    /**
     * Asserts has_dependencies() returns false when skip-logic is disabled.
     *
     * @covers \mod_questionnaire\local\question_navigator::has_dependencies
     */
    public function test_has_dependencies_false_when_skiplogic_disabled(): void {
        $dep = (object)['dependquestionid' => 2, 'dependchoiceid' => 1, 'dependlogic' => 1, 'dependandor' => 'and'];
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, [99 => $dep]),
        ]);
        $nav = $this->make_navigator($survey, false);
        $this->assertFalse($nav->has_dependencies());
    }

    /**
     * Asserts has_dependencies() returns false when skip-logic is on but no question has deps.
     *
     * @covers \mod_questionnaire\local\question_navigator::has_dependencies
     */
    public function test_has_dependencies_false_when_no_question_has_deps(): void {
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, []),
        ]);
        $nav = $this->make_navigator($survey, true);
        $this->assertFalse($nav->has_dependencies());
    }

    /**
     * Asserts has_dependencies() returns true when skip-logic is on and a question has a dep.
     *
     * @covers \mod_questionnaire\local\question_navigator::has_dependencies
     */
    public function test_has_dependencies_true_when_question_has_dep(): void {
        $dep = (object)['dependquestionid' => 1, 'dependchoiceid' => 1, 'dependlogic' => 1, 'dependandor' => 'and'];
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, [99 => $dep]),
        ]);
        $nav = $this->make_navigator($survey, true);
        $this->assertTrue($nav->has_dependencies());
    }

    // Tests for get_dependants().

    /**
     * Asserts get_dependants() returns an empty array when no questions depend on the given question.
     *
     * @covers \mod_questionnaire\local\question_navigator::get_dependants
     */
    public function test_get_dependants_returns_empty_when_none(): void {
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, []),
        ]);
        $nav = $this->make_navigator($survey);
        $this->assertSame([], $nav->get_dependants(1));
    }

    /**
     * Asserts get_dependants() returns IDs of questions that depend on the given question.
     *
     * @covers \mod_questionnaire\local\question_navigator::get_dependants
     */
    public function test_get_dependants_returns_dependent_ids(): void {
        $dep = (object)['dependquestionid' => 1, 'dependchoiceid' => 1, 'dependlogic' => 1, 'dependandor' => 'and'];
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, [10 => $dep]),
            3 => $this->make_stub_question(3, []),
        ]);
        $nav = $this->make_navigator($survey);
        $this->assertSame([2], $nav->get_dependants(1));
    }

    /**
     * Asserts get_dependants() returns multiple IDs when several questions depend on the same parent.
     *
     * @covers \mod_questionnaire\local\question_navigator::get_dependants
     */
    public function test_get_dependants_returns_multiple_dependants(): void {
        $dep1 = (object)['dependquestionid' => 1, 'dependchoiceid' => 1, 'dependlogic' => 1, 'dependandor' => 'and'];
        $dep2 = (object)['dependquestionid' => 1, 'dependchoiceid' => 2, 'dependlogic' => 1, 'dependandor' => 'and'];
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, [10 => $dep1]),
            3 => $this->make_stub_question(3, [11 => $dep2]),
        ]);
        $nav = $this->make_navigator($survey);
        $result = $nav->get_dependants(1);
        sort($result);
        $this->assertSame([2, 3], $result);
    }

    // Tests for get_dependants_and_choices().

    /**
     * Asserts get_dependants_and_choices() returns an empty array when no dependencies exist.
     *
     * @covers \mod_questionnaire\local\question_navigator::get_dependants_and_choices
     */
    public function test_get_dependants_and_choices_empty_when_none(): void {
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, []),
        ]);
        $nav = $this->make_navigator($survey);
        $this->assertSame([], $nav->get_dependants_and_choices());
    }

    /**
     * Asserts get_dependants_and_choices() returns the correct parent→child map.
     *
     * @covers \mod_questionnaire\local\question_navigator::get_dependants_and_choices
     */
    public function test_get_dependants_and_choices_returns_map(): void {
        $dep = (object)['dependquestionid' => 1, 'dependchoiceid' => 5, 'dependlogic' => 1, 'dependandor' => 'and'];
        $survey = $this->make_survey([
            1 => $this->make_stub_question(1, []),
            2 => $this->make_stub_question(2, [10 => $dep]),
        ]);
        $nav = $this->make_navigator($survey);
        $result = $nav->get_dependants_and_choices();
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result[1]);
        $this->assertEquals(5, $result[1][2][0]->choiceid);
        $this->assertEquals(1, $result[1][2][0]->logic);
        $this->assertEquals('and', $result[1][2][0]->andor);
    }

    // Tests for the eligible_questions_on_page, next_page and prev_page navigation methods.

    /**
     * Asserts eligible_questions_on_page() returns true for a question with no dependencies.
     *
     * @covers \mod_questionnaire\local\question_navigator::eligible_questions_on_page
     */
    public function test_eligible_questions_on_page_with_no_deps(): void {
        $stub = $this->make_stub_question(1, []);
        $survey = $this->make_survey([1 => $stub], [1 => [$stub]]);
        $nav = $this->make_navigator($survey, false);
        $this->assertTrue($nav->eligible_questions_on_page(1, 0));
    }

    /**
     * Asserts next_page() increments section when there are no dependencies.
     *
     * @covers \mod_questionnaire\local\question_navigator::next_page
     */
    public function test_next_page_no_dependencies(): void {
        $stub1 = $this->make_stub_question(1, []);
        $stub2 = $this->make_stub_question(2, []);
        $stub3 = $this->make_stub_question(3, []);
        $survey = $this->make_survey(
            [1 => $stub1, 2 => $stub2, 3 => $stub3],
            [1 => [$stub1], 2 => [$stub2], 3 => [$stub3]]
        );
        $nav = $this->make_navigator($survey, false);
        $this->assertEquals(2, $nav->next_page(1, 0));
        $this->assertEquals(3, $nav->next_page(2, 0));
        // Past the last section — returns the (invalid) incremented number, not false,
        // because the skip-loop only runs when has_dependencies() is true.
        $this->assertEquals(4, $nav->next_page(3, 0));
    }

    /**
     * Asserts prev_page() decrements section when there are no dependencies.
     *
     * @covers \mod_questionnaire\local\question_navigator::prev_page
     */
    public function test_prev_page_no_dependencies(): void {
        $stub1 = $this->make_stub_question(1, []);
        $stub2 = $this->make_stub_question(2, []);
        $survey = $this->make_survey(
            [1 => $stub1, 2 => $stub2],
            [1 => [$stub1], 2 => [$stub2]]
        );
        $nav = $this->make_navigator($survey, false);
        $this->assertEquals(1, $nav->prev_page(2, 0));
        // Section 1 → decrements to 0 → returns false.
        $this->assertFalse($nav->prev_page(1, 0));
    }
}
