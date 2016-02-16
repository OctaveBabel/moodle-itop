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
 * lib.php
 * 
 * General library for vmoodle.
 *
 * @package local-vmoodle
 * @category local
 * @author Gabriel Rosset (gabriel.rosset@gmail.com)
 * @author Bruce Bujon (bruce.bujon@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
require_once("{$CFG->dirroot}/local/vmoodle/bootlib.php");
require_once("{$CFG->dirroot}/local/vmoodle/filesystemlib.php");

/** Define constants */
define('VMOODLE_LIBS_DIR', $CFG->dirroot.'/local/vmoodle/plugins/');
define('VMOODLE_PLUGINS_DIR', $CFG->dirroot.'/local/vmoodle/plugins/');

if (!defined('RPC_SUCCESS')) {
    define('RPC_TEST', 100);
    define('RPC_SUCCESS', 200);
    define('RPC_FAILURE', 500);
    define('RPC_FAILURE_USER', 501);
    define('RPC_FAILURE_CONFIG', 502);
    define('RPC_FAILURE_DATA', 503); 
    define('RPC_FAILURE_CAPABILITY', 510);
    define('MNET_FAILURE', 511);
    define('RPC_FAILURE_RECORD', 520);
    define('RPC_FAILURE_RUN', 521);
}

/**
 * Define MySQL and PostgreSQL paths for commands.
 */
// Windows.
if ($CFG->ostype == 'WINDOWS') {
    $CFG->vmoodle_cmd_mysql            =     'C:/Program Files/MySQL/mysql.exe';
    $CFG->vmoodle_cmd_mysqldump        =     'C:/Program Files/MySQL/mysqldump.exe';
    $CFG->vmoodle_cmd_pgsql            =     'C:/Program Files/pgsql/pgsql.exe';
    $CFG->vmoodle_cmd_pgsqldump        =     'C:/Program Files/pgsql/pgsqldump.exe';
} else {
    // Linux.
    $CFG->vmoodle_cmd_mysql            =     '/usr/bin/mysql';
    $CFG->vmoodle_cmd_mysqldump        =     '/usr/bin/mysqldump';
    $CFG->vmoodle_cmd_pgsql            =     '/usr/bin/pgsql';
    $CFG->vmoodle_cmd_pgsqldump        =     '/usr/bin/pgsqldump';
}

/** Define commands' constants */
$vmcommands_constants = array(
    'prefix' => $CFG->prefix,
    'wwwroot' => $CFG->wwwroot,
);

// Loading plugin librairies
$plugin_libs = glob($CFG->dirroot.'/local/vmoodle/plugins/*/lib.php');
foreach ($plugin_libs as $lib) {
    require_once $lib;
}

/**
 * get the list of available vmoodles
 * @return an array of vmoodle objects
 */
function vmoodle_get_vmoodles() {
    global $DB;
    if ($vmoodles = $DB->get_records('local_vmoodle')) {
        return $vmoodles;
    }
    return array();
}

/**
 * setup and configure a mnet environment that describes this vmoodle 
 * @uses $USER for generating keys
 * @uses $CFG
 * @param object $vmoodle
 * @param handle $cnx a connection
 */
function vmoodle_setup_mnet_environment($vmoodle) {
    global $USER, $CFG;

    // Make an empty mnet environment.
    $mnet_env = new mnet_environment();

    $mnet_env->wwwroot              = $vmoodle->vhostname;
    $mnet_env->ip_address           = $CFG->local_vmoodle_vmoodleip;
    $mnet_env->keypair              = array();
    $mnet_env->keypair              = mnet_generate_keypair(null);
    $mnet_env->public_key           = $mnet_env->keypair['certificate'];
    $details                        = openssl_x509_parse($mnet_env->public_key);
    $mnet_env->public_key_expires   = $details['validTo_time_t'];

    return $mnet_env;
}

/**
 * setup services for a given mnet environment in a database
 * @uses $CFG
 * @param object $mnet_env an environment with valid id
 * @param handle $cnx a connection to the target bdd
 * @param array $services an object that holds service setup data
 */
function vmoodle_add_services(&$vmoodle, $mnet_env, $services) {
    if (!$mnet_env->id) {
        return false;
    }
    if ($services) {
        $vdb = vmoodle_setup_DB($vmoodle);
        $record = new stdClass();
        foreach ($services as $service => $keys) {
            $record->hostid = $mnet_env->id;
            $record->serviceid = $service;
            $record->publish = $keys['publish'];
            $record->subscribe = $keys['subscribe'];
            $vdb->insert_record('mnet_host2service', $record);
        }
    }
}

/**
 * get available services in the master
 * @return array of service descriptors.
 */
function vmoodle_get_service_desc() {
    global $DB;

    $services = $DB->get_records('mnet_service', array('offer' => 1));

    $service_descriptor = array();

    if ($services) {
        foreach ($services as $service) {
            $service_descriptor[$service->id]['publish'] = 1;
            $service_descriptor[$service->id]['subscribe'] = 1;
        }
    }
    return $service_descriptor;
}

/**
 * given a complete mnet_environment record, and a connection
 * record this mnet host in remote database. If the record is
 * a new one, gives back a completed env with valid remote id.
 * @param object $mnet_env
 * @return the inserted mnet_env object
 */
function vmoodle_register_mnet_peer(&$vmoodle, $mnet_env) {
    $mnet_array = get_object_vars($mnet_env);
    $vdb = vmoodle_setup_DB($vmoodle);
    $record = new stdClass();
    
    foreach($mnet_array as $key => $value) {
        $record->$key = $value;
    }
    
    if (empty($mnet_env->id)) {
        $mnet_env->id = $vdb->insert_record('mnet_host', $record);
    } else {
        $vdb->update_record('mnet_host', $record);
    }
    return $mnet_env;
}

/**
 * get the mnet_env record for an host
 * @param object $vmoodle
 * @return object a mnet_host record
 */
function vmoodle_get_mnet_env(&$vmoodle) {
    global $DB;

    $mnet_env = $DB->get_record('mnet_host', array('wwwroot' => $vmoodle->vhostname));
    return $mnet_env;
}

/**
 * unregister a vmoodle from the whole remaining network
 * @uses $CFG
 * $param object $vmoodle
 * @param handle $cnx
 * @param object $fromvmoodle
 */
function vmoodle_unregister_mnet(&$vmoodle, $fromvmoodle ) {
    global $CFG;

    if ($fromvmoodle) {
        $vdbprefix = $fromvmoodle->vdbprefix;
    } else {
        $vdbprefix = $CFG->prefix;
    }
    
    $vdb = vmoodle_setup_DB($fromvmoodle);
    if (!$vdb) {
        throw new moodle_exception('vmoodle_unregister_mnet', 'local_vmoodle', '', null, '[local/vmoodle/lib.php] VMoodle peer connection error');
    }
    // cleanup all services for the deleted host
    $vdb->delete_records_select("mnet_host2service", "hostid = (SELECT id FROM {$vdbprefix}mnet_host WHERE wwwroot = ?)", array($vmoodle->vhostname));
    
    // Delete the host.
    $vdb->delete_records_select("mnet_host", "wwwroot = ?", array($vmoodle->vhostname));
}

/**
 * drop a vmoodle database
 * @param object $vmoodle
 * @param handle $side_cnx
 */
function vmoodle_drop_database(&$vmoodle) {
    
    $vdb = vmoodle_setup_DB($vmoodle);
    
    if (!$vdb) {
        $erroritem->message = get_string('couldnotconnecttodb', 'local_vmoodle');
        $erroritem->on = 'db';
        return $erroritem;
    } else {
        $res = $vdb->drop_database($vmoodle->vdbname);
        if (!$res) {
            $erroritem->message = get_string('couldnotdropdb', 'local_vmoodle');
            $erroritem->on = 'db';
            return $erroritem;
        }
    }
    return false;
}

/**
 * load a bulk template in databse
 * @param object $vmoodle
 * @param string $bulfile a bulk file of queries to process on the database
 * @param handle $cnx
 * @param array $vars an array of vars to inject in the bulk file before processing
 */
function vmoodle_load_db_template(&$vmoodle, $bulkfile, $vdb = null, $vars=null, $filter=null){
    global $CFG;

    $local_cnx = 0;
    if (is_null($vdb) || $vmoodle->vdbtype == 'postgres') {
        // Postgress MUST make a new connection to ensure db is bound to handle.
        $vdb = vmoodle_setup_DB($vmoodle);
        $local_cnx = 1;
    }

    // Get dump file.

    if (file_exists($bulkfile)) {
        $sql = file($bulkfile);

        // converts into an array of text lines
        $dumpfile = implode("", $sql);
        if ($filter) {
            foreach ($filter as $from => $to) {
                $dumpfile = mb_ereg_replace(preg_quote($from), $to, $dumpfile);
            }
        }
        // Insert any external vars.
        if (!empty($vars)) {
            foreach ($vars as $key => $value) {
                $dumpfile = str_replace("<%%$key%%>", $value, $dumpfile);
            }
        }
        $sql = explode ("\n", $dumpfile);
        // Cleanup unuseful things.
        if ($vmoodle->vdbtype == 'mysql') {
            $sql = preg_replace("/^--.*/", "", $sql);
            $sql = preg_replace("/^\/\*.*/", "", $sql);
        }
        $dumpfile = implode("\n", $sql);
    } else {
        echo "vmoodle_load_db_template : Bulk file not found";
        return false;
    }
    /// split into single queries
    $dumpfile = str_replace("\r\n", "\n", $dumpfile); // Translates to Unix LF.
    $queries = preg_split("/;\n/", $dumpfile);
    /// feed queries in database
    $i = 0;
    $j = 0;
    if (!empty($queries)) {
        foreach ($queries as $query) {
            $query = trim($query); // Get rid of trailing spaces and returns
            if ($query == '') {
                continue; // Avoid empty queries.
            }
            $query = mb_convert_encoding($query, 'iso-8859-1', 'auto');
            
            if (!$res = $vdb->execute_query($query)) {
                echo "<hr/>load error on <br/>" . $vmoodle->vdbname . "<hr/>";
                $j++;
            } else {
                $i++;
            }
        }
    }
    echo "loaded : $i queries succeeded, $j queries failed<br/>";
    if ($local_cnx) {
        $vdb->dispose();
    }
    return false;
}

/**
 * Get available platforms to send Command.
 * @return array The availables platforms based on MNET or Vmoodle table.
 */
function get_available_platforms() {
    global $CFG, $DB;

    // Getting description of master host.
    $master_host = $DB->get_record('course', array('id' => 1));

    // Setting available platforms.
    $aplatforms = array();
    if (@$CFG->local_vmoodle_host_source == 'vmoodle') {
        $id = 'vhostname';
        $records = $DB->get_records('local_vmoodle', array(), 'name', $id.', name');
        if (!empty($CFG->vmoodledefault)) {
            $records[] = (object) array($id => $CFG->wwwroot, 'name' => $master_host->fullname);
        }
    } else {
        $id = 'wwwroot';
        $moodleapplication = $DB->get_record('mnet_application', array('name' => 'moodle'));
        $records = $DB->get_records('mnet_host', array('deleted' => 0, 'applicationid' => $moodleapplication->id), 'name', $id.', name');
        foreach ($records as $key => $record) {
            if ($record->name == '' || $record->name == 'All Hosts')
            unset($records[$key]);
        }
        $records[] = (object) array($id => $CFG->wwwroot, 'name' => $master_host->fullname);
    }
    if ($records) {
        foreach ($records as $record) {
            $aplatforms[$record->$id] = $record->name;
        }
        asort($aplatforms);
    }

    return $aplatforms;
}

/**
 * Return html help icon from library help files.
 * @param string $library The vmoodle library to display help file.
 * @param string $helpitem The help item to display.
 * @param string $title The title of help.
 * @return string Html span with help icon.
 */
function help_button_vml($library, $helpitem, $title) {
    global $OUTPUT;

    //WAFA: help icon no longer take links, it now takes identifiers to 
    //return $OUTPUT->help_icon('helprouter.html&amp;library='.$library.'&amp;helpitem='.$helpitem, 'local_vmoodle', false);
    return "";//$OUTPUT->help_icon('helprouter.html&amp;library='.$library.'&amp;helpitem='.$helpitem, 'local_vmoodle', false);
    
}

/**
 * Get the parameters' values from the placeholders.
 * We return both canonic name of the variable and replacement value
 * @param array $matches The placeholders found.
 * @param array $data The parameters' values to insert.
 * @param bool $parameters_replace True if variables should be replaced (optional).
 * @param bool $contants_replace True if constants should be replaced (optional).
 * @return string The parameters' values.
 */
function replace_parameters_values($matches, $params, $parameters_replace = true, $constants_replace = true) {
    global $vmcommands_constants;

    // Parsing constants.
    if ($constants_replace 
            && empty($matches[1]) 
                    && array_key_exists($matches[2], $vmcommands_constants)) {
        $value = $vmcommands_constants[$matches[2]];
        // Parsing parameter
    } else if ($parameters_replace && !empty($matches[1]) && array_key_exists($matches[2], $params)) {
        $value = $params[$matches[2]]->getValue();
        /*
        $paramtype = $params[$matches[2]]->getType();
        if ($paramtype == 'text' || $paramtype == 'ltext'){
            // probably obsolete when transferring to Moodle placeholders
            // $value = str_replace("'", "''", $params[$matches[2]]->getValue());
            $value = $params[$matches[2]]->getValue();
        } else {
            $value = $params[$matches[2]]->getValue();
        }
        */

    // Leave untouched
    } else {
        return array($matches[2], $matches[0]);
    }

    // Checking if member is asked.
    if (isset($matches[3]) && is_array($value)) {
        $value = $value[$matches[3]];
    }

    return array($matches[2], $value);
}

/**
 * Print the start of a collapsable block.
 * @param string $id The id of the block.
 * @param string $caption The caption of the block.
 * @param string $classes The CSS classes of the block.
 * @param string $displayed True if the block is displayed by default, false otherwise.
 */
function print_collapsable_bloc_start($id, $caption, $classes = '', $displayed = true) {
    global $CFG, $OUTPUT;

    $caption = strip_tags($caption);

    $pixpath = ($displayed) ? '/t/switch_minus' : '/t/switch_plus';
    echo '<div id="vmblock_'.$id.'">'.
    '<div class="header" onclick="require([\'jquery\'], function($) {$(\'#vmblock_'.$id.' div.bvmc:last-child\').slideToggle(\'swing\');});">'.
                '<div class="title">'.
                    '<input '.
                        'type="image" class="hide-show-image" '.
                        'src="'.$OUTPUT->pix_url($pixpath).'" '.
                        'alt="'.get_string('show').' '.strip_tags($caption).'" '.
                        'title="'.get_string('show').' '.strip_tags($caption).'"/>'.
                    '<h2>'.strip_tags($caption).'</h2>'.
                '</div>'.
            '</div>';
            $hidden = ($displayed) ? '' : ' style="display:none"';
            echo '<div class="content bvmc" '.$hidden.'>';
}

/**
 * Print the end of a collapsable block.
 */
function print_collapsable_block_end() {
    echo '</div></div>';
}

/**
 * Load a vmoodle plugin and cache it.
 * @param string $plugin_name The plugin name.
 * @return Command_Category The category plugin.
 */
function load_vmplugin($plugin_name) {
    global $CFG;
    static $plugins = array();

    if (!array_key_exists($plugin_name, $plugins)) {
        $plugins[$plugin_name] = include_once($CFG->dirroot.'/local/vmoodle/plugins/'.$plugin_name.'/config.php');
    }
    return $plugins[$plugin_name];
}

/**
 * Get available templates for defining a new virtual host.
 * @return array The availables templates, or EMPTY array.
 */
function vmoodle_get_available_templates() {
    global $CFG;

    // Scans the templates.
    if (!filesystem_file_exists('vmoodle', $CFG->dataroot)) {
        mkdir($CFG->dataroot.'/vmoodle');
    }
    $dirs = filesystem_scan_dir('vmoodle', FS_IGNORE_HIDDEN, FS_ONLY_DIRS, $CFG->dataroot);
    $vtemplates = preg_grep("/^(.*)_vmoodledata$/", $dirs);

    // Retrieves template(s) name(s).
    $templatesarray = array();
    if ($vtemplates) {
        foreach ($vtemplates as $vtemplatedir) {
            preg_match("/^(.*)_vmoodledata/", $vtemplatedir, $matches);
            $templatesarray[$matches[1]] = $matches[1];
            if (!isset($first)) {
                $first = $matches[1];
            }
        }
    }

    $templatesarray[] = get_string('reactivetemplate', 'local_vmoodle');

    return $templatesarray;
}

/**
 * Make a fake vmoodle that represents the current host database configuration.
 * @uses $CFG
 * @return object The current host's database configuration.
 */
function vmoodle_make_this(){
    global $CFG;

    $thismoodle = new stdclass;
    $thismoodle->vdbtype = $CFG->dbtype;
    $thismoodle->vdbhost = $CFG->dbhost;
    $thismoodle->vdblogin = $CFG->dbuser;
    $thismoodle->vdbpass = $CFG->dbpass;
    $thismoodle->vdbname = $CFG->dbname;
    //$thismoodle->vdbpersist    = $CFG->dbpersist;    //not available in 2.2
    $thismoodle->vdbprefix    = $CFG->prefix;

    return $thismoodle;
}

/**
 * Dumps a SQL database for having a snapshot.
 * @param object $vmoodle The Vmoodle object.
 * @param string $outputfile The output SQL file.
 * @return bool If TRUE, dumping database was a success, otherwise FALSE.
 */
function vmoodle_dump_database($vmoodle, $outputfile) {
    global $CFG;

    // Separating host and port, if sticked.
    if (strstr($vmoodle->vdbhost, ':') !== false) {
        list($host, $port) = split(':', $vmoodle->vdbhost);
    } else {
        $host = $vmoodle->vdbhost;
    }

    // By default, empty password.
    $pass = '';
    $pgm = null;
    if ($vmoodle->vdbtype == 'sqlsrv') { // SQL Server.
        $cmd = "-vhost {$host} -vusr {$vmoodle->vdblogin} -vpwd {$vmoodle->vdbpass} -vdb {$vmoodle->vdbname} -vpath ".dirname($outputfile);
        if(empty($CFG->local_vmoodle_cmd_sqlsrvdump))
        {
            print_error('errorsqlsrvdumpscriptnotdefined','local_vmoodle');
        }
        $pgm = stripslashes($CFG->local_vmoodle_cmd_sqlsrvdump);
    }
    else if ($vmoodle->vdbtype == 'mysql' || $vmoodle->vdbtype == 'mysqli') { // MysQL.
        // Default port.
        if (empty($port)) {
            $port = 3306;
        }

        // Password.
        if (!empty($vmoodle->vdbpass)) {
            $pass = "-p".escapeshellarg($vmoodle->vdbpass);
        }

        // Making the command.
        if ($CFG->ostype == 'WINDOWS') {
            $cmd = "-h{$host} -P{$port} -u{$vmoodle->vdblogin} {$pass} {$vmoodle->vdbname}";
            $cmd .= " > " . $outputfile;
        } else {
            $cmd = "-h{$host} -P{$port} -u{$vmoodle->vdblogin} {$pass} {$vmoodle->vdbname}";
            $cmd .= " > " . escapeshellarg($outputfile);
        }

        // MySQL application (see 'vconfig.php').
        $pgm = (!empty($CFG->local_vmoodle_cmd_mysqldump)) ? stripslashes($CFG->local_vmoodle_cmd_mysqldump) : false;
    } elseif ($vmoodle->vdbtype == 'postgres') { // PostgreSQL.
        // Default port.
        if (empty($port)) {
            $port = 5432;
        }

        // Password.
        if (!empty($vmoodle->vdbpass)) {
            $pass = $vmoodle->vdbpass;
        }

        // Making the command, (if needed, a password prompt will be displayed).
        if ($CFG->ostype == 'WINDOWS') {
            $cmd = " -d -b -Fc -h {$host} -p {$port} -U {$vmoodle->vdblogin} {$vmoodle->vdbname}";
            $cmd .= " > " . $outputfile;
        } else {
            $cmd = " -d -b -Fc -h {$host} -p {$port} -U {$vmoodle->vdblogin} {$vmoodle->vdbname}";
            $cmd .= " > " . escapeshellarg($outputfile);
        }

        // PostgreSQL application (see 'vconfig.php').
        $pgm = (!empty($CFG->local_vmoodle_cmd_pgsqldump)) ? $CFG->vmoodle_cmd_pgsqldump : false;
    }
    else {
        throw new moodle_exception('vmoodle_dump_database', 'local_vmoodle', '', null, '[local/vmoodle/lib.php] vdbtype not implemented');
    }

    if (!$pgm) {
        print_error("Database dump command not available");
        return false;
    } else {
        $phppgm = str_replace("\\", '/', $pgm);
        $phppgm = str_replace("\"", '', $phppgm);
        $pgm = str_replace('/', DIRECTORY_SEPARATOR, $pgm);

        if ($vmoodle->vdbtype != 'sqlsrv' && !is_executable($phppgm)) {
            error("Database dump command $phppgm does not match any executable");
            return false;
        }
        // Final command.
        if ($vmoodle->vdbtype != 'sqlsrv')
            $cmd = $pgm.' '.$cmd;
        else
            $cmd = "powershell $pgm $cmd";

        // Prints log messages in the page and in 'cmd.log'.
        if ($LOG = fopen(dirname($outputfile).'/cmd.log', 'a')) {
            fwrite($LOG, $cmd."\n");
        }

        // Executes the SQL command.
        exec($cmd, $execoutput, $returnvalue);
        if ($LOG) {
            foreach ($execoutput as $execline) {
                fwrite($LOG, $execline."\n");
            }
            fwrite($LOG, $returnvalue."\n");
            fclose($LOG);
        }
    }

    // End with success.
    return true;
}

/**
 * Loads a complete database dump from a template, and does some update.
 * @uses $CFG, $DB
 * @param object $vmoodledata All the Host_form data.
 * @param array $outputfile The variables to inject in setup template SQL.
 * @return bool If true, loading database from template was sucessful, otherwise false.
 */
function vmoodle_load_database_from_template($vmoodledata) {
    global $CFG, $DB;

    // Gets the HTTP adress scheme (http, https, etc...) if not specified.
    if (is_null(parse_url($vmoodledata->vhostname, PHP_URL_SCHEME))) {
        $vmoodledata->vhostname = parse_url($CFG->wwwroot, PHP_URL_SCHEME).'://'.$vmoodledata->vhostname;
    }

    $manifest = vmoodle_get_vmanifest($vmoodledata->vtemplate);
    $hostname = mnet_get_hostname_from_uri($CFG->wwwroot);
    $description = $DB->get_field('course', 'fullname', array('id' => SITEID)); 
    $cfgipaddress = gethostbyname($hostname);
    
    if($vmoodledata->vdbtype == 'sqlsrv') {
        if ($manifest['templatevdbprefix'] != $vmoodledata->vdbprefix) {
            print_error('changeprefixforsqlsrvnotimplemented', 'local_vmoodle');
            return false;
        }
        
        $pgm = (!empty($CFG->local_vmoodle_cmd_sqlsrvmount)) ? stripslashes($CFG->local_vmoodle_cmd_sqlsrvmount) : false;
        $pgm = str_replace("/", DIRECTORY_SEPARATOR, $pgm);

        // Take the host given (SQL host specified can be different)
        if (strstr($vmoodledata->vdbhost, ':') !== false) {
            list($vmoodledata->vdbhost, $vmoodledata->vdbport) = split(':', $vmoodledata->vdbhost);
        }
        
        //$dbsource = substr($vmoodledata->vtemplate, strrpos($vmoodledata->vtemplate, "_"));
        $dbsource = vmoodle_get_dbname_for_templatename($vmoodledata->vtemplate);
        if(!$dbsource) {
            print_error('templatenotfound', 'local_vmoodle');
        }
        
        $cmd = "-vhost {$vmoodledata->vdbhost} -vusr {$vmoodledata->vdblogin} -vpwd {$vmoodledata->vdbpass} -vpath {$CFG->dataroot}/vmoodle/{$vmoodledata->vtemplate}_sql -dbsource {$dbsource} -dbtarget {$vmoodledata->vdbname}";
        $cmd = "powershell $pgm $cmd";
        
        // Prints log messages in the page and in 'cmd.log'.
        if ($LOG = fopen(dirname("{$CFG->dataroot}/vmoodle/{$vmoodledata->vtemplate}_sql").'/cmd.log', 'a')) {
            fwrite($LOG, $cmd."\n");
        }

        // Executes the SQL command.
        exec($cmd, $execoutput, $returnvalue);
        if ($LOG) {
            foreach ($execoutput as $execline) {
                fwrite($LOG, $execline."\n");
            }
            fwrite($LOG, $returnvalue."\n");
            fclose($LOG);
        }
        
    } else {
        // SQL files paths.
        $templatesqlfile_path = $CFG->dataroot.'/vmoodle/'.$vmoodledata->vtemplate.'_sql/vmoodle_master.sql';
        // Create temporaries files for replacing data.
        $temporarysqlfile_path = $CFG->dataroot.'/vmoodle/'.$vmoodledata->vtemplate.'_sql/vmoodle_master.temp.sql';

        // Retrieves files contents into strings.
        // debug_trace("load_database_from_dump : getting sql content");
        if (!($dumptxt = file_get_contents($templatesqlfile_path))) {
            print_error('nosql', 'local_vmoodle');
            return false;
        }

        // Change the tables prefix if required prefix does not match manifest's one (sql template).
        if ($manifest['templatevdbprefix'] != $vmoodledata->vdbprefix) {
            $dumptxt = str_replace($manifest['templatevdbprefix'], $vmoodledata->vdbprefix, $dumptxt);
        }

        // Fix special case on adodb_logsql table if prefix has a schema part (PostgreSQL).
        if (preg_match('/(.*)\./', $vmoodledata->vdbprefix, $matches)) {
            // We have schema, thus relocate adodb_logsql table within schema.
            $dumptxt = str_replace('adodb_logsql', $matches[1].'.adodb_logsql', $dumptxt);
        }

        // Puts strings into the temporary files.
        // debug_trace("load_database_from_dump : writing modified sql");
        if (!file_put_contents($temporarysqlfile_path, $dumptxt)) {
            print_error('nooutputfortransformedsql', 'local_vmoodle');
            return false;
        }
        // Creates the new database before importing the data.
        $DB->create_database($vmoodledata->vdbhost, $vmoodledata->vdblogin, $vmoodledata->vdbpass, $vmoodledata->vdbname);
        
        $sqlcmd = vmoodle_get_database_dump_cmd($vmoodledata);

        // Make final commands to execute, depending on the database type.
        $import = $sqlcmd.$temporarysqlfile_path;

        // Execute the command.
        // debug_trace("load_database_from_dump : executing feeding sql");

        if (!defined('CLI_SCRIPT')) {
            putenv('LANG=en_US.utf-8'); 
        }

        // Ensure utf8 is correctly handled by php exec().
        // @see http://stackoverflow.com/questions/10028925/call-a-program-via-shell-exec-with-utf-8-text-input

        exec($import, $output, $return);

        // debug_trace(implode("\n", $output)."\n");

        // Remove temporary files.
        //    if(!unlink($temporarysqlfile_path))){
        //        return false;
        //    }

        // End.
        // debug_trace("load_database_from_dump : OUT");
    }
    return true;
}

/**
 * Loads a complete database dump from a template, and does some update.
 * @uses $CFG
 * @param object $vmoodledata All the Host_form data.
 * @param object $this_as_host The mnet_host record that represents the master.
 * @return bool If true, fixing database from template was sucessful, otherwise false.
 */
function vmoodle_fix_database($vmoodledata, $this_as_host) {
    global $CFG, $SITE;

    // debug_trace('fixing_database ; IN');
    $manifest = vmoodle_get_vmanifest($vmoodledata->vtemplate);
    $hostname = mnet_get_hostname_from_uri($CFG->wwwroot);
    $cfgipaddress = gethostbyname($hostname);

    // SQL files paths.
    $temporarysetup_path = $CFG->dataroot.'/vmoodle/'.$vmoodledata->vtemplate.'_sql/vmoodle_setup_template.temp.sql';

    // debug_trace('fixing_database ; opening setup script file');
    if (!$FILE = fopen($temporarysetup_path, 'wb')) {
        print_error('couldnotwritethesetupscript', 'local_vmoodle');
        return false;
    }
    $PREFIX = $vmoodledata->vdbprefix;
    $vmoodledata->description = str_replace("'", "''", $vmoodledata->description);
    $vmoodledata->name = str_replace("'", "''", $vmoodledata->name);
    
    $requestsList = array();
    
    // Setup moodle name and description.
    $requestsList[0] = "UPDATE {$PREFIX}course SET fullname='{$vmoodledata->name}', shortname='{$vmoodledata->shortname}', summary='{$vmoodledata->description}' WHERE category = 0 AND id = 1";
    fwrite($FILE, $requestsList[0].";\n");

    // Setup a suitable cookie name.
    $cookiename = clean_param($vmoodledata->shortname, PARAM_ALPHANUM);
    $requestsList[1] = "UPDATE {$PREFIX}config SET value='{$cookiename}' WHERE name = 'sessioncookie'";
    fwrite($FILE, $requestsList[1].";\n\n");

    // Delete all logs.
    $requestsList[2] = "DELETE FROM {$PREFIX}log";
    fwrite($FILE, $requestsList[2].";\n\n");
    $requestsList[3] = "DELETE FROM {$PREFIX}mnet_log";
    fwrite($FILE, $requestsList[3].";\n\n");
    $requestsList[4] = "DELETE FROM {$PREFIX}mnet_session";
    fwrite($FILE, $requestsList[4].";\n\n"); // purge mnet logs and sessions

    /*
     * we need :
     * clean host to service
     * clean mnet_hosts unless All Hosts and self record
     * rebind self record to new wwwroot, ip and cleaning public key
     */
    fwrite($FILE, "--\n-- Cleans all mnet tables but keeping service configuration in place \n--\n");

    // We first remove all services. Services will be next rebuild based on template or minimal strategy.
    // We expect all service declaraton are ok in the template DB as the template comes from homothetic installation.
    $requestsList[5] = "DELETE FROM {$PREFIX}mnet_host2service";
    fwrite($FILE, $requestsList[5].";\n\n");

    // We first remove all services. Services will be next rebuild based on template or minimal strategy.
    $requestsList[6] = "DELETE FROM {$PREFIX}mnet_host WHERE wwwroot != '' AND wwwroot != '{$manifest['templatewwwroot']}'";
    fwrite($FILE, $requestsList[6].";\n\n");
    $vmoodlenodename = str_replace("'", "''", $vmoodledata->name);
    // Into mnet_host table id which refer to self must be "1" (cannot be forced into sqlserver but shall already be fixed to "1" into the snapshoted node)
    $requestsList[7] = "UPDATE {$PREFIX}mnet_host SET wwwroot = '{$vmoodledata->vhostname}', name = '{$vmoodlenodename}' , public_key = '', public_key_expires = 0, ip_address = '{$cfgipaddress}'  WHERE wwwroot = '{$manifest['templatewwwroot']}'";
    fwrite($FILE, $requestsList[7].";\n\n");
    $requestsList[8] = "UPDATE {$PREFIX}config SET value = 1 WHERE name = 'mnet_localhost_id'";
    fwrite($FILE, $requestsList[8].";\n\n"); // ensure consistance
    $requestsList[9] = "UPDATE {$PREFIX}user SET deleted = 1 WHERE auth = 'mnet' AND username != 'admin'";
    fwrite($FILE, $requestsList[9].";\n\n"); // disable all mnet users

    /* 
     * this is necessary when using a template from another location or deployment target as
     * the salt may have changed. We would like that all primary admins be the same techn admin.
     */
    $localadmin = get_admin();
    fputs($FILE, "--\n-- Force physical admin with same credentials than in master.  \n--\n");
    $requestsList[10] = "UPDATE {$PREFIX}user SET password = '{$localadmin->password}' WHERE auth = 'manual' AND username = 'admin'";
    fwrite($FILE, $requestsList[10].";\n\n");

    if ($vmoodledata->mnet == -1){ // NO MNET AT ALL.
        /*
         * we need :
         * disable mnet
         */
        $requestsList[11] = "UPDATE {$PREFIX}config SET value = 'off' WHERE name = 'mnet_dispatcher_mode'";
        fputs($FILE, $requestsList[11].";\n\n");
    } else { // ALL OTHER CASES.
        /*
         * we need : 
         * enable mnet
         * push our master identity in mnet_host table
         */
        $requestsList[12] = "UPDATE {$PREFIX}config SET value = 'strict' WHERE name = 'mnet_dispatcher_mode'";
        fputs($FILE, $requestsList[12].";\n\n");
        $requestsList[13] = "INSERT INTO {$PREFIX}mnet_host (wwwroot, ip_address, name, public_key, applicationid, public_key_expires) VALUES ('{$this_as_host->wwwroot}', '{$this_as_host->ip_address}', '{$SITE->fullname}', '{$this_as_host->public_key}', {$this_as_host->applicationid}, '{$this_as_host->public_key_expires}')";
        fputs($FILE, $requestsList[13].";\n\n");

        fputs($FILE, "--\n-- Enable the service 'mnetadmin, sso_sp and sso_ip' with host which creates this host.  \n--\n");
        $requestsList[14] = "INSERT INTO {$PREFIX}mnet_host2service (hostid,serviceid,publish,subscribe) VALUES ((SELECT id FROM {$PREFIX}mnet_host WHERE wwwroot LIKE '{$this_as_host->wwwroot}'), (SELECT id FROM {$PREFIX}mnet_service WHERE name LIKE 'mnetadmin'), 1, 0)";
        fputs($FILE, $requestsList[14].";\n\n");
        $requestsList[15] = "INSERT INTO {$PREFIX}mnet_host2service (hostid,serviceid,publish,subscribe) VALUES ((SELECT id FROM {$PREFIX}mnet_host WHERE wwwroot LIKE '{$this_as_host->wwwroot}'), (SELECT id FROM {$PREFIX}mnet_service WHERE name LIKE 'sso_sp'), 1, 0)";
        fputs($FILE, $requestsList[15].";\n\n");
        $requestsList[16] = "INSERT INTO {$PREFIX}mnet_host2service (hostid,serviceid,publish,subscribe) VALUES ((SELECT id FROM {$PREFIX}mnet_host WHERE wwwroot LIKE '{$this_as_host->wwwroot}'), (SELECT id FROM {$PREFIX}mnet_service WHERE name LIKE 'sso_idp'), 0, 1)";
        fputs($FILE, $requestsList[16].";\n\n");

        fputs($FILE, "--\n-- Insert master host user admin.  \n--\n");
        $requestsList[17] = "INSERT INTO {$PREFIX}user (auth, confirmed, policyagreed, deleted, mnethostid, username, password) VALUES ('mnet', 1, 0, 0, (SELECT id FROM {$PREFIX}mnet_host WHERE wwwroot LIKE '{$this_as_host->wwwroot}'), 'admin', '')";
        fputs($FILE, $requestsList[17].";\n\n");

        fputs($FILE, "--\n-- Links role and capabilites for master host admin.  \n--\n");
        $roleid = "(SELECT id FROM {$PREFIX}role WHERE shortname LIKE 'manager')";
        $contextid = 1;
        $userid = "(SELECT id FROM {$PREFIX}user WHERE auth LIKE 'mnet' AND username = 'admin' AND mnethostid = (SELECT id FROM {$PREFIX}mnet_host WHERE wwwroot LIKE '{$this_as_host->wwwroot}'))";
        $timemodified = time();
        $modifierid = $userid;
        $component = "''";
        $itemid = 0;
        $sortorder = 1;
        $requestsList[18] = "INSERT INTO {$PREFIX}role_assignments(roleid,contextid,userid,timemodified,modifierid,component,itemid,sortorder) VALUES ($roleid, $contextid, $userid, $timemodified, $modifierid, $component, $itemid, $sortorder)";
        fputs($FILE, $requestsList[18].";\n\n");

        fputs($FILE, "--\n-- Add new network admin to local siteadmins.  \n--\n");
        $adminidsql = "(SELECT id FROM {$PREFIX}user WHERE auth LIKE 'mnet' AND username = 'admin' AND mnethostid = (SELECT id FROM {$PREFIX}mnet_host WHERE wwwroot LIKE '{$this_as_host->wwwroot}'))";
        if($vmoodledata->vdbtype == 'sqlsrv') {
            $requestsList[19] = "UPDATE {$PREFIX}config SET value = value+','+CAST($adminidsql AS nvarchar(max)) WHERE name = 'siteadmins'";
        } else {
            $requestsList[19] = "UPDATE {$PREFIX}config SET value = CONCAT(value, ',', $adminidsql) WHERE name = 'siteadmins'";
        }
        fputs($FILE, $requestsList[19].";\n");

        fputs($FILE, "--\n-- Create a disposable key for renewing new host's keys.  \n--\n");
        $requestsList[20] = "DELETE FROM {$PREFIX}config WHERE name = 'bootstrap_init'";
        $requestsList[21] = "INSERT INTO {$PREFIX}config (name, value) VALUES ('bootstrap_init', '{$this_as_host->wwwroot}')";
        fputs($FILE, $requestsList[20].";\n");
    }
    fclose($FILE);
    // debug_trace('fixing_database ; setup script written');
    
    $vdb = vmoodle_setup_DB($vmoodledata);
    
    $transaction = $vdb->start_delegated_transaction();
    foreach($requestsList as $req) {
        
        $vdb->execute_query($req);
    }
    $transaction->allow_commit();
    // INSERT into config table must be allowed by unique constraint on column "name" - impossible to check in a transaction
    /*if($lastrequest)
        $vdb->execute_query($lastrequest);*/
    return true;
}

function vmoodle_destroy($vmoodledata){
    global $CFG, $DB, $OUTPUT;

    if (!$vmoodledata) {
        return;
    }

    // Checks if paths commands have been properly defined in 'vconfig.php'.
    if($vmoodledata->vdbtype == 'sqlsrv') {
        $sqldropstatement = "IF EXISTS(SELECT name FROM sys.databases WHERE name = '".$vmoodledata->vdbname."') DROP DATABASE ".$vmoodledata->vdbname;
    } elseif($vmoodledata->vdbtype == 'mysql') {
        $sqldropstatement = 'DROP DATABASE IF EXISTS '.$vmoodledata->vdbname; 
    } elseif ($vmoodledata->vdbtype == 'mysqli') {
        $sqldropstatement = 'DROP DATABASE IF EXISTS '.$vmoodledata->vdbname;
    } elseif ($vmoodledata->vdbtype == 'postgres') {
        $sqldropstatement = 'DROP SCHEMA '.$vmoodledata->vdbname; 
    } else {
        throw new moodle_exception('vmoodle_destroy', 'local_vmoodle', '', null, '[local/vmoodle/lib.php] vdbtype not implemented');
    }
    debug_trace("destroy_database : executing drop sql");

    try {
        $DB->execute($sqldropstatement);
    }
    catch (Exception $e) {
        echo $OUTPUT->notification('noexecutionfor', 'local_vmoodle', $sql);
    }

    // Destroy moodledata.
    if($CFG->ostype == 'WINDOWS') {
        $cmd = 'rd "'.$vmoodledata->vdatapath.'" /S /Q';
    }
    else {
        $cmd = " rm -rf \"$vmoodledata->vdatapath\" ";
    }
    exec($cmd);

    // Delete vmoodle instance.

    $DB->delete_records('local_vmoodle', array('vhostname' => $vmoodledata->vhostname));

    // Delete all related mnet_hosts info.

    $mnet_host = $DB->get_record('mnet_host', array('wwwroot' => $vmoodledata->vhostname));
    if($mnet_host) {
        $DB->delete_records('mnet_host', array('wwwroot' => $mnet_host->wwwroot));
        $DB->delete_records('mnet_host2service', array('hostid' => $mnet_host->id));
        $DB->delete_records('mnetservice_enrol_courses', array('hostid' => $mnet_host->id));
        $DB->delete_records('mnetservice_enrol_enrolments', array('hostid' => $mnet_host->id));
        $DB->delete_records('mnet_log', array('hostid' => $mnet_host->id));
        $DB->delete_records('mnet_session', array('mnethostid' => $mnet_host->id));
        $DB->delete_records('mnet_sso_access_control', array('mnet_host_id' => $mnet_host->id));
    }
}

/**
 * get the service strategy and peer mirror strategy to apply to new host, depending on 
 * settings. If no settings were made, use a simple peer to peer SSO binding so that users
 * can just roam.
 * @param object $vmoodledata the new host definition
 * @param array reference $services the service scheme to apply to new host
 * @param array reference $peerservices the service scheme to apply to new host peers
 */
function vmoodle_get_service_strategy($vmoodledata, &$services, &$peerservices, $domain = 'peer'){
    global $DB;

    // We will mix in order to an single array of configurated service here.
    $servicesstrategy = unserialize(get_config(null, 'local_vmoodle_services_strategy'));
    $servicerecs = $DB->get_records('mnet_service', array());
    $servicesstrategy = (array)$servicesstrategy;

    if (!empty($servicerecs)) { // Should never happen; standard services are always there.
        if ($vmoodledata->services == 'subnetwork'  && !empty($servicesstrategy)) {
            foreach ($servicerecs as $key => $service) {
                $services[$service->name] = new StdClass();
                $peerservices[$service->name] = new StdClass();
                $services[$service->name]->publish       = 0 + @$servicesstrategy[$domain.'_'.$service->name.'_publish'];
                $peerservices[$service->name]->subscribe = 0 + @$servicesstrategy[$domain.'_'.$service->name.'_publish'];
                $services[$service->name]->subscribe     = 0 + @$servicesstrategy[$domain.'_'.$service->name.'_subscribe'];
                $peerservices[$service->name]->publish   = 0 + @$servicesstrategy[$domain.'_'.$service->name.'_subscribe'];
            }
        } else { // If no strategy has been recorded, use default SSO binding.
            foreach ($servicerecs as $key => $service) {
                $services[$service->name] = new StdClass();
                $peerservices[$service->name] = new StdClass();
                $services[$service->name]->publish = 0;
                $peerservices[$service->name]->subscribe = 0;
                $services[$service->name]->subscribe = 0;
                $peerservices[$service->name]->publish = 0;
            }
            $services['sso_sp']->publish = 1;
            $services['sso_sp']->subscribe = 1;
            $services['sso_idp']->publish = 1;
            $services['sso_idp']->subscribe = 1;
            $peerservices['sso_sp']->publish = 1;
            $peerservices['sso_sp']->subscribe = 1;
            $peerservices['sso_idp']->publish = 1;
            $peerservices['sso_idp']->subscribe = 1;
        }
    }

    // With main force mnet admin whatever is said in defaults.
    if ($domain == 'main') {
        $services['sso_sp']->publish = 1;
        $services['sso_idp']->subscribe = 1;
        $services['mnetadmin']->publish = 1;

        // Peer is main.
        $peerservices['sso_sp']->subscribe = 1;
        $peerservices['sso_idp']->publish = 1;
        $peerservices['mnetadmin']->subscribe = 1;
    }
}

/**
* get a proper SQLdump command
* @param object $vmoodledata the complete new host information
* @return string the shell command 
*/
function vmoodle_get_database_dump_cmd($vmoodledata) {
    global $CFG;

    // Checks if paths commands have been properly defined in 'vconfig.php'.
    if ($vmoodledata->vdbtype == 'mysql') {
        $pgm = (!empty($CFG->local_vmoodle_cmd_mysql)) ? stripslashes($CFG->local_vmoodle_cmd_mysql) : false;
    } elseif ($vmoodledata->vdbtype == 'mysqli') {
        $pgm = (!empty($CFG->local_vmoodle_cmd_mysql)) ? stripslashes($CFG->local_vmoodle_cmd_mysql) : false;
    } elseif ($vmoodledata->vdbtype == 'postgres') {
        // Needs to point the pg_restore command.
        $pgm = (!empty($CFG->local_vmoodle_cmd_pgsql)) ? stripslashes($CFG->local_vmoodle_cmd_pgsql) : false;
    } else {
        throw new moodle_exception('vmoodle_destroy', 'local_vmoodle', '', null, '[local/vmoodle/lib.php] vdbtype not implemented');
    }

    // Checks the needed program.
    // debug_trace("load_database_from_dump : checking database command");
    if (!$pgm){
        print_error('dbcommandnotconfigured', 'local_vmoodle');
        return false;
    }

    $phppgm = str_replace("\\", '/', $pgm);
    $phppgm = str_replace("\"", '', $phppgm);
    $pgm = str_replace("/", DIRECTORY_SEPARATOR, $pgm);

    // debug_trace('load_database_from_dump : checking command is available');
    if (!is_executable($phppgm)) {
        print_error('dbcommanddoesnotmatchanexecutablefile', 'local_vmoodle', $phppgm);
        return false;
    }

    // Take the host given (SQL host specified can be different)
    if (strstr($vmoodledata->vdbhost, ':') !== false) {
        list($vmoodledata->vdbhost, $vmoodledata->vdbport) = split(':', $vmoodledata->vdbhost);
    }

    // Password.
    if (!empty($vmoodledata->vdbpass)) {
        $vmoodledata->vdbpass = '-p'.escapeshellarg($vmoodledata->vdbpass).' ';
    }

    // Making the command line (see 'vconfig.php' file for defining the right paths).
    if ($vmoodledata->vdbtype == 'mysql') {
        $sqlcmd    = $pgm.' -h'.$vmoodledata->vdbhost.(isset($vmoodledata->vdbport) ? ' -P'.$vmoodledata->vdbport.' ' : ' ' );
        $sqlcmd .= '-u'.$vmoodledata->vdblogin.' '.$vmoodledata->vdbpass;
        $sqlcmd .= $vmoodledata->vdbname.' < ';
    } elseif ($vmoodledata->vdbtype == 'mysqli') {
        $sqlcmd    = $pgm.' -h'.$vmoodledata->vdbhost.(isset($vmoodledata->vdbport) ? ' -P'.$vmoodledata->vdbport.' ' : ' ' );
        $sqlcmd .= '-u'.$vmoodledata->vdblogin.' '.$vmoodledata->vdbpass;
        $sqlcmd .= $vmoodledata->vdbname.' < ';
    } elseif ($vmoodledata->vdbtype == 'postgres') {
        $sqlcmd    = $pgm.' -Fc -h '.$vmoodledata->vdbhost.(isset($vmoodledata->vdbport) ? ' -p '.$vmoodledata->vdbport.' ' : ' ' );
        $sqlcmd .= '-U '.$vmoodledata->vdblogin.' ';
        $sqlcmd .= '-d '.$vmoodledata->vdbname.' -f ';
    } else {
        throw new moodle_exception('vmoodle_get_database_dump_cmd', 'local_vmoodle', '', null, '[local/vmoodle/lib.php] vdbtype not implemented');
    }
    return $sqlcmd;
}

/**
 * Dump existing files of a template.
 * @uses $CFG
 * @param string $templatename The template's name.
 * @param string $destpath The destination path.
 */
function vmoodle_dump_files_from_template($templatename, $destpath) {
    global $CFG;

    // Copies files and protects against copy recursion.
    $templatefilespath = $CFG->dataroot.'/vmoodle/'.$templatename.'_vmoodledata';
    $destpath = str_replace('\\\\', '\\', $destpath);
    if (!is_dir($destpath)) {
        mkdir($destpath);
    }
    filesystem_copy_tree($templatefilespath, $destpath, '');
}

/**
 * @param object $submitteddata
 *
 */
function vmoodle_bind_to_network($submitteddata, &$newmnet_host){
    global $USER, $CFG, $DB, $OUTPUT;

    // debug_trace("step 4.4 : binding to subnetwork");

    // Getting services schemes to apply
    // debug_trace("step 4.4.1 : getting services");
    vmoodle_get_service_strategy($submitteddata, $services, $peerservices, 'peer');

    // debug_trace("step 4.4.2 : getting possible peers");
    $idnewblock = $DB->get_field('local_vmoodle', 'id', array('vhostname' => $submitteddata->vhostname));

    // last mnet has been raised by one at step 3 so we add to network if less
    if ($submitteddata->mnet < vmoodle_get_last_subnetwork_number()) {
        // Retrieves the subnetwork member(s).
        $subnetwork_hosts = array();
        $select = 'id != ? AND mnet = ? AND enabled = 1';
        $subnetwork_members = $DB->get_records_select('local_vmoodle', $select, array($idnewblock, $submitteddata->mnet));
    
        if (!empty($subnetwork_members)) {
            // debug_trace("step 4.4.3 : preparing peers");
            foreach ($subnetwork_members as $subnetwork_member) {
                $temp_host = new stdClass();
                $temp_host->wwwroot = $subnetwork_member->vhostname;
                $temp_host->name = utf8_decode($subnetwork_member->name);
                $subnetwork_hosts[] = $temp_host;
            }
        }
    
        // Member(s) of the subnetwork add the new host.
        if (!empty($subnetwork_hosts)) {
            // debug_trace("step 4.4.4 : bind peers");
            $rpc_client = new \local_vmoodle\XmlRpc_Client();
            $rpc_client->reset_method();
            $rpc_client->set_method('local/vmoodle/rpclib.php/mnetadmin_rpc_bind_peer');
            // Authentication params.
            $rpc_client->add_param($USER->username, 'string');
            $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));
            $rpc_client->add_param($userhostroot, 'string');
            $rpc_client->add_param($CFG->wwwroot, 'string');
            // Peer to bind to.
            $rpc_client->add_param((array)$newmnet_host, 'array');
            $rpc_client->add_param($peerservices, 'array');

            foreach ($subnetwork_hosts as $subnetwork_host) {
                // debug_trace("step 4.4.4.1 : bind to -> $subnetwork_host->wwwroot");
                $temp_member = new \local_vmoodle\Mnet_Peer();
                $temp_member->set_wwwroot($subnetwork_host->wwwroot);
                if (!$rpc_client->send($temp_member)) {
                    echo $OUTPUT->notification(implode('<br />', $rpc_client->getErrors($temp_member)));
                    if (debugging()) {
                        echo '<pre>';
                        // var_dump($rpc_client);
                        echo '</pre>';
                    }
                }

                // debug_trace("step 4.4.4.1 : bind from <- $subnetwork_host->wwwroot");
                $rpc_client_2 = new \local_vmoodle\XmlRpc_Client();
                $rpc_client_2->reset_method();
                $rpc_client_2->set_method('local/vmoodle/rpclib.php/mnetadmin_rpc_bind_peer');
                // Authentication params.
                $rpc_client_2->add_param($USER->username, 'string');
                $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));
                $rpc_client_2->add_param($userhostroot, 'string');
                $rpc_client_2->add_param($CFG->wwwroot, 'string');
                // Peer to bind to.
                $rpc_client_2->add_param((array)$temp_member, 'array');
                $rpc_client_2->add_param($services, 'array');

                if (!$rpc_client_2->send($newmnet_host)) {
                    echo $OUTPUT->notification(implode('<br />', $rpc_client_2->getErrors($newmnet_host)));
                    if (debugging()) {
                        echo '<pre>';
                        // var_dump($rpc_client_2);
                        echo '</pre>';
                    }
                }
                unset($rpc_client_2); // free some resource
            }
        }
    }

    // Getting services schemes to apply to main
    // debug_trace("step 4.4.5 : getting services");
    vmoodle_get_service_strategy($submitteddata, $services, $peerservices, 'main');

    // debug_trace("step 4.4.5.1 : bind to -> $CFG->wwwroot");
    $mainhost = new \local_vmoodle\Mnet_Peer(); // this is us
    $mainhost->set_wwwroot($CFG->wwwroot);
    
    // debug_trace('step 4.4.5.2 : Binding our main service strategy to remote');
    // bind the local service strategy to new host
    if (!empty($peerservices)) {
        $DB->delete_records('mnet_host2service', array('hostid' => $newmnet_host->id)); // eventually deletes something on the way
        foreach ($peerservices as $servicename => $servicestate) {
            $service = $DB->get_record('mnet_service', array('name' => $servicename));
            $host2service = new stdclass();
            $host2service->hostid = $newmnet_host->id;
            $host2service->serviceid = $service->id;
            $host2service->publish = 0 + @$servicestate->publish;
            $host2service->subscribe = 0 + @$servicestate->subscribe;
            $DB->insert_record('mnet_host2service', $host2service);
            // debug_trace("step 4.4.5.2 : adding ".serialize($host2service));
        }
    }

    // debug_trace('step 4.4.5.4 : Binding remote service strategy to main');
    $rpc_client = new \local_vmoodle\XmlRpc_Client();
    $rpc_client->reset_method();
    $rpc_client->set_method('local/vmoodle/rpclib.php/mnetadmin_rpc_bind_peer');
    $rpc_client->add_param($USER->username, 'string');
    $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));
    $rpc_client->add_param($userhostroot, 'string');
    $rpc_client->add_param($CFG->wwwroot, 'string');
    // Peer to bind to : this is us.
    $rpc_client->add_param((array)$mainhost, 'array');
    $rpc_client->add_param($services, 'array');

    // debug_trace('step 4.4.5.4 : Sending');
    if (!$rpc_client->send($newmnet_host)) {
        // echo $OUTPUT->notification(implode('<br />', $rpc_client->getErrors($newmnet_host)));
        if (debugging()) {
            echo '<pre>';
            // var_dump($rpc_client);
            echo '</pre>';
        }
    }
}

