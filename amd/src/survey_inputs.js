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
 * Survey-response form input behaviours.
 *
 * Replaces the legacy global other_check() / other_rate_uncheck() functions
 * that lived in module.js and were invoked from inline onclick= attributes.
 *
 * Uses click delegation on the #phpesp_response form against two data
 * attributes:
 *
 *   data-questionnaire-other-check        on an "Other" text input —
 *       when clicked, auto-selects the matching radio/checkbox.
 *
 *   data-questionnaire-rate-uncheck       on a rate-row radio input —
 *       when clicked, unchecks every other radio in the same rate-question
 *       column (used by "no duplicate choices" rate questions).
 *
 * @module     mod_questionnaire/survey_inputs
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Auto-select the radio/checkbox whose value matches the trailing segment of
     * the clicked "Other" text input's name attribute.
     *
     * Ports the original other_check(name) logic from module.js.
     *
     * @param {HTMLInputElement} input The clicked "Other" text input.
     */
    var otherCheck = function(input) {
        var name = input.name;
        var idx = name.indexOf('o');
        if (idx < 0) {
            return;
        }
        var other = name.slice(idx + 1);
        var bracket = other.indexOf(']');
        if (bracket !== -1) {
            other = other.slice(0, bracket);
        }
        var form = document.getElementById('phpesp_response');
        if (!form) {
            return;
        }
        for (var i = 0; i < form.elements.length; i++) {
            if (form.elements[i].value === other) {
                form.elements[i].checked = true;
                break;
            }
        }
    };

    /**
     * Uncheck every other rate-question radio in the same column (same value,
     * same name prefix up to the first underscore, different name).
     *
     * Ports the original other_rate_uncheck(name, value) logic from module.js.
     *
     * @param {HTMLInputElement} radio The clicked rate-row radio input.
     */
    var rateUncheck = function(radio) {
        var name = radio.name;
        var value = radio.value;
        var underscoreAt = name.indexOf('_');
        if (underscoreAt < 0) {
            return;
        }
        var colName = name.substr(0, underscoreAt);
        var buttons = document.getElementsByTagName('input');
        for (var i = 0; i < buttons.length; i++) {
            var button = buttons[i];
            if (button.type === 'radio'
                && button.name !== name
                && button.value === value
                && button.name.substr(0, underscoreAt) === colName) {
                button.checked = false;
            }
        }
    };

    return {
        /**
         * Attach delegated click handlers to the survey response form.
         */
        init: function() {
            var form = document.getElementById('phpesp_response');
            if (!form) {
                return;
            }
            form.addEventListener('click', function(event) {
                var target = event.target;
                if (!target || target.tagName !== 'INPUT') {
                    return;
                }
                if (target.hasAttribute('data-questionnaire-other-check')) {
                    otherCheck(target);
                } else if (target.hasAttribute('data-questionnaire-rate-uncheck')) {
                    rateUncheck(target);
                }
            });
        }
    };
});
