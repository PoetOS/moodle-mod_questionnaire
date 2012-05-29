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

/// This page prints a particular instance of questionnaire

    require_once("../../config.php");
    require_once($CFG->dirroot.'/mod/questionnaire/lib.php');
    require_once($CFG->dirroot.'/mod/questionnaire/questions_form.php');

    $id     = required_param('id', PARAM_INT);                  // course module ID
    $action = optional_param('action', 'main', PARAM_ALPHA);    // screen
    $qid    = optional_param('qid', 0, PARAM_INT);              // Question id
    $moveq  = optional_param('moveq', 0, PARAM_INT);            // Question id to move
    $qtype  = optional_param('type_id', 0, PARAM_INT); // Question type

    if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

    require_course_login($course, true, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/questions.php');
    $url->param('id', $id);
    $url->param('action', $action);
    if ($qid) {
        $url->param('qid', $qid);
    }
    if ($moveq) {
        $url->param('moveq', $moveq);
    }
    if ($qtype) {
        $url->param('type_id', $qtype);
    }

    $PAGE->set_url($url);
    $PAGE->set_context($context);

    $questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

    if (!$questionnaire->capabilities->editquestions) {
        print_error('nopermissions', 'error','mod:questionnaire:edit');
    }

    $SESSION->questionnaire->current_tab = 'questions';
    $reload = false;

    /// Process form data:
    if ($action == 'main') {
        $questions_form = new questionnaire_questions_form('questions.php', $moveq);
        $sdata = clone($questionnaire->survey);
        $sdata->sid = $questionnaire->survey->id;
        $sdata->id = $cm->id;
        if (!empty($questionnaire->questions)) {
            $pos = 1;
            foreach ($questionnaire->questions as $qidx => $question) {
                $sdata->{'pos_'.$qidx} = $pos;
                $pos++;
            }
        }
        $questions_form->set_data($sdata);

        if ($qformdata = $questions_form->get_data()) {

        /// Quickforms doesn't return values for 'image' input types using 'exportValue', so we need to grab
        /// it from the raw submitted data.
            $exformdata = data_submitted();
            if (isset($exformdata->moveupbutton)) {
                $qformdata->moveupbutton = $exformdata->moveupbutton;
            } else if (isset($exformdata->movednbutton)) {
                $qformdata->movednbutton = $exformdata->movednbutton;
            } else if (isset($exformdata->movebutton)) {
                $qformdata->movebutton = $exformdata->movebutton;
            } else if (isset($exformdata->moveherebutton)) {
                $qformdata->moveherebutton = $exformdata->moveherebutton;
            } else if (isset($exformdata->editbutton)) {
                $qformdata->editbutton = $exformdata->editbutton;
            } else if (isset($exformdata->removebutton)) {
                $qformdata->removebutton = $exformdata->removebutton;
            }

        /// Insert a section break.
            if (isset($qformdata->removebutton)){
            /// Need to use the key, since IE returns the image position as the value rather than the specified
            /// value in the <input> tag.
                $qid = key($qformdata->removebutton);
                $DB->set_field('questionnaire_question', 'deleted', 'y', array('id' => $qid, 'survey_id' => $qformdata->sid));
                $select = 'survey_id = '.$qformdata->sid.' AND deleted = \'n\' AND position > '.
                          $questionnaire->questions[$qid]->position;
                if ($records = $DB->get_records_select('questionnaire_question', $select, null, 'position ASC')) {
                    foreach ($records as $record) {
                        $DB->set_field('questionnaire_question', 'position', $record->position-1, array('id' => $record->id));
                    }
                }
                $reload = true;
            } else if (isset($qformdata->editbutton)) {
                /// Switch to edit question screen.
                $action = 'question';
            /// Need to use the key, since IE returns the image position as the value rather than the specified
            /// value in the <input> tag.
                $qid = key($qformdata->editbutton);
                $reload = true;
            } else if (isset($qformdata->addqbutton)) {
                if($qformdata->type_id == 99) { // Adding section break is handled right away....
                    $sql = 'SELECT MAX(position) as maxpos FROM {questionnaire_question} '.
                           'WHERE survey_id = '.$qformdata->sid.' AND deleted = \'n\'';
                    if ($record = $DB->get_record_sql($sql)) {
                        $pos = $record->maxpos + 1;
                    } else {
                        $pos = 1;
                    }
                    $question = new Object();
                    $question->survey_id = $qformdata->sid;
                    $question->type_id = 99;
                    $question->position = $pos;
                    $question->content = 'break';
                    $DB->insert_record('questionnaire_question', $question);
                    $reload = true;
                } else {
	                /// Switch to edit question screen.
    	            $action = 'question';
        	        $qtype = $qformdata->type_id;
            	    $qid = 0;
                	$reload = true;
                }
            } else if (isset($qformdata->moveupbutton)) {
            /// Need to use the key, since IE returns the image position as the value rather than the specified
            /// value in the <input> tag.
                $qid = key($qformdata->moveupbutton);
                $DB->set_field('questionnaire_question', 'position', $questionnaire->questions[$qid]->position,
                          array('survey_id' => $questionnaire->sid, 'position' => ($questionnaire->questions[$qid]->position-1)));
                $DB->set_field('questionnaire_question', 'position', ($questionnaire->questions[$qid]->position-1),
                          array('id' => $qid));
                /// Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
                redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id);
                $reload = true;
            } else if (isset($qformdata->movednbutton)) {
            /// Need to use the key, since IE returns the image position as the value rather than the specified
            /// value in the <input> tag.
                $qid = key($qformdata->movednbutton);
                $DB->set_field('questionnaire_question', 'position', $questionnaire->questions[$qid]->position,
                          array('survey_id' => $questionnaire->sid, 'position' => ($questionnaire->questions[$qid]->position + 1),
                                'deleted' => 'n'));
                $DB->set_field('questionnaire_question', 'position', ($questionnaire->questions[$qid]->position+1),
                               array('id' => $qid));
                /// Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
                redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id);
                $reload = true;
            } else if (isset($qformdata->movebutton)) {
                /// Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
                redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id.
                         '&moveq='.key($qformdata->movebutton));
                $reload = true;
            } else if (isset($qformdata->moveherebutton)) {
            /// Need to use the key, since IE returns the image position as the value rather than the specified
            /// value in the <input> tag.
                $qpos = key($qformdata->moveherebutton);
                $questionnaire->move_question($qformdata->moveq, $qpos);
                /// Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
                redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id);
                $reload = true;
            } else if (isset($qformdata->pos)) {
                /// Must be a position change...
                foreach ($qformdata->pos as $qidx => $position) {
                    $newpos = $position;
                    if (($questionnaire->questions[$qidx]->position) != $newpos) {
                        $DB->set_field('questionnaire_question', 'position', $newpos, array('id' => $qidx));
                        $oldpos = $questionnaire->questions[$qidx]->position;
                        break;
                    }
                }

                if ($newpos < $oldpos) {
                    $curpos = 1;
                    reset($questionnaire->questions);
                    foreach ($questionnaire->questions as $qidx => $ques) {
                        if ($curpos < $newpos) {
                            // do nothing yet.
                        } else if ($curpos < $oldpos) {
                            $DB->set_field('questionnaire_question', 'position', ($curpos+1), array('id' => $qidx));
                        } else if ($curpos == $oldpos) {
                            $DB->set_field('questionnaire_question', 'position', $newpos, array('id' => $qidx));
                        } else {
                            break;
                        }
                        $curpos++;
                    }
                } else if ($newpos > $oldpos) {
                    $curpos = 1;
                    foreach ($questionnaire->questions as $qidx => $ques) {
                        if ($curpos < $oldpos) {
                            // do nothing yet.
                        } else if ($curpos == $oldpos) {
                            $DB->set_field('questionnaire_question', 'position', $newpos, array('id' => $qidx));
                        } else if ($curpos <= $newpos) {
                            $DB->set_field('questionnaire_question', 'position', ($curpos-1), array('id' => $qidx));
                        } else {
                            break;
                        }
                        $curpos++;
                    }
                }
                /// Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
                redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id);
                $reload = true;
            }
        }

    } else if ($action == 'question') {
        if ($qid != 0) {
            $question = clone($questionnaire->questions[$qid]);
            $question->qid = $question->id;
            $question->sid = $questionnaire->survey->id;
            $question->id = $cm->id;
            $draftid_editor = file_get_submitted_draft_itemid('question');
            $content = file_prepare_draft_area($draftid_editor, $context->id, 'mod_questionnaire', 'question',
                                               $qid, array('subdirs'=>true), $question->content);
            $question->content = array('text' => $content, 'format' => FORMAT_HTML, 'itemid'=>$draftid_editor);
        } else {
            $question = new Object();
            $question->sid = $questionnaire->survey->id;
            $question->id = $cm->id;
            $question->type_id = $qtype;
            $question->type = '';
            $draftid_editor = file_get_submitted_draft_itemid('question');
            $content = file_prepare_draft_area($draftid_editor, $context->id, 'mod_questionnaire', 'question',
                                               null, array('subdirs'=>true), '');
            $question->content = array('text' => $content, 'format' => FORMAT_HTML, 'itemid'=>$draftid_editor);
        }
        $questions_form = new questionnaire_edit_question_form('questions.php');
        $questions_form->set_data($question);
        if ($questions_form->is_cancelled()) {
            /// Switch to main screen
            $action = 'main';
            $reload = true;

        } else if ($qformdata = $questions_form->get_data()) {
            /// Saving question data
            if (isset($qformdata->makecopy)) {
                $qformdata->qid = 0;
            }

            $has_choices = $questionnaire->type_has_choices();
    /// *** THIS SECTION NEEDS TO BE MOVED OUT OF HERE - SHOULD CREATE QUESTION-SPECIFIC UPDATE FUNCTIONS.
            if ($has_choices[$qformdata->type_id]) {
                // eliminate trailing blank lines
                $qformdata->allchoices =  preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $qformdata->allchoices);
                // trim to eliminate potential trailing carriage return
                $qformdata->allchoices = trim($qformdata->allchoices);
                if (empty($qformdata->allchoices))  {
                   if ($qformdata->type_id != 8) {
                        error (get_string('enterpossibleanswers','questionnaire'));
                   } else {
                    // add dummy blank space character for empty value
                       $qformdata->allchoices = " ";
                   }
                } elseif ($qformdata->type_id == 8) { //rate
                    $allchoices = $qformdata->allchoices;
                    $allchoices = explode("\n", $allchoices);
                    $ispossibleanswer = false;
                    $nbnameddegrees = 0;
                    $nbvalues = 0;
                    foreach ($allchoices as $choice) {
                        if ($choice) {
                            // check for number from 1 to 3 digits, followed by the equal sign =
                             if (preg_match("/^[0-9]{1,3}=/", $choice)) {
                                $nbnameddegrees++;
                            } else {
                                $nbvalues++;
                                $ispossibleanswer = true;
                            }
                        }
                    }
                    // add carriage return and dummy blank space character for empty value
                    if (!$ispossibleanswer) {
                        $qformdata->allchoices.= "\n ";
                    }
                    // sanity checks for correct number of values in $qformdata->length
                    // sanity check for named degrees
                    if ($nbnameddegrees && $nbnameddegrees != $qformdata->length) {
                        $qformdata->length = $nbnameddegrees;
                    }
                    // sanity check for "no duplicate choices"" //dev jr 9 JUL 2010
                    if ($qformdata->precise == 2 && ($qformdata->length > $nbvalues || !$qformdata->length)) {
                        $qformdata->length = $nbvalues;
                    }
                }  elseif ($qformdata->type_id == QUESCHECK) {
                    // sanity checks for min and max checked boxes
                    $allchoices = $qformdata->allchoices;
                    $allchoices = explode("\n", $allchoices);
                    $nbvalues = count($allchoices);
                    if ($qformdata->length > $nbvalues) {
                        $qformdata->length = $nbvalues;
                    }
                    if ($qformdata->precise > $nbvalues) {
                        $qformdata->precise = $nbvalues;
                    }
                    $qformdata->precise = max($qformdata->length, $qformdata->precise);
                }
            }

            if (!empty($qformdata->qid)) {
                /// Update existing question:

                /// Handle any attachments in the content
                $qformdata->itemid  = $qformdata->content['itemid'];
                $qformdata->format  = $qformdata->content['format'];
                $qformdata->content = $qformdata->content['text'];
                $qformdata->content = file_save_draft_area_files($qformdata->itemid, $context->id, 'mod_questionnaire', 'question',
                                                                 $qformdata->qid, array('subdirs'=>true), $qformdata->content);

                $fields = array('name','type_id','length','precise','required','content');
                $question_record = new Object();
                $question_record->id = $qformdata->qid;
                foreach($fields as $f) {
                    if(isset($qformdata->$f))
                        $question_record->$f = trim($qformdata->$f);
                }
                $result = $DB->update_record('questionnaire_question', $question_record);
            } else {
                /// Create new question:
                // set the position to the end
                $sql = 'SELECT MAX(position) as maxpos FROM {questionnaire_question} '.
                       'WHERE survey_id = '.$qformdata->sid.' AND deleted = \'n\'';
                if ($record = $DB->get_record_sql($sql)) {
                    $qformdata->position = $record->maxpos + 1;
                } else {
                    $qformdata->position = 1;
                }

                /// Need to update any image content after the question is created, so create then update the content.
                $qformdata->survey_id = $qformdata->sid;
                $fields = array('survey_id','name','type_id','length','precise','required','position');
                $question_record = new Object();
                foreach($fields as $f) {
                    if(isset($qformdata->$f)) {
                        $question_record->$f = trim($qformdata->$f);
                    }
                }
                $question_record->content = '';
                $qformdata->qid = $DB->insert_record('questionnaire_question', $question_record);

                /// Handle any attachments in the content
                $qformdata->itemid  = $qformdata->content['itemid'];
                $qformdata->format  = $qformdata->content['format'];
                $qformdata->content = $qformdata->content['text'];
                $content            = file_save_draft_area_files($qformdata->itemid, $context->id, 'mod_questionnaire', 'question',
                                                                 $qformdata->qid, array('subdirs'=>true), $qformdata->content);
                $result = $DB->set_field('questionnaire_question', 'content', $content, array('id' => $qformdata->qid));
            }

            // UPDATE or INSERT rows for each of the question choices for this question
            if($has_choices[$qformdata->type_id]) {
                $cidx = 0;
                if (isset($question->choices) && !isset($qformdata->makecopy)) {
                    $oldcount = count($question->choices);
                    $echoice = reset($question->choices);
                    $ekey = key($question->choices);
                } else {
                    $oldcount = 0;
                }

                $newchoices = explode("\n", $qformdata->allchoices);
                $nidx = 0;
                $newcount = count($newchoices);

              while (($nidx < $newcount) && ($cidx < $oldcount)) {
                    if ($newchoices[$nidx] != $echoice->content) {
                        $newchoices[$nidx] = trim ($newchoices[$nidx]);
                        $result = $DB->set_field('questionnaire_quest_choice', 'content', $newchoices[$nidx], array('id' => $ekey));
                    }
                    $nidx++;
                    $echoice = next($question->choices);
                    $ekey = key($question->choices);
                    $cidx++;
                }

                while ($nidx < $newcount) {
                    /// New choices...
                   $choice_record = new Object();
                   $choice_record->question_id = $qformdata->qid;
                   $choice_record->content = trim($newchoices[$nidx]);
                   $result = $DB->insert_record('questionnaire_quest_choice', $choice_record);
                   $nidx++;
                }

                while ($cidx < $oldcount) {
                    $result = $DB->delete_records('questionnaire_quest_choice', array('id' => $ekey));
                    $echoice = next($question->choices);
                    $ekey = key($question->choices);
                    $cidx++;
                }
            }
            // make these field values 'sticky' for further new questions
            if (!isset($qformdata->required)) {
                $qformdata->required = 'n';
            }
            $SESSION->questionnaire->required =  $qformdata->required;
            $SESSION->questionnaire->type_id =  $qformdata->type_id;
            /// Switch to main screen
            $action = 'main';
            $reload = true;
        }
        $questions_form->set_data($question);
    }

