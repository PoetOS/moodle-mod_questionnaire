@mod @mod_questionnaire
Feature: Questionnaire previews and printing
  In order to keep questionnaires private to their courses
  As a teacher
  I need previews and printed copies to be shown only to users who may see them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | teacher2 | Teacher | 2 | teacher2@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | teacher2 | C2 | editingteacher |
    And the following "activities" exist:
      | activity | name | description | course | idnumber | visible |
      | questionnaire | Template questionnaire | Template questionnaire description | C1 | questionnaire1 | 1 |
      | questionnaire | Private questionnaire | Private questionnaire description | C1 | questionnaire2 | 1 |
      | questionnaire | Hidden questionnaire | Hidden questionnaire description | C1 | questionnaire3 | 0 |
    And I am on the "Template questionnaire" "mod_questionnaire > questions" page logged in as "admin"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Template question |
    And I am on the "Private questionnaire" "mod_questionnaire > questions" page
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Private question |
    And I am on the "Hidden questionnaire" "mod_questionnaire > questions" page
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Hidden question |
    And I am on the "Template questionnaire" "mod_questionnaire > advsettings" page
    And I set the field "id_realm" to "template"
    And I press "Save and display"
    And I log out

  Scenario: A teacher previews a template from another course while adding a questionnaire
    Given I log in as "teacher2"
    And I add a questionnaire activity to course "Course 2" section "1"
    And I expand all fieldsets
    When I open the "Template questionnaire [Course 1]" link in this window
    Then I should see "Template question"

  Scenario: Survey previews are only shown for surveys the course can use
    Given I log in as "teacher2"
    When I open the "survey preview" page of questionnaire "Template questionnaire" for course "C2"
    Then I should see "Template question"
    And I open the "survey preview" page of questionnaire "Private questionnaire" for course "C2" and it is refused
    And I open the "survey preview" page of questionnaire "Template questionnaire using Private questionnaire" for course "C2" and it is refused
    And I log out
    And I log in as "student1"
    And I open the "survey preview" page of questionnaire "Template questionnaire" for course "C1" and it is refused

  Scenario: Previews are only shown to users who may preview the questionnaire
    Given I log in as "teacher1"
    When I open the "popup preview" page of questionnaire "Private questionnaire" for course "C1"
    Then I should see "Private question"
    And I log out
    And I log in as "student1"
    And I open the "popup preview" page of questionnaire "Private questionnaire" for course "C1" and it is refused

  Scenario: Hidden questionnaires are only printed for users who may see them
    Given I am on the "Hidden questionnaire" "mod_questionnaire > view" page logged in as "teacher1"
    When I open the "print" page of questionnaire "Hidden questionnaire" for course "C1"
    Then I should see "Hidden question"
    And I log out
    And I am on the "Private questionnaire" "mod_questionnaire > view" page logged in as "student1"
    And I open the "print" page of questionnaire "Private questionnaire" for course "C1"
    And I should see "Private question"
    And I open the "print" page of questionnaire "Hidden questionnaire" for course "C1" and it is refused
