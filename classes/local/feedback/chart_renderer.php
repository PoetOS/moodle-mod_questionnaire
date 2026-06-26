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

/**
 * RGraph chart-rendering helper for the feedback scoreboard.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

/**
 * Builds the canvas markup + RGraph spec consumed by feedback::build_scoreboard().
 *
 * Emits one or two <canvas> elements with unique ids, registers the RGraph
 * third-party scripts on the page, and queues the mod_questionnaire/chart AMD
 * module with a declarative spec describing the chart(s) to draw. The AMD
 * module then constructs the RGraph objects and applies the spec's Set() ops.
 *
 * Data preparation (label padding, color choice, dimensions, RGraph option
 * values) stays in PHP so we don't have to reimplement core_text-aware
 * unicode handling in the JS module.
 */
class chart_renderer {
    /** @var string[] Gradient color sequence used by hbar charts. */
    private const CHART_COLORS_GRADIENT = [
        'Gradient(white:blue)', 'Gradient(white:red)', 'Gradient(white:green)', 'Gradient(white:pink)',
        'Gradient(white:yellow)', 'Gradient(white:cyan)', 'Gradient(white:navy)',
        'Gradient(white:gray)', 'Gradient(white:black)',
    ];

    /** @var string[] Gradient color sequence used by rose charts. */
    private const CHART_COLORS_ROSE = [
        'Gradient(white:red)', 'Gradient(white:green)', 'Gradient(white:blue)',
        'Gradient(white:gray)', 'Gradient(white:purple)', 'Gradient(white:pink)',
        'Gradient(white:orange)', 'Gradient(white:black)',
    ];

    /** @var array charttype => RGraph constructor name. */
    private const RGRAPH_CTOR = [
        'bipolar'   => 'Bipolar',
        'hbar'      => 'HBar',
        'radar'     => 'Radar',
        'rose'      => 'Rose',
        'vprogress' => 'VProgress',
    ];

    /** @var array charttype => filename in javascript/RGraph/ that defines the constructor. */
    private const RGRAPH_SCRIPT = [
        'bipolar'   => 'RGraph.bipolar.js',
        'hbar'      => 'RGraph.hbar.js',
        'radar'     => 'RGraph.radar.js',
        'rose'      => 'RGraph.rose.js',
        'vprogress' => 'RGraph.vprogress.js',
    ];

    /** @var int Monotonic counter for unique canvas ids within a request. */
    private static int $counter = 0;

