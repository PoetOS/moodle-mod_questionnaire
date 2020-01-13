Release Notes

_NOTE - The 3.7 releases will only work on Moodle 3.6, Moodle 3.7, and Moodle 3.8. The 3.5 releases will work on Moodle 3.7, 3.6, 3.5, 3.4
and 3.3._

##### Version 3.7.4 (Build - 2020011300)
This release added improvements to a bug preventing Postgres users from upgrading correctly fixed some potential
security issues, and added some CSS improvements. Thanks to all contributors.

Improvements:
[GHPR257](https://github.com/PoetOS/moodle-mod_questionnaire/pull/257) - Aligning form elements and removing unnecessary css.
[GHPR258](https://github.com/PoetOS/moodle-mod_questionnaire/pull/258) - Removing unnecessary tags from form button.
[GHPR261](https://github.com/PoetOS/moodle-mod_questionnaire/pull/261) - CSS improvements to buttons.
[GHPR262](https://github.com/PoetOS/moodle-mod_questionnaire/pull/262) - Aligning answer buttons.
[GHPR270](https://github.com/PoetOS/moodle-mod_questionnaire/pull/270) - CSS improvements to buttons and form elements.

Bug Fixes:
[CONTRIB-7929](https://tracker.moodle.org/browse/CONTRIB-7929) - Fixed permissions check within all response report.
[GHPR268](https://github.com/PoetOS/moodle-mod_questionnaire/pull/268) - Cleaning user essay responses before storing.
[GHI191](https://github.com/PoetOS/moodle-mod_questionnaire/issues/191) - Removed potential tab characters from !OTHER answers.
[GHI260](https://github.com/PoetOS/moodle-mod_questionnaire/issues/260) - More fixes for Postgres.

##### Version 3.7.3 (Build - 2019120500)
This release fixes a bug preventing Postgres users from upgrading correctly.

Bug Fixes:
* [GHI260](https://github.com/PoetOS/moodle-mod_questionnaire/issues/260) - Postgres can now upgrade correctly.

##### Version 3.7.2 (Build - 2019120400)
This release fixes some serious bugs identified in previous releases, as well as some improvements.
Due to a restore data corruption issue, it is recommended to upgrade to this version as soon as possible.

Improvements:
* [GHI252](https://github.com/PoetOS/moodle-mod_questionnaire/issues/252) - Improved icon in mobile app.
* [GHI251](https://github.com/PoetOS/moodle-mod_questionnaire/issues/251) - Better styling of answer buttons.
* [GHI249](https://github.com/PoetOS/moodle-mod_questionnaire/issues/249) - Improving upgrade performance for rate questions.

Bug Fixes:
* [GHI256](https://github.com/PoetOS/moodle-mod_questionnaire/issues/256) - Fixing potential data corruption for user data restores.
* [GHI255](https://github.com/PoetOS/moodle-mod_questionnaire/issues/255) - Fixing summary display for rate questions with named degrees.

##### Version 3.7.1 (Build - 2019110800)
This release provides a number of bug fixes repoerted in 3.7.0 and some UI improvements to the button styling.
Thanks to lucaboesch (Luca Bösch), scippie75 (Dirk Schippers), anatolyg, yexiangwu, camiernes,
rezeau (Joseph Rezeau), Sharon Strauss and Dagefoerde (Jan Dagefoerde).

Improvements:
* [GHI118](https://github.com/PoetOS/moodle-mod_questionnaire/issues/118) - Completion page "continue" link now a button.
* [GHI223](https://github.com/PoetOS/moodle-mod_questionnaire/pull/223) - Changed rank value storage to use 1 based instead of zero based.
* [GHPR240](https://github.com/PoetOS/moodle-mod_questionnaire/pull/240) - Improved rendering of page navigation and submit buttons.
* [GHPR242](https://github.com/PoetOS/moodle-mod_questionnaire/pull/242) - Added a "More help" link to activity chooser.
* [GHPR245](https://github.com/PoetOS/moodle-mod_questionnaire/pull/245) - Improved rendering of preview button.
* [GHPR248](https://github.com/PoetOS/moodle-mod_questionnaire/pull/248) - Fixed Travis issue with mobile mustache templates.

Bug fixes:
* [GHI231](https://github.com/PoetOS/moodle-mod_questionnaire/issues/231), [GHI236](https://github.com/PoetOS/moodle-mod_questionnaire/issues/236), [CONTRIB-7878](https://tracker.moodle.org/browse/CONTRIB-7878) - Restoring or duplicating questionnaires failed.
* [GHI233](https://github.com/PoetOS/moodle-mod_questionnaire/issues/233) - Rate answers incorrectly displayed in summary report and CVS.
* [GHI239](https://github.com/PoetOS/moodle-mod_questionnaire/issues/239) - Named degrees not displayed on summary displays.
* [GHI246](https://github.com/PoetOS/moodle-mod_questionnaire/issues/246) - Rank questions with N/A and named degree options, could not be completed.
* [GHPR221](https://github.com/PoetOS/moodle-mod_questionnaire/pull/221) - Anonymous questionnaires with feedback sections were revealing the user's name.

##### Version 3.7.0 (Build - 2019101700)
Improvements:

This release provides the first release of the Moodle Mobile questionnaire app. The mobile app provides all completion functions
of the web based app, and allows individual review of completed questionnaires. It does not include the feedback reporting options.
The mobile app feature comes thanks to an Indiegogo crowd funding campaign (see [details](https://www.indiegogo.com/projects/adapt-moodle-questionnaire-plugin-for-mobile-app)).
While every effort has been taken to ensure correct operation on mobile devices, this is the first release. If problems are
discovered, please report them at the [Github repository](https://github.com/PoetOS/moodle-mod_questionnaire) or in the [Moodle
tracker](https://tracker.moodle.org/).

(see CHANGES.TXT in release 3.6 for earlier changes.)