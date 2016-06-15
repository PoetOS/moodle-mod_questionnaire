@mod @mod_questionnaire
Feature: Review responses
  In order to review questionnaire responses
  As a teacher
  I need to access the view responses features

@javascript
  Scenario: Add a questionnaire to a course without questions
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Test questionnaire | Test questionnaire description | C1 | questionnaire0 |
    And "Test questionnaire" has questions and responses
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test questionnaire"
    Then I should see "View All Responses"