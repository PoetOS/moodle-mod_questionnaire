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
 * Unit tests for the trusted-text helper.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local;

/**
 * Unit tests for mod_questionnaire\local\trusttext.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\trusttext
 */
final class trusttext_test extends \advanced_testcase {
    /**
     * Text is flagged trusted only when trusted content is enabled and the user may trust content.
     */
    public function test_flag(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $this->setAdminUser();
        $this->assertSame(0, trusttext::flag($context));

        set_config('enabletrusttext', 1);
        $this->assertSame(1, trusttext::flag($context));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(0, trusttext::flag($context));
    }
}
