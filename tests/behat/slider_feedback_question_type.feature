@mod @mod_questionnaire
Feature: In questionnaire, slider questions can be defined with scores attributed to specific answers, in order
  to provide score dependent feedback.
  In order to define a feedback question
  As a teacher
  I must add a required slider question type.

  @javascript
  Scenario: Create a questionnaire with a slider question type and verify that feedback options exist.
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
      | activity | name | description | course | idnumber | resume | navigate |
      | questionnaire | Test questionnaire | Test questionnaire description | C1 | questionnaire0 | 1 | 1 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I follow "Feedback"    
    And I should not see "Feedback options"
    And I navigate to "Questions" in current page administration
    Then I should see "Add questions"
    And I add a "Slider" question and I fill the form with:
      | Question Name                | Q1                   |
      | Question Text                | Slider question test |
      | Left label                   | Left                 |
      | Right label                  | Right                |
      | Centre label                 | Center               |
      | Minimum slider range (left)  | -5                    |
      | Maximum slider range (right) | 5                  |
      | Slider starting value        | 0                    |
      | Slider increment value       | 1                    |
    Then I should see "position 1"
    And I should see " [Slider] (Q1)"
    And I should see "Slider question test"
    And I follow "Feedback"
    Then I should see "Feedback options are available if your questionnaire contains the following question types and question settings"
    And I navigate to "Questions" in current page administration
    Then I should see "Add questions"
    And I add a "Slider" question and I fill the form with:
      | Question Name                | Q2                   |
      | Question Text                | Slider question test |
      | Left label                   | Left                 |
      | Right label                  | Right                |
      | Centre label                 | Center               |
      | Minimum slider range (left)  | 0                    |
      | Maximum slider range (right) | 5                  |
      | Slider starting value        | 0                    |
      | Slider increment value       | 1                    |
    Then I should see "position 2"
    And I should see " [Slider] (Q2)"
    And I should see "Slider question test"
    And I follow "Feedback"
    And I should see "Feedback options"
    And I log out