@mod @mod_questionnaire
Feature: View questionnaire availability information in the course view
  In order to have visibility of the questionnaire availability requirements
  As a student
  I need to be able to view the availability dates

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity      | name               | introduction                   | course | idnumber       |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 |

  @javascript
  Scenario: Student can see the open and close dates on the course page
    Given I log in as "teacher1"
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Settings" in current page administration
    And I follow "Expand all"
    And I set the field "Allow responses from" to "##3 days ago##"
    And I set the field "Allow responses until" to "##tomorrow noon##"
    And I press "Save and return to course"
    And I log out

    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Opened: "
    And I should see "Closes: "
