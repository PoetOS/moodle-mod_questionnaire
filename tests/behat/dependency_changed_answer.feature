@mod @mod_questionnaire
Feature: A dependent answer is dropped when its parent answer changes
  In order to report only answers to questions a participant was actually asked
  As a teacher
  I need an answer to a dependent question to be discarded once its dependency is no longer fulfilled

  Background: Q2 is shown only when Q1 is answered Yes, and Q3 follows on its own page.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       | resume | navigate |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 | 1      | 1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1   |
      | No            | n    |
      | Question Text | Car? |
    And I add a "Text Box" question and I fill the form with:
      | Question Name            | Q2                         |
      | No                       | n                          |
      | Input box length         | 20                         |
      | Max. text length         | 20                         |
      | id_dependquestions_and_0 | Q1->Yes                    |
      | Question Text            | Which color is your car?   |
    And I set the field "id_typeid" to "Page break"
    And I press "Add selected question type"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q3                   |
      | No            | n                    |
      | Question Text | Any normal question? |
    And I log out

  @javascript
  Scenario: Changing the parent answer from Yes to No removes the dependent answer from the results.
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    And I click on "Yes" "radio"
    And I press "Next Page >>"
    And I should see "Which color is your car?"
    And I set the field "Which color is your car?" to "Bug-Blue"
    And I press "Next Page >>"
    And I should see "Any normal question?"
    And I press "<< Previous Page"
    And I press "<< Previous Page"
    And I should see "Car?"
    And I click on "No" "radio"
    And I press "Next Page >>"
    And I should not see "Which color is your car?"
    And I should see "Any normal question?"
    And I click on "Yes" "radio"
    And I press "Submit questionnaire"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "View all responses" in current page administration
    Then I should see "Car?"
    And I should not see "Bug-Blue"
