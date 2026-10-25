@mod @mod_questionnaire
Feature: Teachers can remove questions from a feedback section and delete feedback sections.
  In order to maintain feedback sections
  As a teacher
  I need to remove questions from sections and delete sections after confirming.

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | First radio |
      | Possible answers | 1=One,2=Two |
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Question Text | Second radio |
      | Possible answers | 1=One,2=Two |
    And I navigate to "Feedback" in current page administration
    And I set the field "id_feedbacksections" to "Feedback sections"
    And I press "Save settings"
    And I click on "Edit" "link" in the "[New section]" "table_row"
    And I expand all fieldsets
    And I set the field "addquestionselect" to "Q1"
    And I press "Add question..."
    And I expand all fieldsets
    And I set the field "addquestionselect" to "Q2"
    And I press "Add question..."

  @javascript
  Scenario: Remove a question from a feedback section after confirming.
    When I expand all fieldsets
    And I press "Remove Q1 from this section"
    Then I should see "Are you sure you want to remove question \"Q1\" from section \"[New section]\"?"
    And I press "Yes"
    And I expand all fieldsets
    And the "addquestionselect" select box should contain "Q1"
    And the "addquestionselect" select box should not contain "Q2"

  @javascript
  Scenario: Add and delete a feedback section from the feedback page.
    When I navigate to "Feedback" in current page administration
    And I set the field "New section label" to "Second section"
    And I press "Add new section"
    Then I should see "Second section section heading"
    And I navigate to "Feedback" in current page administration
    And I click on "Delete" "link" in the "Second section" "table_row"
    Then I should see "Are you sure you want to delete feedback section \"Second section\"?"
    And I press "Yes"
    And I should see "Feedback section deleted."
    And "[New section]" "table_row" should exist
    And "Second section" "table_row" should not exist

  @javascript
  Scenario: Global feedback keeps the sections from sections mode but only lists the first.
    When I navigate to "Feedback" in current page administration
    And I set the field "New section label" to "Second section"
    And I press "Add new section"
    And I navigate to "Feedback" in current page administration
    And I set the field "id_feedbacksections" to "Global Feedback"
    And I press "Save settings"
    Then I should see "1 more section(s) from Feedback sections mode are kept but not used by Global Feedback."
    And "[New section]" "table_row" should exist
    And "Second section" "table_row" should not exist
