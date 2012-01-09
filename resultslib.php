<?php // $Id: resultslib.php,v 1.5 2007/06/12 17:04:48 mchurch Exp $

// Written by James Flemer
// <jflemer@alum.rpi.edu>

// void        mkrespercent(int[char*] counts, int total, bool showTotals);
// void        mkresrank(int[char*] counts, int total, bool showTotals);
// void        mkrescount(int[char*] counts, int total, bool showTotals);
// void        mkreslist(int[char*] counts, int total, bool showTotals);
// void        mkresavg(int[char*] counts, int total, bool showTotals);

/* {{{ proto void mkrespercent(array weights, int total, int precision, bool show_totals)
  Builds HTML showing PERCENTAGE results. */
function mkrespercent($counts,$total,$precision,$showTotals) {
    global $CFG;

    $i=0;
    $bg='';
    $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
?>
<table width="90%" border="0">
<?php
    while(list($content,$num) = each($counts)) {
        if($num>0) { $percent = $num/$total*100.0; }
        else { $percent = 0; }
        if($percent > 100) { $percent = 100; }

        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
?>
    <tr bgcolor="<?php echo($bg); ?>">
        <td><?php echo($content); ?></td>
        <td align="left">
<?php
        if($num) {
            echo("&nbsp;<img src=\"" .$image_url ."hbar_l.gif\" height=9 width=4>");
            printf("<img src=\"" .$image_url ."hbar.gif\" height=9 width=%d>",$percent*2);
            echo("<img src=\"" .$image_url ."hbar_r.gif\" height=9 width=4>");
            printf("&nbsp;%.${precision}f%%",$percent);
        }
?></td>
        <td align="right">(<?php echo($num); ?>)</td>
    </tr>
<?php
        $i += $num;
    } // end while
    if($showTotals) {
        if($i>0) { $percent = $i/$total*100.0; }
        else { $percent = 0; }
        if($percent > 100) { $percent = 100; }

        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
?>
    <tr bgcolor="<?php echo($bg); ?>">
        <td><b><?php print_string('total', 'questionnaire'); ?></b></td>
        <td width="40%"><b>&nbsp;<?php
            echo("<img src=\"" .$image_url ."hbar_l.gif\" height=9 width=4>");
            printf("<img src=\"" .$image_url ."hbar.gif\" height=9 width=%d>",$percent*2);
            echo("<img src=\"" .$image_url ."hbar_r.gif\" height=9 width=4>");
            printf("&nbsp;%.${precision}f%%",$percent); ?></b></td>
        <td width="10%" align="right"><b><?php echo($total); ?></b></td>
    </tr>
<?php } ?>
</table>
<?php
}
/* }}} */


/* {{{ proto void mkresrank(array weights, int total, int precision, bool show_totals)
   Builds HTML showing RANK results. */
function mkresrank($counts,$total,$precision,$showTotals) {
    global $CFG;

    $bg='';
    $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
?>
<table border="0">
    <tr>
        <td align="right"><b><?php print_string('rank', 'questionnaire'); ?></b></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>
<?php
    arsort($counts);
    $i=0; $pt=0;
    while(list($content,$num) = each($counts)) {
        if($num)
            $p = $num/$total*100.0;
        else
            $p = 0;
        $pt += $p;

        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
?>
    <tr bgcolor="<?php echo($bg); ?>">
        <td align="right"><b><?php echo(++$i); ?></b></td>
        <td><?php echo($content); ?></td>
        <td align="right" width="60"><?php if($p) printf("%.${precision}f%%",$p); ?></td>
        <td align="right" width="60">(<?php echo($num); ?>)</td>
    </tr>
<?php
    } // end while
    if($showTotals) {
        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
?>
    <tr bgcolor="<?php echo($bg); ?>">
        <td colspan=2 align="left"><b><?php print_string('total', 'questionnaire'); ?></b></td>
        <td align="right"><b><?php printf("%.${precision}f%%",$pt); ?></b></td>
        <td align="right"><b><?php echo($total); ?></b></td>
    </tr>
<?php } ?>
</table>
<?php
}
/* }}} */