/// Reload the form data if called for...
    if ($reload) {
        unset($questions_form);
        $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm);
        if ($action == 'main') {
            $questions_form = new questionnaire_questions_form('questions.php', $moveq);
            $sdata = clone($questionnaire->survey);
            $sdata->sid = $questionnaire->survey->id;
            $sdata->id = $cm->id;
            if (!empty($questionnaire->questions)) {
                $pos = 1;
                foreach ($questionnaire->questions as $qidx => $question) {
                    $sdata->{'pos_'.$qidx} = $pos;
                    $pos++;
                }
            }
            $questions_form->set_data($sdata);
        } else if ($action == 'question') {
            if ($qid != 0) {
                $question = clone($questionnaire->questions[$qid]);
                $question->qid = $question->id;
                $question->sid = $questionnaire->survey->id;
                $question->id = $cm->id;
                $draftid_editor = file_get_submitted_draft_itemid('question');
                $content = file_prepare_draft_area($draftid_editor, $context->id, 'mod_questionnaire', 'question',
                                                   $qid, array('subdirs'=>true), $question->content);
                $question->content = array('text' => $content, 'format' => FORMAT_HTML, 'itemid'=>$draftid_editor);
            } else {
                $question = new Object();
                $question->sid = $questionnaire->survey->id;
                $question->id = $cm->id;
                $question->type_id = $qtype;
                $question->type = $DB->get_field('questionnaire_question_type', 'type', array('id' => $qtype));
                $draftid_editor = file_get_submitted_draft_itemid('question');
                $content = file_prepare_draft_area($draftid_editor, $context->id, 'mod_questionnaire', 'question',
                                                   null, array('subdirs'=>true), '');
                $question->content = array('text' => $content, 'format' => FORMAT_HTML, 'itemid'=>$draftid_editor);
            }
            $questions_form = new questionnaire_edit_question_form('questions.php');
            $questions_form->set_data($question);
        }
    }

/// Print the page header
    if ($action == 'question') {
	    if (isset($question->qid)) {
	        $streditquestion = get_string('editquestion', 'questionnaire', questionnaire_get_type($question->type_id));
	    } else {
            $streditquestion = get_string('addnewquestion', 'questionnaire', questionnaire_get_type($question->type_id));
	    }
    } else {
        $streditquestion = get_string('questions', 'questionnaire');
    }

    $PAGE->set_title($streditquestion);
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->navbar->add($streditquestion);
    echo $OUTPUT->header();
    include('tabs.php');
    $questions_form->display();
    echo $OUTPUT->footer();