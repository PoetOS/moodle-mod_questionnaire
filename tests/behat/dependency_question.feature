@mod @mod_questionnaire
Feature: Questions can be defined to be dependent on answers to multiple previous questions
  In order to define a dependency
  As a teacher
  I must specify that branching questions are allowed and then create question dependencies

  Background: Add a text box question that is dependent on a yes answer to a yes/no question.
    Given the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    And the following "users" exist:
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
      | questionnaire | Test questionnaire 2 | Test questionnaire description | C1 | questionnaire1 | 1 | 1 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Do you own a car? |
    Then I should see "[Yes/No] (Q1)"
    And I should see "Do you own a car?"
    And I add a "Text Box" question and I fill the form with:
      | Question Name | Q2a |
      | No | n |
      | Input box length | 10 |
      | Max. text length | 15 |
      | id_dependquestions_and_0  | Q1->Yes |
      | Question Text | What colour is the car? |
    Then I should see "[Text Box] (Q2a)"
    And I should see "What colour is the car?"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q2b |
      | No | n |
      | id_dependquestions_and_0  | Q1->No |
      | Question Text | Will you buy a car this year? |
    Then I should see "[Yes/No] (Q2b)"
    And I should see "Will you buy a car this year?"
    And I log out

  @javascript
  Scenario: Student should only be asked for the car colour if they have answered yes to question 1.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Do you own a car?"
    # And I set the field "Do you own a car?" to "y"
    And I click on "Yes" "radio"
    And I press "Next Page >>"
    Then I should see "What colour is the car?"
    And I press "<< Previous Page"
    Then I should see "Do you own a car?"
    # And I set the field "Do you own a car?" to "n"
    And I click on "No" "radio"
    And I press "Next Page >>"
    Then I should see "Will you buy a car this year?"

  @javascript
  Scenario: Add a text box question that is dependent on a multilang dropdown box question.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire 2"
    And I navigate to "Questions" in current page administration
    And I set the field "id_type_id" to "Dropdown Box"
    And I press "Add selected question type"
    And I set the field "Question Name" to "Q1"
    And I set the field "No" to "n"
    And I set the field "Question Text" to "What person are you?"
    And I set the field "Possible answers" to multiline:
    """
    <span lang="de" class="multilang">Katze</span><span lang="en" class="multilang">Cat</span>
    <span lang="de" class="multilang">Hund</span><span lang="en" class="multilang">Dog</span>
    """
    And I press "Save changes"
    Then I should see "[Dropdown Box] (Q1)"
    And I should see "What person are you?"
    And I add a "Text Box" question and I fill the form with:
      | Question Name | Q2a |
      | No | n |
      | Input box length | 10 |
      | Max. text length | 15 |
      | id_dependquestions_and_0  | Q1->Cat |
      | Question Text | What name has your cat? |
    Then I should see "[Text Box] (Q2a)"
    And I should see "What name has your cat?"
    And I should see "Parent Question : position 1 (Q1->Cat) set"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q2b |
      | No | n |
      | id_dependquestions_and_0  | Q1->Dog |
      | Question Text | Do you own a dog? |
    Then I should see "[Yes/No] (Q2b)"
    And I should see "Do you own a dog?"
    And I should see "Parent Question : position 1 (Q1->Dog) set"
    And I log out