/**
 * Checks existence and consistency of a full template.
 * @uses $CFG
 * @param string $templatename The template's name.
 * @return bool Returns TRUE if the full template is consistency, FALSE otherwise.
 */
function vmoodle_exist_template($templatename) {
    global $CFG;

    // Needed paths for checking.
    $templatedir_files = $CFG->dataroot.'/vmoodle/'.$templatename.'_vmoodledata';
    $templatedir_sql = $CFG->dataroot.'/vmoodle/'.$templatename.'_sql';

    return (in_array($templatename, vmoodle_get_available_templates())
        && is_readable($templatedir_files)
            && is_readable($templatedir_sql));
}

/**
 * Read manifest values in vmoodle template.
 */

/**
 * Gets value in manifest file (in SQL folder of a template).
 * @uses $CFG
 * @param string $templatename The template's name.
 * @return array The manifest values.
 */
function vmoodle_get_vmanifest($templatename){
    global $CFG;

    // Reads php values.
    include($CFG->dataroot.'/vmoodle/'.$templatename.'_sql/manifest.php');
    $manifest = array();
    $manifest['templatewwwroot'] = $templatewwwroot;
    $manifest['templatevdbprefix'] = $templatevdbprefix;

    return $manifest;
}

/**
 * Searches and returns the last created subnetwork number.
 * @return integer The last created subnetwork number.
 */
