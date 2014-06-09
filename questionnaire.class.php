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

require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');

class questionnaire {

    // Class Properties.
    /*
     * The survey record.
     * @var object $survey
     */
     //  Todo var $survey; TODO.

    // Class Methods.

    /*
     * The class constructor
     *
     */
    public function __construct($id = 0, $questionnaire = null, &$course, &$cm, $addquestions = true) {
        global $DB;

        if ($id) {
            $questionnaire = $DB->get_record('questionnaire', array('id' => $id));
        }

        if (is_object($questionnaire)) {
            $properties = get_object_vars($questionnaire);
            foreach ($properties as $property => $value) {
                $this->$property = $value;
            }
        }

        if (!empty($this->sid)) {
            $this->add_survey($this->sid);
        }

        $this->course = $course;
        $this->cm = $cm;
        // When we are creating a brand new questionnaire, we will not yet have a context.
        if (!empty($cm) && !empty($this->id)) {
            $this->context = context_module::instance($cm->id);
        } else {
            $this->context = null;
        }

        if ($addquestions && !empty($this->sid)) {
            $this->add_questions($this->sid);
        }

        // Load the capabilities for this user and questionnaire, if not creating a new one.
        if (!empty($this->cm->id)) {
            $this->capabilities = questionnaire_load_capabilities($this->cm->id);
        }
    }

    /**
     * Adding a survey record to the object.
     *
     */
    public function add_survey($sid = 0, $survey = null) {
        global $DB;

        if ($sid) {
            $this->survey = $DB->get_record('questionnaire_survey', array('id' => $sid));
        } else if (is_object($survey)) {
            $this->survey = clone($survey);
        }
    }

    /**
     * Adding questions to the object.
     */
    public function add_questions($sid = false, $section = false) {
        global $DB;

        if ($sid === false) {
            $sid = $this->sid;
        }

        if (!isset($this->questions)) {
            $this->questions = array();
            $this->questionsbysec = array();
        }

        $select = 'survey_id = '.$sid.' AND deleted != \'y\'';
        if ($records = $DB->get_records_select('questionnaire_question', $select, null, 'position')) {
            $sec = 1;
            $isbreak = false;
            foreach ($records as $record) {
                $this->questions[$record->id] = new questionnaire_question(0, $record, $this->context);
                if ($record->type_id != QUESPAGEBREAK) {
                    $this->questionsbysec[$sec][$record->id] = &$this->questions[$record->id];
                    $isbreak = false;
                } else {
                    // Sanity check: no section break allowed as first position, no 2 consecutive section breaks.
                    if ($record->position != 1 && $isbreak == false) {
                        $sec++;
                        $isbreak = true;
                    }
                }
            }
        }
    }

    public function view() {
        global $CFG, $USER, $PAGE, $OUTPUT;

        $PAGE->set_title(format_string($this->name));
        $PAGE->set_heading(format_string($this->course->fullname));

        // Initialise the JavaScript.
        $PAGE->requires->js_init_call('M.mod_questionnaire.init_attempt_form', null, false, questionnaire_get_js_module());

        echo $OUTPUT->header();

        $questionnaire = $this;

        if (!$this->cm->visible && !$this->capabilities->viewhiddenactivities) {
                notice(get_string("activityiscurrentlyhidden"));
        }

        if (!$this->capabilities->view) {
            echo('<br/>');
            questionnaire_notify(get_string("guestsno", "questionnaire", $this->name));
            echo('<div><a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">'.
                get_string("continue").'</a></div>');
            exit;
        }

        // Print the main part of the page.

        if (!$this->is_active()) {
            echo '<div class="notifyproblem">'
            .get_string('notavail', 'questionnaire')
            .'</div>';
        } else if (!$this->is_open()) {
                echo '<div class="notifyproblem">'
                .get_string('notopen', 'questionnaire', userdate($this->opendate))
                .'</div>';
        } else if ($this->is_closed()) {
            echo '<div class="notifyproblem">'
            .get_string('closed', 'questionnaire', userdate($this->closedate))
            .'</div>';
        } else if (!$this->user_is_eligible($USER->id)) {
            echo '<div class="notifyproblem">'
            .get_string('noteligible', 'questionnaire')
            .'</div>';
        } else if ($this->user_can_take($USER->id)) {
            $sid = $this->sid;
            $quser = $USER->id;

            if ($this->survey->realm == 'template') {
                print_string('templatenotviewable', 'questionnaire');
                echo $OUTPUT->footer($this->course);
                exit();
            }

            $msg = $this->print_survey($USER->id, $quser);

            // If Survey was submitted with all required fields completed ($msg is empty),
            // then record the submittal.
            $viewform = data_submitted($CFG->wwwroot."/mod/questionnaire/complete.php");
            if (!empty($viewform->rid)) {
                $viewform->rid = (int)$viewform->rid;
            }
            if (!empty($viewform->sec)) {
                $viewform->sec = (int)$viewform->sec;
            }
            if (data_submitted() && confirm_sesskey() && isset($viewform->submit) && isset($viewform->submittype) &&
                ($viewform->submittype == "Submit Survey") && empty($msg)) {
                $this->response_delete($viewform->rid, $viewform->sec);
                $this->rid = $this->response_insert($this->survey->id, $viewform->sec, $viewform->rid, $quser);
                $this->response_commit($this->rid);

                // If it was a previous save, rid is in the form...
                if (!empty($viewform->rid) && is_numeric($viewform->rid)) {
                    $rid = $viewform->rid;

                    // Otherwise its in this object.
                } else {
                    $rid = $this->rid;
                }

                questionnaire_record_submission($this, $USER->id, $rid);

                if ($this->grade != 0) {
                    $questionnaire = new Object();
                    $questionnaire->id = $this->id;
                    $questionnaire->name = $this->name;
                    $questionnaire->grade = $this->grade;
                    $questionnaire->cmidnumber = $this->cm->idnumber;
                    $questionnaire->courseid = $this->course->id;
                    questionnaire_update_grades($questionnaire, $quser);
                }

                // Update completion state.
                $completion = new completion_info($this->course);
                if ($completion->is_enabled($this->cm) && $this->completionsubmit) {
                    $completion->update_state($this->cm, COMPLETION_COMPLETE);
                }

                add_to_log($this->course->id, "questionnaire", "submit", "view.php?id={$this->cm->id}", "{$this->name}",
                    $this->cm->id, $USER->id);

                $this->response_send_email($this->rid);
                $this->response_goto_thankyou();
            }

        } else {
            switch ($this->qtype) {
                case QUESTIONNAIREDAILY:
                    $msgstring = ' '.get_string('today', 'questionnaire');
                    break;
                case QUESTIONNAIREWEEKLY:
                    $msgstring = ' '.get_string('thisweek', 'questionnaire');
                    break;
                case QUESTIONNAIREMONTHLY:
                    $msgstring = ' '.get_string('thismonth', 'questionnaire');
                    break;
                default:
                    $msgstring = '';
                    break;
            }
            echo ('<div class="notifyproblem">'.get_string("alreadyfilled", "questionnaire", $msgstring).'</div>');
        }

        // Finish the page.
        echo $OUTPUT->footer($this->course);
    }

    /*
    * Function to view an entire responses data.
    *
    */
    public function view_response($rid, $referer= '', $blankquestionnaire = false, $resps = '', $compare = false,
                        $isgroupmember = false, $allresponses = false, $currentgroupid = 0) {
        global $OUTPUT;

        $this->print_survey_start('', 1, 1, 0, $rid, false);

        $data = new Object();
        $i = 0;
        $this->response_import_all($rid, $data);
        if ($referer != 'print') {
            $feedbackmessages = $this->response_analysis($rid, $resps, $compare, $isgroupmember, $allresponses, $currentgroupid);

            if ($feedbackmessages) {
                echo $OUTPUT->heading(get_string('feedbackreport', 'questionnaire'), 3);
                foreach ($feedbackmessages as $msg) {
                    echo $msg;
                }
            }

            if ($this->survey->feedbacknotes) {
                $text = file_rewrite_pluginfile_urls($this->survey->feedbacknotes, 'pluginfile.php',
                                $this->context->id, 'mod_questionnaire', 'feedbacknotes', $this->survey->id);
                echo $OUTPUT->box_start();
                echo format_text($text, FORMAT_HTML);
                echo $OUTPUT->box_end();
            }
        }
        foreach ($this->questions as $question) {
            if ($question->type_id < QUESPAGEBREAK) {
                $i++;
            }
            if ($question->type_id != QUESPAGEBREAK) {
                $question->response_display($data, $i);
            }
        }
    }

    /*
    * Function to view an entire responses data.
    *
    */
    public function view_all_responses($resps) {
        global $qtypenames, $OUTPUT;
        $this->print_survey_start('', 1, 1, 0);

        // If a student's responses have been deleted by teacher while student was viewing the report,
        // then responses may have become empty, hence this test is necessary.
        if ($resps) {
            foreach ($resps as $resp) {
                $data[$resp->id] = new Object();
                $this->response_import_all($resp->id, $data[$resp->id]);
            }

            $i = 0;

            foreach ($this->questions as $question) {
                if ($question->type_id < QUESPAGEBREAK) {
                    $i++;
                }
                $qid = 'q'.$question->id;
                if ($question->type_id != QUESPAGEBREAK) {
                    $method = $qtypenames[$question->type_id].'_response_display';
                    if (method_exists($question, $method)) {
                        echo $OUTPUT->box_start('individualresp');
                        $question->questionstart_survey_display($i);
                        foreach ($data as $respid => $respdata) {
                            $hasresp = false;
                            foreach ($respdata as $key => $value) {
                                $pos = strpos($key, $qid);
                                if ($hasresp = preg_match("/$qid(_|$)/", $key)) {
                                    break;
                                }
                            }
                            // Do not display empty responses.
                            if ($hasresp) {
                                echo '<div class="respdate">'.userdate($resps[$respid]->submitted).'</div>';
                                $question->$method($respdata);
                            }
                        }
                        $question->questionend_survey_display($i);
                        echo $OUTPUT->box_end();
                    } else {
                        print_error('displaymethod', 'questionnaire');
                    }
                }
            }
        } else {
            echo (get_string('noresponses', 'questionnaire'));
        }

        $this->print_survey_end(1, 1);
    }

    // Access Methods.
    public function is_active() {
        return (!empty($this->survey));
    }

    public function is_open() {
        return ($this->opendate > 0) ? ($this->opendate < time()) : true;
    }

    public function is_closed() {
        return ($this->closedate > 0) ? ($this->closedate < time()) : false;
    }

    public function user_can_take($userid) {

        if (!$this->is_active() || !$this->user_is_eligible($userid)) {
            return false;
        } else if ($this->qtype == QUESTIONNAIREUNLIMITED) {
            return true;
        } else if ($userid > 0) {
            return $this->user_time_for_new_attempt($userid);
        } else {
            return false;
        }
    }

    public function user_is_eligible($userid) {
        return ($this->capabilities->view && $this->capabilities->submit);
    }

    public function user_time_for_new_attempt($userid) {
        global $DB;

        $select = 'qid = '.$this->id.' AND userid = '.$userid;
        if (!($attempts = $DB->get_records_select('questionnaire_attempts', $select, null, 'timemodified DESC'))) {
            return true;
        }

        $attempt = reset($attempts);
        $timenow = time();

        switch ($this->qtype) {

            case QUESTIONNAIREUNLIMITED:
                $cantake = true;
                break;

            case QUESTIONNAIREONCE:
                $cantake = false;
                break;

            case QUESTIONNAIREDAILY:
                $attemptyear = date('Y', $attempt->timemodified);
                $currentyear = date('Y', $timenow);
                $attemptdayofyear = date('z', $attempt->timemodified);
                $currentdayofyear = date('z', $timenow);
                $cantake = (($attemptyear < $currentyear) ||
                            (($attemptyear == $currentyear) && ($attemptdayofyear < $currentdayofyear)));
                break;

            case QUESTIONNAIREWEEKLY:
                $attemptyear = date('Y', $attempt->timemodified);
                $currentyear = date('Y', $timenow);
                $attemptweekofyear = date('W', $attempt->timemodified);
                $currentweekofyear = date('W', $timenow);
                $cantake = (($attemptyear < $currentyear) ||
                            (($attemptyear == $currentyear) && ($attemptweekofyear < $currentweekofyear)));
                break;

            case QUESTIONNAIREMONTHLY:
                $attemptyear = date('Y', $attempt->timemodified);
                $currentyear = date('Y', $timenow);
                $attemptmonthofyear = date('n', $attempt->timemodified);
                $currentmonthofyear = date('n', $timenow);
                $cantake = (($attemptyear < $currentyear) ||
                            (($attemptyear == $currentyear) && ($attemptmonthofyear < $currentmonthofyear)));
                break;

            default:
                $cantake = false;
                break;
        }

        return $cantake;
    }

    public function is_survey_owner() {
        return (!empty($this->survey->owner) && ($this->course->id == $this->survey->owner));
    }

