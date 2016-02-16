<?php

if (!defined('MOODLE_INTERNAL')) die('You cannot access this script this way');

require_once($CFG->dirroot.'/lib/formslib.php');

class GetIdForm extends moodleform {

    function definition() {

        $mform = $this->_form;

        $mform->addElement('text', 'search', get_string('search', 'local_ent_installer'), '' );

        $radioarray = array();
        $radioarray[] = & $mform->createElement('radio', 'searchby', '', get_string('byname', 'local_ent_installer'), 1);
        $radioarray[] = & $mform->createElement('radio', 'searchby', '', get_string('bycity', 'local_ent_installer'), 1);
        $mform->addGroup($radioarray, 'radioar', '', array(' '), false);        

        $this->add_action_buttons();
    }
}