@mod @mod_questionnaire
Feature: In questionnaire, personality tests of the DISC assessment type can be constructed using feedback on specific question responses.
  In order to define a feedback questionnaire of the DISC assessment Test type
  As a teacher
  I must add questions of the required question type i.e. radio buttons with a string keyword instead of a value for each possible answer
  and complete the feedback options by creating sections with labels corresponding exactly to the keywords used in the radio buttons possible answers.

  @javascript
  Scenario: Create a questionnaire with radio buttons questions with keywords and the relevant feedback sections.
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
    And I am on the "Test questionnaire" "mod_questionnaire > questions" page logged in as "teacher1"
    Then I should see "Add questions"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | When faced with a challenge, how do you react? |
      | Possible answers | Dominant=Take charge immediately.,Conscientious=Carefully assess the situation before acting.,Steady=Seek guidance and support from others.,Influential=Avoid confrontation and hope the issue resolves itself.|
    Then I should see "[Radio Buttons] (Q1)"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Question Text | How do you approach social situations? |
      | Possible answers | Influential=Eagerly initiate conversations and take the lead.,Conscientious=Observe and listen before contributing.,Steady=Form close connections with a few individuals.,Dominant=Prefer to spend time alone or with a small group of close friends. |
    Then I should see "[Radio Buttons] (Q2)"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q3 |
      | Yes | y |
      | Question Text | How do you handle unexpected changes to your plans? |
      | Possible answers | Conscientious=Feel uneasy and take time to adjust.,Steady=Seek support and guidance from others.,Influential=Quickly adapt and find alternative solutions.,Dominant=Stick to the original plan and hope for the best.|
    Then I should see "[Radio Buttons] (Q3"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q4 |
      | Yes | y |
      | Question Text | How do you express your emotions? |
      | Possible answers | Steady=Carefully and considerately.,Dominant=Privately and discreetly.,Influential=Openly and passionately.,Conscientious=Thoughtfully and logically.|
    Then I should see "[Radio Buttons] (Q4)"
    And I follow "Feedback"
    And I should see "Feedback options"
    # The field "id_feedbacksections" is default set to "Feedback sections"
    And I set the field "id_feedbackscores" to "Yes"
    And I set the field "id_feedbacknotes" to "These are the main Feedback notes"
    And I press "Save settings and edit Feedback Sections"
    Then I should see "[New section]"
    And I set the field "id_sectionlabel" to "Conscientious"
    And I set the field "id_sectionheading" to "Conscientious heading"
    And I press "Save changes"
    And I follow "Conscientious section messages"
    And I set the field "id_feedbacktext_0" to "You seem to have a conscientious style"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "You seem to have an average conscientious style"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "You seem to have a less conscientious style"
    And I press "Save changes"
    And I set the field "id_newsectionlabel" to "Dominant"
    And I press "Add new section"
    And I set the field "id_sectionheading" to "Dominant heading"
    And I follow "Dominant section messages"
    And I set the field "id_feedbacktext_0" to "You seem to have a dominance style"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "You seem to have an average dominance style"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "You seem to have a less dominance style"
    And I press "Save changes"
    And I set the field "id_newsectionlabel" to "Influential"
    And I press "Add new section"
    And I set the field "id_sectionheading" to "Influential heading"
    And I follow "Influential section messages"
    And I set the field "id_feedbacktext_0" to "You seem to have an influential style"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "You seem to have an average influential style"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "You seem to have a less influential style"
    And I press "Save changes"
    And I set the field "id_newsectionlabel" to "Steady"
    And I press "Add new section"
    And I set the field "id_sectionheading" to "Steady heading"
    And I follow "Steady section messages"
    And I set the field "id_feedbacktext_0" to "You seem to have a steady style"
    And I set the field "id_feedbackboundaries_0" to "50"
    And I set the field "id_feedbacktext_1" to "You seem to have an average steady style"
    And I set the field "id_feedbackboundaries_1" to "20"
    And I set the field "id_feedbacktext_2" to "You seem to have a less steady style"
    And I press "Save changes"
    And I log out

#  Scenario: Student completes feedback questions.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    #Q1 "When faced with a challenge, how do you react?"
    And I click on "Take charge immediately." "radio"
    #Q2 "How do you approach social situations?"
    And I click on "Eagerly initiate conversations and take the lead." "radio"
    #Q3 "How do you handle unexpected changes to your plans?"
    And I click on "Stick to the original plan and hope for the best." "radio"
    #Q4 "How do you express your emotions?"
    And I click on "Thoughtfully and logically." "radio"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "View your response(s)"
    And I should see "Feedback Report"
    And I should see "You seem to have an average conscientious style"
    And I should see "You seem to have a dominance style"
    And I should see "You seem to have an average influential style"
    And I should see "You seem to have a less steady style"
    And I should see "These are the main Feedback notes"
    And I log out

#  Scenario: Another student completes feedback questions differently.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    #Q1 "When faced with a challenge, how do you react?"
    And I click on "Carefully assess the situation before acting." "radio"
    #Q2 "How do you approach social situations?"
    And I click on "Observe and listen before contributing." "radio"
    #Q3 "How do you handle unexpected changes to your plans?"
    And I click on "Seek support and guidance from others." "radio"
    #Q4 "How do you express your emotions?"
    And I click on "Privately and discreetly." "radio"
    And I press "Submit questionnaire"
    Then I should see "Thank you for completing this Questionnaire."
    And I press "Continue"
    Then I should see "View your response(s)"
    And I should see "Feedback Report"
    And I should see "You seem to have a conscientious style"
    And I should see "You seem to have an average dominance style"
    And I should see "You seem to have a less influential style"
    And I should see "You seem to have an average steady style"
    And I should see "These are the main Feedback notes"
    And I log out
