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
 * Database enrolment plugin.
 *
 * This plugin synchronises enrolment and roles with external database table.
 *
 * @package    enrol_cohortcateg
 * @copyright  2014 Szilard Szabo {@link http://szilard.co.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Database enrolment plugin implementation.
 * @author  Petr Skoda - based on code by Martin Dougiamas, Martin Langhoff and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_cohortcateg_plugin extends enrol_plugin {


    /**
     * Forces synchronisation of all enrolments with external database.
     *
     * @param progress_trace $trace
     * @param null|int $onecourse limit sync to one course only (used primarily in restore)
     * @return int 0 means success, 1 db connect failure, 2 db read failure
     */
    public function sync_cohorts(progress_trace $trace, $onecourse = null) {

        global $CFG, $DB;

        require ($CFG->dirroot.'/cohort/lib.php');
        require ($CFG->dirroot.'/enrol/cohort/locallib.php');

        // We do not create courses here intentionally because it requires full sync and is slow.
        if (!$this->get_config('dbtype') or !$this->get_config('remoteenroltable') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield')) {
            $trace->output('User enrolment synchronisation skipped.');
            $trace->finished();
            return 0;
        }

        $trace->output('Starting cohort import...');

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external enrolment database');
            $trace->finished();
            return 1;
        }

        // We may need a lot of memory here.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $table            = $this->get_config('remoteenroltable');
        $coursefield      = trim($this->get_config('remotecoursefield'));
        $userfield        = trim($this->get_config('remoteuserfield'));
        $rolefield        = trim($this->get_config('remoterolefield'));

        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $coursefield_l    = strtolower($coursefield);
        $userfield_l      = strtolower($userfield);
        $rolefield_l      = strtolower($rolefield);

        $localrolefield   = $this->get_config('localrolefield');
        $localuserfield   = $this->get_config('localuserfield');
        $localcoursefield = $this->get_config('localcoursefield');

        $unenrolaction    = $this->get_config('unenrolaction');
        $defaultrole      = $this->get_config('defaultrole');

        $ecohorts = array();    // external cohorts

        $sql = "SELECT * FROM " . $table . " LIMIT 100";

        if ($rs = $extdb->Execute($sql)) {
            
            if (!$rs->EOF) {
            
                while ($row = $rs->FetchRow()) {

                    // print "row:\n";
                    // print_r($row);
                    // print "\n";

                    // todo: check if category exists and get its name 

                    $trace->output('Creating cohort ' . $row['cohort_idnumber'] . ' in category ' . $row['category_idnumber'] . '...');

                    $ecohort = new stdClass();

                    $ecohort->name          = $row['cohort_idnumber'];
                    $ecohort->idnumber      = $row['cohort_idnumber'];
                    $ecohort->categoryid    = $row['category_idnumber'];                                                                        
                    $ecohort->roleShortName = $row['role_shortname'];


                    $context = $DB->get_record (
                        'context', 
                        array( 
                            'instanceid' => $row['category_idnumber'],
                            'contextlevel' => 40
                        )
                    );

                    $ecohort->contextid = $context->id;

                    // Check if cohort already exists
                    if(false !== ($cohort = $DB->get_record (
                        'cohort',
                        array(
                            'idnumber' => $row['cohort_idnumber'], 
                            'contextid' => $ecohort->contextid
                        )
                    ))) {

                        $ecohort->id = $cohort->id;

                        $trace->output('Cohort ' . $row['cohort_idnumber'] . ' already exists');

                    } else {

                        $ecohort->id = cohort_add_cohort($ecohort);

                        $trace->output('Cohort ' . $ecohort->idnumber . ' (' . $ecohort->id . ') has been created.');

                    }

                    // print "ecohort:\n";
                    // print_r($ecohort);
                    // print "\n";

                    $ecohorts[] = $ecohort; 
                }
            }

            $rs->Close();
        
        } else {
            $trace->output('Error reading data from the external cohort category table');
            $extdb->Close();
            return 2;
        }

        $trace->output('Starting user import into cohorts...');

        $sql = "SELECT * FROM cohort_enrolment LIMIT 100";

        if ($rs = $extdb->Execute($sql)) {
            
            if (! $rs->EOF) {
            
                while ($row = $rs->FetchRow()) {

                    $trace->output('Adding user ' . $row['user_idnumber'] . ' into cohort ' . $row['cohort_idnumber'] . '...');

                    if(false !== ($cohort = $DB->get_record ('cohort', array( 'idnumber' => $row['cohort_idnumber'])))) {

                        // print "cohort:\n";
                        // print_r($cohort);
                        // print "\n";

                        if(false !== ($user = $DB->get_record ('user', array( 'idnumber' => $row['user_idnumber'])))) {

                            // print "user:\n";
                            // print_r($user);
                            // print "\n";

                            cohort_add_member($cohort->id, $user->id);

                            $trace->output('User ' . $user->idnumber . '(' . $user->id . ') added to cohort ' . $cohort->idnumber . '(' . $cohort->id . ')');

                        } else {

                            $trace->output('User does not exists');   
                        }

                    } else {

                        $trace->output('Cohort does not exists');   
                    }
                }
            }

            $rs->Close();
        
        } else {
            $trace->output('Error reading data from the external cohort enrolment table');
            $extdb->Close();
            return 2;
        }


        // print "ecohorts:\n";
        // print_r($ecohorts);

        $enrol = enrol_get_plugin('cohort');
        // print "enrol:\n";
        // print_r($enrol);

        foreach($ecohorts as $ecohort) {
            
            if(false !== ($role = $DB->get_record ('role', array( 'shortname' => $ecohort->roleShortName),'id'))) {

                // print "role:\n";
                // print_r($role);

                $courses = array();

                $courses = $this->get_courses($courses, $ecohort->categoryid);

                // print "courses:\n";
                // print_r($courses);

                foreach($courses as $course) {

                     $enrol->add_instance($course, array('customint1' => $ecohort->id, 'roleid' => $role->id));    
                
                     enrol_cohort_sync($trace, $course->id);            
                }

            } else {

                $trace->output('Error: invalid role ' . $ecohort->roleShortName . ', user skipped...');  
            }

        }

        // $enrol->add_instance($course, array('customint1' => $cohort->id, 'roleid' => $roleid));

        // enrol_cohort_sync($trace, $course->id);

        return 0;
    }

    private function get_courses($courses, $category_id) {
        global $DB;

        $courses = $DB->get_records('course', array('category' => $category_id));

        $sub_categories = $DB->get_records('course_categories', array('parent' => $category_id));

        foreach($sub_categories as $sub_category) {
            $courses = $this->get_courses($courses, $sub_category->id);
        }
        
        return $courses;
    }


    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */
    protected function db_init() {
        global $CFG;

        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->get_config('dbtype'));
        if ($this->get_config('debugdb')) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->get_config('dbhost'), $this->get_config('dbuser'), $this->get_config('dbpass'), $this->get_config('dbname'), true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->get_config('dbsetupsql')) {
            $extdb->Execute($this->get_config('dbsetupsql'));
        }
        return $extdb;
    }

}
