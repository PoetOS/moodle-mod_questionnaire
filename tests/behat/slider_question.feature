@mod @mod_questionnaire
Feature: Slider questions can add slider with range for users to choose
  In order to setup a slider question
  As a teacher
  I need to specify the range.

  Background: Add a slider question to a questionnaire.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Questions" in current page administration
    And I add a "Slider" question and I fill the form with:
      | Question Name                | Q1                   |
      | Question Text                | Slider quesrion test |
      | Left label                   | Left                 |
      | Right label                  | Right                |
      | Centre label                 | Center               |
      | Minimum slider range (left)  | 5                    |
      | Maximum slider range (right) | 100                  |
      | Slider starting value        | 5                    |
      | Slider increment value       | 5                    |
    Then I should see "position 1"
    And I should see " [Slider] (Q1)"
    And I should see "Slider quesrion test"
    And I log out

  @javascript
  Scenario: Student use slider questionnaire.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Slider quesrion test"
    And I should see "Left"
    And I should see "Right"
    And I should see "Center"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "View your response(s)"

  @javascript
  Scenario: Teacher use slider questionnaire with invalid setting.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Questions" in current page administration
    And I add a "Slider" question and I fill the form with:
      | Question Name                | Q1                   |
      | Question Text                | Slider quesrion test |
      | Left label                   | Left                 |
      | Right label                  | Right                |
      | Centre label                 | Center               |
      | Minimum slider range (left)  | 10                   |
      | Maximum slider range (right) | 5                    |
      | Slider starting value        | 10                   |
      | Slider increment value       | 15                   |
    And I should see "The maximum slider value must be greater than the minimum slider value."
    And I should see "Note that the value increments must be lower than the maximum value. For example, if a scale of 1-10, the increment value would probably be 1."
    And I am on "Course 1" course homepage
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Questions" in current page administration
    And I add a "Slider" question and I fill the form with:
      | Question Name                | Q1                   |
      | Question Text                | Slider quesrion test |
      | Left label                   | Left                 |
      | Right label                  | Right                |
      | Centre label                 | Center               |
      | Minimum slider range (left)  | -999                 |
      | Maximum slider range (right) | 999                  |
      | Slider starting value        | 10                   |
      | Slider increment value       | 15                   |
    And I should see "This question type supports an absolute maximum range of -100 to +100. We expect the vast majority of questionnaire designs to use a range of 1-10 or -10 to +10."
