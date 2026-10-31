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
 * mod_questionnaire\local\feedback\chart_renderer::render(), which puts it in the
 * data-chart-spec attribute of the element wrapping the canvases and dispatches
 * $PAGE->requires->js_call_amd('mod_questionnaire/chart', 'init', [elementId]).
 *
 * spec.rgraph lists the RGraph script files, in load order, that the charts need.
 * The module loads them itself, so they never block the page, and holds a pending
 * marker until the charts are drawn.
 *
 * Each entry in spec.charts describes one canvas to draw:
 *   {
 *     canvasId: 'questionnaire-chart-1-primary',
 *     type:     'Bipolar' | 'VProgress' | 'HBar' | 'Radar' | 'Rose',
 *     args:     [...]      // additional constructor args after canvasId
 *     sets:     [['chart.title', 'Your response'], ['chart.xmax', 100], ...]
 *   }
 *
 * @module     mod_questionnaire/chart
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/config', 'core/pending', 'core/notification'], function(Config, Pending, Notification) {
    /** @var {Object<string, Promise>} One load per RGraph script file, shared by every chart on the page. */
    var scripts = {};

    /**
     * Load one RGraph script file, once per page.
     *
     * @param {string} name File name in mod/questionnaire/javascript/RGraph/.
     * @return {Promise}
     */
    var loadScript = function(name) {
        if (!scripts[name]) {
            scripts[name] = new Promise(function(resolve, reject) {
                var script = document.createElement('script');
                script.src = Config.wwwroot + '/mod/questionnaire/javascript/RGraph/' + name;
                script.onload = resolve;
                script.onerror = function() {
                    reject(new Error('Could not load ' + script.src));
                };
                document.head.appendChild(script);
            });
        }
        return scripts[name];
    };

    /**
     * Load the RGraph script files in order; each chart script extends RGraph.common.core.
     *
     * @param {Array<string>} names
     * @return {Promise}
     */
    var loadScripts = function(names) {
        return names.reduce(function(previous, name) {
            return previous.then(function() {
                return loadScript(name);
            });
        }, Promise.resolve());
    };

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
         * Load the RGraph scripts named in an element's chart spec, then draw every chart in it.
         *
         * @param {string} elementId Id of the element whose data-chart-spec holds
         *     {rgraph: Array<string>, charts: Array<Object>}.
         */
        init: function(elementId) {
            var element = document.getElementById(elementId);
            if (!element) {
                return;
            }
            var spec = JSON.parse(element.dataset.chartSpec || '{}');
            if (!Array.isArray(spec.charts)) {
                return;
            }
            var pending = new Pending('mod_questionnaire/chart:init');
            loadScripts(spec.rgraph || [])
                .then(function() {
                    spec.charts.forEach(drawOne);
                    return pending.resolve();
                })
                .catch(function(error) {
                    pending.resolve();
                    Notification.exception(error);
                });
        }
    };
});
