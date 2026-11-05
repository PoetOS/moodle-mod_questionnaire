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
 * Unit tests for mod_questionnaire\output\pdf_factory.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_questionnaire
 * @covers     \mod_questionnaire\output\pdf_factory
 */
final class pdf_factory_test extends \advanced_testcase {
    /** @var string Base64 SVG whose image element points at an internal address. */
    private const SVG = 'PHN2Zz48aW1hZ2UgeGxpbms6aHJlZj0iaHR0cDovLzEwLjAuMC4xLyIvPjwvc3ZnPg==';

    /**
     * The factory returns a pdf with one page already added.
     */
    public function test_create(): void {
        $pdf = pdf_factory::create();
        $this->assertInstanceOf(\pdf::class, $pdf);
        $this->assertEquals(1, $pdf->getNumPages());
    }

    /**
     * Only the plugin's own images survive, rebuilt from their checked values; others become a marker.
     *
     * @dataProvider strip_remote_images_provider
     * @param string $img The image tag, with {wwwroot} standing for the site address.
     * @param string $expected The expected replacement, with {wwwroot} standing for the site address.
     */
    public function test_strip_remote_images(string $img, string $expected): void {
        global $CFG;
        $img = str_replace('{wwwroot}', $CFG->wwwroot, $img);
        $expected = str_replace('{wwwroot}', $CFG->wwwroot, $expected);
        $html = pdf_factory::strip_remote_images('<p>Answer ' . $img . ' end</p>');
        $this->assertSame('<p>Answer ' . $expected . ' end</p>', $html);
    }

    /**
     * Image tags and their expected replacements.
     *
     * @return array
     */
    public static function strip_remote_images_provider(): array {
        return [
            'metadata address' => ['<img src="http://169.254.169.254/latest/meta-data/">', '[image]'],
            'remote host with alt' => ['<img alt="A cat" src=\'https://example.com/a.png\' />', '[image: A cat]'],
            'alt is escaped' => ['<img alt="&lt;b&gt;x&lt;/b&gt; &amp; y" src="https://example.com/a.png">',
                '[image: &lt;b&gt;x&lt;/b&gt; &amp; y]'],
            'blank alt' => ['<img alt="  " src="https://example.com/a.png">', '[image]'],
            'unquoted' => ['<img src=http://10.0.0.1/a.png>', '[image]'],
            'no src' => ['<img alt="none">', '[image: none]'],
            'data-src only' => ['<img data-src="data:image/png;base64,AAAA">', '[image]'],
            'look-alike host' => ['<img src="{wwwroot}.evil.com/mod/questionnaire/images/hbar.gif">', '[image]'],
            'other site path' => ['<img src="{wwwroot}/pluginfile.php/1/mod_questionnaire/question/1/a.png">', '[image]'],
            'parent path' => ['<img src="{wwwroot}/mod/questionnaire/images/../../../admin/a.php">', '[image]'],
            'uppercase tag' => ['<IMG SRC="http://127.0.0.1:8080/">', '[image]'],
            'inline data' => ['<img alt="x" src="data:image/png;base64,AAAA">', '[image: x]'],
            'tcpdf inline' => ['<img src="@AAAA">', '[image]'],
            'inline svg data' => ['<img src="data:image/svg+xml;base64,' . self::SVG . '">', '[image]'],
            'tcpdf inline svg' => ['<img src="@' . self::SVG . '">', '[image]'],
            'src look-alike in title' => ['<img title="src={wwwroot}/mod/questionnaire/images/hbar.gif" ' .
                'src="http://169.254.169.254/latest/meta-data/" alt="x">', '[image: x]'],
            'repeated src' => ['<img src="{wwwroot}/mod/questionnaire/images/hbar.gif" src="http://10.0.0.1/">', '[image]'],
            'single-quoted plugin image' => ['<img src=\'{wwwroot}/mod/questionnaire/images/hbar.gif\'>', '[image]'],
            'not an image file' => ['<img src="{wwwroot}/mod/questionnaire/images/a.php">', '[image]'],
            'encoded parent path' => ['<img src="{wwwroot}/mod/questionnaire/images/&#46;&#46;/a.gif">', '[image]'],
            'plugin bar image' => ['<img style="height:9px;" alt="" src="{wwwroot}/mod/questionnaire/images/hbar_l.gif">',
                '<img src="{wwwroot}/mod/questionnaire/images/hbar_l.gif" alt="" style="height:9px;" />'],
            'extra attributes dropped' => ['<img srcset="http://10.0.0.1/a.png" alt="bar" ' .
                'SRC="{wwwroot}/mod/questionnaire/images/hbar.gif">',
                '<img src="{wwwroot}/mod/questionnaire/images/hbar.gif" alt="bar" style="" />'],
        ];
    }
}
