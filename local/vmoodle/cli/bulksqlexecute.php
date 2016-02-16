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

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/lib/clilib.php'); // CLI only functions
require_once($CFG->dirroot.'/local/vmoodle/lib.php');

// Ensure options are blanck;
unset($options);

// Now get cli options.

list($options, $unrecognized) = cli_get_params(
    array(
        'help'             	=> false,
        'sql-file'          => '',
        'inc-nodes'   		=> false,
        'exc-nodes'         => false,
        'simulate'          => true,
        'interactive'       => true,
    ),
    array(
        'h' => 'help',
        'f' => 'sql-file',
        'i' => 'inc-nodes',
        'e' => 'exc-nodes',
        's' => 'simulate',
        'a' => 'interactive',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Command line SQL global executer.

        Options:
        -h, --help              Print out this help
        -f, --sql-file          Requiered, complete path to the sql file to execute
        -i, --inc-nodes         If specified purge only given nodes (comma separated shortname)
        -e, --exc-nodes         If specified purge all nodes except the given ones (comma separated shortname)
        -s, --simulate          If not specified or true SQL requests into file will only be displayed
        -a, --interactive       If false confirm prompt will be disabled

        "; 

    echo $help;
    die;
}

if(!file_exists($options['sql-file'])) {
    mtrace("Path given does not match to an existing file");
    die;
}
if (!empty($options['inc-nodes']) && !empty($options['exc-nodes'])) {
    mtrace("You cannot use args inc-nodes and exc-nodes both together !");
    die;
}
if(!$options['simulate'] && $options['interactive']) {
    $input = cli_input("Simulation is off, requests will be executed, continue ? (y/n)", '', array('y', 'n'));
    if ($input == 'n') {
        mtrace("operation aborted by user.");
        exit(1);
    }
}

$incNodes = "";
$excNodes = "";
$where = "";
$patternRNE = '/^[0-9]{7}[a-zA-Z]$/';
$protocol = substr($CFG->wwwroot, 0, strpos($CFG->wwwroot, '://'));

if (!empty($options['inc-nodes'])) {
    $incNodes = explode(',', $options['inc-nodes']);
    foreach($incNodes as $node) {
        if(!preg_match($patternRNE, $node) && $node != 'template' && $node != 'commun') {
            echo "Node '$node' is not a valid identifier (ex: RNE) !";
            die;
        }
        if($where == "")
            $where .= " AND vhostname LIKE '$protocol://$node%'";
        else
            $where .= " OR vhostname LIKE '$protocol://$node%'";
    }
} 

if (!empty($options['exc-nodes'])) {
    $excNodes = explode(',', $options['exc-nodes']);
    foreach($excNodes as $node) {
        if(!preg_match($patternRNE, $node) && $node != 'template' && $node != 'commun') {
            echo "Node '$node' is not a valid identifier (ex: RNE) !";
            die;
        }
        if($where == "")
            $where .= " AND vhostname NOT LIKE '$protocol://$node%'";
        else
            $where .= " AND vhostname NOT LIKE '$protocol://$node%'";
    }
} 

$allhosts = $DB->get_records_sql("SELECT * FROM {local_vmoodle} WHERE enabled=1 $where");

if(!preg_match('/commun/', $options['exc-nodes']) && (preg_match('/commun/', $options['inc-nodes']) || empty($options['inc-nodes']))) {
    $commun = new StdClass();
    $commun->vhostname = $CFG->wwwroot;
    $commun->vdbhost = $CFG->dbhost;
    $commun->vdblogin = $CFG->dbuser;
    $commun->vdbpass = $CFG->dbpass;
    $commun->vdbname = $CFG->dbname;
    $commun->vdbprefix = $CFG->prefix;
    $commun->dblibrary = $CFG->dblibrary;
    $commun->vdbtype = $CFG->dbtype;
    $commun->dboptions = $CFG->dboptions;
    $allhosts[] = $commun;
}

$handle = fopen($options['sql-file'], "r");
$contents = fread($handle, filesize($options['sql-file']));
fclose($handle);
$requests = array_filter(explode(';', $contents));

if($options['simulate'])
    mtrace("Start simulating...");
else
    mtrace("Start processing...");
mtrace('######################################################');
$i = 1;
foreach ($allhosts as $h) {
    mtrace(">Execution for host $i : {$h->vhostname}");
    if(!$options['simulate']) {
        $vdb = vmoodle_setup_DB($h);
    }
    foreach ($requests as $index => $req) {
        $requests[$index] = trim($req);
        mtrace('--');
        mtrace($requests[$index]);
        if(!$options['simulate']) {
            $res = $vdb->execute_query($requests[$index], SQLSRV_CURSOR_STATIC);
            if($res === false) {
                mtrace(' ** ERROR detected, exiting execution : ');
                mtrace(sqlsrv_errors());
                die();
            }
            if($vdb->get_last_request_type() == SQL_QUERY_SELECT) {
                if($vdb->get_num_rows($res) > 0) {
                    $resultset = $vdb->get_all_results_array($res);
                    $columns = array_keys($resultset[0]);
                    $cols = 'line';
                    foreach($columns as $col)
                        $cols .= " | $col";
                    mtrace($cols);
                    $currentline = '';
                    foreach($resultset as $numline => $resultline) {
                        $currentline = "$numline";
                        foreach($resultline as $value){
                            $currentline .= " | $value";
                        }
                        mtrace($currentline);
                    }
                }
                else
                    mtrace('** SELECT request does not return any row');
            }
        }
    }
    mtrace('');
    mtrace('######################################################');
    mtrace('');
    $i++;
}

echo "done.";