    /**
     * Render an RGraph chart for the feedback scoreboard.
     *
     * Builds canvas markup, registers the RGraph third-party scripts on $page,
     * and queues mod_questionnaire/chart::render via $PAGE->requires->js_call_amd
     * with a declarative spec describing the chart(s) to draw.
     *
     * @param \moodle_page $page The page to register JS requirements on (usually $PAGE).
     * @param string $feedbacktype 'global' or 'sections'.
     * @param array $labels Chart axis labels (per-section labels, or [] for global).
     * @param string $groupname Current group name shown in chart title 2.
     * @param bool $allresponses True when rendering all responses (vs. compare-one-to-group).
     * @param string|null $charttype 'bipolar', 'hbar', 'radar', 'rose', 'vprogress'.
     * @param array|null $score The "this response" series.
     * @param array|null $allscore The "group" series.
     * @param string|null $globallabel Label used in 'global' feedbacktype mode.
     * @param string $charttitle First-chart title text (e.g. "Your response").
     * @return string Canvas HTML ready to be inserted into the scoreboard.
     */
    public static function render(
        \moodle_page $page,
        $feedbacktype,
        $labels,
        $groupname,
        $allresponses,
        $charttype = null,
        $score = null,
        $allscore = null,
        $globallabel = null,
        $charttitle = ''
    ): string {
        if (!isset(self::RGRAPH_CTOR[$charttype])) {
            return '';
        }

        $charttitle2 = $groupname;
        $charttitlefont = 'Verdana';
        $charttitlesize = 10;

        if ($allresponses) {
            $nbvalues = count($allscore);
        } else {
            $nbvalues = count($score);
        }
        $nblabels = count($labels);

        if ($feedbacktype == 'global') {
            $labels = [$globallabel];
        }

        $base = 'questionnaire-chart-' . (++self::$counter);
        $canvasidprimary = $base . '-primary';
        $canvasidsecondary = $base . '-secondary';

        $primary = null;
        $secondary = null;
        $primaryhtml = '';
        $secondaryhtml = '';

        switch ($charttype) {
            case 'bipolar':
                [$primary, $secondary, $primaryhtml, $secondaryhtml] = self::build_bipolar(
                    $canvasidprimary,
                    $canvasidsecondary,
                    $feedbacktype,
                    $labels,
                    $charttitle,
                    $charttitle2,
                    $charttitlefont,
                    $charttitlesize,
                    $allresponses,
                    $score,
                    $allscore,
                    $nbvalues,
                    $nblabels
                );
                break;

            case 'hbar':
                [$primary, $secondary, $primaryhtml, $secondaryhtml] = self::build_hbar(
                    $canvasidprimary,
                    $canvasidsecondary,
                    $feedbacktype,
                    $labels,
                    $globallabel,
                    $charttitle,
                    $charttitle2,
                    $charttitlefont,
                    $charttitlesize,
                    $allresponses,
                    $score,
                    $allscore,
                    $nbvalues,
                    $nblabels
                );
                break;

            case 'radar':
                [$primary, $secondary, $primaryhtml, $secondaryhtml] = self::build_radar(
                    $canvasidprimary,
                    $canvasidsecondary,
                    $labels,
                    $charttitle,
                    $charttitle2,
                    $allresponses,
                    $score,
                    $allscore
                );
                break;

            case 'rose':
                [$primary, $secondary, $primaryhtml, $secondaryhtml] = self::build_rose(
                    $canvasidprimary,
                    $canvasidsecondary,
                    $labels,
                    $charttitle,
                    $charttitle2,
                    $charttitlefont,
                    $charttitlesize,
                    $allresponses,
                    $score,
                    $allscore,
                    $nblabels
                );
                break;

            case 'vprogress':
                [$primary, $secondary, $primaryhtml, $secondaryhtml] = self::build_vprogress(
                    $canvasidprimary,
                    $canvasidsecondary,
                    $globallabel,
                    $charttitle,
                    $charttitle2,
                    $charttitlefont,
                    $charttitlesize,
                    $allresponses,
                    $score,
                    $allscore
                );
                break;
        }

        $charts = [];
        if ($primary !== null) {
            $charts[] = $primary;
        }
        if ($secondary !== null) {
            $charts[] = $secondary;
        }
        if (empty($charts)) {
            return '';
        }

        self::queue_rgraph($page, $charttype);
        $page->requires->js_call_amd('mod_questionnaire/chart', 'render', [['charts' => $charts]]);

        return $primaryhtml . $secondaryhtml;
    }

    // Private helpers.

    /**
     * Register the common RGraph script + the charttype-specific RGraph script on $page.
     *
     * Loaded via $page->requires->js() (not AMD) because the RGraph library is a
     * third-party global that the AMD chart driver reads from window.RGraph.
     *
     * @param \moodle_page $page
     * @param string $charttype
     */
    private static function queue_rgraph(\moodle_page $page, string $charttype): void {
        $page->requires->js('/mod/questionnaire/javascript/RGraph/RGraph.common.core.js');
        $page->requires->js('/mod/questionnaire/javascript/RGraph/' . self::RGRAPH_SCRIPT[$charttype]);
    }

    /**
     * Construct a single chart spec entry.
     *
     * @param string $type RGraph constructor name (e.g. 'Bipolar').
     * @param string $canvasid
     * @param array $ctorargs Additional positional args passed after canvasId.
     * @param array $sets List of [key, value] pairs for chart.Set() calls.
     * @return array
     */
    private static function spec(string $type, string $canvasid, array $ctorargs, array $sets): array {
        return [
            'canvasId' => $canvasid,
            'type'     => $type,
            'args'     => $ctorargs,
            'sets'     => $sets,
        ];
    }

    /**
     * Emit a <canvas> tag with the given dimensions.
     *
     * @param string $id
     * @param int $width
     * @param int $height
     * @return string
     */
    private static function canvas(string $id, int $width, int $height): string {
        return '<canvas id="' . $id . '" width="' . $width . '" height="' . $height . '">[No canvas support]</canvas>';
    }

