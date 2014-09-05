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
    
    //$settings->add(new admin_setting_heading('enrol_cohortcateg_settings', '', get_string('pluginname_desc', 'enrol_cohortcateg')));

    $settings->add(new admin_setting_heading('enrol_cohortcateg_exdbheader', get_string('settingsheaderdb', 'enrol_cohortcateg'), ''));

    $options = array('', "access","ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    
    $options = array_combine($options, $options);
    
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/dbtype', get_string('dbtype', 'enrol_cohortcateg'), get_string('dbtype_desc', 'enrol_cohortcateg'), '', $options));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbhost', get_string('dbhost', 'enrol_cohortcateg'), get_string('dbhost_desc', 'enrol_cohortcateg'), 'localhost'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbuser', get_string('dbuser', 'enrol_cohortcateg'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('enrol_cohortcateg/dbpass', get_string('dbpass', 'enrol_cohortcateg'), '', ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbname', get_string('dbname', 'enrol_cohortcateg'), get_string('dbname_desc', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/dbencoding', get_string('dbencoding', 'enrol_cohortcateg'), '', 'utf-8'));

    //$settings->add(new admin_setting_configtext('enrol_cohortcateg/dbsetupsql', get_string('dbsetupsql', 'enrol_cohortcateg'), get_string('dbsetupsql_desc', 'enrol_cohortcateg'), ''));

    //$settings->add(new admin_setting_configcheckbox('enrol_cohortcateg/dbsybasequoting', get_string('dbsybasequoting', 'enrol_cohortcateg'), get_string('dbsybasequoting_desc', 'enrol_cohortcateg'), 0));

    $settings->add(new admin_setting_configcheckbox('enrol_cohortcateg/debugdb', get_string('debugdb', 'enrol_cohortcateg'), get_string('debugdb_desc', 'enrol_cohortcateg'), 0));


    
    $settings->add(new admin_setting_heading('enrol_cohortcateg_newcoursesheader', get_string('settingsheadernewcohorts', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/newcohorttable', get_string('newcohorttable', 'enrol_cohortcateg'), get_string('newcohorttable_desc', 'enrol_cohortcateg'), 'cohort_category'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/newcohortidnumber', get_string('newcohortidnumber', 'enrol_cohortcateg'), '', 'cohort_idnumber'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/categoryidnumber', get_string('categoryidnumber', 'enrol_cohortcateg'), '', 'category_idnumber'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/roleshortname', get_string('roleshortname', 'enrol_cohortcateg'), '', 'role_shortname'));


    $settings->add(new admin_setting_heading('enrol_cohortcateg_remoteheader', get_string('settingsheaderremote', 'enrol_cohortcateg'), ''));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/remoteenroltable', get_string('remoteenroltable', 'enrol_cohortcateg'), get_string('remoteenroltable_desc', 'enrol_cohortcateg'), 'cohort_enrolment'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/remoteuserfield', get_string('remoteuserfield', 'enrol_cohortcateg'), get_string('remoteuserfield_desc', 'enrol_cohortcateg'), 'user_idnumber'));

    $settings->add(new admin_setting_configtext('enrol_cohortcateg/remotecohortfield', get_string('remotecohortfield', 'enrol_cohortcateg'), get_string('remotecohortfield_desc', 'enrol_cohortcateg'), 'cohort_idnumber'));


/*
    $settings->add(new admin_setting_heading('enrol_cohortcateg_localheader', get_string('settingsheaderlocal', 'enrol_cohortcateg'), ''));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localcoursefield', get_string('localcoursefield', 'enrol_cohortcateg'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'email'=>'email', 'username'=>'username'); // only local users if username selected, no mnet users!
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localuserfield', get_string('localuserfield', 'enrol_cohortcateg'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localrolefield', get_string('localrolefield', 'enrol_cohortcateg'), '', 'shortname', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber');
    $settings->add(new admin_setting_configselect('enrol_cohortcateg/localcategoryfield', get_string('localcategoryfield', 'enrol_cohortcateg'), '', 'id', $options));
*/
}
