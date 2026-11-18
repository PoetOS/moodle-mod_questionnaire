@mod @mod_questionnaire
Feature: Feedback sections page access
  In order to keep feedback settings for teachers
  As a student
  I cannot use the feedback sections page

  Scenario: A student is refused the feedback sections page and nothing is created
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Test questionnaire | Test questionnaire description | C1 | questionnaire0 |
    And I log in as "student1"
    When I open the "feedback sections" page of questionnaire "Test questionnaire" for course "C1" and it is refused
    Then questionnaire "Test questionnaire" should have "0" feedback sections
