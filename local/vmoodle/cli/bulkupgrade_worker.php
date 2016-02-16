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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

define('CLI_SCRIPT', true);
define('VMOODLE_UPGRADE_INTERHOST', 1);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/lib/clilib.php'); // CLI only functions

// Ensure options are blanck;
unset($options);

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'non-interactive'   => false,
        'allow-unstable'    => false,
        'hosts'             => false,
        'test'              => false,
        'logfile'           => true,
        'logmode'           => 'w',
        'help'              => false
    ),
    array(
        'l' => 'logfile',
        'm' => 'logmode',
        'h' => 'help'
    )
);

$interactive = empty($options['non-interactive']);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Command line Moodle upgrade.
Please note you must execute this script with the same uid as apache!

Site defaults may be changed via local/defaults.php.

Options:
--non-interactive     No interactive questions or confirmations
--allow-unstable      Upgrade even if the version is not marked as stable yet,
                      required in non-interactive mode.
--hosts               Switch to each comma separated virtual host and process
--test                Stops after host resolution, telling the actual config that will be used
-l, --logfile         the log file to use. No log if not defined
-m, --logmode         'a' to append or 'w' to overwrite (overwrite by default)
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php admin/cli/upgrade.php --hosts=http://my-virtual.moodle.org,http://my-virtual2.moodle.org
"; //TODO: localize - to be translated later when everything is finished

    mtrace($help);
    die;
}

if (empty($options['hosts'])) {
    mtrace('hosts argument required to process the master Moodle use /admin/cli/upgrade.php');
    die;
}

if (empty($options['logmode'])) {
    $options['logmode'] = 'w';
}

if (!empty($options['logfile'])) {
    $LOG = fopen($options['logfile'], $options['logmode']);
}

if (!empty($options['allow-unstable'])) {
    $allowunstable = '--allow-unstable';
} else {
    $allowunstable = '';
}

$ouputline = "Worker started !!!\n";
mtrace($ouputline);
if (isset($LOG)) {
    fputs($LOG, $ouputline);
};

$phpcmd = "php";
if($CFG->ostype == 'WINDOWS' && isset($CFG->phpinstallpath))
    $phpcmd = '"'.$CFG->phpinstallpath.'/php.exe"';

$hostsgiven = explode(',', $options['hosts']);
foreach($hostsgiven as $vhost) {
    if(empty($vhost)) continue;
    
    $workercmd = "$phpcmd {$CFG->dirroot}/local/vmoodle/cli/upgrade.php --host=\"{$vhost}\" --non-interactive {$allowunstable}";
    $ouputline = "\nExecuting : $workercmd\n#------------------------------------------------------\n";
    mtrace($ouputline);
    if (isset($LOG)) {
        fputs($LOG, $ouputline);
    };

    try {
        $return = 0;
        $output = array();
        exec($workercmd, $output, $return);

        if ($return) {
            throw new Exception("Error : local/vmoodle/cli/upgrade.php ended with error for host : {$vhost}");
        }

        if (isset($LOG)) {
            fputs($LOG, implode("\n", $output)."\n");
        };
    }
    catch(Exception $ex) {
        $ouputline = "Error while executing $workercmd : \n".$ex->getMessage();
        mtrace($ouputline);
        if (isset($LOG)) {
            fputs($LOG, $ouputline."\n");
        };
    }
    sleep(VMOODLE_UPGRADE_INTERHOST);
}

$ouputline = "\nWorker ended !!!";
mtrace($ouputline);
if (isset($LOG)) {
    fputs($LOG, $ouputline."\n");
    fclose($LOG);
};
exit(0); // 0 means success
