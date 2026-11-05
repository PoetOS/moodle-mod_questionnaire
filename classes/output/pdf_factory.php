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
     * reader knows something was there.
     *
     * @param string $html
     * @return string
     */
    public static function strip_remote_images(string $html): string {
        global $CFG;
        $allowed = $CFG->wwwroot . '/mod/questionnaire/images/';

        return preg_replace_callback('~<img\b[^>]*>~i', function (array $tag) use ($allowed): string {
            $src = trim(self::attribute_value($tag[0], 'src'));
            if (str_starts_with($src, $allowed) && !str_contains($src, '..')) {
                return $tag[0];
            }
            $alt = trim(self::attribute_value($tag[0], 'alt'));
            return ($alt === '') ? get_string('imageremoved', 'questionnaire') :
                s(get_string('imageremovedalt', 'questionnaire', $alt));
        }, $html);
    }

    /**
     * Return the decoded value of an attribute in an HTML tag, or an empty string if it is missing.
     *
     * @param string $tag
     * @param string $name
     * @return string
     */
    private static function attribute_value(string $tag, string $name): string {
        $pattern = '~(?<![\w-])' . preg_quote($name, '~') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]*))~i';
        if (!preg_match($pattern, $tag, $match)) {
            return '';
        }
        return html_entity_decode($match[1] . ($match[2] ?? '') . ($match[3] ?? ''), ENT_QUOTES | ENT_HTML5);
    }
}
