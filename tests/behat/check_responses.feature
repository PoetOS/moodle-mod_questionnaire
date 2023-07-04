@mod @mod_questionnaire
Feature: Review responses
  In order to review and manage questionnaire responses
  As a teacher
  I need to access the view responses features

  @javascript
  Scenario: Add a questionnaire to a course without questions
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
    And "Test questionnaire" has questions and responses
    And I log in as "admin"
    And I navigate to "Location > Location settings" in site administration
    And I set the field "Default timezone" to "Europe/London"
    And I set the field "Force timezone" to "Europe/London"
    And I press "Save changes"
    And I navigate to "Language > Language settings" in site administration
    And I set the field "Language autodetect" to "0"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    Then I should see "View all responses"
    And I navigate to "View all responses" in current page administration
    Then I should see "View all responses."
    And I should see "All participants."
    And I should see "View Default order"
    And I should see "Responses: 6"
    And I follow "Ascending order"
    Then I should see "View all responses."
    And I should see "All participants."
    And I should see "Ascending order"
    And I should see "Responses: 6"
    And I follow "Descending order"
    Then I should see "View all responses."
    And I should see "All participants."
    And I should see "Descending order"
    And I should see "Responses: 6"
    And I follow "List of responses"
    Then I should see "Individual responses  : All participants"
    And I follow "Admin User"
    Then I should see "1 / 6"
    And I should see "Respondent:"
    And I should see "Admin User"
    And I should see "Submitted on:"
#    And I should see "Thursday, 14 January 2016, 9:22 pm"
    And I should see "Test questionnaire"
    And I follow "Next"
    Then I should see "2 / 6"
#    And I should see "Thursday, 14 January 2016, 8:53 pm"
    And I follow "Last Respondent"
    Then I should see "6 / 6"
#    And I should see "Friday, 19 December 2014, 5:58 pm"
    And I follow "Delete this Response"
    Then I should see "Are you sure you want to delete the response"
#    And I should see "Friday, 19 December 2014, 5:58 pm"
    And I press "Delete"
    Then I should see "Individual responses  : All participants"
    And I follow "Admin User"
    Then I should see "1 / 5"
    And I follow "Summary"
    Then I should see "View all responses."
    And I should see "All participants."
    And I should see "View Default order"
    And I should see "Responses: 5"
    And I follow "Delete ALL Responses"
    Then I should see "Are you sure you want to delete ALL the responses in this questionnaire?"
    And I press "Delete"
    Then I should see "You are not eligible to take this questionnaire."
    And I should not see "View all responses"

  @javascript
  Scenario: Choices with HTML should display filtered HTML in the responses on the response page
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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Check Boxes" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Min. forced responses | 1 |
      | Max. forced responses | 2 |
      | Question Text | Select one or two choices only |
      | Possible answers | <b>One</b>,Two,Three,Four |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Select one or two choices only"
    # And I set the field "Do you own a car?" to "y"
    And I set the field "One" to "checked"
    And I press "Submit questionnaire"
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "View all responses" in current page administration
    Then "//b[text()='One']" "xpath_element" should exist
