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
 * This file catches an action and do the corresponding usecase.
 * Called by 'view.php'.
 *
 * @usecase add (form)
 * @usecase doadd
 * @usecase edit (form)
 * @usecase doedit
 * @usecase enable
 * @usecase disable
 * @usecase snapshot
 * @usecase delete
 * @usecase fulldelete
 * @usecase renewall
 * @usecase generateconfigs
 *
 * @package local_vmoodle
 * @category local
 * @author Moheissen Fabien (fabien.moheissen@gmail.com)
 * @copyright valeisti (http://www.valeisti.fr)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

Use \local_vmoodle\Mnet_Peer;

// Includes the MNET library.
require_once($CFG->dirroot.'/mnet/lib.php');

// Add needed javascript here (because addonload() is needed before).

$PAGE->requires->js('/local/vmoodle/js/host_form.js');

// It must be included from 'view.php' in local/vmoodle.

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

// Confirmation message.
$message_object = new StdClass();
$message_object->message = '';
$message_object->style = 'notifyproblem';

// Execution time can take more than 30 sec (PHP default value).
$initial_max_execution_time = ini_get('max_execution_time');
if ($initial_max_execution_time > 0) {
    set_time_limit(0);
}

// For a 'block' plugin config values are autoloaded into global config but not for a 'local' plugin
vmoodle_setup_local_config();

/**************************** Make the ADD form ************/
if ($action == 'add') {

    // Test the number of templates.
    $templates = vmoodle_get_available_templates();
    if (!empty($templates)) {

        // Default configuration (automated schema).
        if (@$CFG->local_vmoodle_automatedschema) {
            $platform_form = new StdClass();
            $platform_form->vhostname = (@$CFG->local_vmoodle_vmoodlehost) ? $CFG->local_vmoodle_vmoodlehost : 'localhost' ;
            $platform_form->vdbtype = (@$CFG->local_vmoodle_vdbtype) ? $CFG->local_vmoodle_vdbtype : 'mysqli' ;
            $platform_form->vdbhost = (@$CFG->local_vmoodle_vdbhost) ? $CFG->local_vmoodle_vdbhost : 'localhost' ;
            $platform_form->vdblogin = $CFG->local_vmoodle_vdblogin;
            $platform_form->vdbpass = $CFG->local_vmoodle_vdbpass;
            $platform_form->vdbname = $CFG->local_vmoodle_vdbbasename;
            $platform_form->vdbprefix = (@$CFG->local_vmoodle_vdbprefix) ? $CFG->local_vmoodle_vdbprefix : 'mdl_' ;
            $platform_form->vdbpersist = (@$CFG->local_vmoodle_vdbpersist) ? 1 : 0 ;
            $platform_form->vdatapath = stripslashes($CFG->local_vmoodle_vdatapathbase);

            if ($CFG->local_vmoodle_mnet == 'NEW') {
                $lastsubnetwork = $DB->get_field('local_vmoodle', 'MAX(mnet)', array());
                $platform_form->mnet = $lastsubnetwork + 1;
            } else {
                $platform_form->mnet = 0 + @$CFG->local_vmoodle_mnet;
            }
        
            $platform_form->services = $CFG->local_vmoodle_services;

            // Try to get crontab (Linux).
            if ($CFG->ostype != 'WINDOWS') {
                $crontabcmd = escapeshellcmd('crontab -l');
                $platform_form->crontab = passthru($crontabcmd);
            }

            unset($SESSION->vmoodledata);
            echo $OUTPUT->header();
            $form = new \local_vmoodle\Host_Form('add');
            $form->set_data($platform_form);
            $form->display();
            echo $OUTPUT->footer();
            die;
            // Data are placed in session for displaying.
            // $SESSION->vmoodle_mg['dataform'] = $platform_form;
        }
        // Redirect to the 'add' form.
        // header('Location: view.php?view=management&page=add');
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->box(get_string('notemplates', 'local_vmoodle'));
        echo $OUTPUT->continue_button($CFG->wwwroot.'/local/vmoodle/view.php?view=management');
        echo $OUTPUT->footer();
        die;
    }
}
/**************************** Do ADD actions ************/
if ($action == 'doadd') {
    // debug_open_trace();

    if (empty($automation)) {
        $vmoodlestep = optional_param('step', 0, PARAM_INT);

        // Retrieve submitted data, from the add form.
        unset($SESSION->vmoodle_mg['dataform']);
        $platform_form = new \local_vmoodle\Host_Form('add', null);

        // Check if form is cancelled.
        if ($platform_form->is_cancelled()) {
            redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
            die;
        }
    }

    // If there is submitted data from form or in session (no errors).
    if (!isset($SESSION->vmoodledata)) {
        $submitteddata = $SESSION->vmoodledata = $platform_form->get_data();
    } else {
        $submitteddata = $SESSION->vmoodledata;
    }

    if ($submitteddata) {

        // debug_trace("entering doadd case");

        // check vhostname length. Must be less than 64 chars (Mnet CSR reason)
        // may be useless using check_credentials() patchs
        /*
        if (strlen($submitteddata->vhostname) > 64){
            if (empty($automation)){
                $message_object->message = get_string('wwwrootexceedscsrlimits', 'local_vmoodle');
                $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                redirect($CFG->wwwroot.'/local/vmoodle/view.php?view=management');
                die;
            }
            mtrace(get_string('wwwrootexceedscsrlimits', 'local_vmoodle'));
            return -1;
        }
        */

        if ($submitteddata->vtemplate === 0) {

            $sqlrequest = 'UPDATE 
                                {mnet_host}
                           SET
                                deleted = 0
                           WHERE
                                wwwroot = "'.$submitteddata->vhostname.'"';
            $DB->execute($sqlrequest);

            $sqlrequest = 'SELECT 
                            *
                           FROM
                                {local_vmoodle}
                           WHERE
                                vhostname = "'.$submitteddata->vhostname.'"';
            $record = $DB->get_record_sql($sqlrequest);

            if (empty($record)) {
                $record = (object) array('name' => $submitteddata->name,
                           'shortname' => $submitteddata->shortname,
                           'description' => $submitteddata->description,
                           'vhostname' => $submitteddata->vhostname,
                           'vdbtype' => $submitteddata->vdbtype,
                           'vdbhost' => $submitteddata->vdbhost,
                           'vdblogin' => $submitteddata->vdblogin,
                           'vdbpass' => $submitteddata->vdbpass,
                           'vdbname' => $submitteddata->vdbname,
                           'vdbpersist' => $submitteddata->vdbpersist,
                           'vdbprefix' => $submitteddata->vdbprefix,
                           'vdbpersist' => $submitteddata->vdbpersist,
                           'vdatapath' => $submitteddata->vdatapath,
                           'mnet' => $submitteddata->mnet);
                $DB->insert_record('local_vmoodle', $record);
            }

            if (empty($automation)) {
                $message_object->message = get_string('plateformreactivate', 'local_vmoodle');
                $message_object->style = 'notifysuccess';
                $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                die;
            }
            return 0;
        } else {

            // Checks if the chosen template still exists.
            $templates = vmoodle_get_available_templates();
            if (empty($templates) || !vmoodle_exist_template($submitteddata->vtemplate)) {
                // If the snapshot has been deleted between loading the add form and submitting it.
                $message_object->message = get_string('notemplates', 'local_vmoodle');
                if (empty($automation)) {
                    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                    redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                    die;
                }
                mtrace(get_string('notemplates', 'local_vmoodle'));
                return -1;
            }

            // Check if the required hostname has DNS resolution
            $domainname = preg_replace('/https?:\/\//', '', $submitteddata->vhostname);
            if (!gethostbynamel($domainname)) {
                if ($submitteddata->forcedns) {
                    print_string('unknownhostforced', 'local_vmoodle');
                    // $submitteddata->mnet = -1;
                } else {
                    $message_object->message = get_string('unknownhost', 'local_vmoodle'). ' : '.$domainname;
                    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                    if (empty($automation)) {
                        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        die;
                    }
                    mtrace($SESSION->vmoodle_ma['confirm_message']->message);
                    return -1;
                }
            }

            // Do we have a "self" host record ?
            // debug_trace("getting this_host");
            if (!$this_as_host = $DB->get_record('mnet_host', array('wwwroot' => $CFG->wwwroot))) {
                // If loading this host's data has failed.
                $message_object->message = get_string('badthishostdata', 'local_vmoodle');

                if (empty($automation)) {
                    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                    redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                    die;
                }
                mtrace(get_string('badthishostdata', 'local_vmoodle'));
                return -1;
            }

        /// Creates database from template.

            if ($vmoodlestep == 0) {
                // debug_trace("step 0 : loading");
                if (!vmoodle_load_database_from_template($submitteddata, $CFG->dataroot.'/vmoodle')) {
                    // If loading database from template has failed.
                    unset($SESSION->vmoodledata);
                    $message_object->message = get_string('badtemplatation', 'local_vmoodle');
                    if (empty($automation)) {
                        $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        die;
                    }
                    mtrace(get_string('badtemplatation', 'local_vmoodle'));
                    return -1;
                }

                if (empty($automation)){
                    echo $OUTPUT->header();
                    echo $OUTPUT->box(get_string('vmoodledoadd1', 'local_vmoodle'));
                    echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management', 'what' => 'doadd', 'step' => 1)));
                    echo $OUTPUT->footer();
                    die;
                }
                return 0;
            }

        // Fix remote database for Mnet operations.

        /* Fixing database will rewrite and prepare the remote mnet_host table for having 
         * consistant identity of the VMoodle Master node.
         * Additionnaly, some data from instance addition form should be forced into 
         * the SQL template, whatever the configuration of the original Moodle was.
         *
         * A script backup is available in vmoodle data directory as 
         *
         * vmoodle_setup_template.temp.sql 
         *
         * with all fixing SQL instructions processed.
         */

            if ($vmoodlestep == 1) {
                // debug_trace("step 1 : fixing DB");
                if (!vmoodle_fix_database($submitteddata, $this_as_host, $CFG->dataroot.'/vmoodle')) {
                    // If fixing database has failed.
                    unset($SESSION->vmoodledata);
                    $message_object->message = get_string('couldnotfixdatabase', 'local_vmoodle');
                    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                    if (empty($automation)){
                        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        die;
                    }
                    mtrace($SESSION->vmoodle_ma['confirm_message']->message);
                    return -1; 
                }
                if (empty($automation)) {
                    echo $OUTPUT->header();
                    echo $OUTPUT->box(get_string('vmoodledoadd2', 'local_vmoodle'));
                    if (debugging()) {
                        $opts['view'] = 'management';
                        $opts['what'] = 'doadd';
                        $opts['step'] = 2;
                        echo "<center>";
                        echo $OUTPUT->single_button(new moodle_url('/local/vmoodle/view.php', $opts), get_string('skip', 'local_vmoodle'), 'get');
                        echo "</center>";
                    }
                    echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management', 'what' => 'doadd', 'step' => 2)));
                    echo $OUTPUT->footer();
                    die;
                }
                return 0;
            }

        /// Get fileset for moodledata

            if ($vmoodlestep == 2) {
                // debug_trace("step 2 : dumping files");
                vmoodle_dump_files_from_template($submitteddata->vtemplate, $submitteddata->vdatapath);
                if (empty($automation)) {
                    echo $OUTPUT->header();
                    echo $OUTPUT->box(get_string('vmoodledoadd3', 'local_vmoodle'));
                    echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management', 'what' => 'doadd', 'step' => 3)));
                    echo $OUTPUT->footer();
                    die;
                }
                return 0;
            }

        /// Insert proper vmoodle record

            if ($vmoodlestep == 3) {
                // debug_trace("step 3 : registering");
                // Adds the new virtual instance record, with all data if everything is done
                $submitteddata->timecreated    = time();
                $submitteddata->vhostname = preg_replace("/\/$/", '', $submitteddata->vhostname); // fix possible misslashing

                if ($submitteddata->mnet == 'NEW') {
                    $maxmnet = vmoodle_get_last_subnetwork_number();
                    $submitteddata->mnet = $maxmnet + 1;
                }
            
                if (!$idnewblock = $DB->insert_record('local_vmoodle', $submitteddata)) {
                    // If inserting data in 'local_vmoodle' table has failed.
                    $message_object->message = get_string('badblockinsert', 'local_vmoodle');
                    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                    if (empty($automation)) {
                        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        die;
                    }
                    mtrace($SESSION->vmoodle_ma['confirm_message']->message);
                    return -1;
                }
                if (empty($automation)) {
                    echo $OUTPUT->header();
                    echo $OUTPUT->box(get_string('vmoodledoadd4', 'local_vmoodle'));
                    echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management', 'what' => 'doadd', 'step' => 4)));
                    echo $OUTPUT->footer();
                    die;
                }
                return 0;
            }

        /// Mnet bind from master side
            if ($vmoodlestep == 4) {

                $newmnet_host = new \local_vmoodle\Mnet_Peer();
                $newmnet_host->set_wwwroot($submitteddata->vhostname);
                $newmnet_host->set_name($submitteddata->name);

                // debug_trace("step 4 : configuring MNET");
                // If the new host is not using MNET, we discard it from us. There will be no more MNET contact with this host.
                // vmoodle_fix_database should have disabled all mnet operations in the remote moodle.
                if ($submitteddata->mnet == -1) {
                    $newmnet_host->updateparams->deleted = 1;
                    $newmnet_host->commit();
                    $message_object->message = get_string('successaddnewhostwithoutmnet', 'local_vmoodle');
                    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                    if (empty($automation)) {
                        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        die; // we have finished
                    }
                    return 1;
                }

                // force renew using remote keyboot.php access
                // debug_trace("step 4.1 : booting remote key");
                $uri = $submitteddata->vhostname.'/local/vmoodle/keyboot.php';

                $rq = 'pk='.urlencode($this_as_host->public_key);
                $ch = curl_init("$uri");
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $rq);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

                // Try remote key booting.
                if (!$res = curl_exec($ch)) {
                    // If remote keybooting has failed.
                    // debug_trace("step 4.1 : Failed boot / No CURL response");
                    $message_object->message = get_string('couldnotkeyboot', 'local_vmoodle', 'CURL Error');
                    if (empty($automation)) {
                        $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        die;
                    }
                    mtrace(get_string('couldnotkeyboot', 'local_vmoodle', 'CURL Error'));
                    return -1;
                }
                if (preg_match('/ERROR/', $res)) {
                    // debug_trace("step 4.1 : Failed boot / ERROR response");
                    // If remote keybooting has failed.
                    $message_object->message = get_string('couldnotkeyboot', 'local_vmoodle', $res);
                    if (empty($automation)) {
                        $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        die;
                    }
                    mtrace(get_string('couldnotkeyboot', 'local_vmoodle', $res));
                    return -1;
                }
                curl_close($ch);

                // Force new virtual host to renew our key and send his own to us.
                // debug_trace("step 4.2 : exchanging keys");

                if (!$newmnet_host->bootstrap($submitteddata->vhostname, null, 'moodle', 1, $submitteddata->name)) {
                    // If bootstraping the new host has failed.
                    // debug_trace("step 4.2 Failed : bootstrap failure");
                    if (empty($automation)) {
                        $SESSION->vmoodle_ma['confirm_message'] = 'bootstrap failure';
                        if (debugging()){
                            echo $OUTPUT->header();
                            echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                            echo $OUTPUT->footer();
                            die;
                        } else {
                            $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                            redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                        }
                        die;
                    }
                    mtrace('bootstrap failure');
                    return -1;
                }
                $newmnet_host->updateparams->deleted = 0; // in case already there and needs revive;
                $newmnet_host->commit();

                // debug_trace("step 4.3 : setting mnetadmin remote side");

                // we need to start output here in case of exceptions
                // echo $OUTPUT->header();

                // Service 'mnetadmin' is needed to speak with new host. Set it our side.
                $slavehost = $DB->get_record('mnet_host', array('wwwroot' => $submitteddata->vhostname));
                // cleanup any previous records
                $DB->delete_records('mnet_host2service', array('hostid' => $slavehost->id));
                $mnetadminservice = $DB->get_record('mnet_service', array('name' => 'mnetadmin'));
                $host2service = new stdclass();
                $host2service->hostid = $slavehost->id;
                $host2service->serviceid = $mnetadminservice->id;
                $host2service->publish = 0;
                $host2service->subscribe = 1;
                $DB->insert_record('mnet_host2service', $host2service);

                $ssoservice = $DB->get_record('mnet_service', array('name' => 'sso_idp'));
                $host2service = new stdclass();
                $host2service->hostid = $slavehost->id;
                $host2service->serviceid = $ssoservice->id;
                $host2service->publish = 1;
                $host2service->subscribe = 0;
                $DB->insert_record('mnet_host2service', $host2service);

                $ssoservice = $DB->get_record('mnet_service', array('name' => 'sso_sp'));
                $host2service = new stdclass();
                $host2service->hostid = $slavehost->id;
                $host2service->serviceid = $ssoservice->id;
                $host2service->publish = 0;
                $host2service->subscribe = 1;
                $DB->insert_record('mnet_host2service', $host2service);

                //Apply the level of SSL verification : 0=none / 1=host / 2=host & peer
                if(!isset($newmnet_host->sslverification))
                    $newmnet_host->sslverification = 2;

                // MNET subnetworking, unless completely isolated
                if ($submitteddata->mnet > 0) {
                    vmoodle_bind_to_network($submitteddata, $newmnet_host);
                }
            }

            // Creating CRON command.
            // Obsolete with vrcon rotator.

            // Every step was SUCCESS.
            $message_object->message = get_string('successaddnewhost', 'local_vmoodle');
            $message_object->style = 'notifysuccess';

            // Save confirm message before redirection.
            // debug_trace("step 4 : Finished");
            unset($SESSION->vmoodledata);
            $SESSION->vmoodle_ma['confirm_message'] = $message_object;
            if (empty($automation)) {
                redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management'))); // finished
                die;
            }
            return 0; // finished
        }
    }
}
/**************************** Make the EDIT form ************/
if ($action == 'edit') {

    // Retrieve the vmoodle platform data.
    $id = required_param('id', PARAM_INT);
    if ($platform_form = $DB->get_record('local_vmoodle', array('id' => $id))) {
        /*
        $message_object->message = get_string('badmoodleid', 'local_vmoodle');
        $SESSION->vmoodle_ma['confirm_message'] = $message_object;
        header('Location: view.php?view=management');
        */

        // Print title (heading).
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editvmoodle', 'local_vmoodle'));
        // Print beginning of a box.
        echo $OUTPUT->box_start();
        // Displays the form with data (and errors).
        $form = new \local_vmoodle\Host_Form('edit');
        $form->set_data($platform_form);
        $form->display();

        // Print ending of a box.
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        die;
    }
}

