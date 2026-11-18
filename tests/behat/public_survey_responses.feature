@mod @mod_questionnaire
Feature: Responses to a public questionnaire
  In order to review answers given in every course that uses my public questionnaire
  As the teacher of the course that owns it
  I can view the individual responses made in other courses

  Scenario: The owning course's teacher views an individual response made in another course
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity      | name                   | description             | course | idnumber       |
      | questionnaire | Public questionnaire   | Owned by course 1       | C1     | questionnaire1 |
      | questionnaire | Course 2 questionnaire | Uses the public survey  | C2     | questionnaire2 |
    And "Public questionnaire" has questions and responses
    And questionnaire "Course 2 questionnaire" uses the public survey of questionnaire "Public questionnaire"
    And the responses of questionnaire "Public questionnaire" were made in questionnaire "Course 2 questionnaire"
    And I am on the "Public questionnaire" "mod_questionnaire > view" page logged in as "teacher1"
    When I navigate to "View all responses" in current page administration
    And I follow "List of responses"
    And I follow "Admin User"
    Then I should see "Respondent:"
    And I should see "Admin User"
    And I should see "Submitted on:"
