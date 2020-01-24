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
 * This file contains the parent class for questionnaire question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\responsetype;
defined('MOODLE_INTERNAL') || die();
use \html_writer;
use \html_table;

/**
 * Class for response display support.
 *
 * @author Mike Churchward
 * @package display_support
 */

class display_support {

    /* {{{ proto void mkresavg(array weights, int total, int precision, bool show_totals)
        Builds HTML showing AVG results. */

    public static function mkresavg($counts, $total, $question, $showtotals, $sort, $stravgvalue='') {
        global $CFG;
        $stravgrank = get_string('averagerank', 'questionnaire');
        $osgood = false;
        if ($question->precise == 3) { // Osgood's semantic differential.
            $osgood = true;
            $stravgrank = get_string('averageposition', 'questionnaire');
        }
        $stravg = '<div style="text-align:right">'.$stravgrank.$stravgvalue.'</div>';

        $isna = $question->precise == 1;
        $isnahead = '';
        $nbchoices = count ($counts);
        $isrestricted = ($question->length < $nbchoices) && $question->precise == 2;

        if ($isna) {
            $isnahead = get_string('notapplicable', 'questionnaire');
        }
        $pagetags = new \stdClass();
        $pagetags->averages = new \stdClass();

        if ($isna) {
            $header1 = new \stdClass();
            $header1->text = '';
            $header1->align = '';
            $header2 = new \stdClass();
            $header2->text = $stravg;
            $header2->align = '';
            $header3 = new \stdClass();
            $header3->text = '&dArr;';
            $header3->align = 'center';
            $header4 = new \stdClass();
            $header4->text = $isnahead;
            $header4->align = 'right';
        } else {
            if ($osgood) {
                $stravg = '<div style="text-align:center">'.$stravgrank.'</div>';
                $header1 = new \stdClass();
                $header1->text = '';
                $header1->align = '';
                $header2 = new \stdClass();
                $header2->text = $stravg;
                $header2->align = '';
                $header3 = new \stdClass();
                $header3->text = '';
                $header3->align = 'center';
            } else {
                $header1 = new \stdClass();
                $header1->text = '';
                $header1->align = '';
                $header2 = new \stdClass();
                $header2->text = $stravg;
                $header2->align = '';
                $header3 = new \stdClass();
                $header3->text = '&dArr;';
                $header3->align = 'center';
            }
        }
        // TODO JR please calculate the correct width of the question text column (col #1).
        $rightcolwidth = '5%';
        if ($isna) {
            $header1->width = '55%';
            $header2->width = '*';
            $header3->width = $rightcolwidth;
            $header4->width = $rightcolwidth;
        }
        if ($osgood) {
            $header1->width = '25%';
            $header2->width = '50%';
            $header3->width = '25%';
            $pagetags->averages->headers = [$header1, $header2, $header3];
        } else {
            $header1->width = '60%';
            $header2->width = '*';
            $header3->width = $rightcolwidth;
        }
        $pagetags->averages->headers = [$header1, $header2, $header3];
        if (isset($header4)) {
            $pagetags->averages->headers[] = $header4;
        }

        $imageurl = $CFG->wwwroot.'/mod/questionnaire/images/';
        $llength = $question->length;
        if (!$llength) {
            $llength = 5;
        }
        // Add an extra column to accomodate lower ranks in this case.
        $llength += $isrestricted;
        $width = 100 / $llength;
        $n = array();
        $nameddegrees = 0;
        foreach ($question->nameddegrees as $degree) {
            // To take into account languages filter.
            $content = (format_text($degree, FORMAT_HTML, ['noclean' => true]));
            $n[$nameddegrees] = $degree;
            $nameddegrees++;
        }
        for ($j = 0; $j < $question->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j + 1;
            }
        }
        $rankcols = [];
        for ($i = 0; $i <= $llength - 1; $i++) {
            if ($isrestricted && $i == $llength - 1) {
                $str = "...";
                $rankcols[] = (object)['width' => $width . '%', 'text' => '...'];
            } else if (isset($n[$i])) {
                $str = $n[$i];
                $rankcols[] = (object)['width' => $width . '%', 'text' => $n[$i]];
            } else {
                $str = $i + 1;
                $rankcols[] = (object)['width' => $width . '%', 'text' => $i + 1];
            }
        }
        $pagetags->averages->choicelabelrow = new \stdClass();
        if (!$isna) {
            $pagetags->averages->choicelabelrow->column1 = (object)['width' => $header1->width, 'align' => $header1->align,
                'text' => ''];
            $pagetags->averages->choicelabelrow->column2 = (object)['width' => $header2->width, 'align' => $header2->align,
                'ranks' => $rankcols];
            $pagetags->averages->choicelabelrow->column3 = (object)['width' => $header3->width, 'align' => $header3->align,
                'text' => ''];
        } else {
            $pagetags->averages->choicelabelrow->column1 = (object)['width' => $header1->width, 'align' => $header1->align,
                'text' => ''];
            $pagetags->averages->choicelabelrow->column2 = (object)['width' => $header2->width, 'align' => $header2->align,
                'ranks' => $rankcols];
            $pagetags->averages->choicelabelrow->column3 = (object)['width' => $header3->width, 'align' => $header3->align,
                'text' => ''];
            $pagetags->averages->choicelabelrow->column4 = (object)['width' => $header4->width, 'align' => $header4->align,
                'text' => ''];
        }

