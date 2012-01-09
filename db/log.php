<?php
/**
 * Definition of log events
 *
 * @package    contrib
 * @subpackage questionnaire
 * @copyright  2010 Remote-Learner.net (http://www.remote-learner.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'questionnaire', 'action'=>'view all', 'mtable'=>'questionnaire', 'field'=>'name'),
    array('module'=>'questionnaire', 'action'=>'submit', 'mtable'=>'questionnaire_attempts', 'field'=>'rid'),
    array('module'=>'questionnaire', 'action'=>'view', 'mtable'=>'questionnaire', 'field'=>'name'),
);