function vmoodle_get_last_subnetwork_number() {
    global $DB;

    $nbmaxsubnetwork = $DB->get_field('local_vmoodle', 'MAX(mnet)', array());
    return $nbmaxsubnetwork;
}

// **************************************************************************************
// *                                    TO CHECK                                        *
// **************************************************************************************


/**
 * Be careful : this library might be include BEFORE any configuration
 * or other usual Moodle libs are loaded. It cannot rely on
 * most of the Moodle API functions.
 */

/**
 * Prints an administrative status (broken, enabled, disabled) for a Vmoodle.
 *
 * @uses $CFG The global configuration.
 * @param object $vmoodle The Vmoodle object.
 * @param boolean $return If false, prints the Vmoodle state, else not.
 * @return string The Vmoodle state.
 */
function vmoodle_print_status($vmoodle, $return = false) {
    global $CFG, $OUTPUT;

    if (!vmoodle_check_installed($vmoodle)) {
        $vmoodlestate = '<img src="'.$OUTPUT->pix_url('broken', 'local_vmoodle').'"/>';
    } elseif($vmoodle->enabled) {
        $disableurl = new moodle_url('/local/vmoodle/view.php', array('view' => 'management', 'what' => 'disable', 'id' => $vmoodle->id));
        $vmoodlestate = '<a href="'.$disableurl.'" title="'.get_string('disable').'"><img src="'.$OUTPUT->pix_url('enabled', 'local_vmoodle').'" /></a>';
    } else {
        $enableurl = new moodle_url('/local/vmoodle/view.php', array('view' => 'management', 'what' => 'enable', 'id' => $vmoodle->id));
        $vmoodlestate = '<a href="'.$enableurl.'" title="'.get_string('enable').'"><img src="'.$OUTPUT->pix_url('disabled', 'local_vmoodle').'" />';
    }

    // Prints the Vmoodle state.
    if (!$return) {
        echo $vmoodlestate;
    }

    return $vmoodlestate;
}