    public function can_view_response($rid) {
        global $USER, $DB;

        if (!empty($rid)) {
            $response = $DB->get_record('questionnaire_response', array('id' => $rid));

            // If the response was not found, can't view it.
            if (empty($response)) {
                return false;
            }

            // If the response belongs to a different survey than this one, can't view it.
            if ($response->survey_id != $this->survey->id) {
                return false;
            }

            // If you can view all responses always, then you can view it.
            if ($this->capabilities->readallresponseanytime) {
                return true;
            }

            // If you are allowed to view this response for another user.
            if ($this->capabilities->readallresponses &&
                ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $this->is_closed()) ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED  && !$this->user_can_take($USER->id)))) {
                return true;
            }

             // If you can read your own response.
            if (($response->username == $USER->id) && $this->capabilities->readownresponses &&
                            ($this->count_submissions($USER->id) > 0)) {
                return true;
            }

        } else {
            // If you can view all responses always, then you can view it.
            if ($this->capabilities->readallresponseanytime) {
                return true;
            }

            // If you are allowed to view this response for another user.
            if ($this->capabilities->readallresponses &&
                ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $this->is_closed()) ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED  && !$this->user_can_take($USER->id)))) {
                return true;
            }

             // If you can read your own response.
            if ($this->capabilities->readownresponses && ($this->count_submissions($USER->id) > 0)) {
                return true;
            }
        }
    }

    public function count_submissions($userid=false) {
        global $DB;

        if (!$userid) {
            // Provide for groups setting.
            return $DB->count_records('questionnaire_response', array('survey_id' => $this->sid, 'complete' => 'y'));
        } else {
            return $DB->count_records('questionnaire_response', array('survey_id' => $this->sid, 'username' => $userid,
                                      'complete' => 'y'));
        }
    }

    private function has_required($section = 0) {
        if (empty($this->questions)) {
            return false;
        } else if ($section <= 0) {
            foreach ($this->questions as $question) {
                if ($question->required == 'y') {
                    return true;
                }
            }
        } else {
            foreach ($this->questionsbysec[$section] as $question) {
                if ($question->required == 'y') {
                    return true;
                }
            }
        }
        return false;
    }

    // Display Methods.

    public function print_survey($userid=false, $quser) {
        global $SESSION, $DB, $CFG;

        $formdata = new stdClass();
        if (data_submitted() && confirm_sesskey()) {
            $formdata = data_submitted();
        }
        $formdata->rid = $this->get_response($quser);
        // If student saved a "resume" questionnaire OR left a questionnaire unfinished
        // and there are more pages than one find the page of the last answered question.
        if (!empty($formdata->rid) && (empty($formdata->sec) || intval($formdata->sec) < 1)) {
            $formdata->sec = $this->response_select_max_sec($formdata->rid);
        }
        if (empty($formdata->sec)) {
            $formdata->sec = 1;
        } else {
            $formdata->sec = (intval($formdata->sec) > 0) ? intval($formdata->sec) : 1;
        }

        $numsections = isset($this->questionsbysec) ? count($this->questionsbysec) : 0;    // Indexed by section.
        $msg = '';
        $action = $CFG->wwwroot.'/mod/questionnaire/complete.php?id='.$this->cm->id;

        // TODO - Need to rework this. Too much crossover with ->view method.

        // Skip logic :: if this is page 1, it cannot be the end page with no questions on it!
        if ($formdata->sec == 1) {
            $SESSION->questionnaire->end = false;
        }
        // Skip logic: reset this just in case.
        $SESSION->questionnaire->nbquestionsonpage = '';

        if (!empty($formdata->submit)) {
            // Skip logic: we have reached the last page without any questions on it.
            if (isset($SESSION->questionnaire->end) && $SESSION->questionnaire->end == true) {
                return;
            }

            $msg = $this->response_check_format($formdata->sec, $formdata);
            if (empty($msg)) {
                return;
            }
        }

        if (!empty($formdata->resume) && ($this->resume)) {
            $this->response_delete($formdata->rid, $formdata->sec);
            $formdata->rid = $this->response_insert($this->survey->id, $formdata->sec, $formdata->rid, $quser, $resume = true);
            $this->response_goto_saved($action);
            return;
        }

        // Save each section 's $formdata somewhere in case user returns to that page when navigating the questionnaire.
        if (!empty($formdata->next)) {
            $this->response_delete($formdata->rid, $formdata->sec);
            $formdata->rid = $this->response_insert($this->survey->id, $formdata->sec, $formdata->rid, $quser);
            $msg = $this->response_check_format($formdata->sec, $formdata);
            if ( $msg ) {
                $formdata->next = '';
            } else {
                // Skip logic.
                $formdata->sec++;
                if (questionnaire_has_dependencies($this->questions)) {
                    $nbquestionsonpage = questionnaire_nb_questions_on_page($this->questions,
                                    $this->questionsbysec[$formdata->sec], $formdata->rid);
                    while (count($nbquestionsonpage) == 0) {
                        $this->response_delete($formdata->rid, $formdata->sec);
                        $formdata->sec++;
                        // We have reached the end of questionnaire on a page without any question left.
                        if ($formdata->sec > $numsections) {
                            $SESSION->questionnaire->end = true; // End of questionnaire reached on a no questions page.
                            break;
                        }
                        $nbquestionsonpage = questionnaire_nb_questions_on_page($this->questions,
                                        $this->questionsbysec[$formdata->sec], $formdata->rid);
                    }
                    $SESSION->questionnaire->nbquestionsonpage = $nbquestionsonpage;
                }
            }
        }

        if (!empty($formdata->prev)) {
            $this->response_delete($formdata->rid, $formdata->sec);

            // If skip logic and this is last page reached with no questions,
            // unlock questionnaire->end to allow navigate back to previous page.
            if (isset($SESSION->questionnaire->end) && $SESSION->questionnaire->end == true) {
                $SESSION->questionnaire->end = false;
                $formdata->sec --;
            }

                $formdata->rid = $this->response_insert($this->survey->id, $formdata->sec, $formdata->rid, $quser);
            // Prevent navigation to previous page if wrong format in answered questions).
            $msg = $this->response_check_format($formdata->sec, $formdata, $checkmissing = false, $checkwrongformat = true);
            if ( $msg ) {
                $formdata->prev = '';
            } else {
                $formdata->sec--;
                // Skip logic.
                if (questionnaire_has_dependencies($this->questions)) {
                    $nbquestionsonpage = questionnaire_nb_questions_on_page($this->questions,
                                    $this->questionsbysec[$formdata->sec], $formdata->rid);
                    while (count($nbquestionsonpage) == 0) {
                        $formdata->sec--;
                        $nbquestionsonpage = questionnaire_nb_questions_on_page($this->questions,
                                        $this->questionsbysec[$formdata->sec], $formdata->rid);
                    }
                    $SESSION->questionnaire->nbquestionsonpage = $nbquestionsonpage;
                }
            }
        }

        if (!empty($formdata->rid)) {
            $this->response_import_sec($formdata->rid, $formdata->sec, $formdata);
        }

        $formdatareferer = !empty($formdata->referer) ? htmlspecialchars($formdata->referer) : '';
        $formdatarid = isset($formdata->rid) ? $formdata->rid : '0';
        echo '<div class="generalbox">';
        echo '
                <form id="phpesp_response" method="post" action="'.$action.'">
                <div>
                <input type="hidden" name="referer" value="'.$formdatareferer.'" />
                <input type="hidden" name="a" value="'.$this->id.'" />
                <input type="hidden" name="sid" value="'.$this->survey->id.'" />
                <input type="hidden" name="rid" value="'.$formdatarid.'" />
                <input type="hidden" name="sec" value="'.$formdata->sec.'" />
                <input type="hidden" name="sesskey" value="'.sesskey().'" />
                </div>
            ';
        if (isset($this->questions) && $numsections) { // Sanity check.
            $this->survey_render($formdata->sec, $msg, $formdata);
            echo '<div class="notice" style="padding: 0.5em 0 0.5em 0.2em;"><div class="buttons">';
            if ($formdata->sec > 1) {
                echo '<input type="submit" name="prev" value="<<&nbsp;'.get_string('previouspage', 'questionnaire').'" />';
            }
            if ($this->resume) {
                echo '<input type="submit" name="resume" value="'.get_string('save', 'questionnaire').'" />';
            }

            //  Add a 'hidden' variable for the mod's 'view.php', and use a language variable for the submit button.

            if ($formdata->sec == $numsections) {
                echo '
                    <div><input type="hidden" name="submittype" value="Submit Survey" />
                    <input type="submit" name="submit" value="'.get_string('submitsurvey', 'questionnaire').'" /></div>';
            } else {
                echo '&nbsp;<div><input type="submit" name="next" value="'.
                                get_string('nextpage', 'questionnaire').'&nbsp;>>" /></div>';
            }
            echo '</div></div>'; // Divs notice & buttons.
            echo '</form>';
            echo '</div>'; // Div class="generalbox".

            return $msg;
        } else {
            echo '<p>'.get_string('noneinuse', 'questionnaire').'</p>';
            echo '</form>';
            echo '</div>';
        }
    }

    private function survey_render($section = 1, $message = '', &$formdata) {

        $this->usehtmleditor = null;

        if (empty($section)) {
            $section = 1;
        }
        $numsections = isset($this->questionsbysec) ? count($this->questionsbysec) : 0;
        if ($section > $numsections) {
            $formdata->sec = $numsections;
            echo '<div class=warning>'.get_string('finished', 'questionnaire').'</div>';
            return(false);  // Invalid section.
        }

        // Check to see if there are required questions.
        $hasrequired = $this->has_required($section);

        // Find out what question number we are on $i New fix for question numbering.
        $i = 0;
        if ($section > 1) {
            for ($j = 2; $j <= $section; $j++) {
                foreach ($this->questionsbysec[$j - 1] as $question) {
                    if ($question->type_id < QUESPAGEBREAK) {
                        $i++;
                    }
                }
            }
        }

        $this->print_survey_start($message, $section, $numsections, $hasrequired, '', 1);
        foreach ($this->questionsbysec[$section] as $question) {
            if ($question->type_id != QUESSECTIONTEXT) {
                $i++;
            }
            $question->survey_display($formdata, $descendantsdata = '', $i, $this->usehtmleditor);
            // Bug MDL-7292 - Don't count section text as a question number.
            // Process each question.
        }
        // End of questions.

        $this->print_survey_end($section, $numsections);

        return;
    }

    private function print_survey_start($message, $section, $numsections, $hasrequired, $rid='', $blankquestionnaire=false) {
        global $CFG, $DB, $OUTPUT;
        require_once($CFG->libdir.'/filelib.php');
        $userid = '';
        $resp = '';
        $groupname = '';
        $timesubmitted = '';
        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($rid) {
            $courseid = $this->course->id;
            if ($resp = $DB->get_record('questionnaire_response', array('id' => $rid)) ) {
                if ($this->respondenttype == 'fullname') {
                    $userid = $resp->username;
                    // Display name of group(s) that student belongs to... if questionnaire is set to Groups separate or visible.
                    if ($this->cm->groupmode > 0) {
                        if ($groups = groups_get_all_groups($courseid, $resp->username)) {
                            if (count($groups) == 1) {
                                $group = current($groups);
                                $groupname = ' ('.get_string('group').': '.$group->name.')';
                            } else {
                                $groupname = ' ('.get_string('groups').': ';
                                foreach ($groups as $group) {
                                    $groupname .= $group->name.', ';
                                }
                                $groupname = substr($groupname, 0, strlen($groupname) - 2).')';
                            }
                        } else {
                            $groupname = ' ('.get_string('groupnonmembers').')';
                        }
                    }
                }
            }
        }
        $ruser = '';
        if ($resp && !$blankquestionnaire) {
            if ($userid) {
                if ($user = $DB->get_record('user', array('id' => $userid))) {
                    $ruser = fullname($user);
                }
            }
            if ($this->respondenttype == 'anonymous') {
                $ruser = '- '.get_string('anonymous', 'questionnaire').' -';
            } else {
                // JR DEV comment following line out if you do NOT want time submitted displayed in Anonymous surveys.
                if ($resp->submitted) {
                    $timesubmitted = '&nbsp;'.get_string('submitted', 'questionnaire').'&nbsp;'.userdate($resp->submitted);
                }
            }
        }
        if ($ruser) {
            echo (get_string('respondent', 'questionnaire').': <strong>'.$ruser.'</strong>');
            if ($this->survey->realm == 'public') {
                // For a public questionnaire, look for the course that used it.
                $coursename = '';
                $sql = 'SELECT q.id, q.course, c.fullname '.
                       'FROM {questionnaire} q, {questionnaire_attempts} qa, {course} c '.
                       'WHERE qa.rid = ? AND q.id = qa.qid AND c.id = q.course';
                if ($record = $DB->get_record_sql($sql, array($rid))) {
                    $coursename = $record->fullname;
                }
                echo (' '.get_string('course'). ': '.$coursename);
            }
            echo ($groupname);
            echo ($timesubmitted);
        }
        echo '<h3 class="surveyTitle">'.s($this->survey->title).'</h3>';

        // We don't want to display the print icon in the print popup window itself!
        if ($this->capabilities->printblank && $blankquestionnaire && $section == 1) {
            // Open print friendly as popup window.
            $imageurl = $CFG->wwwroot.'/mod/questionnaire/images/';
            $linkname = '<img src="'.$imageurl.'print.gif" alt="Printer-friendly version" />';
            $title = get_string('printblanktooltip', 'questionnaire');
            $url = '/mod/questionnaire/print.php?qid='.$this->id.'&amp;rid=0&amp;'.'courseid='.$this->course->id.'&amp;sec=1';
            $options = array('menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
                    'height' => 600, 'width' => 800, 'title' => $title);
            $name = 'popup';
            $link = new moodle_url($url);
            $action = new popup_action('click', $link, $name, $options);
            $class = "floatprinticon";
            echo $OUTPUT->action_link($link, $linkname, $action, array('class' => $class, 'title' => $title));
        }
        if ($section == 1) {
            if ($this->survey->subtitle) {
                echo '<h4 class="surveySubtitle">'.(format_text($this->survey->subtitle, FORMAT_HTML)).'</h4>';
            }
            if ($this->survey->info) {
                $infotext = file_rewrite_pluginfile_urls($this->survey->info, 'pluginfile.php',
                                $this->context->id, 'mod_questionnaire', 'info', $this->survey->id);
                echo '<div class="addInfo">'.format_text($infotext, FORMAT_HTML).'</div>';
            }
        }

        if ($message) {
            echo '<div class="notifyproblem">'.$message.'</div>';
        }
    }

    private function print_survey_end($section, $numsections) {
        $autonum = $this->autonum;
        // If no questions autonumbering.
        if ($autonum < 3) {
            return;
        }
        if ($numsections > 1) {
            $a = new stdClass();
            $a->page = $section;
            $a->totpages = $numsections;
            echo ('<div class="surveyPage">');
            echo get_string('pageof', 'questionnaire', $a).'&nbsp;&nbsp;';
            echo '</div>';
        }
    }

    // Blankquestionnaire : if we are printing a blank questionnaire.
    public function survey_print_render($message = '', $referer='', $courseid, $rid=0, $blankquestionnaire=false) {
        global $USER, $DB, $OUTPUT, $CFG;

        if (! $course = $DB->get_record("course", array("id" => $courseid))) {
            print_error('incorrectcourseid', 'questionnaire');
        }

        $this->course = $course;

        if (!empty($rid)) {
            // If we're viewing a response, use this method.
            $this->view_response($rid, $referer, $blankquestionnaire);
            return;
        }

        if (empty($section)) {
            $section = 1;
        }

        if (isset($this->questionsbysec)) {
            $numsections = count($this->questionsbysec);
        } else {
            $numsections = 0;
        }

        if ($section > $numsections) {
            return(false);  // Invalid section.
        }

        $hasrequired = $this->has_required();

        // Find out what question number we are on $i.
        $i = 1;
        for ($j = 2; $j <= $section; $j++) {
            $i += count($this->questionsbysec[$j - 1]);
        }

        $action = $CFG->wwwroot.'/mod/questionnaire/preview.php?id='.$this->cm->id;
        echo '<form id="phpesp_response" method="post" action="'.$action.'">
                                ';
        // Print all sections.
        $formdata = new stdClass();
        $errors = 1;
        if (data_submitted()) {
            $formdata = data_submitted();
            $pageerror = '';
            $s = 1;
            $errors = 0;
            foreach ($this->questionsbysec as $section) {
                $errormessage = $this->response_check_format($s, $formdata);
                if ($errormessage) {
                    if ($numsections > 1) {
                        $pageerror = get_string('page', 'questionnaire').' '.$s.' : ';
                    }
                    echo '<div class="notifyproblem">'.$pageerror.$errormessage.'</div>';
                    $errors++;
                }
                $s ++;
            }
        }

        echo $OUTPUT->box_start();

        $this->print_survey_start($message, $section = 1, 1, $hasrequired, $rid = '');

        $descendantsandchoices = array();

        if ($referer == 'preview' && questionnaire_has_dependencies($this->questions) ) {
                $descendantsandchoices = questionnaire_get_descendants_and_choices($this->questions);
        }
        if ($errors == 0) {
            echo '<div class="message">'.get_string('submitpreviewcorrect', 'questionnaire').'</div>';
        }

        $page = 1;
        foreach ($this->questionsbysec as $section) {
            if ($numsections > 1) {
                echo ('<div class="surveyPage">'.get_string('page', 'questionnaire').' '.$page.'</div>');
                $page++;
            }
            foreach ($section as $question) {
                $descendantsdata = array();
                if ($question->type_id == QUESSECTIONTEXT) {
                    $i--;
                }
                if ($referer == 'preview' && $descendantsandchoices && ($question->type_id == QUESYESNO
                                || $question->type_id == QUESRADIO || $question->type_id == QUESDROP) ) {
                    if (isset ($descendantsandchoices['descendants'][$question->id])) {
                        $descendantsdata['descendants'] = $descendantsandchoices['descendants'][$question->id];
                        $descendantsdata['choices'] = $descendantsandchoices['choices'][$question->id];
                    }
                }

                $question->survey_display($formdata, $descendantsdata, $i++, $usehtmleditor = null, $blankquestionnaire, $referer);
            }
        }
        // End of questions.
        if ($referer == 'preview' && !$blankquestionnaire) {
            $url = $CFG->wwwroot.'/mod/questionnaire/preview.php?id='.$this->cm->id;
            echo '
                    <div>
                        <input type="submit" name="submit" value="'.get_string('submitpreview', 'questionnaire').'" />
                        <a href="'.$url.'">'.get_string('reset').'</a>
                    </div>
                ';
        }
        echo $OUTPUT->box_end();
        return;
    }

    public function survey_update($sdata) {
        global $DB;

        $errstr = ''; // TODO: notused!

        // New survey.
        if (empty($this->survey->id)) {
            // Create a new survey in the database.
            $fields = array('name', 'realm', 'title', 'subtitle', 'email', 'theme', 'thanks_page', 'thank_head',
                            'thank_body', 'feedbacknotes', 'info', 'feedbacksections', 'feedbackscores', 'chart_type');
            // Theme field deprecated.
            $record = new Object();
            $record->id = 0;
            $record->owner = $sdata->owner;
            foreach ($fields as $f) {
                if (isset($sdata->$f)) {
                    $record->$f = $sdata->$f;
                }
            }

            $this->survey = new stdClass();
            $this->survey->id = $DB->insert_record('questionnaire_survey', $record);
            $this->add_survey($this->survey->id);

            if (!$this->survey->id) {
                $errstr = get_string('errnewname', 'questionnaire') .' [ :  ]'; // TODO: notused!
                return(false);
            }
        } else {
            if (empty($sdata->name) || empty($sdata->title)
                    || empty($sdata->realm)) {
                return(false);
            }
            if (!isset($sdata->chart_type)) {
                $sdata->chart_type = '';
            }

            $fields = array('name', 'realm', 'title', 'subtitle', 'email', 'theme', 'thanks_page',
                    'thank_head', 'thank_body', 'feedbacknotes', 'info', 'feedbacksections', 'feedbackscores', 'chart_type');
            $name = $DB->get_field('questionnaire_survey', 'name', array('id' => $this->survey->id));

            // Trying to change survey name.
            if (trim($name) != trim(stripslashes($sdata->name))) {  // $sdata will already have slashes added to it.
                $count = $DB->count_records('questionnaire_survey', array('name' => $sdata->name));
                if ($count != 0) {
                    $errstr = get_string('errnewname', 'questionnaire');  // TODO: notused!
                    return(false);
                }
            }

            // UPDATE the row in the DB with current values.
            $surveyrecord = new Object();
            $surveyrecord->id = $this->survey->id;
            foreach ($fields as $f) {
                $surveyrecord->$f = trim($sdata->{$f});
            }

            $result = $DB->update_record('questionnaire_survey', $surveyrecord);
            if (!$result) {
                $errstr = get_string('warning', 'questionnaire').' [ :  ]';  // TODO: notused!
                return(false);
            }
        }

        return($this->survey->id);
    }

    /* Creates an editable copy of a survey. */
    public function survey_copy($owner) {
        global $DB;

        // Clear the sid, clear the creation date, change the name, and clear the status.
        $survey = clone($this->survey);

        unset($survey->id);
        $survey->owner = $owner;
        // Make sure that the survey name is not larger than the field size (CONTRIB-2999). Leave room for extra chars.
        $survey->name = core_text::substr($survey->name, 0, (64 - 10));

        $survey->name .= '_copy';
        $survey->status = 0;

        // Check for 'name' conflict, and resolve.
        $i = 0;
        $name = $survey->name;
        while ($DB->count_records('questionnaire_survey', array('name' => $name)) > 0) {
            $name = $survey->name.(++$i);
        }
        if ($i) {
            $survey->name .= $i;
        }

        // Create new survey.
        if (!($newsid = $DB->insert_record('questionnaire_survey', $survey))) {
            return(false);
        }

        // Make copies of all the questions.
        $pos = 1;
        // Skip logic: some changes needed here for dependencies down below.
        $qidarray = array();
        $cidarray = array();
        foreach ($this->questions as $question) {
            // Fix some fields first.
            $oldid = $question->id;
            unset($question->id);
            $question->survey_id = $newsid;
            $question->position = $pos++;

            // Copy question to new survey.
            if (!($newqid = $DB->insert_record('questionnaire_question', $question))) {
                return(false);
            }
            $qidarray[$oldid] = $newqid;
            foreach ($question->choices as $key => $choice) {
                $oldcid = $key;
                unset($choice->id);
                $choice->question_id = $newqid;
                if (!$newcid = $DB->insert_record('questionnaire_quest_choice', $choice)) {
                    return(false);
                }
                $cidarray[$oldcid] = $newcid;
            }
        }
        // Skip logic: now we need to set the new values for dependencies.
        if ($newquestions = $DB->get_records('questionnaire_question', array('survey_id' => $newsid), 'id')) {
            foreach ($newquestions as $question) {
                if ($question->dependquestion != 0) {
                    $dependqtypeid = $this->questions[$question->dependquestion]->type_id;
                    $record = new object;
                    $record->id = $question->id;
                    $record->dependquestion = $qidarray[$question->dependquestion];
                    if ($dependqtypeid != 1) {
                        $record->dependchoice = $cidarray[$question->dependchoice];
                    }
                    $DB->update_record('questionnaire_question', $record);
                }
            }
        }

        return($newsid);
    }

    public function type_has_choices() {
        global $DB;

        $haschoices = array();

        if ($records = $DB->get_records('questionnaire_question_type', array(), 'typeid', 'typeid, has_choices')) {
            foreach ($records as $record) {
                if ($record->has_choices == 'y') {
                    $haschoices[$record->typeid] = 1;
                } else {
                    $haschoices[$record->typeid] = 0;
                }
            }
        } else {
            $haschoices = array();
        }

        return($haschoices);
    }

    private function array_to_insql($array) {
        if (count($array)) {
            return("IN (".preg_replace("/([^,]+)/", "'\\1'", join(",", $array)).")");
        }
        return 'IS NULL';
    }

    // RESPONSE LIBRARY.

    private function response_check_format($section, $formdata, $checkmissing = true, $checkwrongformat = true) {
        global $PAGE, $OUTPUT;
        $missing = 0;
        $strmissing = '';     // Missing questions.
        $wrongformat = 0;
        $strwrongformat = ''; // Wrongly formatted questions (Numeric, 5:Check Boxes, Date).
        $i = 1;
        for ($j = 2; $j <= $section; $j++) {
            // ADDED A SIMPLE LOOP FOR MAKING SURE PAGE BREAKS (type 99) AND LABELS (type 100) ARE NOT ALLOWED.
            foreach ($this->questionsbysec[$j - 1] as $sectionrecord) {
                $tid = $sectionrecord->type_id;
                if ($tid < QUESPAGEBREAK) {
                    $i++;
                }
            }
        }
        $qnum = $i - 1;

        foreach ($this->questionsbysec[$section] as $question) {

            $qid = $question->id;
            $tid = $question->type_id;
            $lid = $question->length;
            $pid = $question->precise;
            if ($tid != QUESSECTIONTEXT) {
                $qnum++;
            }
            $missingresp = false;

            // Note: The "missing response" detection is carried out differently in Rate questions (see below).

            if ( ($question->required == 'y') && ($question->deleted == 'n') && ((isset($formdata->{'q'.$qid})
                        && $formdata->{'q'.$qid} == '')
                    || (!isset($formdata->{'q'.$qid}))) && $tid != QUESSECTIONTEXT && $tid != QUESRATE) {

                if ($PAGE->pagetype != 'mod-questionnaire-preview') {
                    $missingresp = true;

                } else if ($question->dependquestion == 0) {
                    $missingresp = true;

                } else { // For yes/no questions, convert 0 and 1 to y(es) and n(o).
                    if ($question->dependchoice == 0) {
                        $dependchoice = 'y';
                    } else if ($question->dependchoice == 1) {
                        $dependchoice = 'n';
                    } else {
                        $dependchoice = $question->dependchoice;
                    }
                    if (isset($formdata->{'q'.$question->dependquestion})
                            && $formdata->{'q'.$question->dependquestion} == $dependchoice) {
                        $missingresp = true;
                    }
                }
            }

            if ($missingresp) {
                $missing++;
                $strmissing .= get_string('num', 'questionnaire').$qnum.'. ';
            }

            switch ($tid) {

                case QUESRADIO: // Radio Buttons with !other field.
                    if (!isset($formdata->{'q'.$qid})) {
                        break;
                    }
                    $resp = $formdata->{'q'.$qid};
                    $pos = strpos($resp, 'other_');

                    // Other "other" choice is checked but text box is empty.
                    if (is_int($pos) == true) {
                        $othercontent = "q".$qid.substr($resp, 5);
                        if ( !$formdata->$othercontent ) {
                            $wrongformat++;
                            $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                            break;
                        }
                    }

                    if (is_int($pos) == true && $question->required == 'y') {
                        $resp = 'q'.$qid.''.substr($resp, 5);
                        if (!$formdata->$resp) {
                            $missing++;
                            $strmissing .= get_string('num', 'questionnaire').$qnum.'. ';
                        }
                    }
                    break;

                case QUESCHECK: // Check Boxes.
                    if (!isset($formdata->{'q'.$qid})) {
                        break;
                    }
                    $resps = $formdata->{'q'.$qid};
                    $nbrespchoices = 0;
                    foreach ($resps as $resp) {
                        $pos = strpos($resp, 'other_');

                        // Other "other" choice is checked but text box is empty.
                        if (is_int ($pos) == true) {
                            $othercontent = "q".$qid.substr($resp, 5);
                            if ( !$formdata->$othercontent ) {
                                $wrongformat++;
                                $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                                break;
                            }
                        }

                        if (is_numeric($resp) || is_int($pos) == true) {
                            $nbrespchoices++;
                        }
                    }
                    $nbquestchoices = count($question->choices);
                    $min = $lid;
                    $max = $pid;
                    if ($max == 0) {
                        $max = $nbquestchoices;
                    }
                    if ($min > $max) {
                        $min = $max;     // Sanity check.
                    }
                    $min = min($nbquestchoices, $min);
                    // Number of ticked boxes is not within min and max set limits.
                    if ( $nbrespchoices && ($nbrespchoices < $min || $nbrespchoices > $max) ) {
                        $wrongformat++;
                        $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                        break;
                    }
                    break;

                case QUESRATE: // Rate.
                    $num = 0;
                    $nbchoices = count($question->choices);
                    $na = get_string('notapplicable', 'questionnaire');
                    foreach ($question->choices as $cid => $choice) {
                        // In case we have named degrees on the Likert scale, count them to substract from nbchoices.
                        $nameddegrees = 0;
                        $content = $choice->content;
                        if (preg_match("/^[0-9]{1,3}=/", $content, $ndd)) {
                            $nameddegrees++;
                        } else {
                            $str = 'q'."{$question->id}_$cid";
                            if (isset($formdata->$str) && $formdata->$str == $na) {
                                $formdata->$str = -1;
                            }
                            // If choice value == -999 this is a not yet answered choice.
                            $num += (isset($formdata->$str) && ($formdata->$str != -999));
                        }
                        $nbchoices -= $nameddegrees;
                    }

                    if ($num == 0) {
                        $missingresp = false;
                        if ($PAGE->pagetype != 'mod-questionnaire-preview' || $question->dependquestion == 0) {
                            if ($question->required == 'y') {
                                $missingresp = true;
                            }
                        } else {
                            if (isset($formdata->{'q'.$question->dependquestion})
                                    && $formdata->{'q'.$question->dependquestion} == $question->dependchoice) {
                                $missingresp = true;
                            }
                        }
                        if ($missingresp) {
                            $missing++;
                            $strmissing .= get_string('num', 'questionnaire').$qnum.'. ';
                        }
                    }
                    // If nodupes and nb choice restricted, nbchoices may be > actual choices, so limit it to $question->length.
                    $isrestricted = ($question->length < count($question->choices)) && $question->precise == 2;
                    if ($isrestricted) {
                        $nbchoices = min ($nbchoices, $question->length);
                    }
                    if ( $num != $nbchoices && $num != 0 ) {
                        $wrongformat++;
                        $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                    }
                    break;

                case QUESDATE: // Date.
                    if (!isset($formdata->{'q'.$qid})) {
                        break;
                    }
                    $checkdateresult = '';
                    if ($formdata->{'q'.$qid} != '') {
                        $checkdateresult = questionnaire_check_date($formdata->{'q'.$qid});
                    }
                    if (substr($checkdateresult, 0, 5) == 'wrong') {
                        $wrongformat++;
                        $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                    }
                    break;

                case QUESNUMERIC: // Numeric.
                    if (!isset($formdata->{'q'.$qid})) {
                        break;
                    }
                    if ( ($formdata->{'q'.$qid} != '') && (!is_numeric($formdata->{'q'.$qid})) ) {
                        $wrongformat++;
                        $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                    }
                    break;

                default:
                break;
            }
        }
        $message = '';
        $nonumbering = false;
        $autonum = $this->autonum;
        // If no questions autonumbering do not display missing question(s) number(s).
        if ($autonum != 1 && $autonum != 3) {
            $nonumbering = true;
        }
        if ($checkmissing && $missing) {
            if ($nonumbering) {
                $strmissing = '';
            }
            if ($missing == 1) {
                $message = get_string('missingquestion', 'questionnaire').$strmissing;
            } else {
                $message = get_string('missingquestions', 'questionnaire').$strmissing;
            }
            if ($wrongformat) {
                $message .= '<br />';
            }
        }
        if ($checkwrongformat && $wrongformat) {
            if ($nonumbering) {
                $message .= get_string('wronganswers', 'questionnaire');
            } else {
                if ($wrongformat == 1) {
                    $message .= get_string('wrongformat', 'questionnaire').$strwrongformat;
                } else {
                    $message .= get_string('wrongformats', 'questionnaire').$strwrongformat;
                }
            }
        }
        return ($message);
    }

    private function response_delete($rid, $sec = null) {
        global $DB;

        if (empty($rid)) {
            return;
        }

        if ($sec != null) {
            if ($sec < 1) {
                return;
            }

            // Skip logic.
            $numsections = isset($this->questionsbysec) ? count($this->questionsbysec) : 0;
            $sec = min($numsections , $sec);

            /* get question_id's in this section */
            $qids = '';
            foreach ($this->questionsbysec[$sec] as $question) {
                if (empty($qids)) {
                    $qids .= ' AND question_id IN ('.$question->id;
                } else {
                    $qids .= ','.$question->id;
                }
            }
            if (!empty($qids)) {
                $qids .= ')';
            } else {
                return;
            }
        } else {
            /* delete all */
            $qids = '';
        }

        /* delete values */
        $select = 'response_id = \''.$rid.'\' '.$qids;
        foreach (array('response_bool', 'resp_single', 'resp_multiple', 'response_rank', 'response_text',
                       'response_other', 'response_date') as $tbl) {
            $DB->delete_records_select('questionnaire_'.$tbl, $select);
        }
    }

    private function response_import_sec($rid, $sec, &$varr) {
        if ($sec < 1 || !isset($this->questionsbysec[$sec])) {
            return;
        }
        $vals = $this->response_select($rid, 'content');
        reset($vals);
        foreach ($vals as $id => $arr) {
            if (isset($arr[0]) && is_array($arr[0])) {
                // Multiple.
                $varr->{'q'.$id} = array_map('array_pop', $arr);
            } else {
                $varr->{'q'.$id} = array_pop($arr);
            }
        }
    }

    private function response_import_all($rid, &$varr) {

        $vals = $this->response_select($rid, 'content');
        reset($vals);
        foreach ($vals as $id => $arr) {
            if (strstr($id, '_') && isset($arr[4])) { // Single OR multiple with !other choice selected.
                $varr->{'q'.$id} = $arr[4];
            } else {
                if (isset($arr[0]) && is_array($arr[0])) { // Multiple.
                    $varr->{'q'.$id} = array_map('array_pop', $arr);
                } else { // Boolean, rate and other.
                    $varr->{'q'.$id} = array_pop($arr);
                }
            }
        }
    }

    private function response_commit($rid) {
        global $DB;

        $record = new object;
        $record->id = $rid;
        $record->complete = 'y';
        $record->submitted = time();

        if ($this->grade < 0) {
            $record->grade = 1;  // Don't know what to do if its a scale...
        } else {
            $record->grade = $this->grade;
        }
        return $DB->update_record('questionnaire_response', $record);
    }

    private function get_response($username, $rid = 0) {
        global $DB;

        $rid = intval($rid);
        if ($rid != 0) {
            // Check for valid rid.
            $fields = 'id, username';
            $select = 'id = '.$rid.' AND survey_id = '.$this->sid.' AND username = \''.$username.'\' AND complete = \'n\'';
            return ($DB->get_record_select('questionnaire_response', $select, null, $fields) !== false) ? $rid : '';

        } else {
            // Find latest in progress rid.
            $select = 'survey_id = '.$this->sid.' AND complete = \'n\' AND username = \''.$username.'\'';
            if ($records = $DB->get_records_select('questionnaire_response', $select, null, 'submitted DESC',
                                              'id,survey_id', 0, 1)) {
                $rec = reset($records);
                return $rec->id;
            } else {
                return '';
            }
        }
    }

    // Returns the number of the section in which questions have been answered in a response.
    private function response_select_max_sec($rid) {
        global $DB;

        $pos = $this->response_select_max_pos($rid);
        $select = 'survey_id = \''.$this->sid.'\' AND type_id = 99 AND position < '.$pos.' AND deleted = \'n\'';
        $max = $DB->count_records_select('questionnaire_question', $select) + 1;

        return $max;
    }

    // Returns the position of the last answered question in a response.
    private function response_select_max_pos($rid) {
        global $DB;

        $max = 0;

        foreach (array('response_bool', 'resp_single', 'resp_multiple', 'response_rank', 'response_text',
                       'response_other', 'response_date') as $tbl) {
            $sql = 'SELECT MAX(q.position) as num FROM {questionnaire_'.$tbl.'} a, {questionnaire_question} q '.
                   'WHERE a.response_id = ? AND '.
                   'q.id = a.question_id AND '.
                   'q.survey_id = ? AND '.
                   'q.deleted = \'n\'';
            if ($record = $DB->get_record_sql($sql, array($rid, $this->sid))) {
                $newmax = (int)$record->num;
                if ($newmax > $max) {
                    $max = $newmax;
                }
            }
        }
        return $max;
    }

    /* {{{ proto array response_select_name(int survey_id, int response_id, array question_ids)
       A wrapper around response_select(), that returns an array of
       key/value pairs using the field name as the key.
       $csvexport = true: a parameter to return a different response formatting for CSV export from normal report formatting
     */
    private function response_select_name($rid, $choicecodes, $choicetext) {
        $res = $this->response_select($rid, 'position, type_id, name', true, $choicecodes, $choicetext);
        $nam = array();
        reset($res);
        $subqnum = 0;
        $oldpos = '';
        while (list($qid, $arr) = each($res)) {
            // Question position (there may be "holes" in positions list).
            $qpos = $arr[0];
            // Question type (1:bool,2:text,3:essay,4:radio,5:check,6:dropdn,7:rating(not used),8:rate,9:date,10:numeric).
            $qtype = $arr[1];
            // Variable name; (may be empty); for rate questions: 'variable group' name.
            $qname = $arr[2];
            // Modality; for rate questions: variable.
            $qchoice = $arr[3];

            // Strip potential html tags from modality name.
            if (!empty($qchoice)) {
                $qchoice = strip_tags($arr[3]);
                $qchoice = preg_replace("/[\r\n\t]/", ' ', $qchoice);
            }
            // For rate questions: modality; for multichoice: selected = 1; not selected = 0.
            $q4 = '';
            if (isset($arr[4])) {
                $q4 = $arr[4];
            }
            if (strstr($qid, '_')) {
                if ($qtype == QUESRADIO) {     // Single.
                    $nam[$qpos][$qname.'_'.get_string('other', 'questionnaire')] = $q4;
                    continue;
                }
                // Multiple OR rank.
                if ($oldpos != $qpos) {
                    $subqnum = 1;
                    $oldpos = $qpos;
                } else {
                        $subqnum++;
                }
                if ($qtype == QUESRATE) {     // Rate.
                    $qname .= "->$qchoice";
                    if ($q4 == -1) {
                        // Here $q4 = get_string('notapplicable', 'questionnaire'); DEV JR choose one solution please.
                        $q4 = '';
                    } else {
                        if (is_numeric($q4)) {
                            $q4++;
                        }
                    }
                } else {     // Multiple.
                    $qname .= "->$qchoice";
                }
                $nam[$qpos][$qname] = $q4;
                continue;
            }
            $val = $qchoice;
            $nam[$qpos][$qname] = $val;
        }
        return $nam;
    }

    private function response_send_email($rid, $userid=false) {
        global $CFG, $USER, $DB;

        require_once($CFG->libdir.'/phpmailer/class.phpmailer.php');

        $name = s($this->name);
        if ($record = $DB->get_record('questionnaire_survey', array('id' => $this->survey->id))) {
            $email = $record->email;
        } else {
            $email = '';
        }

        if (empty($email)) {
            return(false);
        }
        $answers = $this->generate_csv($rid, $userid = '', null, 1, $groupid = 0);

        // Line endings for html and plaintext emails.
        $endhtml = "\r\n<br>";
        $endplaintext = "\r\n";

        $subject = get_string('surveyresponse', 'questionnaire') .": $name [$rid]";
        $url = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$this->survey->id.
                '&amp;rid='.$rid.'&amp;instance='.$this->id;

        // Html and plaintext body.
        $bodyhtml        = '<a href="'.$url.'">'.$url.'</a>'.$endhtml;
        $bodyplaintext   = $url.$endplaintext;
        $bodyhtml       .= get_string('surveyresponse', 'questionnaire') .' "'.$name.'"'.$endhtml;
        $bodyplaintext  .= get_string('surveyresponse', 'questionnaire') .' "'.$name.'"'.$endplaintext;

        reset($answers);

        for ($i = 0; $i < count($answers[0]); $i++) {
            $sep = ' : ';

            switch($i) {
                case 1:
                    $sep = ' ';
                    break;
                case 4:
                    $bodyhtml        .= get_string('user').' ';
                    $bodyplaintext   .= get_string('user').' ';
                    break;
                case 6:
                    if ($this->respondenttype != 'anonymous') {
                        $bodyhtml         .= get_string('email').$sep.$USER->email. $endhtml;
                        $bodyplaintext    .= get_string('email').$sep.$USER->email. $endplaintext;
                    }
            }
            $bodyhtml         .= $answers[0][$i].$sep.$answers[1][$i]. $endhtml;
            $bodyplaintext    .= $answers[0][$i].$sep.$answers[1][$i]. $endplaintext;
        }

        // Use plaintext version for altbody.
        $altbody = "\n$bodyplaintext\n";

        $return = true;
        $mailaddresses = preg_split('/,|;/', $email);
        foreach ($mailaddresses as $email) {
            $userto = new Object();
            $userto->email = $email;
            $userto->mailformat = 1;
            // Dummy userid to keep email_to_user happy in moodle 2.6.
            $userto->id = -10;
            $userfrom = $CFG->noreplyaddress;
            if (email_to_user($userto, $userfrom, $subject, $altbody, $bodyhtml)) {
                $return = $return && true;
            } else {
                $return = false;
            }
        }
        return $return;
    }

    private function response_insert($sid, $section, $rid, $userid, $resume=false) {
        global $DB, $USER;

        $record = new object;
        $record->submitted = time();

        if (empty($rid)) {
            // Create a uniqe id for this response.
            $record->survey_id = $sid;
            $record->username = $userid;
            $rid = $DB->insert_record('questionnaire_response', $record);
        } else {
            $record->id = $rid;
            $DB->update_record('questionnaire_response', $record);
        }
        if ($resume) {
            add_to_log($this->course->id, "questionnaire", "save", "view.php?id={$this->cm->id}",
                "{$this->name}", $this->cm->id, $USER->id);
        }

        if (!empty($this->questionsbysec[$section])) {
            foreach ($this->questionsbysec[$section] as $question) {
                $question->insert_response($rid);
            }
        }
        return($rid);
    }

    private function response_select($rid, $col = null, $csvexport = false, $choicecodes=0, $choicetext=1) {
        global $DB;

        $sid = $this->survey->id;
        $values = array();
        $stringother = get_string('other', 'questionnaire');
        if ($col == null) {
            $col = '';
        }
        if (!is_array($col) && !empty($col)) {
            $col = explode(',', preg_replace("/\s/", '', $col));
        }
        if (is_array($col) && count($col) > 0) {
            $col = ',' . implode(',', array_map(create_function('$a', 'return "q.$a";'), $col));
        }

        // Response_bool (yes/no).
        $sql = 'SELECT q.id '.$col.', a.choice_id '.
               'FROM {questionnaire_response_bool} a, {questionnaire_question} q '.
               'WHERE a.response_id= ? AND a.question_id=q.id ';
        if ($records = $DB->get_records_sql($sql, array($rid))) {
            foreach ($records as $qid => $row) {
                $choice = $row->choice_id;
                if (isset ($row->name) && $row->name == '') {
                    $noname = true;
                }
                unset ($row->id);
                unset ($row->choice_id);
                $row = (array)$row;
                $newrow = array();
                foreach ($row as $key => $val) {
                    if (!is_numeric($key)) {
                        $newrow[] = $val;
                    }
                }
                $values[$qid] = $newrow;
                array_push($values["$qid"], ($choice == 'y') ? '1' : '0');
                if (!$csvexport) {
                    array_push($values["$qid"], $choice); // DEV still needed for responses display.
                }
            }
        }

        // Response_single (radio button or dropdown).
        $sql = 'SELECT q.id '.$col.', q.type_id as q_type, c.content as ccontent,c.id as cid '.
               'FROM {questionnaire_resp_single} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
               'WHERE a.response_id = ? AND a.question_id=q.id AND a.choice_id=c.id ';
        if ($records = $DB->get_records_sql($sql, array($rid))) {
            foreach ($records as $qid => $row) {
                $cid = $row->cid;
                $qtype = $row->q_type;
                if ($csvexport) {
                    static $i = 1;
                    $qrecords = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
                    foreach ($qrecords as $value) {
                        if ($value->id == $cid) {
                            $contents = questionnaire_choice_values($value->content);
                            if ($contents->modname) {
                                $row->ccontent = $contents->modname;
                            } else {
                                $content = $contents->text;
                                if (preg_match('/^!other/', $content)) {
                                    $row->ccontent = get_string('other', 'questionnaire');
                                } else if (($choicecodes == 1) && ($choicetext == 1)) {
                                    $row->ccontent = "$i : $content";
                                } else if ($choicecodes == 1) {
                                    $row->ccontent = "$i";
                                } else {
                                    $row->ccontent = $content;
                                }
                            }
                            $i = 1;
                            break;
                        }
                        $i++;
                    }
                }
                unset($row->id);
                unset($row->cid);
                unset($row->q_type);
                $arow = get_object_vars($row);
                $newrow = array();
                foreach ($arow as $key => $val) {
                    if (!is_numeric($key)) {
                        $newrow[] = $val;
                    }
                }
                if (preg_match('/^!other/', $row->ccontent)) {
                    $newrow[] = 'other_' . $cid;
                } else {
                    $newrow[] = (int)$cid;
                }
                $values[$qid] = $newrow;
            }
        }

        // Response_multiple.
        $sql = 'SELECT a.id as aid, q.id as qid '.$col.',c.content as ccontent,c.id as cid '.
               'FROM {questionnaire_resp_multiple} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
               'WHERE a.response_id = ? AND a.question_id=q.id AND a.choice_id=c.id '.
               'ORDER BY a.id,a.question_id,c.id';
        $records = $DB->get_records_sql($sql, array($rid));
        if ($csvexport) {
            $tmp = null;
            if (!empty($records)) {
                $qids2 = array();
                $oldqid = '';
                foreach ($records as $qid => $row) {
                    if ($row->qid != $oldqid) {
                        $qids2[] = $row->qid;
                        $oldqid = $row->qid;
                    }
                }
                if (is_array($qids2)) {
                    $qids2 = 'question_id ' . $this->array_to_insql($qids2);
                } else {
                    $qids2 = 'question_id= ' . $qids2;
                }
                $sql = 'SELECT * FROM {questionnaire_quest_choice} WHERE '.$qids2.
                    'ORDER BY id';
                if ($records2 = $DB->get_records_sql($sql)) {
                    foreach ($records2 as $qid => $row2) {
                        $selected = '0';
                        $qid2 = $row2->question_id;
                        $cid2 = $row2->id;
                        $c2 = $row2->content;
                        $otherend = false;
                        if ($c2 == '!other') {
                            $c2 = '!other='.get_string('other', 'questionnaire');
                        }
                        if (preg_match('/^!other/', $c2)) {
                            $otherend = true;
                        } else {
                            $contents = questionnaire_choice_values($c2);
                            if ($contents->modname) {
                                $c2 = $contents->modname;
                            } else if ($contents->title) {
                                $c2 = $contents->title;
                            }
                        }
                        $sql = 'SELECT a.name as name, a.type_id as q_type, a.position as pos ' .
                                'FROM {questionnaire_question} a WHERE id = ?';
                        if ($currentquestion = $DB->get_records_sql($sql, array($qid2))) {
                            foreach ($currentquestion as $question) {
                                $name1 = $question->name;
                                $type1 = $question->q_type;
                            }
                        }
                        $newrow = array();
                        foreach ($records as $qid => $row1) {
                            $qid1 = $row1->qid;
                            $cid1 = $row1->cid;
                            // If available choice has been selected by student.
                            if ($qid1 == $qid2 && $cid1 == $cid2) {
                                $selected = '1';
                            }
                        }
                        if ($otherend) {
                            $newrow2 = array();
                            $newrow2[] = $question->pos;
                            $newrow2[] = $type1;
                            $newrow2[] = $name1;
                            $newrow2[] = '['.get_string('other', 'questionnaire').']';
                            $newrow2[] = $selected;
                            $tmp2 = $qid2.'_other';
                            $values["$tmp2"] = $newrow2;
                        }
                        $newrow[] = $question->pos;
                        $newrow[] = $type1;
                        $newrow[] = $name1;
                        $newrow[] = $c2;
                        $newrow[] = $selected;
                        $tmp = $qid2.'_'.$cid2;
                        $values["$tmp"] = $newrow;
                    }
                }
            }
            unset($tmp);
            unset($row);

        } else {
                $arr = array();
                $tmp = null;
            if (!empty($records)) {
                foreach ($records as $aid => $row) {
                    $qid = $row->qid;
                    $cid = $row->cid;
                    unset($row->aid);
                    unset($row->qid);
                    unset($row->cid);
                    $arow = get_object_vars($row);
                    $newrow = array();
                    foreach ($arow as $key => $val) {
                        if (!is_numeric($key)) {
                            $newrow[] = $val;
                        }
                    }
                    if (preg_match('/^!other/', $row->ccontent)) {
                        $newrow[] = 'other_' . $cid;
                    } else {
                        $newrow[] = (int)$cid;
                    }
                    if ($tmp == $qid) {
                        $arr[] = $newrow;
                        continue;
                    }
                    if ($tmp != null) {
                        $values["$tmp"] = $arr;
                    }
                    $tmp = $qid;
                    $arr = array($newrow);
                }
            }
            if ($tmp != null) {
                $values["$tmp"] = $arr;
            }
            unset($arr);
            unset($tmp);
            unset($row);
        }

            // Response_other.
            // This will work even for multiple !other fields within one question
            // AND for identical !other responses in different questions JR.
        $sql = 'SELECT c.id as cid, c.content as content, a.response as aresponse, q.id as qid, q.position as position,
                                    q.type_id as type_id, q.name as name '.
               'FROM {questionnaire_response_other} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
               'WHERE a.response_id= ? AND a.question_id=q.id AND a.choice_id=c.id '.
               'ORDER BY a.question_id,c.id ';
        if ($records = $DB->get_records_sql($sql, array($rid))) {
            foreach ($records as $record) {
                $newrow = array();
                $position = $record->position;
                $typeid = $record->type_id;
                $name = $record->name;
                $cid = $record->cid;
                $qid = $record->qid;
                $content = $record->content;

                // The !other modality with no label.
                if ($content == '!other') {
                    $content = '!other='.$stringother;
                }
                $content = substr($content, 7);
                $aresponse = $record->aresponse;
                // The first two empty values are needed for compatibility with "normal" (non !other) responses.
                // They are only needed for the CSV export, in fact.
                $newrow[] = $position;
                $newrow[] = $typeid;
                $newrow[] = $name;
                $content = $stringother;
                $newrow[] = $content;
                $newrow[] = $aresponse;
                $values["${qid}_${cid}"] = $newrow;
            }
        }

        // Response_rank.
        $sql = 'SELECT a.id as aid, q.id AS qid, q.precise AS precise, c.id AS cid '.$col.', c.content as ccontent,
                                a.rank as arank '.
               'FROM {questionnaire_response_rank} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
               'WHERE a.response_id= ? AND a.question_id=q.id AND a.choice_id=c.id '.
               'ORDER BY aid, a.question_id, c.id';
        if ($records = $DB->get_records_sql($sql, array($rid))) {
            foreach ($records as $row) {
                // Next two are 'qid' and 'cid', each with numeric and hash keys.
                $osgood = false;
                if ($row->precise == 3) {
                    $osgood = true;
                }
                $qid = $row->qid.'_'.$row->cid;
                unset($row->aid); // Get rid of the answer id.
                unset($row->qid);
                unset($row->cid);
                unset($row->precise);
                $row = (array)$row;
                $newrow = array();
                foreach ($row as $key => $val) {
                    if ($key != 'content') { // No need to keep question text - ony keep choice text and rank.
                        if ($key == 'ccontent') {
                            if ($osgood) {
                                list($contentleft, $contentright) = preg_split('/[|]/', $val);
                                $contents = questionnaire_choice_values($contentleft);
                                if ($contents->title) {
                                    $contentleft = $contents->title;
                                }
                                $contents = questionnaire_choice_values($contentright);
                                if ($contents->title) {
                                    $contentright = $contents->title;
                                }
                                $val = strip_tags($contentleft.'|'.$contentright);
                                $val = preg_replace("/[\r\n\t]/", ' ', $val);
                            } else {
                                $contents = questionnaire_choice_values($val);
                                if ($contents->modname) {
                                    $val = $contents->modname;
                                } else if ($contents->title) {
                                    $val = $contents->title;
                                } else if ($contents->text) {
                                    $val = strip_tags($contents->text);
                                    $val = preg_replace("/[\r\n\t]/", ' ', $val);
                                }
                            }
                        }
                        $newrow[] = $val;
                    }
                }
                $values[$qid] = $newrow;
            }
        }

        // Response_text.
        $sql = 'SELECT q.id '.$col.', a.response as aresponse '.
               'FROM {questionnaire_response_text} a, {questionnaire_question} q '.
               'WHERE a.response_id=\''.$rid.'\' AND a.question_id=q.id ';
        if ($records = $DB->get_records_sql($sql)) {
            foreach ($records as $qid => $row) {
                unset($row->id);
                $row = (array)$row;
                $newrow = array();
                foreach ($row as $key => $val) {
                    if (!is_numeric($key)) {
                        $newrow[] = $val;
                    }
                }
                $values["$qid"] = $newrow;
                $val = array_pop($values["$qid"]);
                array_push($values["$qid"], $val, $val);
            }
        }

        // Response_date.
        $sql = 'SELECT q.id '.$col.', a.response as aresponse '.
               'FROM {questionnaire_response_date} a, {questionnaire_question} q '.
               'WHERE a.response_id=\''.$rid.'\' AND a.question_id=q.id ';
        if ($records = $DB->get_records_sql($sql)) {
            $dateformat = get_string('strfdate', 'questionnaire');
            foreach ($records as $qid => $row) {
                unset ($row->id);
                $row = (array)$row;
                $newrow = array();
                foreach ($row as $key => $val) {
                    if (!is_numeric($key)) {
                        $newrow[] = $val;
                        // Convert date from yyyy-mm-dd database format to actual questionnaire dateformat.
                        // does not work with dates prior to 1900 under Windows.
                        if (preg_match('/\d\d\d\d-\d\d-\d\d/', $val)) {
                            $dateparts = preg_split('/-/', $val);
                            $val = make_timestamp($dateparts[0], $dateparts[1], $dateparts[2]); // Unix timestamp.
                            $val = userdate ( $val, $dateformat);
                            $newrow[] = $val;
                        }
                    }
                }
                $values["$qid"] = $newrow;
                $val = array_pop($values["$qid"]);
                array_push($values["$qid"], '', '', $val);
            }
        }
        return($values);
    }

    private function response_goto_thankyou() {
        global $CFG, $USER, $DB;

        $select = 'id = '.$this->survey->id;
        $fields = 'thanks_page, thank_head, thank_body';
        if ($result = $DB->get_record_select('questionnaire_survey', $select, null, $fields)) {
            $thankurl = $result->thanks_page;
            $thankhead = $result->thank_head;
            $thankbody = $result->thank_body;
        } else {
            $thankurl = '';
            $thankhead = '';
            $thankbody = '';
        }
        if (!empty($thankurl)) {
            if (!headers_sent()) {
                header("Location: $thankurl");
                exit;
            }
            echo '
                <script language="JavaScript" type="text/javascript">
                <!--
                window.location="'.$thankurl.'"
                //-->
                </script>
                <noscript>
                <h2 class="thankhead">Thank You for completing this survey.</h2>
                <blockquote class="thankbody">Please click
                <a href="'.$thankurl.'">here</a> to continue.</blockquote>
                </noscript>
            ';
            exit;
        }
        if (empty($thankhead)) {
            $thankhead = get_string('thank_head', 'questionnaire');
        }
        $message = '<h3>'.$thankhead.'</h3>'.format_text(file_rewrite_pluginfile_urls($thankbody, 'pluginfile.php',
                        $this->context->id, 'mod_questionnaire', 'thankbody', $this->survey->id), FORMAT_HTML);
        echo ($message);
        // Default set currentgroup to view all participants.
        // TODO why not set to current respondent's groupid (if any)?
        $currentgroupid = 0;
        $currentgroupid = groups_get_activity_group($this->cm);
        if (!groups_is_member($currentgroupid, $USER->id)) {
            $currentgroupid = 0;
        }
        if ($this->capabilities->readownresponses) {
            echo('<a href="'.$CFG->wwwroot.'/mod/questionnaire/myreport.php?id='.
            $this->cm->id.'&amp;instance='.$this->cm->instance.'&amp;user='.$USER->id.'&byresponse=0&action=vresp">'.
            get_string("continue").'</a>');
        } else {
            echo('<a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">'.
            get_string("continue").'</a>');
        }
        return;
    }

    private function response_goto_saved($url) {
        global $CFG;
        $resumesurvey = get_string('resumesurvey', 'questionnaire');
        $savedprogress = get_string('savedprogress', 'questionnaire', '<strong>'.$resumesurvey.'</strong>');

        echo '
                <div class="thankbody">'.$savedprogress.'</div>
                <div class="homelink"><a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">&nbsp;&nbsp;'
                    .get_string("backto", "moodle", $this->course->fullname).'&nbsp;&nbsp;</a></div>
             ';
        return;
    }

    // Survey Results Methods.

    public function survey_results_navbar_alpha($currrid, $currentgroupid, $cm, $byresponse) {
        global $CFG, $DB, $OUTPUT;
        // Is this questionnaire set to fullname or anonymous?
        $isfullname = $this->respondenttype != 'anonymous';
        if ($isfullname) {
            $selectgroupid = '';
            $gmuserid = ', GM.userid ';
            $groupmembers = ', '.$CFG->prefix.'groups_members GM ';
            $castsql = $DB->sql_cast_char2int('R.username');
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $gmuserid = '';
                    $groupmembers = '';
                    break;
                default:     // Members of a specific group.
                    $selectgroupid = ' AND GM.groupid='.$currentgroupid.' AND '.$castsql.' = GM.userid ';
            }
            $sql = 'SELECT R.id AS responseid, R.submitted AS submitted, R.username, U.username AS username,
                            U.id as userid '.$gmuserid.
            'FROM '.$CFG->prefix.'questionnaire_response R,
            '.$CFG->prefix.'user U
            '.$groupmembers.
            'WHERE R.survey_id='.$this->survey->id.
            ' AND complete = \'y\''.
            ' AND U.id = '.$castsql.
            $selectgroupid.
            'ORDER BY U.lastname, U.firstname, R.submitted DESC';
        } else {
            $sql = 'SELECT R.id AS responseid, R.submitted
                   FROM {questionnaire_response} R
                   WHERE R.survey_id = ?
                   AND complete = ?
                   ORDER BY R.submitted DESC';
        }
        if (!$responses = $DB->get_records_sql ($sql, array('survey_id' => $this->survey->id, 'complete' => 'y'))) {
            return;
        }
        $total = count($responses);
        if ($total === 0) {
            return;
        }
        $rids = array();
        if ($isfullname) {
            $ridssub = array();
            $ridsuserfullname = array();
            $ridsuserid = array();
        }
        $i = 0;
        $currpos = -1;
        foreach ($responses as $response) {
            array_push($rids, $response->responseid);
            if ($isfullname) {
                $user = $DB->get_record('user', array('id' => $response->userid));
                $userfullname = fullname($user);
                array_push($ridssub, $response->submitted);
                array_push($ridsuserfullname, fullname($user));
                array_push($ridsuserid, $response->userid);
            }
            if ($response->responseid == $currrid) {
                $currpos = $i;
            }
            $i++;
        }

        $url = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;group='.$currentgroupid;
        $linkarr = array();
        if (!$byresponse) {     // Display navbar.
            // Build navbar.
            $prevrid = ($currpos > 0) ? $rids[$currpos - 1] : null;
            $nextrid = ($currpos < $total - 1) ? $rids[$currpos + 1] : null;
            $firstrid = $rids[0];
            $lastrid = $rids[$total - 1];
            $displaypos = 1;
            if ($prevrid != null) {
                $pos = $currpos - 1;
                if ($isfullname) {
                    $responsedate = userdate($ridssub[$pos]);
                    $title = $ridsuserfullname[$pos];
                    // Only add date if more than one response by a student.
                    if ($ridsuserid[$pos] == $ridsuserid[$currpos]) {
                        $title .= ' | '.$responsedate;
                    }
                    $firstuserfullname = $ridsuserfullname[0];
                    array_push($linkarr, '<b><<</b> <a href="'.$url.'&amp;rid='.$firstrid.'&amp;individualresponse=1" title="'.
                                    $firstuserfullname.'">'.
                                    get_string('firstrespondent', 'questionnaire').'</a>');
                    array_push($linkarr, '<b><&nbsp;</b><a href="'.$url.'&amp;rid='.$prevrid.'&amp;individualresponse=1"
                                    title="'.$title.'">'.get_string('previous').'</a>');
                } else {
                    $title = '';
                    array_push($linkarr, '<b><<</b> <a href="'.$url.'&amp;rid='.$firstrid.'&amp;individualresponse=1" title="'.
                        $title.'">'.get_string('firstrespondent', 'questionnaire').'</a>');
                    array_push($linkarr, '<b><&nbsp;</b><a href="'.$url.'&amp;rid='.$prevrid.'&amp;individualresponse=1"
                                title="'.$title.'">'.get_string('previous').'</a>');
                }
            }
            array_push($linkarr, '<b>'.($currpos + 1).' / '.$total.'</b>');
            if ($nextrid != null) {
                $pos = $currpos + 1;
                $responsedate = '';
                $title = '';
                $lastuserfullname = '';
                if ($isfullname) {
                    $responsedate = userdate($ridssub[$pos]);
                    $title = $ridsuserfullname[$pos];
                    // Only add date if more than one response by a student.
                    if ($ridsuserid[$pos] == $ridsuserid[$currpos]) {
                        $title .= ' | '.$responsedate;
                    }
                    $lastuserfullname = $ridsuserfullname[$total - 1];
                }
                array_push($linkarr, '<a href="'.$url.'&amp;rid='.$nextrid.'&amp;individualresponse=1"
                                title="'.$title.'">'.get_string('next').'</a>&nbsp;<b>></b>');
                array_push($linkarr, '<a href="'.$url.'&amp;rid='.$lastrid.'&amp;individualresponse=1"
                                title="'.$lastuserfullname .'">'.
                                get_string('lastrespondent', 'questionnaire').'</a>&nbsp;<b>>></b>');
            }
            $url = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&byresponse=1&group='.$currentgroupid;
            // Display navbar.
            echo $OUTPUT->box_start('respondentsnavbar');
            echo implode(' | ', $linkarr);
            echo '<br /><b><<< <a href="'.$url.'">'.get_string('viewbyresponse', 'questionnaire').'</a></b>';

            // Display a "print this response" icon here in prevision of total removal of tabs in version 2.6.
            $linkname = get_string('print', 'questionnaire');
            $imageurl = $CFG->wwwroot.'/mod/questionnaire/images/';
            $linkname = '<img src="'.$imageurl.'print.gif" alt="Printer-friendly version" />';
            $url = '/mod/questionnaire/print.php?qid='.$this->id.'&amp;rid='.$currrid.
            '&amp;courseid='.$this->course->id.'&amp;sec=1';
            $title = get_string('printtooltip', 'questionnaire');
            $options = array('menubar' => true, 'location' => false, 'scrollbars' => true,
                            'resizable' => true, 'height' => 600, 'width' => 800);
            $name = 'popup';
            $link = new moodle_url($url);
            $action = new popup_action('click', $link, $name, $options);
            $actionlink = $OUTPUT->action_link($link, $linkname, $action, array('title' => $title));
            echo '&nbsp;|&nbsp;'.$actionlink;

            echo $OUTPUT->box_end();

        } else { // Display respondents list.
            for ($i = 0; $i < $total; $i++) {
                if ($isfullname) {
                    $responsedate = userdate($ridssub[$i]);
                    array_push($linkarr, '<a title = "'.$responsedate.'" href="'.$url.'&amp;rid='.
                        $rids[$i].'&amp;individualresponse=1" >'.$ridsuserfullname[$i].'</a>'.'&nbsp;');
                } else {
                    $responsedate = '';
                    array_push($linkarr, '<a title = "'.$responsedate.'" href="'.$url.'&amp;rid='.
                        $rids[$i].'&amp;individualresponse=1" >'.
                        get_string('response', 'questionnaire').($i + 1).'</a>'.'&nbsp;');
                }
            }
            // Table formatting from http://wikkawiki.org/PageAndCategoryDivisionInACategory.
            $total = count($linkarr);
            $entries = count($linkarr);
            // Default max 3 columns, max 25 lines per column.
            // TODO make this setting customizable.
            $maxlines = 20;
            $maxcols = 3;
            if ($entries >= $maxlines) {
                $colnumber = min (intval($entries / $maxlines), $maxcols);
            } else {
                $colnumber = 1;
            }
            $lines = 0;
            $a = 0;
            $str = '';
            // How many lines with an entry in every column do we have?
            while ($entries / $colnumber > 1) {
                $lines++;
                $entries = $entries - $colnumber;
            }
            // Prepare output.
            for ($i = 0; $i < $colnumber; $i++) {
                $str .= '<div id="respondentscolumn">'."\n";
                for ($j = 0; $j < $lines; $j++) {
                    $str .= $linkarr[$a].'<br />'."\n";
                    $a++;
                }
                // The rest of the entries (less than the number of cols).
                if ($entries) {
                    $str .= $linkarr[$a].'<br />'."\n";
                    $entries--;
                    $a++;
                }
                $str .= "</div>\n";
            }
            $str .= '<div style="clear: both;">'."</div>\n";
            echo $OUTPUT->box_start();
            echo ($str);
            echo $OUTPUT->box_end();
        }
    }

    // Display responses for current user (your responses).
    public function survey_results_navbar_student($currrid, $userid, $instance, $resps, $reporttype='myreport', $sid='') {
        global $DB, $OUTPUT;
        $stranonymous = get_string('anonymous', 'questionnaire');

        $total = count($resps);
        $rids = array();
        $ridssub = array();
        $ridsusers = array();
        $i = 0;
        $currpos = -1;
        $title = '';
        foreach ($resps as $response) {
            array_push($rids, $response->id);
            array_push($ridssub, $response->submitted);
            $ruser = '';
            if ($reporttype == 'report') {
                if ($this->respondenttype != 'anonymous') {
                    if ($user = $DB->get_record('user', array('id' => $response->username))) {
                        $ruser = ' | ' .fullname($user);
                    }
                } else {
                    $ruser = ' | ' . $stranonymous;
                }
            }
            array_push($ridsusers, $ruser);
            if ($response->id == $currrid) {
                $currpos = $i;
            }
            $i++;
        }
        $prevrid = ($currpos > 0) ? $rids[$currpos - 1] : null;
        $nextrid = ($currpos < $total - 1) ? $rids[$currpos + 1] : null;
        $rowsperpage = 1;

        if ($reporttype == 'myreport') {
            $url = 'myreport.php?instance='.$instance.'&amp;user='.$userid.'&amp;action=vresp&amp;byresponse=1';
        } else {
            $url = 'report.php?instance='.$instance.'&amp;user='.$userid.'&amp;action=vresp&amp;byresponse=1&amp;sid='.$sid;
        }
        $linkarr = array();
        $displaypos = 1;
        if ($prevrid != null) {
            $title = userdate($ridssub[$currpos - 1].$ridsusers[$currpos - 1]);
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$prevrid.'" title="'.$title.'">'.get_string('previous').'</a>');
        }
        for ($i = 0; $i < $currpos; $i++) {
            $title = userdate($ridssub[$i]).$ridsusers[$i];
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$rids[$i].'" title="'.$title.'">'.$displaypos.'</a>');
            $displaypos++;
        }
        array_push($linkarr, '<b>'.$displaypos.'</b>');
        for (++$i; $i < $total; $i++) {
            $displaypos++;
            $title = userdate($ridssub[$i]).$ridsusers[$i];
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$rids[$i].'" title="'.$title.'">'.$displaypos.'</a>');
        }
        if ($nextrid != null) {
            $title = userdate($ridssub[$currpos + 1]).$ridsusers[$currpos + 1];
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$nextrid.'" title="'.$title.'">'.get_string('next').'</a>');
        }
        echo $OUTPUT->box_start('respondentsnavbar');
        echo implode(' | ', $linkarr);
        echo $OUTPUT->box_end('respondentsnavbar');
    }

    /* {{{ proto string survey_results(int survey_id, int precision, bool show_totals, int question_id,
     * array choice_ids, int response_id)
        Builds HTML for the results for the survey. If a
        question id and choice id(s) are given, then the results
        are only calculated for respodants who chose from the
        choice ids for the given question id.
        Returns empty string on sucess, else returns an error
        string. */

    public function survey_results($precision = 1, $showtotals = 1, $qid = '', $cids = '', $rid = '',
                $uid=false, $currentgroupid='', $sort='') {
        global $SESSION, $DB;

        $SESSION->questionnaire->noresponses = false;
        if (empty($precision)) {
            $precision  = 1;
        }
        if ($showtotals === '') {
            $showtotals = 1;
        }

        if (is_int($cids)) {
            $cids = array($cids);
        }
        if (is_string($cids)) {
            $cids = preg_split("/ /", $cids); // Turn space seperated list into array.
        }

        // Build associative array holding whether each question
        // type has answer choices or not and the table the answers are in
        // TO DO - FIX BELOW TO USE STANDARD FUNCTIONS.
        $haschoices = array();
        $responsetable = array();
        if (!($types = $DB->get_records('questionnaire_question_type', array(), 'typeid', 'typeid, has_choices, response_table'))) {
            $errmsg = sprintf('%s [ %s: question_type ]',
                    get_string('errortable', 'questionnaire'), 'Table');
            return($errmsg);
        }
        foreach ($types as $type) {
            $haschoices[$type->typeid] = $type->has_choices; // TODO is that variable actually used?
            $responsetable[$type->typeid] = $type->response_table;
        }

        // Load survey title (and other globals).
        if (empty($this->survey)) {
            $errmsg = get_string('erroropening', 'questionnaire') ." [ ID:${sid} R:";
            return($errmsg);
        }

        if (empty($this->questions)) {
            $errmsg = get_string('erroropening', 'questionnaire') .' '. 'No questions found.';
            return($errmsg);
        }

        // Find total number of survey responses and relevant response ID's.
        if (!empty($rid)) {
            $rids = $rid;
            if (is_array($rids)) {
                $navbar = false;
            } else {
                $navbar = true;
            }
            $total = 1;
        } else {
            $navbar = false;
            $sql = "";
            $castsql = $DB->sql_cast_char2int('R.username');
            if ($uid !== false) { // One participant only.
                $sql = "SELECT r.id, r.survey_id
                          FROM {questionnaire_response} r
                         WHERE r.survey_id='{$this->survey->id}' AND
                               r.username = $uid AND
                               r.complete='y'
                         ORDER BY r.id";
                // All participants or all members of a group.
            } else if ($currentgroupid == 0) {
                $sql = "SELECT R.id, R.survey_id, R.username as userid
                          FROM {questionnaire_response} R
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y'
                         ORDER BY R.id";
            } else { // Members of a specific group.
                $sql = "SELECT R.id, R.survey_id
                          FROM {questionnaire_response} R,
                                {groups_members} GM
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               GM.groupid=".$currentgroupid." AND
                               ".$castsql."=GM.userid
                         ORDER BY R.id";
            }
            if (!($rows = $DB->get_records_sql($sql))) {
                echo (get_string('noresponses', 'questionnaire'));
                $SESSION->questionnaire->noresponses = true;
                return;
            }
            $total = count($rows);
            echo (' '.get_string('responses', 'questionnaire').": <strong>$total</strong>");
            if (empty($rows)) {
                $errmsg = get_string('erroropening', 'questionnaire') .' '. get_string('noresponsedata', 'questionnaire');
                    return($errmsg);
            }

            $rids = array();
            foreach ($rows as $row) {
                array_push($rids, $row->id);
            }
        }

        if ($navbar) {
            // Show response navigation bar.
            $this->survey_results_navbar($rid);
        }

        echo '<h3 class="surveyTitle">'.s($this->survey->title).'</h3>';
        if ($this->survey->subtitle) {
            echo('<h4>'.$this->survey->subtitle.'</h4>');
        }
        if ($this->survey->info) {
            $infotext = file_rewrite_pluginfile_urls($this->survey->info, 'pluginfile.php',
                $this->context->id, 'mod_questionnaire', 'info', $this->survey->id);
            echo '<div class="addInfo">'.format_text($infotext, FORMAT_HTML).'</div>';
        }

        $qnum = 0;

        foreach ($this->questions as $question) {
            if ($question->type_id == QUESPAGEBREAK) {
                continue;
            }
            echo html_writer::start_tag('div', array('class' => 'qn-container'));
            if ($question->type_id != QUESSECTIONTEXT) {
                $qnum++;
                echo html_writer::start_tag('div', array('class' => 'qn-info'));
                if ($question->type_id != QUESSECTIONTEXT) {
                    echo html_writer::tag('h2', $qnum, array('class' => 'qn-number'));
                }
                echo html_writer::end_tag('div'); // End qn-info.
            }
            echo html_writer::start_tag('div', array('class' => 'qn-content'));
            // If question text is "empty", i.e. 2 non-breaking spaces were inserted, do not display any question text.
            if ($question->content == '<p>  </p>') {
                $question->content = '';
            }
            echo html_writer::start_tag('div', array('class' => 'qn-question'));
            echo format_text(file_rewrite_pluginfile_urls($question->content, 'pluginfile.php',
                $question->context->id, 'mod_questionnaire', 'question', $question->id), FORMAT_HTML);
            echo html_writer::end_tag('div'); // End qn-question.

            $question->display_results($rids, $sort);
            echo html_writer::end_tag('div'); // End qn-content.

            echo html_writer::end_tag('div'); // End qn-container.
        }

        return;
    }

    /* {{{ proto array survey_generate_csv(int survey_id)
    Exports the results of a survey to an array.
    */
    public function generate_csv($rid='', $userid='', $choicecodes=1, $choicetext=0, $currentgroupid) {
        global $SESSION, $DB;

        $output = array();
        $nbinfocols = 9; // Change this if you want more info columns.
        $stringother = get_string('other', 'questionnaire');
        $columns = array(
                get_string('response', 'questionnaire'),
                get_string('submitted', 'questionnaire'),
                get_string('institution'),
                get_string('department'),
                get_string('course'),
                get_string('group'),
                get_string('id', 'questionnaire'),
                get_string('fullname'),
                get_string('username')
            );

        $types = array(
                0,
                0,
                1,
                1,
                1,
                1,
                0,
                1,
                1,
            );

        $arr = array();

        $idtocsvmap = array(
            '0',    // 0: unused
            '0',    // 1: bool -> boolean
            '1',    // 2: text -> string
            '1',    // 3: essay -> string
            '0',    // 4: radio -> string
            '0',    // 5: check -> string
            '0',    // 6: dropdn -> string
            '0',    // 7: rating -> number
            '0',    // 8: rate -> number
            '1',    // 9: date -> string
            '0'     // 10: numeric -> number.
        );

        if (!$survey = $DB->get_record('questionnaire_survey', array('id' => $this->survey->id))) {
            print_error ('surveynotexists', 'questionnaire');
        }

        $select = 'survey_id = '.$this->survey->id.' AND deleted = \'n\' AND type_id < 50';
        $fields = 'id, name, type_id, position';
        if (!($records = $DB->get_records_select('questionnaire_question', $select, null, 'position', $fields))) {
            $records = array();
        }

        $num = 1;
        foreach ($records as $record) {
            // Establish the table's field names.
            $qid = $record->id;
            $qpos = $record->position;
            $col = $record->name;
            $type = $record->type_id;
            if ($type == QUESRADIO || $type == QUESCHECK || $type == QUESRATE) {
                /* single or multiple or rate */
                $sql = "SELECT c.id as cid, q.id as qid, q.precise AS precise, q.name, c.content
                FROM {questionnaire_question} q ".
                "LEFT JOIN {questionnaire_quest_choice} c ON question_id = q.id ".
                'WHERE q.id = '.$qid.' ORDER BY cid ASC';
                if (!($records2 = $DB->get_records_sql($sql))) {
                    $records2 = array();
                }
                $subqnum = 0;
                switch ($type) {

                    case QUESRADIO: // Single.
                        $columns[][$qpos] = $col;
                        array_push($types, $idtocsvmap[$type]);
                        $thisnum = 1;
                        foreach ($records2 as $record2) {
                            $content = $record2->content;
                            if (preg_match('/^!other/', $content)) {
                                $col = $record2->name.'_'.$stringother;
                                $columns[][$qpos] = $col;
                                array_push($types, '0');
                            }
                        }
                        break;

                    case QUESCHECK: // Multiple.
                        $thisnum = 1;
                        foreach ($records2 as $record2) {
                            $content = $record2->content;
                            $modality = '';
                            if (preg_match('/^!other/', $content)) {
                                $content = $stringother;
                                $col = $record2->name.'->['.$content.']';
                                $columns[][$qpos] = $col;
                                array_push($types, '0');
                            }
                            $contents = questionnaire_choice_values($content);
                            if ($contents->modname) {
                                $modality = $contents->modname;
                            } else if ($contents->title) {
                                $modality = $contents->title;
                            } else {
                                $modality = strip_tags($contents->text);
                            }
                            $col = $record2->name.'->'.$modality;
                            $columns[][$qpos] = $col;
                            array_push($types, '0');
                        }
                        break;

                    case QUESRATE: // Rate.
                        foreach ($records2 as $record2) {
                            $nameddegrees = 0;
                            $modality = '';
                            $content = $record2->content;
                            $osgood = false;
                            if ($record2->precise == 3) {
                                $osgood = true;
                            }
                            if (preg_match("/^[0-9]{1,3}=/", $content, $ndd)) {
                                $nameddegrees++;
                            } else {
                                if ($osgood) {
                                    list($contentleft, $contentright) = preg_split('/[|]/', $content);
                                    $contents = questionnaire_choice_values($contentleft);
                                    if ($contents->title) {
                                        $contentleft = $contents->title;
                                    }
                                    $contents = questionnaire_choice_values($contentright);
                                    if ($contents->title) {
                                        $contentright = $contents->title;
                                    }
                                    $modality = strip_tags($contentleft.'|'.$contentright);
                                    $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                } else {
                                    $contents = questionnaire_choice_values($content);
                                    if ($contents->modname) {
                                        $modality = $contents->modname;
                                    } else if ($contents->title) {
                                        $modality = $contents->title;
                                    } else {
                                        $modality = strip_tags($contents->text);
                                        $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                    }
                                }
                                $col = $record2->name.'->'.$modality;
                                $columns[][$qpos] = $col;
                                array_push($types, $idtocsvmap[$type]);
                            }
                        }
                        break;
                }
            } else {
                $columns[][$qpos] = $col;
                array_push($types, $idtocsvmap[$type]);
            }
            $num++;
        }
        array_push($output, $columns);
        $numcols = count($output[0]);

        if ($rid) {             // Send e-mail for a unique response ($rid).
            $select = 'survey_id = '.$this->survey->id.' AND complete=\'y\' AND id = '.$rid;
            $fields = 'id,submitted,username';
            if (!($records = $DB->get_records_select('questionnaire_response', $select, null, 'submitted', $fields))) {
                $records = array();
            }
        } else if ($userid) {    // Download CSV for one user's own responses'.
                $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                          FROM {questionnaire_response} R
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               R.username='$userid'
                         ORDER BY R.id";
            if (!($records = $DB->get_records_sql($sql))) {
                $records = array();
            }

        } else { // Download CSV for all participants (or groups if enabled).
            $castsql = $DB->sql_cast_char2int('R.username');
            if ($currentgroupid == 0) { // All participants.
                $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                          FROM {questionnaire_response} R
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y'
                         ORDER BY R.id";
            } else {                 // Members of a specific group.
                $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                          FROM {questionnaire_response} R,
                                {groups_members} GM
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               GM.groupid=".$currentgroupid." AND
                               ".$castsql."=GM.userid
                         ORDER BY R.id";
            }
            if (!($records = $DB->get_records_sql($sql))) {
                $records = array();
            }
        }
        $isanonymous = $this->respondenttype == 'anonymous';
        $formatoptions = new Object();
        $formatoptions->filter = false;  // To prevent any filtering in CSV output.
        foreach ($records as $record) {
            // Get the response.
            $response = $this->response_select_name($record->id, $choicecodes, $choicetext);
            $qid = $record->id;
            // For better compabitility & readability with Excel.
            $submitted = date(get_string('strfdateformatcsv', 'questionnaire'), $record->submitted);
            $institution = '';
            $department = '';
            $username  = $record->username;
            if ($user = $DB->get_record('user', array('id' => $username))) {
                $institution = $user->institution;
                $department = $user->department;
            }

            // Moodle:
            //  Get the course name that this questionnaire belongs to.
            if ($survey->realm != 'public') {
                $courseid = $this->course->id;
                $coursename = $this->course->fullname;
            } else {
                // For a public questionnaire, look for the course that used it.
                $sql = 'SELECT q.id, q.course, c.fullname '.
                       'FROM {questionnaire} q, {questionnaire_attempts} qa, {course} c '.
                       'WHERE qa.rid = ? AND q.id = qa.qid AND c.id = q.course';
                if ($record = $DB->get_record_sql($sql, array($qid))) {
                    $courseid = $record->course;
                    $coursename = $record->fullname;
                } else {
                    $courseid = $this->course->id;
                    $coursename = $this->course->fullname;
                }
            }
            // Moodle:
            //  If the username is numeric, try it as a Moodle user id.
            if (is_numeric($username)) {
                if ($user = $DB->get_record('user', array('id' => $username))) {
                    $uid = $username;
                    $fullname = fullname($user);
                    $username = $user->username;
                }
            }

            // Moodle:
            //  Determine if the user is a member of a group in this course or not.
            $groupname = '';
            if ($this->cm->groupmode > 0) {
                if ($currentgroupid > 0) {
                    $groupname = groups_get_group_name($currentgroupid);
                } else {
                    if ($uid) {
                        if ($groups = groups_get_all_groups($courseid, $uid)) {
                            foreach ($groups as $group) {
                                $groupname .= $group->name.', ';
                            }
                            $groupname = substr($groupname, 0, strlen($groupname) - 2);
                        } else {
                            $groupname = ' ('.get_string('groupnonmembers').')';
                        }
                    }
                }
            }
            if ($isanonymous) {
                $fullname = get_string('anonymous', 'questionnaire');
                $username = '';
                $uid = '';
            }
            $arr = array();
            array_push($arr, $qid);
            array_push($arr, $submitted);
            array_push($arr, $institution);
            array_push($arr, $department);
            array_push($arr, $coursename);
            array_push($arr, $groupname);
            array_push($arr, $uid);
            array_push($arr, $fullname);
            array_push($arr, $username);

            // Merge it.
            for ($i = $nbinfocols; $i < $numcols; $i++) {
                $qpos = key($columns[$i]);
                $qname = current($columns[$i]);
                if (isset($response[$qpos][$qname]) && $response[$qpos][$qname] != '') {
                    $thisresponse = $response[$qpos][$qname];
                } else {
                    $thisresponse = '';
                }

                switch ($types[$i]) {
                    case 1:  // String
                             // Excel seems to allow "\n" inside a quoted string, but
                             // "\r\n" is used as a record separator and so "\r" may
                             // not occur within a cell. So if one would like to preserve
                             // new-lines in a response, remove the "\n" from the
                             // regex below.

                            // Email format text is plain text for being displayed in Excel, etc.
                            // But it must be stripped of carriage returns.
                        if ($thisresponse) {
                            $thisresponse = format_text($thisresponse, FORMAT_HTML, $formatoptions);
                            $thisresponse = preg_replace("/[\r\n\t]/", ' ', $thisresponse);
                            $thisresponse = preg_replace('/"/', '""', $thisresponse);
                        }
                         // Fall through.
                    case 0:  // Number.

                    break;
                }
                array_push($arr, $thisresponse);
            }
            array_push($output, $arr);
        }

        // Change table headers to incorporate actual question numbers.
        $numcol = 0;
        $numquestion = 0;
        $out = '';
        $nbrespcols = count($output[0]);
        $oldkey = 0;

        for ($i = $nbinfocols; $i < $nbrespcols; $i++) {
            $sep = '';
            $thisoutput = current($output[0][$i]);
            $thiskey = key($output[0][$i]);
            // Case of unnamed rate single possible answer (full stop char is used for support).
            if (strstr($thisoutput, '->.')) {
                $thisoutput = str_replace('->.', '', $thisoutput);
            }
            // If variable is not named no separator needed between Question number and potential sub-variables.
            if ($thisoutput == '' || strstr($thisoutput, '->.') || substr($thisoutput, 0, 2) == '->'
                    || substr($thisoutput, 0, 1) == '_') {
                $sep = '';
            } else {
                $sep = '_';
            }
            if ($thiskey > $oldkey) {
                $oldkey = $thiskey;
                $numquestion++;
            }
            // Abbreviated modality name in multiple or rate questions (COLORS->blue=the color of the sky...).
            $pos = strpos($thisoutput, '=');
            if ($pos) {
                $thisoutput = substr($thisoutput, 0, $pos);
            }
            $other = $sep.$stringother;
            $out = 'Q'.sprintf("%02d", $numquestion).$sep.$thisoutput;
            $output[0][$i] = $out;
        }
        return $output;
    }

    /* {{{ proto bool survey_export_csv(int survey_id, string filename)
        Exports the results of a survey to a CSV file.
        Returns true on success.
        */

    private function export_csv($filename) {
        $umask = umask(0077);
        $fh = fopen($filename, 'w');
        umask($umask);
        if (!$fh) {
            return 0;
        }

        $data = survey_generate_csv($rid = '', $userid = '', $currentgroupid = '');

        foreach ($data as $row) {
            fputs($fh, join(', ', $row) . "\n");
        }

        fflush($fh);
        fclose($fh);

        return 1;
    }


    /**
     * Function to move a question to a new position.
     * Adapted from feedback plugin.
     *
     * @param int $moveqid The id of the question to be moved.
     * @param int $movetopos The position to move question to.
     *
     */

    public function move_question($moveqid, $movetopos) {
        global $DB;

        $questions = $this->questions;
        $movequestion = $this->questions[$moveqid];

        if (is_array($questions)) {
            $index = 1;
            foreach ($questions as $question) {
                if ($index == $movetopos) {
                    $index++;
                }
                if ($question->id == $movequestion->id) {
                    $movequestion->position = $movetopos;
                    $DB->update_record("questionnaire_question", $movequestion);
                    continue;
                }
                $question->position = $index;
                $DB->update_record("questionnaire_question", $question);
                $index++;
            }
            return true;
        }
        return false;
    }

    public function response_analysis ($rid, $resps, $compare, $isgroupmember, $allresponses, $currentgroupid) {
        global $DB, $CFG, $OUTPUT, $SESSION, $USER;
        $action = optional_param('action', 'vall', PARAM_ALPHA);

        require_once($CFG->libdir.'/tablelib.php');
        require_once($CFG->dirroot.'/mod/questionnaire/drawchart.php');
        if ($resp = $DB->get_record('questionnaire_response', array('id' => $rid)) ) {
            $userid = $resp->username;
            if ($user = $DB->get_record('user', array('id' => $userid))) {
                $ruser = fullname($user);
            }
        }
        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        $groupmode = groups_get_activity_groupmode($this->cm, $this->course);
        $groupname = get_string('allparticipants');
        if ($groupmode > 0) {
            if ($currentgroupid > 0) {
                $groupname = groups_get_group_name($currentgroupid);
            } else {
                $groupname = get_string('allparticipants');
            }
        }
        if ($this->survey->feedbackscores) {
            $table = new html_table();
            $table->size = array(null, null);
            $table->align = array('left', 'right', 'right');
            $table->head = array();
            $table->wrap = array();
            if ($compare) {
                $table->head = array(get_string('feedbacksection', 'questionnaire'), $ruser, $groupname);
            } else {
                $table->head = array(get_string('feedbacksection', 'questionnaire'), $groupname);
            }
        }

        $feedbacksections = $this->survey->feedbacksections;
        $feedbackscores = $this->survey->feedbackscores;
        $sid = $this->survey->id;
        $questions = $this->questions;

        // Find if there are any feedbacks in this questionnaire.
        $sql = "SELECT * FROM {questionnaire_fb_sections} WHERE survey_id = $sid AND section IS NOT NULL";
        if (!$fbsections = $DB->get_records_sql($sql)) {
            return null;
        }

        $fbsectionsnb = array_keys($fbsections);
        // Calculate max score per question in questionnaire.
        $qmax = array();
        $totalscore = 0;
        $maxtotalscore = 0;
        foreach ($questions as $question) {
            $qid = $question->id;
            $qtype = $question->type_id;
            $required = $question->required;
            if (($qtype == QUESRADIO || $qtype == QUESDROP || $qtype == QUESRATE) and $required == 'y') {
                if (!isset($qmax[$qid])) {
                    $qmax[$qid] = 0;
                }
                $nbchoices = 1;
                if ($qtype == QUESRATE) {
                    $nbchoices = 0;
                }
                foreach ($question->choices as $choice) {
                    // Testing NULL and 'NULL' because I changed the automatic null value, must be fixed later... TODO.
                    if (isset($choice->value) && $choice->value != null && $choice->value != 'NULL') {
                        if ($choice->value > $qmax[$qid]) {
                            $qmax[$qid] = $choice->value;
                        }
                    } else {
                        $nbchoices ++;
                    }
                }
                $qmax[$qid] = $qmax[$qid] * $nbchoices;
                $maxtotalscore += $qmax[$qid];
            }
            if ($qtype == QUESYESNO and $required == 'y') {
                $qmax[$qid] = 1;
                $maxtotalscore += 1;
            }
        }
        // Just in case no values have been entered in the various questions possible answers field.
        if ($maxtotalscore === 0) {
            return;
        }
        $feedbackmessages = array();

        // Get individual scores for each question in this responses set.
        $qscore = array();
        $allqscore = array();

        // Get all response ids for all respondents.
        $castsql = $DB->sql_cast_char2int('R.username');

        $rids = array();
        foreach ($resps as $key => $resp) {
            $rids[] = $key;
        }
        $nbparticipants = count($rids);

        if (!$allresponses && $groupmode != 0) {
            $nbparticipants = max(1, $nbparticipants - !$isgroupmember);
        }
        foreach ($rids as $rrid) {
            // Get responses for bool (Yes/No).
            $sql = 'SELECT q.id, q.type_id as q_type, a.choice_id as cid '.
                            'FROM {questionnaire_response_bool} a, {questionnaire_question} q '.
                            'WHERE a.response_id = ? AND a.question_id=q.id ';
            if ($responses = $DB->get_records_sql($sql, array($rrid))) {
                foreach ($responses as $qid => $response) {
                    $responsescore = ($response->cid == 'y' ? 1 : 0);
                    // Individual score.
                    // If this is current user's response OR if current user is viewing another group's results.
                    if ($rrid == $rid || $allresponses) {
                        if (!isset($qscore[$qid])) {
                            $qscore[$qid] = 0;
                        }
                        $qscore[$qid] = $responsescore;
                    }
                    // Course score.
                    if (!isset($allqscore[$qid])) {
                        $allqscore[$qid] = 0;
                    }
                    // Only add current score if conditions below are met.
                    if ($groupmode == 0 || $isgroupmember || (!$isgroupmember && $rrid != $rid) || $allresponses) {
                        $allqscore[$qid] += $responsescore;
                    }
                }
            }

            // Get responses for single (Radio or Dropbox).
            $sql = 'SELECT q.id, q.type_id as q_type, c.content as ccontent,c.id as cid, c.value as score  '.
                            'FROM {questionnaire_resp_single} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
                            'WHERE a.response_id = ? AND a.question_id=q.id AND a.choice_id=c.id ';
            if ($responses = $DB->get_records_sql($sql, array($rrid))) {
                foreach ($responses as $qid => $response) {
                    // Individual score.
                    // If this is current user's response OR if current user is viewing another group's results.
                    if ($rrid == $rid || $allresponses) {
                        if (!isset($qscore[$qid])) {
                            $qscore[$qid] = 0;
                        }
                        $qscore[$qid] = $response->score;
                    }
                    // Course score.
                    if (!isset($allqscore[$qid])) {
                        $allqscore[$qid] = 0;
                    }
                    // Only add current score if conditions below are met.
                    if ($groupmode == 0 || $isgroupmember || (!$isgroupmember && $rrid != $rid) || $allresponses) {
                        $allqscore[$qid] += $response->score;
                    }
                }
            }

            // Get responses for response_rank (Rate).
            $sql = 'SELECT a.id as aid, q.id AS qid, c.id AS cid, a.rank as arank '.
                            'FROM {questionnaire_response_rank} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
                            'WHERE a.response_id= ? AND a.question_id=q.id AND a.choice_id=c.id '.
                            'ORDER BY aid, a.question_id,c.id';
            if ($responses = $DB->get_records_sql($sql, array($rrid))) {
                // We need to store the number of sub-questions for each rate questions.
                $rank = array();
                $firstcid = array();
                foreach ($responses as $response) {
                    $qid = $response->qid;
                    $rank = $response->arank;
                    if (!isset($qscore[$qid])) {
                        $qscore[$qid] = 0;
                        $allqscore[$qid] = 0;
                    }
                    $firstcid[$qid] = $DB->get_record('questionnaire_quest_choice',
                                    array('question_id' => $qid), 'id', IGNORE_MULTIPLE);
                    $firstcidid = $firstcid[$qid]->id;
                    $cidvalue = $firstcidid + $rank;
                    $sql = "SELECT * FROM {questionnaire_quest_choice} WHERE id = $cidvalue";

                    if ($value = $DB->get_record_sql($sql)) {
                        // Individual score.
                        // If this is current user's response OR if current user is viewing another group's results.
                        if ($rrid == $rid || $allresponses) {
                            $qscore[$qid] += $value->value;
                        }
                        // Only add current score if conditions below are met.
                        if ($groupmode == 0 || $isgroupmember || (!$isgroupmember && $rrid != $rid) || $allresponses) {
                            $allqscore[$qid] += $value->value;
                        }
                    }
                }
            }
        }
        $totalscore = array_sum($qscore);
        $scorepercent = round($totalscore / $maxtotalscore * 100);
        $oppositescorepercent = 100 - $scorepercent;
        $alltotalscore = array_sum($allqscore);
        $allscorepercent = round($alltotalscore / $nbparticipants / $maxtotalscore * 100);

        // No need to go further if feedback is global, i.e. only relying on total score.
        if ($feedbacksections == 1) {
            $sectionid = $fbsectionsnb[0];
            $sectionlabel = $fbsections[$sectionid]->sectionlabel;

            $sectionheading = $fbsections[$sectionid]->sectionheading;
            $feedbacks = $DB->get_records('questionnaire_feedback', array('section_id' => $sectionid));
            $labels = array();
            foreach ($feedbacks as $feedback) {
                if ($feedback->feedbacklabel != '') {
                    $labels[] = $feedback->feedbacklabel;
                }
            }
            $feedback = $DB->get_record_select('questionnaire_feedback',
                            'section_id = ? AND minscore <= ? AND ? < maxscore', array($sectionid, $scorepercent, $scorepercent));

            // To eliminate all potential % chars in heading text (might interfere with the sprintf function).
            $sectionheading = str_replace('%', '', $sectionheading);
            // Replace section heading placeholders with their actual value (if any).
            $original = array('$scorepercent', '$oppositescorepercent');
            $result = array('%s%%', '%s%%');
            $sectionheading = str_replace($original, $result, $sectionheading);
            $sectionheading = sprintf($sectionheading , $scorepercent, $oppositescorepercent);
            $sectionheading = file_rewrite_pluginfile_urls($sectionheading, 'pluginfile.php',
                            $this->context->id, 'mod_questionnaire', 'sectionheading', $sectionid);
            $feedbackmessages[] = $OUTPUT->box_start();
            $feedbackmessages[] = format_text($sectionheading, FORMAT_HTML);
            $feedbackmessages[] = $OUTPUT->box_end();

            if (!empty($feedback->feedbacktext)) {
                // Clean the text, ready for display.
                $formatoptions = new stdClass();
                $formatoptions->noclean = true;
                $feedbacktext = file_rewrite_pluginfile_urls($feedback->feedbacktext, 'pluginfile.php',
                                $this->context->id, 'mod_questionnaire', 'feedback', $feedback->id);
                $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);
                $feedbackmessages[] = $OUTPUT->box_start();
                $feedbackmessages[] = $feedbacktext;
                $feedbackmessages[] = $OUTPUT->box_end();
            }
            $score = array($scorepercent, 100 - $scorepercent);
            $allscore = null;
            if ($compare  || $allresponses) {
                $allscore = array($allscorepercent, 100 - $allscorepercent);
            }
            if ($CFG->questionnaire_usergraph && $this->survey->chart_type) {
                draw_chart ($feedbacktype = 'global', $this->survey->chart_type, $labels,
                                    $score, $allscore, $sectionlabel, $groupname, $allresponses);
            }
            // Display class or group score. Pending chart library decision to display?
            // Find out if this feedback sectionlabel has a pipe separator.
            $lb = explode("|", $sectionlabel);
            $oppositescore = '';
            $oppositeallscore = '';
            if (count($lb) > 1) {
                $sectionlabel = $lb[0].' | '.$lb[1];
                $oppositescore = ' | '.$score[1].'%';
                $oppositeallscore = ' | '.$allscore[1].'%';
            }
            if ($this->survey->feedbackscores) {
                if ($compare) {
                    $table->data[] = array($sectionlabel, $score[0].'%'.$oppositescore, $allscore[0].'%'.$oppositeallscore);
                } else {
                    $table->data[] = array($sectionlabel, $allscore[0].'%'.$oppositeallscore);
                }

                echo html_writer::table($table);
            }

            return $feedbackmessages;
        }

        // Now process scores for more than one section.

        // Initialize scores and maxscores to 0.
        $score = array(); $allscore = array(); $maxscore = array(); $scorepercent = array();
        $allscorepercent = array(); $oppositescorepercent = array(); $alloppositescorepercent = array();
        $chartlabels = array(); $chartscore = array();
        for ($i = 1; $i <= $feedbacksections; $i++) {
            $score[$i] = 0; $allscore[$i] = 0; $maxscore[$i] = 0; $scorepercent[$i] = 0;
        }

        for ($section = 1; $section <= $feedbacksections; $section++) {
            foreach ($fbsections as $key => $fbsection) {
                if ($fbsection->section == $section) {
                    $feedbacksectionid = $key;
                    $scorecalculation = unserialize($fbsection->scorecalculation);
                    $sectionheading = $fbsection->sectionheading;
                    $imageid = $fbsection->id;
                    $chartlabels [$section] = $fbsection->sectionlabel;
                }
            }
            foreach ($scorecalculation as $qid => $key) {
                // Just in case a question pertaining to a section has been deleted or made not required
                // after being included in scorecalculation.
                if (isset($qscore[$qid])) {
                    $score[$section] += $qscore[$qid];
                    $maxscore[$section] += $qmax[$qid];
                    if ($compare  || $allresponses) {
                        $allscore[$section] += $allqscore[$qid];
                    }
                }
            }

            $scorepercent[$section] = round($score[$section] / $maxscore[$section] * 100);
            $oppositescorepercent[$section] = 100 - $scorepercent[$section];

            if (($compare || $allresponses) && $nbparticipants != 0) {
                $allscorepercent[$section] = round( ($allscore[$section] / $nbparticipants) / $maxscore[$section] * 100);
                $alloppositescorepercent[$section] = 100 - $allscorepercent[$section];
            }

            if (!$allresponses) {
                // To eliminate all potential % chars in heading text (might interfere with the sprintf function).
                $sectionheading = str_replace('%', '', $sectionheading);

                // Replace section heading placeholders with their actual value (if any).
                $original = array('$scorepercent', '$oppositescorepercent');
                $result = array("$scorepercent[$section]%", "$oppositescorepercent[$section]%");
                $sectionheading = str_replace($original, $result, $sectionheading);
                $formatoptions = new stdClass();
                $formatoptions->noclean = true;
                $sectionheading = file_rewrite_pluginfile_urls($sectionheading, 'pluginfile.php',
                                $this->context->id, 'mod_questionnaire', 'sectionheading', $imageid);
                $sectionheading = format_text($sectionheading, 1, $formatoptions);
                $feedbackmessages[] = $OUTPUT->box_start('reportQuestionTitle');
                $feedbackmessages[] = format_text($sectionheading, FORMAT_HTML);
                $feedback = $DB->get_record_select('questionnaire_feedback',
                                'section_id = ? AND minscore <= ? AND ? < maxscore',
                                array($feedbacksectionid, $scorepercent[$section], $scorepercent[$section]),
                                'id,feedbacktext,feedbacktextformat');
                $feedbackmessages[] = $OUTPUT->box_end();
                if (!empty($feedback->feedbacktext)) {
                    // Clean the text, ready for display.
                    $formatoptions = new stdClass();
                    $formatoptions->noclean = true;
                    $feedbacktext = file_rewrite_pluginfile_urls($feedback->feedbacktext, 'pluginfile.php',
                                    $this->context->id, 'mod_questionnaire', 'feedback', $feedback->id);
                    $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);
                    $feedbackmessages[] = $OUTPUT->box_start('feedbacktext');
                    $feedbackmessages[] = $feedbacktext;
                    $feedbackmessages[] = $OUTPUT->box_end();
                }
            }
        }

        // Display class or group score.
        switch ($action) {
            case 'vallasort':
                asort($allscore);
                break;
            case 'vallarsort':
                arsort($allscore);
                break;
            default:
        }

        foreach ($allscore as $key => $sc) {
            $lb = explode("|", $chartlabels[$key]);
            $oppositescore = '';
            $oppositeallscore = '';
            if (count($lb) > 1) {
                $sectionlabel = $lb[0].' | '.$lb[1];
                $oppositescore = ' | '.$oppositescorepercent[$key].'%';
                $oppositeallscore = ' | '.$alloppositescorepercent[$key].'%';
            } else {
                $sectionlabel = $chartlabels[$key];
            }
            if ($compare) {
                $table->data[] = array($sectionlabel, $scorepercent[$key].'%'.$oppositescore,
                                $allscorepercent[$key].'%'.$oppositeallscore);
            } else {
                $table->data[] = array($sectionlabel, $sc.'%'.$oppositeallscore);
            }
        }
        if (isset($CFG->questionnaire_usergraph) && $CFG->questionnaire_usergraph && $this->survey->chart_type) {
            draw_chart($feedbacktype = 'sections', $this->survey->chart_type, array_values($chartlabels),
                array_values($scorepercent), array_values($allscorepercent), $sectionlabel, $groupname, $allresponses);
        }
        if ($this->survey->feedbackscores) {
            echo html_writer::table($table);
        }

        return $feedbackmessages;
    }

}
