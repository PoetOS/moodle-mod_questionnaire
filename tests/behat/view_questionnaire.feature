@mod @mod_questionnaire
Feature: Questionnaires can be public, private or template
  In order to view a questionnaire
  As a user
  The type of the questionnaire affects how it is displayed.

@javascript
  Scenario: Add a template questionnaire
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | manager1 | Manager | 1 | manager1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | manager1 | C1 | manager |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Test questionnaire | Test questionnaire description | C1 | questionnaire0 |
    And I log in as "manager1"
    And I am on site homepage
    And I follow "Course 1"
    And I follow "Test questionnaire"
    And I navigate to "Advanced settings" node in "Questionnaire administration"
    And I should see "Content options"
    And I set the field "id_realm" to "template"
    And I press "Save and display"
    Then I should see "Template questionnaires are not viewable"

@javascript
  Scenario: Add a questionnaire from a public questionnaire
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | manager1 | Manager | 1 | manager1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | manager1 | C1 | manager |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Test questionnaire | Test questionnaire description | C1 | questionnaire0 |
    And I log in as "manager1"
    And I am on site homepage
    And I follow "Course 1"
    And I follow "Test questionnaire"
    And I navigate to "Advanced settings" node in "Questionnaire administration"
    And I should see "Content options"
    And I set the field "id_realm" to "public"
    And I press "Save and return to course"
