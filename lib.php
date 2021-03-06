<?php

/****************************************************************

File:     /enrol/cohortcateg/lib.php

Purpose:  This file holds the class definition for the plugin.

****************************************************************/

defined('MOODLE_INTERNAL') || die();

class enrol_cohortcateg_plugin extends enrol_plugin {

    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     *
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol);

        } else if (empty($instance->name)) {
            $enrol = $this->get_name();
            $cohort = $DB->get_record('cohort', array('id'=>$instance->customint1));
            if (!$cohort) {
                return get_string('pluginname', 'enrol_'.$enrol);
            }
            $cohortname = format_string($cohort->name, true, array('context'=>context::instance_by_id($cohort->contextid)));
            if ($role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                $role = role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING));
                return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ' - ' . $role .')';
            } else {
                return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ')';
            }

        } else {
            return format_string($instance->name, true, array('context'=>context_course::instance($instance->courseid)));
        }
    }

    /**
     * Read data from external database into internal tables for logging and diagnostic purposes
     *
     * @param  progress_trace  $trace for diagnostic output
     *
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
     * @param  progress_trace  $trace for diagnostic output
     * @param  int             $limit max how many cohorts will be processed in one function call
     *
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
<<<<<<< Updated upstream
    			
                // we can leave the component name empty to keep the cohort manually editable 
                // (if we do that then it is not possible to use the sync_rollback.php anymore to remove the created cohorts) 
                $new_cohort->component = 'enrol_cohortcateg';
=======

                // we can leave the component name empty to keep the cohort manually editable
                // (if we do that then it is not possible to use the sync_rollback.php anymore to remove the created cohorts)
                //$new_cohort->component = 'enrol_cohortcateg';
>>>>>>> Stashed changes

                // --------------------------------------------------
                // we could use the built in function to create the cohort
    			// this would be: $row['cohort_id'] = cohort_add_cohort($new_cohort);  (from cohort/lib.php)
                // but just to see more clearly what is going on in that function (to make it easier to roll back if needed)
                // we create the cohort here with the same way, for do that we need some other fields
                // to be set up with default values:
                $new_cohort->description = '';
                $new_cohort->timecreated = time();
                $new_cohort->descriptionformat = FORMAT_HTML;
                $new_cohort->timecreated = time();
                $new_cohort->timemodified = $new_cohort->timecreated;
                //
                // and then just add the record to the cohort table
                $row['cohort_id'] = $DB->insert_record('cohort', $new_cohort);
                // --------------------------------------------------


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
     * Add users to cohorts
     *
     * The function processes the cohortcateg_enrolmentlog db table, and based on that, it
     * adds the users to the cohorts.
     * The function records diagnostical information to the cohortcateg_enrolmentlog and
     * cohortcateg_enrolments db table.
     * It records the time of processing as a unix timestamp to the "processed" field of the cohortcateg_enrolmentlog table.
     * and also save all the important data of the processed users and cohorts plus the creation date as an unix timestamp
     * to the "created" field along with the roor code to the "error" field of the cohortcateg_enrolments table.
     * The possible values of this error code are:
     * 0 means success, 1 means failure because the user does not exist, 2 means failure because the cohort does not exist.
     *
     * @param  progress_trace  $trace for diagnostic output
     * @param  int             $limit max how many users will be processed in one function call
     *
     * @return int             0 means success, 1 db connect failure, 2 db read failure
     */
    public function process_users(progress_trace $trace, $limit = 10000) {

        global $CFG, $DB;

        // uncomment if the built in cohort_add_member($cohort->id, $user->id) function is used
        // require_once ($CFG->dirroot.'/enrol/cohort/locallib.php');

        $date = new DateTime();

        $trace->output("\nProcessing users...");

        $enrolments = $DB->get_records('cohortcateg_enrolmentlog', array('processed' => NULL), 'id', $fields='*', 0, $limit);

        foreach($enrolments as $enrolment) {

    		$enrolment->processed = $date->getTimestamp();

    		$DB->update_record('cohortcateg_enrolmentlog', $enrolment);

            $trace->output('Adding user ' . trim($enrolment->user_idnumber) . ' to cohort "' .  trim($enrolment->cohort_idnumber) . '"...');

            $row = (array) $enrolment;

            $row['created'] = $date->getTimestamp();

            if(count($cohorts = $DB->get_records ('cohort', array( 'idnumber' => $row['cohort_idnumber']))) > 0) {

                if(false !== ($user = $DB->get_record ('user', array( 'idnumber' => $row['user_idnumber'])))) {

                    $row['error'] = 0;

                    foreach($cohorts as $cohort) {

                        if (! $DB->record_exists('cohort_members', array('cohortid' => $cohort->id, 'userid' => $user->id))) {

                            // --------------------------------------------------
                            // we could use the built in function to add the user to the cohort
                            // this would be: cohort_add_member($cohort->id, $user->id);   (from cohort/lib.php)
                            // but just to see more clearly what is going on in that function (to make it easier to roll back if needed)
                            // we create add the user here with the same way, and to do that we need some other fields
                            // to be set up with default values:
                            $record = new stdClass();
                            $record->cohortid  = $cohort->id;
                            $record->userid    = $user->id;
                            $record->timeadded = time();
                            $DB->insert_record('cohort_members', $record);
                            // --------------------------------------------------

                            $row['cohort_contextid'] = $cohort->contextid;

                            $DB->insert_record('cohortcateg_enrolments', $row);

                            $trace->output('User ' . $user->idnumber . ' (' . $user->id . ') added to cohort "' . $cohort->idnumber . '" (' . $cohort->id . ') /' . $cohort->contextid . '/');

                        } else {

                            $trace->output('User ' . $user->idnumber . ' (' . $user->id . ') already present in cohort "' . $cohort->idnumber . '" (' . $cohort->id . ') /' . $cohort->contextid . '/ skipping...');

                        }
                    }

                } else {

                    $row['error'] = 1;

                    $DB->insert_record('cohortcateg_enrolments', $row);

<<<<<<< Updated upstream
                    $trace->output('User does not exists');   
=======
                    $trace->output('User does not exist');
>>>>>>> Stashed changes
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
     * The function processes the cohortcateg_cohort db table and enrols the cohorts
     * onto all courses in a category with the a given role.
     * The cohort, the category and the role are all coming from the db table.
     * The function records diagnostical information to the cohortcateg_cohorts db table.
     * It records the time of processing as a unix timestamp to the "processed" field and also
     * record an error code to the error field. The possible values of this error code:
     * 0 means success, 1 means failure because of invalid role id
     *
     * @param  progress_trace  $trace  for diagnostic output
     * @param  int             $limit  max how many cohorts will be processed in one function call
     */
    public function add_cohort_to_category_courses(progress_trace $trace, $limit = 10000) {

        global $CFG, $DB;

        $role_shortname_field = $this->get_config('roleshortname');

        $date = new DateTime();

        $trace->output("\nEnroll cohorts to course categories...");

        $cohorts = $DB->get_records('cohortcateg_cohorts', array('processed' => NULL), '', '*', 0, $limit);

        $enrol = enrol_get_plugin('cohortcateg');

        foreach($cohorts as $cohort) {

            $cohort->error = 0;

	       if(false !== ($role = $DB->get_record ('role', array( 'shortname' => $cohort->role_shortname),'id'))) {

                $courses = array();

                $courses = $this->get_courses($courses, $cohort->category_id);

                foreach($courses as $course) {


                 	if ($DB->record_exists('enrol', array(
                            "roleid" => $role->id,
                            "customint1" => $cohort->cohort_id,
                            "courseid" => $course->id,
                            "enrol" => 'cohortcateg'))) {

	          	        $trace->output("\nCohort \"" . $cohort->cohort_name . "\" already exists in course \"". $course->shortname . "\" with role \"" . $cohort->role_shortname . "\" skipping...");

        			} else {

                        // using built-in function from https://github.com/moodle/moodle/blob/master/lib/enrollib.php
                        $enrol->add_instance($course, array(
                            'customint1' => $cohort->cohort_id,
                            'roleid' => $role->id)
                        );

	          	        $trace->output("\nCohort \"" . $cohort->cohort_name . "\" added to course \"". $course->shortname . "\" (" . $course->id . ") with role \"" . $cohort->role_shortname . "\"...");
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

    /**
     * Get all the courses in a category
     *
     * It is a recursive function that gives back all the courses in a category and in all its subcategories.
     * Initially it should be called with an empty $courses array and the related catwegory id.
     *
     * @param  array  $curses       array of courses
     * @param  int    $category_id  category id
     *
     * @return array  array of courses
     */
    private function get_courses($courses, $category_id) {

        global $DB;

        $courses = $DB->get_records('course', array('category' => $category_id));

        $sub_categories = $DB->get_records('course_categories', array('parent' => $category_id));

        foreach($sub_categories as $sub_category) {
            $courses = array_merge($courses, $this->get_courses($courses, $sub_category->id));
        }

        return $courses;
    }


    /**
     * Delete cohort and its members created by this plugin based on cohort id
     * and also remove it from all courses it was added (to clean up the enrol table)
     *
     * @param  int             $id    cohort id
     * @param  progress_trace  $trace for diagnostic output
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
     * Delete every cohorts along with its members created by this plugin
     *
     * It only works if we created the cohort with the component name "enrol_cohortcateg"
     * If the component field is empty in the moodle's cohort table, which means that the cohort
     * is created "manually", the this function can't delete it.
     *
     * @param  progress_trace  $trace for diagnostic output
     */
    public function delete_cohorts(progress_trace $trace) {

        global $DB;

        $trace->output("\nStart deleting cohorts...");

        $cohorts = $DB->get_records ('cohort', array( 'component' => 'enrol_cohortcateg'));

        foreach ($cohorts as $cohort) {

            $this->unenrol_cohort($cohort->id, $trace);

            $this->delete_cohort($cohort->id, $trace);

        }

        $trace->output("Deleting cohorts is done...\n");
    }

    /**
     * Unenrol users from a course enroled by a cohort
     *
     * @param  int             $cohort_id    cohort id
     * @param  int             $course_id    course id
     * @param  progress_trace  $trace        for diagnostic output
     */
    public function unenrol_cohort_course($cohort_id, $course_id, progress_trace $trace) {

        global $DB;

        $trace->output("\Removing role assignmens created by cohort " . $cohort_id .  " from course " . $course_id . " ...\n");
        // not implemented yet!!!
        // almost the same as in the unenrol cohort function but the course's context id must be also specified
        // so we only delete role assignment from the specified course

        $trace->output("\nUnenroling users enroled by cohort " . $cohort_id .  " from course " . $course_id . " ...\n");

        $enrol_id = $DB->get_field ('enrol', 'id', array( 'enrol' => 'cohortcateg', 'courseid' => $course_id, 'customint1' => $cohort_id));

        if($enrol_id) {
            $trace->output("\nUnenroling users from course " . $course_id . " enroled by cohort " . $cohort_id . " (enrol id: " . $enrol_id . ") ...");
            $DB->delete_records ('user_enrolments', array('enrolid' => $enrol_id));
        }
    }

    /**
     * Unenrol users from every course enroled by a cohort
     *
     * @param  int             $cohort_id    cohort id
     * @param  progress_trace  $trace        for diagnostic output
     */
    public function unenrol_cohort($cohort_id, progress_trace $trace) {

        global $DB;

        $trace->output("\nRemoving role assignmens created by cohort " . $cohort_id .  " from all courses...");

        $DB->delete_records ('role_assignments', array( 'component' => 'enrol_cohortcateg', 'itemid' => $cohort_id));

        $trace->output("\nUnenroling users enroled by cohort " . $cohort_id .  " from all courses...");

        $sql = "DELETE ue
                FROM mdl_user_enrolments ue
                INNER JOIN mdl_enrol e ON e.id = ue.enrolid AND e.enrol = 'cohortcateg' AND e.customint1 = :cohortid";

        $rs = $DB->execute($sql, array('cohortid' => $cohort_id));
    }



    /**
     * Attempt to make connection to the external database.
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