        switch ($sort) {
            case 'ascending':
                uasort($counts, 'self::sortavgasc');
                break;
            case 'descending':
                uasort($counts, 'self::sortavgdesc');
                break;
        }
        reset ($counts);

        if (!empty($counts) && is_array($counts)) {
            $pagetags->averages->choiceaverages = [];
            foreach ($counts as $content => $contentobj) {
                // Eliminate potential named degrees on Likert scale.
                if (!preg_match("/^[0-9]{1,3}=/", $content)) {
                    if (isset($contentobj->avg)) {
                        $avg = $contentobj->avg;
                        // If named degrees were used, swap averages for display.
                        if (isset($contentobj->avgvalue)) {
                            $avg = $contentobj->avgvalue;
                            $avgvalue = $contentobj->avg;
                        } else {
                            $avgvalue = '';
                        }
                    } else {
                        $avg = '';
                    }
                    $nbna = $contentobj->nbna;

                    if ($avg) {
                        if (($j = $avg * $width) > 0) {
                            $marginposition = ($avg - 0.5 ) / ($question->length + $isrestricted) * 100;
                        }
                        if (!right_to_left()) {
                            $margin = 'margin-left:' . $marginposition . '%';
                        } else {
                            $margin = 'margin-right:' . $marginposition . '%';
                        }
                    } else {
                            $margin = '';
                    }

                    if ($osgood) {
                        // Ensure there are two bits of content.
                        list($content, $contentright) = array_merge(preg_split('/[|]/', $content), array(' '));
                    } else {
                        $contents = questionnaire_choice_values($content);
                        if ($contents->modname) {
                            $content = $contents->text;
                        }
                    }
                    if ($osgood) {
                        $choicecol1 = new \stdClass();
                        $choicecol1->width = $header1->width;
                        $choicecol1->align = $header1->align;
                        $choicecol1->text = '<div class="mdl-right">' .
                            format_text($content, FORMAT_HTML, ['noclean' => true]) . '</div>';
                        $choicecol2 = new \stdClass();
                        $choicecol2->width = $header2->width;
                        $choicecol2->align = $header2->align;
                        $choicecol2->image2url = $imageurl . 'hbar.gif';
                        $choicecol2->margin = $margin;
                        $choicecol3 = new \stdClass();
                        $choicecol3->width = $header3->width;
                        $choicecol3->align = $header3->align;
                        $choicecol3->text = '<div class="mdl-left">' .
                            format_text($contentright, FORMAT_HTML, ['noclean' => true]) . '</div>';
                        $pagetags->averages->choiceaverages[] = (object)['column1' => $choicecol1, 'column2' => $choicecol2,
                            'column3' => $choicecol3];
                        // JR JUNE 2012 do not display meaningless average rank values for Osgood.
                    } else {
                        if ($avg) {
                            $stravgval = '';
                            if ($stravgvalue) {
                                $stravgval = '('.sprintf('%.1f', $avgvalue).')';
                            }
                            if ($isna) {
                                $choicecol4 = new \stdClass();
                                $choicecol4->width = $header4->width;
                                $choicecol4->align = $header4->align;
                                $choicecol4->text = $nbna;
                            }
                            $choicecol1 = new \stdClass();
                            $choicecol1->width = $header1->width;
                            $choicecol1->align = $header1->align;
                            $choicecol1->text = format_text($content, FORMAT_HTML, ['noclean' => true]);
                            $choicecol2 = new \stdClass();
                            $choicecol2->width = $header2->width;
                            $choicecol2->align = $header2->align;
                            $choicecol2->image2url = $imageurl . 'hbar.gif';
                            $choicecol2->margin = $margin;
                            $choicecol3 = new \stdClass();
                            $choicecol3->width = $header3->width;
                            $choicecol3->align = $header3->align;
                            $choicecol3->text = sprintf('%.1f', $avg).'&nbsp;'.$stravgval;
                            if (isset($choicecol4)) {
                                $pagetags->averages->choiceaverages[] = (object)['column1' => $choicecol1, 'column2' => $choicecol2,
                                    'column3' => $choicecol3, 'column4' => $choicecol4];
                            } else {
                                $pagetags->averages->choiceaverages[] = (object)['column1' => $choicecol1, 'column2' => $choicecol2,
                                    'column3' => $choicecol3];
                            }
                        } else if ($nbna != 0) {
                            $choicecol1 = new \stdClass();
                            $choicecol1->width = $header1->width;
                            $choicecol1->align = $header1->align;
                            $choicecol1->text = format_text($content, FORMAT_HTML, ['noclean' => true]);
                            $choicecol2 = new \stdClass();
                            $choicecol2->width = $header2->width;
                            $choicecol2->align = $header2->align;
                            $choicecol2->image2url = $imageurl . 'hbar.gif';
                            $choicecol2->margin = $margin;
                            $choicecol3 = new \stdClass();
                            $choicecol3->width = $header3->width;
                            $choicecol3->align = $header3->align;
                            $choicecol3->text = '';
                            $choicecol4 = new \stdClass();
                            $choicecol4->width = $header4->width;
                            $choicecol4->align = $header4->align;
                            $choicecol4->text = $nbna;
                            $pagetags->averages->choiceaverages[] = (object)['column1' => $choicecol1, 'column2' => $choicecol2,
                                'column3' => $choicecol3];
                        }
                    }
                } // End if named degrees.
            } // End foreach.
        } else {
            $nodata1 = new \stdClass();
            $nodata1->width = $header1->width;
            $nodata1->align = $header1->align;
            $nodata1->text = '';
            $nodata2 = new \stdClass();
            $nodata2->width = $header2->width;
            $nodata2->align = $header2->align;
            $nodata2->text = get_string('noresponsedata', 'mod_questionnaire');
            $nodata3 = new \stdClass();
            $nodata3->width = $header3->width;
            $nodata3->align = $header3->align;
            $nodata3->text = '';
            if (isset($header4)) {
                $nodata4 = new \stdClass();
                $nodata4->width = $header4->width;
                $nodata4->align = $header4->align;
                $nodata4->text = '';
                $pagetags->averages->nodata = [$nodata1, $nodata2, $nodata3, $nodata4];
            } else {
                $pagetags->averages->nodata = [$nodata1, $nodata2, $nodata3];
            }
        }
        return $pagetags;
    }

    public static function mkrescount($counts, $rids, $rows, $question, $sort) {
        // Display number of responses to Rate questions - see http://moodle.org/mod/forum/discuss.php?d=185106.
        global $DB;

        $nbresponses = count($rids);
        // Prepare data to be displayed.
        $isrestricted = ($question->length < count($question->choices)) && $question->precise == 2;

        $rsql = '';
        if (!empty($rids)) {
            list($rsql, $params) = $DB->get_in_or_equal($rids);
            $rsql = ' AND response_id ' . $rsql;
        }

        array_unshift($params, $question->id); // This is question_id.
        $sql = 'SELECT r.id, c.content, r.rankvalue, c.id AS choiceid ' .
                'FROM {questionnaire_quest_choice} c , ' .
                     '{questionnaire_response_rank} r ' .
                'WHERE c.question_id = ?' .
                ' AND r.question_id = c.question_id' .
                ' AND r.choice_id = c.id ' .
                $rsql .
                ' ORDER BY choiceid, rankvalue ASC';
        $choices = $DB->get_records_sql($sql, $params);

        // Sort rows (results) by average value.
        if ($sort != 'default') {
            $sortarray = array();
            foreach ($rows as $row) {
                foreach ($row as $key => $value) {
                    if (!isset($sortarray[$key])) {
                        $sortarray[$key] = array();
                    }
                    $sortarray[$key][] = $value;
                }
            }
            $orderby = "average";
            switch ($sort) {
                case 'ascending':
                    array_multisort($sortarray[$orderby], SORT_ASC, $rows);
                    break;
                case 'descending':
                    array_multisort($sortarray[$orderby], SORT_DESC, $rows);
                    break;
            }
        }
        $nbranks = $question->length;
        $ranks = [];
        $rankvalue = [];
        if (!empty($question->nameddegrees)) {
            $rankvalue = array_flip(array_keys($question->nameddegrees));
        }
        foreach ($rows as $row) {
            $choiceid = $row->id;
            foreach ($choices as $choice) {
                if ($choice->choiceid == $choiceid) {
                    $n = 0;
                    for ($i = 1; $i <= $nbranks; $i++) {
                        if ((isset($rankvalue[$choice->rankvalue]) && ($rankvalue[$choice->rankvalue] == ($i - 1))) ||
                            (empty($rankvalue) && ($choice->rankvalue == $i))) {
                            $n++;
                            if (!isset($ranks[$choice->content][$i])) {
                                $ranks[$choice->content][$i] = 0;
                            }
                            $ranks[$choice->content][$i] += $n;
                        } else if (!isset($ranks[$choice->content][$i])) {
                            $ranks[$choice->content][$i] = 0;
                        }
                    }
                }
            }
        }

        // Psettings for display.
        $strtotal = '<strong>'.get_string('total', 'questionnaire').'</strong>';
        $isna = $question->precise == 1;
        $osgood = false;
        if ($question->precise == 3) { // Osgood's semantic differential.
            $osgood = true;
        }
        if ($question->precise == 1) {
            $na = get_string('notapplicable', 'questionnaire');
        } else {
            $na = '';
        }
        $nameddegrees = 0;
        $n = array();
        foreach ($question->nameddegrees as $degree) {
            $content = $degree;
            $n[$nameddegrees] = format_text($content, FORMAT_HTML, ['noclean' => true]);
            $nameddegrees++;
        }
        foreach ($question->choices as $choice) {
            $contents = questionnaire_choice_values($choice->content);
            if ($contents->modname) {
                $choice->content = $contents->text;
            }
        }

        $pagetags = new \stdClass();
        $pagetags->totals = new \stdClass();
        $pagetags->totals->headers = [];
        if ($osgood) {
            $align = 'right';
        } else {
            $align = 'left';
        }
        $pagetags->totals->headers[] = (object)['align' => $align,
            'text' => '<span class="smalltext">'.get_string('responses', 'questionnaire').'</span>'];

        // Display the column titles.
        for ($j = 0; $j < $question->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j + 1;
            }
            $pagetags->totals->headers[] = (object)['align' => 'center', 'text' => '<span class="smalltext">'.$str.'</span>'];
        }
        if ($osgood) {
            $pagetags->totals->headers[] = (object)['align' => 'left', 'text' => ''];
        }
        $pagetags->totals->headers[] = (object)['align' => 'center', 'text' => $strtotal];
        if ($isrestricted) {
            $pagetags->totals->headers[] = (object)['align' => 'center', 'text' => get_string('notapplicable', 'questionnaire')];
        }
        if ($na) {
            $pagetags->totals->headers[] = (object)['align' => 'center', 'text' => $na];
        }

        // Now display the responses.
        $pagetags->totals->choices = [];
        foreach ($ranks as $content => $rank) {
            $totalcols = [];
            // Eliminate potential named degrees on Likert scale.
            if (!preg_match("/^[0-9]{1,3}=/", $content)) {
                // First display the list of degrees (named or un-named)
                // number of NOT AVAILABLE responses for this possible answer.
                $nbna = $counts[$content]->nbna;
                // TOTAL number of responses for this possible answer.
                $total = $counts[$content]->num;
                $nbresp = '<strong>'.$total.'<strong>';
                if ($osgood) {
                    // Ensure there are two bits of content.
                    list($content, $contentright) = array_merge(preg_split('/[|]/', $content), array(' '));
                    $header = reset($pagetags->totals->headers);
                    $totalcols[] = (object)['align' => $header->align,
                        'text' => format_text($content, FORMAT_HTML, ['noclean' => true])];
                } else {
                    // Eliminate potentially short-named choices.
                    $contents = questionnaire_choice_values($content);
                    if ($contents->modname) {
                        $content = $contents->text;
                    }
                    $header = reset($pagetags->totals->headers);
                    $totalcols[] = (object)['align' => $header->align,
                        'text' => format_text($content, FORMAT_HTML, ['noclean' => true])];
                }
                // Display ranks/rates numbers.
                $maxrank = max($rank);
                for ($i = 1; $i <= $question->length; $i++) {
                    $percent = '';
                    if (isset($rank[$i])) {
                        $str = $rank[$i];
                        if ($total !== 0 && $str !== 0) {
                            $percent = ' (<span class="percent">'.number_format(($str * 100) / $total).'%</span>)';
                        }
                        // Emphasize responses with max rank value.
                        if ($str == $maxrank) {
                            $str = '<strong>'.$str.'</strong>';
                        }
                    } else {
                        $str = 0;
                    }
                    $header = next($pagetags->totals->headers);
                    $totalcols[] = (object)['align' => $header->align, 'text' => $str.$percent];
                }
                if ($osgood) {
                    $header = next($pagetags->totals->headers);
                    $totalcols[] = (object)['align' => $header->align,
                        'text' => format_text($contentright, FORMAT_HTML, ['noclean' => true])];
                }
                $header = next($pagetags->totals->headers);
                $totalcols[] = (object)['align' => $header->align, 'text' => $nbresp];
                if ($isrestricted) {
                    $header = next($pagetags->totals->headers);
                    $totalcols[] = (object)['align' => $header->align, 'text' => $nbresponses - $total];
                }
                if (!$osgood) {
                    if ($na) {
                        $header = next($pagetags->totals->headers);
                        $totalcols[] = (object)['align' => $header->align, 'text' => $nbna];
                    }
                }
            } // End named degrees.
            $pagetags->totals->choices[] = (object)['totalcols' => $totalcols];
        }
        return $pagetags;
    }

    /**
     * Sorting functions for ascending and descending.
     *
     */
    static private function sortavgasc($a, $b) {
        if (isset($a->avg) && isset($b->avg)) {
            if ( $a->avg < $b->avg ) {
                return -1;
            } else if ($a->avg > $b->avg ) {
                return 1;
            } else {
                return 0;
            }
        }
    }

    static private function sortavgdesc($a, $b) {
        if (isset($a->avg) && isset($b->avg)) {
            if ( $a->avg > $b->avg ) {
                return -1;
            } else if ($a->avg < $b->avg) {
                return 1;
            } else {
                return 0;
            }
        }
    }
}
