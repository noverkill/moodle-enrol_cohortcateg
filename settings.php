<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database enrolment plugin settings and presets.
 *
 * @package    enrol_cohortcateg
 * @copyright  2014 Szilard Szabo {@link http://szilard.co.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_cohortcateg_settings', '', get_string('pluginname_desc', 'enrol_cohortcateg')));

    $settings->add(new admin_setting_heading('enrol_cohortcateg_exdbheader', get_string('settingsheaderdb', 'enrol_cohortcateg'), ''));

    $options = array('', "access","ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/dbtype', get_string('dbtype', 'enrol_cohortcateg'), get_string('dbtype_desc', 'enrol_cohortcateg'), '', $options));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbhost', get_string('dbhost', 'enrol_cohortcateg'), get_string('dbhost_desc', 'enrol_cohortcateg'), 'localhost'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbuser', get_string('dbuser', 'enrol_cohortcateg'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('enrol_cohortcateg/dbpass', get_string('dbpass', 'enrol_cohortcateg'), '', ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbname', get_string('dbname', 'enrol_cohortcateg'), get_string('dbname_desc', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbencoding', get_string('dbencoding', 'enrol_cohortcateg'), '', 'utf-8'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbsetupsql', get_string('dbsetupsql', 'enrol_cohortcateg'), get_string('dbsetupsql_desc', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configcheckbox('enrol_cohortcateg/dbsybasequoting', get_string('dbsybasequoting', 'enrol_cohortcateg'), get_string('dbsybasequoting_desc', 'enrol_cohortcateg'), 0));

    $settings->add(new admin_setting_configcheckbox('enrol_cohortcateg/debugdb', get_string('debugdb', 'enrol_cohortcateg'), get_string('debugdb_desc', 'enrol_cohortcateg'), 0));



    $settings->add(new admin_setting_heading('enrol_cohortcateg_localheader', get_string('settingsheaderlocal', 'enrol_cohortcateg'), ''));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localcoursefield', get_string('localcoursefield', 'enrol_cohortcateg'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'email'=>'email', 'username'=>'username'); // only local users if username selected, no mnet users!
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localuserfield', get_string('localuserfield', 'enrol_cohortcateg'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localrolefield', get_string('localrolefield', 'enrol_cohortcateg'), '', 'shortname', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber');
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localcategoryfield', get_string('localcategoryfield', 'enrol_cohortcateg'), '', 'id', $options));


    $settings->add(new admin_setting_heading('enrol_cohortcateg_remoteheader', get_string('settingsheaderremote', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/remoteenroltable', get_string('remoteenroltable', 'enrol_cohortcateg'), get_string('remoteenroltable_desc', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/remotecoursefield', get_string('remotecoursefield', 'enrol_cohortcateg'), get_string('remotecoursefield_desc', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/remoteuserfield', get_string('remoteuserfield', 'enrol_cohortcateg'), get_string('remoteuserfield_desc', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/remoterolefield', get_string('remoterolefield', 'enrol_cohortcateg'), get_string('remoterolefield_desc', 'enrol_cohortcateg'), ''));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_cohortcateg/defaultrole', get_string('defaultrole', 'enrol_cohortcateg'), get_string('defaultrole_desc', 'enrol_cohortcateg'), $student->id, $options));
    }

    $settings->add(new admin_setting_configcheckbox('enrol_cohortcateg/ignorehiddencourses', get_string('ignorehiddencourses', 'enrol_cohortcateg'), get_string('ignorehiddencourses_desc', 'enrol_cohortcateg'), 0));

    $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                     ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));



    $settings->add(new admin_setting_heading('enrol_cohortcateg_newcoursesheader', get_string('settingsheadernewcourses', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/newcoursetable', get_string('newcoursetable', 'enrol_cohortcateg'), get_string('newcoursetable_desc', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/newcoursefullname', get_string('newcoursefullname', 'enrol_cohortcateg'), '', 'fullname'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/newcourseshortname', get_string('newcourseshortname', 'enrol_cohortcateg'), '', 'shortname'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/newcourseidnumber', get_string('newcourseidnumber', 'enrol_cohortcateg'), '', 'idnumber'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/newcoursecategory', get_string('newcoursecategory', 'enrol_cohortcateg'), '', ''));

    if (!during_initial_install()) {
        $settings->add(new admin_setting_configselect('enrol_cohortcateg/defaultcategory', get_string('defaultcategory', 'enrol_cohortcateg'), get_string('defaultcategory_desc', 'enrol_cohortcateg'), 1, make_categories_options()));
    }

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/templatecourse', get_string('templatecourse', 'enrol_cohortcateg'), get_string('templatecourse_desc', 'enrol_cohortcateg'), ''));
}
