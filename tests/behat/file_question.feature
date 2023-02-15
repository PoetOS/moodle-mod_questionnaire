@mod @mod_questionnaire
Feature: In questionnaire, we can add a question requiring a file upload.

  @javascript @_file_upload
  Scenario: As a teacher, I create a questionnaire in my course with a file question and a student answers to it. Then the file has to be accessible.
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

    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    Then I should see "Add questions"
    And I add a "File" question and I fill the form with:
      | Question Name | File question |
      | Yes | Yes |
      | Question Text | Add a file as an answer |
    And I log out

    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    When I upload "mod/questionnaire/tests/fixtures/testfilequestion.pdf" to questionnaire filemanager
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    And I should see "Your response"
    And I log out

    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    When I navigate to "View All Responses" in current page administration
    Then I should see "testfilequestion.pdf"
    And I follow "student1"
    # Todo find how to check if the pdf viewer is there.
    And I log out
