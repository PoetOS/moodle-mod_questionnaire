[![Moodle plugin CI](https://github.com/PoetOS/moodle-mod_questionnaire/actions/workflows/ci.yml/badge.svg)](https://github.com/PoetOS/moodle-mod_questionnaire/actions/workflows/ci.yml)

# moodle-mod_questionnaire

The questionnaire module allows you to construct questionnaires (surveys) from a
variety of question type. It was originally based on phpESP, and Open Source
survey tool.


## Try in Moodle Playground

Click the badge below to open this plugin instantly in
[Moodle Playground](https://moodle-playground.com) — a full Moodle site
running in the browser, with no local install. The demo includes a
"Questionnaire Demo" course with a sample "Course feedback survey"
(Yes/No, multiple-choice and text-box questions) and opens on its preview.

<a href="https://moodle-playground.com/?blueprint-url=https://raw.githubusercontent.com/PoetOS/moodle-mod_questionnaire/refs/heads/MOODLE_500_STABLE/blueprint.json" target="_blank" rel="noopener"><img src="https://raw.githubusercontent.com/ateeducacion/action-moodle-playground-pr-preview/refs/heads/main/assets/playground-preview-button.svg" alt="Preview in Moodle Playground" width="200"></a>

## Developers Note

There is no main branch. Questionnaire is maintained in MOODLE_XXX_STABLE
branches. Use the latest STABLE branch for development or installation.

Submit a pull request against the current stable branch to provide fixes, improvements or features. Make sure the change is defined well in the pull request or associated issue. Pull requests should contain changes for the issue at hand only. Any other changes must be in a separate pull request.

**The current stable branch is MOODLE_500_STABLE, and supports Moodle 5.0 through 5.1.**

Use the MOODLE_404_STABLE branch for Moodle 4.4 through 4.5.

Use the MOODLE_401_STABLE branch for Moodle 4.1 through 4.3.

## To Install

1. Load the questionnaire module directory into your "mod" subdirectory.
2. Visit your admin page to create all of the necessary data tables.

## To Upgrade

1. Copy all of the files into your 'mod/questionnaire' directory.
2. Visit your admin page. The database will be updated.

Please read the CHANGES.md file for more info about successive changes
