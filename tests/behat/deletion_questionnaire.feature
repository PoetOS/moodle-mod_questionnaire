@mod @mod_questionnaire @_file_upload
Feature: Deletion questions area
  In order to manage deletion question of questionnaire in a course
  As a teacher
  I need to manage the delete questions in questionnaire.
  And as admin
  I need to setup time for schedule task to run cron job to deteting questionnaire.

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
    And the following config values are set as admin:
      | enableasyncbackup | 0 |
    And I log in as "teacher1"
    And I am on the "Course 1" "restore" page
    And I press "Manage course backups"
    And I upload "mod/questionnaire/tests/fixtures/backup-activity-questionnaire.mbz" file to "Files" filemanager
    And I press "Save changes"
    Then I restore "backup-activity-questionnaire.mbz" backup into "Course 1" course using this options:

  @javascript
  Scenario: Manage deletion questionnaire area
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "My Questionnaire 1"
    And I navigate to "Questions" in current page administration
    And I should see "Question deletion area"
    And I should see "[Dropdown Box] (Demo dropdown 1)"
    And I should see "Demo dropdown 1"
    And I should see "[Numeric] (Demo numeric 1)"
    And I should see "Demo numeric 1"
    And I should see "NA"
    And I click on "Move to deletion area" "link" in the "[Check Boxes] (Demo checkbox 1)" "list_item"
    And I should see "Confirm"
    And I should see "Are you sure you want to move the question at position 1 (Demo checkbox 1) to the deletion area?"
    And I press "Yes"
    And I should not see "[Check Boxes] (Demo checkbox 1)" in the "questionnaire-questionlist" "region"
    And I wait until the page is ready
    And I click on "Restore this question" "link" in the "[Numeric] (Demo numeric 1)" "list_item"
    And I wait until the page is ready
    And "//li[contains(concat(' ', normalize-space(@class), ' '), ' restored-question ')]" "xpath_element" should exist
    And I click on "Permanently delete question" "link" in the "[Dropdown Box] (Demo dropdown 1)" "list_item"
    And I should see "Are you sure you want to permanently delete this question?"
    And I should see "Demo dropdown 1"
    And I should see "NA"
    And I press "Yes"
    Then I should not see "[Dropdown Box] (Demo dropdown 1)" in the "questionnaire-recyclebin" "region"

  @javascript
  Scenario: Cron task for deletion questionnaire
    And I custom deleted date in table questionnaire question for cron task
    Given I log in as "admin"
    And I navigate to "Server > Tasks > Scheduled tasks" in site administration
    And I click on "Empty Questionnaire 'Recycle bin'" "link"
    And I set the field "id_minute" to "*/1"
    And I set the field "id_day" to "*"
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "My Questionnaire 1"
    Then I navigate to "Questions" in current page administration
    Then I click on "Move to deletion area" "link" in the "[Check Boxes] (Demo checkbox 1)" "list_item"
    Then I press "Yes"
    And I wait "61" seconds
    Then I trigger cron
    And I am on "Course 1" course homepage
    And I follow "My Questionnaire 1"
    When I navigate to "Questions" in current page administration
    Then I should not see "[Dropdown Box] (Demo dropdown 1)" in the "questionnaire-recyclebin" "region"
    And I should not see "[Numeric] (Demo numeric 1)" in the "questionnaire-recyclebin" "region"
    And I should see "[Check Boxes] (Demo checkbox 1)" in the "questionnaire-recyclebin" "region"
    And I navigate to "Plugins > Activity modules > Questionnaire" in site administration
    And I set the field "Delete questions older than" to "0"
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "My Questionnaire 1"
    And I navigate to "Questions" in current page administration
    And I should see "Automatic deletion is disabled"
