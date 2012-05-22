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
 * This library replaces the phpESP application with Moodle specific code. It will eventually
 * replace all of the phpESP application, removing the dependency on that.
 */

/**
 * Updates the contents of the survey with the provided data. If no data is provided,
 * it checks for posted data.
 *
 * @param int $survey_id The id of the survey to update.
 * @param string $old_tab The function that was being executed.
 * @param object $sdata The data to update the survey with.
 *
 * @return string|boolean The function to go to, or false on error.
 *
 */

require_once('questiontypes/questiontypes.class.php');

class questionnaire {

/// Class Properties
    /**
     * The survey record.
     * @var object $survey
     */
     var $survey;

/// Class Methods

    /**
     * The class constructor
     *
     */
    function questionnaire($id = 0, $questionnaire = null, &$course, &$cm, $addquestions = true) {
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
        /// When we are creating a brand new questionnaire, we will not yet have a context.
        if (!empty($cm) && !empty($this->id)) {
            $this->context = get_context_instance(CONTEXT_MODULE, $cm->id);
        } else {
            $this->context = null;
        }

        if ($addquestions && !empty($this->sid)) {
            $this->add_questions($this->sid);
        }

        $this->usehtmleditor = can_use_html_editor();

    /// Load the capabilities for this user and questionnaire, if not creating a new one.
        if (!empty($this->cm->id)) {
            $this->capabilities = questionnaire_load_capabilities($this->cm->id);
        }
    }

    /**
     * Fake constructor to keep PHP5 happy
     *
     */
    function __construct($id = 0, $questionnaire = null, &$course, &$cm, $addquestions = true) {
        $this->questionnaire($id, $questionnaire, $course, $cm, $addquestions);
    }

