The questionnaire module allows you to construct questionnaires (surveys) from a
variety of question type. It is based on phpESP, and Open Source survey tool 
(see: http://phpesp.sourceforge.net). 
--------------------------------------------------------------------------------
Version 2005021101 - Updates:

The management functions have been (stage 1) integrated into Moodle. This
consists of using the existing phpESP tab screens, but building them into the
standard Moodle module interfaces.

Creating and editing questionnaires is done in two phases (similar to quizzes
and resources). The first phase lets you enter all of the module parameters that
affect how it works. The second phase is the content management (i.e. editing
the actual survey).

The old methods of phpESP user and group management have been removed. Now,
questionnaires are owned by the course they are created in, and can be managed
by teachers of tha course.

There are now three types of questionnaires:

1. Private - belongs to the course it is defined in only.
This is the standard Moodle module concept. You create a questionnaire and its
survey content for the course it is defined in. Editing teachers of that course
can change the questionnaire and all teachers can review the results.

2. Public - can be shared among courses.
This is a hybrid of the Moodle module concept. This provides a similar function
to the 'library of surveys' from previous releases of this module. Public
questionnaires can be assigned to a newly created questionnaire. If a
questionnaire uses a public questionnaire from a different course, teachers
cannot edit the content nor view the results in the course that is using it.
Only teachers from the course that defined the original 'public' questionnaire
can do this.

3. Template - can be copied and edited.
This type of questionnaire cannot be used directly, but its content can be
copied into a new questionnaire and edited.

The old way of assigning 'Active' surveys to questionnaires is gone. When you
create a questionnaire, you create a survey - either by creating a new one from
scratch, by copying an existing one, or by assigning a public one.

Some of phpESP's safety features have been disabled. In particular, you can edit
the content of a survey even after its active.

Responses are now available from the 'view' page if you are a teacher.

phpESP survey names are no longer unique. You can give any number of surveys the
same name.

--------------------------------------------------------------------------------
To Install:

1. Load the questionnaire module directory into your "mod" subdirectory.
2. Visit your admin page to create all of the necessary data tables.

--------------------------------------------------------------------------------
To Upgrade:

1. Copy all of the files into your 'mod/questionnaire' directory.
2. Visit your admin page. The database will be updated.
3. You may need to copy the 'lang/help/questionnaire/*' files to your language
   directory.
4. As part of the update, all existing surveys are assigned as either 'private',
   'public' or 'temmplate'. Surveys assigned to a single questionnaire are set
   to 'private' with the questionnaire's course as the owner. Surveys assigned
   to multiple questionnaires in the same course are set to 'public' with the
   questionnaire's course as the owner. Surveys assigned to multiple 
   questionnaires in multiple courses are set to 'public' with the site ID as
   the owner. Surveys that are not deleted but have no associated questionnaires
   are set to 'template' with the site ID as the owner.
   
--------------------------------------------------------------------------------

You can still access phpESP directly, if you so desire.
To access your phpESP management functions, go to:
http://[yoursite]/[yourroot]/mod/questionnaire/phpESP/admin/

--------------------------------------------------------------------------------
KNOWN ISSUES:
1) If you are using PHP V5:
   The directive 'register_long_arrays' must be set to 'on'. phpESP uses older
   global constructs, and requires this.

--------------------------------------------------------------------------------
To Do's:
1) Implement Backup! When backup is implemented, I will add full deletion.
2) Fully integrate the reporting functions into Moodle.
