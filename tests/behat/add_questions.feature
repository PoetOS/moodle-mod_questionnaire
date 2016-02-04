@mod @mod_questionnaire
Feature: Add questions to a questionnaire activity
  In order to conduct surveys of the users in a course
  As a teacher
  I need to add a questionnaire activity with questions to a moodle course

@javascript
  Scenario: Add a questionnaire to a course with one of each question type
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
    And I log in as "teacher1"
    And I add a questionnaire "Test questionnaire" to the course "Course 1" and start to enter questions
    And I add a "Check Boxes" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Min. forced responses | 1 |
      | Max. forced responses | 2 |
      | Question Text | Select one or two choices only |
      | Possible answers | One,Two,Three,Four |
    And I should see "[Check Boxes] (Q1)"
    And I should see "Select one or two choices only"
    And I add a "Date" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Question Text | Enter today's date |
    And I should see "[Date] (Q2)"
    And I should see "Enter today's date"
    And I add a "Dropdown Box" question and I fill the form with:
      | Question Name | Q3 |
      | No | n |
      | Question Text | Select one choice |
      | Possible answers | One,Two,Three,Four |
    And I should see "[Dropdown Box] (Q3)"
    And I should see "Select one choice"
    And I add a "Essay Box" question and I fill the form with:
      | Question Name | Q4 |
      | No | n |
      | Response format | 0 |
      | Input box size | 10 lines |
      | Question Text | Enter your essay |
    And I should see "[Essay Box] (Q4)"
    And I should see "Enter your essay"
    And I add a "Label" question and I fill the form with:
      | Question Text | Section header |
    And I should see "[Label]"
    And I should see "Section header"
    And I add a "Numeric" question and I fill the form with:
      | Question Name | Q5 |
      | Yes | y |
      | Max. digits allowed | 4 |
      | Nb of decimal digits | 1 |
      | Question Text | Enter a number with a decimal |
    And I should see "[Numeric] (Q5)"
    And I should see "Enter a number with a decimal"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q6 |
      | Yes | y |
      | Horizontal | Checked |
      | Question Text | Select one choice |
      | Possible answers | One,Two,Three,Four |
    And I should see "[Radio Buttons] (Q6)"
    And I should see "Select one choice"
    And I add a "Rate (scale 1..5)" question and I fill the form with:
      | Question Name | Q7 |
      | Yes | y |
      | Nb of scale items | 4 |
      | Type of rate scale | N/A column |
      | Question Text | Rate these |
      | Possible answers | One,Two,Three,Four |
    And I should see "[Rate (scale 1..5)] (Q7)"
    And I should see "Rate these"
    And I add a "Text Box" question and I fill the form with:
      | Question Name | Q8 |
      | No | n |
      | Input box length | 10 |
      | Max. text length | 15 |
      | Question Text | Enter some text |
    And I should see "[Text Box] (Q8)"
    And I should see "Enter some text"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q9 |
      | Yes | y |
      | Question Text | Choose yes or no |
    And I should see "[Yes/No] (Q9)"
    And I should see "Choose yes or no"
    And I set the field "id_type_id" to "----- Page Break -----"
    And I press "Add selected question type"
    And I should see "[----- Page Break -----]"