    /**
     * Adding a survey record to the object.
     *
     */
    function add_survey($sid = 0, $survey = null) {
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
    function add_questions($sid = false, $section = false) {
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
                if ($record->type_id != 99) {
                    $this->questionsbysec[$sec][$record->id] = &$this->questions[$record->id];
                    $isbreak = false;
                } else {
                    // sanity check: no section break allowed as first position, no 2 consecutive section breaks
                    if ($record->position != 1 && $isbreak == false) {
                        $sec++;
                        $isbreak = true;
                    }
                }
            }
        }
    }

    function view() {
        global $CFG, $USER, $PAGE, $OUTPUT;

        $PAGE->set_title(format_string($this->name));
        $PAGE->set_heading(format_string($this->course->fullname));
        $PAGE->set_button(update_module_button($this->cm->id, $this->course->id, $this->strquestionnaire));
        echo $OUTPUT->header();

        /// print the tabs
        $questionnaire = $this;
        include('tabs.php');

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

    /// Print the main part of the page

        if (!$this->is_active()) {
            echo '<div class="message">'
            .get_string('notavail', 'questionnaire')
            .'</div>';
        }
        else if (!$this->is_open()) {
            echo '<div class="message">'
            .get_string('notopen', 'questionnaire', userdate($this->opendate))
            .'</div>';
        }
        else if ($this->is_closed()) {
            echo '<div class="message">'
            .get_string('closed', 'questionnaire', userdate($this->closedate))
            .'</div>';
        }
        else if (!$this->user_is_eligible($USER->id)) {
            echo '<div class="message">'
            .get_string('noteligible', 'questionnaire')
            .'</div>';
        }
        else if ($this->user_can_take($USER->id)) {
            $sid=$this->sid;
            $quser = $USER->id;

            if ($this->survey->realm == 'template') {
                print_string('templatenotviewable', 'questionnaire');
                echo $OUTPUT->footer($this->course);
                exit();
            }

            if ((!empty($this->questions)) && $this->capabilities->printblank) {
                // open print friendly as popup window
	            $image_url = $CFG->wwwroot.'/mod/questionnaire/images/';
	            $linkname = '<img src="'.$image_url.'print.gif" alt="Printer-friendly version" />';
                $title = get_string('printblanktooltip','questionnaire');
                $url = '/mod/questionnaire/print.php?qid='.$this->id.'&amp;rid=0&amp;'.'courseid='.$this->course->id.'&amp;sec=1';
                $options = array('menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
	                    'height' => 600, 'width' => 800, 'title'=>$title);
	            $name = 'popup';
	            $link = new moodle_url($url);
	            $action = new popup_action('click', $link, $name, $options);
                $class = "floatprinticon";
	            echo $OUTPUT->action_link($link, $linkname, $action, array('class'=>$class, 'title'=>$title));
            }
            $msg = $this->print_survey($USER->id, $quser);
    ///     If Survey was submitted with all required fields completed ($msg is empty),
    ///     then record the submittal.
            $viewform = data_submitted($CFG->wwwroot."/mod/questionnaire/view.php");
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

                /// If it was a previous save, rid is in the form...
                if (!empty($viewform->rid) && is_numeric($viewform->rid)) {
                    $rid = $viewform->rid;

                /// Otherwise its in this object.
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

                add_to_log($this->course->id, "questionnaire", "submit", "view.php?id={$this->cm->id}", "{$this->name}", $this->cm->id, $USER->id);

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
            echo ('<div class="message">'.get_string("alreadyfilled", "questionnaire", $msgstring).'</div>');
        }

    /// Finish the page
        echo $OUTPUT->footer($this->course);
    }

   /**
    * Function to view an entire responses data.
    *
    */
    function view_response($rid, $blankquestionnaire=false) {
        global $OUTPUT;

        echo $OUTPUT->box_start();
        $this->print_survey_start('', 1, 1, 0, $rid, $blankquestionnaire);

        $data = new Object();
        $i = 1;
        if (!$blankquestionnaire) {
            $this->response_import_all($rid, $data);
        }
        foreach ($this->questions as $question) {
            if ($question->type_id < QUESPAGEBREAK) {
                $question->response_display($data, $i++);
            }
        }

        $this->print_survey_end(1, 1);
        echo $OUTPUT->box_end();
    }

   /**
    * Function to view an entire responses data.
    *
    */
    function view_all_responses($resps) {
        global $QTYPENAMES, $OUTPUT;
        echo $OUTPUT->box_start();
        $this->print_survey_start('', 1, 1, 0);

        foreach ($resps as $resp) {
            $data[$resp->id] = new Object();
            $this->response_import_all($resp->id, $data[$resp->id]);
        }

        $i = 1;
        echo '<div class="mainTable">';
        foreach ($this->questions as $question) {
            if ($question->type_id < QUESPAGEBREAK) {
                $method = $QTYPENAMES[$question->type_id].'_response_display';
                if (method_exists($question, $method)) {
                    $question->questionstart_survey_display($i);
                    $numItems = count($data);
                    $inneri = 0;
                    foreach ($data as $respid => $respdata) {
                        echo '<div class="respdate">'.userdate($resps[$respid]->submitted).'</div>';
                        $question->$method($respdata);
                        $inneri++;
                        if ($inneri < $numItems) {
                            echo '<hr />';
                        }
                    }
                    $question->questionend_survey_display($i);
                } else {
                    print_error('displaymethod', 'questionnaire');
                }
            $i++;
            }
        }
        echo '</div>';

        $this->print_survey_end(1, 1);
        echo $OUTPUT->box_end();
    }

/// Access Methods
    function is_active() {
        return (!empty($this->survey));
    }

    function is_open() {
        return ($this->opendate > 0) ? ($this->opendate < time()) : true;
    }

    function is_closed() {
        return ($this->closedate > 0) ? ($this->closedate < time()) : false;
    }

    function user_can_take($userid) {

        if (!$this->is_active() || !$this->user_is_eligible($userid)) {
            return false;
        }
        else if ($this->qtype == QUESTIONNAIREUNLIMITED) {
            return true;
        }
        else if ($userid > 0){
            return $this->user_time_for_new_attempt($userid);
        }
        else {
            return false;
        }
    }

    function user_is_eligible($userid) {
        return ($this->capabilities->view && $this->capabilities->submit);
    }

    function user_time_for_new_attempt($userid) {
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

    function is_survey_owner() {
        return (!empty($this->survey->owner) && ($this->course->id == $this->survey->owner));
    }

    function can_view_response($rid) {
        global $USER, $DB;

        if (!empty($rid)) {
            $response = $DB->get_record('questionnaire_response', array('id' => $rid));

            /// If the response was not found, can't view it.
            if (empty($response)) {
                return false;
            }

            /// If the response belongs to a different survey than this one, can't view it.
            if ($response->survey_id != $this->survey->id) {
                return false;
            }

            /// If you can view all responses always, then you can view it.
            if ($this->capabilities->readallresponseanytime) {
                return true;
            }

            /// If you are allowed to view this response for another user.
            if ($this->capabilities->readallresponses &&
                ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $this->is_closed()) ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED  && !$this->user_can_take($USER->id)))) {
                return true;
             }

             /// If you can read your own response
            if (($response->username == $USER->id) && $this->capabilities->readownresponses && ($this->count_submissions($USER->id) > 0)) {
                return true;
            }

        } else {
            /// If you can view all responses always, then you can view it.
            if ($this->capabilities->readallresponseanytime) {
                return true;
            }

            /// If you are allowed to view this response for another user.
            if ($this->capabilities->readallresponses &&
                ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_ALWAYS ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENCLOSED && $this->is_closed()) ||
                 ($this->resp_view == QUESTIONNAIRE_STUDENTVIEWRESPONSES_WHENANSWERED  && !$this->user_can_take($USER->id)))) {
                return true;
             }

             /// If you can read your own response
            if ($this->capabilities->readownresponses && ($this->count_submissions($USER->id) > 0)) {
             return true;
            }

         }
    }

    function count_submissions($userid=false) {
        global $DB;

        if (!$userid) {
            // provide for groups setting
            return $DB->count_records('questionnaire_response', array('survey_id' => $this->sid, 'complete' => 'y'));
        } else {
            return $DB->count_records('questionnaire_response', array('survey_id' => $this->sid, 'username' => $userid,
                                      'complete' => 'y'));
        }
    }

    function has_required($section = 0) {
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
/// Display Methods

    function print_survey($userid=false, $quser) {
        global $CFG;

        $formdata = new stdClass();
        if (data_submitted() && confirm_sesskey()) {
            $formdata = data_submitted();
        }
        $formdata->rid = $this->get_response($quser);
        if (!empty($formdata->rid) && (empty($formdata->sec) || intval($formdata->sec) < 1)) {
            $formdata->sec = $this->response_select_max_sec($formdata->rid);
        }
        if (empty($formdata->sec)) {
            $formdata->sec = 1;
        } else {
            $formdata->sec = (intval($formdata->sec) > 0) ? intval($formdata->sec) : 1;
        }

        $num_sections = isset($this->questionsbysec) ? count($this->questionsbysec) : 0;    /// indexed by section.
        $msg = '';
        $action = $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$this->cm->id;

/// TODO - Need to rework this. Too much crossover with ->view method.
        if(!empty($formdata->submit)) {
            $msg = $this->response_check_format($formdata->sec, $formdata);
            if(empty($msg)) {
                return;
            }
        }

        if(!empty($formdata->resume) && ($this->resume)) {
            $this->response_delete($formdata->rid, $formdata->sec);
            $formdata->rid = $this->response_insert($this->survey->id, $formdata->sec, $formdata->rid, $quser);
            $this->response_goto_saved($action);
            return;
        }
 // JR save each section 's $formdata somewhere in case user returns to that page when navigating the questionnaire...
        if(!empty($formdata->next)) {
            $this->response_delete($formdata->rid, $formdata->sec);
            $formdata->rid = $this->response_insert($this->survey->id, $formdata->sec, $formdata->rid, $quser);
            $msg = $this->response_check_format($formdata->sec, $formdata);
            if ( $msg ) {
                $formdata->next = '';
            } else {
                $formdata->sec++;
            }
        }
        if (!empty($formdata->prev) && ($this->navigate)) {
            $this->response_delete($formdata->rid, $formdata->sec);
            $formdata->rid = $this->response_insert($this->survey->id, $formdata->sec, $formdata->rid, $quser);
            $msg = $this->response_check_format($formdata->sec, $formdata);
            if ( $msg ) {
                $formdata->prev = '';
            } else {
                $formdata->sec--;
            }
        }

        if (!empty($formdata->rid)) {
            $this->response_import_sec($formdata->rid, $formdata->sec, $formdata);
        }
        echo '
    <script type="text/javascript">
    <!-- // Begin
    // when respondent enters text in !other field, corresponding radio button OR check box is automatically checked
    function other_check(name) {
      other = name.split("_");
      var f = document.getElementById("phpesp_response");
      for (var i=0; i<=f.elements.length; i++) {
        if (f.elements[i].value == "other_"+other[1]) {
          f.elements[i].checked=true;
          break;
        }
      }
    }

    // function added by JR to automatically empty an !other text input field if another Radio button is clicked
    function other_check_empty(name, value) {
      var f = document.getElementById("phpesp_response");
      for (var i=0; i<f.elements.length; i++) {
        if ((f.elements[i].name == name) && f.elements[i].value.substr(0,6) == "other_") {
            f.elements[i].checked=true;
            var otherid = f.elements[i].name + "_" + f.elements[i].value.substring(6);
            var other = document.getElementsByName (otherid);
            if (value.substr(0,6) != "other_") {
               other[0].value = "";
            } else {
                other[0].focus();
            }
            var actualbuttons = document.getElementsByName (name);
              for (var i=0; i<=actualbuttons.length; i++) {
                if (actualbuttons[i].value == value) {
                    actualbuttons[i].checked=true;
                    break;
                }
            }
        break;
        }
      }
    }

    // function added by JR in a Rate question type of sub-type Order to automatically uncheck a Radio button
    // when another radio button in the same column is clicked
    function other_rate_uncheck(name, value) {
        col_name = name.substr(0, name.indexOf("_"));
        var inputbuttons = document.getElementsByTagName("input");
        for (var i=0; i<=inputbuttons.length - 1; i++) {
            button = inputbuttons[i];
            if (button.type == "radio" && button.name != name && button.value == value && button.name.substr(0, name.indexOf("_")) == col_name) {
                button.checked = false;
            }
        }
    }

    // function added by JR to empty an !other text input when corresponding Check Box is clicked (supposedly to empty it)
    function checkbox_empty(name) {
        var actualbuttons = document.getElementsByName (name);
        for (var i=0; i<=actualbuttons.length; i++) {
            if (actualbuttons[i].value.substr(0,6) == "other_") {
                name = name.substring(0,name.length-2) + actualbuttons[i].value.substring(5);
                var othertext = document.getElementsByName (name);
                if (othertext[0].value == "" && actualbuttons[i].checked == true) {
                    othertext[0].focus();
                } else {
                    othertext[0].value = "";
                }
                break;
            }
        }
    }
    // End -->
    </script>
            ';

        echo '<div class="generalbox">';

    ?>
    <form id="phpesp_response" method="post" action="<?php echo($action); ?>">
    <div>
    <input type="hidden" name="referer" value="<?php echo (!empty($formdata->referer) ? htmlspecialchars($formdata->referer) : ''); ?>" />
    <input type="hidden" name="a" value="<?php echo($this->id); ?>" />
    <input type="hidden" name="sid" value="<?php echo($this->survey->id); ?>" />
    <input type="hidden" name="rid" value="<?php echo (isset($formdata->rid) ? $formdata->rid : '0'); ?>" />
    <input type="hidden" name="sec" value="<?php echo($formdata->sec); ?>" />
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
    </div>
    <?php
        if (isset($this->questions) && $num_sections) { // sanity check
            $this->survey_render($formdata->sec, $msg, $formdata);
            echo '<div class="notice" style="padding: 0.5em 0 0.5em 0.2em;"><div class="buttons">';
            if (($this->navigate) && ($formdata->sec > 1)) {
                echo '<input type="submit" name="prev" value="'.get_string('previouspage', 'questionnaire').'" />';
            }
            if ($this->resume) {
                echo '<input type="submit" name="resume" value="'.get_string('save', 'questionnaire').'" />';
            }
        //  Add a 'hidden' variable for the mod's 'view.php', and use a language variable for the submit button.

            if($formdata->sec == $num_sections) {
                echo '
            <div><input type="hidden" name="submittype" value="Submit Survey" />
            <input type="submit" name="submit" value="'.get_string('submitsurvey', 'questionnaire').'" /></div>';
            } else {
                echo '<div><input type="submit" name="next" value="'.get_string('nextpage', 'questionnaire').'" /></div>';
            }
            echo '</div></div>'; //divs notice & buttons
            echo '</form>';

            echo '</div>'; //div class="generalbox"

            return $msg;
        } else {
            echo '<p>'.get_string('noneinuse','questionnaire').'</p>';
            echo '</form>';
            echo '</div>'; //div class="generalbox"
        }
    }

    function survey_render($section = 1, $message = '', &$formdata) {

        $this->usehtmleditor = null;

        if(empty($section)) {
            $section = 1;
        }

        $num_sections = isset($this->questionsbysec) ? count($this->questionsbysec) : 0;    /// indexed by section.
        if($section > $num_sections) {
            return(false);  // invalid section
        }

    // check to see if there are required questions
        $has_required = $this->has_required($section);

    // find out what question number we are on $i New fix for question numbering
        $i = 0;
        if ($section > 1) {
        	for($j = 2; $j<=$section; $j++) {
                foreach ($this->questionsbysec[$j-1] as $question) {
                    if ($question->type_id < 99) {
                        $i++;
                    }
                }
            }
        }

        $this->print_survey_start($message, $section, $num_sections, $has_required);
        foreach ($this->questionsbysec[$section] as $question) {
            if ($question->type === 'Essay Box') {
                $this->usehtmleditor = can_use_html_editor();
            }
            if ($question->type_id != QUESSECTIONTEXT) {
                $i++;
            }
            $question->survey_display($formdata, $i, $this->usehtmleditor);
            /// Bug MDL-7292 - Don't count section text as a question number.
            // process each question
        }
        // end of questions
        echo ('<div class="surveyPage">');
        $this->print_survey_end($section, $num_sections);
        echo '</div>';
        return;
    }

    function print_survey_start($message, $section, $num_sections, $has_required, $rid='', $blankquestionnaire=false) {
        global $CFG;
        global $DB;
        require_once($CFG->libdir.'/filelib.php');

        $userid = '';
        $resp = '';
        $groupname = '';
        $timesubmitted = '';
        //available group modes (0 = no groups; 1 = separate groups; 2 = visible groups)

        if ($rid) {
            $courseid = $this->course->id;
            if ($resp = $DB->get_record('questionnaire_response', array('id' => $rid)) ) {
                if ($this->respondenttype == 'fullname') {
                    $userid = $resp->username;
                    // display name of group(s) that student belongs to... if questionnaire is set to Groups separate or visible
                    if ($this->cm->groupmode > 0) {
                        if ($groups = groups_get_all_groups($courseid, $resp->username)) {
                            if (count($groups) == 1) {
                                $group = current($groups);
                                $groupname = ' ('.get_string('group').': '.$group->name.')';
                            } else {
                                $groupname = ' ('.get_string('groups').': ';
                                foreach ($groups as $group) {
                                    $groupname.= $group->name.', ';
                                }
                                $groupname = substr($groupname, 0, strlen($groupname) -2).')';
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
            // JR DEV comment following line out if you do NOT want time submitted displayed in Anonymous surveys
                if ($resp->submitted) {
                    $timesubmitted = '&nbsp;'.get_string('submitted', 'questionnaire').'&nbsp;'.userdate($resp->submitted);
                }
            }
        }
        if ($ruser) {
            echo (get_string('respondent', 'questionnaire').': <strong>'.$ruser.'</strong>');
            if ($this->survey->realm == 'public') {
                /// For a public questionnaire, look for the course that used it.
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
        if ($section == 1) {
            if ($this->survey->subtitle) {
                echo '<h4 class="surveySubtitle">'.(format_text($this->survey->subtitle, FORMAT_HTML)).'</h4>';
            }
            if ($this->survey->info) {
                $infotext = file_rewrite_pluginfile_urls($this->survey->info, 'pluginfile.php', $this->context->id, 'mod_questionnaire', 'info', $this->survey->id);
                echo '<div class="addInfo">'.format_text($infotext, FORMAT_HTML).'</div>';
            }
        }
        if($num_sections>1) {
            $a = '';
            $a->page = $section;
            $a->totpages = $num_sections;
            echo '<div class="surveyPage">&nbsp;'.get_string('pageof', 'questionnaire', $a).'</div>';
        }
        if ($message) {
            echo '<div class="message">'.$message.'</div>'; //JR
        }

    }

    function print_survey_end($section, $num_sections) {
        if($num_sections>1) {
            $a = '';
            $a->page = $section;
            $a->totpages = $num_sections;
            echo get_string('pageof', 'questionnaire', $a).'&nbsp;&nbsp;';
        }
    }

    function survey_print_render($message = '', $referer='', $courseid, $blankquestionnaire=false) {
        global $USER, $DB, $OUTPUT;

        $rid = optional_param('rid', 0, PARAM_INT);

        if (! $course = $DB->get_record("course", array("id" => $courseid))) {
            print_error('incorrectcourseid', 'questionnaire');
        }
        $this->course = $course;

        if ($this->resume && empty($rid)) {
            $rid = $this->get_response($USER->id, $rid);
        }

        if (!empty($rid)) {
        // If we're viewing a response, use this method.
            $this->view_response($rid, $blankquestionnaire);
            return;
        }

        if(empty($section)) {
            $section = 1;
        }

        $num_sections = isset($this->questionsbysec) ? count($this->questionsbysec) : 0;
        if($section > $num_sections)
            return(false);  // invalid section

        $has_required = $this->has_required();

    // find out what question number we are on $i
        $i = 1;
        for($j = 2; $j<=$section; $j++) {
            $i += count($this->questionsbysec[$j-1]);
        }

        echo $OUTPUT->box_start();
        $this->print_survey_start($message, 1, 1, $has_required);
        /// Print all sections:
        $formdata = new stdClass();
        if (data_submitted() && confirm_sesskey()) {
            $formdata = data_submitted();
        }
        foreach ($this->questionsbysec as $section) {
            foreach ($section as $question) {
                if ($question->type_id == QUESSECTIONTEXT) {
                    $i--;
                }
                $question->survey_display($formdata, $i++, $usehtmleditor=null);
            }
            if (!$blankquestionnaire) {
                echo (get_string('sectionbreak', 'questionnaire').'<br /><br />'); // print on preview questionaire page only
            }
        }
        // end of questions

        echo $OUTPUT->box_end();
        return;
    }

    function survey_update($sdata) {
        global $DB;

        $errstr = ''; //TODO: notused!

        // new survey
        if(empty($this->survey->id)) {
            // create a new survey in the database
            $fields = array('name','realm','title','subtitle','email','theme','thanks_page','thank_head','thank_body','info'); // theme field deprecated
            $record = new Object();
            $record->id = 0;
            $record->owner = $sdata->owner;
            foreach($fields as $f) {
                if(isset($sdata->$f)) {
                    $record->$f = $sdata->$f;
                }
            }

            $this->survey->id = $DB->insert_record('questionnaire_survey', $record);
            $this->add_survey($this->survey->id);

            if(!$this->survey->id) {
                $errstr = get_string('errnewname', 'questionnaire') .' [ :  ]'; //TODO: notused!
                return(false);
            }
        } else {
            if(empty($sdata->name) || empty($sdata->title)
                    || empty($sdata->realm)) {
                return(false);
            }

            $fields = array('name','realm','title','subtitle','email','theme','thanks_page','thank_head','thank_body','info');  // theme field deprecated

            $name = $DB->get_field('questionnaire_survey', 'name', array('id' => $this->survey->id));

            // trying to change survey name
            if(trim($name) != trim(stripslashes($sdata->name))) {  // $sdata will already have slashes added to it.
                $count = $DB->count_records('questionnaire_survey', array('name' => $sdata->name));
                if($count != 0) {
                    $errstr = get_string('errnewname', 'questionnaire');  //TODO: notused!
                    return(false);
                }
            }

            // UPDATE the row in the DB with current values
            $survey_record = new Object();
            $survey_record->id = $this->survey->id;
            foreach($fields as $f) {
                $survey_record->$f = trim($sdata->{$f});
            }

            $result = $DB->update_record('questionnaire_survey', $survey_record);
            if(!$result) {
                $errstr = get_string('warning', 'questionnaire').' [ :  ]';  //TODO: notused!
                return(false);
            }
        }

        return($this->survey->id);
    }

    /* Creates an editable copy of a survey. */
    function survey_copy($owner) {
        global $DB;

        // clear the sid, clear the creation date, change the name, and clear the status
        // Since we're copying a data record, addslashes.
        // 2.0 - don't need to do this now, since its handled by the $DB-> functions.
        $survey = clone($this->survey);

        unset($survey->id);
        $survey->owner = $owner;
        // Make sure that the survey name is not larger than the field size (CONTRIB-2999). Leave room for extra chars.
        $survey->name = substr($survey->name, 0, (64-10));
        $survey->name .= '_copy';
        $survey->status = 0;

        // check for 'name' conflict, and resolve
        $i=0;
        $name = $survey->name;
        while ($DB->count_records('questionnaire_survey', array('name' => $name)) > 0) {
            $name = $survey->name.(++$i);
        }
        if($i) {
            $survey->name .= $i;
        }

        // create new survey
        if (!($new_sid = $DB->insert_record('questionnaire_survey', $survey))) {
            return(false);
        }

        // make copies of all the questions
        $pos=1;
        foreach ($this->questions as $question) {
            // fix some fields first
            unset($question->id);
            $question->survey_id = $new_sid;
            $question->position = $pos++;
            $question->name = addslashes($question->name);
            $question->content = addslashes($question->content);

            // copy question to new survey
            if (!($new_qid = $DB->insert_record('questionnaire_question', $question))) {
                return(false);
            }

            foreach ($question->choices as $choice) {
                unset($choice->id);
                $choice->question_id = $new_qid;
                $choice->content = addslashes($choice->content);
                $choice->value = addslashes($choice->value);
                if (!$DB->insert_record('questionnaire_quest_choice', $choice)) {
                    return(false);
                }
            }
        }

        return($new_sid);
    }

    function type_has_choices() {
        global $DB;

        $has_choices = array();

        if ($records = $DB->get_records('questionnaire_question_type', array(), 'typeid', 'typeid,has_choices')) {
            foreach ($records as $record) {
                if($record->has_choices == 'y') {
                    $has_choices[$record->typeid]=1;
                } else {
                    $has_choices[$record->typeid]=0;
                }
            }
        } else {
            $has_choices = array();
        }

        return($has_choices);
    }

    function array_to_insql($array) {
        if (count($array))
            return("IN (".ereg_replace("([^,]+)","'\\1'",join(",",$array)).")");
        return 'IS NULL';
    }

    // ---- RESPONSE LIBRARY

    function response_check_format($section, &$formdata, $qnum='') {
        $missing = 0;
        $strmissing = ''; // missing questions
        $wrongformat = 0;
        $strwrongformat = ''; // wrongly formatted questions (Numeric, 5:Check Boxes, Date)
        $i = 1;
        for($j = 2; $j<=$section; $j++) {
        // ADDED A SIMPLE LOOP FOR MAKING SURE PAGE BREAKS (type 99) AND LABELS (type 100) ARE NOT ALLOWED
            foreach ($this->questionsbysec[$j-1] as $sectionrecord) {
            $tid = $sectionrecord->type_id;
                if ($tid < 99) {
                $i++;
                }
            }
        }
        $qnum = $i - 1;

        foreach ($this->questionsbysec[$section] as $record) {

            $qid = $record->id;
            $tid = $record->type_id;
            $lid = $record->length;
            $pid = $record->precise;
            if ($tid != 100) {
                $qnum++;
            }
            if ( ($record->required == 'y') && ($record->deleted == 'n') && ((isset($formdata->{'q'.$qid}) && $formdata->{'q'.$qid} == '') || (!isset($formdata->{'q'.$qid}))) && $tid != 8 && $tid != 100 ) {
                $missing++;
                $strmissing .= get_string('num', 'questionnaire').$qnum.'. ';
            }

            switch ($tid) {

            case 4: // Radio Buttons with !other field
                if (!isset($formdata->{'q'.$qid})) {
                    break;
                }
                $resp = $formdata->{'q'.$qid};
                $pos = strpos($resp, 'other_');

                            // "other" choice is checked but text box is empty
                if (is_int($pos) == true){
                    $othercontent = "q".$qid.substr($resp, 5);
                    if ( !$formdata->$othercontent ) {
                        $wrongformat++;
                        $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                        break;
                    }
                }

                if (is_int($pos) == true && $record->required == 'y') {
                    $resp = 'q'.$qid.''.substr($resp,5);
                    if (!$formdata->$resp) {
                        $missing++;
                        $strmissing .= get_string('num', 'questionnaire').$qnum.'. ';
                    }
                }
                break;

            case 5: // Check Boxes
                if (!isset($formdata->{'q'.$qid})) {
                    break;
                }
                $resps = $formdata->{'q'.$qid};
                $nbrespchoices = 0;
                foreach ($resps as $resp) {
                    $pos = strpos($resp, 'other_');

                    // "other" choice is checked but text box is empty
                    if (is_int($pos) == true){
                        $othercontent = "q".$qid.substr($resp, 5);
                        if ( !$formdata->$othercontent ) {
                            $wrongformat++;
                            $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                            break;
                        }
                    }

                    if (is_numeric($resp) || is_int($pos) == true) { //JR fixed bug CONTRIB-884
                        $nbrespchoices++;
                    }
                }
                $nbquestchoices = count($record->choices);
                $min = $lid;
                $max = $pid;
                if ($max == 0) {
                    $max = $nbquestchoices;
                }
                if ($min > $max) {
                    $min = $max; // sanity check
                }
                $min = min($nbquestchoices, $min);
                // number of ticked boxes is not within min and max set limits
                if ( $nbrespchoices && ($nbrespchoices < $min || $nbrespchoices > $max) ) {
                    $wrongformat++;
                    $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                    break;
                }
                break;

            case 6: // Drop
                $resp = $formdata->{'q'.$qid};
                if (!$resp && $record->required == 'y') {
                    $missing++;
                    $strmissing .= get_string('num', 'questionnaire').$qnum.'. ';
                }
                break;

            case 8: // Rate
                $num = 0;
                $nbchoices = count($record->choices);
                $na = get_string('notapplicable', 'questionnaire');
                foreach ($record->choices as $cid => $choice) {
                    // in case we have named degrees on the Likert scale, count them to substract from nbchoices
                    $nameddegrees = 0;
                    $content = $choice->content;
                    if (ereg("^[0-9]{1,3}=", $content,$ndd)) {
                        $nameddegrees++;
                    } else {
                        $str = 'q'."{$record->id}_$cid";
						if (isset($formdata->$str) && $formdata->$str == $na) {
							$formdata->$str = -1;
						}
                        for ($j = 0; $j < $record->length; $j++) {
                            $num += (isset($formdata->$str) && ($j == $formdata->$str));
                        }
                        $num += (($record->precise) && isset($formdata->$str) && ($formdata->$str == -1));
                    }
                    $nbchoices -= $nameddegrees;
                }
                if ( $num == 0 && $record->required == 'y') {
                    $missing++;
                    $strmissing .= get_string('num', 'questionnaire').$qnum.'. ';
                    break;
                }
                // if nodupes and nb choice restricted, nbchoices may be > actual choices, so limit it to $record->length
                $isrestricted = ($record->length < count($record->choices)) && $record->precise == 2;
                if ($isrestricted) {
                    $nbchoices = min ($nbchoices, $record->length);
                }
                if ( $num != $nbchoices && $num!=0 ) {
                    $wrongformat++;
                    $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                }
                break;

            case 9: // Date
                $checkdateresult = '';
                if ($formdata->{'q'.$qid} != '') {
                    $checkdateresult = check_date($formdata->{'q'.$qid});
                }
                if (substr($checkdateresult,0,5) == 'wrong') {
                    $wrongformat++;
                    $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                }
                break;

            case 10: // Numeric
                if ( ($formdata->{'q'.$qid} != '') && (!is_numeric($formdata->{'q'.$qid})) ) {
                    $wrongformat++;
                    $strwrongformat .= get_string('num', 'questionnaire').$qnum.'. ';
                }
                break;

            default:
                break;
            }
        }
        $message ='';
        if($missing) {
            if ($missing == 1) {
                $message = get_string('missingquestion', 'questionnaire').$strmissing;
            } else {
                $message = get_string('missingquestions', 'questionnaire').$strmissing;
            }
            if ($wrongformat) {
                $message .= '<br />';
            }
        }
        if($wrongformat) {
            if ($wrongformat == 1) {
                $message .= get_string('wrongformat', 'questionnaire').$strwrongformat;
            } else {
                $message .= get_string('wrongformats', 'questionnaire').$strwrongformat;
            }
        }
        return ($message);
    }


    function response_delete($rid, $sec = null) {
        global $DB;

        if (empty($rid)) {
            return;
        }

        if ($sec != null) {
            if ($sec < 1) {
                return;
            }

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

    function response_import_sec($rid, $sec, &$varr) {
        if ($sec < 1 || !isset($this->questionsbysec[$sec])) {
            return;
        }
		$vals = $this->response_select($rid, 'content');
        reset($vals);
        foreach ($vals as $id => $arr) {
            if (isset($arr[0]) && is_array($arr[0])) {
                // multiple
                $varr->{'q'.$id} = array_map('array_pop', $arr);
            } else {
                $varr->{'q'.$id} = array_pop($arr);
            }
        }
    }



    function response_import_all($rid, &$varr) {
        $vals = $this->response_select($rid, 'content');
        reset($vals);
        foreach ($vals as $id => $arr) {
            if (strstr($id, '_') && isset($arr[4])) { // single OR multiple with !other choice selected
                $varr->{'q'.$id} = $arr[4];
            } else {
                if (isset($arr[0]) && is_array($arr[0])) { // multiple
                    $varr->{'q'.$id} = array_map('array_pop', $arr);
                } else { // boolean, rate and other
                    $varr->{'q'.$id} = array_pop($arr);
                }
            }
        }
    }

    function response_commit($rid) {
        global $DB;

        $record = new object;
        $record->id = $rid;
        $record->complete = 'y';
        $record->submitted = time();

        if ($this->grade < 0) {
            $record->grade = 1;  /// Don't know what to do if its a scale...
        } else {
            $record->grade = $this->grade;
        }
        return $DB->update_record('questionnaire_response', $record);
    }

    function get_response($username, $rid = 0) {
        global $DB;

        $rid = intval($rid);
        if ($rid != 0) {
            // check for valid rid
            $fields = 'id, username';
            $select = 'id = '.$rid.' AND survey_id = '.$this->sid.' AND username = \''.$username.'\' AND complete = \'n\'';
            return ($DB->get_record_select('questionnaire_response', $select, null, $fields) !== false) ? $rid : '';

        } else {
            // find latest in progress rid
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

    function response_select_max_sec($rid) {
        global $DB;

        $pos = $this->response_select_max_pos($rid);
        $select = 'survey_id = \''.$this->sid.'\' AND type_id = 99 AND position < '.$pos.' AND deleted = \'n\'';
        $max = $DB->count_records_select('questionnaire_question', $select) + 1;

        return $max;
    }

    function response_select_max_pos($rid) {
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
                $max = (int)$record->num;
            }
        }
        return $max;
    }

/* {{{ proto array response_select_name(int survey_id, int response_id, array question_ids)
   A wrapper around response_select(), that returns an array of
   key/value pairs using the field name as the key.
   $csvexport = true: a parameter to return a different response formatting for CSV export from normal report formatting
 */
    function response_select_name($rid, $choicecodes, $choicetext) {
        $res = $this->response_select($rid, 'position,type_id,name', true, $choicecodes, $choicetext);
        $nam = array();
        reset($res);
        $subqnum = 0;
        $oldpos = '';
        while(list($qid, $arr) = each($res)) {
            $qpos = $arr[0]; // question position (there may be "holes" in positions list)
            $qtype = $arr[1]; // question type (1:bool,2:text,3:essay,4:radio,5:check,6:dropdn,7:rating(not used),8:rate,9:date,10:numeric)
            $qname = $arr[2]; // variable name; (may be empty); for rate questions: 'variable group' name
            $qchoice = $arr[3]; // modality; for rate questions: variable

            // strip potential html tags from modality name
            if (!empty($qchoice)) {
            	$qchoice = strip_tags($arr[3]);
                $qchoice = ereg_replace("[\r\n\t]", ' ', $qchoice);
			}
            $q4 = ''; // for rate questions: modality; for multichoice: selected = 1; not selected = 0
            if (isset($arr[4])) {
                $q4 = $arr[4];
            }
            if (strstr($qid, '_')) {
                if ($qtype == 4) { //single
                    $nam[$qpos][$qname.'_'.get_string('other', 'questionnaire')] = $q4;
                    continue;
                }
                // multiple OR rank
                if ($oldpos != $qpos) {
                    $subqnum = 1;
                    $oldpos = $qpos;
                } else {
                        $subqnum++;
                }
                if ($qtype == 8) { // rate
                    $qname .= "->$qchoice";
                    if ($q4 == -1) {
//                        $q4 = get_string('notapplicable', 'questionnaire'); DEV JR choose one solution please
                        $q4 = '';
                    } else {
                        if (is_numeric($q4)) {
                            $q4++;
                        }
                    }
                } else { // multiple
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

    function response_send_email($rid, $userid=false) {
        global $CFG, $USER, $DB;

        require_once($CFG->libdir.'/phpmailer/class.phpmailer.php');

        $name = s($this->name);
        if ($record = $DB->get_record('questionnaire_survey', array('id' => $this->survey->id))) {
            $email = $record->email;
        } else {
            $email = '';
        }

        if(empty($email)) {
            return(false);
        }
        $answers = $this->generate_csv($rid, $userid='', null, 1);

        // line endings for html and plaintext emails
        $end_html = "\r\n<br>";
        $end_plaintext = "\r\n";

        $subject = get_string('surveyresponse', 'questionnaire') .": $name [$rid]";
        $url = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$this->survey->id.
                '&amp;rid='.$rid.'&amp;instance='.$this->id;

        // html and plaintext body
        $body_html        = '<a href="'.$url.'">'.$url.'</a>'.$end_html;
        $body_plaintext   = $url.$end_plaintext;
        $body_html       .= get_string('surveyresponse', 'questionnaire') .' "'.$name.'"'.$end_html;
        $body_plaintext  .= get_string('surveyresponse', 'questionnaire') .' "'.$name.'"'.$end_plaintext;

        reset($answers);

        for ($i = 0; $i < count($answers[0]); $i++) {
            $sep = ' : ';
            switch($i) {
            case 1:
                $sep = ' ';
                break;
            case 4:
                $body_html        .= get_string('user').' ';
                $body_plaintext   .= get_string('user').' ';
                break;
            case 6:
                if ($this->respondenttype != 'anonymous') {
                    $body_html         .= get_string('email').$sep.$USER->email. $end_html;
                    $body_plaintext    .= get_string('email').$sep.$USER->email. $end_plaintext;
                }
            }
            $body_html         .= $answers[0][$i].$sep.$answers[1][$i]. $end_html;
            $body_plaintext    .= $answers[0][$i].$sep.$answers[1][$i]. $end_plaintext;
        }

        // use plaintext version for altbody
        $altbody =  "\n$body_plaintext\n";

        $return = true;
        $mailaddresses = preg_split('/,|;/', $email);
        foreach ($mailaddresses as $email) {
            $userto = new Object();
            $userto->email = $email;
            $userto->mailformat = 1;
            $userfrom = $CFG->noreplyaddress;
            if (email_to_user($userto, $userfrom, $subject, $altbody, $body_html)) {
                $return = $return && true;
            } else {
                $return = false;
            }
        }
        return $return;
    }

    function response_insert($sid, $section, $rid, $userid) {
        global $DB;

        if(empty($rid)) {
            // create a uniqe id for this response
            $record = new object;
            $record->survey_id = $sid;
            $record->username = $userid;
            $rid = $DB->insert_record('questionnaire_response', $record);
        }

        if (!empty($this->questionsbysec[$section])) {
            foreach ($this->questionsbysec[$section] as $question) {
                $question->insert_response($rid);
            }
        }
        return($rid);
    }

    function response_select($rid, $col = null, $csvexport = false, $choicecodes=0, $choicetext=1) {
        global $DB;

        $sid = $this->survey->id;
        $values = array();
        $stringother = get_string('other', 'questionnaire');
        if ($col == null) {
            $col = '';
        }
        if (!is_array($col) && !empty($col)) {
            $col = explode(',', preg_replace("/\s/",'', $col));
        }
        if (is_array($col) && count($col) > 0) {
            $col = ',' . implode(',', array_map(create_function('$a','return "q.$a";'), $col));
        }

        // --------------------- response_bool (yes/no)---------------------
        $sql = 'SELECT q.id '.$col.', a.choice_id '.
               'FROM {questionnaire_response_bool} a, {questionnaire_question} q '.
               'WHERE a.response_id= ? AND a.question_id=q.id ';
        if ($records = $DB->get_records_sql($sql, array($rid))) {
            foreach ($records as $qid => $row) {
                $choice = $row->choice_id;
                if (isset ($row->name) && $row->name == '') {
                    $noname = TRUE;
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
                    array_push($values["$qid"], $choice); //DEV still needed for responses display
                }
            }
        }

        // --------------------- response_single (radio button or dropdown)---------------------
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
                    foreach($qrecords as $value) {
                        if ($value->id == $cid) {
                            $contents = choice_values($value->content);
                            if ($contents->modname) {
                                $row->ccontent = $contents->modname;
                            } else {
                                $content = $contents->text;
                                if (ereg('^!other', $content)) {
                                    $row->ccontent = get_string('other','questionnaire');
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
                if (ereg('^!other', $row->ccontent)) {
                    $newrow[] = 'other_' . $cid;
                } else {
                    $newrow[] = (int)$cid;
                }
                $values[$qid] = $newrow;
            }
        }

        // --------------------- response_multiple ---------------------
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
                                $c2 = '!other='.get_string('other','questionnaire');
                            }
                            if (ereg('^!other', $c2)) {
                                $otherend = true;
                            } else {
                                $contents = choice_values($c2);
                                if ($contents->modname) {
                                    $c2 = $contents->modname;
                                } elseif ($contents->title) {
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
                                // if available choice has been selected by student
                                if ($qid1 == $qid2 && $cid1 == $cid2) {
                                    $selected = '1';
                                }
                            }
                            if ($otherend) {
                                $newrow2 = array();
                                $newrow2[] = $question->pos;
                                $newrow2[] = $type1;
                                $newrow2[] = $name1;
                                $newrow2[] = '['.get_string('other','questionnaire').']';
                                $newrow2[] = $selected;
                                $tmp2 = $qid2.'_other';
                                $values["$tmp2"]=$newrow2;
                            }
                            $newrow[] = $question->pos;
                            $newrow[] = $type1;
                            $newrow[] = $name1;
                            $newrow[] = $c2;
                            $newrow[] = $selected;
                            $tmp = $qid2.'_'.$cid2;
                            $values["$tmp"]=$newrow;
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
                        if (ereg('^!other', $row->ccontent)) {
                            $newrow[] = 'other_' . $cid;
                        } else {
                            $newrow[] = (int)$cid;
                        }
                        if($tmp == $qid) {
                            $arr[] = $newrow;
                            continue;
                        }
                        if($tmp != null) {
                            $values["$tmp"]=$arr;
                        }
                        $tmp = $qid;
                        $arr = array($newrow);
                    }
                }
                if($tmp != null) {
                    $values["$tmp"]=$arr;
                }
                unset($arr);
                unset($tmp);
                unset($row);


        }

            // --------------------- response_other ---------------------
            // this will work even for multiple !other fields within one question AND for identical !other responses in different questions JR
        $sql = 'SELECT c.id as cid, c.content as content, a.response as aresponse, q.id as qid, q.position as position, q.type_id as type_id, q.name as name '.
               'FROM {questionnaire_response_other} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
               'WHERE a.response_id= ? AND a.question_id=q.id AND a.choice_id=c.id '.
               'ORDER BY a.question_id,c.id ';
        if ($records = $DB->get_records_sql($sql, array($rid))) {
            foreach ($records as $record) {
                $newrow = array();
                $position = $record->position;
                $type_id = $record->type_id;
                $name = $record->name;
                $cid = $record->cid;
                $qid = $record->qid;
                $content = $record->content;

                //!other modality with no label
                if ($content == '!other') {
                    $content = '!other='.$stringother;
                }
                $content = substr($content,7);
                $aresponse = $record->aresponse;
                // the first two empty values are needed for compatibility with "normal" (non !other) responses
                // they are only needed for the CSV export, in fact - JR
                $newrow[] = $position;
                $newrow[] = $type_id;
                $newrow[] = $name;
                $content = $stringother;
                $newrow[] = $content;
                $newrow[] = $aresponse;
                $values["${qid}_${cid}"] = $newrow;
            }
        }

            // --------------------- response_rank ---------------------
        $sql = 'SELECT a.id as aid, q.id AS qid, q.precise AS precise, c.id AS cid '.$col.',c.content as ccontent,a.rank as arank '.
               'FROM {questionnaire_response_rank} a, {questionnaire_question} q, {questionnaire_quest_choice} c '.
               'WHERE a.response_id= ? AND a.question_id=q.id AND a.choice_id=c.id '.
               'ORDER BY aid, a.question_id,c.id';
        if ($records = $DB->get_records_sql($sql, array($rid))) {
            foreach ($records as $row) {
                /// Next two are 'qid' and 'cid', each with numeric and hash keys.
                $osgood = false;
                if ($row->precise == 3) {
                    $osgood = true;
                }
                $qid = $row->qid.'_'.$row->cid;
                unset($row->aid); // get rid of the answer id.
                unset($row->qid);
                unset($row->cid);
                unset($row->precise);
                $row = (array)$row;
                $newrow = array();
                foreach ($row as $key => $val) {
                    if ($key != 'content') { // no need to keep question text - ony keep choice text and rank
                        if ($key == 'ccontent') {
                            if ($osgood) {
                                list($contentleft, $contentright) = split('[|]', $val);
                                $contents = choice_values($contentleft);
                                if ($contents->title) {
                                    $contentleft = $contents->title;
                                }
                                $contents = choice_values($contentright);
                                if ($contents->title) {
                                    $contentright = $contents->title;
                                }
                                $val = strip_tags($contentleft.'|'.$contentright);
                                $val = ereg_replace("[\r\n\t]", ' ', $val);
                            } else {
                                $contents = choice_values($val);
                                if ($contents->modname) {
                                    $val = $contents->modname;
                                } elseif ($contents->title) {
                                    $val = $contents->title;
                                } elseif ($contents->text) {
                                    $val = strip_tags($contents->text);
                                    $val = ereg_replace("[\r\n\t]", ' ', $val);
                                }
                            }
                        }
                        $newrow[] = $val;
                    }
                }
                $values[$qid] = $newrow;
            }
        }

            // --------------------- response_text ---------------------
        $sql = 'SELECT q.id '.$col.',a.response as aresponse '.
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
                $values["$qid"]=$newrow;
                $val = array_pop($values["$qid"]);
                array_push($values["$qid"], $val, $val);
            }
        }

            // --------------------- response_date ---------------------
        $sql = 'SELECT q.id '.$col.',a.response as aresponse '.
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
                    // convert date from yyyy-mm-dd database format to actual questionnaire dateformat
                    // does not work with dates prior to 1900 under Windows
                        if (preg_match('/\d\d\d\d-\d\d-\d\d/', $val)) {
                            $dateparts = split('-', $val);
                            $val = make_timestamp($dateparts[0], $dateparts[1], $dateparts[2]); // Unix timestamp
                            $val = userdate ( $val, $dateformat);
                            $newrow[] = $val;
                        }
                    }
                }
                $values["$qid"]=$newrow;
                $val = array_pop($values["$qid"]);
                array_push($values["$qid"], '', '', $val);
            }
        }

            // --------------------- return ---------------------
            return($values);
    }

    function response_goto_thankyou() {
        global $CFG, $USER, $DB;

        $select = 'id = '.$this->survey->id;
        $fields = 'thanks_page,thank_head,thank_body';
        if ($result = $DB->get_record_select('questionnaire_survey', $select, null, $fields)) {
            $thank_url = $result->thanks_page;
            $thank_head = $result->thank_head;
            $thank_body = $result->thank_body;
        } else {
            $thank_url = '';
            $thank_head = '';
            $thank_body = '';
        }
        if(!empty($thank_url)) {
            if(!headers_sent()) {
                header("Location: $thank_url");
                exit;
            }
    ?>
    <script language="JavaScript" type="text/javascript">
    <!--
    window.location="<?php echo($thank_url); ?>"
    //-->
    </script>
    <noscript>
    <h2 class="thankhead">Thank You for completing this survey.</h2>
    <blockquote class="thankbody">Please click
    <a href="<?php echo($thank_url); ?>">here</a>
    to continue.</blockquote>
    </noscript>
    <?php
            exit;
        }
        if(empty($thank_head)) {
            $thank_head = get_string('thank_head', 'questionnaire');
        }
        $message =  '<h3>'.$thank_head.'</h3>'.file_rewrite_pluginfile_urls($thank_body, 'pluginfile.php', $this->context->id, 'mod_questionnaire', 'thankbody', $this->id);
		echo ($message);
        if ($this->capabilities->readownresponses) {
        	echo('<a href="'.$CFG->wwwroot.'/mod/questionnaire/myreport.php?id='.
			$this->cm->id.'&amp;instance='.$this->cm->instance.'&amp;user='.$USER->id.'">'.
			get_string("continue").'</a>');
        } else {
        	echo('<a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">'.
			get_string("continue").'</a>');
        }
        return;
    }

    function response_goto_saved($url) {
    ?>
    <div class="thankbody">
    <?php print_string('savedprogress', 'questionnaire', '<strong>'.get_string('resumesurvey', 'questionnaire').'</strong>'); ?>
        <div><a href="<?php echo $url; ?>"><?php print_string('resumesurvey', 'questionnaire'); ?></a>
        </div>
    </div>

    <?php
        global $CFG;
        echo ('<div class="homelink"><a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">&nbsp;&nbsp;'
        .get_string("backto","moodle",$this->course->fullname).'&nbsp;&nbsp;</a></div>');
    ?>
    <?php
        return;
    }


    /// Survey Results Methods

    function survey_results_navbar($curr_rid, $userid=false) {
        global $CFG, $DB;

		$stranonymous = get_string('anonymous', 'questionnaire');

        $select = 'survey_id='.$this->survey->id.' AND complete = \'y\'';
        if ($userid !== false) {
            $select .= ' AND username = \''.$userid.'\'';
        }
        if (!($responses = $DB->get_records_select('questionnaire_response', $select, null, 'id', 'id,survey_id,submitted,username'))) {
            return;
        }
        $total = count($responses);
        if ($total == 1) {
            return;
        }
        $rids = array();
        $ridssub = array();
        $ridsusername = array();
        $i = 0;
        $curr_pos = -1;
        foreach ($responses as $response) {
            array_push($rids, $response->id);
            array_push($ridssub, $response->submitted);
            array_push($ridsusername, $response->username);
            if ($response->id == $curr_rid) {
                $curr_pos = $i;
            }
            $i++;
        }

        $prev_rid = ($curr_pos > 0) ? $rids[$curr_pos - 1] : null;
        $next_rid = ($curr_pos < $total - 1) ? $rids[$curr_pos + 1] : null;
        $rows_per_page = 1;
        $pages = ceil($total / $rows_per_page);

        $url = $CFG->wwwroot.'/mod/questionnaire/report.php?action=vresp&amp;sid='.$this->survey->id;

        $mlink = create_function('$i,$r', 'return "<a href=\"'.$url.'&amp;rid=$r\">$i</a>";');

        $linkarr = array();

        $display_pos = 1;
        if ($prev_rid != null) {
            array_push($linkarr, "<a href=\"$url&amp;rid=$prev_rid\">".get_string('previous').'</a>');
        }
        $ruser = '';
        for ($i = 0; $i < $curr_pos; $i++) {
            if ($this->respondenttype != 'anonymous') {
                if ($user = $DB->get_record('user', array('id' => $ridsusername[$i]))) {
                    $ruser = fullname($user);
                }
            } else {
                $ruser = $stranonymous;
            }
            $title = userdate($ridssub[$i]).' | ' .$ruser;
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$rids[$i].'" title="'.$title.'">'.$display_pos.'</a>');
            $display_pos++;
        }
        array_push($linkarr, '<b>'.$display_pos.'</b>');
        for (++$i; $i < $total; $i++) {
            if ($this->respondenttype != 'anonymous') {
                if ($user = $DB->get_record('user', array('id' => $ridsusername[$i]))) {
                    $ruser = fullname($user);
                }
            } else {
                $ruser = $stranonymous;
            }
            $title = userdate($ridssub[$i]).' | ' .$ruser;
            $display_pos++;
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$rids[$i].'" title="'.$title.'">'.$display_pos.'</a>');

        }
        if ($next_rid != null) {
            array_push($linkarr, "<a href=\"$url&amp;rid=$next_rid\">".get_string('next').'</a>');
        }
        echo implode(' | ', $linkarr);
    }

    function survey_results_navbar_student($curr_rid, $userid, $instance, $resps, $reporttype='myreport', $sid='') {
        global $DB;

        $stranonymous = get_string('anonymous', 'questionnaire');

        $total = count($resps);
        $rids = array();
        $ridssub = array();
        $ridsusers = array();
        $i = 0;
        $curr_pos = -1;
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
            if ($response->id == $curr_rid) {
                $curr_pos = $i;
            }
            $i++;
        }
        $prev_rid = ($curr_pos > 0) ? $rids[$curr_pos - 1] : null;
        $next_rid = ($curr_pos < $total - 1) ? $rids[$curr_pos + 1] : null;
        $rows_per_page = 1;
        $pages = ceil($total / $rows_per_page);

        if ($reporttype == 'myreport') {
            $url = 'myreport.php?instance='.$instance.'&amp;user='.$userid.'&amp;action=vresp';
        } else {
            $url = 'report.php?instance='.$instance.'&amp;user='.$userid.'&amp;action=vresp&amp;byresponse=1&amp;sid='.$sid;
        }
        $linkarr = array();
        $display_pos = 1;
        if ($prev_rid != null) {
            $title = userdate($ridssub[$curr_pos - 1].$ridsusers[$curr_pos - 1]);
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$prev_rid.'" title="'.$title.'">'.get_string('previous').'</a>');
        }
        for ($i = 0; $i < $curr_pos; $i++) {
            $title = userdate($ridssub[$i]).$ridsusers[$i];
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$rids[$i].'" title="'.$title.'">'.$display_pos.'</a>');
            $display_pos++;
        }
        array_push($linkarr, '<b>'.$display_pos.'</b>');
        for (++$i; $i < $total; $i++) {
            $display_pos++;
            $title = userdate($ridssub[$i]).$ridsusers[$i];
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$rids[$i].'" title="'.$title.'">'.$display_pos.'</a>');
        }
        if ($next_rid != null) {
//            $title = userdate($ridssub[$curr_pos]);
            $title = userdate($ridssub[$curr_pos + 1]).$ridsusers[$curr_pos + 1];
            array_push($linkarr, '<a href="'.$url.'&amp;rid='.$next_rid.'" title="'.$title.'">'.get_string('next').'</a>');
        }
        echo implode(' | ', $linkarr);
    }

    /* {{{ proto string survey_results(int survey_id, int precision, bool show_totals, int question_id, array choice_ids, int response_id)
        Builds HTML for the results for the survey. If a
        question id and choice id(s) are given, then the results
        are only calculated for respodants who chose from the
        choice ids for the given question id.
        Returns empty string on sucess, else returns an error
        string. */
    function survey_results($precision = 1, $showTotals = 1, $qid = '', $cids = '', $rid = '', $guicross='', $uid=false, $groupid='', $sort='') {
        global $SESSION, $DB;

        $SESSION->questionnaire->noresponses = false;
        if(empty($precision)) {
            $precision  = 1;
        }
        if($showTotals === '') {
            $showTotals = 1;
        }

        if(is_int($cids)) {
            $cids = array($cids);
        }
        if(is_string($cids)) {
            $cids = split(" ",$cids); // turn space seperated list into array
        }

        // set up things differently for cross analysis
        $cross = !empty($qid);
        if($cross) {
            if(is_array($cids) && count($cids)>0) {
                $cidstr = $this->array_to_insql($cids);
            } else {
                $cidstr = '';
            }
        }

        // build associative array holding whether each question
        // type has answer choices or not and the table the answers are in
        /// TO DO - FIX BELOW TO USE STANDARD FUNCTIONS
        $has_choices = array();
        $response_table = array();
        if (!($types = $DB->get_records('questionnaire_question_type', array(), 'typeid', 'typeid,has_choices,response_table'))) {
            $errmsg = sprintf('%s [ %s: question_type ]',
                    get_string('errortable', 'questionnaire'), 'Table');
            return($errmsg);
        }
        foreach ($types as $type) {
            $has_choices[$type->typeid]=$type->has_choices;
            $response_table[$type->typeid]=$type->response_table;
        }

        // load survey title (and other globals)
        if (empty($this->survey)) {
            $errmsg = get_string('erroropening', 'questionnaire') ." [ ID:${sid} R:";
            return($errmsg);
        }

        if (empty($this->questions)) {
            $errmsg = get_string('erroropening', 'questionnaire') .' '. 'No questions found.' ." [ ID:${sid} ]";
            return($errmsg);
        }

        // find out more about the question we are cross analyzing on (if any)
        if($cross) {
            $crossTable = $response_table[$DB->get_field('questionnaire_question', 'type_id', array('id' => $qid))];
            if(!in_array($crossTable, array('resp_single','response_bool','resp_multiple'))) {
                $errmsg = get_string('errorcross', 'questionnaire') .' [ '. 'Table' .": ${crossTable} ]";
                return($errmsg);
            }
        }

    // find total number of survey responses
    // and relevant response ID's
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
            if($cross) {
                if(!empty($cidstr))
                    $sql = "SELECT A.response_id, R.id
                              FROM {questionnaire_".$crossTable."} A,
                                   {questionnaire_response} R
                             WHERE A.response_id=R.id AND
                                   R.complete='y' AND
                                   A.question_id='${qid}' AND
                                   A.choice_id ${cidstr}
                             ORDER BY A.response_id";
                else
                    $sql = "SELECT A.response_id, R.id
                              FROM {questionnaire_".$crossTable."} A,
                                   {questionnaire_response} R
                             WHERE A.response_id=R.id AND
                                   R.complete='y' AND
                                   A.question_id='${qid}' AND
                                   A.choice_id = 0
                             ORDER BY A.response_id";
            } else if ($uid !== false) { // one participant only
                $sql = "SELECT r.id, r.survey_id
                          FROM {questionnaire_response} r
                         WHERE r.survey_id='{$this->survey->id}' AND
                               r.username = $uid AND
                               r.complete='y'
                         ORDER BY r.id";
            } else if ($groupid == -1) { // all participants
                $sql = "SELECT R.id, R.survey_id
                          FROM {questionnaire_response} R
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y'
                         ORDER BY R.id";
            } else if ($groupid == -2) { // all members of any group
                $sql = "SELECT R.id, R.survey_id
                          FROM {questionnaire_response} R,
                                {groups_members} GM
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               GM.groupid>0 AND
                               ".$castsql."=GM.userid
                         ORDER BY R.id";
            } else if ($groupid == -3) { // not members of any group
                $sql = "SELECT R.id, R.survey_id, U.id AS userid
                          FROM {questionnaire_response} R,
                                {user} U
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               ".$castsql."=U.id
                         ORDER BY userid";
            } else { // members of a specific group
                $sql = "SELECT R.id, R.survey_id
                          FROM {questionnaire_response} R,
                                {groups_members} GM
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               GM.groupid=".$groupid." AND
                               ".$castsql."=GM.userid
                         ORDER BY R.id";
            }
            if (!($rows = $DB->get_records_sql($sql))) {
                echo (get_string('noresponses','questionnaire'));
                $SESSION->questionnaire->noresponses = true;
                return;
            }
            if ($groupid == -3) { // members of no group
                foreach ($rows as $row=>$key) {
                    if (groups_has_membership($this->cm, $key->userid)) {
                        unset($rows[$row]);
                    }
                }
            }
            $total = count($rows);
            echo (' '.get_string('responses','questionnaire').": <strong>$total</strong>");
            if(empty($rows)) {
                $errmsg = get_string('erroropening', 'questionnaire') .' '. get_string('noresponsedata', 'questionnaire');
                    return($errmsg);
            }

            $rids = array();
            foreach ($rows as $row) {
                array_push($rids, $row->id);
            }
        }

        if ($navbar) {
            // show response navigation bar
            $this->survey_results_navbar($rid);
        }

    ?>
    <h2><?php echo($this->survey->title); ?></h2>
    <?php
        if ($this->survey->subtitle) {
            echo('<h3>'.$this->survey->subtitle.'</h3>');
        }
    ?>
    <?php echo(file_rewrite_pluginfile_urls($this->survey->info, 'pluginfile.php', $this->context->id, 'mod_questionnaire', 'info', $this->id)); ?>
    <?php
        if($cross) {
            echo("<blockquote>" ._('Cross analysis on QID:') ." ${qid}</blockquote>\n");
        }
    ?>
    <table border="0" style="width:100%">
    <?php
        $i=0; // question number counter
        foreach ($this->questions as $question) {
            // process each question

            if ($question->type_id == 99) {
                continue;
            }
            if ($question->type_id == 100) {
                echo ("<td colspan=\"2\">". format_text(file_rewrite_pluginfile_urls($question->content, 'pluginfile.php', $question->context->id, 'mod_questionnaire',
                                                  'question', $question->id), FORMAT_HTML)."</td></tr>\n");
                continue;
            }
            echo ("<tr>\n");
            echo ("<td class = \"reportQuestionNumber\">");
            if ($question->type_id < 50) {
                if (!empty($guicross)){
                    echo ('<div>');
                    echo ('<input type="hidden" name="where" value="results" />');
                    echo ('<input type="hidden" name="sid" value="'.$this->survey->id.'" />');
                    echo ('</div>');
                    echo ("\n<table width=\"90%\" border=\"0\">\n");
                    echo ("<tbody>\n");
                    echo ("   <tr>\n");
                    echo ("      <td width=\"34\" height=\"31\" >\n");
                    if ($question->type_id ==1 || $question->type_id ==4 || $question->type_id ==5 || $question->type_id ==6){
                        echo ("<div align=\"center\">\n");
                        echo ("   <input type=\"radio\" name=\"qid\" value=\"".$question->id."\" />\n");
                        echo ("</div>\n");
                    }
                    echo ("</td>\n");
                    echo ("<td width=\"429\" >\n");
                } //end if empty($guicross)
                echo ++$i;
                echo ("</td>");
                echo ("<td>");
                echo ("<div class = \"reportQuestionTitle\">");

                if (!empty($guicross)){
                    echo ("</td>\n");
                    echo ("<td width=\"33\" >\n");
                    if ($question->type_id ==1 || $question->type_id ==4 || $question->type_id ==5 || $question->type_id ==6){
                        echo ("<div align=\"center\">\n");
                        echo ("<input type=\"radio\" name=\"qidr\" value=\"".$question->id."\" />\n");
                        echo ("</div>\n");
                    }
                    echo ("</td>\n");
                    echo ("<td width=\"32\" >\n");
                    if ($question->type_id ==1 || $question->type_id ==4 || $question->type_id ==5 || $question->type_id ==6){
                        echo ("<div align=\"center\">\n");
                        echo ("<input type=\"radio\" name=\"qidc\" value=\"".$question->id."\" />\n");
                        echo ("</div>\n");
                    }
                    echo ("</td>\n");
                    echo ("</tr>\n");
                    echo ("</tbody>\n");
                    echo ("</table>\n");
                } //end if empty($guicross)
            } //end if ($question->type_id  < 50)

    $counts = array();

    // ---------------------------------------------------------------------------
    echo format_text(file_rewrite_pluginfile_urls($question->content, 'pluginfile.php', $question->context->id, 'mod_questionnaire',
                                                  'question', $question->id), FORMAT_HTML).'</div>'; // moved from $question->display_results
    $question->display_results($rids, $guicross, $sort);

    ?>
            </td>
        </tr>
    <?php } // end while ?>
    </table>
    <?php
        return;
    }

