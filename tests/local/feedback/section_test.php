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
 * Unit tests for mod_questionnaire\local\feedback\section.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

/**
 * Unit tests for mod_questionnaire\local\feedback\section.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\section
 */
final class section_test extends \advanced_testcase {
    /**
     * Build a sample feedback-band data object suitable for sectionfeedback::new_sectionfeedback().
     *
     * @param int $sectionid
     * @param float $min
     * @param float $max
     * @return \stdClass
     */
    private function band(int $sectionid, float $min, float $max): \stdClass {
        return (object)[
            'sectionid' => $sectionid,
            'feedbacklabel' => 'L',
            'feedbacktext' => 'T',
            'feedbacktextformat' => FORMAT_HTML,
            'minscore' => $min,
            'maxscore' => $max,
        ];
    }

    /**
     * new_section() inserts a new feedback-section row and returns an instance with section = max + 1.
     */
    public function test_new_section_increments_section_number(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 5001;

        $first = section::new_section($sid, 'First');
        $this->assertGreaterThan(0, $first->id);
        $this->assertSame(1, $first->section);
        $this->assertSame('First', $first->sectionlabel);
        $this->assertTrue($DB->record_exists('questionnaire_fb_sections', ['id' => $first->id]));

        $second = section::new_section($sid, 'Second');
        $this->assertSame(2, $second->section);
    }

    /**
     * new_section() uses a default label when none is supplied.
     */
    public function test_new_section_default_label(): void {
        $this->resetAfterTest();
        $sid = 5002;
        $section = section::new_section($sid);
        $this->assertSame(get_string('feedbackdefaultlabel', 'questionnaire'), $section->sectionlabel);
    }

    /**
     * decode_scorecalculation() returns an empty array for null/empty input.
     */
    public function test_decode_scorecalculation_empty(): void {
        $this->assertSame([], section::decode_scorecalculation(null));
        $this->assertSame([], section::decode_scorecalculation(''));
    }

    /**
     * decode_scorecalculation() unserializes a valid score array.
     */
    public function test_decode_scorecalculation_valid(): void {
        $encoded = serialize([10 => 1, 11 => 2]);
        $this->assertSame([10 => 1, 11 => 2], section::decode_scorecalculation($encoded));
    }

    /**
     * decode_scorecalculation() throws when an entry's score is non-numeric.
     */
    public function test_decode_scorecalculation_invalid(): void {
        $this->expectException(\coding_exception::class);
        section::decode_scorecalculation(serialize([10 => 'not-a-number']));
    }

    /**
     * load_section() reads a section by id and attaches its feedback bands.
     */
    public function test_load_section_by_id(): void {
        $this->resetAfterTest();
        $sid = 5003;
        $created = section::new_section($sid, 'lbl');

        // Add two feedback bands.
        sectionfeedback::new_sectionfeedback($this->band($created->id, 0, 50));
        sectionfeedback::new_sectionfeedback($this->band($created->id, 50, 100));

        $loaded = new section([], ['id' => $created->id]);
        $this->assertSame($created->id, $loaded->id);
        $this->assertSame($sid, $loaded->surveyid);
        $this->assertCount(2, $loaded->sectionfeedback);
    }

    /**
     * load_section() can find by surveyid + section number.
     */
    public function test_load_section_by_surveyid(): void {
        $this->resetAfterTest();
        $sid = 5004;
        section::new_section($sid, 'first');
        $second = section::new_section($sid, 'second');
        sectionfeedback::new_sectionfeedback($this->band($second->id, 0, 100));

        $loaded = new section([], ['surveyid' => $sid, 'sectionnum' => 2]);
        $this->assertSame($second->id, $loaded->id);
        $this->assertSame('second', $loaded->sectionlabel);
    }

    /**
     * load_section() throws when nothing matches the supplied parameters.
     */
    public function test_load_section_missing_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        new section([], ['id' => 99999]);
    }

    /**
     * update() writes current in-memory state back to the row.
     */
    public function test_update_persists_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 5005;
        $section = section::new_section($sid);
        $section->sectionlabel = 'renamed';
        $section->update();

        $row = $DB->get_record('questionnaire_fb_sections', ['id' => $section->id]);
        $this->assertSame('renamed', $row->sectionlabel);
    }

    /**
     * delete_sectionfeedback() removes all feedback bands for the section.
     */
    public function test_delete_sectionfeedback_removes_bands(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 5006;
        $section = section::new_section($sid);
        sectionfeedback::new_sectionfeedback($this->band($section->id, 0, 50));
        sectionfeedback::new_sectionfeedback($this->band($section->id, 50, 100));

        $this->assertEquals(2, $DB->count_records('questionnaire_feedback', ['sectionid' => $section->id]));
        $section->delete_sectionfeedback();
        $this->assertEquals(0, $DB->count_records('questionnaire_feedback', ['sectionid' => $section->id]));
        $this->assertSame([], $section->sectionfeedback);
    }

    /**
     * delete() removes the section row, its feedback bands, and resequences remaining sections.
     */
    public function test_delete_resequences(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 5007;
        $s1 = section::new_section($sid);
        $s2 = section::new_section($sid);
        $s3 = section::new_section($sid);
        sectionfeedback::new_sectionfeedback($this->band($s2->id, 0, 100));

        // Delete the middle section.
        $s2->delete();

        $this->assertFalse($DB->record_exists('questionnaire_fb_sections', ['id' => $s2->id]));
        $this->assertEquals(0, $DB->count_records('questionnaire_feedback', ['sectionid' => $s2->id]));

        $remaining = $DB->get_records('questionnaire_fb_sections', ['surveyid' => $sid], 'section ASC');
        $sections = array_map(fn($r) => (int) $r->section, array_values($remaining));
        $this->assertSame([1, 2], $sections);
    }

    /**
     * load_sectionfeedback() registers a new band on this section and inserts its row.
     */
    public function test_load_sectionfeedback_creates_new_band(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 5008;
        $section = section::new_section($sid);

        $data = (object)[
            'sectionid' => $section->id,
            'feedbacklabel' => 'L',
            'feedbacktext' => 'T',
            'feedbacktextformat' => FORMAT_HTML,
            'minscore' => 10,
            'maxscore' => 60,
        ];
        $newid = $section->load_sectionfeedback($data);

        $this->assertGreaterThan(0, $newid);
        $this->assertArrayHasKey($newid, $section->sectionfeedback);
        $this->assertTrue($DB->record_exists('questionnaire_feedback', ['id' => $newid]));
    }

    /**
     * set_new_scorecalculation() persists the encoded payload and keeps the in-memory state aligned.
     */
    public function test_set_new_scorecalculation_persists(): void {
        global $DB;
        $this->resetAfterTest();
        $sid = 5009;
        $section = section::new_section($sid);

        $section->set_new_scorecalculation([10 => 1, 11 => 2]);

        $stored = $DB->get_field('questionnaire_fb_sections', 'scorecalculation', ['id' => $section->id]);
        $this->assertSame([10 => 1, 11 => 2], unserialize($stored));
        $this->assertSame([10 => 1, 11 => 2], $section->scorecalculation);
    }
}
