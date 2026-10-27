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
 * Unit tests for mod_questionnaire\output\page.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

/**
 * Unit tests for mod_questionnaire\output\page.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\output\page
 */
final class page_test extends \advanced_testcase {
    /**
     * Fetch the plugin renderer.
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('mod_questionnaire');
    }

    /**
     * Each factory names its page's template.
     */
    public function test_factories_name_their_templates(): void {
        $expected = [
            'mod_questionnaire/completepage' => page::complete(),
            'mod_questionnaire/previewpage' => page::preview(),
            'mod_questionnaire/reportpage' => page::report(),
            'mod_questionnaire/reportpagepdf' => page::report_pdf(),
            'mod_questionnaire/responsepagepdf' => page::response_pdf(),
            'mod_questionnaire/questionspage' => page::questions(),
            'mod_questionnaire/qsettingspage' => page::settings(),
        ];
        foreach ($expected as $template => $page) {
            $this->assertEquals($template, $page->get_template_name($this->renderer()));
        }
    }

    /**
     * A list element collects each entry; any other element appends strings and keeps its first array.
     */
    public function test_add_to_page(): void {
        $page = page::complete();
        $page->add_to_page('questions', 'Q1');
        $page->add_to_page('questions', 'Q2');
        $page->add_to_page('links', ['url' => 'a', 'label' => 'A']);
        $page->add_to_page('notifications', '<p>One</p>');
        $page->add_to_page('notifications', '<p>Two</p>');
        $page->add_to_page('form', ['action' => 'x']);

        $data = $page->export_for_template($this->renderer());

        $this->assertEquals(['Q1', 'Q2'], $data->questions);
        $this->assertEquals([['url' => 'a', 'label' => 'A']], $data->links);
        $this->assertEquals('<p>One</p><p>Two</p>', $data->notifications);
        $this->assertEquals(['action' => 'x'], $data->form);
    }

    /**
     * An element is a list only on the pages that collect it.
     */
    public function test_lists_belong_to_their_page(): void {
        $page = page::settings();
        $page->add_to_page('questions', 'Q1');
        $page->add_to_page('questions', 'Q2');

        $this->assertEquals('Q1Q2', $page->export_for_template($this->renderer())->questions);

        $page = page::response_pdf();
        $page->add_to_page('responses', 'R1');
        $page->add_to_page('questionresults', 'A');
        $page->add_to_page('questionresults', 'B');

        $data = $page->export_for_template($this->renderer());
        $this->assertEquals(['R1'], $data->responses);
        $this->assertEquals('AB', $data->questionresults);
    }

    /**
     * The renderer renders a page through its named template.
     */
    public function test_renders_through_its_template(): void {
        $page = page::settings();
        $page->add_to_page('formarea', '<div id="settingsform">Settings</div>');

        $html = $this->renderer()->render($page);

        $this->assertStringContainsString('<div id="settingsform">Settings</div>', $html);
    }
}
