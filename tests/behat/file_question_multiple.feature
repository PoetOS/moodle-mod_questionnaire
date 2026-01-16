@mod @mod_questionnaire
Feature: Add a question requiring more than one file upload questions in a questionnaire.
  In order to use this plugin
  As a student
  I need to add two files in a questionnaire and be able to save and resume the questionnaire.

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
      | activity      | name                              | description                    | course | idnumber       | resume | navigate |
      | questionnaire | Test questionnaire multiple files | Test questionnaire description | C1     | questionnaire0 | 1      | 1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire multiple files"
    And I navigate to "Questions" in current page administration
    And I add a "Check Boxes" question and I fill the form with:
      | Question Name    | Q1               |
      | Yes              | Yes              |
      | Question Text    | Please answer Q1 |
      | Possible answers | yes,no,may be    |
    And I add a "File" question and I fill the form with:
      | Question Name    | File question One      |
      | Yes              | Yes                    |
      | Question Text    | Add file1 as an answer |
    And I add a "File" question and I fill the form with:
      | Question Name    | File question Two      |
      | Yes              | No                     |
      | Question Text    | Add file2 as an answer |
    And I log out

  @javascript @_file_upload
  Scenario: Add one file to the questionnaire and verify that the uploaded files exists in the filepicker.
    Given I log in as "student1"
    When I am on the "Test questionnaire multiple files" "questionnaire activity" page
    And I navigate to "Answer the questions..." in current page administration
    And I upload "mod/questionnaire/tests/fixtures/testfilequestion.pdf" to questionnaire "Add file1 as an answer" filemanager
    And I press "Submit questionnaire"
    And I should not see "Thank you for completing this Questionnaire"
    And I should see "Please answer required question #1."
    And I should see "testfilequestion.pdf" in the "(//*[contains(@class, 'filemanager-container')])[1]//*[contains(@class, 'fp-filename')]" "xpath"
    And I should see "You can drag and drop files here to add them." in the "(//*[contains(@class, 'filemanager-container')])[2]//*[contains(@class, 'dndupload-message')]" "xpath"
    And I upload "mod/questionnaire/tests/fixtures/testfilequestion2.pdf" to questionnaire "Add file2 as an answer" filemanager
    And I press "Submit questionnaire"
    And I should not see "Thank you for completing this Questionnaire"
    And I should see "Please answer required question #1."
    And I set the field "may be" to "checked"
    And I press "Submit questionnaire"
    And I should see "Thank you for completing this Questionnaire"
    And I press "Continue"
    And I should see "View your response(s)"
    And ".resourcecontent.resourcepdf" "css_element" should exist
    And I log out
    And I log in as "teacher1"
    And I am on the "Test questionnaire multiple files" "questionnaire activity" page
    And I navigate to "View all responses" in current page administration
    Then I should see "testfilequestion.pdf"
    And I should see "testfilequestion2.pdf"

  @javascript @_file_upload
  Scenario: Add one file at a time when the questionnaire is saved and resumed and check that the filepicker contains the uploaded files.
    Given I log in as "student1"
    When I am on the "Test questionnaire multiple files" "questionnaire activity" page
    And I navigate to "Answer the questions..." in current page administration
    And I upload "mod/questionnaire/tests/fixtures/testfilequestion.pdf" to questionnaire "Add file2 as an answer" filemanager
    And I press "Save and exit"
    And I should see "Your progress has been saved."
    And I click on "//a[contains(@class, 'btn-primary')][contains(@href, 'resume=1')]" "xpath_element"
    And I should see "testfilequestion.pdf" in the "(//*[contains(@class, 'filemanager-container')])[2]//*[contains(@class, 'fp-filename')]" "xpath"
    And I should see "You can drag and drop files here to add them." in the "(//*[contains(@class, 'filemanager-container')])[1]//*[contains(@class, 'dndupload-message')]" "xpath"
    And I upload "mod/questionnaire/tests/fixtures/testfilequestion2.pdf" to questionnaire "Add file1 as an answer" filemanager
    And I press "Save and exit"
    And I should see "Your progress has been saved."
    And I click on "//a[contains(@class, 'btn-primary')][contains(@href, 'resume=1')]" "xpath_element"
    And I should see "testfilequestion2.pdf" in the "(//*[contains(@class, 'filemanager-container')])[1]//*[contains(@class, 'fp-filename')]" "xpath"
    And I should see "testfilequestion.pdf" in the "(//*[contains(@class, 'filemanager-container')])[2]//*[contains(@class, 'fp-filename')]" "xpath"
    And I set the field "may be" to "checked"
    And I press "Submit questionnaire"
    And I should see "Thank you for completing this Questionnaire"
    And I log out
    And I log in as "teacher1"
    And I am on the "Test questionnaire multiple files" "questionnaire activity" page
    And I navigate to "View all responses" in current page administration
    Then I should see "testfilequestion.pdf"
    And I should see "testfilequestion2.pdf"
