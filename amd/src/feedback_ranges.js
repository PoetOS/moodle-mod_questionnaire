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
 * Keeps each feedback range's "From X%" in step with the boundary of the range above it.
 *
 * @module     mod_questionnaire/feedback_ranges
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Read a boundary as a number, allowing a trailing '%'.
 *
 * @param {string} value
 * @returns {number|null}
 */
const parseBoundary = (value) => {
    const text = value.trim().replace(/%$/, '').trim().replace(',', '.');
    if (text === '' || isNaN(text)) {
        return null;
    }
    return parseFloat(text);
};

/**
 * Recalculate every range's starting value.
 *
 * @param {HTMLFormElement} form
 */
const refresh = (form) => {
    const starts = form.querySelectorAll('[data-region="rangefrom"]');
    const boundaries = form.querySelectorAll('input[name^="feedbackboundaries["]');
    let from = '100%';
    starts.forEach((start, index) => {
        start.textContent = from;
        const boundary = boundaries[index] ? parseBoundary(boundaries[index].value) : null;
        from = (boundary === null) ? '…' : `${boundary}%`;
    });
};

/**
 * Watch the form's boundary fields.
 *
 * @param {string} formId The id of the section editor form.
 */
export const init = (formId) => {
    const form = document.getElementById(formId);
    if (!form) {
        return;
    }
    form.addEventListener('input', (e) => {
        if (e.target.matches('input[name^="feedbackboundaries["]')) {
            refresh(form);
        }
    });
};
