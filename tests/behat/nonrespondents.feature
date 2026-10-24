@mod @mod_questionnaire
Feature: Message questionnaire non-respondents
  In order to remind students to complete a questionnaire
  As a teacher
  I need to see who has not responded and send them a message

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
      | activity      | name                 | description | course | idnumber       | respondenttype |
      | questionnaire | Named questionnaire  | Named       | C1     | questionnaire0 | fullname       |
      | questionnaire | Anonymous survey     | Anonymous   | C1     | questionnaire1 | anonymous      |

  Scenario: A teacher sees the named non-respondents and messages a selected one
    Given I am on the "Named questionnaire" "mod_questionnaire > nonrespondents" page logged in as "teacher1"
    Then I should see "Student 1" in the "showentrytable" "table"
    And I should see "Student 2" in the "showentrytable" "table"
    And I set the field "Select 'Student 1'" to "1"
    And I set the field "Subject" to "Reminder"
    And I set the field "Message body" to "Please complete the questionnaire."
    And I press "Send"
    And I should see "Selected users have been messaged"

  Scenario: Sending without a subject or message keeps the teacher on the page with an error
    Given I am on the "Named questionnaire" "mod_questionnaire > nonrespondents" page logged in as "teacher1"
    When I set the field "Select 'Student 1'" to "1"
    And I press "Send"
    Then I should see "Please enter both a subject and a message."
    And I should see "Student 1" in the "showentrytable" "table"

  Scenario: Sending without selecting anyone shows an error
    Given I am on the "Named questionnaire" "mod_questionnaire > nonrespondents" page logged in as "teacher1"
    When I set the field "Subject" to "Reminder"
    And I set the field "Message body" to "Please complete the questionnaire."
    And I press "Send"
    Then I should see "No users selected"
    And the field "Subject" matches value "Reminder"

  @javascript
  Scenario: Select all ticks every non-respondent
    Given I am on the "Named questionnaire" "mod_questionnaire > nonrespondents" page logged in as "teacher1"
    When I press "Select all"
    Then the field "Select 'Student 1'" matches value "1"
    And the field "Select 'Student 2'" matches value "1"
    And I press "Deselect all"
    And the field "Select 'Student 1'" matches value "0"

  Scenario: An anonymous questionnaire shows only a count and messages everyone chosen by label
    Given I am on the "Anonymous survey" "mod_questionnaire > nonrespondents" page logged in as "teacher1"
    Then I should see "(2)"
    And I should see "Anonymous"
    And I should not see "Student 1"
    And I set the field "All" to "1"
    And I set the field "Subject" to "Reminder"
    And I set the field "Message body" to "Please complete the survey."
    And I press "Send"
    And I should see "Selected users have been messaged"