/**
 * Checks physical availability of the Vmoodle.
 * @param object $vmoodle The Vmoodle object.
 * @return boolean If true, the Vmoodle is physically available.
 */
function vmoodle_check_installed($vmoodle) {
    return (filesystem_is_dir($vmoodle->vdatapath, ''));
}

/**
 * Adds an CSS marker error in case of matching error.
 * @param array $errors The current error set.
 * @param string $errorkey The error key.
 */
if (!function_exists('print_error_class')) {
    function print_error_class($errors, $errorkeylist) {
        if ($errors) {
            foreach($errors as $anError) {
                if ($anError->on == '') {
                    continue;
                }
                if (preg_match("/\\b{$anError->on}\\b/" ,$errorkeylist)) {
                    echo " class=\"formerror\" ";
                    return;
                }
            }
        }
    }
}

function vmoodle_get_string($identifier, $subplugin, $a = '', $lang = ''){
    global $CFG;

    static $string = array();

    if (empty($lang)) {
        $lang = current_language();
    }

    list($type, $plug) = explode('_', $subplugin);
    
    include($CFG->dirroot.'/local/vmoodle/db/subplugins.php');
    
    if (!isset($plugstring[$plug])) {
        if (file_exists($CFG->dirroot.'/'.$subplugins[$type].'/'.$plug.'/lang/en/'.$subplugin.'.php')) {
            include($CFG->dirroot.'/'.$subplugins[$type].'/'.$plug.'/lang/en/'.$subplugin.'.php');
        } else {
            debugging("English lang file must exist", DEBUG_DEVELOPER);
        }

        // Override with lang file if exists.
        if (file_exists($CFG->dirroot.'/'.$subplugins[$type].'/'.$plug.'/lang/'.$lang.'/'.$subplugin.'.php')) {
            include($CFG->dirroot.'/'.$subplugins[$type].'/'.$plug.'/lang/'.$lang.'/'.$subplugin.'.php');
        } else {
            $string = array();
        }
        $plugstring[$plug] = $string;
    }

    if (array_key_exists($identifier, $plugstring[$plug])) {
        $result = $plugstring[$plug][$identifier];
        if ($a !== NULL) {
            if (is_object($a) || is_array($a)) {
                $a = (array)$a;
                $search = array();
                $replace = array();
                foreach ($a as $key => $value) {
                    if (is_int($key)) {
                        // We do not support numeric keys - sorry!
                        continue;
                    }
                    $search[]  = '{$a->'.$key.'}';
                    $replace[] = (string)$value;
                }
                if ($search) {
                    $result = str_replace($search, $replace, $result);
                }
            } else {
                $result = str_replace('{$a}', (string)$a, $result);
            }
        }
        // Debugging feature lets you display string identifier and component.
        if (!empty($CFG->debugstringids) && optional_param('strings', 0, PARAM_INT)) {
            $result .= ' {' . $identifier . '/' . $subplugin . '}';
        }
        return $result;
    }

    if (!empty($CFG->debugstringids) && optional_param('strings', 0, PARAM_INT)) {
        return "[[$identifier/$subplugin]]";
    } else {
        return "[[$identifier]]";
    }
}