    /**
     * Build the bipolar chart primary/secondary specs and canvases.
     *
     * @return array [primary spec|null, secondary spec|null, primary html, secondary html]
     */
    private static function build_bipolar(
        string $canvasidprimary,
        string $canvasidsecondary,
        string $feedbacktype,
        array $labels,
        string $charttitle,
        string $charttitle2,
        string $charttitlefont,
        int $charttitlesize,
        bool $allresponses,
        ?array $score,
        ?array $allscore,
        int $nbvalues,
        int $nblabels
    ): array {
        $oppositescore = null;
        $alloppositescore = null;

        if ($feedbacktype == 'global') {
            if ($score) {
                if ($allresponses) {
                    $score = null;
                } else {
                    $original = $score;
                    $score = [$original[0]];
                    $oppositescore = [$original[1]];
                }
            }
            if ($allscore) {
                $original = $allscore;
                $allscore = [$original[0]];
                $alloppositescore = [$original[1]];
            }
            $nblabels = 1.5;     // For a single horizontal bar with 1.5 height.
            $nbvalues = 1;       // Only one hbar.
        } else {
            if ($score) {
                $oppositescore = [];
                foreach ($score as $sc) {
                    $oppositescore[] = 100 - $sc;
                }
            }
            if ($allscore) {
                $alloppositescore = [];
                foreach ($allscore as $sc) {
                    $alloppositescore[] = 100 - $sc;
                }
            }
        }

        // Pad each label so the left and right segments line up around the pipe separator.
        foreach ($labels as $key => $label) {
            $lb = explode('|', $label);
            if (count($lb) > 1) {
                $left = $lb[0];
                $right = $lb[1];
                $lenleft = \core_text::strlen($left);
                $diffleft = strlen($left) - $lenleft;
                $lenright = \core_text::strlen($right);
                $diffright = strlen($right) - $lenright;
                if ($lenleft < $lenright) {
                    $left = str_pad($left, $lenright + $diffleft, ' ', STR_PAD_LEFT);
                }
                if ($lenleft > $lenright) {
                    $right = str_pad($right, $lenleft + $diffright, ' ', STR_PAD_RIGHT);
                }
                $labels[$key] = $left . ' ' . $right;
            }
        }
        $maxlen = 0;
        foreach ($labels as $label) {
            $maxlen = max($maxlen, \core_text::strlen($label));
        }

        $chartcolors = [];
        $chartcolors2 = [];
        if ($score) {
            for ($i = 0; $i < $nbvalues; $i++) {
                if ($score[$i] != 0) {
                    $chartcolors[] = 'lightgreen';
                }
            }
            for ($i = $nbvalues; $i < $nbvalues * 2; $i++) {
                $chartcolors[] = 'pink';
            }
        }
        if ($allscore) {
            for ($i = 0; $i < $nbvalues; $i++) {
                if ($allscore[$i] != 0) {
                    $chartcolors2[] = 'lightgreen';
                }
            }
            for ($i = $nbvalues; $i < $nbvalues * 2; $i++) {
                $chartcolors2[] = 'pink';
            }
        }

        $canvasheight = (int)(($nblabels * 25) + 60);
        $canvaswidth = max(300, (int)(100 + ($maxlen * 7)));

        $primary = null;
        $primaryhtml = '';
        if (!$allresponses && $score) {
            $primaryhtml = self::canvas($canvasidprimary, $canvaswidth, $canvasheight);
            $primary = self::spec('Bipolar', $canvasidprimary, [$score, $oppositescore], [
                ['chart.title', $charttitle],
                ['chart.title.font', $charttitlefont],
                ['chart.title.size', (string)$charttitlesize],
                ['chart.labels', array_values($labels)],
                ['chart.gutter.center', 0],
                ['chart.gutter.left', 10],
                ['chart.gutter.top', 40],
                ['chart.gutter.bottom', 20],
                ['chart.xmax', 100],
                ['chart.text.size', 10],
                ['chart.text.font', 'Courier'],
                ['chart.colors', $chartcolors],
                ['chart.colors.sequential', true],
            ]);
        }

        $secondary = null;
        $secondaryhtml = '';
        if ($allscore) {
            $secondaryhtml = self::canvas($canvasidsecondary, $canvaswidth, $canvasheight);
            $secondary = self::spec('Bipolar', $canvasidsecondary, [$allscore, $alloppositescore], [
                ['chart.title', $charttitle2],
                ['chart.title.font', $charttitlefont],
                ['chart.title.size', (string)$charttitlesize],
                ['chart.labels', array_values($labels)],
                ['chart.gutter.center', 0],
                ['chart.gutter.left', 10],
                ['chart.gutter.right', 15],
                ['chart.gutter.top', 40],
                ['chart.gutter.bottom', 20],
                ['chart.xmax', 100],
                ['chart.text.size', 10],
                ['chart.text.font', 'Courier'],
                ['chart.colors', $chartcolors2],
                ['chart.colors.sequential', true],
            ]);
        }

        return [$primary, $secondary, $primaryhtml, $secondaryhtml];
    }

