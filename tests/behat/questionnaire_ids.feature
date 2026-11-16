@mod @mod_questionnaire
Feature: Questionnaire edits stay in their own questionnaire
  In order to keep each course's questionnaires separate
  As a teacher
  I need my edits to only change the questionnaire I am editing

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Own questionnaire | Own questionnaire description | C1 | questionnaire1 |
      | questionnaire | Other questionnaire | Other questionnaire description | C2 | questionnaire2 |
    And I am on the "Other questionnaire" "mod_questionnaire > questions" page logged in as "admin"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Other Q1 |
      | Yes | y |
      | Question Text | Other question |
    And I log out

  @javascript
  Scenario: A page break is added to the questionnaire being edited
    Given I am on the "Own questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Own Q1 |
      | Yes | y |
      | Question Text | Own question |
    And I set the field "id_type_id" to "----- Page Break -----"
    When I set the hidden field "sid" to the "survey id" of questionnaire "Other questionnaire"
    And I press "Add selected question type"
    Then I should see "[----- Page Break -----]"
    And I am on the "Other questionnaire" "mod_questionnaire > questions" page logged in as "admin"
    And I should see "[Yes/No] (Other Q1)"
    And I should not see "[----- Page Break -----]"

  @javascript
  Scenario: Only the questionnaire's own feedback sections can be opened
    Given I am on the "Other questionnaire" "mod_questionnaire > view" page logged in as "admin"
    And I navigate to "Feedback" in current page administration
    And I set the field "id_feedbacksections" to "Feedback sections"
    And I set the field "id_feedbackscores" to "Yes"
    And I press "Save settings and edit Feedback Sections"
    And I should see "[New section] section questions"
    And I log out
    And I am on the "Own questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Own Q1 |
      | Yes | y |
      | Question Text | Own question |
    And I follow "Feedback"
    And I set the field "id_feedbacksections" to "Feedback sections"
    And I set the field "id_feedbackscores" to "Yes"
    And I press "Save settings and edit Feedback Sections"
    And I should see "[New section] section questions"
    When I open "fbsections.php" of questionnaire "Own questionnaire" with "sectionid" set to the "first feedback section id" of "Other questionnaire" and it is refused