/**
 * Get database name for the given template name
 *
 * @global stdClass $DB The global moodle_database instance.
 * @global stdClass $CFG The global configuration instance.
 * @param string $templatename The name of the template.
 * @return string|bool Returns the database name or false if not found.
 */
function vmoodle_get_dbname_for_templatename($templatename) {
    global $DB, $CFG;
    
    // First check if snapshoted used node is not the main host
    $hostname = preg_replace('/https?:\/\//', '', $CFG->wwwroot);
    $hostname = str_replace(':', '_', $hostname);
    $hostname = str_replace('.', '_', $hostname);
    $hostname = str_replace('-', '_', $hostname);
    $hostname = str_replace('/', '_', $hostname);
    $hostname = str_replace('\\', '_', $hostname);
    if($hostname == $templatename)
        return $CFG->dbname;
    
    // If not check recorded vmoodle nodes
    $vmoodles = $DB->get_records_select('local_vmoodle', null, null, null, 'vhostname, vdbname');
    foreach($vmoodles as $vmoodle) {
        $hostname = preg_replace('/https?:\/\//', '', $vmoodle->vhostname);
        $hostname = str_replace(':', '_', $hostname);
        $hostname = str_replace('.', '_', $hostname);
        $hostname = str_replace('-', '_', $hostname);
        $hostname = str_replace('/', '_', $hostname);
        $hostname = str_replace('\\', '_', $hostname);
        if($hostname == $templatename)
            return $vmoodle->vdbname;
    }
    
    // If every nodes does not match then return false
    return false;
}


