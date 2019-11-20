@mod @mod_questionnaire
Feature: Questionnaires can use an existing public survey to gather responses in one place.
  When a student answers the same public questionnaire in two different course instances
  The answers will be visible only in those course instances.

  Background: Add a public questionnaire and use it in two different course.
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | teacher2 | Teacher | 2 | teacher2@example.com |
      | teacher3 | Teacher | 3 | teacher3@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
      | Course 3 | C3 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | manager |
      | teacher2 | C2 | editingteacher |
      | student1 | C2 | student |
      | teacher3 | C3 | editingteacher |
      | student1 | C3 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questionnaire | Public questionnaire | Anonymous questionnaire description | C1 | questionnaire0 |

    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Public questionnaire"
    And I navigate to "Advanced settings" in current page administration
    And I set the field "realm" to "public"
    And I press "Save and display"
    And I navigate to "Questions" in current page administration
    And I add a "Numeric" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Enter a number |
    And I log out

    And I log in as "teacher2"
    And I am on "Course 2" course homepage with editing mode on
    And I follow "Add an activity or resource"
    And I click on "Questionnaire" "radio"
    And I click on "Add" "button" in the "Add an activity or resource" "dialogue"
    And I set the field "Name" to "Questionnaire instance 1"
    And I expand all fieldsets
    Then I should see "Content options"
    And I click on "Public questionnaire [Course 1]" "radio"
    And I press "Save and display"
    Then I should see "Questionnaire instance 1"
    And I log out

    And I log in as "teacher3"
    And I am on "Course 3" course homepage with editing mode on
    And I follow "Add an activity or resource"
    And I click on "Questionnaire" "radio"
    And I click on "Add" "button" in the "Add an activity or resource" "dialogue"
    And I set the field "Name" to "Questionnaire instance 2"
    And I expand all fieldsets
    Then I should see "Content options"
    And I click on "Public questionnaire [Course 1]" "radio"
    And I press "Save and display"
    Then I should see "Questionnaire instance 2"
    And I log out

  @javascript
  Scenario: Student completes public questionnaire instances in two different courses and sees each response in the proper course
    And I log in as "student1"
    And I am on "Course 2" course homepage
    And I follow "Questionnaire instance 1"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Questionnaire instance 1"
    And I set the field "Enter a number" to "1"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "Your response"
    And I should see "Enter a number"
    And "//div[contains(@class,'questionnaire_numeric') and contains(@class,'questionnaire_response')]//span[@class='selected' and text()='1']" "xpath_element" should exist

    And I am on "Course 3" course homepage
    And I follow "Questionnaire instance 2"
    And I should see "Answer the questions..."
    And I should not see "Your response"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Questionnaire instance 2"
    And I set the field "Enter a number" to "2"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "Your response"
    And I should see "Enter a number"
    And "//div[contains(@class,'questionnaire_numeric') and contains(@class,'questionnaire_response')]//span[@class='selected' and text()='2']" "xpath_element" should exist

    And I am on "Course 2" course homepage
    And I follow "Questionnaire instance 1"
    Then I should see "Your response"
    And I navigate to "Your response" in current page administration
    And I should see "Enter a number"
    And "//div[contains(@class,'questionnaire_numeric') and contains(@class,'questionnaire_response')]//span[@class='selected' and text()='1']" "xpath_element" should exist

    And I am on "Course 3" course homepage
    And I follow "Questionnaire instance 2"
    Then I should see "Your response"
    And I navigate to "Your response" in current page administration
    And I should see "Enter a number"
    And "//div[contains(@class,'questionnaire_numeric') and contains(@class,'questionnaire_response')]//span[@class='selected' and text()='2']" "xpath_element" should exist
    And I log out