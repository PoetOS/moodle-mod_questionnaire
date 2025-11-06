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
 * Steps definitions related with the questionnaire activity.
 *
 * @package    mod_questionnaire
 * @category   test
 * @copyright  2016 Mike Churchward - Poet Open Source
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given,
    Behat\Behat\Context\Step\When,
    Behat\Gherkin\Node\TableNode,
    Behat\Gherkin\Node\PyStringNode,
    Behat\Mink\Exception\ExpectationException;

#[\AllowDynamicProperties]
/**
 * Questionnaire-related steps definitions.
 *
 * @package    mod_questionnaire
 * @category   test
 * @copyright  2016 Mike Churchward - Poet Open Source
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_questionnaire extends behat_base {
    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | None so far!      |                                                              |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Admin notification'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            default:
                throw new Exception('Unrecognised quiz page type "' . $page . '."');
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype          | name meaning                                | description                                           |
     * | view              | Questionnaire name                          | The questionnaire info page (view.php)                |
     * | preview           | Questionnaire name                          | The questionnaire preview page (preview.php)          |
     * | questions         | Questionnaire name                          | The questionnaire questions page (questions.php)      |
     * | advsettings       | Questionnaire name                          | The advanced settings page (questions.php)            |
     *
     * @param string $type identifies which type of page this is, e.g. 'preview'.
     * @param string $identifier identifies the particular page, e.g. 'Test questionnaire > preview > Attempt 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url(
                    '/mod/questionnaire/view.php',
                    ['id' => $this->get_cm_by_questionnaire_name($identifier)->id]
                );

            case 'preview':
                return new moodle_url(
                    '/mod/questionnaire/preview.php',
                    ['id' => $this->get_cm_by_questionnaire_name($identifier)->id]
                );

            case 'questions':
                return new moodle_url(
                    '/mod/questionnaire/questions.php',
                    ['id' => $this->get_cm_by_questionnaire_name($identifier)->id]
                );

            case 'advsettings':
                return new moodle_url(
                    '/mod/questionnaire/qsettings.php',
                    ['id' => $this->get_cm_by_questionnaire_name($identifier)->id]
                );

            default:
                throw new Exception('Unrecognised questionnaire page type "' . $type . '."');
        }
    }

    /**
     * Adds a question to the questionnaire with the provided data.
     *
     * @Given /^I add a "([^"]*)" question and I fill the form with:$/
     *
     * @param string $questiontype The question type by text name to enter.
     * @param TableNode $fielddata
     */
    public function i_add_a_question_and_i_fill_the_form_with($questiontype, TableNode $fielddata) {
        $validtypes = [
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
            'Yes/No',
            'Slider',
        ];

        if (!in_array($questiontype, $validtypes)) {
            throw new ExpectationException('Invalid question type specified.', $this->getSession());
        }

        // We get option choices as CSV strings. If we have this, modify it for use in
        // multiline data.
        $rows = $fielddata->getRows();
        $hashrows = $fielddata->getRowsHash();
        if (isset($hashrows['Possible answers'])) {
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
        if (isset($hashrows['Named degrees'])) {
            // Find the row that contained multiline data and add line breaks. Rows are two item arrays where the
            // first is an identifier and the second is the value.
            foreach ($rows as $key => $row) {
                if ($row[0] == 'Named degrees') {
                    $row[1] = str_replace(',', "\n", $row[1]);
                    $rows[$key] = $row;
                    break;
                }
            }
            $fielddata = new TableNode($rows);
        }

        $this->execute('behat_forms::i_set_the_field_to', ['id_type_id', $questiontype]);
        $this->execute('behat_forms::press_button', 'Add selected question type');
        if (isset($hashrows['id_dependquestions_and_1'])) {
            $this->execute('behat_forms::press_button', 'id_adddependencies_and');
        }
        if (isset($hashrows['id_dependquestions_or_1'])) {
            $this->execute('behat_forms::press_button', 'id_adddependencies_or');
        }
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $fielddata);
        $this->execute('behat_forms::press_button', 'Save changes');
    }

    /**
     * Selects a radio button option in the named radio button group.
     *
     * @Given /^I click the "([^"]*)" radio button$/
     *
     * @param int $radioid
     */
    public function i_click_the_radio_button($radioid) {
        $session = $this->getSession();
        $page = $session->getPage();
        $radios = $page->findAll('xpath', '//input[@type="radio" and @id="' . $radioid . '"]');
        $radios[0]->click();
    }

    /**
     * Adds a questions and responses to the questionnaire with the provided name.
     *
     * @Given /^"([^"]*)" has questions and responses$/
     *
     * @param string $questionnairename The name of an existing questionnaire.
     */
    public function has_questions_and_responses($questionnairename) {
        global $DB;

        if (!$questionnaire = $DB->get_record('questionnaire', ['name' => $questionnairename], 'id,sid')) {
            throw new ExpectationException('Invalid questionnaire name specified.', $this->getSession());
        }

        if (!$DB->record_exists('questionnaire_survey', ['id' => $questionnaire->sid])) {
            throw new ExpectationException('Questionnaire survey does not exist.', $this->getSession());
        }

        $this->add_question_data($questionnaire->sid);
        $this->add_response_data($questionnaire->id, $questionnaire->sid);
    }

    /**
     * Adds a question data to the given survey id.
     *
     * @param int $sid The id field of an existing questionnaire_survey record.
     * @return null
     */
    private function add_question_data($sid) {
        $questiondata = [
            [
                "id",
                "surveyid",
                "name",
                "type_id",
                "result_id",
                "length",
                "precise",
                "position",
                "content",
                "required",
                "deleted",
                "dependquestion",
                "dependchoice",
            ],
            ["1", $sid, "own car", "1", null, "0", "0", "1", "<p>Do you own a car?</p>", "y", "n", "0", "0"],
            [
                "2",
                $sid,
                "optional",
                "2",
                null,
                "20",
                "25",
                "3",
                "<p>What is the colour of your car?</p>",
                "y",
                "n",
                "121",
                "0",
            ],
            ["3", $sid, null, "99", null, "0", "0", "2", "break", "n", "n", "0", "0"],
            [
                "4",
                $sid,
                "optional2",
                "1",
                null,
                "0",
                "0",
                "5",
                "<p>Do you sometimes use public transport to go to work?</p>",
                "y",
                "n",
                "0",
                "0",
            ],
            ["5", $sid, null, "99", null, "0", "0", "4", "break", "n", "n", "0", "0"],
            [
                "6",
                $sid,
                "entertext",
                "2",
                null,
                "20",
                "10",
                "6",
                "<p>Enter no more than 10 characters.<br></p>",
                "n",
                "n",
                "0",
                "0",
            ],
            ["7", $sid, "q7", "5", null, "0", "0", "7", "<p>Check all that apply<br></p>", "n", "n", "0", "0"],
            ["8", $sid, "q8", "9", null, "0", "0", "8", "<p>Enter today's date<br></p>", "n", "n", "0", "0"],
            ["9", $sid, "q9", "6", null, "0", "0", "9", "<p>Choose One<br></p>", "n", "n", "0", "0"],
            ["10", $sid, "q10", "3", null, "5", "0", "10", "<p>Write an essay<br></p>", "n", "n", "0", "0"],
            ["11", $sid, "q11", "10", null, "10", "0", "11", "<p>Enter a number<br></p>", "n", "n", "0", "0"],
            ["12", $sid, "q12", "4", null, "1", "0", "13", "<p>Choose a colour<br></p>", "n", "n", "0", "0"],
            ["13", $sid, "q13", "8", null, "5", "1", "14", "<p>Rate this.<br></p>", "n", "n", "0", "0"],
            ["14", $sid, null, "99", null, "0", "0", "12", "break", "n", "y", "0", "0"],
            ["15", $sid, null, "99", null, "0", "0", "12", "break", "n", "n", "0", "0"],
            ["16", $sid, "Q1", "10", null, "3", "2", "15", "Enter a number<br><p><br></p>", "y", "n", "0", "0"],
        ];

        $choicedata = [
            ["id", "question_id", "content", "value"],
            ["1", "7", "1", null],
            ["2", "7", "2", null],
            ["3", "7", "3", null],
            ["4", "7", "4", null],
            ["5", "7", "5", null],
            ["6", "9", "1", null],
            ["7", "9", "One", null],
            ["8", "9", "2", null],
            ["9", "9", "Two", null],
            ["10", "9", "3", null],
            ["11", "9", "Three", null],
            ["12", "12", "Red", null],
            ["13", "12", "Toyota", null],
            ["14", "12", "Bird", null],
            ["15", "12", "Blew", null],
            ["16", "13", "Good", null],
            ["17", "13", "Great", null],
            ["18", "13", "So-so", null],
            ["19", "13", "Lamp", null],
            ["20", "13", "Huh?", null],
            ["21", "7", "!other=Another number", null],
            ["22", "12", "!other=Something else", null],
        ];

        $this->add_data($questiondata, 'questionnaire_question', 'questionmap');
        $this->add_data($choicedata, 'questionnaire_quest_choice', 'choicemap', ['questionmap' => 'question_id']);
    }

    /**
     * Adds response data to the given questionnaire and survey id.
     *
     * @param int $qid The id field of an existing questionnaire record.
     * @param int $sid The id field of an existing questionnaire_survey record.
     * @return null
     */
    private function add_response_data($qid, $sid) {
        global $DB;

        $responses = [
            ["id", "questionnaireid", "submitted", "complete", "grade", "userid"],
            ["1", $qid, "1419011935", "y", "0", "2"],
            ["2", $qid, "1449064371", "y", "0", "2"],
            ["3", $qid, "1449258520", "y", "0", "2"],
            ["4", $qid, "1452020444", "y", "0", "2"],
            ["5", $qid, "1452804783", "y", "0", "2"],
            ["6", $qid, "1452806547", "y", "0", "2"],
            ["7", $qid, "1465415731", "n", "0", "2"],
        ];
        $this->add_data($responses, 'questionnaire_response', 'responsemap');

        $responsebool = [
            ["id", "response_id", "question_id", "choice_id"],
            ["", "1", "1", "y"],
            ["", "1", "4", "n"],
            ["", "2", "1", "y"],
            ["", "2", "4", "n"],
            ["", "3", "1", "n"],
            ["", "3", "4", "y"],
            ["", "4", "1", "y"],
            ["", "4", "4", "n"],
            ["", "5", "1", "n"],
            ["", "5", "4", "n"],
            ["", "6", "1", "n"],
            ["", "6", "4", "n"],
            ["", "7", "1", "y"],
            ["", "7", "4", "y"],
        ];
        $this->add_data(
            $responsebool,
            'questionnaire_response_bool',
            '',
            ['responsemap' => 'response_id', 'questionmap' => 'question_id']
        );

        $responsedate = [
            ["id", "response_id", "question_id", "response"],
            ["", "1", "8", "2014-12-19"],
            ["", "2", "8", "2015-12-02"],
            ["", "3", "8", "2015-12-04"],
            ["", "4", "8", "2016-01-06"],
            ["", "5", "8", "2016-01-13"],
            ["", "6", "8", "2016-01-13"],
        ];
        $this->add_data(
            $responsedate,
            'questionnaire_response_date',
            '',
            ['responsemap' => 'response_id', 'questionmap' => 'question_id']
        );

        $responseother = [
            ["id", "response_id", "question_id", "choice_id", "response"],
            ["", "5", "7", "21", "Forty-four"],
            ["", "6", "12", "22", "Green"],
            ["", "7", "7", "21", "5"],
        ];
        $this->add_data(
            $responseother,
            'questionnaire_response_other',
            '',
            ['responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id']
        );

        $responserank = [
            ["id", "response_id", "question_id", "choice_id", "rankvalue"],
            ["", "1", "13", "16", "0"],
            ["", "1", "13", "17", "1"],
            ["", "1", "13", "18", "2"],
            ["", "1", "13", "19", "3"],
            ["", "1", "13", "20", "4"],
            ["", "2", "13", "16", "0"],
            ["", "2", "13", "17", "1"],
            ["", "2", "13", "18", "2"],
            ["", "2", "13", "19", "3"],
            ["", "2", "13", "20", "4"],
            ["", "3", "13", "16", "4"],
            ["", "3", "13", "17", "0"],
            ["", "3", "13", "18", "3"],
            ["", "3", "13", "19", "1"],
            ["", "3", "13", "20", "2"],
            ["", "4", "13", "16", "2"],
            ["", "4", "13", "17", "2"],
            ["", "4", "13", "18", "2"],
            ["", "4", "13", "19", "2"],
            ["", "4", "13", "20", "2"],
            ["", "5", "13", "16", "1"],
            ["", "5", "13", "17", "1"],
            ["", "5", "13", "18", "1"],
            ["", "5", "13", "19", "1"],
            ["", "5", "13", "20", "-1"],
            ["", "6", "13", "16", "2"],
            ["", "6", "13", "17", "3"],
            ["", "6", "13", "18", "-1"],
            ["", "6", "13", "19", "1"],
            ["", "6", "13", "20", "-1"],
            ["", "7", "13", "16", "-999"],
            ["", "7", "13", "17", "-999"],
            ["", "7", "13", "18", "-999"],
            ["", "7", "13", "19", "-999"],
            ["", "7", "13", "20", "-999"],
        ];
        $this->add_data(
            $responserank,
            'questionnaire_response_rank',
            '',
            ['responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id']
        );

        $respmultiple = [
            ["id", "response_id", "question_id", "choice_id"],
            ["", "1", "7", "1"],
            ["", "1", "7", "3"],
            ["", "1", "7", "5"],
            ["", "2", "7", "4"],
            ["", "3", "7", "2"],
            ["", "3", "7", "4"],
            ["", "4", "7", "2"],
            ["", "4", "7", "4"],
            ["", "4", "7", "5"],
            ["", "5", "7", "2"],
            ["", "5", "7", "3"],
            ["", "5", "7", "21"],
            ["", "6", "7", "2"],
            ["", "6", "7", "5"],
            ["", "7", "7", "21"],
        ];
        $this->add_data(
            $respmultiple,
            'questionnaire_resp_multiple',
            '',
            ['responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id']
        );

        $respsingle = [
            ["id", "response_id", "question_id", "choice_id"],
            ["", "1", "9", "7"],
            ["", "1", "12", "15"],
            ["", "2", "9", "7"],
            ["", "2", "12", "14"],
            ["", "3", "9", "11"],
            ["", "3", "12", "15"],
            ["", "4", "9", "6"],
            ["", "4", "12", "12"],
            ["", "5", "9", "6"],
            ["", "5", "12", "13"],
            ["", "6", "9", "7"],
            ["", "6", "12", "22"],
        ];
        $this->add_data(
            $respsingle,
            'questionnaire_resp_single',
            '',
            ['responsemap' => 'response_id', 'questionmap' => 'question_id', 'choicemap' => 'choice_id']
        );
    }

    /**
     * Helper function to insert record data, save mapping data and remap data where necessary.
     *
     * @param array $data Array of data record row arrays. The first row contains the field names.
     * @param string $datatable The name of the data table to insert records into.
     * @param string $mapvar The name of the object variable to store oldid / newid mappings (optional).
     * @param array|null $replvars Array of key/value pairs where key is the mapvar and value is the record field
     *                         to replace with mapped values.
     * @return null
     */
    private function add_data(array $data, $datatable, $mapvar = '', ?array $replvars = null) {
        global $DB;

        if ($replvars === null) {
            $replvars = [];
        }
        $fields = array_shift($data);
        foreach ($data as $row) {
            $record = new stdClass();
            foreach ($row as $key => $fieldvalue) {
                if ($fields[$key] == 'id') {
                    if (!empty($mapvar)) {
                        $oldid = $fieldvalue;
                    }
                } else if (($replvar = array_search($fields[$key], $replvars)) !== false) {
                    $record->{$fields[$key]} = $this->{$replvar}[$fieldvalue];
                } else {
                    $record->{$fields[$key]} = $fieldvalue;
                }
            }
            $newid = $DB->insert_record($datatable, $record);
            if (!empty($mapvar)) {
                $this->{$mapvar}[$oldid] = $newid;
            }
        }
    }
    /**
     * Get a questionnaire by name.
     *
     * @param string $name questionnaire name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_questionnaire_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('questionnaire', ['name' => $name], '*', MUST_EXIST);
    }

    /**
     * Get a questionnaire cmid from the quiz name.
     *
     * @param string $name questionnaire name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_questionnaire_name(string $name): stdClass {
        $questionnaire = $this->get_questionnaire_by_name($name);
        return get_coursemodule_from_instance('questionnaire', $questionnaire->id, $questionnaire->course);
    }
}
