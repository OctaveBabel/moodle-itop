<?php

if (!defined('MOODLE_INTERNAL')) die('You cannot access this script this way');

require_once($CFG->dirroot.'/lib/formslib.php');

class SyncUsersForm extends moodleform {

    function definition() {

        $mform = $this->_form;

        $mform->addElement('checkbox', 'force', get_string('force', 'local_ent_installer'));

        $this->add_action_buttons();
    }
}