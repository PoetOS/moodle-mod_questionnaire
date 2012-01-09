<?php // $Id: backuplib.php,v 1.7.2.4 2009/11/23 20:14:49 mchurch Exp $
    //This php script contains all the stuff to backup/restore
    //questionnaire mods

    //This is the "graphical" structure of the questionnaire mod:
    //
    //                    questionnaire
    //                     (CL, pk->id, fk->sid)
    //
    //                    questionnaire_attempts
    //                     (UL, pk->id, fk->qid, fk->userid, fk->rid)
    //
    //                    questionnaire_survey
    //                     (CL, pk->id)
    //
    //                    questionnaire_question
    //                     (CL, pk->id, fk->survey_id, fk->result_id)
    //
    //                    questionnaire_quest_choice
    //                     (CL, pk->id, fk->question_id)
    //
    //                    questionnaire_response
    //                     (UL, pk->id, fk->survey_id)
    //
    //                    questionnaire_response_bool
    //                     (UL, pk->id, pk->(fk->response_id, fk->question_id))
    //
    //                    questionnaire_response_date
    //                     (UL, pk->id, pk->(fk->response_id, fk->question_id))
    //
    //                    questionnaire_resp_multiple
    //                     (UL, pk->id, fk->response_id, fk->question_id, fk->choice_id)
    //
    //                    questionnaire_response_other
    //                     (UL, pk->id, pk->(fk->response_id, fk->question_id, fk->choice_id))
    //
    //                    questionnaire_response_rank
    //                     (UL, pk->id, pk->(fk->response_id, fk->question_id, fk->choice_id))
    //
    //                    questionnaire_resp_single
    //                     (UL, pk->id, pk->(fk->response_id, fk->question_id), fk->choice_id)
    //
    //                    questionnaire_response_text
    //                     (UL, pk->id, pk->(fk->response_id, fk->question_id))
    //
    // Meaning: pk->primary key field of the table
    //          fk->foreign key to link with parent
    //          nt->nested field (recursive data)
    //          CL->course level info
    //          UL->user level info
    //          files->table may have files)
    //
    //-----------------------------------------------------------

    ////Return an array of info (name,value)
    function questionnaire_check_backup_mods($course, $user_data=false, $backup_unique_code, $instances=null) {
        global $CFG;
        if (!empty($instances) && is_array($instances) && count($instances)) {
            $info = array();
            foreach ($instances as $id => $instance) {
                $info += questionnaire_check_backup_mods_instances($instance,$backup_unique_code);
            }
            return $info;
        }
        //First the course data
        $info[0][0] = get_string('modulenameplural', 'questionnaire');
        $info[0][1] = count_records('questionnaire', 'course', $course);

        //Now, if requested, the user_data
        if ($user_data) {
            $info[1][0] = get_string('respondents', 'questionnaire');
            $sql = 'SELECT COUNT(qa.id) '.
                   'FROM '.$CFG->prefix.'questionnaire q, '.$CFG->prefix.'questionnaire_attempts qa '.
                   'WHERE q.course = '.$course.' AND qa.qid = q.id';
            $info[1][1] = count_records_sql($sql);
        }

        return $info;
    }

    ////Return an array of info (name,value)
    function questionnaire_check_backup_mods_instances($instance,$backup_unique_code) {
        global $CFG;

        //First the course data
        $info[$instance->id.'0'][0] = '<b>'.$instance->name.'</b>';
        $info[$instance->id.'0'][1] = '';

        //Now, if requested, the user_data
        if (!empty($instance->userdata)) {
            $info[$instance->id.'1'][0] = get_string('respondents', 'questionnaire');
            $sql = 'SELECT COUNT(qa.id) '.
                   'FROM '.$CFG->prefix.'questionnaire_attempts qa '.
                   'WHERE qa.qid = '.$instance->id;
            $info[$instance->id.'1'][1] = count_records_sql($sql);
        }

        return $info;
    }

    //This function executes all the backup procedure about this mod
    function questionnaire_backup_mods($bf,$preferences) {
        global $CFG;

        $status = true;

        ////Iterate over questionnaire table
        if ($questionnaires = get_records ('questionnaire', 'course', $preferences->backup_course, 'id')) {
        /// Allow this to work on pre-1.6 versions too.
            $notv16 = !function_exists('backup_mod_selected');
            foreach ($questionnaires as $questionnaire) {
                if ($notv16 || backup_mod_selected($preferences,'questionnaire',$questionnaire->id)) {
                    $status = questionnaire_backup_one_mod($bf,$preferences,$questionnaire);
                }
            }
        }

        return $status;
    }

    function questionnaire_backup_one_mod($bf,$preferences,$questionnaire) {

        global $CFG;

        if (is_numeric($questionnaire)) {
            $questionnaire = get_record('questionnaire','id',$questionnaire);
        }

        $status = true;

        //Start mod
        fwrite ($bf,start_tag('MOD',3,true));
        //Print assignment data
        fwrite ($bf,full_tag('ID',4,false,$questionnaire->id));
        fwrite ($bf,full_tag('MODTYPE',4,false,'questionnaire'));
        fwrite ($bf,full_tag('NAME',4,false,$questionnaire->name));
        fwrite ($bf,full_tag('SUMMARY',4,false,$questionnaire->summary));
        fwrite ($bf,full_tag('QTYPE',4,false,$questionnaire->qtype));
        fwrite ($bf,full_tag('RESPONDENTTYPE',4,false,$questionnaire->respondenttype));
        fwrite ($bf,full_tag('RESP_ELIGIBLE',4,false,$questionnaire->resp_eligible));
        /// JR added resp_view and resume to backup and restore
        fwrite ($bf,full_tag('RESP_VIEW',4,false,$questionnaire->resp_view));
        fwrite ($bf,full_tag('RESUME',4,false,$questionnaire->resume));
        fwrite ($bf,full_tag('OPENDATE',4,false,$questionnaire->opendate));
        fwrite ($bf,full_tag('CLOSEDATE',4,false,$questionnaire->closedate));
        fwrite ($bf,full_tag('TIMEMODIFIED',4,false,$questionnaire->timemodified));
        fwrite ($bf,full_tag('SID',4,false,$questionnaire->sid));
        fwrite ($bf,full_tag('GRADE',4,false,$questionnaire->grade));

        // backup question data
        if ($questionnaire->sid > 0) {
            backup_questionnaire_survey($bf, $questionnaire->sid);
        }

        // backup entries and pages
    /// Allow this to work on pre-1.6 versions too.
        $notv16 = !function_exists('backup_userdata_selected');
        if (($notv16 && $preferences->mods['questionnaire']->userinfo) ||
            (!$notv16 && backup_userdata_selected($preferences,'questionnaire',$questionnaire->id))) {
            $status = backup_questionnaire_responses($bf, $questionnaire->id, $questionnaire->sid);
        }

        //End mod
        fwrite ($bf,end_tag("MOD",3,true));

        return $status;
    }

    function backup_questionnaire_survey($bf, $sid) {

        $survey = get_record('questionnaire_survey', 'id', $sid);
        fwrite ($bf,start_tag('SURVEY',4,true));

        fwrite ($bf,full_tag('ID',5,false,$survey->id));
        fwrite ($bf,full_tag('NAME',5,false,$survey->name));
        fwrite ($bf,full_tag('OWNER',5,false,$survey->owner));
        fwrite ($bf,full_tag('REALM',5,false,$survey->realm));
        fwrite ($bf,full_tag('STATUS',5,false,$survey->status));
        fwrite ($bf,full_tag('TITLE',5,false,$survey->title));
        fwrite ($bf,full_tag('EMAIL',5,false,$survey->email));
        fwrite ($bf,full_tag('SUBTITLE',5,false,$survey->subtitle));
        fwrite ($bf,full_tag('INFO',5,false,$survey->info));
        fwrite ($bf,full_tag('THEME',5,false,$survey->theme));
        fwrite ($bf,full_tag('THANKS_PAGE',5,false,$survey->thanks_page));
        fwrite ($bf,full_tag('THANK_HEAD',5,false,$survey->thank_head));
        fwrite ($bf,full_tag('THANK_BODY',5,false,$survey->thank_body));

        if ($questions = get_records('questionnaire_question', 'survey_id', $sid, 'id')) {
            foreach ($questions as $question) {
                fwrite ($bf,start_tag('QUESTION',5,true));

                fwrite ($bf,full_tag('ID',6,false,$question->id));
                fwrite ($bf,full_tag('SURVEY_ID',6,false,$question->survey_id));
                fwrite ($bf,full_tag('NAME',6,false,$question->name));
                fwrite ($bf,full_tag('TYPE_ID',6,false,$question->type_id));
                fwrite ($bf,full_tag('RESULT_ID',6,false,$question->result_id));
                fwrite ($bf,full_tag('LENGTH',6,false,$question->length));
                fwrite ($bf,full_tag('PRECISE',6,false,$question->precise));
                fwrite ($bf,full_tag('POSITION',6,false,$question->position));
                fwrite ($bf,full_tag('CONTENT',6,false,$question->content));
                fwrite ($bf,full_tag('REQUIRED',6,false,$question->required));
                fwrite ($bf,full_tag('DELETED',6,false,$question->deleted));

                if ($choices = get_records('questionnaire_quest_choice', 'question_id', $question->id, 'id')) {
                    foreach ($choices as $choice) {
                        fwrite ($bf,start_tag('QUESTION_CHOICE',6,true));

                        fwrite ($bf,full_tag('ID',7,false,$choice->id));
                        fwrite ($bf,full_tag('QUESTION_ID',7,false,$choice->question_id));
                        fwrite ($bf,full_tag('CONTENT',7,false,$choice->content));
                        fwrite ($bf,full_tag('VALUE',7,false,$choice->value));

                        fwrite ($bf,end_tag('QUESTION_CHOICE',6,true));
                    }
                }

                fwrite ($bf,end_tag('QUESTION',5,true));
            }
        }

        fwrite ($bf,end_tag('SURVEY',4,true));
    }

     //Backup questionnaire responses.
    function backup_questionnaire_responses ($bf, $qid, $sid) {
        global $CFG, $db;

        $status = true;

        if ($attempts = get_records('questionnaire_attempts', 'qid', $qid, 'id')) {
            foreach ($attempts as $attempt) {
                fwrite ($bf,start_tag('ATTEMPT',4,true));

                fwrite ($bf,full_tag('ID',5,false,$attempt->id));
                fwrite ($bf,full_tag('QID',5,false,$attempt->qid));
                fwrite ($bf,full_tag('USERID',5,false,$attempt->userid));
                fwrite ($bf,full_tag('RID',5,false,$attempt->rid));
                fwrite ($bf,full_tag('TIMEMODIFIED',5,false,$attempt->timemodified));

                fwrite ($bf,end_tag('ATTEMPT',4,true));
            }
        }

        if ($responses = get_records('questionnaire_response', 'survey_id', $sid, 'id')) {
            foreach ($responses as $response) {
                fwrite ($bf,start_tag('RESPONSE',4,true));

                fwrite ($bf,full_tag('ID',5,false,$response->id));
                fwrite ($bf,full_tag('SURVEY_ID',5,false,$response->survey_id));
                fwrite ($bf,full_tag('SUBMITTED',5,false,$response->submitted));
                fwrite ($bf,full_tag('COMPLETE',5,false,$response->complete));
                fwrite ($bf,full_tag('GRADE',5,false,$response->grade));
                fwrite ($bf,full_tag('USERNAME',5,false,$response->username));

                if ($resps = get_records('questionnaire_response_bool', 'response_id', $response->id)) {
                    foreach ($resps as $resp) {
                        fwrite ($bf,start_tag('RESPONSE_BOOL',5,true));
                        fwrite ($bf,full_tag('RESPONSE_ID',6,false,$resp->response_id));
                        fwrite ($bf,full_tag('QUESTION_ID',6,false,$resp->question_id));
                        fwrite ($bf,full_tag('CHOICE_ID',6,false,$resp->choice_id));
                        fwrite ($bf,end_tag('RESPONSE_BOOL',5,true));
                    }
                }
                if ($resps = get_records('questionnaire_response_date', 'response_id', $response->id)) {
                    foreach ($resps as $resp) {
                        fwrite ($bf,start_tag('RESPONSE_DATE',5,true));
                        fwrite ($bf,full_tag('RESPONSE_ID',6,false,$resp->response_id));
                        fwrite ($bf,full_tag('QUESTION_ID',6,false,$resp->question_id));
                        fwrite ($bf,full_tag('RESPONSE',6,false,$resp->response));
                        fwrite ($bf,end_tag('RESPONSE_DATE',5,true));
                    }
                }
                if ($resps = get_records('questionnaire_resp_multiple', 'response_id', $response->id, 'id')) {
                    foreach ($resps as $resp) {
                        fwrite ($bf,start_tag('RESPONSE_MULTIPLE',5,true));
                        fwrite ($bf,full_tag('ID',6,false,$resp->id));
                        fwrite ($bf,full_tag('RESPONSE_ID',6,false,$resp->response_id));
                        fwrite ($bf,full_tag('QUESTION_ID',6,false,$resp->question_id));
                        fwrite ($bf,full_tag('CHOICE_ID',6,false,$resp->choice_id));
                        fwrite ($bf,end_tag('RESPONSE_MULTIPLE',5,true));
                    }
                }
                if ($resps = get_records('questionnaire_response_other', 'response_id', $response->id)) {
                    foreach ($resps as $resp) {
                        fwrite ($bf,start_tag('RESPONSE_OTHER',5,true));
                        fwrite ($bf,full_tag('RESPONSE_ID',6,false,$resp->response_id));
                        fwrite ($bf,full_tag('QUESTION_ID',6,false,$resp->question_id));
                        fwrite ($bf,full_tag('CHOICE_ID',6,false,$resp->choice_id));
                        fwrite ($bf,full_tag('RESPONSE',6,false,$resp->response));
                        fwrite ($bf,end_tag('RESPONSE_OTHER',5,true));
                    }
                }
            /// phpESP uses PRIMARY keys made from mutiple fields. Moodle's get_records won't work on those.
            /// For now, access the db object directly.
                if ($resps = get_records('questionnaire_response_rank', 'response_id', $response->id)) {
                    foreach ($resps as $resp) {
                        fwrite ($bf,start_tag('RESPONSE_RANK',5,true));
                        fwrite ($bf,full_tag('RESPONSE_ID',6,false,$resp->response_id));
                        fwrite ($bf,full_tag('QUESTION_ID',6,false,$resp->question_id));
                        fwrite ($bf,full_tag('CHOICE_ID',6,false,$resp->choice_id));
                        fwrite ($bf,full_tag('RANK',6,false,$resp->rank));
                        fwrite ($bf,end_tag('RESPONSE_RANK',5,true));
                    }
                }
            /// phpESP uses PRIMARY keys made from mutiple fields. Moodle's get_records won't work on those.
            /// For now, access the db object directly.
                if ($resps = get_records('questionnaire_resp_single', 'response_id', $response->id)) {
                    foreach ($resps as $resp) {
                        fwrite ($bf,start_tag('RESPONSE_SINGLE',5,true));
                        fwrite ($bf,full_tag('RESPONSE_ID',6,false,$resp->response_id));
                        fwrite ($bf,full_tag('QUESTION_ID',6,false,$resp->question_id));
                        fwrite ($bf,full_tag('CHOICE_ID',6,false,$resp->choice_id));
                        fwrite ($bf,end_tag('RESPONSE_SINGLE',5,true));
                    }
                }
            /// phpESP uses PRIMARY keys made from mutiple fields. Moodle's get_records won't work on those.
            /// For now, access the db object directly.
                if ($resps = get_records('questionnaire_response_text', 'response_id', $response->id)) {
                    foreach ($resps as $resp) {
                        fwrite ($bf,start_tag('RESPONSE_TEXT',5,true));
                        fwrite ($bf,full_tag('RESPONSE_ID',6,false,$resp->response_id));
                        fwrite ($bf,full_tag('QUESTION_ID',6,false,$resp->question_id));
                        fwrite ($bf,full_tag('RESPONSE',6,false,$resp->response));
                        fwrite ($bf,end_tag('RESPONSE_TEXT',5,true));
                    }
                }

                fwrite ($bf,end_tag('RESPONSE',4,true));
            }
        }

        return $status;
    }
?>