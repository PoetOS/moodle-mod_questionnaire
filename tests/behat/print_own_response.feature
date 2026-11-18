@mod @mod_questionnaire
Feature: Print my own response
  In order to keep a copy of my answers
  As a student
  I can print my own response

  @javascript
  Scenario: A student prints their own submitted response
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
      | activity      | name               | description                    | course | idnumber       |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 |
    And I am on the "Test questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1               |
      | Yes           | y                |
      | Question Text | Do you own a car |
    And I log out
    And I am on the "Test questionnaire" "mod_questionnaire > view" page logged in as "student1"
    And I navigate to "Answer the questions..." in current page administration
    And I click on "Yes" "radio"
    And I press "Submit questionnaire"
    And I press "Continue"
    And I should see "View your response(s)"
    When I click on "Print this Response" "link"
    And I switch to a second window
    Then I should see "Respondent:"
    And I should see "Student 1"
    And I should see "Do you own a car"

  Scenario: A student prints their own response in a course using another course's public questionnaire
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C2     | student |
    And the following "activities" exist:
      | activity      | name                   | description            | course | idnumber       |
      | questionnaire | Public questionnaire   | Owned by course 1      | C1     | questionnaire1 |
      | questionnaire | Course 2 questionnaire | Uses the public survey | C2     | questionnaire2 |
    And "Public questionnaire" has questions and responses
    And questionnaire "Course 2 questionnaire" uses the public survey of questionnaire "Public questionnaire"
    And the responses of questionnaire "Public questionnaire" were made in questionnaire "Course 2 questionnaire"
    And the responses of questionnaire "Course 2 questionnaire" were made by "student1"
    And I am on the "Course 2 questionnaire" "mod_questionnaire > view" page logged in as "student1"
    And I follow "View your response(s)"
    And I follow "Individual responses"
    When I follow "Print this Response"
    Then I should see "Respondent:"
    And I should see "Student 1"
