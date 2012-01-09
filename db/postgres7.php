<?php // $Id: postgres7.php,v 1.5.10.3 2008/06/20 13:36:47 mchurch Exp $

function questionnaire_upgrade($oldversion) {
// This function does anything necessary to upgrade
// older versions to match current functionality

    global $CFG;

    $result = true;

    if ($oldversion < 2005062701) {
    }

    if ($oldversion < 2006012700) {
        questionnaire_upgrade_2006012700();
    }

    if ($oldversion < 2006031700) {
        questionnaire_upgrade_2006031700();
    }

    return $result;
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

                    if ($record->qtype == 'unlimited') {
                        $qtype = 0;
                    } else if ($record->type == 'once') {
                        $qtype = 1;
                    }
                    set_field('questionnaire_response', 'qtype', $qtype, 'id', $record->id);
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