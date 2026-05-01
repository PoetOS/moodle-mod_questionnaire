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

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Provided the main API functions for questionnaire.
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire {
    // Class Properties.

    // Properties sourced from the questionnaire DB record.

    /** @var int $id The activity instance id. */
    public $id = 0;

    /** @var int $course The course id this questionnaire belongs to. */
    public $course = 0;

    /** @var string $name The questionnaire name. */
    public $name = '';

    /** @var string $intro The introductory text. */
    public $intro = '';

    /** @var int $introformat The format of the intro field. */
    public $introformat = FORMAT_HTML;

    /** @var int $qtype The questionnaire type. */
    public $qtype = 0;

    /** @var string $respondenttype The respondent type (fullname, anonymous, etc). */
    public $respondenttype = 'fullname';

    /** @var string $respeligible Who is eligible to respond. */
    public $respeligible = 'all';

    /** @var int $respview Who can view responses. */
    public $respview = 0;

    /** @var int $notifications Notification setting. */
    public $notifications = 0;

    /** @var int $opendate The date the questionnaire opens (unix timestamp, 0 = always open). */
    public $opendate = 0;

    /** @var int $closedate The date the questionnaire closes (unix timestamp, 0 = never closes). */
    public $closedate = 0;

    /** @var int $resume Whether resuming a response is allowed. */
    public $resume = 0;

    /** @var int $navigate Whether navigation is enabled. */
    public $navigate = 0;

    /** @var int $grade The maximum grade. */
    public $grade = 0;

    /** @var int $sid The survey id this questionnaire uses. */
    public $sid = 0;

    /** @var int $timemodified Unix timestamp of last modification. */
    public $timemodified = 0;

    /** @var int $completionsubmit Whether submitting counts as completion. */
    public $completionsubmit = 0;

    /** @var int $autonum Auto-numbering setting. */
    public $autonum = 3;

    /** @var int $progressbar Whether to show a progress bar. */
    public $progressbar = 0;

    /** @var int $removeafter Days after which to remove responses. */
    public $removeafter = 0;

    /** @var int $cmid The course module id; added by Moodle's generator framework. */
    public $cmid = 0;

    /** @var string $cmidnumber The course module id number; may be set externally. */
    public $cmidnumber = '';

    /** @var int $courseid The course id; may be set externally. */
    public $courseid = 0;

    // Properties set explicitly in the constructor.

    /** @var \stdClass|null $survey The survey record loaded from questionnaire_survey. */
    public $survey = null;

    /** @var \stdClass|\course_modinfo $cm The course module record or info object. */
    public $cm = null;

    /** @var \context_module|null $context The module context. */
    public $context = null;

    /** @var \stdClass $capabilities The capabilities object for the current user. */
    public $capabilities = null;

    /** @var array $questionsbysec Questions grouped by section (page). */
    public $questionsbysec = [];

    /**
     * @var \mod_questionnaire\local\question\question[] $questions
     */
    public $questions = [];

    /** @var \mod_questionnaire\local\response\questionnaire_responses|null $responses Lazy-initialised response handler. */
    protected $responses = null;

    /** @var int|null $rid The id of the most recently inserted/committed response. */
    public $rid = null;

    /** @var mixed $usehtmleditor Whether to use the HTML editor; set to null before survey display. */
    public $usehtmleditor = null;

    /**
     * @var $renderer Contains the page renderer when loaded, or false if not.
     */
    public $renderer = false;

    /**
     * @var $page Contains the renderable, templatable page when loaded, or false if not.
     */
    public $page = false;

    /** @var string Localised plural module name set by page scripts (e.g. complete.php). */
    public $strquestionnaires = '';

    /** @var string Localised singular module name set by page scripts (e.g. complete.php). */
    public $strquestionnaire = '';

    /** @var bool Whether the current user can view all groups; set by report.php / myreport.php. */
    public $canviewallgroups = false;

    // Class Methods.


    /**
     * The constructor.
     * @param stdClass $course
     * @param cm_info $cm
     * @param int $id
     * @param null|stdClass $questionnaire
     * @param bool $addquestions
     * @throws dml_exception
     */
    public function __construct(&$course, &$cm, $id = 0, $questionnaire = null, $addquestions = true) {
        global $DB;

        if ($id) {
            $questionnaire = $DB->get_record('questionnaire', ['id' => $id]);
        }

        if (is_object($questionnaire)) {
            $properties = get_object_vars($questionnaire);
            foreach ($properties as $property => $value) {
                if (property_exists($this, $property)) {
                    $this->$property = $value;
                }
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
            $this->capabilities = $this->load_capabilities();
        }
    }

    /**
     * Display a survey suitable for printing.
     * @param int $courseid
     * @param string $message
     * @param string $referer
     * @param int $rid
     * @param bool $blankquestionnaire If we are printing a blank questionnaire.
     * @return false|void
     */
    public function survey_print_render($courseid, $message = '', $referer = '', $rid = 0, $blankquestionnaire = false) {
        global $DB, $CFG;

        if (! $course = $DB->get_record("course", ["id" => $courseid])) {
            throw new \moodle_exception('incorrectcourseid', 'mod_questionnaire');
        }

        $this->course = $course;

        if (!empty($rid)) {
            // If we're viewing a response, use this method.
            $this->view_response($rid, $referer);
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

        $action = $CFG->wwwroot . '/mod/questionnaire/preview.php?id=' . $this->cm->id;
        $this->page->add_to_page(
            'formstart',
            $this->renderer->complete_formstart($action)
        );
        // Print all sections.
        $formdata = new stdClass();
        $errors = 1;
        if (data_submitted()) {
            $formdata = data_submitted();
            $formdata->rid = $formdata->rid ?? 0;
            $this->add_response_from_formdata($formdata);
            $pageerror = '';
            $s = 1;
            $errors = 0;
            foreach ($this->questionsbysec as $section) {
                $errormessage = $this->response_check_format($s, $formdata);
                if ($errormessage) {
                    if ($numsections > 1) {
                        $pageerror = get_string('page', 'questionnaire') . ' ' . $s . ' : ';
                    }
                    $this->page->add_to_page(
                        'notifications',
                        $this->renderer->notification($pageerror . $errormessage, \core\output\notification::NOTIFY_ERROR)
                    );
                    $errors++;
                }
                $s++;
            }
        }

        $this->print_survey_start($message, 1, 1, $hasrequired, '');

        if (($referer == 'preview') && $this->has_dependencies()) {
            $allqdependants = $this->get_dependants_and_choices();
        } else {
            $allqdependants = [];
        }
        if ($errors == 0) {
            $this->page->add_to_page(
                'message',
                $this->renderer->notification(
                    get_string('submitpreviewcorrect', 'questionnaire'),
                    \core\output\notification::NOTIFY_SUCCESS
                )
            );
        }

        $page = 1;
        foreach ($this->questionsbysec as $section) {
            $output = '';
            if ($numsections > 1) {
                $output .= $this->renderer->print_preview_pagenumber(get_string('page', 'questionnaire') . ' ' . $page);
                $page++;
            }
            foreach ($section as $questionid) {
                if (!$this->questions[$questionid]->is_numbered()) {
                    $i--;
                }
                if (isset($allqdependants[$questionid])) {
                    $dependants = $allqdependants[$questionid];
                } else {
                    $dependants = [];
                }
                $this->questions[$questionid]->set_isprint($referer === 'print');
                $output .= $this->renderer->question_output(
                    $this->questions[$questionid],
                    ($this->responses()->get_response(0) ?? new \mod_questionnaire\local\response\response()),
                    $i++,
                    null,
                    $dependants,
                    $this
                );
                $this->page->add_to_page('questions', $output);
                $output = '';
            }
        }
        // End of questions.
        if ($referer == 'preview' && !$blankquestionnaire) {
            $url = $CFG->wwwroot . '/mod/questionnaire/preview.php?id=' . $this->cm->id;
            $this->page->add_to_page(
                'formend',
                $this->renderer->print_preview_formend(
                    $url,
                    get_string('submitpreview', 'questionnaire'),
                    get_string('reset')
                )
            );
        }
        return;
    }
    /**
     * Adding questions to the object.
     * @param bool $sid
     */
    private function add_questions($sid = 0) {
        if ($sid === 0) {
            $sid = $this->sid;
        }

        if (!isset($this->questions)) {
            $this->questions = [];
            $this->questionsbysec = [];
        }

        $records = \mod_questionnaire\local\db\question_record::get_active_for_survey($sid);
        if ($records) {
            $sec = 1;
            $isbreak = false;
            foreach ($records as $rec) {
                $typeid = $rec->get('typeid');
                $qid = $rec->get('id');
                $this->questions[$qid] = \mod_questionnaire\local\question\question::question_builder(
                    $typeid,
                    $rec->to_record(),
                    $this->context
                );

                if ($typeid != QUESPAGEBREAK) {
                    $this->questionsbysec[$sec][] = $qid;
                    $isbreak = false;
                } else {
                    // Sanity check: no section break allowed as first position, no 2 consecutive section breaks.
                    if ($rec->get('position') != 1 && $isbreak == false) {
                        $sec++;
                        $isbreak = true;
                    }
                }
            }
        }
    }

    /**
     * Return the response handler for this questionnaire instance (lazy-initialised).
     * @return \mod_questionnaire\local\response\questionnaire_responses
     */
    private function responses(): \mod_questionnaire\local\response\questionnaire_responses {
        if (!isset($this->responses)) {
            $this->responses = new \mod_questionnaire\local\response\questionnaire_responses(
                \mod_questionnaire\questionnaire::from_instanceid($this->id)
            );
        }
        return $this->responses;
    }

    /**
     * Return true if questions should be automatically numbered.
     * @return bool
     */
    public function questions_autonumbered() {
        // Value of 1 if questions should be numbered. Value of 3 if both questions and pages should be numbered.
        return (!empty($this->autonum) && (($this->autonum == 1) || ($this->autonum == 3)));
    }

    /**
     * Check if current questionnaire has dependencies set and any question has dependencies.
     *
     * @return boolean Whether dependencies are set or not.
     */
    private function has_dependencies() {
        $hasdependencies = false;
        if (($this->navigate > 0) && isset($this->questions) && !empty($this->questions)) {
            foreach ($this->questions as $question) {
                if ($question->has_dependencies()) {
                    $hasdependencies = true;
                    break;
                }
            }
        }
        return $hasdependencies;
    }

    /**
     * Load needed parent question information into the dependencies structure for the requested question.
     * @param \mod_questionnaire\local\question\question $question
     * @return bool
     */
    public function load_parents($question) {
        foreach ($question->dependencies as $did => $dependency) {
            $dependquestion = $this->questions[$dependency->dependquestionid];
            $qdependchoice = '';
            switch ($dependquestion->typeid()) {
                case QUESRADIO:
                case QUESDROP:
                case QUESCHECK:
                    $qdependchoice = $dependency->dependchoiceid;
                    $dependchoice = $dependquestion->choices[$dependency->dependchoiceid]->content;

                    $contents = \mod_questionnaire\local\question\question::parse_choice_content($dependchoice);
                    if ($contents->modname) {
                        $dependchoice = $contents->modname;
                    }
                    break;
                case QUESYESNO:
                    switch ($dependency->dependchoiceid) {
                        case 0:
                            $dependchoice = get_string('yes');
                            $qdependchoice = 'y';
                            break;
                        case 1:
                            $dependchoice = get_string('no');
                            $qdependchoice = 'n';
                            break;
                    }
                    break;
            }
            // Qdependquestion, parenttype and qdependchoice fields to be used in preview mode.
            $question->dependencies[$did]->qdependquestion = 'q' . $dependquestion->id();
            $question->dependencies[$did]->qdependchoice = $qdependchoice;
            $question->dependencies[$did]->parenttype = $dependquestion->typeid();
            // Other fields to be used in Questions edit mode.
            $question->dependencies[$did]->position = $question->position();
            $question->dependencies[$did]->name = $question->name();
            $question->dependencies[$did]->content = $question->content();
            $question->dependencies[$did]->parentposition = $dependquestion->position();
            $question->dependencies[$did]->parent = format_string($dependquestion->name()) . '->' . format_string($dependchoice);
        }
        return true;
    }

    /**
     * Adding a survey record to the object.
     * @param int $sid
     * @param null $survey
     */
    private function add_survey($sid = 0, $survey = null) {
        global $DB;

        if ($sid) {
            $this->survey = $DB->get_record('questionnaire_survey', ['id' => $sid]);
        } else if (is_object($survey)) {
            $this->survey = clone($survey);
        }
    }

    /**
     * Load the response information from a submitted web form.
     *
     * @param stdClass $formdata
     */
    private function add_response_from_formdata(stdClass $formdata) {
        $this->responses()->add_response_from_formdata($formdata);
    }

    /**
     * Return the questionnaire instance ID.
     *
     * @return int
     */
    private function id() {
        return $this->id;
    }

    /**
     * True if any of the questions are required.
     * @param int $section
     * @return bool
     */
    private function has_required($section = 0) {
        if (empty($this->questions)) {
            return false;
        } else if ($section <= 0) {
            foreach ($this->questions as $question) {
                if ($question->required()) {
                    return true;
                }
            }
        } else if (key_exists($section, $this->questionsbysec)) {
            foreach ($this->questionsbysec[$section] as $questionid) {
                if ($this->questions[$questionid]->required()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get all descendants and choices for questions with descendants.
     * @return array
     */
    private function get_dependants_and_choices() {
        $questions = array_reverse($this->questions, true);
        $parents = [];
        foreach ($questions as $question) {
            foreach ($question->dependencies as $dependency) {
                $child = new stdClass();
                $child->choiceid = $dependency->dependchoiceid;
                $child->logic = $dependency->dependlogic;
                $child->andor = $dependency->dependandor;
                $parents[$dependency->dependquestionid][$question->id()][] = $child;
            }
        }
        return($parents);
    }

    /**
     * Print the start of the survey page.
     * @param string $message
     * @param int $section
     * @param int $numsections
     * @param bool $hasrequired
     * @param string $rid
     * @param bool $blankquestionnaire
     * @param string $outputtarget
     */
    private function print_survey_start(
        $message,
        $section,
        $numsections,
        $hasrequired,
        $rid = '',
        $blankquestionnaire = false,
        $outputtarget = 'html'
    ) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        $userid = '';
        $resp = '';
        $groupname = '';
        $currentgroupid = 0;
        $timesubmitted = '';
        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($rid) {
            $courseid = $this->course->id;
            if ($resp = $DB->get_record('questionnaire_response', ['id' => $rid])) {
                if ($this->respondenttype == 'fullname') {
                    $userid = $resp->userid;
                    // Display name of group(s) that student belongs to... if questionnaire is set to Groups separate or visible.
                    if (groups_get_activity_groupmode($this->cm, $this->course)) {
                        if ($groups = groups_get_all_groups($courseid, $resp->userid)) {
                            if (count($groups) == 1) {
                                $group = current($groups);
                                $currentgroupid = $group->id;
                                $groupname = ' (' . get_string('group') . ': ' . $group->name . ')';
                            } else {
                                $groupname = ' (' . get_string('groups') . ': ';
                                foreach ($groups as $group) {
                                    $groupname .= $group->name . ', ';
                                }
                                $groupname = substr($groupname, 0, strlen($groupname) - 2) . ')';
                            }
                        } else {
                            $groupname = ' (' . get_string('groupnonmembers') . ')';
                        }
                    }

                    $params = [
                        'objectid' => $this->survey->id,
                        'context' => $this->context,
                        'courseid' => $this->course->id,
                        'relateduserid' => $userid,
                        'other' => ['action' => 'vresp', 'currentgroupid' => $currentgroupid, 'rid' => $rid],
                    ];
                    $event = \mod_questionnaire\event\response_viewed::create($params);
                    $event->trigger();
                }
            }
        }
        $ruser = '';
        if ($resp && !$blankquestionnaire) {
            if ($userid) {
                if ($user = $DB->get_record('user', ['id' => $userid])) {
                    $ruser = fullname($user);
                }
            }
            if ($this->respondenttype == 'anonymous') {
                $ruser = '- ' . get_string('anonymous', 'questionnaire') . ' -';
            } else {
                // JR DEV comment following line out if you do NOT want time submitted displayed in Anonymous surveys.
                if ($resp->submitted) {
                    $timesubmitted = '&nbsp;' . get_string('submitted', 'questionnaire') . '&nbsp;' . userdate($resp->submitted);
                }
            }
        }
        if ($ruser) {
            $respinfo = '';
            if ($outputtarget == 'html') {
                // Disable the pdf function for now, until it looks a lot better.
                if (false) {
                    $linkname = get_string('downloadpdf', 'mod_questionnaire');
                    $link = new moodle_url(
                        '/mod/questionnaire/report.php',
                        [
                            'action' => 'vresp',
                            'instance' => $this->id,
                            'target' => 'pdf',
                            'individualresponse' => 1,
                            'rid' => $rid,
                        ]
                    );
                    $downpdficon = new pix_icon('b/pdfdown', $linkname, 'mod_questionnaire');
                    $respinfo .= $this->renderer->action_link($link, null, null, null, $downpdficon);
                }

                $linkname = get_string('print', 'mod_questionnaire');
                $link = new \moodle_url(
                    '/mod/questionnaire/report.php',
                    [
                        'action' => 'vresp',
                        'instance' => $this->id,
                        'target' => 'print',
                        'individualresponse' => 1,
                        'rid' => $rid,
                    ]
                );
                $htmlicon = new pix_icon('t/print', $linkname);
                $options = ['menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
                    'height' => 600, 'width' => 800, 'title' => $linkname];
                $name = 'popup';
                $action = new popup_action('click', $link, $name, $options);
                $respinfo .= $this->renderer->action_link($link, null, $action, ['title' => $linkname], $htmlicon) . '&nbsp;';
            }
            $respinfo .= get_string('respondent', 'questionnaire') . ': <strong>' . $ruser . '</strong>';
            if ($this->survey_is_public()) {
                // For a public questionnaire, look for the course that used it.
                $coursename = '';
                $sql = 'SELECT q.id, q.course, c.fullname ' .
                       'FROM {questionnaire_response} qr ' .
                       'INNER JOIN {questionnaire} q ON qr.questionnaireid = q.id ' .
                       'INNER JOIN {course} c ON q.course = c.id ' .
                       'WHERE qr.id = ? AND qr.complete = ? ';
                if ($record = $DB->get_record_sql($sql, [$rid, 'y'])) {
                    $coursename = $record->fullname;
                }
                $respinfo .= ' ' . get_string('course') . ': ' . $coursename;
            }
            $respinfo .= $groupname;
            $respinfo .= $timesubmitted;
            $this->page->add_to_page('respondentinfo', $this->renderer->respondent_info($respinfo));
        }

        // We don't want to display the print icon in the print popup window itself!
        if ($this->capabilities->printblank && $blankquestionnaire && $section == 1) {
            // Open print friendly as popup window.
            $linkname = '&nbsp;' . get_string('printblank', 'questionnaire');
            $title = get_string('printblanktooltip', 'questionnaire');
            $url = '/mod/questionnaire/print.php?qid=' . $this->id . '&amp;rid=0&amp;' . 'courseid=' .
                $this->course->id . '&amp;sec=1';
            $options = [
                'menubar' => true,
                'location' => false,
                'scrollbars' => true,
                'resizable' => true,
                'height' => 600,
                'width' => 800,
                'title' => $title,
            ];
            $name = 'popup';
            $link = new moodle_url($url);
            $action = new popup_action('click', $link, $name, $options);
            $class = "floatprinticon";
            $this->page->add_to_page(
                'printblank',
                $this->renderer->action_link(
                    $link,
                    $linkname,
                    $action,
                    ['class' => $class, 'title' => $title],
                    new pix_icon('t/print', $title)
                )
            );
        }
        if ($section == 1) {
            if (!empty($this->survey->title)) {
                $this->survey->title = format_string($this->survey->title);
                $this->page->add_to_page('title', $this->survey->title);
            }
            if (!empty($this->survey->subtitle)) {
                $this->survey->subtitle = format_string($this->survey->subtitle);
                $this->page->add_to_page('subtitle', $this->survey->subtitle);
            }
            if ($this->survey->info) {
                $infotext = file_rewrite_pluginfile_urls(
                    $this->survey->info,
                    'pluginfile.php',
                    $this->context->id,
                    'mod_questionnaire',
                    'info',
                    $this->survey->id
                );
                $this->page->add_to_page('addinfo', format_text($infotext, FORMAT_HTML, ['noclean' => true]));
            }
        }

        if ($message) {
            $this->page->add_to_page('message', $this->renderer->notification($message, \core\output\notification::NOTIFY_ERROR));
        }
    }

    /**
     * Check that all questions have been answered in a suitable way.
     * @param int $section
     * @param stdClass $formdata
     * @param bool $checkmissing
     * @param bool $checkwrongformat
     * @return string
     */
    private function response_check_format($section, $formdata, $checkmissing = true, $checkwrongformat = true) {
        return $this->responses()->response_check_format(
            $section,
            $formdata,
            $checkmissing,
            $checkwrongformat,
            $this->questions
        );
    }
}
