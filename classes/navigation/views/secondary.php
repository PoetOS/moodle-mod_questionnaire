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

namespace mod_questionnaire\navigation\views;

use core\navigation\views\secondary as core_secondary;

/**
 * Custom secondary navigation for mod_questionnaire.
 *
 * Orders the plugin's settings-navigation nodes so the visible bar reads
 * Questionnaire | Settings | Questions | Preview | Responses, with the less-used
 * destinations (Advanced settings, Feedback, Non-respondents) overflowing into "More".
 *
 * @package     mod_questionnaire
 * @category    navigation
 * @copyright   2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author      Mike Churchward
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secondary extends core_secondary {
    /**
     * Insert the questionnaire settings-nav keys into the default ordering map.
     *
     * @return array
     */
    protected function get_default_module_mapping(): array {
        $basenodes = parent::get_default_module_mapping();
        // Weights must be integers: core treats float weights ("7.2") as children nested
        // under the node at the floored weight, which removes them from the flat bar.
        // 3 and 4 are the quiz-style user/group-override keys, which questionnaire never emits.
        $basenodes[self::TYPE_SETTING] += [
            'questions' => 2,
            'preview' => 3,
            'vall' => 4,
            'nonrespondents' => 13,
            'feedback' => 14,
            'advancedsettings' => 15,
        ];

        return $basenodes;
    }
}
