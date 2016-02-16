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

define('CLI_SCRIPT', true);
define('ENT_INSTALLER_SYNC_INTERHOST', 1);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/lib/clilib.php'); // CLI only functions

// Now get cli options.

list($options, $unrecognized) = cli_get_params(
    array(
        'help'                          => false,
        'nodes'                         => false,
        'verbose'                       => false,
        'simulate'                      => false,
        'fulldelete'                    => false,
        'role'                          => false,
        'unassignteachercategoryrole'   => false,
        'matchlevel'                    => false,
        'force'                         => false,
        'logfile'                       => true,
        'logmode'                       => 'w',
    ),
    array(
        'h' => 'help',
        'n' => 'nodes',
        'v' => 'verbose',
        's' => 'simulate',
        'D' => 'fulldelete',
        'r' => 'role',
        'u' => 'unassignteachercategoryrole',
        'm' => 'matchlevel',
        'f' => 'force',
        'l' => 'logfile',
        'm' => 'logmode'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['nodes'])) {
    $help =
        "Command line ENT Sync worker.
        
        Options:
        -h, --help          Print out this help
        -n, --nodes         Node ids to work with.
        -v, --verbose       Provides lot of output
        -s, --simulate      Get all data for simulation but will NOT process any writing in database.
        -D, --fulldelete    propagates a full delete option to final workers
        -r, --role          Restricts sync to a specific role ('eleve', 'enseignant', 'administration').
        -u, --unassignteachercategoryrole          
                            Unassign given comma separated roles to teacher category.
        -m, --matchlevel    Index of tolerance level to match users ('10:LASTNAME/FIRSTNAME','15:USERNAME (default)','20:ID/LASTNAME','50:FULL NO USERNAME','100:FULL NO GUID','200:GUID ENT').
        -f, --force         Force updating all data.
        -l, --logfile       the log file to use. No log if not defined
        -m, --logmode       'a' to append or 'w' to overwrite (overwrite by default)

        "; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

if (empty($options['logmode'])) {
    $options['logmode'] = 'w';
}

if (!empty($options['logfile'])) {
    $LOG = fopen($options['logfile'], $options['logmode']);
}

// Fire sequential synchronisation.
$ouputline = "Worker started !!!";
mtrace($ouputline);
if (isset($LOG)) {
    fputs($LOG, $ouputline."\n");
};

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

$phpcmd = "php";
if($CFG->ostype == 'WINDOWS' && isset($CFG->phpinstallpath))
    $phpcmd = '"'.$CFG->phpinstallpath.'/php.exe"';

$nodes = explode(',', $options['nodes']);
foreach ($nodes as $nodeid) {
    $host = $DB->get_record('local_vmoodle', array('id' => $nodeid));
    $cmd = "$phpcmd {$CFG->dirroot}/local/ent_installer/cli/sync_users.php --host={$host->vhostname} $params";
    $ouputline = "\nExecuting : $cmd\n#------------------------------------------------------\n";
    mtrace($ouputline);
    if (isset($LOG)) {
        fputs($LOG, $ouputline);
    };
    
    try {
        $return = 0;
        $output = array();
        exec($cmd, $output, $return);
        if ($return) {
            throw new Exception("Error : local/vmoodle/cli/upgrade.php ended with error for host : {$host->vhostname}");
        }
        if (isset($LOG)) {
            fputs($LOG, implode("\n", $output)."\n");
        };
    }
    catch(Exception $ex) {
        $ouputline = "Error while executing $cmd : \n".$ex->getMessage();
        mtrace($ouputline);
        if (isset($LOG)) {
            fputs($LOG, $ouputline."\n");
        };
    }
    sleep(ENT_INSTALLER_SYNC_INTERHOST);
}

$ouputline = "\nWorker ended !!!";
mtrace($ouputline);
if (isset($LOG)) {
    fputs($LOG, $ouputline."\n");
    fclose($LOG);
};

exit(0); // 0 means success