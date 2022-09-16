@mod @mod_questionnaire
Feature: Download the results of a questionnaire, if it contains text and date questions.
  In order to analyze a feedback questionnaire
  As a teacher
  I must download the feedback answers.

  @javascript
  Scenario: Create a questionnaire with a text question type and a date question type and download the results.
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
    And I follow "Questions"
    Then I should see "Add questions"
    And I add a "Text Box" question and I fill the form with:
      | Question Name | Q1                 |
      | Yes           | y                  |
      | Question Text | What is your name? |
    Then I should see "[Text Box] (Q1)"
    And I add a "Date" question and I fill the form with:
      | Question Name | Q2                       |
      | Yes           | y                        |
      | Question Text | What is your birth date? |
    Then I should see "[Date] (Q2)"
    And I log out

#  Scenario: Student completes feedback questions.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "What is your name?"
    And I set the field "What is your name?" to "David Beckham"
    And I set the field "What is your birth date?" to "02/05/1975"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    And I log out

#  Scenario: Download the responses.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "View all responses" in current page administration
    And I follow "Download"
    And I press "Download"
    And I log out
