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

// Ensure options are blanck;
unset($options);

// Now get cli options.

list($options, $unrecognized) = cli_get_params(
    array(
        'help'               => false,
        'config'             => false,
        'cache'              => true,
        'theme'              => false,
        'inc-nodes'          => false,
        'exc-nodes'          => false,
        'only-enabled'       => false,
    ),
    array(
        'h' => 'help',
        'f' => 'config',
        'c' => 'cache',
        't' => 'theme',
        'i' => 'inc-nodes',
        'e' => 'exc-nodes',
        'o' => 'only-enabled',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Command line Global cache purger.

        Options:
        -h, --help              Print out this help
        -f, --config            If specified invalidate the configuration for customized cache store
        -c, --cache             If specified purge all server caches (default true)
        -t, --theme             If specified purge only server and client side theme files caches
        -i, --inc-nodes         If specified purge only given nodes (comma separated shortname)
        -e, --exc-nodes         If specified purge all nodes except the given ones (comma separated shortname)
        -o, --only-enabled      If specified do not purge disabled nodes
        "; 

    echo $help;
    die;
}

if (!empty($options['inc-nodes']) && !empty($options['exc-nodes'])) {
    echo "You cannot use args inc-nodes and exc-nodes both together !";
    die;
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
$restrict = '1=1';
if (!empty($options['only-enabled']))
    $restrict = 'enabled=1';
$allhosts = $DB->get_records_sql("SELECT * FROM {local_vmoodle} WHERE $restrict $where");

if(!preg_match('/commun/', $options['exc-nodes']) && (preg_match('/commun/', $options['inc-nodes']) || empty($options['inc-nodes']))) {
    $commun = new StdClass();
    $commun->vdatapath = $CFG->dataroot;
    $commun->vhostname = $CFG->wwwroot;
    $allhosts[] = $commun;
}
mtrace("Start purging...");

$caches = array();
if($options['config'])
    $caches['config-cache'] = 'muc';
if($options['cache'])
    $caches['cache'] = 'cache';
if($options['theme'])
    $caches['theme-cache'] = 'localcache';

if($CFG->ostype != 'WINDOWS') {
    mtrace('Only available on Windows yet, exiting.');
    exit(0);
}

foreach ($allhosts as $h) {
    // If a specific drive is used for local cache
    if(isset($CFG->local_vmoodle_localcachedir)) {
        $splitted_path_local = explode('/', rtrim(str_replace("\\", '/', $CFG->local_vmoodle_localcachedir), '/'));
        if(count($splitted_path_local) > 0) {
            $splittedvdatapath = explode('/', rtrim(str_replace("\\", '/', $h->vdatapath), '/'));
            $splitted_path_local[] = end($splittedvdatapath);
            $h->vlocalcachedir = implode('/', $splitted_path_local);
        }
    }
    foreach($caches as $cachetype => $cache) {
        if(isset($h->vlocalcachedir) && $cachetype == 'theme-cache') 
            $cacheDir = str_replace('/','\\', $h->vlocalcachedir).'\\'.$cache.'\\';
        else
            $cacheDir = str_replace('/','\\', $h->vdatapath).'\\'.$cache.'\\';

        if(file_exists($cacheDir)) {
            $workercmd = 'rd "'.$cacheDir.'" /S /Q';

            mtrace(">Removing $cachetype for {$h->vhostname}");
            $output = array();
            
            exec($workercmd, $output, $return);
            if ($return) 
                mtrace("!!! Worker ended with error");
        }
        else {
            mtrace(">Cache for {$h->vhostname} already empty ($cacheDir) !");
        }
    }
    mtrace('######################################################');
}

echo "done.";