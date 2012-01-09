*** IMPORTANT see install recommendations at the end of this document ***

enchanced version
Updates by Joseph Rézeau
moodle@rezeau.org
timestamp:
22:35 10/06/2007

--------------------------------------------
This is a provisional readme file documenting changes to the Questionnaire module version 1.20 dated 2007/01/18 15:56:00 by Mike Churchward. These changes are proposed by Joseph Rézeau

1. Fixed bugs

1.1 Edit questions - Navigation problem
The following sequence results in wrong navigation.  Reorder Questions -> Add Section Break (one or several) -> Preview -> Close Preview popup window
return to Reorder Questions -> Add Section Break (or Section Break Remove) => you are taken to the Edit questions page. FIXED.
1.2 Edit questions – Preview: no navigation from Section 1
If a questionnaire has more than one section (page), then the Preview screen only shows the first page. FIXED: all sections are shown on one screen, with section breaks clearly marked.
1.3 Display N/A checked state in reports
Rate question type, if N/A was checked by student, it is NOT displayed as selected in reports. FIXED.
1.4 Rate questions – problem with the N/A column
If a rate question has an N/A column and is a required question, if the respondent checks a radio button in the N/A column, upon submitting that button is unchecked and the corresponding radio button in column 1 is checked instead. FIXED.
1.5 Parameter Save/Resume answers does not stick
On Updating Questionnaire in topic 00 page, the Parameter Save/Resume answers is lost each time you re-edit Questionnaire. FIXED: does not keep memory of the Save/Resume setting, because this is saved as 0/1 in database, and interface expects Y/N In lib.php and mod.html -> replaced Y/N with 0/1.
1.6 Edit Questions mode - Add new question, Add another answer line -> all the lines disappear! FIXED
1.7 In some cases, a response is not "complete", in table questionnaire_response, field 'complete' is set to 'N' instead of 'Y'. Such incomplete answers should not be displayed in reports. FIXED.
1.8 Report - In view your responses -> all -> if Q1, Q2, Section Break, Q3, etc. displays 1. Q1, 2. Q2, 4. Q3 etc. FIXED.
1.9 In questiontypes.class.php, in response_insert functions, lots of php Notice errors are displayed when responses are empty. Added some if(isset conditions for this... FIXED some of them, but there are A LOT more to. Not too serious, notice errors only displayed in debug mode.
1.10 repaired bug in automatic Question naming see http://moodle.org/mod/forum/discuss.php?d=69555
1.11 Report: radio button questions are displayed sorted alphabetically rather than in the order they have been created in questionnaire. FIXED (retain original order).
1.12 If questionnaire has more than one section/page, navigating backward & forward resets previously answered questions. FIXED.
1.13 Copying a Public Questionnaire to another questionnaire in the same Moodle course!
a. Create a public questionnaire QU01 with some questions.
b. Enter some responses in it
c. Create a questionnaire (private) copying public QU01 - call it QU02
d. when you open QU02 (as teacher) you see that it has all the responses from QU01 already in it! You MUST NOT CLICK on Use public: when you are in the course where that course was originally created -> this radio button choice SHOULD NOT EXIST HERE!
there is no point making a copy of a public questionnaire in the same course! FIXED in lib.php function questionnaire_print_survey_select.
1.14 When page is re-displayed, apostrophes are backslashed... solution: use stripslashes for TExt Box, Rate & Radiobutton (!other input text)... FIXED
1.15 Template surveys should NOT be displayed as an activity to students! FIXED.
---
1.16 When student is logged in, if questionnaire is set to many responses, student can view their responses. All Responses link should be called Your Responses. FIXED.
1.17 When Adding a new Questionnaire, a lits of existing questionnaires is displayed, to possibly Copy them. The link on each existing questionnaire name allows preview, but this is not working. FIXED 23:33 17/05/2007
1.18 Essay type questions which contain carriage return broke CSV export in Excel. FIXED 23:16 21/05/2007
1.19 when a check boxes question has more than one !other fields, and those fields are completed by respondent, all responses are saved and all are displayed on all responses report screen, but only *the last one* is displayed on individual responses screen and saved to CSV
FIXED 10:14 10/06/2007



2. Enhancements, etc.

2.1 Print Survey
If a questionnaire is set to Respond many times, users can only print a blank survey before their first attempt. On all successive attempts Print Survey will print the Questionnaire filled with responses from their latest attempt.
FIXED: a Print Blank button has been added to allow users to print a blank Questionnaire at any time.
2.2 Print Respondent's name & submission date
In Report mode, View by Response (Navigate Individual Respondent Submissions), only respondent's name is displayed: added submission date.
Respondent's name is not displayed on the Print window: added.
2.3 Checking responses for proper format
There is no check on numeric or date questions responses. If a numeric or date question is answered with e.g. text, the response to the questionnaire are saved and, upon further displaying an empty box is displayed: no response. FIXED.
A complete system of checking Date and Numeric question types has been implemented. If required questions are filled in with an incorrect type of date, a notice message is displayed and the respondent is prevented from submitting the Questionnaire (or from moving to another page if there are more than one page in the questionnaire).
2.3.1 Date checking
The correct date formatting is determined by the string $string['strfdate'] located in the lang/questionnaire.php file. Examples:
English: $string['strfdate'] = '%%d/%%m/%%Y'; will require: 31/12/2000
American: $string['strfdate'] = '%%m/%%d/%%Y'; will require: 21/31/2000
French: $string['strfdate'] =  '%%d-%%m-%%Y'; will require: 31-12-2000
Please note that the Date question type does not accept dates prior to the year 1901. This is a feature of the php function mktime.
2.3.2 Numeric checking
Accepted numeric responses will depend on the setting of the Length and Precision parameters on the questions editing page. Depending on those settings, some examples of accepted responses are: 99 +99 -99 99.5 99.50 etc. The Precision parameter is used to specify the number of decimal places expected. If it is set e.g. to 2, and respondent enters 99 or 99.5 then this will be automatically reformatted to 99.00 or 99.50. If the precision parameter is set to 0 (default value), then a response such as 9.5 would be reformatted/rounded to 100. Whenever a numeric response is re-formatted, a notice message is displayed.
2.3.3 Rate scales
If a rate question consists of more than one line, then one radio button must be checked in each line. An error notice is displayed if some lines remain empty.
2.4 Reports – Statistics
In report mode, I have removed the Downloadcsv button from the Viewbyresponse page, as it might wrongly mean download csv just for this single response, when in fact it contains the stats for all responses.
2.4.1 Numeric question
Responses to numeric questions are sorted (in ascending order). Extra Total and Average lines have been added to the Report table.
2.4.2 Date question
Responses to Date questions are sorted (in ascending order). 
2.5 Display of questionnaire subtitle & additional info
Only display subtitle & additional info on top of 1st section, do not repeat for each section.
2.6 Editing questions
On Editing questions page added a button to delete the question currently being edited. This doubles the other possibility of deleting questions from the Reorder Questions/Preview page. 
2.7 Current questionnaire theme is not displayed in report mode nor in Questionnaire Preview window, myReport and Report -> moved from preview.inc to manage_survey.php. ADDED.
2.8 Check missing required responses
The display system of missing responses to required answers has been changed. Instead of re-displaying the question text at the top of the page, the number of each missing question is mentioned. Plus, if there is something wrong with the formatting or required number of check boxes, etc. the relevant question numbers are mentioned in an error message.
2.9 In rate questions, display columns using CSS classes from the questionnaire themes rather than hard-coded colors
2.10 Changed the horizontal bars in report statistics
a) individual themes can have their own bars
b) 3 kinds of bars: normal (hbar, hbar_l, hbar_r) total (thbar etc) and ratings (rhbar etc)
2.11 In Reports, added bulk delete of all responses.
2.12 In re-order questions window, display question type for each question & display asterisk for required questions. DONE.
2.13 On many Reports and Edit Questionnaire screens added navigation links or buttons to the top as well as to the bottom to make navigation easier on long pages.
2.14 Reports.- added display of number of N/A choices by respondents
2.15 in various reports All responses / Your responses: made systematic display of navigation buttons + links to individual questions both at top & bottom of page + added a youarehere label in the breadcrumb
2.16 In reports, added tooltip TITLE with date & respondent name to all report pages
2.17 Check Boxes: implemented the use of the Length and Precision fields
2.18 Removed the Required questions message from top of page -> req questions are marked by an asterisk, and appropriate message is displayed if req questions have not been filled in (when changing pages or when submitting questionnaire)
2.19 Text from ESSAY with HTML editor enabled should be exported as plain text to CSV...
DONE -> used format_text_email
2.20 Removed Preview button from General and Edit Questions pages because of possible confusion. Left them on re-order page, renamed Reorder Questions/Preview page.
Indeed, on General page, if a different questionnaire theme is selected and Preview button is clicked, the newly selected theme has not yet been updated in the questionnaire database, and is not displayed! On the Edit Questions page, if a question is created/edited but not "saved" and the Preview button is clicked, that recently edited question is not displayed. The only way to correctly Preview is to go to the Re-order/Preview page, which is the only page in questionnaire editing where I have maintained its existence.
2.21 There are two "names" for a questionnaire : a) on Updating Questionnaire front page and b) on Editing Survey page (where it is called "Survey filename". This is used for all further access to this survey.) This is very confusing. I have enchanced this by removing all visible reference to "survey name", which is only used internally by the module, and does not need to be shown to the user. Plus: If no Questionnaire name is entered on the Updating Questionnaire front page, then the name "Questionnaire" is automatically given. If a name has been entered, then that Questionnaire name is automatically entered in the Title field, where of course it can be edited by the user at any time.
2.22 On the Updating Questionnaire front page, the list of questionnaires available for COPY is made of the TITLES of those questionnaires rather than their "survey name" (which was sometimes not very readable, esp. when it resulted from a transformation of non-ascii characters!)
2.23 On the Re-order Questions/Preview page, question text is now plain text, stripped of HTML formatting, images, etc.
2.24 Automatic removal of common words for labelling of question names is now language-dependent
2.25 Rate question type (Likert scales) now features optional naming of degrees. e.g. instead of 1 .. 5: Strongly disagree, Disagree, Neither agree nor disagree, Agree, Strongly agree.
2.26 CSV export now exports Submitted dates in human-readable format, based on the user's actual language (en, fr, en_us).
2.27 Rate question type (Likert scales) now features (optional) mutually exclusive column radio buttons. If you have 3 degrees 1, 2 and 3 and 3 items A, B and C, the respondent cannot check A1 AND B1 or A1 AND C1 or A1 AND B1 AND C1. This option is very useful if you want for example the respondent to ORDER a number of items on a 1 to n scale, without offering the possibility of equal places. Used in conjunction with the named degrees this feature allows one-to-one matching of items and named degrees.
2.28 in backup and restore: ADDED: res_view & resume settings on 14:17 09/06/2007
2.29 added report functions to lib.php and changed content of info field from questionnaire id to questionnaire name
***SEPTEMBER 2007***
2.30 Changed example date formatting to 14th March 1945.
2.31 Added Osgood's Semantic Differential display for rate question.
2.32 Changed graphic display of results in rate question type: replaced "percentage bar" with single marker.
2.33 Added display of user's group in Display by response Results.
2.34 Added default = blank space for choice_1 in rate question type.
2.35 Changed the naming/numbering scheme for CSV export. Now if question names are left empty an automatic numbering based on the actual number (i.e. position) of the questions in the Questionnaire.
2.36 Added help file "downloadtextformat.html" to explain this new naming/numbering scheme.
2.37 Made better provision for the "date" question type. Added explanation about the 1902-2037 date range.
2.38 Made provision for better UTF-8 coding compliance.
2.39 Changed email output to something more user-friendly (based on the new naming/numbering scheme for CSV export).
2.40 Slightly modified the available options (buttons) on the Report pages. The Delete All Responses has been moved from the View by Response page to the View All Responses page (logically). The Download CSV button has been renamed Download in text format (following conventions in Quiz, etc.).



