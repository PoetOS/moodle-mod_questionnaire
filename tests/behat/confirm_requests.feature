@mod @mod_questionnaire
Feature: Confirmed questionnaire actions
  In order to change a questionnaire safely
  As a teacher
  I need deletions and messages to happen only from the questionnaire's own forms

  Background:
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

  @javascript
  Scenario: Responses are only deleted from the confirmation form
    Given "Test questionnaire" has questions and responses
    And I am on the "Test questionnaire" "mod_questionnaire > view" page logged in as "admin"
    And I navigate to "View all responses" in current page administration
    And I follow "List of responses"
    And I follow "Admin User"
    And I follow "Delete this Response"
    When I submit the form of the "Delete" button without a sesskey and it is refused
    And I am on the "Test questionnaire" "mod_questionnaire > view" page
    And I navigate to "View all responses" in current page administration
    And I should see "Responses: 7 (Submissions: 6 | In progress: 1)"
    And I follow "Delete ALL Responses"
    And I submit the form of the "Delete" button without a sesskey and it is refused
    And I am on the "Test questionnaire" "mod_questionnaire > view" page
    And I navigate to "View all responses" in current page administration
    And I should see "Responses: 7 (Submissions: 6 | In progress: 1)"

  @javascript
  Scenario: Questions are only deleted from the confirmation form
    Given I am on the "Test questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | First question |
    And I set the field "id_type_id" to "----- Page Break -----"
    And I press "Add selected question type"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Question Text | Second question |
    And I should see "[----- Page Break -----]"
    # A page break is deleted straight away, without a confirmation.
    When I click on "(//input[starts-with(@name, 'removebutton')])[2]" "xpath_element"
    Then I should not see "[----- Page Break -----]"
    And I click on "(//input[starts-with(@name, 'removebutton')])[1]" "xpath_element"
    And I should see "Are you sure you want to move the question at position"
    And I should see "(Q1)"
    And I submit the form of the "Yes" button without a sesskey and it is refused
    And I am on the "Test questionnaire" "mod_questionnaire > questions" page
    And "Permanently delete question" "button" should not exist
    And I click on "(//input[starts-with(@name, 'removebutton')])[1]" "xpath_element"
    And I press "Yes"
    And "Permanently delete question" "button" should exist
    And I press "Permanently delete question"
    And I should see "Are you sure you want to permanently delete this question?"
    And I submit the form of the "Yes" button without a sesskey and it is refused
    And I am on the "Test questionnaire" "mod_questionnaire > questions" page
    And "Permanently delete question" "button" should exist
    And I press "Permanently delete question"
    And I press "Yes"
    And "Permanently delete question" "button" should not exist

  @javascript
  Scenario: Feedback sections are only changed from the confirmation form
    Given I am on the "Test questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Are you still in School? |
    And I follow "Feedback"
    And I set the field "id_feedbacksections" to "Feedback sections"
    And I set the field "id_feedbackscores" to "Yes"
    And I press "Save settings and edit Feedback Sections"
    And I follow "[New section] section questions"
    And I set the field "addquestionselect" to "Q1"
    And I press "Add question..."
    And I set the field "id_sectionlabel" to "Section A"
    And I press "Save changes"
    And I expand all fieldsets
    When I click on "input[name^='confirmremovequestion']" "css_element"
    And I should see "Are you sure you want to remove question \"Q1\""
    And I submit the form of the "Yes" button without a sesskey and it is refused
    And I am on the "Test questionnaire" "mod_questionnaire > view" page
    And I navigate to "Feedback" in current page administration
    And I press "Save settings and edit Feedback Sections"
    And I expand all fieldsets
    And I click on "input[name^='confirmremovequestion']" "css_element"
    And I press "Yes"
    And I expand all fieldsets
    And "input[name^='confirmremovequestion']" "css_element" should not exist
    And I press "Delete this section"
    And I should see "Are you sure you want to delete feedback section"
    And I submit the form of the "Yes" button without a sesskey and it is refused
    And I am on the "Test questionnaire" "mod_questionnaire > view" page
    And I navigate to "Feedback" in current page administration
    And I press "Save settings and edit Feedback Sections"
    And I should see "Section A section questions"
    And I press "Delete this section"
    And I press "Yes"
    And I should not see "Section A section questions"

  Scenario: Non-respondents are only messaged from the message form
    Given I am on the "Test questionnaire" "mod_questionnaire > view" page logged in as "teacher1"
    And I navigate to "Non-respondents" in current page administration
    And I set the field with xpath "//input[@name='messageuser[]']" to "1"
    And I set the field "subject" to "Reminder"
    And I set the field "message" to "Please answer the questionnaire."
    When I submit the form of the "Send" button without a sesskey and it is refused
    And I am on the "Test questionnaire" "mod_questionnaire > view" page
    And I navigate to "Non-respondents" in current page administration
    And I set the field with xpath "//input[@name='messageuser[]']" to "1"
    And I set the field "subject" to "Reminder"
    And I set the field "message" to "Please answer the questionnaire."
    And I press "Send"
    And I should see "Selected users have been messaged"

  Scenario: Responses are only downloaded from the download form
    Given "Test questionnaire" has questions and responses
    And I am on the "Test questionnaire" "mod_questionnaire > view" page logged in as "admin"
    And I navigate to "View all responses" in current page administration
    And I follow "Download"
    When I submit the form of the "Download" button without a sesskey and it is refused
