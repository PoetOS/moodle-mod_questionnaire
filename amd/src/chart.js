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
 * RGraph chart driver for the feedback scoreboard.
 *
 * Driven by a declarative spec built in PHP by
 * mod_questionnaire\local\feedback\chart_renderer::render() and dispatched via
 * $PAGE->requires->js_call_amd('mod_questionnaire/chart', 'render', [spec]).
 *
 * Each entry in spec.charts describes one canvas to draw:
 *   {
 *     canvasId: 'questionnaire-chart-1-primary',
 *     type:     'Bipolar' | 'VProgress' | 'HBar' | 'Radar' | 'Rose',
 *     args:     [...]      // additional constructor args after canvasId
 *     sets:     [['chart.title', 'Your response'], ['chart.xmax', 100], ...]
 *   }
 *
 * The module assumes the corresponding RGraph third-party scripts have already
 * been loaded into window.RGraph by the caller (chart_renderer queues these
 * via $PAGE->requires->js() adjacent to the js_call_amd).
 *
 * @module     mod_questionnaire/chart
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Draw one chart from a spec entry.
     *
     * @param {{canvasId: string, type: string, args: Array, sets: Array<[string, *]>}} spec
     */
    var drawOne = function(spec) {
        if (typeof window.RGraph === 'undefined' || typeof window.RGraph[spec.type] !== 'function') {
            return;
        }
        var canvas = document.getElementById(spec.canvasId);
        if (!canvas) {
            return;
        }
        var ctorArgs = [spec.canvasId].concat(spec.args || []);
        var chart = new (Function.prototype.bind.apply(window.RGraph[spec.type], [null].concat(ctorArgs)))();
        (spec.sets || []).forEach(function(pair) {
            chart.Set(pair[0], pair[1]);
        });
        chart.Draw();
    };

    return {
        /**
         * Render every chart in the spec.
         *
         * @param {{charts: Array<Object>}} spec
         */
        render: function(spec) {
            if (!spec || !Array.isArray(spec.charts)) {
                return;
            }
            spec.charts.forEach(drawOne);
        }
    };
});
