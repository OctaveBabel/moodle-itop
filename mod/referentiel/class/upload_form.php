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

require_once($CFG->libdir.'/formslib.php');//putting this is as a safety as i got a class not found error.
/**
 * @package   mod-referentiel
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_referentiel_upload_form extends moodleform {
    function definition() {
        $mform = & $this->_form;
        $instance = $this->_customdata;

        // visible elements
        $mform->addElement('header', 'general', $instance['msg']);
        $mform->addHelpButton('general', 'documenth','referentiel');

        $mform->addElement('text','type',get_string('type_document','referentiel'));
        $mform->setType('type', PARAM_TEXT);
        $mform->addRule('type', get_string('type_aide','referentiel'), 'maxlength', '20', 'server', false, false);

        $mform->addElement('text','description',get_string('description','referentiel'));
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('text','etiquette',get_string('etiquette_document','referentiel'));
        $mform->setType('etiquette', PARAM_TEXT);

        $mform->addElement('text','url',get_string('url','referentiel'));
        $mform->setType('url', PARAM_URL);
        // $mform->addHelpButton('url', 'documenth','referentiel');

$radioarray=array();
$radioarray[] = $mform->createElement('radio', 'cible', '', get_string('yes'), 1, NULL);
$radioarray[] = $mform->createElement('radio', 'cible', '', get_string('no'), 0, NULL);
$mform->addGroup($radioarray, 'cible_link', get_string('cible_link','referentiel'), array(' '), false);
$mform->setDefault('cible', 1);

        //$mform->addElement('filemanager', 'newfile', get_string('uploadafile'));
        //$mform->addElement('filemanager', 'referentiel_file', get_string('uploadafile'), null, $instance['options']);

        // pour une importation puis suppression
        $mform->addElement('filepicker', 'referentiel_file', get_string('uploadafile'), null, $instance['options']);

        // hidden params
        $mform->addElement('hidden', 'd', $instance['d']);
        $mform->setType('d', PARAM_INT);
        
        $mform->addElement('hidden', 'activiteid', $instance['activiteid']);
        $mform->setType('activiteid', PARAM_INT);
        
        $mform->addElement('hidden', 'contextid', $instance['contextid']);
        $mform->setType('contextid', PARAM_INT);
        
        $mform->addElement('hidden', 'userid', $instance['userid']);
        $mform->setType('userid', PARAM_INT);
        
        // MODIF JF 2011/11/29
        if (isset($instance['mailnow'])){
            $mform->addElement('hidden', 'mailnow', $instance['mailnow']);
            $mform->setType('mailnow', PARAM_INT);
        }
        else{
            $mform->addElement('hidden', 'mailnow', 0);
            $mform->setType('mailnow', PARAM_INT);
        }
        
        $mform->addElement('hidden', 'filearea', $instance['filearea']);
        $mform->setType('filearea', PARAM_ALPHA);
        
        $mform->addElement('hidden', 'action', 'uploadfile');
        $mform->setType('action', PARAM_ALPHA);

        // buttons
        $this->add_action_buttons(true, get_string('savechanges', 'admin'));
    }
}