    /**
     * Build the hbar chart primary/secondary specs and canvases.
     *
     * @return array [primary spec|null, secondary spec|null, primary html, secondary html]
     */
    private static function build_hbar(
        string $canvasidprimary,
        string $canvasidsecondary,
        string $feedbacktype,
        array $labels,
        ?string $globallabel,
        string $charttitle,
        string $charttitle2,
        string $charttitlefont,
        int $charttitlesize,
        bool $allresponses,
        ?array $score,
        ?array $allscore,
        int $nbvalues,
        int $nblabels
    ): array {
        $sequential = true;

        if ($feedbacktype == 'global') {
            $lb = explode('|', (string)$globallabel);
            if (count($lb) > 1) {
                $labels = [];
                $left = $lb[0];
                $right = $lb[1];
                $lenleft = \core_text::strlen($left);
                $lenright = \core_text::strlen($right);
                if ($lenleft < $lenright) {
                    $left = str_pad($left, $lenright, ' ', STR_PAD_LEFT);
                }
                if ($lenleft > $lenright) {
                    $right = str_pad($right, $lenleft, ' ', STR_PAD_RIGHT);
                }
                $labels[0] = $left;
                $labels[1] = $right;
            }
        } else {
            if ($nblabels > $nbvalues) {
                for ($i = 1; $i < $nblabels - 1; $i++) {
                    unset($labels[$i]);
                }
            }
            $sequential = false;
        }
        $nblabels = count($labels) + 1;

        $maxlen = 0;
        foreach ($labels as $label) {
            $maxlen = max($maxlen, \core_text::strlen($label));
        }
        $labels = array_values($labels);

        $canvasheight = (int)(($nblabels * 20) + 60);
        $gutterleft = (int)(($maxlen * 8) + 5);
        $canvaswidth = 400 + $gutterleft;

        $commonsets = function (string $title) use ($labels, $charttitlefont, $charttitlesize, $gutterleft, $sequential): array {
            return [
                ['chart.title', $title],
                ['chart.title.font', $charttitlefont],
                ['chart.title.size', (string)$charttitlesize],
                ['chart.title.x', 400],
                ['gutter.left', (string)$gutterleft],
                ['gutter.right', 2],
                ['chart.text.font', 'Courier'],
                ['labels', $labels],
                ['chart.colors', self::CHART_COLORS_GRADIENT],
                ['chart.colors.sequential', $sequential],
                ['xmax', 100],
            ];
        };

        $primary = null;
        $primaryhtml = '';
        if (!$allresponses && $score) {
            $primaryhtml = self::canvas($canvasidprimary, $canvaswidth, $canvasheight);
            $primary = self::spec('HBar', $canvasidprimary, [$score], $commonsets($charttitle));
        }

        $secondary = null;
        $secondaryhtml = '';
        if ($allscore) {
            $secondaryhtml = self::canvas($canvasidsecondary, $canvaswidth, $canvasheight);
            $secondary = self::spec('HBar', $canvasidsecondary, [$allscore], $commonsets($charttitle2));
        }

        return [$primary, $secondary, $primaryhtml, $secondaryhtml];
    }

    /**
     * Build the radar chart primary/secondary specs and canvases.
     *
     * @return array [primary spec|null, secondary spec|null, primary html, secondary html]
     */
    private static function build_radar(
        string $canvasidprimary,
        string $canvasidsecondary,
        array $labels,
        string $charttitle,
        string $charttitle2,
        bool $allresponses,
        ?array $score,
        ?array $allscore
    ): array {
        foreach ($labels as $key => $label) {
            if ($key != 0) {
                $labels[$key] = wordwrap($label, 20, "\r\n");
            } else {
                $labels[$key] = $label . "\r\n";
            }
        }
        $labels = array_values($labels);

        $commonsets = function (string $title) use ($labels): array {
            return [
                ['chart.title', $title],
                ['chart.labels', $labels],
                ['chart.labels.offset', 15],
                ['chart.radius', 150],
                ['chart.ymax', 100],
                ['chart.labels.axes', 'n'],
            ];
        };

        $primary = null;
        $primaryhtml = '';
        if (!$allresponses && $score) {
            $primaryhtml = self::canvas($canvasidprimary, 550, 400);
            $primary = self::spec('Radar', $canvasidprimary, [$score], $commonsets($charttitle));
        }

        $secondary = null;
        $secondaryhtml = '';
        if ($allscore) {
            $secondaryhtml = self::canvas($canvasidsecondary, 550, 400);
            $secondary = self::spec('Radar', $canvasidsecondary, [$allscore], $commonsets($charttitle2));
        }

        return [$primary, $secondary, $primaryhtml, $secondaryhtml];
    }

