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
 * Questionnaire version information.
 *
 * @package mod_questionnaire
 * @author  Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2022042900;  // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2020110900; // Moodle version (3.10).

$plugin->component = 'mod_questionnaire';

$plugin->release  = '3.10.2 (Build - 2022030300)';
$plugin->maturity  = MATURITY_STABLE;
