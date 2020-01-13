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
        $table = new html_table();

        $table->align = array('', '', 'center', 'right');
        $table->width = '    99%';
        if ($isna) {
            $table->head = array('', $stravg, '&dArr;', $isnahead);
        } else {
            if ($osgood) {
                $stravg = '<div style="text-align:center">'.$stravgrank.'</div>';
                $table->head = array('', $stravg, '');
            } else {
                $table->head = array('', $stravg, '&dArr;');
            }
        }
        // TODO JR please calculate the correct width of the question text column (col #1).
        $rightcolwidth = '5%';
        $table->size = array('60%', '*', $rightcolwidth);
        if ($isna) {
            $table->size = array('55%', '*', $rightcolwidth, $rightcolwidth);
        }
        if ($osgood) {
            $table->size = array('25%', '50%', '25%');
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
        $nbchoices = $question->length;
        for ($j = 0; $j < $question->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j + 1;
            }
        }
        $out = '<table style="width:100%" cellpadding="2" cellspacing="0" border="1"><tr>';
        for ($i = 0; $i <= $llength - 1; $i++) {
            if (isset($n[$i])) {
                $str = $n[$i];
            } else {
                $str = $i + 1;
            }
            if ($isrestricted && $i == $llength - 1) {
                $str = "...";
            }
            $out .= '<td style="text-align: center; width:'.$width.'%" class="smalltext">'.$str.'</td>';
        }
        $out .= '</tr></table>';
        if (!$isna) {
            $table->data[] = array('', $out, '');
        } else {
            $table->data[] = array('', $out, '', '');
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
                        $out = '';
                        if (($j = $avg * $width) > 0) {
                            $marginposition = ($avg - 0.5 ) / ($question->length + $isrestricted) * 100;
                        }
                        if (!right_to_left()) {
                            $out .= '<img style="height:12px; width: 6px; margin-left: '.$marginposition.
                                '%;" alt="" src="'.$imageurl.'hbar.gif" />';
                        } else {
                            $out .= '<img style="height:12px; width: 6px; margin-right: '.$marginposition.
                                '%;" alt="" src="'.$imageurl.'hbar.gif" />';
                        }
                    } else {
                            $out = '';
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
                        $table->data[] = array('<div class="mdl-right">'.
                            format_text($content, FORMAT_HTML, ['noclean' => true]).'</div>', $out,
                            '<div class="mdl-left">'.format_text($contentright, FORMAT_HTML, ['noclean' => true]).'</div>');
                        // JR JUNE 2012 do not display meaningless average rank values for Osgood.
                    } else {
                        if ($avg) {
                            $stravgval = '';
                            if ($stravgvalue) {
                                $stravgval = '('.sprintf('%.1f', $avgvalue).')';
                            }
                            if ($isna) {
                                $table->data[] = [format_text($content, FORMAT_HTML, ['noclean' => true]), $out,
                                    sprintf('%.1f', $avg).'&nbsp;'.$stravgval, $nbna];
                            } else {
                                $table->data[] = [format_text($content, FORMAT_HTML, ['noclean' => true]), $out,
                                    sprintf('%.1f', $avg).'&nbsp;'.$stravgval];
                            }
                        } else if ($nbna != 0) {
                            $table->data[] = array(format_text($content, FORMAT_HTML, ['noclean' => true]), $out, '', $nbna);
                        }
                    }
                } // End if named degrees.
            } // End foreach.
        } else {
            $table->data[] = array('', get_string('noresponsedata', 'questionnaire'));
        }
        return html_writer::table($table);
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
        $isnahead = '';
        $osgood = false;
        if ($question->precise == 3) { // Osgood's semantic differential.
            $osgood = true;
        }
        if ($isna) {
            $isnahead = get_string('notapplicable', 'questionnaire').'<br />(#)';
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

        $headings = array('<span class="smalltext">'.get_string('responses', 'questionnaire').'</span>');
        if ($osgood) {
            $align = array('right');
        } else {
            $align = array('left');
        }

        // Display the column titles.
        for ($j = 0; $j < $question->length; $j++) {
            if (isset($n[$j])) {
                $str = $n[$j];
            } else {
                $str = $j + 1;
            }
            array_push($headings, '<span class="smalltext">'.$str.'</span>');
            array_push($align, 'center');
        }
        if ($osgood) {
            array_push($headings, '');
            array_push($align, 'left');
        }
        array_push($headings, $strtotal);
        if ($isrestricted) {
            array_push($headings, get_string('notapplicable', 'questionnaire'));
            array_push($align, 'center');
        }
        array_push($align, 'center');
        if ($na) {
            array_push($headings, $na);
            array_push($align, 'center');
        }

        $table = new html_table();
        $table->head = $headings;
        $table->align = $align;
        $table->attributes['class'] = 'generaltable';
        // Now display the responses.
        foreach ($ranks as $content => $rank) {
            $data = array();
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
                    $data[] = format_text($content, FORMAT_HTML, ['noclean' => true]);
                } else {
                    // Eliminate potentially short-named choices.
                    $contents = questionnaire_choice_values($content);
                    if ($contents->modname) {
                        $content = $contents->text;
                    }
                    $data[] = format_text($content, FORMAT_HTML, ['noclean' => true]);
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
                    $data[] = $str.$percent;
                }
                if ($osgood) {
                    $data[] = format_text($contentright, FORMAT_HTML, ['noclean' => true]);
                }
                $data[] = $nbresp;
                if ($isrestricted) {
                    $data[] = $nbresponses - $total;
                }
                if (!$osgood) {
                    if ($na) {
                        $data[] = $nbna;
                    }
                }
            } // End named degrees.
            $table->data[] = $data;
        }
        return html_writer::table($table);
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
