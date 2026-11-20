@mod @mod_questionnaire
Feature: Students view the response summaries
  In order to compare my answers with the class
  As a student
  I can view the response summaries, but not other students' individual responses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       | resp_view |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 | 3         |
    And "Test questionnaire" has questions and responses
    And the responses of questionnaire "Test questionnaire" were made by "student2"

  Scenario: A student sees the summary but not the individual responses
    Given I am on the "Test questionnaire" "mod_questionnaire > view" page logged in as "student1"
    When I follow "View all responses"
    Then I should see "Do you own a car?"
    And I open the "respondents" page of questionnaire "Test questionnaire" for course "C1" and it is refused
    And I open the "first response" page of questionnaire "Test questionnaire" for course "C1" and it is refused

  Scenario: A teacher sees the individual responses
    Given I log in as "teacher1"
    When I open the "first response" page of questionnaire "Test questionnaire" for course "C1"
    Then I should see "Student 2"
    And I should see "Do you own a car?"
