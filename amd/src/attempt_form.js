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
 * Form-change watcher for the survey response form.
 *
 * Replaces the legacy M.mod_questionnaire.init_attempt_form callback.
 *
 * @module     mod_questionnaire/attempt_form
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_form/changechecker'], function(FormChangeChecker) {
    return {
        /**
         * Attach the form-change watcher to the response form.
         */
        init: function() {
            FormChangeChecker.watchFormById('phpesp_response');
        }
    };
});
