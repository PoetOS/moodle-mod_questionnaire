@mod @mod_questionnaire
Feature: Questionnaire preview and print access
  In order to keep questionnaire content private
  As a site
  Previews and printouts must respect login, enrolment and activity visibility.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C2     | editingteacher |
      | student1 | C1     | student        |
      | student1 | C2     | student        |

  Scenario: A popup preview requires login
    Given the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       | visible |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 | 1       |
    And "Test questionnaire" has questions and responses
    When I visit the popup preview page of questionnaire "Test questionnaire"
    Then I should not see "Do you own a car?"
    And I should see "Log in"

  Scenario: A student cannot print a hidden questionnaire
    Given the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       | visible |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 | 0       |
    And "Test questionnaire" has questions and responses
    And I log in as "student1"
    When I visit the blank print page of questionnaire "Test questionnaire"
    Then I should not see "Do you own a car?"

  Scenario: A teacher can preview a template from another course only while adding to their own course
    Given the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       | visible |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 | 1       |
    And "Test questionnaire" has questions and responses
    And I am on the "Test questionnaire" "mod_questionnaire > advsettings" page logged in as "admin"
    And I set the field "id_realm" to "template"
    And I press "Save and display"
    And I log out
    And I log in as "teacher1"
    And I add a questionnaire activity to course "Course 2" section "1"
    When I follow "Test questionnaire [Course 1]"
    Then I should see "Do you own a car?"
    And I log out
    And I log in as "student1"
    And previewing questionnaire "Test questionnaire" for adding to course "Course 2" is refused
