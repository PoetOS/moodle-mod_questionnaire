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
    And I click on "Include" "checkbox" in the "Q1" "form_row"
    And I click on "Include" "checkbox" in the "Q2" "form_row"
    And I press "Save changes"

  @javascript
  Scenario: Choose the questions and weights that count towards a section.
    Then I should see "2" in the "[New section]" "table_row"
    And I click on "Edit" "link" in the "[New section]" "table_row"
    And I click on "Include" "checkbox" in the "Q1" "form_row"
    And I set the field "Weight for Q2" to "1.5"
    And I press "Save changes"
    And I should see "Enter a weight greater than 0 and no more than 1."
    And I set the field "Weight for Q2" to "0.5"
    And I press "Save changes"
    And I should see "1" in the "[New section]" "table_row"
    And I click on "Edit" "link" in the "[New section]" "table_row"
    And the field "Weight for Q2" matches value "0.5"

  @javascript
  Scenario: Enter a section's messages as score ranges.
    When I click on "Edit" "link" in the "[New section]" "table_row"
    Then I should see "From 100%" in the "Range 1" "form_row"
    And I set the field "id_feedbackboundaries_0" to "60"
    And I should see "From 60%" in the "Range 2" "form_row"
    And I set the field "id_feedbacktext_0" to "High scores"
    And I set the field "id_feedbackboundaries_1" to "70"
    And I set the field "id_feedbacktext_1" to "Low scores"
    And I press "Save changes"
    And I should see "The boundary must be lower than the one in the range above."
    And I set the field "id_feedbackboundaries_1" to ""
    And I press "Save changes"
    And I should see "2" in the "[New section]" "table_row"
    And I click on "Edit" "link" in the "[New section]" "table_row"
    And the field "id_feedbackboundaries_0" matches value "60"
    And I should see "From 60%" in the "Range 2" "form_row"
    And I press "Add a range"
    And I should see "Range 4"
    And I press "Remove this range"
    And I should not see "Range 1"
    And I should see "Range 2"

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