/**
 * Sets up global $DB moodle_database instance
 *
 * @global stdClass $CFG The global configuration instance.
 * @see config.php
 * @see config-dist.php
 * @global stdClass $DB The global moodle_database instance.
 * @return resource Returns true when finished setting up $DB. Returns void when $DB has already been set.
 */
function vmoodle_setup_DB($vmoodle) {
    global $CFG;

    if (!isset($vmoodle->vdblogin)) {
        $vmoodle->vdblogin = '';
    }

    if (!isset($vmoodle->vdbpass)) {
        $vmoodle->vdbpass = '';
    }

    if (!isset($vmoodle->vdbname)) {
        $vmoodle->vdbname = '';
    }

    if (!isset($vmoodle->dblibrary)) {
        $vmoodle->dblibrary = 'itop';
        // Use new drivers instead of the old adodb driver names.
        switch ($vmoodle->vdbtype) {
            case 'postgres7' :
                $vmoodle->vdbtype = 'pgsql';
                break;

            case 'mssql_n':
                $vmoodle->vdbtype = 'mssql';
                break;

            case 'oci8po':
                $vmoodle->vdbtype = 'oci';
                break;

            case 'mysql' :
                $vmoodle->vdbtype = 'mysqli';
                break;
        }
    }

    if (!isset($vmoodle->dboptions)) {
        $vmoodle->dboptions = array (
            'dbpersist' => 0,
            'dbport' => $CFG->dboptions['dbport'],
            'dbsocket' => '',
        );
    }

    if (isset($vmoodle->vdbpersist)) {
        $vmoodle->dboptions['dbpersist'] = $vmoodle->vdbpersist;
    }

    if (!$vdb = moodle_database::get_driver_instance($vmoodle->vdbtype, $vmoodle->dblibrary)) {
        throw new dml_exception('dbdriverproblem', "Unknown driver $vmoodle->dblibrary/$vmoodle->dbtype");
    }

    $vdb->connect($vmoodle->vdbhost, $vmoodle->vdblogin, $vmoodle->vdbpass, $vmoodle->vdbname, $vmoodle->vdbprefix, $vmoodle->dboptions);

    $vmoodle->vdbfamily = $vdb->get_dbfamily(); // TODO: BC only for now

    return $vdb;
}

