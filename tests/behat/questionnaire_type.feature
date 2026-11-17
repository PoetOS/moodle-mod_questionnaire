@mod @mod_questionnaire
Feature: Questionnaire type in the advanced settings
  In order to share questionnaires only when allowed
  As a teacher
  I need the questionnaire type to change only when I may set it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Test questionnaire | Test questionnaire description | C1 | questionnaire0 |

  Scenario: A teacher who may not share questionnaires keeps the questionnaire private
    Given I am on the "Test questionnaire" "mod_questionnaire > advsettings" page logged in as "teacher1"
    And "id_realm" "select" should not exist
    When I set the hidden field "realm" to "public"
    And I press "Save and return to course"
    And I log out
    Then I am on the "Test questionnaire" "mod_questionnaire > advsettings" page logged in as "admin"
    And the field "id_realm" matches value "private"

  Scenario: A teacher who may not share questionnaires keeps an existing type when saving
    Given I am on the "Test questionnaire" "mod_questionnaire > advsettings" page logged in as "admin"
    And I set the field "id_realm" to "public"
    And I press "Save and return to course"
    And I log out
    And I am on the "Test questionnaire" "mod_questionnaire > advsettings" page logged in as "teacher1"
    When I set the field "title" to "New title"
    And I press "Save and return to course"
    And I log out
    Then I am on the "Test questionnaire" "mod_questionnaire > advsettings" page logged in as "admin"
    And the field "id_realm" matches value "public"
    And the field "title" matches value "New title"
