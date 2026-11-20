@mod @mod_questionnaire
Feature: Staff-authored markup is kept
  In order to use rich content in a questionnaire
  As a teacher
  The markup I write in question text and survey information is kept when saved and shown

  Scenario: Re-saving question text and survey information keeps their markup
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 |
    And questionnaire "Test questionnaire" has an essay question named "Rich question" with this text:
      """
      <details><summary>Question summary</summary><p>Question detail</p></details><iframe src="https://example.com/question"></iframe>
      """
    And the survey information of questionnaire "Test questionnaire" is:
      """
      <details><summary>Info summary</summary><p>Info detail</p></details><iframe src="https://example.com/info"></iframe>
      """
    And I log in as "teacher1"
    When I open the edit page of question "Rich question" of questionnaire "Test questionnaire"
    And I press "Save changes"
    And I am on the "Test questionnaire" "mod_questionnaire > advsettings" page
    And I press "Save and display"
    And I am on the "Test questionnaire" "mod_questionnaire > preview" page
    Then "//details/summary[text()='Question summary']" "xpath_element" should exist
    And "iframe[src='https://example.com/question']" "css_element" should exist
    And "//details/summary[text()='Info summary']" "xpath_element" should exist
    And "iframe[src='https://example.com/info']" "css_element" should exist
