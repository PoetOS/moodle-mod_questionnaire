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
 * Unit tests for the question recycle-bin task schedule.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\task;

/**
 * Unit tests for the default schedule of mod_questionnaire\task\cron_task.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\task\cron_task
 */
final class cron_task_test extends \advanced_testcase {
    /**
     * The recycle-bin task runs once a day at a random time, not every minute.
     */
    public function test_default_schedule_is_daily(): void {
        $tasks = \core\task\manager::load_default_scheduled_tasks_for_component('mod_questionnaire');
        $task = null;
        foreach ($tasks as $candidate) {
            if ($candidate instanceof cron_task) {
                $task = $candidate;
            }
        }

        $this->assertNotNull($task);
        $this->assertMatchesRegularExpression('/^\d+$/', $task->get_minute());
        $this->assertMatchesRegularExpression('/^\d+$/', $task->get_hour());
        $this->assertSame('*', $task->get_day());
        $this->assertSame('*', $task->get_day_of_week());
    }
}
