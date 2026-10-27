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

namespace mod_questionnaire\output;

use core\output\named_templatable;
use renderable;
use renderer_base;
use stdClass;

/**
 * A page whose builders add template data piece by piece before it is rendered.
 *
 * Each factory names the page's template and the elements that collect a list; any other element holds one value,
 * and strings added to it again are appended.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page implements named_templatable, renderable {
    /** @var stdClass The template data collected so far. */
    protected stdClass $data;

    /**
     * Constructor.
     *
     * @param string $template Template name within mod_questionnaire.
     * @param array $lists Elements that collect a list of entries.
     */
    public function __construct(
        /** @var string Template name within mod_questionnaire. */
        protected readonly string $template,
        /** @var array Elements that collect a list of entries. */
        protected readonly array $lists = [],
    ) {
        $this->data = new stdClass();
    }

    /**
     * The page for completing the questionnaire.
     *
     * @return self
     */
    public static function complete(): self {
        return new self('completepage', ['questions', 'links']);
    }

    /**
     * The preview and print pages.
     *
     * @return self
     */
    public static function preview(): self {
        return new self('previewpage', ['questions']);
    }

    /**
     * The response report pages.
     *
     * @return self
     */
    public static function report(): self {
        return new self('reportpage', ['responses', 'questionresults']);
    }

    /**
     * The PDF version of the results summary.
     *
     * @return self
     */
    public static function report_pdf(): self {
        return new self('reportpagepdf', ['responses', 'questionresults']);
    }

    /**
     * The PDF version of an individual response.
     *
     * @return self
     */
    public static function response_pdf(): self {
        return new self('responsepagepdf', ['responses']);
    }

    /**
     * The manage-questions page.
     *
     * @return self
     */
    public static function questions(): self {
        return new self('questionspage');
    }

    /**
     * The advanced settings and feedback pages.
     *
     * @return self
     */
    public static function settings(): self {
        return new self('qsettingspage');
    }

    /**
     * Add data for export.
     *
     * @param string $element The index for the data.
     * @param mixed $content The content for the index.
     */
    public function add_to_page(string $element, $content): void {
        if (in_array($element, $this->lists)) {
            $this->data->{$element}[] = $content;
        } else {
            $this->data->{$element} = empty($this->data->{$element}) ? $content : ($this->data->{$element} . $content);
        }
    }

    /**
     * Export the data for template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        return $this->data;
    }

    /**
     * The template that renders this page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_questionnaire/' . $this->template;
    }
}
