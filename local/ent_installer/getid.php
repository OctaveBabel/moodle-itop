<?php

/**
 * An accessory script allowing to query the ENT annuary 
 * for school IDs
 * 
 * Implementation Specific : ATOS / ENT Atrium Paca
 */

require('../../config.php');
require_once($CFG->dirroot.'/local/ent_installer/getid_form.php');
require_once($CFG->dirroot.'/local/ent_installer/ldap/ldaplib.php');

$url = $CFG->wwwroot.'/local/ent_installer/getid.php';
$PAGE->set_url($url);

// Security.

require_login();
$systemcontext = context_system::instance();
require_capability('moodle/site:config', $systemcontext);

$getidstr = get_string('getinstitutionidservice', 'local_ent_installer');

$PAGE->set_context($systemcontext);
$PAGE->set_heading($getidstr);
$PAGE->set_pagelayout('admin');

$form = new GetIdForm();

// Get ldap params from real ldap plugin.
$ldapauth = get_auth_plugin('ldap');

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', array('section' => 'local_ent_installer')));
}

$results = array();
if ($data = $form->get_data()) {
    $results = local_ent_installer_ldap_search_institution_id($ldapauth, $data->search, $data->searchby);
}

echo $OUTPUT->header();

echo $OUTPUT->heading($getidstr);

if (!empty($results)) {
    $table = new html_table();
    $table->head = array(get_string('id', 'local_ent_installer'), '', get_string('name'));
    $table->width = '90%';
    $table->size = array('20%', '10%', '70%');
    $table->align = array('left', 'center', 'left');

    foreach ($results as $result) {
        preg_match('/(\d+)(\D)/', $result->id, $matches);
        $numid = $matches[1];
        $keychar = $matches[2];
        $table->data[] = array($numid, $keychar, $result->name);
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->box(get_string('noresults', 'local_ent_installer'));
}

if (get_config('showbenches', 'local_ent_installer')) {
    global $LDAPQUERYTRACE;
    echo $OUTPUT->box($LDAPQUERYTRACE, 'technical-output');
}

$form->display();

echo $OUTPUT->footer();
