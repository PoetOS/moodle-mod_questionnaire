<?php  // $Id$

/// This page prints a particular instance of questionnaire
    global $SESSION, $CFG;
    require_once("../../config.php");
    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

    $instance = optional_param('instance', false, PARAM_INT);   // questionnaire ID
    $action = optional_param('action', 'vall', PARAM_ALPHA);
    $sid = optional_param('sid', NULL, PARAM_INT);              // Survey id.
    $rid = optional_param('rid', false, PARAM_INT);
    $type = optional_param('type', '', PARAM_ALPHA);
    $byresponse = optional_param('byresponse', false, PARAM_INT);
    $currentgroupid = optional_param('currentgroupid', -1, PARAM_INT); //groupid
    $user = optional_param('user', '', PARAM_INT);
    $userid = $USER->id;
    switch ($action) {
		case 'vallasort':
	    	$sort = 'ascending';
	    	break;
		case 'vallarsort':
	    	$sort = 'descending';
	    	break;
		default:
			$sort = 'default';
	}

    if ($instance === false) {
        if (!empty($SESSION->instance)) {
            $instance = $SESSION->instance;
        } else {
            error(get_string('requiredparameter', 'questionnaire'));
        }
    }
    $SESSION->instance = $instance;

    if (! $questionnaire = get_record("questionnaire", "id", $instance)) {
        error(get_string('incorrectquestionnaire', 'questionnaire'));
    }
    if (! $course = get_record("course", "id", $questionnaire->course)) {
        error("get_string('misconfigured', 'questionnaire')");
    }
    if (! $cm = get_coursemodule_from_instance("questionnaire", $questionnaire->id, $course->id)) {
        error(get_string('incorrectmodule', 'questionnaire'));
    }

    require_login($course->id);

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

    /// If you can't view the questionnaire, or can't view a specified response, error out.
    if (!($questionnaire->capabilities->view && $questionnaire->can_view_response($rid))) {
        /// Should never happen, unless called directly by a snoop...
        print_error('nopermissions', 'moodle', $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id);
    }

    $questionnaire->canviewallgroups = has_capability('moodle/site:accessallgroups', $context, NULL, false);
    $sid = $questionnaire->survey->id;