/**************************** Do EDIT actions ************/

if ($action == 'doedit') {
    // Retrieves data from the edit form.
    $platform_form = new \local_vmoodle\Host_Form('edit');

    // Checks if form is cancelled
    if ($platform_form->is_cancelled()) {
        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
        die;
    }

    // If there is submitted data (no errors).
    if ($submitteddata = $platform_form->get_data()) {
        
        // Updates the host, with all data.
        $olddata = $DB->get_record('local_vmoodle', array('id' => $submitteddata->id));
        $success = false;
        
        if (!$DB->update_record('local_vmoodle', $submitteddata)) {
            // If updating data in 'local_vmoodle' table has failed.
            $message_object->message = get_string('badblockupdate', 'local_vmoodle');
            $SESSION->vmoodle_ma['confirm_message'] = $message_object;
            redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
            die;
        }

        // Updates MNET state, if required.
        if($olddata->mnet != $submitteddata->mnet){

            // Creating the needed mnet_peer object, to do actions.
            $edited_host = new \local_vmoodle\Mnet_Peer();
            if (!$edited_host->bootstrap($olddata->vhostname, null, 'moodle', 1)) {
                // If bootstraping the host has failed.
                $message_object->message = get_string('badbootstraphost', 'local_vmoodle', $olddata->vhostname).' = '.$submitteddata->mnet;
                if (debugging()){
                    echo $OUTPUT->header();
                    echo implode('<br/>', $edited_host->errors);
                    echo $OUTPUT->continue_button('view.php?view=management');
                    echo $OUTPUT->footer();
                } else {
                    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
                    redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
                }
                die;
            }

            // Retrieves last subnetwork members.
            if ($olddata->mnet > 0) {
                $lastsubnetwork_hosts = array();
                $lastsubnetwork_members = $DB->get_records_sql('select * from {local_vmoodle} WHERE id != '.$olddata->id.' AND mnet = '.$olddata->mnet.' AND enabled = 1');
                if (!empty($lastsubnetwork_members)) {
                    foreach ($lastsubnetwork_members as $lastsubnetwork_member) {
                        $temp_host = new stdClass();
                        $temp_host->wwwroot = $lastsubnetwork_member->vhostname;
                        $temp_host->name = utf8_decode($lastsubnetwork_member->name);
                        $lastsubnetwork_hosts[] = $temp_host;
                    }
                }
            }

            // Prepares future subnetwork members.
            if ($submitteddata->mnet > 0) {
                $subnetwork_hosts = array();
                $subnetwork_members = $DB->get_records_sql(' select * from {local_vmoodle} WHERE  id != '.$submitteddata->id.' AND mnet = '.$submitteddata->mnet.' AND enabled = 1');
                if (!empty($subnetwork_members)) {
                    foreach ($subnetwork_members as $subnetwork_member) {
                        $temp_host = new stdClass();
                        $temp_host->wwwroot = $subnetwork_member->vhostname;
                        $temp_host->name = utf8_decode($subnetwork_member->name);
                        $subnetwork_hosts[] = $temp_host;
                    }
                }
            }

            /*
             * Deletes peer in last subnetwork members, and disconnects
             * peer from them, if was subnetworking.
             */
            if ($olddata->mnet > 0) {

                // Call to 'unbind_peer'.
                $rpc_client = new \local_vmoodle\XmlRpc_Client();
                $rpc_client->set_method('local/vmoodle/rpclib.php/mnetadmin_rpc_unbind_peer');
                // Authentication params.
                $rpc_client->add_param($USER->username, 'string');
                $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));
                $rpc_client->add_param($userhostroot, 'string');
                $rpc_client->add_param($CFG->wwwroot, 'string');
                // Peer to unbind from.
                $rpc_client->add_param($edited_host->wwwroot, 'string');
                foreach($lastsubnetwork_hosts as $lastsubnetwork_host){
                    $temp_member = new \local_vmoodle\Mnet_Peer();
                    $temp_member->set_wwwroot($lastsubnetwork_host->wwwroot);
                    // RPC error.
                    if(!$rpc_client->send($temp_member)){
                       echo $OUTPUT->notification(implode('<br />', $rpc_client->getErrors($temp_member)));
                       if (debugging()){
                            echo '<pre>';
                            var_dump($rpc_client);
                            echo '</pre>';
                        }
                    }

                    // unbind other from edited
                    // Call to 'disconnect_from_subnetwork'.
                    $rpc_client_2 = new \local_vmoodle\XmlRpc_Client();
                    $rpc_client_2->set_method('local/vmoodle/rpclib.php/mnetadmin_rpc_unbind_peer');
                    // Authentication params.
                    $rpc_client_2->add_param($USER->username, 'string');
                    $userhostroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $USER->mnethostid));
                    $rpc_client_2->add_param($userhostroot, 'string');
                    $rpc_client_2->add_param($CFG->wwwroot, 'string');
                    // Other to unbind from.
                    $rpc_client_2->add_param($lastsubnetwork_host->wwwroot, 'string');
                    // RPC error.
                    if (!$rpc_client_2->send($edited_host)) {
                        echo $OUTPUT->header();
                        echo $OUTPUT->notification(implode('<br />', $rpc_client_2->getErrors($edited_host)));
                        if (debugging()) {
                            echo '<pre>';
                            var_dump($rpc_client_2);
                            echo '</pre>';
                        }
                        // echo $OUTPUT->footer();
                    }
                    unset($rpc_client_2);
                }
            }

            /*
             * Rebind peer to the new subnetwork members, and connect
             * it to them, if it is subnetworking and not creating new subnetwork.
             */
            if (($submitteddata->mnet > 0) && ($submitteddata->mnet <= vmoodle_get_last_subnetwork_number())) {
                vmoodle_bind_to_network($submitteddata, $edited_host);
            }

            // first check for global mnet disabing/reviving 
            if ($submitteddata->mnet > -1) {
                $edited_host->updateparams->deleted = 0;
            } else {
                // this host has been unbound from all others
                // we should remotely disable its network
                $edited_host->updateparams->deleted = 1;
                $edited_host->commit();
            }

            // Every step was SUCCESS.
            $success = true;
        } else {
            // Every step was SUCCESS.
            $success = true;
        }

        // Every step was SUCCESS.
        if (isset($success) && $success) {
            $message_object->message = get_string('successedithost', 'local_vmoodle').' ';
            $message_object->style = 'notifysuccess';
        }

        // Save confirm message before redirection.
        $SESSION->vmoodle_ma['confirm_message'] = $message_object;
        redirect(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
        die;
    }
}
/**************************** Enables a Vmoodle ************/
if ($action == 'enable') {
    $vmoodleid = required_param('id', PARAM_INT);
    $DB->set_field('local_vmoodle', 'enabled', 1, array('id' => $vmoodleid));
}
/**************************** Disables a vmoodle ************/
if ($action == 'disable') {
    $vmoodleid = required_param('id', PARAM_INT);
    $DB->set_field('local_vmoodle', 'enabled', 0, array('id' => $vmoodleid));
}
/**************************** Snapshots a Vmoodle in the templates ************/
if ($action == 'snapshot'){

    // Parsing url for building the template name.
    if (empty($automation)) {
        $wwwroot = optional_param('wwwroot', '', PARAM_URL);
        $vmoodlestep = optional_param('step', 0, PARAM_INT);
    }
    $hostname = preg_replace('/https?:\/\//', '', $wwwroot);
    $hostname = str_replace(':', '_', $hostname);
    $hostname = str_replace('.', '_', $hostname);
    $hostname = str_replace('-', '_', $hostname);
    $hostname = str_replace('/', '_', $hostname);
    $hostname = str_replace('\\', '_', $hostname);

    // Make template directory (files and SQL).
    $templatefoldername = 'vmoodle';
    $separator = DIRECTORY_SEPARATOR;
    $relative_datadir = $templatefoldername.$separator.$hostname.'_vmoodledata';
    $absolute_datadir = $CFG->dataroot.$separator.$relative_datadir;
    $relative_sqldir = $templatefoldername.$separator.$hostname.'_sql';
    $absolute_sqldir = $CFG->dataroot.$separator.$relative_sqldir;

    if (preg_match('/ /', $absolute_sqldir)) {
        print_error('errorbaddirectorylocation', 'local_vmoodle');
        if ($automation) {
            return -1;
        }
    }

    if (!filesystem_is_dir('vmoodle', $CFG->dataroot)) {
        mkdir($CFG->dataroot.'/vmoodle');
    }

    if ($vmoodlestep == 0) {
        // Create directories, if necessary.
        if (!filesystem_is_dir($relative_datadir, $CFG->dataroot)) {
            mkdir($absolute_datadir, 0777, true);
        } else {
            filesystem_clear_dir($relative_datadir, false, $CFG->dataroot);
        }
        if (!filesystem_is_dir($relative_sqldir, $CFG->dataroot)) {
            mkdir($absolute_sqldir, 0777, true);
        }
        if (empty($automation)) {
            echo $OUTPUT->header();
            echo $OUTPUT->box(get_string('vmoodlesnapshot1', 'local_vmoodle'));
            echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management', 'what' => 'snapshot', 'step' => 1, 'wwwroot' => $wwwroot)));
            echo $OUTPUT->footer();
            die;
        } else {
            // Chain following steps
            $vmoodlestep++;
        }
    }
    if ($vmoodlestep > 0) {
        if ($wwwroot == $CFG->wwwroot) {
            // Make fake Vmoodle record.
            $vmoodle = vmoodle_make_this();
            $vdatabase = '';
            $vdatapath = $CFG->dataroot;
        } else {
            // Get Vmoodle known record.
            $vmoodle = $DB->get_record('local_vmoodle', array('vhostname' => $wwwroot));
            $vdatabase = '';
            $vdatapath = $vmoodle->vdatapath;
        }

        if ($vmoodlestep == 1) {
            echo $OUTPUT->header();
            // Auto dump the database in a master template_folder.
            if (!vmoodle_dump_database($vmoodle, $absolute_sqldir.$separator.'vmoodle_master.sql')) {
                print_error('baddumpcommandpath', 'local_vmoodle');
                if ($automation) {
                    return -1;
                }
            }
            if (empty($automation)) {
                echo $OUTPUT->box(get_string('vmoodlesnapshot2', 'local_vmoodle'));
                $params = array('view' => 'management', 'what' => 'snapshot', 'step' => 2, 'wwwroot' => $wwwroot);
                echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', $params));
                echo $OUTPUT->footer();
                die;
            }
        }

        // End of process.

        // copy moodle data and protect against copy recursion.
        // $CFG->filedebug = 1;
        filesystem_copy_tree($vdatapath, $absolute_datadir, $vdatabase, array("^$templatefoldername\$"));
        // Remove Vmoodle clone session, temp and cache dir.
        filesystem_clear_dir($relative_datadir.$separator.'sessions', true);
        filesystem_clear_dir($relative_datadir.$separator.'temp', true);
        filesystem_clear_dir($relative_datadir.$separator.'cache', true);
        filesystem_clear_dir($relative_datadir.$separator.'localcache', true);

        // Store original hostname for further database replacements.
        $FILE = fopen($absolute_sqldir.$separator.'manifest.php', 'w');
        fwrite($FILE, "<?php\n ");
        fwrite($FILE, "\$templatewwwroot = '".$wwwroot."';\n");
        fwrite($FILE, "\$templatevdbprefix = '".$CFG->prefix."';\n ");
        fwrite($FILE, "?>");
        fclose($FILE);

        if (empty($automation)) {
            // Every step was SUCCESS.
            $message_object->message = get_string('successfinishedcapture', 'local_vmoodle');
            $message_object->style = 'notifysuccess';
    
            // Save confirm message before redirection.
            $SESSION->vmoodle_ma['confirm_message'] = $message_object;
            echo $OUTPUT->header();
            echo $OUTPUT->box(get_string('vmoodlesnapshot3', 'local_vmoodle'));
            echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
            echo $OUTPUT->footer();
            die;
        } else {
            mtrace(get_string('successfinishedcapture', 'local_vmoodle'));
        }
    }
}
/**************************** Delete a Vmoodle and uninstall it ************/
if (($action == 'delete') || ($action == 'fulldelete')) {
    $id = required_param('id', PARAM_INT);
    // Unmarks the Vmoodle in everyplace (subnetwork, common).
    if ($vmoodle = $DB->get_record('local_vmoodle', array('id' => $id))) {
        if($vmoodle_host = $DB->get_record('mnet_host', array('wwwroot' => $vmoodle->vhostname))) {
            // debug_trace('Deleting vmoodle host');
            if (($vmoodle_host->deleted == 0)) {
                $vmoodle_host->deleted    = 1;
                $DB->update_record('mnet_host', $vmoodle_host);
                /*
                if($vmoodle->mnet == 0 || $vmoodle->mnet == -1) {
                    $sqlrequest = 'DELETE
                                   FROM
                                        {local_vmoodle}
                                   WHERE
                                        id='.$id;
                    if($DB->execute($sqlrequest)) {
                        $message_object->message = get_string('successdeletehost', 'local_vmoodle');
                        $message_object->style    =    'notifysuccess';
                    }
                    else {
                        $message_object->message = get_string('badhostalreadydeleted', 'local_vmoodle');
                        $message_object->style    =    'notifysuccess';
                    }
                }
                */
            }

            if (($vmoodle->enabled == 1)) {

                // Deletes(unmarking) the local record and host. It could be regenerated.
                $vmoodle->enabled = 0;
                $vmoodle->vdatapath = addslashes($vmoodle->vdatapath);
                $vmoodle_host->deleted = 1;
                $DB->update_record('local_vmoodle', $vmoodle);
                $DB->update_record('mnet_host', $vmoodle_host);

                // Members of the subnetwork delete the host.
                if ($vmoodle->mnet > 0) {
                    $subnetwork_hosts = array();
                    $sql = "
                        SELECT 
                            * 
                        FROM 
                            {local_vmoodle}
                        WHERE
                            vhostname != ? AND
                            mnet = ? AND
                            enabled  = 1
                        ORDER BY
                            vhostname
                    ";
                    $subnetwork_members = $DB->get_records_sql($sql, array($vmoodle->vhostname, $vmoodle->mnet));
                    if (!empty($subnetwork_members)) {
                        foreach ($subnetwork_members as $subnetwork_member) {
                            $temp_host = new stdClass();
                            $temp_host->wwwroot = $subnetwork_member->vhostname;
                            $temp_host->name = utf8_decode($subnetwork_member->name);
                            $subnetwork_hosts[] = $temp_host;
                        }
                    }

                    if (count($subnetwork_hosts) > 0) {
                        $rpc_client = new \local_vmoodle\XmlRpc_Client();
                        $rpc_client->set_method('local/vmoodle/rpclib.php/mnetadmin_rpc_unbind_peer');
                        $rpc_client->add_param($vmoodle->vhostname, 'string');
                        foreach ($subnetwork_hosts as $subnetwork_host) {
                            $temp_member = new mnet_peer();
                            $temp_member->set_wwwroot($subnetwork_host->wwwroot);
                            // RPC error.
                            if (!$rpc_client->send($temp_member)) {
                                echo $OUTPUT->notification(implode('<br />', $rpc_client->getErrors($temp_member)));if (debugging()){echo '<pre>';var_dump($rpc_client);echo '</pre>';}
                            }
                        }

                        $rpc_client = new \local_vmoodle\XmlRpc_Client();
                        $rpc_client->set_method('local/vmoodle/rpclib.php/mnetadmin_rpc_disconnect_from_subnetwork');
                        $rpc_client->add_param($subnetwork_hosts, 'array');
                        $deleted_peer = new mnet_peer();
                        $deleted_peer->set_wwwroot($vmoodle_host->wwwroot);
                        // RPC error.
                        if(!$rpc_client->send($deleted_peer)){echo $OUTPUT->notification(implode('<br />', $rpc_client->getErrors($deleted_peer)));if (debugging()){echo '<pre>';var_dump($rpc_client);echo '</pre>';}}
                    }
                    // Every step was SUCCESS.
                    $message_object->message = get_string('successdeletehost', 'local_vmoodle');
                    $message_object->style = 'notifysuccess';
                }
            } else {
                // If trying to delete an already deleted host.
                $message_object->message = get_string('badhostalreadydeleted', 'local_vmoodle');
            }
        } else {
            // If local_vmoodles and host are not synchronized.
            $sqlrequest = 'DELETE
                           FROM
                                {local_vmoodle} 
                           WHERE
                                id='.$id;
            if($DB->execute($sqlrequest)) {
                $message_object->message = get_string('successdeletehost', 'local_vmoodle');
                $message_object->style = 'notifysuccess';
            } else {
                $message_object->message = get_string('badhostalreadydeleted', 'local_vmoodle');
                $message_object->style = 'notifysuccess';
            }
        }
    } else {
        // If the Vmoodle record doesn't exist in the block, because of a manual action.
        $message_object->message = get_string('novmoodle', 'local_vmoodle');
    }
    
    if ($action == 'fulldelete'){
        debug_trace('Full deleting vmoodle host');
        vmoodle_destroy($vmoodle);
    }

    /* // Save confirm message before redirection.
    $SESSION->vmoodle_ma['confirm_message'] = $message_object;
    header('Location: view.php?view=management');
    return -1; */
}
/********************* Just rough destroy without care (for bulk cleaning) ************/
if ($action == 'destroy') {

    // If there is submitted data from form or in session (no errors).
    if (isset($SESSION->vmoodledata)) {
        $submitteddata = $SESSION->vmoodledata;
    } else {
        $id = required_param('id', PARAM_INT);
        $submitteddata = $DB->get_record('local_vmoodle', array('id' => $id));
    }

    if ($submitteddata) {
        debug_trace('Destroying vmoodle host');
        vmoodle_destroy($submitteddata);
    }
}
/********************* Run an interactive cronlike trigger forcing key renew on all vmoodle ************/
if ($action == 'renewall'){

    // self renew
    echo $OUTPUT->header();
    echo '<pre>';
    $renewuri = $CFG->wwwroot.'/admin/cron.php?forcerenew=1';
    echo "Running on : $renewuri\n";

    echo "#############################\n";

    $ch = curl_init($renewuri);

    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $raw = curl_exec($ch);
    echo $raw."\n\n";
    echo '</pre>';

    $vmoodles = $DB->get_records_sql(' select * from {local_vmoodle} where mnet > -1');

    echo '<pre>';
    foreach($vmoodles as $vmoodle){
        $renewuri = $vmoodle->vhostname.'/admin/cron.php?forcerenew=1';
        echo "Running on : $renewuri\n";

        echo "#############################\n";

        $ch = curl_init($renewuri);

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $raw = curl_exec($ch);
        echo $raw."\n\n";
    }
    echo '</pre>';
    echo '<center>';
    echo $OUTPUT->continue_button(new moodle_url('/local/vmoodle/view.php', array('view' => 'management')));
    echo '</center>';
    echo $OUTPUT->footer();
    die;
}
/* ******************** Generates physical configs *********** */
if ($action == 'generateconfigs'){

    $allvmoodles = $DB->get_records('local_vmoodle', array());

/// prepare generation dir

    $configpath = $CFG->dataroot.'/vmoodle_configs';
    
    if (!is_dir($configpath)){
        mkdir($configpath, 0777);
    }

/// generate

    $configtemplate = implode('', file($CFG->dirroot.'/config.php'));
    
    $generated = array();
    
    $result = 'generating';

    foreach($allvmoodles as $vm){
        
        $configvm = $configtemplate;

        assert(preg_match("#CFG->wwwroot\s+=\s+'.*?';#", $configvm));
        
        $configvm = preg_replace("#CFG->wwwroot\s+=\s+['\"].*?['\"];#s", 'CFG->wwwroot = \''.$vm->vhostname."';", $configvm);
        $configvm = preg_replace("#CFG->dataroot\s+=\s+['\"].*?['\"];#s", 'CFG->dataroot = \''.$vm->vdatapath."';", $configvm);
        $configvm = preg_replace("#CFG->dbhost\s+=\s+['\"].*?['\"];#s", 'CFG->dbhost = \''.$vm->vdbhost."';", $configvm);
        $configvm = preg_replace("#CFG->dbname\s+=\s+['\"].*?['\"];#s", 'CFG->dbname = \''.$vm->vdbname."';", $configvm);
        $configvm = preg_replace("#CFG->dbuser\s+=\s+['\"].*?['\"];#s", 'CFG->dbuser = \''.$vm->vdblogin."';", $configvm);
        $configvm = preg_replace("#CFG->dbpass\s+=\s+['\"].*?['\"];#s", 'CFG->dbpass = \''.$vm->vdbpass."';", $configvm);
        $configvm = preg_replace("#CFG->prefix\s+=\s+['\"].*?['\"];#s", 'CFG->prefix = \''.$vm->vdbprefix."';", $configvm);
        if ($vm->vdbpersist){
            $configvm = preg_replace("#'dbpersist'\s+=\s+.*?,#", "'dbpersist' = true,", $configvm);
        }
        
        if ($CONFIG = fopen($configpath.'/config-'.$vm->shortname.'.php', 'w')){
            $generated[] = 'config-'.$vm->shortname.'.php';
            fputs($CONFIG, $configvm);
            fclose($CONFIG);
        }
    }
    if (!empty($generated)){
        $result = implode("\n", $generated);
        $controllerresult = get_string('generatedconfigs', 'local_vmoodle', $result);
    }

}
/* ******************** Enable instances *********** */

