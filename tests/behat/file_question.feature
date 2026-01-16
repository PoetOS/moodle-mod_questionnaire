@mod @mod_questionnaire
Feature: Add a question requiring a file upload in questionnaire.
  In order to use this plugin
  As a teacher
  I need to add a a file question to a questionnaire created in my course
  and a student answers to it. Then the file has to be accessible.

  Background:
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
      | activity      | name               | description                    | course | idnumber       | resume | navigate |
      | questionnaire | Test questionnaire | Test questionnaire description | C1     | questionnaire0 | 1      | 1        |

  @javascript @_file_upload
  Scenario: Add a single file question to a questionnaire and view an answer with an uploaded file.
    Given I log in as "teacher1"
    When I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Questions" in current page administration
    And I should see "Add questions"
    And I add a "File" question and I fill the form with:
      | Question Name | File question           |
      | Yes           | Yes                     |
      | Question Text | Add a file as an answer |
    And I log out
    And I log in as "student1"
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Answer the questions..." in current page administration
    And I upload "mod/questionnaire/tests/fixtures/testfilequestion.pdf" to questionnaire "Add a file as an answer" filemanager
    And I press "Submit questionnaire"
    And I should see "Thank you for completing this Questionnaire"
    And I press "Continue"
    And I should see "View your response(s)"
    And ".resourcecontent.resourcepdf" "css_element" should exist
    And I log out
    And I log in as "teacher1"
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "View all responses" in current page administration
    Then I should see "testfilequestion.pdf"

  @javascript @_file_upload
  Scenario: Add two file questions to a questionnaire and view an answer with two uploaded file.
    Given I log in as "teacher1"
    When I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Questions" in current page administration
    And I should see "Add questions"
    And I add a "File" question and I fill the form with:
      | Question Name | File question one             |
      | Yes           | Yes                           |
      | Question Text | Add a first file as an answer |
    And I add a "File" question and I fill the form with:
      | Question Name | File question two              |
      | Yes           | Yes                            |
      | Question Text | Add a second file as an answer |
    And I log out
    And I log in as "student1"
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "Answer the questions..." in current page administration
    And I upload "mod/questionnaire/tests/fixtures/testfilequestion.pdf" to questionnaire "Add a first file as an answer" filemanager
    And I upload "mod/questionnaire/tests/fixtures/testfilequestion2.pdf" to questionnaire "Add a second file as an answer" filemanager
    And I press "Submit questionnaire"
    And I should see "Thank you for completing this Questionnaire"
    And I press "Continue"
    And I should see "View your response(s)"
    And ".resourcecontent.resourcepdf" "css_element" should exist
    And I log out
    And I log in as "teacher1"
    And I am on the "Test questionnaire" "questionnaire activity" page
    And I navigate to "View all responses" in current page administration
    Then I should see "testfilequestion.pdf"
    And I should see "testfilequestion2.pdf"
