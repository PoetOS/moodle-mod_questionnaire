@mod @mod_questionnaire
Feature: Print your own response
  In order to keep a copy of my answers
  As a student
  I can print my own response without being able to view all responses

  Scenario: A student prints their own response
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 |
    And "Test questionnaire" has questions and responses
    And the responses of questionnaire "Test questionnaire" were made by "student1"
    And I am on the "Test questionnaire" "mod_questionnaire > view" page logged in as "student1"
    And I follow "View your response(s)"
    And I follow "Individual responses"
    When I open the "Print this Response" link in this window
    Then I should see "Respondent:"
    And I should see "Student 1"
    And I should see "Do you own a car?"
