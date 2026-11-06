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

/**
 * Factory for the report-page TCPDF object used by report.php in PDF mode.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_factory {
    /**
     * Return a configured \pdf instance with one landscape page already added.
     *
     * @return \pdf
     */
    public static function create(): \pdf {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $pdf = new \pdf();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Moodle Questionnaire');
        $pdf->SetTitle('All responses');
        $pdf->setPrintHeader(false);
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFillColor(238, 238, 238);
        $pdf->AddPage('L');

        return $pdf;
    }

    /**
     * Remove images that TCPDF would fetch from another server.
     *
     * TCPDF fetches image URLs itself, bypassing the site's curl security settings, and respondent answers can
     * contain images. Inline images are not safe either, since an inline SVG can link to a remote image. Only the
     * plugin's own chart images are kept; any other image is replaced by a marker showing its alt text, so the
     * reader knows something was there. Each tag is read the way TCPDF reads it, and a kept tag is rebuilt from
     * the checked values only, so nothing else in it can point TCPDF somewhere else.
     *
     * @param string $html
     * @return string
     */
    public static function strip_remote_images(string $html): string {
        global $CFG;
        $allowed = '~^' . preg_quote($CFG->wwwroot . '/mod/questionnaire/images/', '~') . '[\w-]+\.(?:gif|png)$~';

        return preg_replace_callback('~<img\b[^>]*>~i', function (array $tag) use ($allowed): string {
            $attributes = self::tcpdf_attributes($tag[0]);
            $src = $attributes['src'] ?? '';
            $alt = $attributes['alt'] ?? '';
            if (preg_match($allowed, $src)) {
                return '<img src="' . $src . '" alt="' . $alt . '" style="' . ($attributes['style'] ?? '') . '" />';
            }
            $alt = trim(html_entity_decode($alt, ENT_QUOTES | ENT_HTML5));
            return ($alt === '') ? get_string('imageremoved', 'questionnaire') :
                s(get_string('imageremovedalt', 'questionnaire', $alt));
        }, $html);
    }

    /**
     * Return a tag's attributes as TCPDF reads them: double-quoted values only, keyed by lowercase name, last one wins.
     *
     * The values are returned raw. They cannot contain a double quote, so they can be put back in a double-quoted
     * attribute as they are.
     *
     * @param string $tag
     * @return array
     */
    private static function tcpdf_attributes(string $tag): array {
        preg_match_all('/([^=\s]*)[\s]*=[\s]*"([^"]*)"/', $tag, $matches, PREG_PATTERN_ORDER);
        $attributes = [];
        foreach ($matches[1] as $index => $name) {
            $attributes[strtolower($name)] = $matches[2][$index];
        }
        return $attributes;
    }
}