/**
 * Sets up global $CFG->local_vmoodle_* from config_plugins SQL table (similar as moodle plugin manager do for a block plugin)
 *
 * @global stdClass $CFG The global configuration instance.
 * @see config.php
 * @global stdClass $DB The global moodle_database instance.
 */
function vmoodle_setup_local_config() {
    global $CFG, $DB;

    // Nothing to do if already done
    if(isset($CFG->local_vmoodle_version)) 
        return;
    
    $dblocalconfig = $DB->get_records('config_plugins', array('plugin' => 'local_vmoodle'));
    $prefix = 'local_vmoodle_';
    foreach($dblocalconfig as $record) {
        $cfgname = $prefix.$record->name;
        if(!isset($CFG->$cfgname))
            $CFG->$cfgname = $record->value;
    }
}

function local_vmoodle_cron() {
    global $CFG, $DB;
    
    set_config('local_vmoodle_cron_running', 1);
    
    if($CFG->ostype != 'WINDOWS') {
        mtrace('virtualized moodledata size survey only implemented on Windows yet, exiting.');
        set_config('local_vmoodle_cron_running', null);
        return;
    }

    if($CFG->mainwwwroot != $CFG->wwwroot) {
        mtrace('current host is not the master host - exiting');
        set_config('local_vmoodle_cron_running', null);
        return;
    } 
    mtrace('current host is the master host - checking last execution');
    
    $now = time();
    $lastexec = get_config('local_vmoodle', 'last_croned_date');
    mtrace("last execution : $lastexec / current execution : $now");
    
    // waiting 24 hours (86400s)
    if($now - $lastexec < 86400) {
        mtrace('waiting 24 hours to update data, exiting...');
        set_config('local_vmoodle_cron_running', null);
        return;
    }
    set_config('last_croned_date', $now, 'local_vmoodle');

    $obj = new COM('scripting.filesystemobject');
    if (!is_object($obj)) {
        mtrace('can not create COM object scripting.filesystemobject');
        set_config('local_vmoodle_cron_running', null);
        return;
    }

    $vhosts = $DB->get_records('local_vmoodle');
    if(isset($vhosts) && count($vhosts) > 0) {
        mtrace(count($vhosts).' virtual hosts found, updating moodledata size record for each node');
    }
    $projectsize = 0;
    $mailalertcontent = array();
    foreach($vhosts as $vhost) {
        $ref = $obj->getfolder($vhost->vdatapath);
        if(isset($CFG->nodequotaalert) && $ref->size > $CFG->nodequotaalert) {
            $mailalertcontent[$vhost->vdatapath] = 'directory: '.$vhost->vdatapath.' => size: '.$ref->size.' bytes exceed node size limit ! ('.$CFG->nodequotaalert.')';
            mtrace($mailalertcontent[$vhost->vdatapath]);
        }
        else {
            mtrace('directory: '.$vhost->vdatapath.' => size: '.$ref->size.' bytes');
        }
        $projectsize += $ref->size;
        $vhost->vdatasize = $ref->size;
        $DB->update_record('local_vmoodle', $vhost);
    }
    if(isset($CFG->projectquotaalert) && $projectsize > $CFG->projectquotaalert) {
        $mailalertcontent['project'] = 'project size: '.$projectsize.' bytes exceed project size limit ! ('.$CFG->projectquotaalert.')';
        mtrace($mailalertcontent['project']);
    }
    else {
        mtrace('project size: '.$projectsize.' bytes');
    }

    if(count($mailalertcontent) > 0 && isset($CFG->emailquotaexceeded)) {
        $CRLF = "\r\n";
        if(mail($CFG->emailquotaexceeded, 'Moodle alert : quota exceeded', "One or more moodledata quota has been exceeded, see details below.$CRLF".implode($CRLF, $mailalertcontent))) 
            mtrace("alert for quota exceeded has been sent to '{$CFG->emailquotaexceeded}'"); 
        else 
            mtrace("error while sending -alert for quota exceeded- to '{$CFG->emailquotaexceeded}' !");
    }

    $obj = null;

    set_config('local_vmoodle_cron_running', null);
    return;
}
