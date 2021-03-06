<?php

/****************************************************************

File:     /enrol/cohortcateg/lang/en/enrol_cohortcateg.php

Purpose:  Plugin's strings for language 'en'

****************************************************************/

$string['database:unenrol'] = 'Unenrol suspended users';
$string['dbencoding'] = 'Database encoding';
$string['dbhost'] = 'Database host';
$string['dbhost_desc'] = 'Type database server IP address or host name. Use a system DSN name if using ODBC.';
$string['dbname'] = 'Database name';
$string['dbname_desc'] = 'Leave empty if using a DSN name in database host.';
$string['dbpass'] = 'Database password';
$string['dbsetupsql'] = 'Database setup command';
$string['dbsetupsql_desc'] = 'SQL command for special database setup, often used to setup communication encoding - example for MySQL and PostgreSQL: <em>SET NAMES \'utf8\'</em>';
$string['dbsybasequoting'] = 'Use sybase quotes';
$string['dbsybasequoting_desc'] = 'Sybase style single quote escaping - needed for Oracle, MS SQL and some other databases. Do not use for MySQL!';
$string['dbtype'] = 'Database driver';
$string['dbtype_desc'] = 'ADOdb database driver name, type of the external database engine.';
$string['dbuser'] = 'Database user';
$string['debugdb'] = 'Debug ADOdb';
$string['debugdb_desc'] = 'Debug ADOdb connection to external database. Not suitable for production sites!';
$string['defaultcategory'] = 'Default new course category';
$string['defaultcategory_desc'] = 'The default category for auto-created courses. Used when no new category id specified or not found.';
$string['defaultrole'] = 'Default role';
$string['defaultrole_desc'] = 'The role that will be assigned by default if no other role is specified in external table.';
$string['ignorehiddencourses'] = 'Ignore hidden courses';
$string['ignorehiddencourses_desc'] = 'If enabled users will not be enrolled on courses that are set to be unavailable to students.';
$string['localcategoryfield'] = 'Local category field';
$string['localcoursefield'] = 'Local course field';
$string['localrolefield'] = 'Local role field';
$string['localuserfield'] = 'Local user field';

$string['newcoursetable'] = 'Remote new courses table';
$string['newcoursetable_desc'] = 'Specify of the name of the table that contains list of courses that should be created automatically. Empty means no courses are created.';

$string['newcohorttable'] = 'Remote new cohorts table';
$string['newcohorttable_desc'] = 'Specify the name of the table that contains list of cohorts that should be created automatically. Empty means no cohorts are created.';


$string['newcoursecategory'] = 'New course category field';

$string['newcoursefullname'] = 'New course full name field';
$string['newcohortidnumber'] = 'New cohort idnumber';

$string['roleshortname'] = 'Role shortname';

$string['newcourseshortname'] = 'New course short name field';

$string['categoryidnumber'] = 'Category idnumber';


$string['pluginname'] = 'Cohort category enrolment';
$string['pluginname_desc'] = 'You can use an external database (of nearly any kind) to control your enrolments. It is assumed your external database contains at least a field containing a course ID, and a field containing a user ID. These are compared against fields that you choose in the local course and user tables.';
$string['remotecoursefield'] = 'Remote course field';
$string['remotecoursefield_desc'] = 'The name of the field in the remote table that we are using to match entries in the course table.';
$string['remoteenroltable'] = 'Remote user enrolment table';

$string['remoteenroltable_desc'] = 'Specify the name of the table that contains list of users need to be added to the cohorts. Empty means no user sync.';

$string['remoterolefield'] = 'Remote role field';
$string['remotecohortfield'] = 'Remote cohort field';

$string['remoterolefield_desc'] = 'The name of the field in the remote table that we are using to match entries in the roles table.';
$string['remotecohortfield_desc'] = 'The name of the field in the remote table that we are using to match entries in the cohort table.';

$string['remoteuserfield'] = 'Remote user field';
$string['settingsheaderdb'] = 'External database connection';
$string['settingsheaderlocal'] = 'Local field mapping';

$string['settingsheaderremote'] = 'Remote user sync';

$string['settingsheadernewcourses'] = 'Creation of new courses';
$string['settingsheadernewcohorts'] = 'Creation of new cohorts';


$string['remoteuserfield_desc'] = 'The name of the field in the remote table that we are using to match entries in the user table.';

$string['templatecourse'] = 'New course template';
$string['templatecourse_desc'] = 'Optional: auto-created courses can copy their settings from a template course. Type here the shortname of the template course.';
