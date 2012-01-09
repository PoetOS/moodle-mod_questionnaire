<?php //$Id: restorelib.php,v 1.12.2.8 2009/12/05 08:21:40 joseph_rezeau Exp $
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

    function questionnaire_restore_mods($mod, $restore) {

        global $CFG;
        global $NEWQUEST, $NEWCHOICE;

        $status = true;

        //Get record from backup_ids
        $data = backup_getid($restore->backup_unique_code, $mod->modtype, $mod->id);

        if ($data) {
            //Now get completed xmlized object
            $info = $data->info;
            //traverse_xmlize($info);                                                                     //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //Now, build the QUIZ record structure
            $questionnaire->course = $restore->course_id;
            $questionnaire->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
            $questionnaire->summary = backup_todb($info['MOD']['#']['SUMMARY']['0']['#']);

        /// QTYPE was changed from enum to integer in version 2006031700. Ensure old backups restore properly.
            $qtype = backup_todb($info['MOD']['#']['QTYPE']['0']['#']);
            if ($qtype == 'unlimited') {
                $qtype = 0;
            } else if ($qtype == 'once') {
                $qtype = 1;
            } else if (!is_numeric($qtype)) {
                $qtype = 0;
            }
            $questionnaire->qtype = $qtype;
            $questionnaire->respondenttype = backup_todb($info['MOD']['#']['RESPONDENTTYPE']['0']['#']);
            $questionnaire->resp_eligible = backup_todb($info['MOD']['#']['RESP_ELIGIBLE']['0']['#']);
        /// JR added resp_view and resume to backup and restore
            $questionnaire->resp_view = backup_todb($info['MOD']['#']['RESP_VIEW']['0']['#']);
            $questionnaire->resume = backup_todb($info['MOD']['#']['RESUME']['0']['#']);
            $questionnaire->opendate = backup_todb($info['MOD']['#']['OPENDATE']['0']['#']);
            $questionnaire->closedate = backup_todb($info['MOD']['#']['CLOSEDATE']['0']['#']);
        /// to gracefully import questionnaires with data authored in moodle 1.8
            if (array_key_exists('GRADE', $info['MOD']['#'])) {
                $questionnaire->grade = backup_todb($info['MOD']['#']['GRADE']['0']['#']);
            } else {
                $questionnaire->grade = 0;
            }
            $questionnaire->timemodified = backup_todb($info['MOD']['#']['TIMEMODIFIED']['0']['#']);

        /// Get the survey data array:
            $surveydata = $info['MOD']['#']['SURVEY']['0']['#'];

            $survey->name = backup_todb($surveydata['NAME']['0']['#']);
        /// NEED TO CHECK THE TYPE OF SURVEY BEING USED...
        /// IF IT WAS USING A PUBLIC ONE, WHAT DO WE DO??
        /// Set the owner to the new course id.
            $survey->owner = $restore->course_id;
            $survey->realm = backup_todb($surveydata['REALM']['0']['#']);
            $survey->status = backup_todb($surveydata['STATUS']['0']['#']);
            $survey->title = backup_todb($surveydata['TITLE']['0']['#']);
            $survey->email = backup_todb($surveydata['EMAIL']['0']['#']);
            $survey->subtitle = backup_todb($surveydata['SUBTITLE']['0']['#']);
            $survey->info = backup_todb($surveydata['INFO']['0']['#']);
            $survey->theme = backup_todb($surveydata['THEME']['0']['#']);
            $survey->thanks_page = backup_todb($surveydata['THANKS_PAGE']['0']['#']);
            $survey->thank_head = backup_todb($surveydata['THANK_HEAD']['0']['#']);
            $survey->thank_body = backup_todb($surveydata['THANK_BODY']['0']['#']);

            //The structure is equal to the db, so insert the survey:
            if (!($survey->id = insert_record ('questionnaire_survey', $survey))) {
                $newid = false;
            } else {
                //The structure is equal to the db, so insert the questionnaire:
                $questionnaire->sid = $survey->id;
                $newid = insert_record ('questionnaire', $questionnaire);
            }

            //Do some output
            if (!defined('RESTORE_SILENTLY')) {
                echo "<ul><li>".get_string('modulename', 'questionnaire')." \"".$questionnaire->name."\"<br>";
            }
            backup_flush(300);

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code, $mod->modtype, $mod->id, $newid);

                // Now restore all of the question data:
                $status = questionnaire_restore_questions($survey->id, $surveydata['QUESTION']);

                //Now check if want to restore user data and do it.
            /// Allow this to work on pre-1.6 versions too.
                $notv16 = !function_exists('restore_userdata_selected');
                if (($notv16 && $restore->mods['questionnaire']->userinfo) ||
                    (!$notv16 && restore_userdata_selected($restore,'questionnaire',$mod->id))) {
                    //Restore questionnaire_attempts
                    $status = questionnaire_attempts_restore_mods ($newid, $survey->id, $info, $restore);
                }
            } else {
                $status = false;
            }

            //Finalize ul
            if (!defined('RESTORE_SILENTLY')) {
                echo "</ul>";
            }

        } else {
            $status = false;
        }

        return $status;
    }

    /// Restore all question data:
    function questionnaire_restore_questions($sid, &$questiondata) {
        global $NEWQUEST, $NEWCHOICE;

        $status = true;

        if (is_array($questiondata)) {
            foreach ($questiondata as $questrec) {
                $newquest->survey_id = $sid;
                $newquest->name = backup_todb($questrec['#']['NAME']['0']['#']);
                $newquest->type_id = backup_todb($questrec['#']['TYPE_ID']['0']['#']);
                $newquest->result_id = backup_todb($questrec['#']['RESULT_ID']['0']['#']);
                $newquest->length = backup_todb($questrec['#']['LENGTH']['0']['#']);
                $newquest->precise = backup_todb($questrec['#']['PRECISE']['0']['#']);
                $newquest->position = backup_todb($questrec['#']['POSITION']['0']['#']);
                $newquest->content = backup_todb($questrec['#']['CONTENT']['0']['#']);
                $newquest->required = strtolower(backup_todb($questrec['#']['REQUIRED']['0']['#']));
                $newquest->deleted = strtolower(backup_todb($questrec['#']['DELETED']['0']['#']));

                if ($qid = insert_record('questionnaire_question', $newquest)) {
                /// If the question has choices, restore them:
                    if (isset($questrec['#']['QUESTION_CHOICE'])) {
                        foreach ($questrec['#']['QUESTION_CHOICE'] as $choicerec) {
                            $choice->question_id = $qid;
                            $choice->content = backup_todb($choicerec['#']['CONTENT']['0']['#']);
                            $choice->value = backup_todb($choicerec['#']['VALUE']['0']['#']);
                            if ($cid = insert_record('questionnaire_quest_choice', $choice)) {
                            /// Store the old id => new id record.
                                $NEWCHOICE[$choicerec['#']['ID']['0']['#']] = $cid;
                            } else {
                                $status = false;
                            }
                        }
                    }
                /// Store the old id => new id record.
                    $NEWQUEST[$questrec['#']['ID']['0']['#']] = $qid;
                } else {
                    $status = false;
                }
            }
        }
        return $status;
    }

    /// Restore all of the user data.
    function questionnaire_attempts_restore_mods($qid, $sid, &$info, &$restore) {
        global $CFG, $NEWQUEST, $NEWCHOICE;

        $status = true;
        $attempts = array();

    /// Restore the attempts:
        if (isset($info['MOD']['#']['ATTEMPT'])) {
            foreach($info['MOD']['#']['ATTEMPT'] as $attemptrec) {
                unset($attempt);
                $attempt->qid = $qid;
                $attempt->userid = backup_todb($attemptrec['#']['USERID']['0']['#']);
                $attempt->rid = backup_todb($attemptrec['#']['RID']['0']['#']);
                $attempt->timemodified = backup_todb($attemptrec['#']['TIMEMODIFIED']['0']['#']);
                //We may have to recode the userid field  ... added JR
                $user = backup_getid($restore->backup_unique_code,"user",$attempt->userid);
                if (is_object($user)) {
                    $attempt->userid = $user->new_id;
                }
                if ($attempt->rid != 0) {
                /// Store attempts with responses to correct the rid's later.
                    $attempts[$attempt->rid] = $attempt;
                } else if (!insert_record('questionnaire_attempts', $attempt)) {
                        $status = false;
                }
            }
        }

    /// Restore the specific responses:
        if (isset($info['MOD']['#']['RESPONSE'])) {
            foreach($info['MOD']['#']['RESPONSE'] as $responserec) {
                $response->survey_id = $sid;
                $response->submitted = backup_todb($responserec['#']['SUBMITTED']['0']['#']);
                $response->complete = strtolower(backup_todb($responserec['#']['COMPLETE']['0']['#']));
        /// to gracefully import questionnaires with data authored in moodle 1.8
                if (array_key_exists('GRADE', $responserec['#'])) {
                    $response->grade = backup_todb($responserec['#']['GRADE']['0']['#']);
                } else {
                    $response->grade = 0;
                }

                $response->username = backup_todb($responserec['#']['USERNAME']['0']['#']);
                $user = backup_getid($restore->backup_unique_code,"user",$response->username);
                if (is_object($user)) { // added JR
                    $response->username = $user->new_id;
                }
                if (!($rid = insert_record('questionnaire_response', $response))) {
                    $status = false;
                } else {
                /// Restore the associated attempt.
                    if (isset($attempts[$responserec['#']['ID']['0']['#']])) {
                        $attempts[$responserec['#']['ID']['0']['#']]->rid = $rid;
                        if (!insert_record('questionnaire_attempts',
                                           $attempts[$responserec['#']['ID']['0']['#']])) {
                            $status = false;
                        }
                    }

                /// Restore any other response data:
                    if (isset($responserec['#']['RESPONSE_BOOL'])) {
                        foreach ($responserec['#']['RESPONSE_BOOL'] as $rrec) {
                            unset($response);
                            $response->response_id = $rid;
                            $response->question_id = $NEWQUEST[$rrec['#']['QUESTION_ID']['0']['#']];
                            $response->choice_id = strtolower(backup_todb($rrec['#']['CHOICE_ID']['0']['#']));
                            if (!insert_record('questionnaire_response_bool', $response)) {
                                $status = false;
                            }
                        }
                    }
                    if (isset($responserec['#']['RESPONSE_DATE'])) {
                        foreach ($responserec['#']['RESPONSE_DATE'] as $rrec) {
                            unset($response);
                            $response->response_id = $rid;
                            $response->question_id = $NEWQUEST[$rrec['#']['QUESTION_ID']['0']['#']];
                            $response->response = backup_todb($rrec['#']['RESPONSE']['0']['#']);
                            if (!insert_record('questionnaire_response_date', $response)) {
                                $status = false;
                            }
                        }
                    }
                    if (isset($responserec['#']['RESPONSE_MULTIPLE'])) {
                        foreach ($responserec['#']['RESPONSE_MULTIPLE'] as $rrec) {
                            unset($response);
                            $response->response_id = $rid;
                            $response->question_id = $NEWQUEST[$rrec['#']['QUESTION_ID']['0']['#']];
                             $response->choice_id = $NEWCHOICE[$rrec['#']['CHOICE_ID']['0']['#']];
                            /// to gracefully import questionnaires with data authored in moodle 1.8
                            /// or data saved with questionnaire 1.9 previously to 07-FEB-2009
                            if ($response->choice_id != 0) {
                                if (!insert_record('questionnaire_resp_multiple', $response)) {
                                    $status = false;
                                }
                            }
                        }
                    }
                    if (isset($responserec['#']['RESPONSE_OTHER'])) {
                        foreach ($responserec['#']['RESPONSE_OTHER'] as $rrec) {
                            unset($response);
                            $response->response_id = $rid;
                            $response->question_id = $NEWQUEST[$rrec['#']['QUESTION_ID']['0']['#']];
                            $response->choice_id = $NEWCHOICE[$rrec['#']['CHOICE_ID']['0']['#']];
                            $response->response = backup_todb($rrec['#']['RESPONSE']['0']['#']);
                            if (!insert_record('questionnaire_response_other', $response)) {
                                $status = false;
                            }
                        }
                    }
                    if (isset($responserec['#']['RESPONSE_RANK'])) {
                        foreach ($responserec['#']['RESPONSE_RANK'] as $rrec) {
                            unset($response);
                            $response->response_id = $rid;
                            $response->question_id = $NEWQUEST[$rrec['#']['QUESTION_ID']['0']['#']];
                            $response->choice_id = $NEWCHOICE[$rrec['#']['CHOICE_ID']['0']['#']];
                            $response->rank = backup_todb($rrec['#']['RANK']['0']['#']);
                            if (!insert_record('questionnaire_response_rank', $response)) {
                                $status = false;
                            }
                        }
                    }
                    if (isset($responserec['#']['RESPONSE_SINGLE'])) {
                        foreach ($responserec['#']['RESPONSE_SINGLE'] as $rrec) {
                            unset($response);
                            $response->response_id = $rid;
                            $response->question_id = $NEWQUEST[$rrec['#']['QUESTION_ID']['0']['#']];
                            if (($rrec['#']['CHOICE_ID']['0']['#'] == '$@NULL@$') || ($rrec['#']['CHOICE_ID']['0']['#'] == 0)) {
                                $response->choice_id = 0;
                            } else {
                                $response->choice_id = $NEWCHOICE[$rrec['#']['CHOICE_ID']['0']['#']];
                            }
                            if (!insert_record('questionnaire_resp_single', $response)) {
                                $status = false;
                            }
                        }
                    }
                    if (isset($responserec['#']['RESPONSE_TEXT'])) {
                        foreach ($responserec['#']['RESPONSE_TEXT'] as $rrec) {
                            unset($response);
                            $response->response_id = $rid;
                            $response->question_id = $NEWQUEST[$rrec['#']['QUESTION_ID']['0']['#']];
                            $response->response = backup_todb($rrec['#']['RESPONSE']['0']['#']);
                            if (!insert_record('questionnaire_response_text', $response)) {
                                $status = false;
                            }
                        }
                    }
                }
            }
        }

        return $status;
    }
?>