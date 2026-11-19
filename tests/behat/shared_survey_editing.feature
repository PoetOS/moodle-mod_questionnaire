@mod @mod_questionnaire
Feature: Editing a shared public questionnaire
  In order to keep a public questionnaire the same for every course using it
  As a teacher
  I can only edit it in the course that owns it

  Scenario: A course using a public questionnaire cannot open its editing pages
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
      | teacher1 | C2     | editingteacher |
    And the following "activities" exist:
      | activity      | name                   | description            | course | idnumber       |
      | questionnaire | Public questionnaire   | Owned by course 1      | C1     | questionnaire1 |
      | questionnaire | Course 2 questionnaire | Uses the public survey | C2     | questionnaire2 |
    And questionnaire "Course 2 questionnaire" uses the public survey of questionnaire "Public questionnaire"
    And I log in as "teacher1"
    When I open the "questions" page of questionnaire "Course 2 questionnaire" for course "C2" and it is refused
    And I open the "advanced settings" page of questionnaire "Course 2 questionnaire" for course "C2" and it is refused
    And I open the "feedback" page of questionnaire "Course 2 questionnaire" for course "C2" and it is refused
    And I open the "feedback sections" page of questionnaire "Course 2 questionnaire" for course "C2" and it is refused
    Then I am on the "Public questionnaire" "mod_questionnaire > questions" page
    And I should see "Manage questions"
    And I am on the "Public questionnaire" "mod_questionnaire > advsettings" page
    And I should see "Advanced settings"