/* {{{ proto void mkrescount(array weights, int total, int precision, bool show_totals)
   Builds HTML showing COUNT results. */
function mkrescount($counts,$total,$precision,$showTotals) {
    global $CFG;

    $i=0;
    $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
?>
<table width="90%" border="0">
<?php
    $bg = '';
    while(list($content,$num) = each($counts)) {
        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
?>
    <tr bgcolor="<?php echo($bg); ?>">
        <td><?php echo($content); ?></td>
        <td align="right" width="60"><?php echo($num); ?></td>
        <td align="right" width="60">(<?php if($num) printf("%.${precision}f",$num/$total*100.0); ?>%)</td>
    </tr>
<?php
        $i += $num;
    } // end while
    if($showTotals) {
        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
?>
    <tr bgcolor="<?php echo($bg); ?>">
        <td><b><?php print_string('total', 'questionnaire'); ?></b></td>
        <td align="right"><b><?php echo($total); ?></b></td>
        <td align="right"><b>(<?php if($i) printf("%.${precision}f",$i/$total*100.0); ?>%)</b></td>
    </tr>
<?php    } ?>
</table>
<?php
}
/* }}} */

/* {{{ proto void mkreslist(array weights, int total, int precision, bool show_totals)
    Builds HTML showing LIST results. */
function mkreslist($counts,$total,$precision,$showTotals) {
    global $CFG;

    if($total==0)    return;
    $bg='';
    $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
?>
<table width="90%" border="0" cellpadding="1">
    <tr><th align="left"><?php print_string('num', 'questionnaire'); ?></th><th><?php print_string('response', 'questionnaire'); ?></th></tr>
<?php
    while(list($text,$num) = each($counts)) {
        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
        echo("<tr bgcolor=\"$bg\"><th align=\"left\" valign=\"top\">$num</th><td>$text</td></tr>\n");
    }
?>
</table>
<?php
}
/* }}} */

/* {{{ proto void mkresavg(array weights, int total, int precision, bool show_totals)
    Builds HTML showing AVG results. */
function mkresavg($counts,$total,$precision,$showTotals,$length) {
    global $CFG;

    $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
    if (!$length)
        $length = 5;
    $width = 200 / $length;
?>
<table border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td></td>
        <td align="center" colspan="<?php echo($length+2); ?>"><?php print_string('averagerank', 'questionnaire'); ?></td>
    </tr>
    <tr>
        <td></td>
        <?php
            for ($i = 0; $i < $length; )
                echo( "<td align=\"right\" width=\"$width\">". ++$i ."</td>\n");
        ?>
        <td width="20"></td>
        <td></td>
    </tr>
<?php
    $bg = '';
    while(list($content,$avg) = each($counts)) {
        if($bg != $GLOBALS['ESPCONFIG']['bgalt_color1'])
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color1'];
        else
            $bg = $GLOBALS['ESPCONFIG']['bgalt_color2'];
?>
    <tr bgcolor="<?php echo($bg); ?>">
        <td align="right"><?php echo($content); ?>&nbsp;</td>
        <td align="left" width="220" colspan="<?php echo($length+1); ?>">
<?php
        if($avg) {
            echo('<img src="'. $image_url .'hbar_l.gif" height="9" width="4">');
            if (($j = $avg * $width - 11) > 0)
                printf('<img src="'. $image_url .'hbar.gif" height="9" width="%d">', $j);
            echo('<img src="'. $image_url .'hbar_r.gif" height="9" width="4">');
        }
?>
        </td>
        <td align="right" width="60">(<?php printf("%.${precision}f",$avg); ?>)</td>
    </tr>
<?php
    } // end while
?>
</table>
<?php
}
/* }}} */

?>
