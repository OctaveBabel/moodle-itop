<?php

require_once($CFG->dirroot.'/mnet/lib.php');
require_once($CFG->dirroot.'/local/vmoodle/plugins/plugins/pluginscontrolslib.php');
require_once($CFG->dirroot.'/local/ent_installer/locallib.php');

/**
* will provide all ent specific initializers after install
*
*/
function xmldb_local_ent_installer_install() {
    global $DB, $CFG;

    mtrace("Installing local distribution configurations");

    // initalize MNET and ensure providing a first key
    // Unfortunately, during initial install, a suitable key pair WILL NOT be generated.
    // This will be fixed by further fix_config.php script.
    $mnet = get_mnet_environment();
    $mnet->init();

    // Init mnet auth.
    $authcontrol = new auth_remote_control('auth','mnet');
    $authcontrol->action('enable');

    // Disable IMS module.
    $authcontrol = new mod_remote_control('mod','imscp');
    $authcontrol->action('disable');

    // ## Publishflow

    // initiate publishflow platform type
    set_config('moodlenodetype', 'factory,catalog');

    // initiate publishflow retrofit mode for common
    set_config('enableretrofit', 1);

    // initiate publishflow files delivery
    set_config('coursedeliveryislocal', 1);

    // initiate publishflow categories
    set_config('coursedelivery_deploycategory', 1);

    // initiate publishflow categories
    set_config('coursedelivery_runningcategory', 1);

    // initiate publishflow categories
    set_config('coursedelivery_closedcategory', 1);
    
    // initiate publishflow topology prefixes
    set_config('mainhostprefix', 'http://commun');

    // initiate publishflow topology prefixes
    set_config('factoryprefix', 'http://commun');
    
    // initiate publishflow topology refresh
    set_config('coursedelivery_networkrefreshautomation', 604800);

    // ## sharedresource

    // initiate sharedresource model
    set_config('pluginchoice', 'scolomfr');

    // ## enhanced my

    // enable enhanced my
    set_config('localmyenable', 1);

    // enable my pinting categories
    set_config('localmyprintcategories', 1);

    // initiaing my enhanced module list
    set_config('localmymodules', "my_caption\nme\nleft_edition_column\nmy_courses\nauthored_courses\ncourse_areas\nauthored_courses\navailable_courses\nlatestnews_simple");

    // ## Adjust some fields length

    $dbman = $DB->get_manager();

    $table = new xmldb_table('user');
    $field = new xmldb_field('department');
    $field->set_attributes(XMLDB_TYPE_CHAR, '126', null, null, null, null, 'institution');
    $dbman->change_field_precision($table, $field);

    // ## Adding usertypes categorization

    $categoryrec = new StdClass;
    $categoryrec->name = ent_installer_string('usertypecategoryname');
    $oldcat = $DB->get_record('user_info_category', array('name' => $categoryrec->name));
    if (!$oldcat) {
        $usertypecategoryid = $DB->insert_record('user_info_category', $categoryrec);
    } else {
        $usertypecategoryid = $oldcat->id;
    }

    // ## Adding usertypes for ENT model

    $i = 0;
    $userfield = new StdClass;
    $userfield->name = ent_installer_string('usertypestudent');
    $userfield->shortname = 'eleve';
    $userfield->datatype = 'checkbox';
    $userfield->description    = ent_installer_string('usertypestudentdesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $usertypecategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible    = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'eleve'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    $i++;
    $userfield = new StdClass;
    $userfield->name = ent_installer_string('usertypeteacher');
    $userfield->shortname = 'enseignant';
    $userfield->datatype = 'checkbox';
    $userfield->description    = ent_installer_string('usertypeteacherdesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $usertypecategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible    = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'enseignant'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    $i++;
    $userfield = new StdClass;
    $userfield->name = ent_installer_string('usertypeparent');
    $userfield->shortname = 'parent';
    $userfield->datatype = 'checkbox';
    $userfield->description    = ent_installer_string('usertypeparentdesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $usertypecategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible    = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'parent'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    $i++;
    $userfield = new StdClass;
    $userfield->name = ent_installer_string('usertypestaff');
    $userfield->shortname = 'administration';
    $userfield->datatype = 'checkbox';
    $userfield->description    = ent_installer_string('usertypestaffdesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $usertypecategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible    = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'administration'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    $i++;
    $userfield = new StdClass;
    $userfield->name = ent_installer_string('usertypeworkmanager');
    $userfield->shortname = 'cdt';
    $userfield->datatype = 'checkbox';
    $userfield->description    = ent_installer_string('usertypeworkmanagerdesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $usertypecategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible    = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'cdt'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    // ## Adding academic information

    $categoryrec = new StdClass;
    $categoryrec->name = ent_installer_string('academicinfocategoryname');
    $academicinfocategoryid = $DB->insert_record('user_info_category', $categoryrec);

    $i = 0;
    $userfield = new StdClass;
    $userfield->name = ent_installer_string('transport');
    $userfield->shortname = 'transport';
    $userfield->datatype = 'checkbox';
    $userfield->description    = ent_installer_string('transportdesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $academicinfocategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible    = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'transport'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    $i++;

    $userfield = new StdClass;
    $userfield->name = ent_installer_string('cohort');
    $userfield->shortname = 'cohort';
    $userfield->datatype = 'text';
    $userfield->description    = ent_installer_string('cohortdesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $academicinfocategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible    = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    $userfield->param1 = 30;
    $userfield->param2 = 32;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'cohort'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    $i++;

    $userfield = new StdClass;
    $userfield->name = ent_installer_string('regime');
    $userfield->shortname = 'regime';
    $userfield->datatype = 'text';
    $userfield->description    = ent_installer_string('regimedesc');
    $userfield->descriptionformat = FORMAT_MOODLE;
    $userfield->categoryid = $academicinfocategoryid;
    $userfield->sortorder = $i;
    $userfield->required = 0;
    $userfield->locked = 1;
    $userfield->visible = 0;
    $userfield->forceunique = 0;
    $userfield->signup = 0;
    $userfield->param1 = 30;
    $userfield->param2 = 128;
    if (!$DB->record_exists('user_info_field', array('shortname' => 'regime'))) {
        $DB->insert_record('user_info_field', $userfield);
    }

    return true;
}