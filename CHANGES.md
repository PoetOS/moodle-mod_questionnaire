Release Notes

_NOTE - The 3.7 releases will only work on Moodle 3.6 and Moodle 3.7. The 3.5 releases will work on Moodle 3.7, 3.6, 3.5, 3.4
and 3.3._

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