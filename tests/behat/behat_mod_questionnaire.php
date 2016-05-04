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
 * Steps definitions related with the database activity.
 *
 * @package    mod_questuionnaire
 * @category   test
 * @copyright  2016 Mike Churchward - The POET Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Behat\Context\Step\When as When,
    Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Gherkin\Node\PyStringNode as PyStringNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException;
;
/**
 * Database-related steps definitions.
 *
 * @package    mod_questionnaire
 * @category   test
 * @copyright  2016 Mike Churchward - The POET Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_questionnaire extends behat_base {

    /**
     *
     * @Given /^I add a questionnaire "([^"]*)" to the course "([^"]*)" and start to enter questions$/
     *
     * @param string $questionnairename
     * @param string $coursename
     * @return Given[]
     *
     */
    public function i_add_a_questionnaire_to_the_course_and_start_to_enter_questions($questionnairename, $coursename) {
        $fielddata = new TableNode();
        $fielddata->addRow('| Name | '.$questionnairename.' |');
        $fielddata->addRow('| Description | Test questionnaire description |');
        return array(
            new Given('I follow "'.$coursename.'"'),
            new Given('I turn editing mode on'),
            new Given('I add a "Questionnaire" to section "1" and I fill the form with:', $fielddata),
            new Given('I follow "'.$questionnairename.'"'),
            new Given('I follow "Add questions"'));
    }

    /**
     * Adds a question to the questionnaire with the provided data.
     *
     * @Given /^I add a "([^"]*)" question and I fill the form with:$/
     *
     * @param string $questiontype The question type by text name to enter.
     * @param TableNode $fielddata
     * @return Given[]
     */
    public function i_add_a_question_and_i_fill_the_form_with($questiontype, TableNode $fielddata) {
        $validtypes = array(
            '----- Page Break -----',
            'Check Boxes',
            'Date',
            'Dropdown Box',
            'Essay Box',
            'Label',
            'Numeric',
            'Radio Buttons',
            'Rate (scale 1..5)',
            'Text Box',
            'Yes/No');

        if (!in_array($questiontype, $validtypes)) {
            throw new ExpectationException('Invalid question type specified.', $this->getSession());
        }

        // We get option choices as CSV strings. If we have this, modify it for use in
        // multiline data.
        $rows = $fielddata->getRows();
        $hashrows = $fielddata->getRowsHash();
        $options = array();
        if (isset($hashrows['Possible answers'])) {
            $options = explode(',', $hashrows['Possible answers']);
            $rownum = -1;
            // Find the row that contained multiline data and add line breaks. Rows are two item arrays where the
            // first is an identifier and the second is the value.
            foreach ($rows as $key => $row) {
                if ($row[0] == 'Possible answers') {
                    $row[1] = str_replace(',', "\n", $row[1]);
                    $rows[$key] = $row;
                    break;
                }
            }
            $fielddata = new TableNode($rows);
        }

        $steps = array(
            new Given('I set the field "id_type_id" to "'.$questiontype.'"'),
            new Given('I press "Add selected question type"'),
            new Given('I set the following fields to these values:', $fielddata),
            new Given('I press "Save changes"'));

        return $steps;
    }
}