<?php // $Id: tabs.php,v 1.11.2.6 2009/11/17 22:25:22 joseph_rezeau Exp $
/**
* prints the tabbed bar
*
* @version $Id: tabs.php,v 1.11.2.6 2009/11/17 22:25:22 joseph_rezeau Exp $
* @author Mike Churchward
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package questionnaire
*/
    $tabs = array();
    $row  = array();
    $inactive = array();
    $activated = array();

    $courseid = optional_param('courseid', false, PARAM_INT);
    $current_tab = $SESSION->questionnaire->current_tab;

    // If this questionnaire has a survey, get the survey and owner.
    // In a questionnaire instance created "using" a PUBLIC questionnaire, prevent anyone from editing settings, editing questions,
    // viewing all responses...except in the course where that PUBLIC questionnaire was originally created

    $courseid = $questionnaire->course->id;
    if ($survey = get_record('questionnaire_survey', 'id', $questionnaire->sid)) {
        $owner = (trim($survey->owner) == trim($courseid));
    } else {
        $survey = false;
        $owner = true;
    }

    if ($questionnaire->capabilities->view) {
        $row[] = new tabobject('view', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/view.php?'.
                               'id='.$questionnaire->cm->id), get_string('view', 'questionnaire'));
    }
    $numresp = $questionnaire->count_submissions($USER->id);

    if ($questionnaire->capabilities->readownresponses && ($numresp > 0)) {
        $argstr = 'instance='.$questionnaire->id.'&user='.$USER->id;
        $row[] = new tabobject('myreport', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/myreport.php?'.
                               $argstr), get_string('viewyourresponses', 'questionnaire', $numresp));

        if (in_array($current_tab, array('mysummary', 'mybyresponse', 'myvall', 'mydownloadcsv'))) {
            $inactive[] = 'myreport';
            $activated[] = 'myreport';
            $row2 = array();
            $argstr2 = $argstr.'&byresp=0&action=summary';
            $row2[] = new tabobject('mysummary', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/myreport.php?'.$argstr2),
                                    get_string('order_default', 'questionnaire'));
            $argstr2 = $argstr.'&byresp=0&action=vresp';
            $row2[] = new tabobject('mybyresponse', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/myreport.php?'.$argstr2),
                                    get_string('viewbyresponse', 'questionnaire'));
            $argstr2 = $argstr.'&byresp=1&action=vall';
            $row2[] = new tabobject('myvall', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/myreport.php?'.$argstr2),
                                    get_string('myresponses', 'questionnaire'));
            if ($questionnaire->capabilities->downloadresponses) {
                $argstr2 = $argstr.'&action=dwnpg'.'&sid='.$questionnaire->sid;
                $link  = $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2);
                $row2[] = new tabobject('mydownloadcsv', $link, get_string('downloadtext'));
            }
        }
    }

    $numresp = $questionnaire->count_submissions();
    // number of responses in currently selected group (or all participants etc.)
    if (isset($SESSION->questionnaire->numselectedresps)) {
        $numselectedresps = $SESSION->questionnaire->numselectedresps;
    } else {
        $numselectedresps = $numresp;
    }
    if (isset($SESSION->questionnaire->currentsessiongroupid)) {
        $currentgroupid = $SESSION->questionnaire->currentsessiongroupid;
    } else {
        $currentgroupid  = -1;
    }
    
    if ($questionnaire->capabilities->readallresponseanytime && $numresp > 0 && $owner && $numselectedresps > 0) {
        $argstr = 'instance='.$questionnaire->id.'&sid='.$questionnaire->sid;
        $row[] = new tabobject('allreport', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.
                               $argstr.'&action=vall'), get_string('viewresponses', 'questionnaire', $numresp));
        if (in_array($current_tab, array('vall', 'vresp', 'valldefault', 'vallasort', 'vallarsort', 'deleteall', 'downloadcsv',
                                         'vrespsummary', 'printresp', 'deleteresp'))) {    
        $inactive[] = 'allreport';
            $activated[] = 'allreport';
            $row2 = array();
            $argstr2 = $argstr.'&action=vall';
            $row2[] = new tabobject('vall', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                    get_string('viewallresponses', 'questionnaire'));
            $argstr2 = $argstr.'&byresponse=1&action=vresp';
            if ($questionnaire->capabilities->viewsingleresponse) {
                    $argstr2 = $argstr.'&byresponse=1&action=vresp';
                    $row2[] = new tabobject('vresp', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                            get_string('viewbyresponse', 'questionnaire'));
             }
        }
        if (in_array($current_tab, array('valldefault',  'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'))) {
            //$inactive[] = 'vall';
           	$activated[] = 'vall';
           	$row3 = array();
           	
            $argstr2 = $argstr.'&action=vall';
            $row3[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                    get_string('order_default', 'questionnaire'));
            if ($current_tab != 'downloadcsv' && $current_tab != 'deleteall') {
	            $argstr2 = $argstr.'&action=vallasort&currentgroupid='.$currentgroupid;
	            $row3[] = new tabobject('vallasort', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
	                                    get_string('order_ascending', 'questionnaire'));
	            $argstr2 = $argstr.'&action=vallarsort&currentgroupid='.$currentgroupid;
				$row3[] = new tabobject('vallarsort', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
	                                    get_string('order_descending', 'questionnaire'));
            }                                    
            if ($questionnaire->capabilities->deleteresponses) {
                $argstr2 = $argstr.'&action=delallresp';
                $row3[] = new tabobject('deleteall', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                        get_string('deleteallresponses', 'questionnaire'));
            }

            if ($questionnaire->capabilities->downloadresponses) {
                $argstr2 = $argstr.'&action=dwnpg&currentgroupid='.$currentgroupid;
                $link  = $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2);
                $row3[] = new tabobject('downloadcsv', $link, get_string('downloadtext'));
            }
        }

        if (in_array($current_tab, array('vrespsummary', 'printresp', 'deleteresp'))) {
        	$inactive[] = 'vresp';
            $activated[] = 'vresp';
            $row3 = array();

            $argstr2 = $argstr.'&action=vresp';
            $row3[] = new tabobject('vrespsummary', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                    get_string('summary', 'questionnaire'));

            $linkname = get_string('print','questionnaire');
            $title = get_string('printtooltip','questionnaire').'" target="_blank';
            $url = '/mod/questionnaire/print.php?qid='.$questionnaire->id.'&amp;rid='.$rid.
                   '&amp;courseid='.$course->id.'&amp;sec=1';
            $onclick="this.target='popup'; return openpopup('".$url."', 'popup', 'menubar=1, location=0, scrollbars, resizable', 0);";
            /// Fudge 'onclick' into the title so we can open a popup window.
            // Note JR.- 'target' attribute not admitted by validator

            $row3[] = new tabobject('printresp', '', $linkname, $title.'" onclick="'.$onclick);

            if ($questionnaire->capabilities->deleteresponses) {
                $argstr2 = $argstr.'&action=dresp&rid='.$rid;
                $row3[] = new tabobject('deleteresp', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                        get_string('deleteresp', 'questionnaire'));
            }
        }
    } else if ($questionnaire->capabilities->readallresponses && ($numresp > 0) &&
               ($questionnaire->resp_view == $QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                ($questionnaire->resp_view == $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED
                    && $questionnaire->is_closed()) ||
                ($questionnaire->resp_view == $QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED
                    && !$questionnaire->user_can_take($USER->id))) &&
               $questionnaire->is_survey_owner()) {
		$argstr = 'instance='.$questionnaire->id.'&sid='.$questionnaire->sid;
        $row[] = new tabobject('allreport', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.
                               $argstr.'&action=vall'), get_string('viewresponses', 'questionnaire', $numresp));
        if (in_array($current_tab, array('valldefault',  'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'))) {
        	$inactive[] = 'vall';
            $activated[] = 'vall';
            $row2 = array();
            $argstr2 = $argstr.'&action=vall';
            $row2[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                    get_string('summary', 'questionnaire'));
			$inactive[] = $current_tab;
			$activated[] = $current_tab;
            $row3 = array();
        	$argstr2 = $argstr.'&action=vall';
            $row3[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                    get_string('order_default', 'questionnaire'));
            $argstr2 = $argstr.'&action=vallasort&currentgroupid='.$currentgroupid;
            $row3[] = new tabobject('vallasort', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                    get_string('order_ascending', 'questionnaire'));
            $argstr2 = $argstr.'&action=vallarsort&currentgroupid='.$currentgroupid;
			$row3[] = new tabobject('vallarsort', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                    get_string('order_descending', 'questionnaire'));
			if ($questionnaire->capabilities->deleteresponses) {
                $argstr2 = $argstr.'&action=delallresp';
                $row2[] = new tabobject('deleteall', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2),
                                        get_string('deleteallresponses', 'questionnaire'));
            }

            if ($questionnaire->capabilities->downloadresponses) {
                $argstr2 = $argstr.'&action=dwnpg';
                $link  = htmlspecialchars('/mod/questionnaire/report.php?'.$argstr2);
                $row2[] = new tabobject('downloadcsv', $link, get_string('downloadtext'));
            }
            if (count($row2) <= 1) {
                $current_tab = 'allreport';
            }
        }
    }

    if($questionnaire->capabilities->manage  && $owner) {
        $row[] = new tabobject('settings', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/qsettings.php?'.
                               'id='.$questionnaire->cm->id), get_string('advancedsettings'));
    }

    if($questionnaire->capabilities->editquestions && $owner) {
        $row[] = new tabobject('questions', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/questions.php?'.
                               'id='.$questionnaire->cm->id), get_string('questions', 'questionnaire'));
        $row[] = new tabobject('preview', $CFG->wwwroot.htmlspecialchars('/mod/questionnaire/preview.php?'.
                               'id='.$questionnaire->cm->id), get_string('preview_label', 'questionnaire'));
    }

    if((count($row) > 1) || (!empty($row2) && (count($row2) > 1))) {
        $tabs[] = $row;

        if (!empty($row2) && (count($row2) > 1)) {
            $tabs[] = $row2;
        }

        if (!empty($row3) && (count($row3) > 1)) {
            $tabs[] = $row3;
        }

        print_tabs($tabs, $current_tab, $inactive, $activated);

    }
?>
