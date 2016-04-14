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
 * Test performance of questionnaire.
 * @author    Guy Thomas
 * @copyright Copyright (c) 2015 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Performance test for questionnaire module.
 * @author     Guy Thomas
 * @copyright Copyright (c) 2015 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class performance_test extends advanced_testcase {

    static $noreset = false;

    public function setUp() {
        global $CFG;

        require_once($CFG->dirroot.'/mod/questionnaire/questiontypes/questiontypes.class.php');
        require_once($CFG->dirroot.'/lib/testing/generator/data_generator.php');
        require_once($CFG->dirroot.'/lib/testing/generator/component_generator_base.php');
        require_once($CFG->dirroot.'/lib/testing/generator/module_generator.php');
    }

    /**
     * Get csv text
     *
     * @param array $rows
     * @return string
     */
    private function get_csv_text(array $rows) {
        $text = '';
        foreach ($rows as $row) {
            $text .= implode("\t", $row);
            $text .= "\r\n";
        }
        return $text;
    }

    public function test_performance() {

        $this->resetAfterTest(!static::$noreset);
        $dg = $this->getDataGenerator();
        $qdg = $dg->get_plugin_generator('mod_questionnaire');
        $qdg->create_and_fully_populate(1, 400, 1, 5);

        $q = 0;
        $questionnaires = $qdg->questionnaires();
        foreach ($questionnaires as $questionnaire) {
            $q ++;
            list ($course, $cm) = get_course_and_cm_from_instance($questionnaire->id, 'questionnaire', $questionnaire->course);
            $questionnaireinst = new questionnaire(0, $questionnaire, $course, $cm);
            $start = microtime(true);
            $oldoutput = $this->get_csv_text($questionnaireinst->generate_csv('', '', 0, 0, 0));
            $end = microtime(true);
            mtrace('Old CSV export for questionnaire  ' . $q . ' of ' . count($questionnaires) . ' - operation took ' . round($end - $start, 2) . ' seconds');
            $start = microtime(true);
            $newoutput = $this->get_csv_text($questionnaireinst->generate_csv_new('', '', 0, 0, 0));
            $this->assertEquals($oldoutput, $newoutput);
            $end = microtime(true);
            mtrace('New CSV export for questionnaire  ' . $q . ' of ' . count($questionnaires) . ' - operation took ' . round($end - $start, 2) . ' seconds');
        }
    }

    public static function tearDownAfterClass() {
        if (!static::$noreset) {
            self::resetAllData();
        }
    }
}
