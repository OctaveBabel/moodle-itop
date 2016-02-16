<?php
// This file keeps track of upgrades to 
// the vmoodle block
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_local_vmoodle_upgrade($oldversion = 0) {
    global $CFG, $DB;

    $result = true;
    $dbman = $DB->get_manager();

    if ($oldversion < 2015110100) {

        // Changing precision of field vdbpass on table block_vmoodle to (32).
        $table = new xmldb_table('local_vmoodle');
        $field = new xmldb_field('vdatasize', XMLDB_TYPE_INTEGER, '11', true, null, null, null, 'croncount');

        // Launch change of precision for field vdbpass.
        $dbman->add_field($table, $field);
    }

    return $result;
}
