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
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test questionnaire"
    Then I should see "View All Responses"
    And I follow "View All Responses"
    Then I should see "View All Responses. All participants. View Default order Responses: 6"
    And I follow "Ascending order"
    Then I should see "View All Responses. All participants. Ascending order Responses: 6"
    And I follow "Descending order"
    Then I should see "View All Responses. All participants. Descending order Responses: 6"
    And I follow "List of responses"
    Then I should see "Individual responses  : All participants"
    And I follow "Admin User"
    Then I should see "1 / 6"
    And I should see "Friday, 15 January 2016, 5:22 am"
    And I should see "Test questionnaire"
    And I follow "Next"
    Then I should see "2 / 6"
    And I should see "Friday, 15 January 2016, 4:53 am"
    And I follow "Last Respondent"
    Then I should see "6 / 6"
    And I should see "Saturday, 20 December 2014, 1:58 am"
    And I follow "Delete this Response"
    Then I should see "Are you sure you want to delete the response"
    And I should see "Saturday, 20 December 2014, 1:58 am"
    And I press "Yes"
    Then I should see "Individual responses  : All participants"
    And I follow "Admin User"
    Then I should see "1 / 5"
    And I follow "Summary"
    Then I should see "View All Responses. All participants. View Default order Responses: 5"
    And I follow "Delete ALL Responses"
    Then I should see "Are you sure you want to delete ALL the responses in this questionnaire?"
    And I press "Yes"
    Then I should see "You are not eligible to take this questionnaire."
    And I should not see "View All Responses"
