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

namespace mod_questionnaire\local;

/**
 * Moodle's trusted-text flag for staff-authored HTML: question text and choices, survey info and thank-you text,
 * and feedback.
 *
 * Text saved by a user with moodle/site:trustcontent, on a site with trusted content enabled, is stored with a trust
 * flag. The flag is recorded only: staff-authored text is shown unfiltered, as the capabilities to author it carry
 * RISK_XSS.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trusttext {
    /**
     * The trust flag to store with text the current user saves in the given context.
     *
     * @param \context $context
     * @return int 1 if trusted, 0 otherwise.
     */
    public static function flag(\context $context): int {
        return trusttext_trusted($context) ? 1 : 0;
    }
}
