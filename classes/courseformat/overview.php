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

namespace mod_questionnaire\courseformat;

use cm_info;
use core\output\pix_icon;
use mod_questionnaire\manager;
use core\activity_dates;
use core\output\action_link;
use core_calendar\output\humandate;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core_courseformat\local\overview\overviewitem;

/**
 * Questionnaire overview integration.
 *
 * @package    mod_questionnaire
 * @copyright  2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \core_courseformat\activityoverviewbase {
    /**
     * @var manager the questionnaire manager.
     */
    private manager $manager;

    /**
     * Constructor.
     *
     * @param cm_info $cm the course module instance.
     * @param \core\output\renderer_helper $rendererhelper the renderer helper.
     */
    public function __construct(
        cm_info $cm,
        /** @var \core\output\renderer_helper $rendererhelper the renderer helper */
        protected readonly \core\output\renderer_helper $rendererhelper,
    ) {
        parent::__construct($cm);
        $this->manager = manager::create_from_coursemodule($cm);
    }

    #[\Override]
    public function get_due_date_overview(): ?overviewitem {
        global $USER;

        $dates = activity_dates::get_dates_for_module($this->cm, $USER->id);
        $closedate = null;
        foreach ($dates as $date) {
            if ($date['dataid'] === 'timeclose') {
                $closedate = $date['timestamp'];
                break;
            }
        }
        if (empty($closedate)) {
            return new overviewitem(
                name: get_string('duedate', 'questionnaire'),
                value: null,
                content: '-',
            );
        }

        $content = humandate::create_from_timestamp($closedate);

        return new overviewitem(
            name: get_string('duedate', 'questionnaire'),
            value: $closedate,
            content: $content,
        );
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        if (!has_capability('mod/questionnaire:viewsingleresponse', $this->context)) {
            return null;
        }

        $currentanswerscount = $this->manager->count_all_users_answered();

        $content = new action_link(
            url: new \moodle_url('/mod/questionnaire/report.php', ['instance' => $this->cm->instance]),
            text: get_string('view', 'core'),
            attributes: ['class' => button::SECONDARY_OUTLINE->classes()],
        );

        return new overviewitem(
            name: get_string('actions'),
            value: get_string('viewallxresponses', 'questionnaire', $currentanswerscount),
            content: $content,
            textalign: text_align::CENTER,
        );
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        return [
            'studentwhoresponded' => $this->get_extra_students_who_responded_overview(),
            'responded' => $this->get_extra_status_for_user(),
        ];
    }

    /**
     * Get the response status overview item.
     *
     * @return overviewitem|null An overview item or null for teachers.
     */
    private function get_extra_status_for_user(): ?overviewitem {
        if (has_capability('mod/questionnaire:viewsingleresponse', $this->cm->context)) {
            return null;
        }

        $status = $this->manager->has_answered();
        $statustext = get_string('notanswered', 'questionnaire');
        if ($status) {
            $statustext = get_string('answered', 'questionnaire');
        }
        $submittedstatuscontent = "-";
        if ($status) {
            $submittedstatuscontent = new pix_icon(
                pix: 'i/checkedcircle',
                alt: $statustext,
                component: 'core',
                attributes: ['class' => 'text-success'],
            );
        }
        return new overviewitem(
            name: get_string('responded', 'questionnaire'),
            value: $status,
            content: $submittedstatuscontent,
            textalign: text_align::CENTER,
        );
    }

    /**
     * Get the count of student who responded.
     *
     * @return overviewitem|null An overview item or null if for students.
     */
    private function get_extra_students_who_responded_overview(): ?overviewitem {
        if (!has_capability('mod/questionnaire:viewsingleresponse', $this->cm->context)) {
            return null;
        }

        if (is_callable([$this, 'get_groups_for_filtering'])) {
            $groupids = array_keys($this->get_groups_for_filtering());
        } else {
            $groupids = [];
        }
        $studentswhoresponded = $this->manager->count_all_users_answered($groupids);

        return new overviewitem(
            name: get_string('studentwhoresponded', 'questionnaire'),
            value: $studentswhoresponded,
            content: $studentswhoresponded,
            textalign: text_align::END,
        );
    }
}
