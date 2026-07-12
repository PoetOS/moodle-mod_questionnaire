@mod @mod_questionnaire
Feature: When the questionnaire usergraph site setting is enabled and a feedback section has a chart type set, the student's response view renders the RGraph chart canvas alongside the feedback text.
  In order to verify the chart rendering pipeline
  As a developer
  I need a Behat scenario that exercises the usergraph code path.

  Background:
    Given the following config values are set as admin:
      | usergraph | 1 | questionnaire |
    And the following "users" exist:
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
      | activity      | name               | description           | course | idnumber       | resume | navigate |
      | questionnaire | Test questionnaire | Test questionnaire    | C1     | questionnaire0 | 1      | 1        |

  @javascript
  Scenario: A submitted response renders the chart canvas in the feedback view.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name    | Q1                       |
      | Yes              | y                        |
      | Horizontal       | Checked                  |
      | Question Text    | Pick one                 |
      | Possible answers | 1=One,2=Two,3=Three      |
    Then I should see "[Radio Buttons] (Q1)"
    And I navigate to "Feedback" in current page administration
    And I set the field "id_feedbacksections" to "Global Feedback"
    And I set the field "id_feedbackscores" to "Yes"
    And I set the field "id_chart_type_global" to "Bipolar bars"
    And I set the field "id_feedbacknotes" to "Feedback notes here"
    And I press "Save settings and edit Feedback Sections"
    Then I should see "Global Feedback heading"
    And I set the field "id_sectionlabel" to "Global feedback label"
    And I set the field "id_sectionheading" to "Global section heading"
    And I set the field "id_feedbacktext_0" to "Feedback band A"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "Feedback band B"
    And I press "Save changes"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    And I click on "Three" "radio"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "Feedback notes here"
    And I should see "Global feedback label"
    And "//canvas[starts-with(@id, 'questionnaire-chart-')]" "xpath_element" should exist
    And I log out
