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
 * Slider question type behaviour: animates the value bubble alongside the
 * native <input type="range"> thumb and renders an off-screen accessibility
 * heading describing min/middle/max labels.
 *
 * Replaces the legacy M.mod_questionnaire.init_slider callback.
 *
 * @module     mod_questionnaire/slider
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Calculate the centre value(s) for the slider's range.
     *
     * Returns one value when the range has an even number of steps, two when odd.
     *
     * @param {HTMLInputElement} range
     * @returns {number[]}
     */
    var calculateCenterValues = function(range) {
        var min = parseInt(range.min);
        var max = parseInt(range.max);
        var step = parseInt(range.step);
        var rangeNum = max - min;
        var numSteps = rangeNum / step;
        var centerValues = [];
        if (numSteps % 2 === 0) {
            centerValues.push((min + max) / 2);
        } else {
            var lowerCenter = min + Math.floor(numSteps / 2) * step;
            centerValues.push(lowerCenter, lowerCenter + step);
        }
        return centerValues;
    };

    /**
     * Position the value bubble alongside the thumb and write the current value.
     *
     * @param {HTMLInputElement} range
     * @param {HTMLElement} bubble
     */
    var setBubble = function(range, bubble) {
        var val = range.value;
        var min = range.min ? range.min : 0;
        var max = range.max ? range.max : 100;
        var newVal = Number(((val - min) * 100) / (max - min));
        var positiveVal = '';
        if (range.min && range.min < 0 && range.max && range.max > 0 && val > 0) {
            positiveVal = '+';
        }
        bubble.innerHTML = positiveVal + val;
        bubble.style.left = 'calc(' + newVal + '% + (' + (8 - newVal * 0.15) + 'px))';
    };

    /**
     * Append an off-screen <h2> that describes the slider for screen readers.
     *
     * @param {HTMLInputElement} range
     * @param {HTMLElement} bubble
     * @param {{leftlabel: HTMLElement, middlelabel: HTMLElement, rightlabel: HTMLElement}} labels
     */
    var createAccessibilityHeading = function(range, bubble, labels) {
        var min = range.min ? range.min : 0;
        var max = range.max ? range.max : 100;
        var step = range.step ? range.step : 1;
        var centerValues = calculateCenterValues(range);
        var accesshideElement = document.createElement('h2');
        accesshideElement.classList.add('accesshide');

        var a = {
            min: min,
            max: max,
            leftlabel: labels.leftlabel.innerHTML,
            rightlabel: labels.rightlabel.innerHTML,
            middlelabel: labels.middlelabel.innerHTML,
            centreval: centerValues[0],
            centreval1: centerValues[0],
            centreval2: centerValues[1]
        };

        var rangeNum = max - min;
        var numSteps = rangeNum / step;
        var middleLabel = (numSteps % 2 !== 0)
            ? (labels.middlelabel.innerHTML
                ? M.util.get_string('middlepartwithtwovalues', 'questionnaire', a)
                : M.util.get_string('middlepartwithtwovaluesdefault', 'questionnaire', a))
            : (labels.middlelabel.innerHTML
                ? M.util.get_string('middlepart', 'questionnaire', a)
                : M.util.get_string('middlepartdefault', 'questionnaire', a));
        var leftPart = labels.leftlabel.innerHTML
            ? M.util.get_string('leftpart', 'questionnaire', a)
            : M.util.get_string('leftpartdefault', 'questionnaire', a);
        var middlePart = middleLabel ? middleLabel : '';
        var rightPart = labels.rightlabel.innerHTML
            ? M.util.get_string('rightpart', 'questionnaire', a)
            : M.util.get_string('rightpartdefault', 'questionnaire', a);

        // Whitespace at the start accommodates an NVDA screen-reader setting.
        var whitespace = '\xa0';
        accesshideElement.textContent = whitespace + M.util.get_string('where', 'questionnaire', a) +
            leftPart + middlePart + rightPart;
        bubble.appendChild(accesshideElement);
    };

    return {
        /**
         * Initialise every .question-slider on the page.
         */
        init: function() {
            var allRanges = document.querySelectorAll('.question-slider');
            allRanges.forEach(function(wrap) {
                var range = wrap.querySelector('input.questionnaire-slider');
                var bubble = wrap.querySelector('.bubble');
                var labels = {
                    leftlabel: wrap.querySelector('.left-side-label'),
                    middlelabel: wrap.querySelector('.middle-side-label'),
                    rightlabel: wrap.querySelector('.right-side-label')
                };
                range.addEventListener('input', function() {
                    setBubble(range, bubble);
                    createAccessibilityHeading(range, bubble, labels);
                });
                setBubble(range, bubble);
                createAccessibilityHeading(range, bubble, labels);
            });
        }
    };
});