    /**
     * Build the rose chart primary/secondary specs and canvases.
     *
     * @return array [primary spec|null, secondary spec|null, primary html, secondary html]
     */
    private static function build_rose(
        string $canvasidprimary,
        string $canvasidsecondary,
        array $labels,
        string $charttitle,
        string $charttitle2,
        string $charttitlefont,
        int $charttitlesize,
        bool $allresponses,
        ?array $score,
        ?array $allscore,
        int $nblabels
    ): array {
        foreach ($labels as $key => $label) {
            $labels[$key] = wordwrap($label, 8, "\r\n");
        }
        $labels = array_values($labels);

        $commonsets = function (string $title) use ($labels, $charttitlefont, $charttitlesize, $nblabels): array {
            return [
                ['chart.title', $title],
                ['chart.title.font', $charttitlefont],
                ['chart.title.size', (string)$charttitlesize],
                ['chart.title.vpos', 0.2],
                ['chart.labels', $labels],
                ['chart.labels.offset', 10],
                ['chart.background.grid.spokes', $nblabels],
                ['chart.labels.axes', 'n'],
                ['chart.radius', 100],
                ['chart.ymax', 100],
                ['chart.background.axes', false],
                ['chart.colors.sequential', true],
                ['chart.colors', self::CHART_COLORS_ROSE],
            ];
        };

        $primary = null;
        $primaryhtml = '';
        if (!$allresponses && $score) {
            $primaryhtml = self::canvas($canvasidprimary, 400, 400);
            $primary = self::spec('Rose', $canvasidprimary, [$score], $commonsets($charttitle));
        }

        $secondary = null;
        $secondaryhtml = '';
        if ($allscore) {
            // The original PHP injected three &nbsp; characters between the two rose canvases.
            $secondaryhtml = '&nbsp;&nbsp;&nbsp;' . self::canvas($canvasidsecondary, 400, 400);
            $secondary = self::spec('Rose', $canvasidsecondary, [$allscore], $commonsets($charttitle2));
        }

        return [$primary, $secondary, $primaryhtml, $secondaryhtml];
    }

    /**
     * Build the vprogress chart primary/secondary specs and canvases.
     *
     * @return array [primary spec|null, secondary spec|null, primary html, secondary html]
     */
    private static function build_vprogress(
        string $canvasidprimary,
        string $canvasidsecondary,
        ?string $globallabel,
        string $charttitle,
        string $charttitle2,
        string $charttitlefont,
        int $charttitlesize,
        bool $allresponses,
        ?array $score,
        ?array $allscore
    ): array {
        $primaryscore = (!$allresponses && $score) ? $score[0] : null;
        $secondaryscore = $allscore ? $allscore[0] : null;

        // Check presence of pipe separator in label.
        $labels = [];
        $maxlen = 0;
        $lb = explode('|', (string)$globallabel);
        if (count($lb) > 1) {
            $labels = array_reverse($lb);
            foreach ($labels as $label) {
                $maxlen = max($maxlen, \core_text::strlen($label));
            }
        }

        $gutterright = 150 + ($maxlen * 3);
        $canvaswidth = 250 + ($maxlen * 3);

        $primary = null;
        $primaryhtml = '';
        if (!$allresponses && $primaryscore !== null) {
            $primaryhtml = self::canvas($canvasidprimary, $canvaswidth, 400);
            $primarysets = [
                ['chart.gutter.top', 30],
                ['chart.gutter.left', 50],
                ['chart.gutter.right', (string)$gutterright],
                ['scale.decimals', 0],
                ['chart.text.font', 'Courier'],
                ['chart.title', $charttitle],
                ['chart.title.font', $charttitlefont],
                ['chart.title.size', (string)$charttitlesize],
            ];
            if (!empty($labels)) {
                $primarysets[] = ['chart.labels.specific', $labels];
            }
            $primary = self::spec('VProgress', $canvasidprimary, [$primaryscore, 100], $primarysets);
        }

        $secondary = null;
        $secondaryhtml = '';
        if ($secondaryscore !== null) {
            $secondaryhtml = self::canvas($canvasidsecondary, 250, 400);
            $secondary = self::spec('VProgress', $canvasidsecondary, [$secondaryscore, 100], [
                ['chart.gutter.top', 30],
                ['chart.gutter.left', 50],
                ['chart.gutter.right', 150],
                ['chart.title', $charttitle2],
                ['chart.text.font', 'Courier'],
                ['chart.title.font', $charttitlefont],
                ['chart.title.size', (string)$charttitlesize],
                ['scale.decimals', 0],
            ]);
        }

        return [$primary, $secondary, $primaryhtml, $secondaryhtml];
    }
}
