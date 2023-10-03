@mod @mod_questionnaire
Feature: In questionnaire, keywords (DISC) personality tests can be constructed using feedback on specific question responses and questions can be
  assigned to multiple sections.
  In order to define a DISC personality test (Dominance, Inducement, Submission, and Compliance).
  As a teacher
  I must add the required question types and complete the feedback options with more than one section per question.

  @javascript
  Scenario: Create a questionnaire with a feeback question types and add more than one feedback section.
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
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    And I am on the "Test questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    Then I should see "Add questions"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Horizontal | Checked |
      | Question Text | When faced with a challenge, how do you react? |
      | Possible answers | Dominant=Take charge immediately.,Conscientious=Carefully assess the situation before acting.,Steady=Seek guidance and support from others.,Influential=Avoid confrontation and hope the issue resolves itself. |
    Then I should see "[Radio Buttons] (Q1)"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Horizontal | Checked |
      | Question Text | How do you approach social situations? |
      | Possible answers | Influential=Eagerly initiate conversations and take the lead.,Conscientious=Observe and listen before contributing.,Steady=Form close connections with a few individuals.,Dominant=Prefer to spend time alone or with a small group of close friends. |
    Then I should see "[Radio Buttons] (Q2)"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q3 |
      | Yes | y |
      | Horizontal | Checked |
      | Question Text | How do you handle unexpected changes to your plans? |
      | Possible answers | Conscientious=Feel uneasy and take time to adjust.,Steady=Seek support and guidance from others.,Influential=Quickly adapt and find alternative solutions.,Dominant=Stick to the original plan and hope for the best. |
    Then I should see "[Radio Buttons] (Q3)"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q4 |
      | Yes | y |
      | Horizontal | Checked |
      | Question Text | How do you express your emotions? |
      | Possible answers | Steady=Carefully and considerately.,Dominant=Privately and discreetly.,Influential=Openly and passionately.,Conscientious=Thoughtfully and logically. |
    Then I should see "[Radio Buttons] (Q4)"
    And I follow "Feedback"
    And I should see "Feedback options"
    And I set the field "id_feedbacksections" to "Feedback sections"
    And I set the field "id_feedbackscores" to "Yes"
    And I set the field "id_feedbacknotes" to "These are the main Feedback notes"
    And I press "Save settings and edit Feedback Sections"
    Then I should see "[New section] section heading"
    And I should not see "[New section] section questions"
    And I set the field "id_sectionlabel" to "Conscientious"
    And I set the field "id_sectionheading" to "Conscientious"
    And I press "Save changes"
    And I follow "Conscientious section messages"
    And I set the field "id_feedbacktext_0" to "Feedback 1 100%"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "Feedback 1 50%"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "Feedback 1 20%"
    And I press "Save changes"
    And I set the field "id_newsectionlabel" to "Dominant"
    And I press "Add new section"
    And I set the field "id_sectionheading" to "Dominant"
    And I press "Save changes"
    And I follow "Dominant section messages"
    And I set the field "id_feedbacktext_0" to "Feedback 2 100%"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "Feedback 2 50%"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "Feedback 2 20%"
    And I press "Save changes"
    And I set the field "id_newsectionlabel" to "Influential"
    And I press "Add new section"
    And I set the field "id_sectionheading" to "Influential"
    And I press "Save changes"
    And I follow "Influential section messages"
    And I set the field "id_feedbacktext_0" to "Feedback 3 100%"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "Feedback 3 50%"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "Feedback 3 20%"
    And I press "Save changes"
    And I set the field "id_newsectionlabel" to "Steady"
    And I press "Add new section"
    And I set the field "id_sectionheading" to "Steady"
    And I press "Save changes"
    And I follow "Steady section messages"
    And I set the field "id_feedbacktext_0" to "Feedback 4 100%"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "Feedback 4 50%"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "Feedback 4 20%"
    And I press "Save changes"
    And I log out

#  Scenario: Student completes feedback questions.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "When faced with a challenge, how do you react?"
    And I click on "Take charge immediately" "radio"
    And I click on "Eagerly initiate conversations and take the lead." "radio"
    And I click on "Quickly adapt and find alternative solutions." "radio"
    And I click on "Thoughtfully and logically." "radio"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "View your response(s)"
    Then I should see "Conscientious"
    And I should see "25%"
    And I should see "Dominant"
    And I should see "25%"
    And I should see "Influential"
    And I should see "50%"
    And I should see "Steady"
    And I should see "0%"
    And I log out
