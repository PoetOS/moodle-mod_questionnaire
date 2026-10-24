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

namespace mod_questionnaire\local\report;

/**
 * The choices posted from the download options form, read by report.php and passed to report_downloader.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class download_options {
    /**
     * Constructor.
     *
     * @param string $dataformat Dataformat name ('csv', 'excel', etc.).
     * @param bool $choicecodes Include choice codes in the export.
     * @param bool $choicetext Include choice text in the export.
     * @param bool $showincompletes Include responses still in progress.
     * @param bool $rankaverages Include rank averages.
     * @param bool $emailreport Email the export instead of downloading it.
     * @param bool $emailroles Email the users notified of submissions.
     * @param string $emailextra Comma-separated extra email addresses.
     */
    public function __construct(
        /** @var string Dataformat name ('csv', 'excel', etc.). */
        public readonly string $dataformat = '',
        /** @var bool Include choice codes in the export. */
        public readonly bool $choicecodes = false,
        /** @var bool Include choice text in the export. */
        public readonly bool $choicetext = false,
        /** @var bool Include responses still in progress. */
        public readonly bool $showincompletes = false,
        /** @var bool Include rank averages. */
        public readonly bool $rankaverages = false,
        /** @var bool Email the export instead of downloading it. */
        public readonly bool $emailreport = false,
        /** @var bool Email the users notified of submissions. */
        public readonly bool $emailroles = false,
        /** @var string Comma-separated extra email addresses. */
        public readonly string $emailextra = '',
    ) {
    }
}
