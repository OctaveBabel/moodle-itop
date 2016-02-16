<?php
/**
 * An accessory script allowing to query the ENT annuary 
 * for school IDs
 * 
 * Implementation Specific : ITOP (fork de celle d'ATOS / ENT Atrium Paca par Valery Fremaux)
 */

require('../../config.php');
require_once($CFG->dirroot.'/local/ent_installer/sync_form.php');
require_once($CFG->dirroot.'/local/ent_installer/ldap/ldaplib.php');
require_once($CFG->dirroot.'/local/ent_installer/locallib.php');

$url = new moodle_url('/local/ent_installer/sync.php');
$PAGE->set_url($url);

// Security.

require_login();
$systemcontext = context_system::instance();
require_capability('moodle/site:config', $systemcontext);

$syncstr = get_string('synchroniseusers', 'local_ent_installer');

$PAGE->set_context($systemcontext);
$PAGE->set_heading($syncstr);
$PAGE->set_pagelayout('admin');

$mform = new SyncUsersForm();

// Get ldap params from real ldap plugin.
$ldapauth = get_auth_plugin('ldap');

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', array('section' => 'local_ent_installer')));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($syncstr);

if ($data = $mform->get_data()) {
    // Get ldap params from real ldap plugin.
    $ldapauth = get_auth_plugin('ldap');
    
    // Run the customised synchro.
    $options['force'] = @$data->force;
    $options['verbose'] = true;
    echo '<div class="console">';
    echo '<pre>';
    local_ent_installer_sync_users($ldapauth, $options);
    echo '</pre>';
    echo '</div>';
} else {
    $mform->display();
}

echo '<p><center>';
echo $OUTPUT->single_button(new moodle_url('/admin/settings.php', array('section' => 'local_ent_installer')), get_string('backtosettings', 'local_ent_installer'));
echo '</center></p>';
echo $OUTPUT->footer();
