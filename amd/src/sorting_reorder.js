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

/*
 * Generic library to allow things in a vertical list to be re-ordered using drag and drop.
 *
 * To make a set of things draggable, create a new instance of this object passing the
 * necessary config, as explained in the comment on the constructor.
 *
 * @package   mod_questionnaire
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module questionnaire/sorting_reorder
 */
define(['mod_questionnaire/sorting_drag_reorder'], function(DragReorder) {
    return {
        /**
         * Initialise one ordering question.
         */
        init: function() {
            var elements = document.getElementsByClassName('qn-sorting-list');
            elements.forEach(function(element) {
                new DragReorder({
                    list: 'ol#' + element.id,
                    item: 'li.qn-sorting-list__items',
                    proxyHtml: '<div class="qn-sorting-list-dragproxy">' +
                        '<ol class="%%LIST_CLASS_NAME%%"><li class="%%ITEM_CLASS_NAME%% item-moving">' +
                        '%%ITEM_HTML%%</li></ol></div>',
                    itemMovingClass: "current-drop",
                    idGetter: function(item) {
                        return item.getAttribute('id');
                    },
                    nameGetter: function(item) {
                        return item.text;
                    },
                    // eslint-disable-next-line no-unused-vars
                    reorderStart: function(list, item) {
                        // Do nothing.
                    },
                    // eslint-disable-next-line no-unused-vars
                    reorderEnd: function(list, item) {
                        // Do nothing.
                    },
                    // eslint-disable-next-line no-unused-vars
                    reorderDone: function(list, item, newOrder) {
                        // Do nothing.
                    }
                });
            });
        }
    };
});
