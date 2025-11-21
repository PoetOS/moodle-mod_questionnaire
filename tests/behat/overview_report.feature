@mod @mod_questionnaire
Feature: Testing overview integration in mod_questionnaire
  In order to analyze the questionnaires filled out by students
  As a user
  I need to be able to see the questionnaire overview

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
      | student2 | Student   | 2        |
      | student3 | Student   | 3        |
      | teacher1 | Teacher   | T        |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity      | name            | description                 | course | idnumber       | completion |
      | questionnaire | Questionnaire 1 | Questionnaire Description 1 | C1     | questionnaire1 | 1          |
      | questionnaire | Questionnaire 2 | Questionnaire Description 2 | C1     | questionnaire2 | 0          |
      | questionnaire | Questionnaire 3 | Questionnaire Description 3 | C1     | questionnaire3 | 0          |
      | questionnaire | Questionnaire 4 | Questionnaire Description 4 | C1     | questionnaire4 | 0          |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Questionnaire 1"
    And I navigate to "Questions" in current page administration
    And I add a "Rate (scale 1..5)" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Nb of scale items | 3 |
      | Type of rate scale | Normal |
      | Question Text | What did you think of these movies? |
      | Possible answers | Star Wars,Casablanca,Airplane |
      | Named degrees    | 1=I did not like,2=Ehhh,3=I liked |
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | closedate[enabled] | 1       |
      | closedate[day]     | 1       |
      | closedate[month]   | January |
      | closedate[year]    | 2040    |
      | closedate[hour]    | 08      |
      | closedate[minute]  | 00      |
    And I press "Save and return to course"
    And I follow "Questionnaire 2"
    And I navigate to "Questions" in current page administration
    And I add a "Rate (scale 1..5)" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Nb of scale items | 3 |
      | Type of rate scale | Normal |
      | Question Text | What did you think of these movies? |
      | Possible answers | Star Wars,Casablanca,Airplane |
      | Named degrees    | 1=I did not like,2=Ehhh,3=I liked |
    And I log out

  Scenario: The questionnaire overview report should generate log events
    Given I am on the "Course 1" "course > activities > questionnaire" page logged in as "teacher1"
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I click on "Get these logs" "button"
    Then I should see "Course activities overview page viewed"
    And I should see "viewed the instance list for the module 'questionnaire'"

  @javascript
  Scenario: Students can see relevant columns and content in the questionnaire overview for Moodle ≤ 5.0

    Given the site is running Moodle version 5.0 or lower
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Questionnaire 1"
    And I navigate to "Answer the questions..." in current page administration
    And I should see "Questionnaire 1"
    And I should see "What did you think of these movies?"
    And I click on "Row 2, Star Wars: Column 5, I liked." "radio"
    And I click on "Row 3, Casablanca: Column 5, I liked." "radio"
    And I click on "Row 4, Airplane: Column 5, I liked." "radio"
    And I press "Submit questionnaire"
    And I am on the "Course 1" "course > activities > questionnaire" page
    Then the following should exist in the "Table listing all Questionnaire activities" table:
      | Name            | Due date       | Completion status | Responded |
      | Questionnaire 1 | 1 January 2040 | Mark as done      |           |
      | Questionnaire 2 | -              | -                 | -         |
      | Questionnaire 3 | -              | -                 | -         |
      | Questionnaire 4 | -              | -                 | -         |
    And I should not see "Actions" in the "questionnaire_overview_collapsible" "region"
    # Check Responded column.
    And "Answered" "icon" should exist in the "Questionnaire 1" "table_row"
    And "Answered" "icon" should not exist in the "Questionnaire 2" "table_row"

  @javascript
  Scenario: Students can see relevant columns and content in the questionnaire overview for Moodle ≥ 5.1
    Given the site is running Moodle version 5.1 or higher
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Questionnaire 1"
    And I navigate to "Answer the questions..." in current page administration
    And I should see "Questionnaire 1"
    And I should see "What did you think of these movies?"
    And I click on "Row 2, Star Wars: Column 5, I liked." "radio"
    And I click on "Row 3, Casablanca: Column 5, I liked." "radio"
    And I click on "Row 4, Airplane: Column 5, I liked." "radio"
    And I press "Submit questionnaire"
    And I am on the "Course 1" "course > activities > questionnaire" page
    Then the following should exist in the "Table listing all Questionnaire activities" table:
      | Name            | Due date       | Status       | Responded |
      | Questionnaire 1 | 1 January 2040 | Mark as done |           |
      | Questionnaire 2 | -              | -            | -         |
      | Questionnaire 3 | -              | -            | -         |
      | Questionnaire 4 | -              | -            | -         |
    And I should not see "Actions" in the "questionnaire_overview_collapsible" "region"
    # Check Responded column.
    And "Answered" "icon" should exist in the "Questionnaire 1" "table_row"
    And "Answered" "icon" should not exist in the "Questionnaire 2" "table_row"

  @javascript
  Scenario: Teachers can see relevant columns and content in the questionnaire overview
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Questionnaire 1"
    And I navigate to "Answer the questions..." in current page administration
    And I should see "Questionnaire 1"
    And I should see "What did you think of these movies?"
    And I click on "Row 2, Star Wars: Column 5, I liked." "radio"
    And I click on "Row 3, Casablanca: Column 5, I liked." "radio"
    And I click on "Row 4, Airplane: Column 5, I liked." "radio"
    And I press "Submit questionnaire"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Questionnaire 1"
    And I navigate to "Answer the questions..." in current page administration
    And I should see "Questionnaire 1"
    And I should see "What did you think of these movies?"
    And I click on "Row 2, Star Wars: Column 5, I liked." "radio"
    And I click on "Row 3, Casablanca: Column 5, I liked." "radio"
    And I click on "Row 4, Airplane: Column 5, I liked." "radio"
    And I press "Submit questionnaire"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Questionnaire 2"
    And I navigate to "Answer the questions..." in current page administration
    And I should see "Questionnaire 2"
    And I should see "What did you think of these movies?"
    And I click on "Row 2, Star Wars: Column 5, I liked." "radio"
    And I click on "Row 3, Casablanca: Column 5, I liked." "radio"
    And I click on "Row 4, Airplane: Column 5, I liked." "radio"
    And I press "Submit questionnaire"
    And I log out
    And I am on the "Course 1" "course > activities > questionnaire" page logged in as "teacher1"
    Then the following should exist in the "Table listing all Questionnaire activities" table:
      | Name            | Due date       | Students who responded | Actions |
      | Questionnaire 1 | 1 January 2040 | 2                      | View    |
      | Questionnaire 2 | -              | 1                      | View    |
      | Questionnaire 3 | -              | 0                      | View    |
      | Questionnaire 4 | -              | 0                      | View    |
    And I should not see "Status" in the "questionnaire_overview_collapsible" "region"
    And I should not see "Responded" in the "questionnaire_overview_collapsible" "region"

  Scenario: The questionnaire index redirect to the activities overview
    When I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Activities" block
    And I click on "Questionnaires" "link" in the "Activities" "block"
    Then I should see "An overview of all activities in the course"
    And I should see "Name" in the "questionnaire_overview_collapsible" "region"
    And I should see "Due date" in the "questionnaire_overview_collapsible" "region"