/// Tab setup:
    $SESSION->questionnaire->current_tab = 'allreport';

    $formdata = data_submitted();

    $strcrossanalyze = get_string('crossanalyze', 'questionnaire');
    $strcrosstabulate = get_string('crosstabulate', 'questionnaire');
    $strdeleteallresponses = get_string('deleteallresponses', 'questionnaire');
    $strdeleteresp = get_string('deleteresp', 'questionnaire');
    $strdownloadcsv = get_string('downloadtext');
    $strviewallresponses = get_string('viewallresponses', 'questionnaire');
    $strsummary = get_string('summary', 'questionnaire');
    $strviewbyresponse = get_string('viewbyresponse', 'questionnaire');
    $strquestionnaires = get_string('modulenameplural', 'questionnaire');

    /// get all responses for further use in viewbyresp and deleteall etc.
    // all participants
    $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
             FROM ".$CFG->prefix."questionnaire_response R
             WHERE R.survey_id=".$sid." AND
                   R.complete='y'
             ORDER BY R.id";
    if (!($respsallparticipants = get_records_sql($sql))) {
        $respsallparticipants = array();
    }
    $SESSION->questionnaire->numrespsallparticipants = count ($respsallparticipants);
    $SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numrespsallparticipants;

    //available group modes (0 = no groups; 1 = separate groups; 2 = visible groups)
    $groupmode = groups_get_activity_groupmode($cm, $course);
    $questionnairegroups = '';
    $groupscount = 0;
    $currentsessiongroupid = -1;
    $SESSION->questionnaire->respscount = 0;
    $SESSION->questionnaire_survey_id = $sid;

    if ($groupmode > 0) {
        if ($groupmode == 1) {
            $questionnairegroups = groups_get_all_groups($course->id, $userid);
        }
        if ($groupmode == 2 || $questionnaire->canviewallgroups) {
            $questionnairegroups = groups_get_all_groups($course->id);
        }
        if (!empty($questionnairegroups)) {
            $groupscount = count($questionnairegroups);
            foreach ($questionnairegroups as $key) {
                $firstgroupid = $key->id;
                break;
            }
            $SESSION->questionnaire->currentsessiongroupid = $currentgroupid;
            if ($groupscount === 0 && $groupmode == 1) {
                $currentgroupid = 0;
            }
            if ($groupmode == 1 && !$questionnaire->canviewallgroups && $currentgroupid == -1) {
                $currentgroupid = $firstgroupid;
            }
            if (isset($SESSION->questionnaire->currentgroupid)) { // needed for view by resp and delete all
                $currentsessiongroupid = $SESSION->questionnaire->currentgroupid;
            } else{
                $currentsessiongroupid = -1;
            }

            // all members of any group
            $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                    FROM ".$CFG->prefix."questionnaire_response R,
                        ".$CFG->prefix."groups_members GM
                     WHERE R.survey_id=".$sid." AND
                       R.complete='y' AND
                       GM.groupid>0 AND
                       R.username=GM.userid
                    ORDER BY R.id";
            if (!($respsallgroupmembers = get_records_sql($sql))) {
                $respsallgroupmembers = array();
            }
            $SESSION->questionnaire->numrespsallgroupmembers = count ($respsallgroupmembers);

            // not members of any group
            $sql = "SELECT R.id, R.survey_id, R.submitted, R.username, U.id AS user
                    FROM ".$CFG->prefix."questionnaire_response R,
                        ".$CFG->prefix."user U
                     WHERE R.survey_id=".$sid." AND
                       R.complete='y' AND
                       R.username=U.id
                    ORDER BY user";
            if (!($respsnongroupmembers = get_records_sql($sql))) {
                $respsnongroupmembers = array();
            }
            foreach ($respsnongroupmembers as $resp=>$key) {
                if (groups_has_membership($cm, $key->user)) {
                    unset($respsnongroupmembers[$resp]);
                }
            }
            if (!($respsnongroupmembers)) {
                $respsnongroupmembers = array();
            }
            $SESSION->questionnaire->numrespsnongroupmembers = count ($respsnongroupmembers);

            // current group members
            $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                FROM ".$CFG->prefix."questionnaire_response R,
                    ".$CFG->prefix."groups_members GM
                 WHERE R.survey_id=".$sid." AND
                   R.complete='y' AND
                   GM.groupid=".$currentgroupid." AND
                   R.username=GM.userid
                ORDER BY R.id";
                if (!($currentgroupresps = get_records_sql($sql))) {
                    $currentgroupresps = array();
                }
                $SESSION->questionnaire->numcurrentgroupresps = count ($currentgroupresps);
            } else {
                //groupmode = separate groups but user is not member of any group
                // and does not have moodle/site:accessallgroups capability -> refuse view responses
                if (!$questionnaire->canviewallgroups) {
                    $currentgroupid = 0;
                }
            }

            if ($currentsessiongroupid > 0) {
                $groupname = get_string('group').' <strong>'.groups_get_group_name($currentsessiongroupid).'</strong>';
                //$numselectedresps = $numcurrentgroupresps;
            } else {
                switch ($currentsessiongroupid) {
                    case '0':
                        $groupname = '<strong>'.get_string('groupmembersonlyerror','group').'</strong>';
                        break;
                    case '-1':
                        $groupname = '<strong>'.get_string('allparticipants').'</strong>';
                        break;
                    case '-2':
                        $groupname = '<strong>'.get_string('allgroups').'</strong>';
                        break;
                    case '-3':
                        $groupname = '<strong>'.get_string('groupnonmembers').'</strong>';
                        break;                 }
            }
        }

        if ($currentgroupid > 0) {
            $SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numcurrentgroupresps;
        } else {
            switch ($currentgroupid) {
                case '0':
                        $SESSION->questionnaire->numselectedresps = 0;
                    break;
                case '-2':
                    $SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numrespsallgroupmembers;
                    break;
                case '-3':
                    $SESSION->questionnaire->numselectedresps = $SESSION->questionnaire->numrespsnongroupmembers;
            }
        }

    switch ($action) {

    case 'dresp':
        if (empty($questionnaire->survey)) {
            $id = $questionnaire->survey;
            notify ("questionnaire->survey = /$id/");

            error(get_string('surveynotexists', 'questionnaire'));
        } else if ($questionnaire->survey->owner != $course->id) {
            error(get_string('surveyowner', 'questionnaire'));
        } else if (!$rid || !is_numeric($rid)) {
            error(get_string('invalidresponse', 'questionnaire'));
        } else if (!($resp = get_record('questionnaire_response', 'id', $rid))) {
            error(get_string('invalidresponserecord', 'questionnaire'));
        }

        $ruser = false;
        if (is_numeric($resp->username)) {
            if ($user = get_record('user', 'id', $resp->username)) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'questionnaire').' -';
            }
        } else {
            $ruser = $resp->username;
        }

    /// Print the page header
        $extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => $strviewallresponses, 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $questionnaire->cm);
        print_header_simple(get_string('deletingresp', 'questionnaire'), '', $navigation);

        /// print the tabs
        $SESSION->questionnaire->current_tab = 'deleteresp';
        include('tabs.php');

        if ($questionnaire->respondenttype == 'anonymous') {
                $ruser = '- '.get_string('anonymous', 'questionnaire').' -';
        }
        notice_yesno(get_string('confirmdelresp', 'questionnaire', $ruser),
            $CFG->wwwroot.'/mod/questionnaire/report.php?action=dvresp&amp;sid='.$sid.'&amp;rid='.$rid,
            $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$sid.'&amp;rid='.$rid.
            '&amp;instance='.$instance.'&amp;byresponse=1');

    /// Finish the page
        print_footer($course);
        break;

    case 'delallresp': // delete all responses
    	// TODO
	    /// Should never happen, unless called directly by a snoop...
	    if ( !has_capability('mod/questionnaire:deleteresponses',$context) ) {
	        error('Permission denied');
	    }

        $select = 'survey_id='.$sid.' AND complete = \'y\'';
        if (!($responses = get_records_select('questionnaire_response', $select, 'id', 'id'))) {
            return;
        }
        foreach($responses as $rid=>$valeur) {
            break;
        }
        if (empty($questionnaire->survey)) {
            $id = $questionnaire->survey;
            notify ("questionnaire->survey = /$id/");
            error(get_string('surveynotexists', 'questionnaire'));
        } else if ($questionnaire->survey->owner != $course->id) {
            error(get_string('surveyowner', 'questionnaire'));
        } else if (!$rid || !is_numeric($rid)) {
            error(get_string('invalidresponse', 'questionnaire'));
        } else if (!($resp = get_record('questionnaire_response', 'id', $rid))) {
            error(get_string('invalidresponserecord', 'questionnaire'));
        }

        $ruser = false;
        if (is_numeric($resp->username)) {
            if ($user = get_record('user', 'id', $resp->username)) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'questionnaire').' -';
            }
        } else {
            $ruser = $resp->username;
        }

    /// Print the page header
        $extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => $strviewallresponses, 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $questionnaire->cm);
        print_header_simple(get_string('deletingresp', 'questionnaire'), '', $navigation);

        /// print the tabs
        $SESSION->questionnaire->current_tab = 'deleteall';
        include('tabs.php');

        if ($groupmode == 0) { // no groups or visible groups
            $confirmdelstr = get_string('confirmdelallresp', 'questionnaire');
        } else { // separate groups
            $confirmdelstr = get_string('confirmdelgroupresp', 'questionnaire', $groupname);
        }
        echo '<br /><br />';
        notice_yesno($confirmdelstr,
                $CFG->wwwroot.'/mod/questionnaire/report.php?action=dvallresp&amp;sid='.$sid.'&amp;instance='.$instance,
                $CFG->wwwroot.'/mod/questionnaire/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance);
        echo '</div>';
    /// Finish the page
        print_footer($course);
        break;

    case 'dvresp':

        if (empty($questionnaire->survey)) {
            error(get_string('surveynotexists', 'questionnaire'));
        } else if ($questionnaire->survey->owner != $course->id) {
            error(get_string('surveyowner', 'questionnaire'));
        } else if (!$rid || !is_numeric($rid)) {
            error(get_string('invalidresponse', 'questionnaire'));
        } else if (!($resp = get_record('questionnaire_response', 'id', $rid))) {
            error(get_string('invalidresponserecord', 'questionnaire'));
        }

        $ruser = false;
        if (is_numeric($resp->username)) {
            if ($user = get_record('user', 'id', $resp->username)) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'questionnaire').' -';
            }
        } else {
            $ruser = $resp->username;
        }

        if (questionnaire_delete_response($rid)) {
            if ($questionnaire->respondenttype == 'anonymous') {
                    $ruser = '- '.get_string('anonymous', 'questionnaire').' -';
            }
            redirect($CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$sid.
                     '&amp;instance='.$instance.'&amp;byresponse=1', get_string('deletedresp', 'questionnaire').
                     $rid.get_string('by', 'questionnaire').$ruser.'.');
        } else {
            error (get_string('couldnotdelresp', 'questionnaire').$rid.get_string('by', 'questionnaire').$ruser.'?',
                   $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$sid.'&amp;&amp;instance='.
                   $instance.'byresponse=1');
        }
        break;

    case 'dvallresp': // delete all responses in questionnaire (or group)

        if (empty($questionnaire->survey)) {
            error(get_string('surveynotexists', 'questionnaire'));
        } else if ($questionnaire->survey->owner != $course->id) {
            error(get_string('surveyowner', 'questionnaire'));
        }

    /// Print the page header
        $extranav = 'Survey Reports';
        $navigation = build_navigation($extranav, $questionnaire->cm);

        print_header_simple(get_string('deleteallresponses', 'questionnaire'), '', $navigation);

        /// print the tabs
        $SESSION->questionnaire->current_tab = 'deleteall';
        include('tabs.php');

        //available group modes (0 = no groups; 1 = separate groups; 2 = visible groups)
            $groupid = $currentsessiongroupid;
            if ($groupmode > 0) {
                switch ($groupid) {
                    case -1: // all participants
                        $resps = $respsallparticipants;
                        break;
                    case -2: // all members of any group
                        $resps = $respsallgroupmembers;
                            break;
                    case -3: // not members of any group
                        $resps = $respsnongroupmembers;
                            break;
                    default: // members of a specific group
                    $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                            FROM ".$CFG->prefix."questionnaire_response R,
                                ".$CFG->prefix."groups_members GM
                             WHERE R.survey_id=".$sid." AND
                               R.complete='y' AND
                               GM.groupid=".$groupid." AND
                               R.username=GM.userid
                            ORDER BY R.id";
                    if (!($resps = get_records_sql($sql))) {
                        $resps = array();
                    }
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else {
                    if ($rid === false) {
                        $resp = current($resps);
                        $rid = $resp->id;
                    } else {
                        $resp = get_record('questionnaire_response', 'id', $rid);
                    }
                    if (is_numeric($resp->username)) {
                        if ($user = get_record('user', 'id', $resp->username)) {
                            $ruser = fullname($user);
                        } else {
                        $ruser = '- '.get_string('unknown', 'questionnaire').' -';
                        }
                    } else {
                        $ruser = $resp->username;
                    }
                }
           } else {
                $resps = $respsallparticipants;
           }

        if (!empty($resps)) {
            foreach ($resps as $response) {
                questionnaire_delete_response($response->id);
            }
            echo '<br /><br />';
            if ($groupid == -1) { // deleted ALL responses
                $deletedstr = get_string('deletedallresp', 'questionnaire');
            } elseif ($groupid == -3) {
                $deletedstr = get_string('deletedallgroupresp', 'questionnaire', '<strong>'.get_string('groupnonmembers').'</strong>');
            } else { // deleted responses in current group only
                $deletedstr = get_string('deletedallgroupresp', 'questionnaire', '<strong>'.groups_get_group_name($groupid).'</strong>');
            }
            $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                     FROM ".$CFG->prefix."questionnaire_response R
                     WHERE R.survey_id=".$sid." AND
                           R.complete='y'
                     ORDER BY R.id";
            if (!($resps = get_records_sql($sql))) {
                $respsallparticipants = array();
            }
            if (empty($resps)) {
                $redirection = $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id;
            } else {
                $redirection = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance;
            }
            redirect($redirection, $deletedstr, -1);
        } else {
            error (get_string('couldnotdelresp', 'questionnaire'),
                   $CFG->wwwroot.'/mod/questionnaire/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance);
        }
        break;

    case 'dwnpg': // Download page options
        /// Should never happen, unless called directly by a snoop...
        if ( !has_capability('mod/questionnaire:downloadresponses',$context,$userid) ) {
            error('Permission denied');
        }

    	$extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => get_string('downloadtext'), 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $questionnaire->cm);
        print_header_simple(get_string('questionnairereport', 'questionnaire'), '', $navigation);

        /// print the tabs
    /// Tab setup:
        if (empty($user)) {
            $SESSION->questionnaire->current_tab = 'downloadcsv';
        } else {
            $SESSION->questionnaire->current_tab = 'mydownloadcsv';
        }

        include('tabs.php');

        $groupname = '';
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case -1: // all participants
                    $groupname = get_string('allparticipants');
                    break;
                case -2: // all members of any group
                    $groupname = get_string('membersofselectedgroup','group').' '.get_string('allgroups');
                	break;
                case -3: // not members of any group
                    $groupname = get_string('groupnonmembers');
                	break;
                default: // members of a specific group
                	$groupname = get_string('membersofselectedgroup','group').' '.get_string('group').' '.$questionnairegroups[$currentgroupid]->name;
            }
        }
        echo "<br /><br />\n";
        helpbutton('downloadtextformat', get_string('downloadtext'), 'questionnaire', true, false);
        echo (get_string('downloadtext').' <strong>'.$groupname.'</strong>');
        print_heading(get_string('textdownloadoptions', 'questionnaire'));
        print_box_start();
        echo "<form action=\"{$CFG->wwwroot}/mod/questionnaire/report.php\" method=\"GET\">\n";
        echo "<input type=\"hidden\" name=\"instance\" value=\"$instance\" />\n";
        echo "<input type=\"hidden\" name=\"user\" value=\"$user\" />\n";
        echo "<input type=\"hidden\" name=\"sid\" value=\"$sid\" />\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"dcsv\" />\n";
        print_checkbox('choicecodes', 1, true, get_string('includechoicecodes', 'questionnaire'));
        echo "<br />\n";
        print_checkbox('choicetext', 1, true, get_string('includechoicetext', 'questionnaire'));
        echo "<br />\n";
        echo "<br />\n";
        echo "<input type=\"submit\" name=\"submit\" value=\"".get_string('download', 'questionnaire')."\" />\n";
        echo "</form>\n";
        print_box_end();

        print_footer('none');
        exit();
        break;

    case 'dcsv': // download as text (cvs) format

    /// Use the questionnaire name as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($questionnaire->name, PARAM_FILE);
        $name = eregi_replace("[^A-Z0-9]+", "_", trim($name));

            $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
            $choicetext  = optional_param('choicetext', '0', PARAM_INT);
            $output = $questionnaire->generate_csv('', $user, $choicecodes, $choicetext);
            // CSV
            // SEP. 2007 JR changed file extension to *.txt for non-English Excel users' sake
            // and changed separator to tabulation
            // JAN. 2008 added \r carriage return for better Windows implementation
            header("Content-Disposition: attachment; filename=$name.txt");
            header("Content-Type: text/comma-separated-values");
            foreach ($output as $row ) {
                $text = implode("\t", $row);
                echo $text."\r\n";
            }
        exit();
        break;

    case 'vall': // view all responses
    case 'vallasort': // view all responses sorted in ascending order
    case 'vallarsort': // view all responses sorted in descending order
    	$extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => $strviewallresponses, 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $questionnaire->cm);
        print_header_simple(get_string('questionnairereport', 'questionnaire'), '', $navigation);

        /// print the tabs
	    switch ($action) {
			case 'vallasort':
                $SESSION->questionnaire->current_tab = 'vallasort';
                break;
			case 'vallarsort':
                $SESSION->questionnaire->current_tab = 'vallarsort';
				break;
			default:
                $SESSION->questionnaire->current_tab = 'valldefault';
			}

			$SESSION->questionnaire->currentsessiongroupid = $currentgroupid;
			include('tabs.php');

        if (!empty($questionnaire->survey->theme)) {
            $href = $CFG->wwwroot.'/mod/questionnaire/css/'.$questionnaire->survey->theme;
            echo '<script type="text/javascript">
                //<![CDATA[
                document.write("<link rel=\"stylesheet\" type=\"text/css\" href=\"'.$href.'\">")
                //]]>
                </script>';
        }
        echo ('<br />');

        // enable choose_group if there are questionnaire groups and groupmode is not set to "no groups"
        // and if there are more goups than 1 (or if user can view all groups)
        $SESSION->questionnaire->currentgroupid = $currentgroupid;
        if (is_array($questionnairegroups) && $groupmode > 0 && $groupscount > 1 - $questionnaire->canviewallgroups) {
            require_once('choose_group_form.php');
            $choose_group_form = new questionnaire_choose_group_form();
            $choose_group_form->set_questionnairedata(array('groups'=>$questionnairegroups,
                 'currentgroupid'=>$currentgroupid, 'groupmode'=>$groupmode, 'canviewallgroups'=>$questionnaire->canviewallgroups));
            $choose_group_form->set_form_elements();
            $choose_group_form->display();
        } else {
            echo ('<br />');
        }
        if ($currentgroupid > 0) {
            $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
        } else {
            switch ($currentgroupid) {
                case '0':
                    $groupname = '<strong>'.get_string('groupmembersonlyerror','group').'</strong>';
                    break;
                case '-1':
                    $groupname = '<strong>'.get_string('allparticipants').'</strong>';
                    break;
                case '-2':
                    $groupname = '<strong>'.get_string('allgroups').'</strong>';
                    break;
                case '-3':
                    $groupname = '<strong>'.get_string('groupnonmembers').'</strong>';
                    break;}
        }
        echo'<div class = "active">';
        echo (get_string('viewallresponses','questionnaire').'. '.$groupname.'. ');
    	$strsort = get_string('order_'.$sort, 'questionnaire');
        echo $strsort;
        helpbutton('orderresponses', get_string('orderresponses', 'questionnaire'), 'questionnaire', true, false);
        $ret = $questionnaire->survey_results(1, 1, '', '', '', '', $uid=false, $currentgroupid, $sort);
        echo '</div>';

    /// Finish the page
        print_footer($course);
        break;

    case 'cross':
        $type = 'cross';
        /// Fall down into the vresp section.

    case 'vresp': // view by response
    default:
        if (empty($questionnaire->survey)) {
            error(get_string('surveynotexists', 'questionnaire'));
        } else if ($questionnaire->survey->owner != $course->id) {
            error(get_string('surveyowner', 'questionnaire'));
        }
        $ruser = false;
        $noresponses = false;

        if ($byresponse || $rid) {

        //available group modes (0 = no groups; 1 = separate groups; 2 = visible groups)
            $groupid = $currentsessiongroupid;
            if ($groupmode > 0) {
                switch ($groupid) {
                    case -1: // all participants
                        $resps = $respsallparticipants;
                        break;
                    case -2: // all members of any group
                        $resps = $respsallgroupmembers;
                            break;
                    case -3: // not members of any group
                        $resps = $respsnongroupmembers;
                            break;
                    default: // members of a specific group
                    $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                            FROM ".$CFG->prefix."questionnaire_response R,
                                ".$CFG->prefix."groups_members GM
                             WHERE R.survey_id=".$sid." AND
                               R.complete='y' AND
                               GM.groupid=".$groupid." AND
                               R.username=GM.userid
                            ORDER BY R.id";
                    if (!($resps = get_records_sql($sql))) {
                        $resps = array();
                    }
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else {
                    if ($rid === false) {
                        $resp = current($resps);
                        $rid = $resp->id;
                    } else {
                        $resp = get_record('questionnaire_response', 'id', $rid);
                    }
                    if (is_numeric($resp->username)) {
                        if ($user = get_record('user', 'id', $resp->username)) {
                            $ruser = fullname($user);
                        } else {
                        $ruser = '- '.get_string('unknown', 'questionnaire').' -';
                        }
                    } else {
                        $ruser = $resp->username;
                    }
                }
           } else {
                $resps = $respsallparticipants;
           }
        }
        $rids = array_keys($resps);
        if (!$rid) {
            $rid = $rids[0];
        }

    /// Print the page header
        $extranav = array();
        $extranav[] = array('name' => get_string('questionnairereport', 'questionnaire'), 'link' => '', 'type' => 'activity');
        $extranav[] = array('name' => $strviewallresponses, 'link' => "", 'type' => 'activity');
        $navigation = build_navigation($extranav, $questionnaire->cm);
        print_header_simple(get_string('questionnairereport', 'questionnaire'), '', $navigation);

        /// print the tabs
        $SESSION->questionnaire->current_tab = 'vrespsummary';
        include('tabs.php');

        if (!empty($questionnaire->survey->theme)) {
            $href = $CFG->wwwroot.'/mod/questionnaire/css/'.$questionnaire->survey->theme;
            echo '<script type="text/javascript">
                //<![CDATA[
                document.write("<link rel=\"stylesheet\" type=\"text/css\" href=\"'.$href.'\">")
                //]]>
                </script>';
        }

    /// Print the main part of the page

        echo '<br/><br/>';
        echo '<div style="text-align:center; padding-bottom:5px;">';
        if ($groupid === 0) {
            $groupname = '<strong>'.get_string('groupmembersonlyerror','group').'</strong>';;
            echo (get_string('viewbyresponse','questionnaire').'. '.$groupname.'. ');
        } elseif  ($noresponses){
            echo (get_string('group').' <strong>'.groups_get_group_name($groupid).'</strong>: '.
                get_string('noresponses','questionnaire'));
        } else {
            if ($groupid != -1 ) {
                $questionnaire->survey_results_navbar_student ($rid, $resp->username, $instance, $resps, 'report', $sid);
            } else {
                $questionnaire->survey_results_navbar($rid);
            }
            echo '</div>';
            echo'<div class = "active">';
            $ret = $questionnaire->view_response($rid);
            echo '</div>';
            echo '<div style="text-align:center; padding-bottom:5px;">';
            if ($groupid != -1 ) {
                $questionnaire->survey_results_navbar_student ($rid, $userid, $instance, $resps, 'report', $sid);
            } else {
                $questionnaire->survey_results_navbar($rid);
            }
        }
        echo '</div>';

    /// Finish the page
        print_footer($course);
        break;
    }
?>