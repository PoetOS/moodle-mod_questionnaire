@mod @mod_questionnaire @mod_questionnaire_sorting @javascript
Feature: Add sorting questions to a questionnaire activity
  In order to conduct surveys of the users in a course
  As a teacher
  I need to add a questionnaire activity with sorting questions to a moodle course

  Background: Add a questionnaire to a course with one of each question type
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Sorting" question and I fill the form with:
      | Question Name | Sorting-001      |
      | Direction     | Vertical         |
      | Question Text | Sorting-001 text |
      | id_answer_0   | Sorting item 1   |
      | id_answer_1   | Sorting item 2   |
      | id_answer_2   | Sorting item 3   |

  @javascript
  Scenario: Basic create a sorting questionnaire.
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Sorting-001 text"
    And I should see "Please rank the following items. It is OK if you don't need to change anything."
    And I should see "Sorting item 1"
    And I should see "Sorting item 2"
    And I should see "Sorting item 3"

  @javascript
  Scenario: Student answers the student and teacher view the responses.
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Sorting-001 text"
    And I drag "Sorting item 1" to space "3" in the sorting question
    And I press "Submit questionnaire"
    And I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    And I should see "View your response(s)"
    And I should see "Test questionnaire"
    And I should see "Sorting-001 text"
    And I should see "Respondent: Student 1"
    And "//*[contains(@class, 'qn-sorting-list')]/li[1][contains(., 'Sorting item 2')]" "xpath_element" should exist
    And "//*[contains(@class, 'qn-sorting-list')]/li[2][contains(., 'Sorting item 3')]" "xpath_element" should exist
    And "//*[contains(@class, 'qn-sorting-list')]/li[3][contains(., 'Sorting item 1')]" "xpath_element" should exist
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    And I press "Submit questionnaire"
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "View all responses" in current page administration
    And I should see "All participants."
    And I should see "View Default order"
    And I should see "Student 1"
    And "//*[contains(@class, 'qn-sorting-container')][1]//ol[contains(@class, 'qn-sorting-list')]/li[1][contains(., 'Sorting item 2')]" "xpath_element" should exist
    And "//*[contains(@class, 'qn-sorting-container')][1]//ol[contains(@class, 'qn-sorting-list')]/li[2][contains(., 'Sorting item 3')]" "xpath_element" should exist
    And "//*[contains(@class, 'qn-sorting-container')][1]//ol[contains(@class, 'qn-sorting-list')]/li[3][contains(., 'Sorting item 1')]" "xpath_element" should exist
    And I should see "Student 2"
    And "//*[contains(@class, 'qn-sorting-container')][1]//ol[contains(@class, 'qn-sorting-list')]/li[1][contains(., 'Sorting item 1')]" "xpath_element" should exist
    And "//*[contains(@class, 'qn-sorting-container')][1]//ol[contains(@class, 'qn-sorting-list')]/li[2][contains(., 'Sorting item 2')]" "xpath_element" should exist
    And "//*[contains(@class, 'qn-sorting-container')][1]//ol[contains(@class, 'qn-sorting-list')]/li[3][contains(., 'Sorting item 3')]" "xpath_element" should exist
