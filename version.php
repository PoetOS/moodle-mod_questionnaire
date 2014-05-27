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
 * scorm version information.
 *
 * @package    mod
 * @subpackage questionnaire
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$plugin->version  = 2014041800;  // The current module version (Date: YYYYMMDDXX)
$plugin->requires = 2013111800; // Moodle version.

$plugin->component = 'mod_questionnaire';
$plugin->cron     = 60 * 60 * 12;    // Period for cron to check this module (secs).

$plugin->release  = '2.6.4 (Build - 2014041800)';
$plugin->maturity  = MATURITY_STABLE;
