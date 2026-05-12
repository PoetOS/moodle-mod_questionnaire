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
 * Unit tests for mod_questionnaire\local\feedback\sectionfeedback.
 *
 * @package    mod_questionnaire
 * @copyright  2026 Mike Churchward (mike.churchward@poetopensource.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\local\feedback;

/**
 * Unit tests for mod_questionnaire\local\feedback\sectionfeedback.
 *
 * @group mod_questionnaire
 * @covers \mod_questionnaire\local\feedback\sectionfeedback
 */
final class sectionfeedback_test extends \advanced_testcase {
    /**
     * Build a stdClass with the fields new_sectionfeedback expects.
     *
     * @param int $sectionid
     * @param float $minscore
     * @param float $maxscore
     * @return \stdClass
     */
    private function sample_data(int $sectionid, float $minscore = 0.0, float $maxscore = 100.0): \stdClass {
        return (object)[
            'sectionid' => $sectionid,
            'feedbacklabel' => 'label',
            'feedbacktext' => 'text',
            'feedbacktextformat' => FORMAT_HTML,
            'minscore' => $minscore,
            'maxscore' => $maxscore,
        ];
    }

    /**
     * The constructor with id = 0 leaves the object in its empty default state.
     */
    public function test_construct_empty(): void {
        $sf = new sectionfeedback();
        $this->assertSame(0, $sf->id);
        $this->assertSame(0, $sf->sectionid);
    }

    /**
     * Constructing with a stdClass record copies known fields without writing to the DB.
     */
    public function test_construct_with_record(): void {
        global $DB;
        $this->resetAfterTest();
        $countbefore = $DB->count_records('questionnaire_feedback');

        $rec = (object)[
            'id' => 0,
            'sectionid' => 10,
            'feedbacklabel' => 'lbl',
            'feedbacktext' => 'body',
            'feedbacktextformat' => FORMAT_HTML,
            'minscore' => 25.0,
            'maxscore' => 75.0,
        ];
        $sf = new sectionfeedback(0, $rec);

        $this->assertSame(10, $sf->sectionid);
        $this->assertSame('lbl', $sf->feedbacklabel);
        $this->assertSame('body', $sf->feedbacktext);
        $this->assertEquals(25.0, $sf->minscore);
        $this->assertEquals(75.0, $sf->maxscore);
        $this->assertSame($countbefore, $DB->count_records('questionnaire_feedback'));
    }

    /**
     * Constructing with an existing id loads the row from the database.
     */
    public function test_construct_loads_existing_id(): void {
        $this->resetAfterTest();
        $created = sectionfeedback::new_sectionfeedback($this->sample_data(20));

        $loaded = new sectionfeedback($created->id);
        $this->assertEquals($created->id, $loaded->id);
        $this->assertEquals(20, $loaded->sectionid);
        $this->assertSame('label', $loaded->feedbacklabel);
    }

    /**
     * Constructing with a non-existent id throws.
     */
    public function test_construct_unknown_id_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        new sectionfeedback(99999);
    }

    /**
     * new_sectionfeedback() persists a new row and returns an instance with a real id.
     */
    public function test_new_sectionfeedback_persists(): void {
        global $DB;
        $this->resetAfterTest();

        $sf = sectionfeedback::new_sectionfeedback($this->sample_data(30, 10.0, 90.0));

        $this->assertGreaterThan(0, $sf->id);
        $row = $DB->get_record('questionnaire_feedback', ['id' => $sf->id]);
        $this->assertNotEmpty($row);
        $this->assertEquals(30, $row->sectionid);
        $this->assertEquals(10.0, $row->minscore);
        $this->assertEquals(90.0, $row->maxscore);
    }

    /**
     * update() writes current in-memory state back to the row.
     */
    public function test_update_persists_changes(): void {
        global $DB;
        $this->resetAfterTest();

        $sf = sectionfeedback::new_sectionfeedback($this->sample_data(40, 0.0, 100.0));
        $sf->minscore = 33.0;
        $sf->maxscore = 66.0;
        $sf->feedbacktext = 'updated text';
        $sf->update();

        $row = $DB->get_record('questionnaire_feedback', ['id' => $sf->id]);
        $this->assertEquals(33.0, $row->minscore);
        $this->assertEquals(66.0, $row->maxscore);
        $this->assertSame('updated text', $row->feedbacktext);
    }
}
