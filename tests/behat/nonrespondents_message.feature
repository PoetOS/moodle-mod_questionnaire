@mod @mod_questionnaire
Feature: Message non-respondents
  In order to remind students to answer a questionnaire
  As a teacher
  I need the message form to keep what I typed

  Scenario: The message form shows the subject exactly as it was typed
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
    And I am on the "Test questionnaire" "mod_questionnaire > view" page logged in as "teacher1"
    And I navigate to "Non-respondents" in current page administration
    And I should see "Student 1"
    When I set the field "subject" to "Hello \"there\" data-extra=\"1"
    And I press "Send"
    Then I should see "All fields are required."
    And the field "subject" matches value "Hello \"there\" data-extra=\"1"
    And "input[data-extra]" "css_element" should not exist
