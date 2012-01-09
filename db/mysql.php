<?php // $Id: mysql.php,v 1.15.2.5 2008/06/20 13:36:46 mchurch Exp $

function questionnaire_upgrade($oldversion) {
/// This function does anything necessary to upgrade 
/// older versions to match current functionality 

    global $CFG;

    if ($oldversion < 2004021300) {

       # Do something ...

    }

    if ($oldversion < 2004081300) {
        execute_sql('ALTER TABLE `'.$CFG->prefix.'questionnaire` ADD `respondenttype` ENUM( \'fullname\', \'anonymous\' ) DEFAULT \'fullname\' NOT NULL AFTER `qtype`');
    }

    if ($oldversion < 2004090700) {
        execute_sql('ALTER TABLE `'.$CFG->prefix.'questionnaire` ADD `resp_eligible` ENUM( \'all\', \'students\', \'teachers\' ) DEFAULT \'all\' NOT NULL AFTER `respondenttype`');
    }

    if ($oldversion < 2004090900) {
        execute_sql('ALTER TABLE `'.$CFG->prefix.'questionnaire` ADD `opendate` INT( 10 ) NOT NULL AFTER `resp_eligible` , '.
                    'ADD `closedate` INT( 10 ) NOT NULL AFTER `opendate`');
    }

    if ($oldversion < 2005021100) {
        execute_sql('ALTER TABLE `'.$CFG->prefix.'questionnaire` ADD INDEX ( `sid` )'); 
        execute_sql('ALTER TABLE `'.$CFG->prefix.'questionnaire_survey` ADD INDEX ( `owner` )');
        execute_sql('ALTER TABLE `'.$CFG->prefix.'questionnaire_survey` DROP INDEX `name` , '.
                    'ADD INDEX `name` ( `name` )'); 
        questionnaire_upgrade_2005021100();
    }

    if ($oldversion < 2005030100) {
        execute_sql('ALTER TABLE `'.$CFG->prefix.'questionnaire_attempts` ADD `rid` INT( 10 ) UNSIGNED ' .
                    'DEFAULT \'0\' NOT NULL AFTER `userid`');
    }

    if ($oldversion < 2005062700) {
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_bool` DROP PRIMARY KEY ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_bool` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_bool` ADD INDEX `response_question` ( `response_id` , `question_id` ) ;');

        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_single` DROP PRIMARY KEY ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_single` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_single` ADD INDEX `response_question` ( `response_id` , `question_id` ) ;');

        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_rank` DROP PRIMARY KEY ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_rank` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_rank` ADD INDEX `response_question_choice` ( `response_id` , `question_id`, `choice_id` ) ;');

        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_text` DROP PRIMARY KEY ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_text` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_text` ADD INDEX `response_question` ( `response_id` , `question_id` ) ;');

        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_other` DROP PRIMARY KEY ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_other` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_other` ADD INDEX `response_question_choice` ( `response_id` , `question_id`, `choice_id` ) ;');

        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_date` DROP PRIMARY KEY ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_date` ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;');
        modify_database('', 'ALTER TABLE `prefix_questionnaire_response_date` ADD INDEX `response_question` ( `response_id` , `question_id` ) ;');
    }

    if ($oldversion < 2006012700) {
        questionnaire_upgrade_2006012700();
    }

    if ($oldversion < 2006031702) {
        questionnaire_upgrade_2006031700();
    }

    return true;
}

/// Upgrade the questionnaire_survey table to use the new 'owner/realm' configuration.
/// Any surveys belonging to a specific course will be given the owner '[course shortname]:[course id]', and
/// the realm 'course' to identify them with that course. Any surveys not assigned to a course will be given 
/// the owner and realm 'public'.
function questionnaire_upgrade_2005021100() {

    $maxnum = 50;   /// Do 50 at a time.
    $deleted = 4;
    /// Count the number of undeleted surveys.
    if (($numsurveys = count_records_select('questionnaire_survey', ('status != '.$deleted))) <= 0) {
        return true;
    }

    $startfrom = 0;
    while ($numsurveys > 0) {
        if ($surveys =
            get_records_select('questionnaire_survey', ('status != '.$deleted), '', '*', $startfrom, $maxnum)) {
            foreach ($surveys as $survey) {
                /// If a survey belongs to a questionnaire, it *should* belong to a course, so name
                /// the realm accordingly.
                if ($quests = get_records('questionnaire', 'sid', $survey->id)) {
                    /// If the survey is in more than one course, call it public...
                    $realm = false;
                    foreach ($quests as $quest) {
                        /// Make sure we don't orphan any due to missing courses
                        if ($cid = get_field('course', 'id', 'id', $quest->course)) {
                            if ($realm === false) {
                                $cidchk = $cid;
                                $realm = 'private';
                                $owner = $quest->course;
                            } else if ($cid != $cidchk) {
                                $realm = 'public';
                                $owner = SITEID;
                                break;
                            }
                        } else {
                            $realm = 'public';
                            $owner = SITEID;
                            break;
                        }
                    }
                    $survey->realm = $realm;
                    $survey->owner = $owner;
                /// If the survey doesn't belong to a questionnaire, make it a template.
                } else {
                    $survey->realm = 'template';
                    $survey->owner = SITEID;
                }
                update_record('questionnaire_survey', $survey);
            }
        }
        $numsurveys -= $maxnum;
        $startfrom += $maxnum;
    }
    return true;
}

/// Upgrade the questionnaire table to use the resume and navigate fields in the questionnaire table.
/// Removing phpESP dependencies.
function questionnaire_upgrade_2006012700() {
    table_column('questionnaire', '', 'resume', 'integer', '2', 'unsigned', '0', 'not null', 'closedate');
    table_column('questionnaire', '', 'navigate', 'integer', '2', 'unsigned', '0', 'not null', 'resume');

    $select = 'survey_id > 0 AND (resume = \'Y\' OR navigate = \'Y\')';
    if (!($accrecs = get_records_select('questionnaire_access', $select))) {
        /// Nothing to do, return.
        return true;
    }

    foreach ($accrecs as $accrec) {
        if ($surveyrecs = get_records('questionnaire', 'sid', $accrec->survey_id)) {
            foreach ($surveyrecs as $surveyrec) {
                $surveyrec->resume = ($accrec->resume == 'Y') ? 1 : 0;
                $surveyrec->navigate = ($accrec->navigate == 'Y') ? 1 : 0;
                update_record('questionnaire', $surveyrec);
            }
        }
    }

    return true;
}

function questionnaire_upgrade_2006031700() {
/// Upgrade the questionnaire_response table to use integer timestamps.
    table_column('questionnaire_response', 'submitted', 'submitted', 'varchar', '20', '', ' ');
    
    /// This will be heavy on the database....
    if (($numrecs = count_records('questionnaire_response')) > 0) {
        $recstart = 0;
        $recstoget = 100;
        while ($recstart < $numrecs) {
            if ($records = get_records('questionnaire_response', '', '', '', '*', $recstart, $recstoget)) {
                foreach ($records as $record) {
                    $tstampparts = explode(' ', $record->submitted);
                    $dateparts = explode('-', $tstampparts[0]);
                    $timeparts = explode(':', $tstampparts[1]);
                    $time = mktime($timeparts[0], $timeparts[1], $timeparts[2], $dateparts[1], $dateparts[2], $dateparts[0]);
                    set_field('questionnaire_response', 'submitted', $time, 'id', $record->id);
                }
            }
            $recstart += $recstoget;
        }
    }
    table_column('questionnaire_response', 'submitted', 'submitted', 'integer', '10');


    /// This will be heavy on the database....
    if (($numrecs = count_records('questionnaire')) > 0) {
        $recstart = 0;
        $recstoget = 100;
        while ($recstart < $numrecs) {
            if ($records = get_records('questionnaire', '', '', '', '*', $recstart, $recstoget)) {
                foreach ($records as $record) {
                    if ($record->qtype == 'unlimited') {
                        $qtype = 0;
                    } else if ($record->qtype == 'once') {
                        $qtype = 1;
                    }
                    set_field('questionnaire', 'qtype', $qtype, 'id', $record->id);
                }
            }
            $recstart += $recstoget;
        }
    }

/// Modify the qtype field of the 'questionnaire' table to be integer instead of enum.
    table_column('questionnaire', 'qtype', 'qtype', 'integer', '10');

/// Add response viewing eligibility.
    table_column('questionnaire', '', 'resp_view', 'integer', '2', 'unsigned', '0', 'not null', 'resp_eligible');

    return true;
}
?>