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

namespace mod_questionnaire;

use context;

/**
 * Static helpers that return option arrays and editor configs for questionnaire forms.
 *
 * Pure presentation helpers — no questionnaire instance state required.
 *
 * @package mod_questionnaire
 * @copyright 2026 onward Mike Churchward (mike.churchward@poetopensource.org)
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class form_options {
    /**
     * Return the JS module config used by Moodle's $PAGE->requires->js_init_call().
     *
     * @return array
     */
    public static function js_module(): array {
        return [
            'name'     => 'mod_questionnaire',
            'fullpath' => '/mod/questionnaire/module.js',
            'requires' => ['base', 'dom', 'event-delegate', 'event-key',
                'core_question_engine', 'moodle-core-formchangechecker'],
            'strings'  => [
                ['cancel', 'moodle'],
                ['flagged', 'question'],
                ['functiondisabledbysecuremode', 'quiz'],
                ['startattempt', 'quiz'],
                ['timesup', 'quiz'],
                ['changesmadereallygoaway', 'moodle'],
                ['leftpart', 'questionnaire'],
                ['leftpartdefault', 'questionnaire'],
                ['middlepart', 'questionnaire'],
                ['middlepartdefault', 'questionnaire'],
                ['middlepartwithtwovalues', 'questionnaire'],
                ['middlepartwithtwovaluesdefault', 'questionnaire'],
                ['rightpart', 'questionnaire'],
                ['rightpartdefault', 'questionnaire'],
                ['where', 'questionnaire'],
            ],
        ];
    }

    /**
     * Return a localised value => label map of response-frequency options for form selects.
     *
     * @return array
     */
    public static function response_frequency(): array {
        return [
            questionnaire::QTYPE_UNLIMITED => get_string('qtypeunlimited', 'questionnaire'),
            questionnaire::QTYPE_ONCE      => get_string('qtypeonce', 'questionnaire'),
            questionnaire::QTYPE_DAILY     => get_string('qtypedaily', 'questionnaire'),
            questionnaire::QTYPE_WEEKLY    => get_string('qtypeweekly', 'questionnaire'),
            questionnaire::QTYPE_MONTHLY   => get_string('qtypemonthly', 'questionnaire'),
        ];
    }

    /**
     * Return a localised value => label map of student-response-viewer options for form selects.
     *
     * @return array
     */
    public static function response_viewer(): array {
        return [
            questionnaire::RESPVIEW_WHENANSWERED => get_string('responseviewstudentswhenanswered', 'questionnaire'),
            questionnaire::RESPVIEW_WHENCLOSED   => get_string('responseviewstudentswhenclosed', 'questionnaire'),
            questionnaire::RESPVIEW_ALWAYS       => get_string('responseviewstudentsalways', 'questionnaire'),
            questionnaire::RESPVIEW_NEVER        => get_string('responseviewstudentsnever', 'questionnaire'),
        ];
    }

    /**
     * Return a localised value => label map of respondent-type options for form selects.
     *
     * @return array
     */
    public static function respondent_type(): array {
        return [
            'fullname'  => get_string('respondenttypefullname', 'questionnaire'),
            'anonymous' => get_string('respondenttypeanonymous', 'questionnaire'),
        ];
    }

    /**
     * Return a localised value => label map of survey-realm options for form selects.
     *
     * @return array
     */
    public static function realm(): array {
        return [
            'private'  => get_string('private', 'questionnaire'),
            'public'   => get_string('public', 'questionnaire'),
            'template' => get_string('template', 'questionnaire'),
        ];
    }

    /**
     * Return a localised value => label map of auto-numbering options for form selects.
     *
     * @return array
     */
    public static function auto_numbering(): array {
        return [
            0 => get_string('autonumberno', 'questionnaire'),
            1 => get_string('autonumberquestions', 'questionnaire'),
            2 => get_string('autonumberpages', 'questionnaire'),
            3 => get_string('autonumberpagesandquestions', 'questionnaire'),
        ];
    }

    /**
     * Return the HTML-editor options array for editors used outside Moodle forms.
     *
     * @param context $context The module context.
     * @return array
     */
    public static function editor(context $context): array {
        return [
            'subdirs'   => 0,
            'maxbytes'  => 0,
            'maxfiles'  => -1,
            'context'   => $context,
            'noclean'   => 0,
            'trusttext' => 0,
        ];
    }

    /**
     * Return a localised value => label map of response-removal duration options for admin form selects.
     *
     * @return array Keys are seconds (0 = never).
     */
    public static function response_removal(): array {
        $options = [0 => get_string('removeoldresponsesdefault', 'questionnaire')];
        for ($i = 1; $i <= 36; $i++) {
            $options[$i * 2592000] = $i > 1
                ? get_string('nummonths', 'moodle', $i)
                : get_string('onemonth', 'questionnaire');
        }
        return $options;
    }
}
