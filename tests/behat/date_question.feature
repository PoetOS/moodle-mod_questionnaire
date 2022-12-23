@mod @mod_questionnaire @javascript
Feature: Date question
  A date type question can be added and completed.
  
  Scenario: Add a questionnaire to a course with a date question
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
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
    And I add a "Date" question and I fill the form with:
      | Question Name | Q2                       |
      | Yes           | y                        |
      | Question Text | What is your birth date? |
    And I wait until the page is ready
    Then I should see "[Date] (Q2)"
    And I log out

#  Scenario: Student completes feedback questions.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    And I should see "What is your birth date?"
    And I set the field "What is your birth date?" to "2012-03-21T12:00:00"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "What is your birth date?"
    And I should see "2012-03-21"
    And I log out