/* {{{ proto array survey_generate_csv(int survey_id)
    Exports the results of a survey to an array.
    */
    function generate_csv($rid='', $userid='', $choicecodes=1, $choicetext=0) {
	    global $SESSION, $DB;

        if (isset($SESSION->questionnaire->currentgroupid)) {
            $groupid = $SESSION->questionnaire->currentgroupid;
        } else{
            $groupid = -1;
        }
        $output = array();
        $nbinfocols = 9; // change this if you want more info columns
        $stringother = get_string('other', 'questionnaire');
        $columns = array(
                get_string('response','questionnaire'),
                get_string('submitted','questionnaire'),
                get_string('institution'),
                get_string('department'),
                get_string('course'),
                get_string('group'),
                get_string('id','questionnaire'),
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
        // 0 = number; 1 = text
        $id_to_csv_map = array(
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
            '0'     // 10: numeric -> number
        );

        if (!$survey = $DB->get_record('questionnaire_survey', array('id' => $this->survey->id))) {
            print_error ('surveynotexists', 'questionnaire');
        }

        $select = 'survey_id = '.$this->survey->id.' AND deleted = \'n\' AND type_id < 50';
        $fields = 'id,name,type_id,position';
        if (!($records = $DB->get_records_select('questionnaire_question', $select, null, 'position', $fields))) {
            $records = array();
        }

        $num = 1;
        foreach ($records as $record) {
            // establish the table's field names
            $qid = $record->id;
            $qpos = $record->position;
            if ($record->name == '') {
            }
            $col = $record->name;
            $type = $record->type_id;
            if ($type == 4 || $type == 5 || $type == 8) {
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

                    case 4: // single
                        $columns[][$qpos] = $col;
                        array_push($types, $id_to_csv_map[$type]);
                        $thisnum = 1;
                        foreach ($records2 as $record2) {
                            $content = $record2->content;
                            if (ereg('^!other', $content)) {
                                $col = $record2->name.'_'.$stringother;
                                $columns[][$qpos] = $col;
                                array_push($types, '0');
                            }
                        }
                        break;

                    case 5: // multiple
                        $thisnum = 1;
                        foreach ($records2 as $record2) {
                            $content = $record2->content;
                            $modality = '';
                            if (ereg('^!other', $content)) {
                                $content = $stringother;
                                $col = $record2->name.'->['.$content.']';
                                $columns[][$qpos] = $col;
                                array_push($types, '0');
                            }
                            $contents = choice_values($content);
                            if ($contents->modname) {
                                $modality = $contents->modname;
                            } elseif ($contents->title) {
                                $modality = $contents->title;
                            } else {
                                $modality = strip_tags($contents->text);
                            }
                            $col = $record2->name.'->'.$modality;
                            $columns[][$qpos] = $col;
                            array_push($types, '0');
                        }
                        break;

                    case 8: // rate
                        foreach ($records2 as $record2) {
                            $nameddegrees = 0;
                            $modality = '';
                            $content = $record2->content;
                            $osgood = false;
                            if ($record2->precise == 3) {
                                $osgood = true;
                            }
                            if (ereg("^[0-9]{1,3}=", $content,$ndd)) {
                                $nameddegrees++;
                            } else {
                                if ($osgood) {
                                    list($contentleft, $contentright) = split('[|]', $content);
                                    $contents = choice_values($contentleft);
                                    if ($contents->title) {
                                        $contentleft = $contents->title;
                                    }
                                    $contents = choice_values($contentright);
                                    if ($contents->title) {
                                        $contentright = $contents->title;
                                    }
                                    $modality = strip_tags($contentleft.'|'.$contentright);
                                    $modality = ereg_replace("[\r\n\t]", ' ', $modality);
                                } else {
                                    $contents = choice_values($content);
                                    if ($contents->modname) {
                                        $modality = $contents->modname;
                                    } elseif ($contents->title) {
                                        $modality = $contents->title;
                                    } else {
                                        $modality = strip_tags($contents->text);
                                        $modality = ereg_replace("[\r\n\t]", ' ', $modality);
                                    }
                                }
                                $col = $record2->name.'->'.$modality;
                                $columns[][$qpos] = $col;
                                array_push($types, $id_to_csv_map[$type]);
                            }
                        }
                        break;
                }
            } else {
                $columns[][$qpos] = $col;
                array_push($types, $id_to_csv_map[$type]);
            }
            $num++;
        }
        array_push($output, $columns);
        $numcols = count($output[0]);

        if ($rid) {         // send e-mail for a unique response ($rid)
            $select = 'survey_id = '.$this->survey->id.' AND complete=\'y\' AND id = '.$rid;
            $fields = 'id,submitted,username';
            if (!($records = $DB->get_records_select('questionnaire_response', $select, null, 'submitted', $fields))) {
                $records = array();
            }
        } else if ($userid) { // download CSV for one user's own responses'
                $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                          FROM {questionnaire_response} R
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               R.username='$userid'
                         ORDER BY R.id";
            if (!($records = $DB->get_records_sql($sql))) {
                $records = array();
            }

        } else { // download CSV for all participants (or groups if enabled)
            $castsql = $DB->sql_cast_char2int('R.username');
            if ($groupid == -1) { // all participants
                $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                          FROM {questionnaire_response} R
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y'
                         ORDER BY R.id";
            } else if ($groupid == -2) { // all members of any group
                $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                          FROM {questionnaire_response} R,
                                {groups_members} GM
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               GM.groupid>0 AND
                               ".$castsql."=GM.userid
                         ORDER BY R.id";
            } else if ($groupid == -3) { // not members of any group
                $sql = "SELECT R.id, R.survey_id, R.submitted,  U.id AS username
                          FROM {questionnaire_response} R,
                                {user} U
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               ".$castsql."=U.id
                         ORDER BY username";
            } else { // members of a specific group
                $sql = "SELECT R.id, R.survey_id, R.submitted, R.username
                          FROM {questionnaire_response} R,
                                {groups_members} GM
                         WHERE R.survey_id='{$this->survey->id}' AND
                               R.complete='y' AND
                               GM.groupid=".$groupid." AND
                               ".$castsql."=GM.userid
                         ORDER BY R.id";
            }
            if (!($records = $DB->get_records_sql($sql))) {
                $records = array();
            }
            if ($groupid == -3) { // members of no group
                foreach ($records as $row=>$key) {
                    $userid = $key->username;
                    if (groups_has_membership($this->cm, $userid)) {
                        unset($records[$row]);
                    }
                }
            }
        }
        $isanonymous = $this->respondenttype == 'anonymous';
        $format_options = new Object();
        $format_options->filter = false;  // To prevent any filtering in CSV output...
        foreach ($records as $record) {
            // get the response
            $response = $this->response_select_name($record->id, $choicecodes, $choicetext);
            $qid = $record->id;
            //JR for better compabitility & readability with Excel
            $submitted = date(get_string('strfdateformatcsv', 'questionnaire'), $record->submitted);
            $institution = '';
            $department = '';
            $username  = $record->username;
            if ($user = $DB->get_record('user', array('id' => $username))) {
                $institution = $user->institution;
                $department = $user->department;
            }

            /// Moodle:
            //  Get the course name that this questionnaire belongs to.
            if ($survey->realm != 'public') {
                $courseid = $this->course->id;
                $coursename = $this->course->fullname;
            } else {
                /// For a public questionnaire, look for the course that used it.
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
            /// Moodle:
            //  If the username is numeric, try it as a Moodle user id.
            if (is_numeric($username)) {
                if ($user = $DB->get_record('user', array('id' => $username))) {
                    $uid = $username;
                    $fullname = fullname($user);
                    $username = $user->username;
                }
            }

            /// Moodle:
            //  Determine if the user is a member of a group in this course or not.
            $groupname = '';
            if ($this->cm->groupmode > 0) {
                if ($groupid > 0) {
                    $groupname = groups_get_group_name($groupid);
                } else {
                    if ($uid) {
                        if ($groups = groups_get_all_groups($courseid, $uid)) {
                            foreach ($groups as $group) {
                                $groupname.= $group->name.', ';
                            }
                            $groupname = substr($groupname, 0, strlen($groupname) -2);
                        } else {
                            $groupname = ' ('.get_string('groupnonmembers').')';
                        }
                    }
                }
            }
            if ($isanonymous) {
                $fullname =  get_string('anonymous', 'questionnaire');
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

            // merge it
            for($i = $nbinfocols; $i < $numcols; $i++) {
                /*if (isset($response[$columns[$i]]) && is_array($response[$columns[$i]])) {
                    $response[$columns[$i]] = join(',', $response[$columns[$i]]);
                }*/

            $qpos = key($columns[$i]);
            $qname = current($columns[$i]);
            if (isset($response[$qpos][$qname]) && $response[$qpos][$qname] != '') {
                $thisresponse = $response[$qpos][$qname];
            } else {
                $thisresponse = '';
            }

            switch ($types[$i]) {
                case 1:  //string
                         // Excel seems to allow "\n" inside a quoted string, but
                         // "\r\n" is used as a record separator and so "\r" may
                         // not occur within a cell. So if one would like to preserve
                         // new-lines in a response, remove the "\n" from the
                         // regex below.

                        // email format text is plain text for being displayed in Excel, etc. added by JR
                        // but it must be stripped of carriage returns
                    if ($thisresponse) {
                        $thisresponse = format_text($thisresponse, FORMAT_HTML, $format_options);
                        $thisresponse = ereg_replace("[\r\n\t]", ' ', $thisresponse);
                        $thisresponse = ereg_replace('"', '""', $thisresponse);
                    }
                     // fall through
                case 0:  //number
                        //array_push($arr,$thisresponse);
                break;
                }
                array_push($arr,$thisresponse);
            }
            array_push($output, $arr);
        }

        // change table headers to incorporate actual question numbers
        $numcol = 0;
        $numquestion = 0;
        $out = '';
        $nbrespcols = count($output[0]);
        $oldkey = 0;

        for ($i = $nbinfocols;$i < $nbrespcols; $i++) {
            $sep = '';
            $thisoutput = current($output[0][$i]);
            $thiskey =  key($output[0][$i]);
            // case of unnamed rate single possible answer (full stop char is used for support)
            if (strstr($thisoutput,'->.')) {
                $thisoutput = str_replace('->.','',$thisoutput);
            }

            // if variable is not named no separator needed between Question number and potential sub-variables
            if ($thisoutput == '' || strstr($thisoutput,'->.') || substr($thisoutput,0,2) == '->' || substr($thisoutput,0,1) == '_') {
                $sep = '';
            } else {
                $sep = '_';
            }
            if ($thiskey > $oldkey) {
                $oldkey = $thiskey;
                $numquestion++;
            }
            // abbreviated modality name in multiple or rate questions (COLORS->blue=the color of the sky...)
            $pos = strpos($thisoutput, '=');
            if($pos) {
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
    function export_csv($filename) {
        $umask = umask(0077);
        $fh = fopen($filename, 'w');
        umask($umask);
        if(!$fh)
            return 0;

        $data = survey_generate_csv($rid='', $userid='', $groupid='');

        foreach ($data as $row) {
            fputs($fh, join(',', $row) . "\n");
        }

        fflush($fh);
        fclose($fh);

        return 1;
    }

    /**
     * Function to move a question to a new position.
     *
     * @param int $moveqid The id of the question to be moved.
     * @param int $movetopos The position to move before, or zero if the end.
     *
     */
    function move_question($moveqid, $movetopos) {
        global $DB;

        /// If its moving to the last position (moveto = 0), or its moving to a higher position
        /// No point in moving it to where it already is...
        if (($movetopos == 0) || (($movetopos-1) > $this->questions[$moveqid]->position)) {
            $found = false;
            foreach ($this->questions as $qid => $question) {
                if ($moveqid == $qid) {
                    $found = true;
                    continue;
                }
                if ($found) {
                    $DB->set_field('questionnaire_question', 'position', $question->position-1, array('id' => $qid));
                }
                if ($question->position == ($movetopos-1)) {
                    break;
                }
            }
            if ($movetopos == 0) {
                $movetopos = count($this->questions);
            } else {
                $movetopos--;
            }
            $DB->set_field('questionnaire_question', 'position', $movetopos, array('id' => $moveqid));

        } else if ($movetopos < $this->questions[$moveqid]->position) {
            $found = false;
            foreach ($this->questions as $qid => $question) {
                if ($movetopos == $question->position) {
                    $found = true;
                }
                if (!$found) {
                    continue;
                } else {
                    $DB->set_field('questionnaire_question', 'position', $question->position+1, array('id' => $qid));
                }
                if ($question->position == ($this->questions[$moveqid]->position-1)) {
                    break;
                }
            }
            $DB->set_field('questionnaire_question', 'position', $movetopos, array('id' => $moveqid));
        }
    }
}
    /* {{{ proto void mkcrossformat (array weights, integer qid)
       Builds HTML to allow for cross tabulation/analysis reporting.
     */
function questionnaire_response_key_cmp($l, $r) {
    $lx = explode('_', $l);
    $rx = explode('_', $r);
    $lc = intval($lx[0]);
    $rc = intval($rx[0]);
    if ($lc == $rc) {
        if (count($lx) > 1 && count($rx) > 1) {
            $lc = intval($lx[1]);
            $rc = intval($rx[1]);
        } else if (count($lx) > 1) {
            $lc++;
        } else if (count($rx) > 1) {
            $rc++;
        }
    }
    if ($lc == $rc)
        return 0;
    return ($lc > $rc) ? 1 : -1;
}

    function check_date ($thisdate, $insert=false) {
        $dateformat = get_string('strfdate', 'questionnaire');
        if (preg_match('/(%[mdyY])(.+)(%[mdyY])(.+)(%[mdyY])/', $dateformat, $matches)) {
            $date_pieces = explode($matches[2], $thisdate);
            foreach ($date_pieces as $datepiece) {
                if (!is_numeric($datepiece)) {
                    return 'wrongdateformat';
                }
            }
            $pattern = "/[^dmy]/i";
            $dateorder = strtolower(preg_replace($pattern, '', $dateformat));
            $countpieces = count($date_pieces);
            if ($countpieces == 1) { // assume only year entered
                switch ($dateorder) {
                    case 'dmy': // most countries
                    case 'mdy': // USA
                        $date_pieces[2] = $date_pieces[0]; // year
                        $date_pieces[0] = '1'; // assumed 1st month of year
                        $date_pieces[1] = '1'; // assumed 1st day of month
                        break;
                    case 'ymd': // ISO 8601 standard
                        $date_pieces[1] = '1'; // assumed 1st month of year
                        $date_pieces[2] = '1'; // assumed 1st day of month
                        break;
                }
            }
            if ($countpieces == 2) { // assume only month and year entered
                switch ($dateorder) {
                    case 'dmy': // most countries
                        $date_pieces[2] = $date_pieces[1]; //year
                        $date_pieces[1] = $date_pieces[0]; // month
                        $date_pieces[0] = '1'; // assumed 1st day of month
                        break;
                    case 'mdy': // USA
                        $date_pieces[2] = $date_pieces[1]; //year
                        $date_pieces[0] = $date_pieces[0]; // month
                        $date_pieces[1] = '1'; // assumed 1st day of month
                        break;
                    case 'ymd': // ISO 8601 standard
                        $date_pieces[2] = '1'; // assumed 1st day of month
                        break;
                }
            }
            if (count($date_pieces) > 1) {
                if ($matches[1] == '%m') $month = $date_pieces[0];
                if ($matches[1] == '%d') $day = $date_pieces[0];
                if ($matches[1] == '%y') $year = strftime('%C').$date_pieces[0];
                if ($matches[1] == '%Y') $year = $date_pieces[0];

                if ($matches[3] == '%m') $month = $date_pieces[1];
                if ($matches[3] == '%d') $day = $date_pieces[1];
                if ($matches[3] == '%y') $year = strftime('%C').$date_pieces[1];
                if ($matches[3] == '%Y') $year = $date_pieces[1];

                if ($matches[5] == '%m') $month = $date_pieces[2];
                if ($matches[5] == '%d') $day = $date_pieces[2];
                if ($matches[5] == '%y') $year = strftime('%C').$date_pieces[2];
                if ($matches[5] == '%Y') $year = $date_pieces[2];

                $month = min(12,$month);
                $month = max(1,$month);
                if ($month == 2) {
                    $day = min(29, $day);
                } else if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
                    $day = min(30, $day);
                } else {
                    $day = min(31, $day);
                }
                $day = max(1, $day);
                if (!$thisdate = gmmktime(0, 0, 0, $month, $day, $year)) {
                    return 'wrongdaterange';
                } else {
                    if ($insert) {
                        $thisdate = trim(userdate ($thisdate, '%Y-%m-%d', '1', false));
                    } else {
                        $thisdate = trim(userdate ($thisdate, $dateformat, '1', false));
                    }
                }
                return $thisdate;
            }
        } else return ('wrongdateformat');
    }
    // .mform span.required .mform div.error
    // a variant of Moodle's notify function, with a different formatting
    function questionnaire_notify($message) {
        $message = clean_text($message);
        $errorstart = '<div class="message">';
        $errorend = '</div>';
        $output = $errorstart.$message.$errorend;
        echo $output;
    }

    /// deprecated
    function questionnaire_preview ($questionnaire) {
        global $DB;
        /// Print the page header
        /// Templates may not have questionnaires yet...
        $tempsid = $questionnaire->survey->id; // this is needed for Preview cases later on

        if (!isset($questionnaire->name)) {
            $name = $DB->get_field('questionnaire_survey', 'name', array('id' => $tempsid));
            $questionnaire->sid = $tempsid;
            $questionnaire->add_questions($tempsid);
        } else {
            $name = $questionnaire->name;
        }
        $qp = get_string('preview_questionnaire', 'questionnaire');
        $pq = get_string('previewing', 'questionnaire');
        $course = $questionnaire->course;
        print_header($course->shortname.$qp,
                     $course->fullname.$pq.$name, '', '', '', false);
    /// Print the main part of the page
        $SESSION->questionnaire_survey_id = $tempsid;
        if (isset($formdata->sid) && $formdata->sid != 0) {
            $sid = $SESSION->questionnaire_survey_id = $formdata->sid;
        } else {
            $sid = $SESSION->questionnaire_survey_id;
        }
        $questionnaire->survey = $DB->get_record('questionnaire_survey', array('id' => $sid));
        $n = $DB->count_records('questionnaire_question', array('survey_id' => $sid, 'type_id' => '99', 'deleted' => 'n'));
        for ($i=1; $i<$n+2 ; $i++) {
            $questionnaire->survey_render($i, '', $formdata);
        }
        close_window_button();
        echo '</div></div></body></html>';
        break;
    }

    function choice_values($content) {

        /// If we run the content through format_text first, any filters we want to use (e.g. multilanguage) should work.
        // examines the content of a possible answer from radio button, check boxes or rate question
        // returns ->text to be displayed, ->image if present, ->modname name of modality, image ->title
        $contents = '';
        $contents->text = '';
        $contents->image = '';
        $contents->modname = '';
        $contents->title = '';
        // has image
        if ($count = preg_match('/(<img)\s .*(src="(.[^"]{1,})")/isxmU',$content,$matches)) {
            $contents->image = $matches[0];
            $imageurl = $matches[3];
            // image has a title or alt text: use one of them
            if (preg_match('/(title=.)([^"]{1,})/',$content,$matches)
                 || preg_match('/(alt=.)([^"]{1,})/',$content,$matches) ) {
                $contents->title = $matches[2];
            } else {
                // image has no title nor alt text: use its filename (without the extension)
                ereg(".*\/(.*)\..*$", $imageurl, $matches);
                $contents->title = $matches[1];
            }
            // content has text or named modality plus an image
            if (preg_match('/(.*)(<img.*)/',$content,$matches)) {
                $content = $matches[1];
            } else {
                // just an image
                return $contents;
            }
        }
        // look for named modalities
        $contents->text = $content;
        if ($pos = strpos($content, '=')) {
            // the equal sign used for named modalities must NOT be followed by a double quote
            // because an equal sign followed by double quote might introduce e.g. a lang tag
            if (substr($content, $pos + 1, 1) != '"') {
                $contents->text = substr($content, $pos + 1);
                $contents->modname =substr($content, 0, $pos);
            }
         }
        return $contents;
    }
