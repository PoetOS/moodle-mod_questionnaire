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
 * Drag-and-drop reordering for the manage-questions page.
 *
 * Wires core/sortable_list onto the question list; on drop, persists the new order via
 * the mod_questionnaire_question_reorder AJAX external. The server is the source of truth
 * for whether an order is valid (a question cannot be dropped at or before one it depends
 * on) — sortable_list has no way to block an individual drop target, so a rejected drop
 * shows the server's error and reloads to restore the true order. A drop that is accepted
 * but triggers a page-break repair also reloads, so position numbers never lie.
 *
 * Keyboard reordering comes for free from core/drag_handle + core/sortable_list's built-in
 * move dialogue; no separate up/down control is needed.
 *
 * @module     mod_questionnaire/question_manager
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/sortable_list', 'core/ajax', 'core/notification', 'core/str', 'core/toast'],
function(SortableList, Ajax, Notification, Str, Toast) {

    var SELECTORS = {
        list: '[data-region="question-list"]',
        row: '[data-question-id]',
        positionBadge: '[data-region="position-badge"]'
    };

    /**
     * Read the current top-to-bottom question id order from the DOM.
     *
     * @param {Element} listElement
     * @return {String} Comma-separated question ids.
     */
    var getItemOrder = function(listElement) {
        var ids = [];
        listElement.querySelectorAll(SELECTORS.row).forEach(function(row) {
            ids.push(row.getAttribute('data-question-id'));
        });
        return ids.join(',');
    };

    /**
     * Renumber the visible position badges to match the current DOM order.
     *
     * @param {Element} listElement
     */
    var renumberPositions = function(listElement) {
        listElement.querySelectorAll(SELECTORS.row).forEach(function(row, index) {
            var badge = row.querySelector(SELECTORS.positionBadge);
            if (badge) {
                badge.textContent = index + 1;
            }
        });
    };

    return {
        /**
         * Attach the drag-and-drop reorder behaviour to the question list.
         *
         * @param {Number} cmId Course module id, threaded into the AJAX call.
         */
        init: function(cmId) {
            var listElement = document.querySelector(SELECTORS.list);
            if (!listElement) {
                return;
            }

            var sortableList = new SortableList(listElement);
            sortableList.getElementName = function(element) {
                return Promise.resolve(element[0].getAttribute('data-name'));
            };

            document.addEventListener(SortableList.EVENTS.elementDrop, function(event) {
                if (!event.detail.positionChanged) {
                    return;
                }

                var itemOrder = getItemOrder(listElement);
                Ajax.call([{
                    methodname: 'mod_questionnaire_question_reorder',
                    args: {cmid: cmId, itemorder: itemOrder}
                }])[0].then(function(result) {
                    if (result.reload) {
                        // The server added or removed a page break to keep dependencies valid;
                        // the row list is stale and must be re-fetched rather than patched in place.
                        Notification.addNotification({message: result.warnings, type: 'warning'});
                        window.location.reload();
                        return null;
                    }
                    renumberPositions(listElement);
                    return Str.get_string('questionmoved', 'mod_questionnaire');
                }).then(function(string) {
                    if (string) {
                        Toast.add(string);
                    }
                    return null;
                }).catch(function(error) {
                    Notification.exception(error);
                    window.location.reload();
                });
            });
        }
    };
});
