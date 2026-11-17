@mod @mod_questionnaire
Feature: Deleting questions only changes the questionnaire being edited
  In order to keep other questionnaires intact
  As a teacher
  I need deleting questions to change only my own questionnaire

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Own questionnaire | Own questionnaire description | C1 | questionnaire1 |
      | questionnaire | Other questionnaire | Other questionnaire description | C2 | questionnaire2 |

  @javascript
  Scenario: Deleting the first question before a page break keeps other question positions
    Given I am on the "Other questionnaire" "mod_questionnaire > questions" page logged in as "admin"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Other Q1 |
      | Yes | y |
      | Question Text | Other question 1 |
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Other Q2 |
      | Yes | y |
      | Question Text | Other question 2 |
    And I log out
    And I am on the "Own questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | First question |
    And I set the field "id_type_id" to "----- Page Break -----"
    And I press "Add selected question type"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Question Text | Second question |
    When I click on "(//input[starts-with(@name, 'removebutton')])[1]" "xpath_element"
    And I press "Yes"
    Then I should not see "[----- Page Break -----]"
    And the question positions of questionnaire "Own questionnaire" should be "1"
    And the question positions of questionnaire "Other questionnaire" should be "1, 2"

  @javascript
  Scenario: Deleting the last question only removes the questionnaire's own responses
    Given I am on the "Own questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | First question |
    And a response exists with the "questionnaire id" of questionnaire "Own questionnaire" as its questionnaire id
    And a response exists with the "first question id" of questionnaire "Own questionnaire" as its questionnaire id
    When I click on "(//input[starts-with(@name, 'removebutton')])[1]" "xpath_element"
    And I press "Yes"
    Then "0" responses should exist with the "questionnaire id" of questionnaire "Own questionnaire" as their questionnaire id
    And "1" responses should exist with the "first question id" of questionnaire "Own questionnaire" as their questionnaire id
