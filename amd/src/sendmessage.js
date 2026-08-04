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
 * Bulk-select helpers for the non-respondents list (Send message page).
 *
 * Replaces the legacy YUI M.mod_questionnaire.init_sendmessage callback.
 * Wires #checkall / #checknone / #checknotstarted / #checkstarted to the
 * .usercheckbox checkboxes; each checkbox's `alt` attribute holds 0 for
 * "not started" and 1 for "started".
 *
 * @module     mod_questionnaire/sendmessage
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Apply a checked-state function to every .usercheckbox in the document.
     *
     * @param {function(HTMLInputElement): boolean} predicate
     */
    var apply = function(predicate) {
        var boxes = document.querySelectorAll('input.usercheckbox');
        boxes.forEach(function(box) {
            box.checked = predicate(box);
        });
    };

    /**
     * Attach a click handler to an element by id (no-op if not present).
     *
     * @param {string} id
     * @param {function(HTMLInputElement): boolean} predicate
     */
    var bind = function(id, predicate) {
        var trigger = document.getElementById(id);
        if (trigger) {
            trigger.addEventListener('click', function() {
                apply(predicate);
            });
        }
    };

    return {
        /**
         * Bind the four bulk-select triggers on the Send-message page.
         */
        init: function() {
            bind('checkall', function() {
                return true;
            });
            bind('checknone', function() {
                return false;
            });
            bind('checknotstarted', function(box) {
                return box.getAttribute('alt') === '0';
            });
            bind('checkstarted', function(box) {
                return box.getAttribute('alt') === '1';
            });
        }
    };
});
