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
 *
 * @authors Andreas Grabs, Mike Churchward and Joseph Rézeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package    mod
 * @subpackage questionnaire
 *
 */

require_once($CFG->libdir.'/formslib.php');

class questionnaire_choose_group_form extends moodleform {

    public function definition() {
        $this->questionnairedata = new object();
        // This function can not be called, because not all data are available at this time.
        // I use set_form_elements instead.
    }

    // This function sets the data used in set_form_elements().
    // In this form the only value have to set is course.
    // Eg: array('course' => $course).
    public function set_questionnairedata($data) {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $this->questionnairedata->{$key} = $val;
            }
        }
    }

    // Here the elements will be set.
    // This function must be called manually.
    // The advantage is that the data is already set.
    public function set_form_elements() {
        global $SESSION, $DB;
        $mform =& $this->_form;
        $sid = $SESSION->questionnaire_survey_id;
        $elementgroup = array();
        // Hidden elements.
        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'do_show');
        // Visible elements.
        $groupsoptions = array();
        if (isset($this->questionnairedata->currentgroupid)) {
            $currentgroupid = $this->questionnairedata->currentgroupid;
        }
        if (isset($this->questionnairedata->groups)) {
            $canviewallgroups =  $this->questionnairedata->canviewallgroups;
            $groupmode =  $this->questionnairedata->groupmode;
            if ($canviewallgroups) {
                $groupsoptions['-1'] = get_string('allparticipants');
            }
            // Count number of responses in each group.
            $castsql = $DB->sql_cast_char2int('R.username');
            foreach ($this->questionnairedata->groups as $group) {
                $sql = "SELECT R.id, GM.id as groupid
                    FROM {questionnaire_response} R, {groups_members} GM
                    WHERE R.survey_id= ? AND
                          R.complete='y' AND
                          GM.groupid= ? AND " . $castsql . "=GM.userid";
                if (!($resps = $DB->get_records_sql($sql, array($sid, $group->id)))) {
                    $resps = array();
                }
                if (!empty ($resps)) {
                    $respscount = count($resps);
                } else {
                    $respscount = 0;
                }
                $groupsoptions[$group->id] = get_string('group').': '.$group->name.' ('.$respscount.')';
            }
            if ($canviewallgroups) {
                $groupsoptions['-2'] = '---'.get_string('membersofselectedgroup', 'group').' '.get_string('allgroups').'---';
                $groupsoptions['-3'] = '---'.get_string('groupnonmembers').'---';
            }
			if ($groupmode == 2) {
				$groupsoptions['-2'] = '---'.get_string('membersofselectedgroup', 'group').' '.get_string('allgroups').'---';
			}
        }
        $attributes = 'onChange="M.core_formchangechecker.set_form_submitted(); this.form.submit()"';
        $elementgroup[] =& $mform->createElement('select', 'currentgroupid', '', $groupsoptions, $attributes);
        // Buttons.
		$mform->setDefault('currentgroupid', $currentgroupid);
        $mform->addGroup($elementgroup, 'elementgroup', '', array(' '), false);
        $mform->addHelpButton('elementgroup', 'viewallresponses', 'questionnaire');
    }
}