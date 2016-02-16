<?php

defined('MOODLE_INTERNAL') || die;
require_once $CFG->dirroot.'/local/ent_installer/adminlib.php';

if ($hassiteconfig) { // needs this condition or there is error on login page
    $settings = new admin_settingpage('local_ent_installer', get_string('pluginname', 'local_ent_installer'));

    $settingurl = new moodle_url('/local/ent_installer/synctimereport.php');
    $settings->add(new admin_setting_heading('syncbench', get_string('syncbench', 'local_ent_installer'),
                   get_string('syncbenchreportdesc', 'local_ent_installer', $settingurl->out())));

    $settingurl = new moodle_url('/local/ent_installer/sync.php');
    $settings->add(new admin_setting_heading('syncusers', get_string('syncusers', 'local_ent_installer'),
                   get_string('syncusersdesc', 'local_ent_installer', $settingurl->out())));

    $settings->add(new admin_setting_heading('head0', get_string('datasyncsettings', 'local_ent_installer'), ''));

    $frequoptions = array(
        DAYSECS => get_string('onceaday', 'local_ent_installer'),
        7 * DAYSECS => get_string('onceaweek', 'local_ent_installer'),
        30 * DAYSECS => get_string('onceamonth', 'local_ent_installer'),
    );

    $settings->add(new admin_setting_configcheckbox('local_ent_installer/sync_enable', get_string('configsyncenable', 'local_ent_installer'), get_string('configsyncenabledesc', 'local_ent_installer'), ''));
    $settings->add(new admin_setting_configcheckbox('local_ent_installer/cron_enable', get_string('configcronenable', 'local_ent_installer'), get_string('configcronenabledesc', 'local_ent_installer'), ''));
    $settings->add(new admin_setting_configtime('local_ent_installer/cron_hour', 'local_ent_installer/cron_min', get_string('configcrontime', 'local_ent_installer'), '', array('h' => get_config('local_ent_installer','cron_hour'), 'm' => get_config('local_ent_installer', 'cron_min'))));

    $settings->add(new admin_setting_configtext('local_ent_installer/institution_id', get_string('configinstitutionid', 'local_ent_installer'), get_string('configinstitutioniddesc', 'local_ent_installer'), ''));

    $settings->add(new admin_setting_configtext('local_ent_installer/cohort_ix', get_string('configcohortindex', 'local_ent_installer'), get_string('configcohortindexdesc', 'local_ent_installer'), ''));

    $settings->add(new admin_setting_configdatetime('local_ent_installer/last_sync_date', get_string('configlastsyncdate', 'local_ent_installer'),
                       get_string('configlastsyncdatedesc', 'local_ent_installer'), ''));

    $authplugins = get_enabled_auth_plugins(true);
    $authoptions = array();
    foreach ($authplugins as $authname) {
        $authoptions[$authname] = get_string('pluginname', 'auth_'.$authname);
    }
    $settings->add(new admin_setting_configselect('local_ent_installer/real_used_auth', get_string('configrealauth', 'local_ent_installer'), get_string('configrealauthdesc', 'local_ent_installer'), 'ldap', $authoptions));

    $maildisplayoptions = array();
    $maildisplayoptions['0'] = get_string('emaildisplayno');
    $maildisplayoptions['1'] = get_string('emaildisplayyes');
    $maildisplayoptions['2'] = get_string('emaildisplaycourse');
    $settings->add(new admin_setting_configselect('local_ent_installer/initialmaildisplay', get_string('configmaildisplay', 'local_ent_installer'), get_string('configmaildisplaydesc', 'local_ent_installer'), '0', $maildisplayoptions));

    $settings->add(new admin_setting_configtext('local_ent_installer/fake_mail_domain', get_string('configfakemaildomain', 'local_ent_installer'), get_string('configfakemaildomaindesc', 'local_ent_installer'), ''));

    $settings->add(new admin_setting_configcheckbox('local_ent_installer/build_teacher_category', get_string('configbuildteachercategory', 'local_ent_installer'), get_string('configbuildteachercategorydesc', 'local_ent_installer'), ''));

    $categoryoptions = $DB->get_records_menu('course_categories', array(), 'parent,sortorder', 'id, name');
    $settings->add(new admin_setting_configselect('local_ent_installer/teacher_stub_category', get_string('configteacherstubcategory', 'local_ent_installer'), get_string('configteacherstubcategorydesc', 'local_ent_installer'), 'ldap', $categoryoptions));

    $settings->add(new admin_setting_configcheckbox('local_ent_installer/update_institution_structure', get_string('configupdateinstitutionstructure', 'local_ent_installer'), get_string('configupdateinstitutionstructuredesc', 'local_ent_installer'), ''));

    $settings->add(new admin_setting_heading('head1', get_string('structuresearch', 'local_ent_installer'), ''));

    $settings->add(new admin_setting_configtext('local_ent_installer/structure_context', get_string('configstructurecontext', 'local_ent_installer'), get_string('configstructurecontextdesc', 'local_ent_installer'), ''));

    $settings->add(new admin_setting_configtext('local_ent_installer/structure_id_attribute', get_string('configstructureid', 'local_ent_installer'), get_string('configstructureiddesc', 'local_ent_installer'), ''));

    $settings->add(new admin_setting_configtext('local_ent_installer/structure_name_attribute', get_string('configstructurename', 'local_ent_installer'), get_string('configstructurenamedesc', 'local_ent_installer'), ''));

    $getidstr = get_string('configgetinstitutionidservice', 'local_ent_installer');
    $settings->add(new admin_setting_heading('local_ent_installer_searchid', get_string('configgetid', 'local_ent_installer'), "<a href=\"{$CFG->wwwroot}/local/ent_installer/getid.php\">$getidstr</a>"));

    $ADMIN->add('localplugins', $settings);
}

