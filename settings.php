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
 * Setting page for questionaire module
 *
 * @package    mod_questionnaire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $options = array(0 => get_string('no'), 1 => get_string('yes'));
    $str = get_string('configusergraphlong', 'questionnaire');
    $settings->add(new admin_setting_configselect('questionnaire/usergraph',
                                    get_string('configusergraph', 'questionnaire'),
                                    $str, 0, $options));
    $settings->add(new admin_setting_configtext('questionnaire/maxsections',
                                    get_string('configmaxsections', 'questionnaire'),
                                    '', 10, PARAM_INT));
    $choices = array(
        'response' => get_string('response', 'questionnaire'),
        'submitted' => get_string('submitted', 'questionnaire'),
        'institution' => get_string('institution'),
        'department' => get_string('department'),
        'course' => get_string('course'),
        'group' => get_string('group'),
        'id' => get_string('id', 'questionnaire'),
        'fullname' => get_string('fullname'),
        'username' => get_string('username')
    );

    $settings->add(new admin_setting_configmultiselect('questionnaire/downloadoptions',
            get_string('textdownloadoptions', 'questionnaire'), '', array_keys($choices), $choices));

    $settings->add(new admin_setting_configcheckbox('questionnaire/allowemailreporting',
        get_string('configemailreporting', 'questionnaire'), get_string('configemailreportinglong', 'questionnaire'), 0));

    $questionnairerespondents = array (
            'fullname' => get_string('respondenttypefullname', 'questionnaire'),
            'anonymous' => get_string('respondenttypeanonymous', 'questionnaire'));

    $respondenttypesetting = new admin_setting_configselect('questionnaire/respondenttype',
            get_string('default').': '.get_string('respondenttype', 'questionnaire'),
            get_string('respondenttype_help', 'questionnaire'), 'anonymous', $questionnairerespondents);
    $respondenttypesetting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($respondenttypesetting);

    $questionnaireresponseviewers = array (
            1 => get_string('responseviewstudentswhenanswered', 'questionnaire'),
            2 => get_string('responseviewstudentswhenclosed', 'questionnaire'),
            3 => get_string('responseviewstudentsalways', 'questionnaire'),
            0 => get_string('responseviewstudentsnever', 'questionnaire'));

    $respviewsetting = new admin_setting_configselect('questionnaire/resp_view',
            get_string('default').': '.get_string('responseview', 'questionnaire'),
            get_string('responseview_help', 'questionnaire'), 0, $questionnaireresponseviewers);
    $respviewsetting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($respviewsetting);
}
