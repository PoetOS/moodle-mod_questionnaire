Release Notes

_NOTE - The 3.8 releases will only work on Moodle 3.6, Moodle 3.7, Moodle 3.8, and Moodle 3.9.
The 3.5 releases will work on Moodle 3.7, 3.6, 3.5, 3.4 and 3.3._

##### Version 3.8.3 (Build - 2020062400)
This release ensures 3.9 compliance.

Improvements:

* Updated Behat tests for 3.9 compliance.
* Fixing code style issues for CI compliance.

Bug fixes:

* [CONTRIB-8096](https://tracker.moodle.org/browse/CONTRIB-8096) - Ignore MSSQL when updating primary key sizes.

##### Version 3.8.2 (Build - 2020052100)
This release fixes a problem when upgrading using Postgres.

Bug fixes:

* [CONTRIB-8096](https://tracker.moodle.org/browse/CONTRIB-8096) - Ignore Postgres when updating primary key sizes.

##### Version 3.8.1 (Build - 2020051500)
This release immediately replaces 3.8.0 to avoid a database issue with primary keys. If you installed 3.8.0, immediately
upgrade to 3.8.1

Bug fixes:

* [GHI288](https://github.com/PoetOS/moodle-mod_questionnaire/issues/288) - Working on reported date selector issues.

##### Version 3.8.0 (Build - 2020051400)
This release provides a number of reporting improvements to the summary pages and the download functions. These
changes were somewhat funded by the [Indiegogo campaign](https://www.indiegogo.com/projects/add-better-reporting-to-moodle-questionnaire/x/19609728#/).
The bulk of financial support came from USPHSCC and Remote Learner. Please review the
[project on Github](https://github.com/PoetOS/moodle-mod_questionnaire/projects/2) for specific details.

Other contributers to this release include Luca Bösch, Günter Lukas and Martin Gauk.

Improvements:

* [GHI235](https://github.com/PoetOS/moodle-mod_questionnaire/issues/235) - Allowing data downloads to be emailed
directly from the server to specified email addresses.
* [GHI237](https://github.com/PoetOS/moodle-mod_questionnaire/issues/237) - Allow rank choice averages to be
exported in the data exports.
* [GHI234](https://github.com/PoetOS/moodle-mod_questionnaire/issues/234) - Allow summary page to be exported to PDF.
* [GHI232](https://github.com/PoetOS/moodle-mod_questionnaire/issues/232) - Provide a printable individual result
page.
* [GHI230](https://github.com/PoetOS/moodle-mod_questionnaire/issues/230) - Provide all supported download formats
for data download feature.
* [CONTRIB-8003](https://tracker.moodle.org/browse/CONTRIB-8003) - Standardizing DB schema.
* [CONTRIB-8009](https://tracker.moodle.org/browse/CONTRIB-8009) - Allow for just a minimum value to be specified for checkbox answers.

Bug fixes:

* [PR271](https://github.com/PoetOS/moodle-mod_questionnaire/pull/271) - Prevent warning on Non-respondents page.
* [PR272](https://github.com/PoetOS/moodle-mod_questionnaire/pull/273) - Apply Moodle filters to title fields.
* [PR277](https://github.com/PoetOS/moodle-mod_questionnaire/pull/277) - Ensure questions maintain order on restores.

(see CHANGES.TXT in release 3.7 for earlier changes.)