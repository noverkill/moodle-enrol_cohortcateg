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
     * Read data from external database into internal tables to keep log
     *
     * @param  ADOdbConnection $extdb
     * @param  progress_trace  $trace
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function read_external(progress_trace $trace) {

        global $CFG, $DB;

        require_once ($CFG->dirroot.'/cohort/lib.php');
        require_once ($CFG->dirroot.'/enrol/cohort/locallib.php');

        $trace->output("\nCopy data from external database into internal tables...");

    	if (!$extdb = $this->db_init()) {
    	    $trace->output('Error while communicating with external enrolment database');
    	    $trace->finished();
    	    return 1;
    	}

        $newcohorttable          = $this->get_config ('newcohorttable');
        $cohort_idnumber_field   = $this->get_config ('newcohortidnumber');
        $category_idnumber_field = $this->get_config ('categoryidnumber');
        $role_shortname_field    = $this->get_config ('roleshortname');

        $date = new DateTime();

        if($newcohorttable != '') {

            $sql = "SELECT $cohort_idnumber_field as cohort_idnumber, 
                           $category_idnumber_field as category_idnumber,  
                           $role_shortname_field as role_shortname
                    FROM $newcohorttable
                    ORDER BY id";

            if ($rs = $extdb->Execute($sql)) {
               
                if (!$rs->EOF) {
                
                    while ($crow = $rs->FetchRow()) {
                        
            		    $crow['created'] = $date->getTimestamp();

                        $DB->insert_record('cohortcateg_categorylog', $crow); 
            		}
        	    } 

        	    $rs->Close();

            } else {
                $trace->output("\nError reading data from the external cohort category table");
                $extdb->Close();
                return 2;
            }
        } else {
            $trace->output("\nNo remote cohort table name provided, skipping cohort sreation...");  
        }

        $remoteenroltable      = $this->get_config ('remoteenroltable');
        $user_idnumber_field   = $this->get_config ('remoteuserfield');
        $cohort_idnumber_field = $this->get_config ('remotecohortfield');

        if($remoteenroltable != '') {

            $sql = "SELECT $user_idnumber_field as user_idnumber, 
                           $cohort_idnumber_field as cohort_idnumber 
                    FROM $remoteenroltable
                    ORDER BY id";

            if ($rs = $extdb->Execute($sql)) {
              
                if (! $rs->EOF) {
                               
            		while ($erow = $rs->FetchRow()) {

            		    $erow['created'] = $date->getTimestamp();

                        $DB->insert_record('cohortcateg_enrolmentlog', $erow); 
            		}
        	    }

    	        $rs->Close();

            } else {
                $trace->output("\nError reading data from the external cohort enrolment table");
                $extdb->Close();
                return 2;
            }
        } else {
            $trace->output("\nNo remote enrolment table name provided, skipping user enrolment...");
        }

       $trace->output("\nCopy data from external database is done...");
	
	   return 0;
    }

    /**
     * Create cohorts
     *
     * @param  progress_trace  $trace
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function process_cohorts(progress_trace $trace, $limit = 1000) {

        global $CFG, $DB;

        $date = new DateTime();

        $trace->output("\nProcessing cohorts...");

    	$cohorts = $DB->get_records('cohortcateg_categorylog', array('processed' => NULL), 'id', $fields='*', 0, $limit);

        foreach($cohorts as $cohort) {

    		$cohort->processed = $date->getTimestamp(); 

    		$DB->update_record('cohortcateg_categorylog', $cohort);
    	
    		$row = (array) $cohort;

    		// todo: check if category exists and get its name 

            $trace->output('Creating cohort "' . $row['cohort_idnumber'] . '" in category "' . $row['category_idnumber'] . '"...');

    		$row['cohort_name'] = $row['cohort_idnumber'];

            $category = $DB->get_record (
                'course_categories', 
                array( 
                    'idnumber' => $row['category_idnumber']
                )
            );

    		if ((int) $category->id < 1) {
    			$trace->output("Warning! Category \"" . $row['category_idnumber'] . "\" does not exist, skipping...");
    			continue;		
    		}

            $row['category_id'] = $category->id;

            $context = $DB->get_record (
                'context', 
                array( 
                    'instanceid' => $category->id,
                    'contextlevel' => 40
                )
            );

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
    			$new_cohort->component = 'enrol_cohortcateg';

                // we could use the built in function to create the cohort  
    			// this would be: $row['cohort_id'] = cohort_add_cohort($new_cohort);  (from cohort/lib.php)
                // but just to see more clearly what is going on in that function (to make it easier to roll back if needed)
                // we create the cohort here with the same way, for do that we need some other fields
                // to be set up with default values as it is done there:
                $new_cohort->description = '';
                $new_cohort->timecreated = time();
                $new_cohort->descriptionformat = FORMAT_HTML;
                $new_cohort->timecreated = time();
                $new_cohort->timemodified = $new_cohort->timecreated;
                
                // and then just add the record to the cohort table
                $row['cohort_id'] = $DB->insert_record('cohort', $new_cohort);

    			$trace->output('Cohort "' . $row['cohort_idnumber'] . '" (' . $row['cohort_id'] . ') has been created.');

    		}

    		// create log

    		$row['created'] = $date->getTimestamp();
    		$row['processed'] = NULL;

    		$DB->insert_record('cohortcateg_cohorts', $row); 

        }

        $trace->output("Processing cohorts is done...\n");

        return 0;    
    }

    /**
     * Import users into cohorts
     *
     * @param  progress_trace  $trace
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function process_users(progress_trace $trace, $limit = 10000) {

        global $CFG, $DB;

        //require_once ($CFG->dirroot.'/enrol/cohort/locallib.php');

        $date = new DateTime();

        $trace->output("\nProcessing users...");

        $enrolments = $DB->get_records('cohortcateg_enrolmentlog', array('processed' => NULL), 'id', $fields='*', 0, $limit);

        foreach($enrolments as $enrolment) {

    		$enrolment->processed = $date->getTimestamp(); 

    		$DB->update_record('cohortcateg_enrolmentlog', $enrolment);
			
            $trace->output('Adding user ' . trim($enrolment->user_idnumber) . ' into cohort "' .  trim($enrolment->cohort_idnumber) . '"...');

            $row = (array) $enrolment;

            $row['created'] = $date->getTimestamp();

            if(count($cohorts = $DB->get_records ('cohort', array( 'idnumber' => $row['cohort_idnumber']))) > 0) {

                if(false !== ($user = $DB->get_record ('user', array( 'idnumber' => $row['user_idnumber'])))) {

                    $row['error'] = 0;

                    foreach($cohorts as $cohort) {
                        
                        if (! $DB->record_exists('cohort_members', array('cohortid' => $cohort->id, 'userid' => $user->id))) {
                   
                            // we could use the built in function to add the user to the cohort
                            // this would be: cohort_add_member($cohort->id, $user->id);   (from cohort/lib.php)
                            // but just to see more clearly what is going on in that function (to make it easier to roll back if needed)
                            // we create add the user here with the same way, and to do that we need some other fields 
                            // to be set up with default values as it is done there:
                            $record = new stdClass();
                            $record->cohortid  = $cohort->id;
                            $record->userid    = $user->id;
                            $record->timeadded = time();
                            $DB->insert_record('cohort_members', $record);

                            $row['cohort_contextid'] = $cohort->contextid;
                            
                            $DB->insert_record('cohortcateg_enrolments', $row);

                            $trace->output('User ' . $user->idnumber . ' (' . $user->id . ') enrolled into cohort "' . $cohort->idnumber . '" (' . $cohort->id . ') /contextid:' . $cohort->contextid . '/');                        
                        
                        } else {
                        
                            $trace->output('User ' . $user->idnumber . ' (' . $user->id . ') already enrolled into cohort "' . $cohort->idnumber . '" (' . $cohort->id . ') /contextid:' . $cohort->contextid . '/ skipping...');
                        
                        }
                    }

                } else {

                    $row['error'] = 1;

                    $DB->insert_record('cohortcateg_enrolments', $row);                    

                    $trace->output('User does not exists');   
                }

            } else {

                $row['error'] = 2;

                $DB->insert_record('cohortcateg_enrolments', $row);
                
                $trace->output('Cohort does not exists');   
            }

        }

        $trace->output("Processing users is done...\n");
    }

    /**
     * Enrol cohorts to course categories
     *
     * @param  progress_trace  $trace
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function add_cohort_to_category_courses(progress_trace $trace, $limit = 10000) {

        global $CFG, $DB;

        $role_shortname_field = $this->get_config('roleshortname'); 

        $date = new DateTime();

        $trace->output("\nEnroll cohorts to course categories...");

        $cohorts = $DB->get_records('cohortcateg_cohorts', array('processed' => NULL), '', '*', 0, $limit);

        // print "cohorts:\n";
        // print_r($cohorts);

        $enrol = enrol_get_plugin('cohortcateg');
        
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

                $courses = $this->get_courses($courses, $cohort->category_id);

                // print "courses:\n";
                // print_r($courses);

                foreach($courses as $course) {


                 	if ($DB->record_exists('enrol', array(
                            "roleid" => $role->id, 
                            "customint1" => $cohort->cohort_id, 
                            "courseid" => $course->id, 
                            "enrol" => 'cohortcateg'))) {

	          	        $trace->output("\nCohort \"" . $cohort->cohort_name . "\" already exists in course \"". $course->shortname . "\" with role \"" . $cohort->role_shortname . "\" skipping...");

        			} else {

                        // we could use the built in function to add the cohort to the course
                        // and this would be: (from https://github.com/moodle/moodle/blob/master/lib/enrollib.php)
                        $enrol->add_instance($course, array(
                            'customint1' => $cohort->cohort_id, 
                            'roleid' => $role->id)
                        );
                        // but just to see more clearly what is going on in that function
                        // (and to make it easier to roll back if needed)
                        // we add the cohort to the course here with the same way: 
                        // ...under progress

	          	        $trace->output("\nCohort \"" . $cohort->cohort_name . "\" added to course \"". $course->shortname . "\" (" . $course->id . ") with role \"" . $cohort->role_shortname . "\"...");
                        
                        // cohort syn needs to be run to actually enrol the users from the cohort
  		                // enrol_cohort_sync($trace, $course->id);
        			}
                }

            } else {

                $cohort->error = 1;

                $trace->output("\nError: invalid role " . $cohort->role_shortname . ", user skipped...");  

            }
 
            $cohort->processed = $date->getTimestamp();
 
            $DB->update_record('cohortcateg_cohorts', array('id' => $cohort->id, 'processed' => $cohort->processed, 'error' => $cohort->error));
        }

        $trace->output("Enroll cohorts is done...\n");
    }

    private function get_courses($courses, $category_id) {

        global $DB;

        $courses = $DB->get_records('course', array('category' => $category_id));

        /*
        $sub_categories = $DB->get_records('course_categories', array('parent' => $category_id));

        foreach($sub_categories as $sub_category) {
            $courses = array_merge($courses, $this->get_courses($courses, $sub_category->id));
        }
        */

        return $courses;
    }


    /**
     * Delete cohort and its members created by this plugin based on cohort id
     * and also remove from all courses it was added (clean up the enrol table)
     *
     * @param  int             $id    cohort id
     * @param  progress_trace  $trace
     */
    public function delete_cohort($id, progress_trace $trace) {

        global $DB;

        if(false !== ($cohort = $DB->get_record ('cohort', array( 'id' => $id, 'component' => 'enrol_cohortcateg')))) {
            
            $trace->output("\nRemoving cohort \"" . $cohort->idnumber . "\"(" . $id .  ") from courses...\n");

            $DB->delete_records ('enrol', array('enrol' => 'cohortcateg', 'customint1' => $id));                   

            $trace->output("Removing members from cohort \"" . $cohort->idnumber . "\"(" . $id .  ") ...\n");

            $DB->delete_records ('cohort_members', array( 'cohortid' => $id));                

            $trace->output("Deleting cohort \"" . $cohort->idnumber . "\"(" . $id .  ") ...\n");

            $DB->delete_records ('cohort', array( 'id' => $id, 'component' => 'enrol_cohortcateg'));

        } else {

            $trace->output("Error! Cohort id: " . $id . ", does not exist...");  

        }
    }    

    /**
     * Delete all cohort and its members created by this plugin
     *
     * @param  progress_trace  $trace
     */
    public function delete_cohorts(progress_trace $trace) {

        global $DB;

        $trace->output("\nStart deleting cohorts...");

        $cohorts = $DB->get_records ('cohort', array( 'component' => 'enrol_cohortcateg'));

        foreach ($cohorts as $cohort) {

            $this->delete_cohort($cohort->id, $trace);
        
        }

        $trace->output("Deleting cohorts is done...\n");
    }

    /**
     * Unenrol users from course enroled by cohort
     *
     * @param  int             $cohort_id    cohort id
     * @param  int             $course_id    course id
     * @param  progress_trace  $trace
     */
    public function unenrol_cohort($cohort_id, $course_id, progress_trace $trace) {

        global $DB;

        $enrol_id = 33786; //$DB->get_field ('enrol', 'id', array( 'enrol' => 'cohortcateg', 'courseid' => $course_id, 'customint1' => $cohort_id));

        if($enrol_id) {
            $trace->output("\nUnenroling users from course " . $course_id . " enroled by cohort " . $cohort_id . " (enrol id: " . $enrol_id . ") ...");
            $DB->delete_records ('user_enrolments', array('enrolid' => $enrol_id)); 
        }
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
