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

define('ENT_INSTALLER_SYNC_MAX_WORKERS', 2);
define('JOB_INTERLEAVE', 2);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/lib/clilib.php'); // CLI only functions

// Ensure options are blanck;
unset($options);

// Now get cli options.

list($options, $unrecognized) = cli_get_params(
    array(
        'help'                          => false,
        'workers'                       => false,
        'distributed'                   => false,
        'verbose'                       => false,
        'simulate'                      => false,
        'fulldelete'                    => false,
        'role'                          => false,
        'unassignteachercategoryrole'   => false,
        'matchlevel'                    => false,
        'force'                         => false,
        'logroot'                       => false,
    ),
    array(
        'h' => 'help',
        'w' => 'workers',
        'd' => 'distributed',
        'v' => 'verbose',
        's' => 'simulate',
        'D' => 'fulldelete',
        'r' => 'role',
        'u' => 'unassignteachercategoryrole',
        'm' => 'matchlevel',
        'f' => 'force',
        'l' => 'logroot',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Command line ENT Sync worker.

        Options:
        -h, --help          Print out this help
        -w, --workers       Number of workers.
        -d, --distributed   Distributed operations.
        -v, --verbose       Provides lot of output
        -s, --simulate      Get all data for simulation but will NOT process any writing in database.
        -D, --fulldelete    Propagates a full delete option to all workers.
        -r, --role          Restricts sync to a specific role ('eleve', 'enseignant', 'administration').
        -u, --unassignteachercategoryrole          
                            Unassign given comma separated roles to teacher category.
        -m, --matchlevel    Index of tolerance level to match users ('10:LASTNAME/FIRSTNAME','15:USERNAME (default)','20:ID/LASTNAME','50:FULL NO USERNAME','100:FULL NO GUID','200:GUID ENT').
        -f, --force         Force updating all data.
        -l, --logroot       Root directory for logs.

        "; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

if ($options['workers'] === false) {
    $options['workers'] = ENT_INSTALLER_SYNC_MAX_WORKERS;
}

if (!empty($options['logroot'])) {
    $logroot = $options['logroot'];
} else {
    $logroot = $CFG->dataroot;
}

$allhosts = $DB->get_records('local_vmoodle', array('enabled' => 1));

// Make worker lists

$joblists = array();
$i = 0;
foreach ($allhosts as $h) {
    $joblist[$i][] = $h->id;
    $i++;
    if ($i == $options['workers']) {
        $i = 0;
    }
}

$params = '';
if (!empty($options['fulldelete'])) {
    $params .= '--fulldelete ';
}
if (!empty($options['force'])) {
    $params .= '--force ';
}
if (!empty($options['simulate'])) {
    $params .= '--simulate ';
}
if (!empty($options['verbose'])) {
    $params .= '--verbose ';
}
if (!empty($options['role'])) {
    $params .= '--role='.$options['role'].' ';
}
if (!empty($options['matchlevel'])) {
    $params .= '--matchlevel='.$options['matchlevel'];
}

// Start spreading workers, and pass the list of vhost ids. Launch workers in background

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
    $jobids = array();
    if (!empty($jl)) {
        $hids = implode(',', $jl);
        $workercmd = "$phpcmd {$CFG->dirroot}/local/ent_installer/cli/sync_hosts_worker.php --nodes=\"$hids\" --logfile={$logroot}/ent_sync_log_{$i}.log {$params}";
        
        if($options['distributed'] && (isset($CFG->ostype) && $CFG->ostype != 'WINDOWS')) 
            $workercmd .= ' &';

        mtrace("Executing $workercmd\n######################################################\n");

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

echo "done.";