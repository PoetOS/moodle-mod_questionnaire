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
 * Feedback scoreboard value object.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\feedback;

/**
 * The data side of a feedback render: messages, score table, chart.
 *
 * Built by {@see \mod_questionnaire\feedback::build_scoreboard()}; consumed by
 * {@see \mod_questionnaire\reporter::response_analysis()} which applies the
 * matching `add_to_page` side effects. Each field is independent — any subset
 * may be null/empty depending on the questionnaire's feedback configuration.
 */
class scoreboard {
    /**
     * Constructor.
     *
     * @param string[] $messages HTML chunks for the feedback messages output area.
     * @param string|null $table Pre-rendered feedbackscores table HTML, null when not shown.
     * @param string|null $chart Pre-rendered feedbackcharts HTML, null when not shown.
     */
    public function __construct(
        /** @var string[] HTML chunks for the feedback messages output area. */
        public readonly array $messages,
        /** @var string|null Pre-rendered feedbackscores table HTML, null when not shown. */
        public readonly ?string $table,
        /** @var string|null Pre-rendered feedbackcharts HTML, null when not shown. */
        public readonly ?string $chart,
    ) {
    }
}
