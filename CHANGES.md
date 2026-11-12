# Release Notes #

## Release 5.3.1 (Build - 2026111201) ##
  * Security and bug fix release for Moodle 5.3 forward.
  * Supports back to Moodle 5.0 (now requires the 5.0 release, 2025041400, or later).
  * All sites should upgrade to this release in order to guarantee security.

  ### Security fixes: ###
  Issues uncovered by [MDLShield](https://mdlshield.com/) reviews.

  Access and permissions:
  * The Moodle App web services now check that the user and response belong together.
  * Final submission uses the server's response id, and every page of a response is checked as complete before it is submitted.
  * Report deletes and downloads require a sesskey.
  * Previews and printouts require login and preview rights.
  * Printing a blank questionnaire requires the printblank capability.
  * Viewing other users' individual responses requires viewsingleresponse.
  * Viewing a single response enforces separate groups.
  * The report's group filter is checked against the groups the user may see.
  * Staff without a group are refused the non-respondents page in separate groups mode.
  * Only the owning course can edit a shared public survey.
  * Previews only use the checked survey.
  * Question create and delete are limited to the current survey.
  * Hidden and disabled survey settings fields are no longer trusted.
  * Response files are only served to users who may view the response.

  Escaping and cleaning output (XSS):
  * Essay answers are escaped.
  * Answers and question text in full submission notifications are escaped.
  * Answers in the survey-address report email are escaped.
  * Group, respondent and questionnaire names in reports are formatted.
  * Staff-authored HTML follows Moodle's trusted-text rules instead of noclean.
  * Choice images and "other" labels are cleaned according to the author's trust.

  Anonymity and privacy:
  * Anonymous responses can no longer be traced to their authors.
  * Anonymous exports leave out identifying user details.
  * Anonymous text results no longer show submission times.
  * The non-respondents report respects the site's identity and hidden user fields.
  * Privacy deletion only removes this questionnaire's responses, drafts included.
  * Uploaded response files are deleted and exported with their responses.
  * The respondent email address is declared in the privacy metadata.

  Files, server requests and SQL:
  * Uploaded file types are checked on the server, and response files always download.
  * File answers are saved under the server's record id.
  * Emailed exports are written to a per-request directory.
  * PDF exports no longer fetch remote or inline images found in answers.
  * The thank-you URL is validated and redirected with redirect().
  * Grade resets use SQL placeholders and the gradebook API.
  * Rating and slider answers only keep values the question can produce.
  * Feedback heading placeholders are substituted without sprintf.

  ### Bug fixes and improvements: ###
  * Email reports, text and file result links, a default value and a group heading fixed.
  * Earlier file uploads are kept when a page is shown again.
  * "Other" answer text is stored as cleaned raw text so submission cannot fail.
  * The recycle bin's permanent deletion time is shown as a date (#783).
  * The recycle-bin task runs daily, and its setting is stored under the plugin.
  * RGraph loads from the chart module instead of blocking footer scripts.
  * Capability defaults use archetypes; RGraph's details are declared in thirdpartylibs.
  * Hard-coded text replaced by language strings; debug files removed.


## Release 5.3.0 (Build - 2026112801) ##
* Initial release for Moodle 5.3 forward.
* Supports back to Moodle 5.0.
* All sites should upgrade to this release in order to guarantee security.
* To upgrade, it is recommended to delete all of your questionnaire files before installing the new ones.

### Improvements: ###
* This is a complete refactoring of questionnaire into sensibles classes. Many new unit tests and Behat tests have been created.
* See [project details on Github](https://github.com/orgs/PoetOS/projects/1/views/1?filterQuery=) for a description of the refactoring.
* Security issues uncovered by [MDLShield](https://mdlshield.com/) have been fixed.


(see CHANGES.md in release 5.0.3 for earlier changes.)