3. Terminology

3.1 Replaced the word "Field" with "Question" e.g. in Edit this field, or click the number of the field you would like to edit ? Edit this question, or click the number of the question you would like to edit
3.2 Replaced the word "Survey" with "Questionnaire" throughout (for consistency's sake).
3.3 Renamed the page "Re-order questions" to "Re-order questions/Preview".

4. Language strings

Replaced all hard-coded labels, instructions etc. in English with language strings + added some new labels, etc. Language file upgraded from 145 to 210 language strings. All done in English en_utf8 and translated to French fr_utf8.
For compatibility's sake I have kept the language files in the Questionnaire folder, but I strongly recommend copying them to the relevant folders in lang/en_utf8, lang/en_utf8/help, moodledata/lang/fr_utf8 etc.

Available:
English: all language strings & help files
French:  all language strings & help files (Joseph Rézeau)
Português - Portugal (pt): all langue strings (thanks to Antonio Vilela)
Español - Internacional (es): all language strings and most help files (thanks to Jesus Martin)
Српски (sr_cr): all language strings (thanks to Marija C.)
Srpski (sr_lt): all language strings (thanks to Marija C.)

5. Coding

5.1 ... [removed]
5.2 Formatting
Removed lots of <br /> which waste real estate space on the pages
Removed hard-coded font size tag (deprecated) : every formatting should be done via CSS classes.
5.3 Removed old phpESP code
// Colors used by phpESP
$ESPCONFIG['main_bgcolor']= '#FFFFFF';
headerGraphic, etc.
There probably remain a number of deprecated phpESP lines in the code…
5.4 Removed deprecated resultslib.php library
---
5.5 Removed deprecated preview.inc file (moved to manage_survey)
5.6 In CSV export, replaced the *.csv extension with *.txt in order to be compliant with "manual" import into Excel (non English versions?).

6. Help
Re-written and added to Help files.
Added Help icons to many places in Edit Questionnaire/Questions mode.

7. Remaining bugs
7.1 Deleting last question: error message
In Report mode, when deleting the questions one by one, upon deleting the very last question an error message is displayed: Error opening survey. [ ID: ]. This seems to come from another module than Questionnaire itself. To be investigated.

8. TODO
8.1 When a questionnaire is set to anonymous, the response id is saved with Anonymous entry in Table: mdl_questionnaire_response, column username. Unfortunately this means that respondents cannot view their responses later on (if more than one attempt is allowed).
8.2 Checking mechanisms in question editing not robust
When editing a question, if question type or question text are not entered, an error message is displayed. Same if teacher attempts to change question type from a different "family" of questions (i.e. those with/without answers). The mechanism which the checking and the error message display was not properly set. I have set it, but it needs more debugging.
At the moment, no check is done when a question which requires Possible Answers is created with no such answers provided.
8.3 Make the Cross Analysis module really work. In the meantime I have removed the buttons linking to this module.
8.4 Adapt questionnaire themes to default.css (but this should be done by users themselves).
8.5 Find Moodle users to translate language files.
8.6 "Removed Questions"
Removed questions remain in the database, where their 'deleted' field is set to 'Y'. There should be a mechanism to "purge" those questions, preferably automatic, upon saving the full questionnaire. Of course, they get deleted if the questionnaire itself is deleted...
NOTE.- 22:34 10/06/2007 this could be achieved through the CRON task in lib.php
Another possibility would be: when a question has been 'deleted', the Remove button is replaced by a Restore button. But of course, "removed' questions should automatically be moved to the end of the questions list OR AND their associated order droplist should be removed or grayed out... and it might not be a good idea to restore questions to a questionnaire which already has responses.
8.7 Allow students to view all responses Summary (stats page) (cf. the Feedback module).
8.8 Define roles (for 1.7 onward compatibility, see Feedback module). This is not essential.
8.9 Reformat e-mail output to make it Excel-readable. DONE sept. 2007.

*** INSTALL RECOMMENDATIONS (from Moodle version 1.6 upwards) ***
Previous README files say:
To install:
1. Load the questionnaire module directory into your "mod" subdirectory.
2. Visit your admin page to create all of the necessary data tables.
3. Language files must reside in moodle/mod/questionnaire/lang/ folder.