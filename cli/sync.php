<?php

/****************************************************************

File:     /enrol/cohortcateg/cli/sync.php

Purpose:  Command line script for the plugin.

Notes:
		  - it is required to use the web server account when executing PHP CLI scripts
	      - you need to change the "www-data" to match the apache user account
	      - use "su" if "sudo" not available

****************************************************************/

// only run from the command line
define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once("$CFG->libdir/clilib.php");

// output all errors
error_reporting('E_ALL');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// we will measure execution time
$time_start = microtime(true);

// uncomment this to get more detailed db debug info
// $DB->set_debug(true);

// we may need a lot of time and memory to ruin this script
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// get the cli options
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

Example of running the cript from the command line saving its output to a log file
with the current date and time in its name:
php sync.php -v | tee log_sync_$(date '+%Y-%m-%d-%T') 2>&1

";

    echo $help;
    die;
}

// only run the script if the plugin is enabled
if (!enrol_is_enabled('cohortcateg')) {
    cli_error('enrol_cohortcateg plugin is disabled, execution stopped', 2);
}

// set level of verbosity
if (empty($options['verbose'])) {
    $trace = new null_progress_trace();
} else {
    $trace = new text_progress_trace();
}

$enrol = enrol_get_plugin('cohortcateg');

$result = 0;

$result = $result | $enrol->read_external($trace);

$result = $result | $enrol->process_cohorts($trace);

$result = $result | $enrol->process_users ($trace);

//$result = $result | $enrol->add_cohort_to_category_courses ($trace);

$trace->output("\nResult: $result\n");

$time_end = microtime(true);

$execution_time = ($time_end - $time_start)/60;

$trace->output("\nTotal execution time: $execution_time mins\n");

$trace->finished();
