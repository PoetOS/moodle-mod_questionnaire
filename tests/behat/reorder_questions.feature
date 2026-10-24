@mod @mod_questionnaire
Feature: Reorder questions on the manage-questions page
  In order to arrange the questions in a questionnaire
  As a teacher
  I need to move questions using the drag handle's move dialogue

  Background:
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
      | activity      | name               | description                    | course | idnumber       | navigate |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 | 1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1                  |
      | Yes           | y                   |
      | Question Text | Do you own a car?   |
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q2                  |
      | Yes           | y                   |
      | Question Text | Do you own a bike?  |

  @javascript
  Scenario: Move a question and the new order persists
    Given I add a "Yes/No" question and I fill the form with:
      | Question Name | Q3                  |
      | Yes           | y                   |
      | Question Text | Do you own a boat?  |
    And "[Yes/No] (Q2)" "list_item" should appear before "[Yes/No] (Q3)" "list_item"
    When I click on "Move [Yes/No] (Q3)" "button" in the "questionnaire-questionlist" "region"
    And I click on "After \"[Yes/No] (Q1)\"" "link" in the "Move [Yes/No] (Q3)" "dialogue"
    Then "Question moved" "toast_message" should be visible
    And "[Yes/No] (Q1)" "list_item" should appear before "[Yes/No] (Q3)" "list_item"
    And "[Yes/No] (Q3)" "list_item" should appear before "[Yes/No] (Q2)" "list_item"
    And I should see "position 2" in the "[Yes/No] (Q3)" "list_item"
    And I should see "position 3" in the "[Yes/No] (Q2)" "list_item"
    And I reload the page
    And "[Yes/No] (Q1)" "list_item" should appear before "[Yes/No] (Q3)" "list_item"
    And "[Yes/No] (Q3)" "list_item" should appear before "[Yes/No] (Q2)" "list_item"
    And I should see "position 2" in the "[Yes/No] (Q3)" "list_item"

  @javascript
  Scenario: Move a question to the top of the list
    When I click on "Move [Yes/No] (Q2)" "button" in the "questionnaire-questionlist" "region"
    And I click on "To the top of the list" "link" in the "Move [Yes/No] (Q2)" "dialogue"
    Then "Question moved" "toast_message" should be visible
    And I reload the page
    And "[Yes/No] (Q2)" "list_item" should appear before "[Yes/No] (Q1)" "list_item"
    And I should see "position 1" in the "[Yes/No] (Q2)" "list_item"

  @javascript
  Scenario: A question cannot be moved above the question it depends on
    Given I add a "Yes/No" question and I fill the form with:
      | Question Name            | Q3                         |
      | Yes                      | y                          |
      | id_dependquestions_and_0 | Q1->Yes                    |
      | Question Text            | Is the car insured?        |
    When I click on "Move [Yes/No] (Q3)" "button" in the "questionnaire-questionlist" "region"
    And I click on "To the top of the list" "link" in the "Move [Yes/No] (Q3)" "dialogue"
    Then I should see "This order would place a question before one it depends on." in the "Error" "dialogue"
    And I click on "OK" "button" in the "Error" "dialogue"
    And "[Yes/No] (Q1)" "list_item" should appear before "[Yes/No] (Q3)" "list_item"
    And I should see "position 1" in the "[Yes/No] (Q1)" "list_item"
    And I reload the page
    And "[Yes/No] (Q1)" "list_item" should appear before "[Yes/No] (Q3)" "list_item"