if ($action == 'enableinstances') {
    $nodes = optional_param_array('vmoodleids', null, PARAM_INT);
    if (!empty($nodes)) {
        $nodelist = implode("','", $nodes);
        $sql = "
            UPDATE 
                {local_vmoodle}
            SET 
                enabled = 1
            WHERE
                id IN ('$nodelist')
        ";
        $DB->execute($sql);
    }
}

/* ******************** Disable instances *********** */

if ($action == 'disableinstances') {
    $nodes = optional_param_array('vmoodleids', null, PARAM_INT);
    if (!empty($nodes)) {
        $nodelist = implode("','", $nodes);
        $sql = "
            UPDATE 
                {local_vmoodle}
            SET 
                enabled = 0
            WHERE
                id IN ('$nodelist')
        ";
        $DB->execute($sql);
    }
}

/* ******************** Destroy instances *********** */

if ($action == 'deleteinstances') {
    $nodes = optional_param_array('vmoodleids', null, PARAM_INT);
    if (!empty($nodes)) {
        $vmoodles = $DB->get_records_list('local_vmoodle', 'id', $nodes);
        if ($vmoodles) {
            foreach ($vmoodles as $vm) {
                if ($vm->enabled == 0) {
                    // Only destroy not running moodle for security
                    vmoodle_destroy($vm);
                }
            }
        }
    }
}

// Return to initial 'max_execution_time' value, in every case.
set_time_limit($initial_max_execution_time);