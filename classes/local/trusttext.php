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
 * Moodle's trusted-text rules for staff-authored HTML: question text and choices, survey info and thank-you text,
 * and feedback.
 *
 * Text saved by a user with moodle/site:trustcontent, on a site with trusted content enabled, is stored with a trust
 * flag and shown unfiltered. All other text is cleaned when shown.
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

    /**
     * Prepare stored text for an editor: untrusted text, or text the current user may not trust, is cleaned first.
     *
     * @param string|null $text
     * @param int $trust The stored trust flag.
     * @param \context $context
     * @return string
     */
    public static function for_edit(?string $text, int $trust, \context $context): string {
        $text = (string)$text;
        if (!$trust || !trusttext_trusted($context)) {
            $text = clean_text($text, FORMAT_HTML);
        }
        return $text;
    }

    /**
     * Format stored HTML for display, cleaning it unless it is trusted.
     *
     * @param string|null $text
     * @param int $trust The stored trust flag.
     * @param array $options Other format_text() options.
     * @return string
     */
    public static function format(?string $text, int $trust, array $options = []): string {
        unset($options['noclean']);
        $options['trusted'] = (bool)$trust;
        return format_text((string)$text, FORMAT_HTML, $options);
    }
}
