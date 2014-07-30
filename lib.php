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
 * Cohort category enrolment plugin implementation.
 * @author  Szilard Szabo
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_cohortcateg_plugin extends enrol_plugin {


    /**
     * Import cohorts from external database 
     *
     * @param  ADOdbConnection $extdb
     * @param  progress_trace  $trace
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function import_cohorts($extdb, progress_trace $trace) {

        global $CFG, $DB;

        require_once ($CFG->dirroot.'/cohort/lib.php');
        require_once ($CFG->dirroot.'/enrol/cohort/locallib.php');

        $newcohorttable = 'cohort_category'; //$this->get_config('newcohorttable');
        $cohort_idnumber_field = 'cohort_idnumber'; //$this->get_config('cohortidnumber'); 
        $category_idnumber_field = 'category_idnumber'; //$this->get_config('categoryidnumber'); 
        $role_shortname_field = 'role_shortname'; //$this->get_config('roleshortname'); 

        if($newcohorttable == '') return 0;

        $date = new DateTime();

        $trace->output("\nImporting cohorts from external database...");

        $sql = "SELECT $cohort_idnumber_field as cohort_idnumber, 
                       $category_idnumber_field as category_idnumber,  
                       $role_shortname_field as role_shortname
                FROM $newcohorttable 
                LIMIT 100";

        if ($rs = $extdb->Execute($sql)) {
           
            if (!$rs->EOF) {
            
                while ($row = $rs->FetchRow()) {

                    // print "row:\n";
                    // print_r($row);
                    // print "\n";

                    // todo: check if category exists and get its name 

                    $trace->output('Creating cohort "' . 
                                        $row['cohort_idnumber'] . '" in category "' . 
                                        $row['category_idnumber'] . '"...'
                                   );

                    $row['cohort_name'] = $row['cohort_idnumber'];

                    $category = $DB->get_record (
                        'course_categories', 
                        array( 
                            'idnumber' => $row['category_idnumber']
                        )
                    );

		    if ((int) $category->id < 1) {
		    	$trace->output("Warning! Category \"" . $row['category_idnumber'] . "\" does not exist.");
 		    	$trace->output("Skipping...");
			continue;		
	            }

                    $context = $DB->get_record (
                        'context', 
                        array( 
                            'instanceid' => $category->id,
                            'contextlevel' => 40
                        )
                    );

                    //print "context:\n";
                    //print_r($context);
                    //print "\n";
                    
		    $row['context_id'] = $context->id;

                    // Check if cohort already exists
                    if(false !== (
                        $cohort = $DB->get_record (
                            'cohort',
                            array(
                                'idnumber' => $row['cohort_idnumber'], 
                                'contextid' => $row['context_id']
                            )
                    ))) {

                        $row['cohort_exists'] = 1;

                        $row['cohort_id'] = $cohort->id;

                        $trace->output('Cohort "' . $row['cohort_idnumber'] . '" already exists, skipping...');

                    } else {

                        $row['cohort_exists'] = 0;

                        $new_cohort = new \stdClass;
                        $new_cohort->name = $row['cohort_name'];
                        $new_cohort->idnumber = $row['cohort_idnumber'];
                        $new_cohort->contextid = $row['context_id'];

                         // print "new_cohort:\n";
                         // print_r($new_cohort);
                         // print "\n";
 
			$row['cohort_id'] = cohort_add_cohort($new_cohort);	//$DB->insert_record('cohort', $new_cohort);

                        $trace->output('Cohort "' . $row['cohort_idnumber'] . '" (' . $row['cohort_id'] . ') has been created.');

                    }

                    // print "row:\n";
                    // print_r($row);
                    // print "\n";

                    // create log
            
                    $row['created'] = $date->getTimestamp();

                    $DB->insert_record('cohortcateg_cohorts', $row); 

                }
            }

            $rs->Close();
        
        } else {
            $trace->output('Error reading data from the external cohort category table');
            $extdb->Close();
            return 2;
        }

        $trace->output("Importing cohorts is done...\n");

        return 0;    
    }

    /**
     * Import users into cohorts from external database
     *
     * @param  ADOdbConnection $extdb
     * @param  progress_trace  $trace
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function import_users($extdb, progress_trace $trace) {

        global $CFG, $DB;

        require_once ($CFG->dirroot.'/cohort/lib.php');
        require_once ($CFG->dirroot.'/enrol/cohort/locallib.php');

        $remoteenroltable = 'cohort_enrolment'; //$this->get_config('remoteenroltable');
        $user_idnumber_field = 'user_idnumber'; //$this->get_config('remoteuserfield'); 
        $cohort_idnumber_field = 'cohort_idnumber'; //$this->get_config('remotecohortfield'); 

        $date = new DateTime();

        $trace->output("\nImporting users into cohorts from external database...");

        $sql = "SELECT $user_idnumber_field as user_idnumber, $cohort_idnumber_field as cohort_idnumber FROM $remoteenroltable LIMIT 10000";

        if ($rs = $extdb->Execute($sql)) {
            
            if (! $rs->EOF) {
            
                while ($row = $rs->FetchRow()) {

                    $trace->output('Adding user ' . $row['user_idnumber'] . ' into cohort "' . $row['cohort_idnumber'] . '"...');

                    $row['error'] = 0;

                    // print "row:\n";
                    // print_r($row);
                    // print "\n";

                    if(false !== ($cohort = $DB->get_record ('cohort', array( 'idnumber' => $row['cohort_idnumber'])))) {

                        // print "cohort:\n";
                        // print_r($cohort);
                        // print "\n";

                        if(false !== ($user = $DB->get_record ('user', array( 'idnumber' => $row['user_idnumber'])))) {

                            // print "user:\n";
                            // print_r($user);
                            // print "\n";

                            cohort_add_member($cohort->id, $user->id);  //Note: user will not be added again id already in the cohort (as this function has no return value there is no way to notify about this)

                            $trace->output('User ' . $user->idnumber . ' (' . $user->id . ') enrolled into cohort "' . $cohort->idnumber . '" (' . $cohort->id . ')');

                        } else {

                            $row['error'] = 2;

                            $trace->output('User does not exists');   
                        }

                    } else {

                        $row['error'] = 2;

                        $trace->output('Cohort does not exists');   
                    }

                    // create log

                    $row['created'] = $date->getTimestamp();

                    $DB->insert_record('cohortcateg_enrolments', $row);
                }
            }

            $rs->Close();
        
        } else {
            $trace->output('Error reading data from the external cohort enrolment table');
            $extdb->Close();
            return 2;
        }

        $trace->output("Importing users is done...\n");
    }

    /**
     * Import cohort users from external database
     *
     * @param  ADOdbConnection $extdb
     * @param  progress_trace  $trace
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function add_cohort_to_category_courses($extdb, progress_trace $trace) {

        global $CFG, $DB;

        require_once ($CFG->dirroot.'/cohort/lib.php');
        require_once ($CFG->dirroot.'/enrol/cohort/locallib.php');

        $role_shortname_field = $this->get_config('roleshortname'); 

        $date = new DateTime();

        $trace->output("\nEnroll cohorts to course categories...");

        $cohorts = $DB->get_records('cohortcateg_cohorts', array('processed' => NULL), '', '*', 0, 100);

        //print "cohorts:\n";
        //print_r($cohorts);

        $enrol = enrol_get_plugin('cohort');
        
        // print "enrol:\n";
        // print_r($enrol);

        foreach($cohorts as $cohort) {

            // print "cohort:\n";
            // print_r($cohort);

            $cohort->error = 0;

            if(false !== ($role = $DB->get_record ('role', array( 'shortname' => $cohort->role_shortname),'id'))) {

                // print "role:\n";
                // print_r($role);

                $courses = array();

                $courses = $this->get_courses($courses, $cohort->category_idnumber);

                // print "courses:\n";
                // print_r($courses);

                foreach($courses as $course) {

                     $enrol->add_instance($course, array('customint1' => $cohort->cohort_id, 'roleid' => $role->id));    
                
                     enrol_cohort_sync($trace, $course->id);            
                }

            } else {

                $cohort->error = 1;

                $trace->output('Error: invalid role ' . $cohort->role_shortname . ', user skipped...');  
            }
            
            $cohort->processed = $date->getTimestamp();
 
            $DB->update_record('cohortcateg_cohorts', array('id' => $cohort->id, 'processed' => "{$cohort->processed}"));
        }

        $trace->output("Enroll cohorts is done...\n");
    }

    /**
     * Forces synchronisation of all enrolments with external database.
     *
     * @param progress_trace $trace
     * @param null|int $onecourse limit sync to one course only (used primarily in restore)
     * @return int 0 means success, 1 db connect failure, 2 db read failure
     */
    public function sync_cohorts(progress_trace $trace) {

        global $CFG;

        require_once ($CFG->dirroot.'/cohort/lib.php');
        require_once ($CFG->dirroot.'/enrol/cohort/locallib.php');

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external enrolment database');
            $trace->finished();
            return 1;
        }

        $this->import_cohorts ($extdb, $trace);
        
        $this->import_users ($extdb, $trace);

        $this->add_cohort_to_category_courses ($extdb, $trace);

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
    public function db_init() {
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
