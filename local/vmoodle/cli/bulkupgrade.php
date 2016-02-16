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
define('VMOODLE_UPGRADE_MAX_WORKERS', 2);
define('JOB_INTERLEAVE', 2);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/lib/clilib.php'); // CLI only functions

// Ensure options are blanck;
unset($options);

// Now get cli options.

list($options, $unrecognized) = cli_get_params(
    array(
        'help'             => false,
        'workers'          => false,
        'distributed'      => false,
        'allow-unstable'   => false,
        'logroot'          => false,
    ),
    array(
        'h' => 'help',
        'w' => 'workers',
        'd' => 'distributed',
        'a' => 'allow-unstable',
        'l' => 'logroot',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Command line ENT Global Updater.

        Options:
        -h, --help              Print out this help
        -w, --workers       Number of workers.
        -d, --distributed   Distributed operations.
        -a, --allow-unstable    Print out this help
        -l, --logroot           Root directory for logs.

        "; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

if ($options['workers'] === false) {
    $options['workers'] = VMOODLE_UPGRADE_MAX_WORKERS;
}

if (!empty($options['logroot'])) {
    $logroot = $options['logroot'];
} else {
    $logroot = $CFG->dataroot;
}

if (!empty($options['allow-unstable'])) {
    $allowunstable = '--allow-unstable';
} else {
    $allowunstable = '';
}

$allhosts = $DB->get_records('local_vmoodle', array('enabled' => 1));

$joblists = array();
$i = 0;
foreach ($allhosts as $h) {
    $joblist[$i][] = $h->vhostname;
    $i++;
    if ($i == $options['workers']) {
        $i = 0;
    }
}

mtrace("Start upgrading...\n");

$phpcmd = "php";
if(!isset($CFG->ostype) || $CFG->ostype == 'WINDOWS') {
    if(isset($CFG->phpinstallpath))
        $phpcmd = '"'.$CFG->phpinstallpath.'/php.exe"';
    if ($options['distributed'] && !@class_exists('COM'))
    {
        mtrace("Windows error : php_com_dotnet extension is required to work on distributed mode");
        die;
    }
}

$i = 1;
foreach ($joblist as $jl) {
    if (isset($jl)) {
        $vhosts = implode(',', $jl);
        
        $workercmd = "$phpcmd {$CFG->dirroot}/local/vmoodle/cli/bulkupgrade_worker.php --hosts=\"{$vhosts}\" --non-interactive {$allowunstable} --logfile={$logroot}/upgrade_worker_{$i}.log";

        if($options['distributed'] && (isset($CFG->ostype) && $CFG->ostype != 'WINDOWS')) 
            $workercmd .= ' &';

        mtrace("Executing :\n$workercmd\n######################################################\n");

        if($options['distributed'] && (!isset($CFG->ostype) || $CFG->ostype == 'WINDOWS')) {
            try {
                $shell = new COM("WScript.Shell");
                $return = $shell->Run($workercmd);
                mtrace("return code for worker $i : ".$return);
            }
            catch(Exception $ex) {
                mtrace("Error while invoking WScript for command $workercmd : \n".$ex->getMessage());
            }
        } else {
            $output = array();
            exec($workercmd, $output, $return);
            if ($return) {
                mtrace("Worker {$i} ended with error");
            }
            if (!$options['distributed']) {
                mtrace(implode("\n", $output));
            }
        }
        
        $i++;
        sleep(JOB_INTERLEAVE);
    }
}

//Upgrade master host wich is not into its self VMoodle hosts SQL table 
$workercmd = "$phpcmd {$CFG->dirroot}/admin/cli/upgrade.php --non-interactive {$allowunstable} > {$logroot}/upgrade_commun.log";
mtrace("Executing $workercmd\n######################################################\n");
$output = array();
exec($workercmd, $output, $return);
if ($return) {
    die("upgrade ended with error for master host");
}

echo "done.";
