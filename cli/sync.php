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
 * CLI sync for cohort category enrolment.
 *
 * Sample cron entry:
 * # 5 minutes past 4am
 * 5 4 * * * $sudo -u www-data /usr/bin/php /var/www/moodle/enrol/database/cli/sync.php
 *
 * Example of running from command line saving log to a dated file
 * php sync.php -v | tee log_$(date '+%Y-%m-%d-%T')
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *
 * @package    enrol_cohortcateg
 * @copyright  2014 Szilard Szabo {@link http://szilard.co.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once("$CFG->libdir/clilib.php");

error_reporting('E_ALL');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

//$DB->set_debug(true);

// We may need a lot of memory here.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array('verbose'=>false, 'help'=>false), array('v'=>'verbose', 'h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Execute cohort category sync with external database.
The enrol_cohortcateg plugin must be enabled and properly configured.

Options:
-v, --verbose         Print verbose progress information
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php enrol/database/cli/sync.php

Sample cron entry:
# 5 minutes past 4am
5 4 * * * sudo -u www-data /usr/bin/php /var/www/moodle/enrol/database/cli/sync.php
";

    echo $help;
    die;
}

if (!enrol_is_enabled('cohortcateg')) {
    cli_error('enrol_cohortcateg plugin is disabled, synchronisation stopped', 2);
}

if (empty($options['verbose'])) {
    $trace = new null_progress_trace();
} else {
    $trace = new text_progress_trace();
}

/** @var enrol_cohortcateg_plugin $enrol  */
$enrol = enrol_get_plugin('cohortcateg');

//$result = $result | $enrol->sync_cohorts($trace);

$limit = 10000;

$result = 0;

/*
$result = $result | $enrol->read_external($trace);

$result = $result | $enrol->process_cohorts ($trace, $limit);

$result = $result | $enrol->process_users ($trace, $limit);

$result = $result | $enrol->add_cohort_to_category_courses ($trace, $limit);
*/

$result = $result | enrol_cohort_sync($trace);

$trace->finished();

exit($result);



/**
 * Sync all cohort course links.
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_cohort_sync(progress_trace $trace, $courseid = NULL, $cohortlimit = 1, $courselimit = 1000) {
    
    global $CFG, $DB;

    // Unfortunately this may take a long time, this script can be interrupted without problems.
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);

    $trace->output('Starting user enrolment synchronisation...');

    // List cohorts created by this plugin which need enrol sync
    $sql = "SELECT DISTINCT(c.id)
			FROM {cohort_members} cm
			JOIN {cohort} c ON (c.id = cm.cohortid AND c.component = 'enrol_cohortcateg')
			JOIN {enrol} e ON (e.customint1 = cm.cohortid AND e.enrol = 'cohort' )
			JOIN {user} u ON (u.id = cm.userid AND u.deleted = 0)
			LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = cm.userid)
			WHERE ue.id IS NULL
			ORDER BY c.id"; 

	//print $sql;

	$cohorts = $DB->get_recordset_sql($sql, array("cohortlimit" => $cohortlimit), 0, $cohortlimit);

    // print 'cohorts:';
    // print_r($cohorts);
    // print "\n";

    foreach($cohorts as $cohort) {

	    // print 'cohort:';
	    // print_r($cohort);
	    // print "\n";

    	$trace->output("Start syncing courses with cohort " .  $cohort->id . "\n");

    	$params = array('cohortid' => $cohort->id);
	    
	    $count = "SELECT COUNT(DISTINCT(e.courseid)) ";
	    
	    $fields = "SELECT DISTINCT(e.courseid) as courseid, e.roleid as roleid ";

	    $sql = "FROM {cohort_members} cm
				JOIN {enrol} e ON (e.customint1 = cm.cohortid AND e.enrol = 'cohort' )
				JOIN {user} u ON (u.id = cm.userid AND u.deleted = 0)
				LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = cm.userid)
				WHERE ue.id IS NULL 
				AND e.customint1 = :cohortid 	
				ORDER BY e.courseid"; 

		$course_count = $DB->count_records_sql($count . $sql, $params);

	    $trace->output($course_count . " course need to be synced cohort " .  $cohort->id . "\n");
	    
		$courses = $DB->get_recordset_sql($fields . $sql, $params, 0, $courselimit);

		foreach($courses as $course) {

			//     print 'course:';
			//     print_r($course);
			//     print "\n";

    		$trace->output("Syncing course " . $course->courseid . " with cohort " .  $cohort->id . "\n");		

			// sync cohort enrolment with course
	   		$sql = "INSERT INTO {user_enrolments}
					SELECT '0',0,e.id,cm.userid,0,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()
					FROM  {cohort_members} cm
					JOIN  {enrol} e ON  (e.customint1 = cm.cohortid AND e.enrol = 'cohort' )
					JOIN  {user} u ON (u.id = cm.userid AND u.deleted = 0)
					LEFT JOIN  {user_enrolments} ue ON   (ue.enrolid = e.id AND ue.userid = cm.userid)
					WHERE  ue.id IS NULL 
					AND e.customint1 = :cohortid  
					AND e.courseid = :courseid";

			$rs1 = $DB->execute($sql, array('cohortid' => $cohort->id, 'courseid' => $course->courseid));

		    // print 'rs:';
		    // print_r($rs1);
		    // print "\n";

			$context = context_course::instance($course->courseid);

		    // print 'rs:';
		    // print_r($context);
		    // print "\n";

    		$trace->output("Assign role (" . $course->roleid . ") to enroled users into course " . $course->courseid . " (contextid " .  $context->id . ")\n");		

			// add role assignment to enroled users into the course
	   		$sql = "INSERT into {role_assignments}
					SELECT '0',:roleid,:contextid,ue.userid,UNIX_TIMESTAMP(),0,'enrol_cohortcateg',0,0
					FROM {user_enrolments} ue
					LEFT JOIN {enrol} e ON e.id = ue.enrolid
					LEFT JOIN {course} c ON c.id = e.courseid
					WHERE e.enrol = 'cohort'
					AND e.customint1 = :cohortid
					AND e.courseid = :courseid";

			$rs2 = $DB->execute($sql, array(
				'roleid'    => $course->roleid, 
				'contextid' => $context->id, 
				'cohortid'  => $cohort->id, 
				'courseid'  => $course->courseid)
			);	

		    // print 'rs:';
		    // print_r($rs2);
		    // print "\n";

    		//break;
		}

    	$courses->close();
		
		break;
	}

    $cohorts->close();

    return 0;
}


