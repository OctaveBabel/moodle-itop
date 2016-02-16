<?php

define('ENT_TEACHER_CATEGORY_ROLE', 'coursecreator'); // Previously 'manager' but this role won't auto enroll teacher to the courses created in their category

define('ENT_MATCH_GUID', 200);
define('ENT_MATCH_FULL', 100);
define('ENT_MATCH_ID_NO_USERNAME', 50);
define('ENT_MATCH_ID_LASTNAME_NO_USERNAME_FIRSTNAME', 20);
define('ENT_MATCH_USERNAME_ONLY', 15);
define('ENT_MATCH_NO_ID_NO_USERNAME_LASTNAME_FIRSTNAME', 10);
define('ENT_NO_MATCH', 0);

if(isset($options['matchlevel']) && is_numeric($options['matchlevel']))
    define('ENT_ALLOW_MINIMUM_MATCH_LEVEL', $options['matchlevel']);
else
    define('ENT_ALLOW_MINIMUM_MATCH_LEVEL', ENT_MATCH_USERNAME_ONLY);

global $MATCH_STATUS;
$MATCH_STATUS = array(
    ENT_MATCH_GUID => 'MATCH GUID ENT',
    ENT_MATCH_FULL => 'FULL MATCH',
    ENT_MATCH_ID_NO_USERNAME => 'FIX USERNAME',
    ENT_MATCH_ID_LASTNAME_NO_USERNAME_FIRSTNAME => 'LOW MATCH BY ID LASTNAME',
    ENT_MATCH_USERNAME_ONLY => 'LOW MATCH BY USERNAME',
    ENT_MATCH_NO_ID_NO_USERNAME_LASTNAME_FIRSTNAME => 'LOW MATCH BY LASTNAME AND FIRSTNAME'
);

/**
 * Syncronizes user from external LDAP server to moodle user table
 *
 * Sync is now using username attribute.
 *
 * Syncing users removes or suspends users that dont exists anymore in external LDAP.
 * Creates new users and updates coursecreator status of users.
 *
 * @param bool $do_updates will do pull in data updates from LDAP if relevant
 */
function local_ent_installer_sync_users($ldapauth, $options) {
    global $CFG, $DB, $MATCH_STATUS;

    mtrace('');
    $enable = get_config('local_ent_installer', 'sync_enable');
    if (!$enable) {
        mtrace(get_string('syncdisabled', 'local_ent_installer'));
        return;
    }

    $USERFIELDS = local_ent_installer_load_user_fields();

    $lastrun = get_config('local_ent_installer', 'last_sync_date');
    mtrace(get_string('lastrun', 'local_ent_installer', userdate($lastrun)));
    mtrace(get_string('connectingldap', 'auth_ldap'));
    
    if(isset($CFG->auth_ldap_sync_user_type))
        $ldapauth->config->user_type = $CFG->auth_ldap_sync_user_type;
    
    if(isset($CFG->auth_ldap_sync_search_contexts))
        $ldapauth->config->contexts = $CFG->auth_ldap_sync_search_contexts;
    
    $ldapconnection = $ldapauth->ldap_connect();

    $dbman = $DB->get_manager();

    list($usec, $sec) = explode(' ',microtime());
    $starttick = (float)$sec + (float)$usec;

    // Define table user to be created.

    $table = new xmldb_table('tmp_extuser');
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('username', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
    $table->add_field('mnethostid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
    $table->add_field('usertype', XMLDB_TYPE_CHAR, '16', null, null, null, null);
    $table->add_field('lastmodified', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    $table->add_index('userprofile', XMLDB_INDEX_UNIQUE, array('mnethostid', 'username', 'usertype'));

    mtrace(get_string('creatingtemptable', 'auth_ldap', 'tmp_extuser'));

    if ($dbman->table_exists($table)) {
        $dbman->drop_table($table);
    }
    $dbman->create_temp_table($table);

    //
    // get user's list from ldap to sql in a scalable fashion from different user profiles
    // defined as LDAP filters
    //
    // prepare some data we'll need

    $filters = array();

    $institutionid = get_config('local_ent_installer', 'institution_id');

    // Students.
    if (empty($options['role']) || preg_match('/eleve/', $options['role'])) {
        $filterdef = new StdClass();
        $filterdef->institution = '(ENTPersonEtablissements='.$institutionid.')';
        $filterdef->usertype = '(ENTPersonProfils=Eleves)';
        $filterdef->userfield = 'eleve';
        $filters[] = $filterdef;
    }

    // Teaching staff.
    if (empty($options['role']) || preg_match('/enseignant/', $options['role'])) {
        $filterdef = new StdClass();
        $filterdef->institution = '(ENTPersonEtablissements='.$institutionid.')';
        $filterdef->usertype = '(|(ENTPersonProfils=Professeurs)(ENTPersonProfils=Documentalistes)(ENTPersonProfils=ProfesseursVacataires)(ENTPersonProfils=Inspecteur))';
        $filterdef->userfield = 'enseignant';
        $filters[] = $filterdef;
    }

    // Non teaching staff.
    if (empty($options['role']) || preg_match('/administration/', $options['role'])) {
        $filterdef = new StdClass();
        $filterdef->institution = '(ENTPersonEtablissements='.$institutionid.')';
        $filterdef->usertype = '(|(ENTPersonProfils=Administrateurs)(ENTPersonProfils=Direction)(ENTPersonProfils=PersonnelAdministratif)(ENTPersonProfils=PersonnelNonEnseignant)'
            .'(ENTPersonProfils=AdministrateurITOP)(ENTPersonProfils=PersonnelVieScolaire)(ENTPersonProfils=ATOS)(ENTPersonProfils=PersonnelRectorat)(ENTPersonProfils=MissionTice)'
            .'(ENTPersonProfils=TuteurEntreprise)(ENTPersonProfils=CollectivitesLocales)(ENTPersonProfils=AccompagnementEducatif)(ENTPersonProfils=CPE)(ENTPersonProfils=Invite)'
            .'(ENTPersonProfils=AdminProjet))';
        $filterdef->userfield = 'administration';
        $filters[] = $filterdef;
    }

    $contexts = explode(';', $ldapauth->config->contexts);

    if (!empty($ldapauth->config->create_context)) {
        array_push($contexts, $ldapauth->config->create_context);
    }

    $ldap_pagedresults = ldap_paged_results_supported($ldapauth->config->ldap_version);
    $ldap_cookie = '';
    foreach($filters as $filterdef){

        $filter = '(&('.$ldapauth->config->user_attribute.'=*)'.$filterdef->usertype.$filterdef->institution.')';

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            do {
                if ($ldap_pagedresults) {
                    ldap_control_paged_result($ldapconnection, $ldapauth->config->pagesize, true, $ldap_cookie);
                }
                if ($ldapauth->config->search_sub) {
                    // Use ldap_search to find first user from subtree.
                    mtrace("ldapsearch $context, $filter for ".$ldapauth->config->user_attribute);
                    $ldap_result = ldap_search($ldapconnection, $context, $filter, array($ldapauth->config->user_attribute, 'whenChanged'));
                } else {
                    // Search only in this context.
                    mtrace("ldaplist $context, $filter for ".$ldapauth->config->user_attribute);
                    $ldap_result = ldap_list($ldapconnection, $context, $filter, array($ldapauth->config->user_attribute, 'whenChanged'));
                }
                if (!$ldap_result) {
                    continue;
                }
                if ($ldap_pagedresults) {
                    ldap_control_paged_result_response($ldapconnection, $ldap_result, $ldap_cookie);
                }
                if ($entry = @ldap_first_entry($ldapconnection, $ldap_result)) {
                    do {
                        $value = ldap_get_values_len($ldapconnection, $entry, $ldapauth->config->user_attribute);
                        $value = core_text::convert($value[0], $ldapauth->config->ldapencoding, 'utf-8');

                        $modify = ldap_get_values_len($ldapconnection, $entry, 'whenChanged');
                        // $modify = strtotime($modify[0]); // OpenLDAP version
                        if (preg_match('/(\\d{4})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})/', $modify[0], $matches)) {
                            $year = $matches[1];
                            $month = $matches[2];
                            $day = $matches[3];
                            $hour = $matches[4];
                            $min = $matches[5];
                            $sec = $matches[6];
                            $modify = mktime($hour, $min, $sec, $month, $day, $year);
                        } else {
                            $modify = time();
                        }

                        local_ent_installer_ldap_bulk_insert($value, $filterdef->userfield, $modify);
                    } while ($entry = ldap_next_entry($ldapconnection, $entry));
                }
                unset($ldap_result); // Free mem.
            } while ($ldap_pagedresults && !empty($ldap_cookie));
        }
    }

    /*
     * If LDAP paged results were used, the current connection must be completely
     * closed and a new one created, to work without paged results from here on.
     */
    if ($ldap_pagedresults) {
        $ldapauth->ldap_close(true);
        $ldapconnection = $ldapauth->ldap_connect();
    }

    /*
     * preserve our user database
     * if the temp table is empty, it probably means that something went wrong, exit
     * so as to avoid mass deletion of users; which is hard to undo
     */
    $count = $DB->count_records_sql('SELECT COUNT(username) AS count, 1 FROM {tmp_extuser}');
    if ($count < 1) {
        mtrace(get_string('didntgetusersfromldap', 'auth_ldap'));
        $dbman->drop_table($table);
        $ldapauth->ldap_close(true);
        return false;
    } else {
        mtrace(get_string('gotcountrecordsfromldap', 'auth_ldap', $count));
    }


    /********************** User removal. *****************************/
    /*
     * Find users in DB that aren't in ldap -- to be removed!
     * this is still not as scalable (but how often do we mass delete?)
     */
    if ($ldapauth->config->removeuser != AUTH_REMOVEUSER_KEEP) {
        $sql = '
            SELECT
                u.*
            FROM 
                {user} u
            LEFT JOIN 
                {tmp_extuser} e
            ON 
                (u.username = e.username AND u.mnethostid = e.mnethostid)
            WHERE
                u.auth = ? AND 
                u.deleted = 0 AND 
                e.username IS NULL
        ';
        $real_user_auth = get_config('local_ent_installer', 'real_used_auth');
        $remove_users = $DB->get_records_sql($sql, array($real_user_auth));

        if (!empty($remove_users)) {
            mtrace(get_string('userentriestoremove', 'auth_ldap', count($remove_users)));

            foreach ($remove_users as $user) {
                if ($ldapauth->config->removeuser == AUTH_REMOVEUSER_FULLDELETE) {
                    if (empty($options['simulate'])) {
                        if (empty($options['fulldelete'])) {
                            // Make a light delete of users, but keeping data for revival.
                            $user->deleted = 1;
                            try {
                                $DB->update_record('user', $user);
                                // Subscription does not work if it contains a user marked as deleted
                                $DB->delete_records('cohort_members', array('userid' => $user->id));

                                mtrace(get_string('auth_dbdeleteuser', 'auth_db', array('name' => $user->username, 'id' => $user->id)));
                            }
                            catch(Exception $e) {
                                mtrace(get_string('auth_dbdeleteusererror', 'auth_db', $user->username));
                            }
                        } else {
                            // Make a complete delete of users, enrols, grades and data
                            if (delete_user($user)) {
                                echo "\t";
                                mtrace(get_string('auth_dbdeleteuser', 'auth_db', array('name' => $user->username, 'id' => $user->id)));
                            } else {
                                echo "\t";
                                mtrace(get_string('auth_dbdeleteusererror', 'auth_db', $user->username));
                            }
                        }
                    } else {
                        mtrace(get_string('simulateuserdelete', 'ent_installer', $user->username));
                    }
                } elseif ($ldapauth->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
                    if (empty($options['simulate'])) {
                        $updateuser = new stdClass();
                        $updateuser->id = $user->id;
                        $updateuser->auth = 'nologin';
                        $DB->update_record('user', $updateuser);
                        echo "\t";
                        mtrace(get_string('auth_dbsuspenduser', 'auth_db', array('name' => $user->username, 'id' => $user->id)));
                        $euser = $DB->get_record('user', array('id' => $user->id));
                        //events_trigger('user_updated', $euser); => deprecated !
                        $event = core\event\user_updated::create_from_userid($euser->id);
                        $event->trigger();
                    } else {
                        mtrace(get_string('simulateusersuspend', 'ent_installer', $user->username));
                    }
                }
            }
        } else {
            mtrace(get_string('nouserentriestoremove', 'auth_ldap'));
        }
        unset($remove_users); // free mem!
    }
    else {
        mtrace("AUTH_REMOVEUSER_KEEP is on or ldapauth config removeuser is off");
    }

    /********************* Revive suspended users. *********************************/
    if (!empty($ldapauth->config->removeuser) && $ldapauth->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
        $sql = "
            SELECT
                u.id, u.username
            FROM
                {user} u
            JOIN
                {tmp_extuser} e
            ON
                (u.username = e.username AND u.mnethostid = e.mnethostid)
            WHERE
                u.auth = 'nologin' AND u.deleted = 0
        ";
        $revive_users = $DB->get_records_sql($sql);

        if (!empty($revive_users)) {
            mtrace(get_string('userentriestorevive', 'auth_ldap', count($revive_users)));

            foreach ($revive_users as $user) {
                $updateuser = new stdClass();
                $updateuser->id = $user->id;
                $updateuser->auth = $ldapauth->authtype;
                $DB->update_record('user', $updateuser);
                echo "\t";
                mtrace(get_string('auth_dbreviveduser', 'auth_db', array('name' => $user->username, 'id' => $user->id)));
                $euser = $DB->get_record('user', array('id' => $user->id));
                //events_trigger('user_updated', $euser); => deprecated !
                $event = core\event\user_updated::create_from_userid($euser->id);
                $event->trigger();
            }
        } else {
            mtrace(get_string('nouserentriestorevive', 'auth_ldap'));
        }

        unset($revive_users);
    }

    /****************************** User Updates - time-consuming (optional). ***************************/
    // This might be an OBSOLETE code, regarding the updat ecapability of the create process.
    if (!empty($options['doupdates'])) {
        // Narrow down what fields we need to update.
        $all_keys = array_keys(get_object_vars($ldapauth->config));
        $updatekeys = array();
        foreach ($all_keys as $key) {
            if (preg_match('/^field_updatelocal_(.+)$/', $key, $match)) {
                /*
                 * If we have a field to update it from
                 * and it must be updated 'onlogin' we
                 * update it on cron
                 */
                if (!empty($ldapauth->config->{'field_map_'.$match[1]}) && ($ldapauth->config->{$match[0]} === 'onlogin')) {
                    // The actual key name.
                    array_push($updatekeys, $match[1]);
                }
            }
        }
        unset($all_keys); 
        unset($key);

    } else {
        mtrace(get_string('noupdatestobedone', 'auth_ldap'));
    }

    if (@$options['doupdates'] and !empty($updatekeys)) {
        // Run updates only if relevant.
        $users = $DB->get_records_sql('SELECT u.username, u.id
                                         FROM {user} u
                                        WHERE u.deleted = 0 AND u.auth = ? AND u.mnethostid = ?',
                                      array($ldapauth->authtype, $CFG->mnet_localhost_id));
        if (!empty($users)) {
            mtrace(get_string('userentriestoupdate', 'auth_ldap', count($users)));

            $sitecontext = context_system::instance();
            if (!empty($ldapauth->config->creators) and !empty($ldapauth->config->memberattribute) 
                    && $roles = get_archetype_roles('coursecreator')) {
                // We can only use one, let's use the first one.
                $creatorrole = array_shift($roles);
            } else {
                $creatorrole = false;
            }

            $transaction = $DB->start_delegated_transaction();
            $xcount = 0;
            $maxxcount = 100;

            foreach ($users as $user) {
                echo "\t";
                $tracestr = get_string('auth_dbupdatinguser', 'auth_db', array('name' => $user->username, 'id' => $user->id)); 
                if (!$ldapauth->update_user_record($user->username, $updatekeys)) {
                    $tracestr .= ' - '.get_string('skipped');
                }
                mtrace($tracestr);
                $xcount++;

                // Update course creators if needed.
                if ($creatorrole !== false) {
                    if ($ldapauth->iscreator($user->username)) {
                        role_assign($creatorrole->id, $user->id, $sitecontext->id, $ldapauth->roleauth);
                    } else {
                        role_unassign($creatorrole->id, $user->id, $sitecontext->id, $ldapauth->roleauth);
                    }
                }
            }
            $transaction->allow_commit();
            unset($users); // free mem
        }
    } else { 
        // End do updates.
        mtrace(get_string('noupdatestobedone', 'auth_ldap'));
    }

    /***************************** User Additions or full profile update. **********************************/
    /*
     * Find users missing in DB that are in LDAP or users that have been modified since last run
     * and gives me a nifty object I don't want.
     * note: we do not care about deleted accounts anymore, this feature was replaced by suspending to nologin auth plugin
     */
    if (empty($options['force'])) {
        $sql = 'SELECT e.id, e.username, e.usertype
                  FROM {tmp_extuser} e
                  LEFT JOIN {user} u ON (e.username = u.username AND e.mnethostid = u.mnethostid)
                 WHERE u.id IS NULL OR (
                 e.lastmodified > ? ) ORDER BY e.username';
        $params = array($lastrun);
    } else {
        $sql = 'SELECT e.id, e.username, e.usertype
                  FROM {tmp_extuser} e ORDER BY e.username';
        $params = array();
    }
    $add_users = $DB->get_records_sql($sql, $params);

    if (!empty($add_users)) {
        mtrace(get_string('userentriestoadd', 'auth_ldap', count($add_users)));

        $sitecontext = context_system::instance();
        if (!empty($ldapauth->config->creators) && !empty($ldapauth->config->memberattribute)
          && $roles = get_archetype_roles('coursecreator')) {
            // We can only use one, let's use the first one.
            $creatorrole = array_shift($roles);
        } else {
            $creatorrole = false;
        }

        $inserterrorcount = 0;
        $updateerrorcount = 0;
        $insertcount = 0;
        $updatecount = 0;
        
        // Before processing each user we check that every common hosts access keys fields are correctly set up
        if (file_exists($CFG->dirroot.'/blocks/user_mnet_hosts/locallib.php')) {
            require_once($CFG->dirroot.'/blocks/user_mnet_hosts/locallib.php');

            $like = $DB->sql_like('wwwroot', ':wwwroot', false, false);
            $mainhosts = explode(',', @$CFG->mainhostprefix);
            if (!empty($mainhosts)) {
                foreach ($mainhosts as $hostwww) {
                    // Check if common hosts exist
                    if ($commonroots = $DB->get_records_select('mnet_host', " $like AND deleted <> 1 ", array('wwwroot' => $hostwww.'%'))) {
                        foreach($commonroots as $root) {
                            // Check if current common host access key field exists, if not create one
                            $expectedname = user_mnet_hosts_make_accesskey($root->wwwroot, true);
                            $accessmainhost = $DB->get_records('user_info_field', array('shortname' => $expectedname), '', 'id');
                            if(!$accessmainhost) {
                                mtrace("Common host access key '$expectedname' not found");
                                if (!isset($CFG->accesscategory)) {
                                    $accesscategory = new stdClass();
                                    $accesscategory->name = get_string('accesscategory', 'block_user_mnet_hosts');
                                    $accesscategory->sortorder = 1;
                                    $id = $DB->insert_record('user_info_category', $accesscategory);
                                    set_config('accesscategory', $id);
                                }
                                $hostkey = user_mnet_hosts_make_accesskey($root->wwwroot, false);
                                
                                $newfield = new stdClass();
                                $newfield->shortname = $expectedname;
                                $newfield->name = get_string('fieldname', 'block_user_mnet_hosts').' '.$hostkey;
                                $newfield->datatype = 'checkbox';
                                $newfield->locked = 1;
                                $newfield->categoryid = $CFG->accesscategory;
                                if ($DB->insert_record('user_info_field', $newfield)) {
                                    mtrace("Common host access key created : $expectedname");
                                } else {
                                    mtrace("Common host access key creation error : $expectedname");
                                }
                            }
                        }
                    }
                }
            }
        }
        // Before processing each user we check that custom field to store ENT profil & guid are correctly set up
        $infoscateg = $DB->get_records('user_info_category', array('name' => 'Informations académiques'), '', 'id');
        if(!empty($infoscateg)){
            $infoscateg = array_values($infoscateg);
            $idinfocateg = $infoscateg[0]->id;

            $profilent = $DB->get_records('user_info_field', array('shortname' => 'profilent'), '', 'id');
            if(empty($profilent)){
                $maxsortorder = $DB->get_records_sql('SELECT MAX(sortorder) AS maxsortorder FROM {user_info_field} WHERE categoryid = ?', array($idinfocateg));
                $maxsortorder = array_values($maxsortorder);
                $sortordertoinsert = $maxsortorder[0]->maxsortorder+1;
                
                $user_info_field = new stdClass();
                $user_info_field->shortname = 'profilent';
                $user_info_field->name = 'Profil ENT';
                $user_info_field->datatype = 'text';
                $user_info_field->description = '<p>Les profils ENT sont utilisés pour traitements spécifiques post-synchronisation et proviennent du champ ENTPersonProfils du SDET<br></p>';
                $user_info_field->descriptionformat = 1;
                $user_info_field->categoryid = $idinfocateg;
                $user_info_field->sortorder = $sortordertoinsert;
                $user_info_field->required = 0;
                $user_info_field->locked = 1;
                $user_info_field->visible = 0;
                $user_info_field->forceunique = 0;
                $user_info_field->signup = 0;
                $user_info_field->defaultdata = '';
                $user_info_field->defaultdataformat = 0;
                $user_info_field->param1 = '30';
                $user_info_field->param2 = '2048';
                $user_info_field->param3 = '0';
                $user_info_field->param4 = '';
                $user_info_field->param5 = '';
                
                $fieldid = $DB->insert_record('user_info_field', $user_info_field);
                mtrace("user_info_field : 'profilent' inserted !");
                
                if($USERFIELDS['profilent'] == 0 && $fieldid != 0)
                    $USERFIELDS['profilent'] = $fieldid;
                else
                    mtrace("user_info_field : cannot get id for 'profilent' !");
            }
            
            $guident = $DB->get_records('user_info_field', array('shortname' => 'guident'), '', 'id');
            if(empty($guident)){
                $maxsortorder = $DB->get_records_sql('SELECT MAX(sortorder) AS maxsortorder FROM {user_info_field} WHERE categoryid = ?', array($idinfocateg));
                $maxsortorder = array_values($maxsortorder);
                $sortordertoinsert = $maxsortorder[0]->maxsortorder+1;
                
                $user_info_field = new stdClass();
                $user_info_field->shortname = 'guident';
                $user_info_field->name = 'Guid ENT';
                $user_info_field->datatype = 'text';
                $user_info_field->description = '<p><p>Le GUID ENT est utilisé pour la synchronisation des comptes et provient du champ AD objectGuid<br></p></p>';
                $user_info_field->descriptionformat = 1;
                $user_info_field->categoryid = $idinfocateg;
                $user_info_field->sortorder = $sortordertoinsert;
                $user_info_field->required = 0;
                $user_info_field->locked = 1;
                $user_info_field->visible = 0;
                $user_info_field->forceunique = 0;
                $user_info_field->signup = 0;
                $user_info_field->defaultdata = '';
                $user_info_field->defaultdataformat = 0;
                $user_info_field->param1 = '36';
                $user_info_field->param2 = '2048';
                $user_info_field->param3 = '0';
                $user_info_field->param4 = '';
                $user_info_field->param5 = '';
                
                $fieldid = $DB->insert_record('user_info_field', $user_info_field);
                mtrace("user_info_field : 'guident' inserted !");
                
                if($USERFIELDS['guident'] == 0 && $fieldid != 0)
                    $USERFIELDS['guident'] = $fieldid;
                else
                    mtrace("user_info_field : cannot get id for 'guident' !");
            }
        }
        else
            mtrace("user_info_category : 'Informations académiques' missing !");
        // We scan new proposed users from LDAP.
        foreach ($add_users as $user) {

            // Save usertype.
            $usertype = $user->usertype;

            $user = local_ent_installer_get_userinfo_asobj($ldapauth, $user->username, $options);
            // Restore usertype in user.
            $user->usertype = $usertype;

            // Post filter of idnumber
            list($foosdetprefix, $user->idnumber) = explode('$', $user->idnumber);

            if (empty($user->firstname)) {
                mtrace('ERROR : Missing firstname in incoming record '.$user->username);
                $updateerrorcount++;
                continue;
            }

            if (empty($user->lastname)) {
                mtrace('ERROR : Missing lastname in incoming record '.$user->username);
                $updateerrorcount++;
                continue;
            }

            if (empty($user->email)) {
                $user->email = local_ent_installer_generate_email($user);
            }

            // Prep a few params.
            $user->modified = time();
            $user->confirmed = 1;
            $user->deleted = 0;
            $user->suspended = 0;

            // Authentication is the ldap plugin or a real auth plugin defined in setup.
            $realauth = get_config('local_ent_installer', 'real_used_auth');
            $user->auth = (empty($realauth)) ? $ldapauth->authtype : $realauth ;
            $user->mnethostid = $CFG->mnet_localhost_id;
            $user->country = $CFG->country;

            /*
             * Get_userinfo_asobj() might have replaced $user->username with the value.
             * from the LDAP server (which can be mixed-case). Make sure it's lowercase
             */
            $user->username = trim(core_text::strtolower($user->username));
            if (empty($user->lang)) {
                $user->lang = $CFG->lang;
            }

            /*
             * Process additional info for student :
             * extra information fields transport and regime.
             */
            if ($user->usertype == 'eleve') {

                // Transport.
                $user->profile_field_transport = ('Y' == @$user->ENTEleveTransport) ? '1' : 0;

                // Regime.
                $user->profile_field_regime = @$user->ENTEleveRegime;

                // Cohort (must have).
                $user->profile_field_cohort = $user->ENTEleveClasses;
            }
            // Profil ENT
            $user->profile_field_profil = $user->ENTPersonProfils;
            // Guid ENT
            $user->profile_field_guid = $user->objectGUID;

            $personfunction = @$user->ENTPersonFonctions;
            unset($user->ENTPersonFonctions);

            // Get the last term of personfunction and set it as department.
            if (!empty($personfunction)) {
                preg_match('/\\$([^\\$]+)$/', $personfunction, $matches);
                $user->department = $matches[1];
            }

            if (empty($options['simulate'])) {

                // Creation/full update sequence.
                $a = clone($user);
                $a->function = $personfunction;
                
                /*
                 * Special case : si there a matching policy possible for previous accounts NOT being
                 * created by this system ? 
                 */
                
                try {
                    $oldrec = local_ent_installer_guess_old_record($user, $status);
                }
                catch(Exception $e) {
                    mtrace('ERROR : Fail to bind user '.$user->username); 
                    if ($options['verbose'])  trace_debug($e);
                    $inserterrorcount++;
                    continue;
                }
                if ($oldrec) {

                    $a->status = $MATCH_STATUS[$status];
                    $id = $user->id = $oldrec->id;
                    try {
                        $DB->update_record('user', $user);
                        mtrace(get_string('dbupdateuser', 'local_ent_installer', $a));
                        $updatecount++;
                    }
                    catch(Exception $e) {
                        mtrace('ERROR : Fail to update user '.$user->username);
                        if ($options['verbose'])  trace_debug($e);
                        $updateerrorcount++;
                        continue;
                    }
                } else {
                    try {
                        $id = $DB->insert_record('user', $user);
                        mtrace(get_string('dbinsertuser', 'local_ent_installer', $a));
                        $insertcount++;
                    }
                    catch(Exception $e) {
                        mtrace('ERROR : Fail to insert user '.$user->username); 
                        if ($options['verbose'])  trace_debug($e);
                        $inserterrorcount++;
                        continue;
                    }
                }

            } else {
                $a = clone($user);
                $a->function = $personfunction;
                if (!$oldrec = local_ent_installer_guess_old_record($user, $status)) {
                    mtrace(get_string('dbinsertusersimul', 'local_ent_installer', $a));
                } else {
                    $a->status = $MATCH_STATUS[$status];
                    mtrace(get_string('dbupdateusersimul', 'local_ent_installer', $a));
                }
            }

            if (empty($options['simulate'])) {
                $euser = $DB->get_record('user', array('id' => $id));
                //events_trigger('user_created', $euser); => deprecated !
                $event = core\event\user_created::create_from_userid($euser->id);
                $event->trigger();

                if (!empty($ldapauth->config->forcechangepassword)) {
                    set_user_preference('auth_forcepasswordchange', 1, $id);
                }

                // Cohort information / create/update cohorts.
                if ($user->usertype == 'eleve') {

                    // Adds user to cohort and create cohort if missing.
                    $cohortshort = local_ent_installer_check_cohort($id, $user->profile_field_cohort);

                    local_ent_installer_update_info_data($id, $USERFIELDS['transport'], $user->profile_field_transport);
                    local_ent_installer_update_info_data($id, $USERFIELDS['regime'], $user->profile_field_regime);
                    local_ent_installer_update_info_data($id, $USERFIELDS['cohort'], $cohortshort);

                    if(isset($user->ENTEleveGroupes) && count($user->ENTEleveGroupes) > 0) {
                        foreach($user->ENTEleveGroupes as $group) {
                            $cohortshort = local_ent_installer_check_cohort($id, $group, false, $options['verbose']);
                        }
                    }
                }
                // Create profil cohort to allow enrollment by profil
                $cohortshort = local_ent_installer_check_cohort($id, $user->profile_field_profil, false, $options['verbose']);
                

                if ($user->profile_field_profil == 'Administrateurs') {
                    if(isset($id)) {
                        $managerrole = $DB->get_record('role', array('shortname' => 'manager'));
                        role_assign($managerrole->id, $id, 1); // 1=system context
                    }
                    else
                        mtrace('WARNING : Cannot assign role manager to '.$user->username);
                }
                local_ent_installer_update_info_data($id, $USERFIELDS['profilent'], $user->profile_field_profil);
                local_ent_installer_update_info_data($id, $USERFIELDS['guident'], $user->profile_field_guid);

                // Add course creators if needed.
                if ($creatorrole !== false and $ldapauth->iscreator($user->username)) {
                    role_assign($creatorrole->id, $id, $sitecontext->id, $ldapauth->roleauth);
                }

                // Process user_fields setup.
                if (preg_match('#\\$CTR\\$#', $personfunction)) {
                    // Special case.
                    local_ent_installer_update_info_data($id, $USERFIELDS['cdt'], 1);
                } else {
                    // Other user types.
                    local_ent_installer_update_info_data($id, $USERFIELDS[$user->usertype], 1);
                }

                if (file_exists($CFG->dirroot.'/blocks/user_mnet_hosts/locallib.php')) {
                    // user_mnet_hosts local library has already been included upstream of this script

                    // All users have access marked on self.
                    user_mnet_hosts_set_access($id, true);

                    // Setting default access field policy for powered users.
                    if ($user->usertype == 'enseignant' || $user->usertype == 'administration') {
                        $like = $DB->sql_like('wwwroot', ':wwwroot', false, false);
                        $mainhosts = explode(',', @$CFG->mainhostprefix);
                        $given = false;
                        if (!empty($mainhosts)) {
                            foreach ($mainhosts as $hostwww) {
                                if ($commonroots = $DB->get_records_select('mnet_host', " $like AND deleted <> 1 ", array('wwwroot' => $hostwww.'%'))) {
                                    foreach($commonroots as $root) {
                                        $given = true;
                                        user_mnet_hosts_set_access($id, true, $root->wwwroot);
                                    }
                                }
                            }
                        }
                        if (!$given) {
                            mtrace('Giving teacher access : no common host found ');
                        }
                    }
                }

                // Add a workplace to teachers.
                if ($user->usertype == 'enseignant') {
                    if (get_config('local_ent_installer', 'build_teacher_category')) {
                        if(isset($options['unassignteachercategoryrole']))
                            local_ent_installer_make_teacher_category($euser, $options['unassignteachercategoryrole']);
                        else
                            local_ent_installer_make_teacher_category($euser);
                    }
                }

                // Identify librarians and give library enabled role at system level.
                if (preg_match('#\\$DOC\\$#', $personfunction)) {
                    if ($role = $DB->get_record('role', array('shortname' => 'librarian'))) {
                        $systemcontext = context_system::instance();
                        role_assign($role->id, $id, $systemcontext->id);
                    }
                }
            }
        }
        unset($add_users); // free mem
    } else {
        mtrace(get_string('nouserstobeadded', 'auth_ldap'));
    }

    $ldapauth->ldap_close();

    list($usec, $sec) = explode(' ',microtime());
    $stoptick = (float)$sec + (float)$usec;

    $deltatime = $stoptick - $starttick;

    mtrace('Execution time : '.$deltatime);
    $benchrec = new StdClass();
    $benchrec->timestart = floor($starttick);
    $benchrec->timerun = ceil($deltatime);
    $benchrec->added = 0 + @$insertcount;
    $benchrec->updated = 0 + @$updatecount;
    $benchrec->updateerrors = 0 + @$inserterrorcount;
    $benchrec->inserterrors = 0 + @$updateerrorcount;

    $DB->insert_record('local_ent_installer', $benchrec);

    // Mark last time the user sync was run.
    set_config('last_sync_date', time(), 'local_ent_installer');

    try {
        $dbman->drop_table($table);
    } catch (Exception $e) {}

    return true;
}

/**
 * This function encapsulates all the strategies to find old records in moodle, matching
 * a new user proposal. In standard cases (regular updates), the username is sufficiant and
 * consistant. In cases of a system initialisation or IDP change, the username matching may require
 * some translation ro catch older records.
 *
 * the matching strategy adopted is a regressive check from very constrainted match to less constraint match
 */
function local_ent_installer_guess_old_record($newuser, &$status) {
    global $DB;
    
    // We do not take care of case, our collation is FRENCH_CI_AI
    
    // If users come from an ITOP Active Directory we use the GUID ENT which is consistent and unique (not always the case for idnumber)
    if(isset($newuser->objectGUID) && strlen($newuser->objectGUID) == 36) {
        $oldrecs = $DB->get_records_sql(
            'SELECT u.* FROM {user} u'
                .' INNER JOIN {user_info_data} d ON u.id = d.userid'
                .' INNER JOIN {user_info_field} f ON d.fieldid = f.id'
                .' WHERE f.shortname = ? AND d.data = ?'
            , array('guident', $newuser->objectGUID)
        );
        if ($oldrecs) {
            $status = ENT_MATCH_GUID;
            return array_shift($oldrecs);
        }
    }
    
    // If all ID parts match, we are sure (usual case when regular updating).
    if(ENT_MATCH_FULL >= ENT_ALLOW_MINIMUM_MATCH_LEVEL) {
        $oldrec = $DB->get_record('user', array('username' => $newuser->username, 'idnumber' => $newuser->idnumber, 'firstname' => toASCII($newuser->firstname), 'lastname' => toASCII($newuser->lastname)));
        if ($oldrec) {
            $status = ENT_MATCH_FULL;
            return $oldrec;
        }
    }

    // Assuming matching IDNumber and all name parts is good : username not matching, will be updated to new
    if(ENT_MATCH_ID_NO_USERNAME >= ENT_ALLOW_MINIMUM_MATCH_LEVEL) {
        $oldrec = $DB->get_record('user', array('idnumber' => $newuser->idnumber, 'firstname' => toASCII($newuser->firstname), 'lastname' => toASCII($newuser->lastname)));
        if ($oldrec) {
            $status = ENT_MATCH_ID_NO_USERNAME;
            return $oldrec;
        }
    }

    // failover : IDNumber and last name match, but not firstname. this may occur with misspelling
    if(ENT_MATCH_ID_LASTNAME_NO_USERNAME_FIRSTNAME >= ENT_ALLOW_MINIMUM_MATCH_LEVEL) {
        $oldrec = $DB->get_record('user', array('idnumber' => $newuser->idnumber, 'lastname' => toASCII($newuser->lastname)));
        if ($oldrec) {
            $status = ENT_MATCH_ID_LASTNAME_NO_USERNAME_FIRSTNAME;
            return $oldrec;
        }
    }

    // failover : only login match
    if(ENT_MATCH_USERNAME_ONLY >= ENT_ALLOW_MINIMUM_MATCH_LEVEL) {
        $oldrec = $DB->get_record('user', array('username' => $newuser->username));
        if ($oldrec) {
            $status = ENT_MATCH_USERNAME_ONLY;
            return $oldrec;
        }
    }

    // failover : Only lastname and firstname match, but we might have more than one records
    if(ENT_MATCH_NO_ID_NO_USERNAME_LASTNAME_FIRSTNAME >= ENT_ALLOW_MINIMUM_MATCH_LEVEL) {
        $oldrecs = $DB->get_records('user', array('firstname' => toASCII($newuser->firstname), 'lastname' => toASCII($newuser->lastname)));
        if ($oldrecs) {
            $status = ENT_MATCH_NO_ID_NO_USERNAME_LASTNAME_FIRSTNAME;
            return array_shift($oldrecs);
        }
    }
    
    $status = ENT_NO_MATCH;
    return null;
}

/**
 * Bulk insert in SQL's temp table
 */
function local_ent_installer_ldap_bulk_insert($username, $usertype, $timemodified) {
    global $DB, $CFG;

    $username = core_text::strtolower($username); // usernames are __always__ lowercase.
    if (!$DB->record_exists('tmp_extuser', array('username' => $username,
                                                'mnethostid' => $CFG->mnet_localhost_id,
                                                'usertype' => $usertype))) {
        $DB->insert_record_raw('tmp_extuser', array('username' => $username,
                                                    'mnethostid' => $CFG->mnet_localhost_id,
                                                    'usertype' => $usertype,
                                                    'lastmodified' => $timemodified), false, true);
    }
    echo '.';
}

/**
 * loads User Type special info fields definition
 * @return an array of info/custom field mappings
 */
function local_ent_installer_load_user_fields() {
    global $DB;

    $USERFIELDS = array();

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'eleve'));
    assert($fieldid != 0);
    $USERFIELDS['eleve'] = $fieldid;

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'parent'));
    assert($fieldid != 0);
    $USERFIELDS['parent'] = $fieldid;

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'enseignant'));
    assert($fieldid != 0);
    $USERFIELDS['enseignant'] = $fieldid;

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'administration'));
    assert($fieldid != 0);
    $USERFIELDS['administration'] = $fieldid;

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'cdt'));
    assert($fieldid != 0);
    $USERFIELDS['cdt'] = $fieldid;

    // Academic info.

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'cohort'));
    assert($fieldid != 0);
    $USERFIELDS['cohort'] = $fieldid;

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'transport'));
    assert($fieldid != 0);
    $USERFIELDS['transport'] = $fieldid;

    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'regime'));
    assert($fieldid != 0);
    $USERFIELDS['regime'] = $fieldid;
    
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'profilent'));
    assert($fieldid != 0);
    $USERFIELDS['profilent'] = $fieldid;
    
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'guident'));
    assert($fieldid != 0);
    $USERFIELDS['guident'] = $fieldid;

    return $USERFIELDS;
}

/**
 * an utility function that explores the ldap ENTEtablissement object list to get proper institution id
 *
 * @param object $ldapauth the ldap authentication instance
 * @param string $search the search pattern
 * @param array $searchby where to search, either 'name' or 'city'
 * @return an array of objects with institution ID and institution name
 */
function local_ent_installer_ldap_search_institution_id($ldapauth, $search, $searchby = 'name') {
    global $LDAPQUERYTRACE;

    $ldapconnection = $ldapauth->ldap_connect();

    $context = get_config('local_ent_installer', 'structure_context');

    if ($search != '*') {
        $search = '*'.$search.'*';
    }

    if ($searchby = 'name') {
        $filter = str_replace('%%SEARCH%%', '', get_config('local_ent_installer', 'structure_name_filter'));

        // Just for tests.
        if (empty($filter)) {
            $filter = '(&(objectClass=ENTEtablissement)(ENTStructureNomCourant='.$search.'))';
        }
    } else {
        $filter = str_replace('%%SEARCH%%', '', get_config('local_ent_installer', 'structure_city_filter'));

        // Just for tests.
        if (empty($filter)) {
            $filter = '(&(objectClass=ENTEtablissement)(ENTEtablissementBassin='.$search.'))';
        }
    }

    $structureid = get_config('local_ent_installer', 'structure_id_attribute');

    // Just for tests.
    if (empty($structureid)) {
        $structureid = 'ENTStructureUAI';
    }

    $structurename = get_config('local_ent_installer', 'structure_name_attribute');

    // Just for tests.
    if (empty($structurename)) {
        $structurename = 'ENTStructureNomCourant';
    }

    list($usec, $sec) = explode(' ',microtime()); 
    $pretick = (float)$sec + (float)$usec;

    // Search only in this context.
    $ldap_result = @ldap_search($ldapconnection, $context, $filter, array($structureid, $structurename));
    list($usec, $sec) = explode(' ',microtime()); 
    $posttick = (float)$sec + (float)$usec;

    $LDAPQUERYTRACE = $posttick - $pretick. ' s. ('.$context.' '.$filter.' ['.$structureid.','.$structurename.'])';

    if (!$ldap_result) {
        return '';
    }

    $results = array();
    if ($entry = @ldap_first_entry($ldapconnection, $ldap_result)) {
        do {
            $institution = new StdClass();

            $value = ldap_get_values_len($ldapconnection, $entry, $structureid);
            $institution->id = core_text::convert($value[0], $ldapauth->config->ldapencoding, 'utf-8');

            $value = ldap_get_values_len($ldapconnection, $entry, $structurename);
            $institution->name = core_text::convert($value[0], $ldapauth->config->ldapencoding, 'utf-8');
            $results[] = $institution;
        } while ($entry = ldap_next_entry($ldapconnection, $entry));
    }
    unset($ldap_result); // Free mem.

    return $results;
}

/**
 * Reads user information from ldap and returns it in array()
 *
 * Function should return all information available. If you are saving
 * this information to moodle user-table you should honor syncronization flags
 *
 * @param object $ldapauth the ldap authentication instance
 * @param string $username username
 * @param array $options an array with CLI input options
 *
 * @return mixed array with no magic quotes or false on error
 */
function local_ent_installer_get_userinfo($ldapauth, $username, $options = array()) {
    static $entattributes;

    // Load some cached static data.
    if (!isset($entattributes)) {
        // aggregate additional ent specific attributes that hold interesting information
        $configattribs = get_config('local_ent_installer', 'ent_userinfo_attributes');
        if (empty($configattribs)) {
            $entattributes = array('ENTPersonFonctions','ENTPersonJointure', 'ENTEleveClasses', 'ENTEleveGroupes', 'ENTEleveTransport', 'ENTEleveRegime', 'ENTPersonProfils', 'objectGUID');
        } else {
            $entattributes = explode(',', $configattribs);
        }
    }

    $extusername = core_text::convert($username, 'utf-8', $ldapauth->config->ldapencoding);

    $ldapconnection = $ldapauth->ldap_connect();
    if (!($user_dn = $ldapauth->ldap_find_userdn($ldapconnection, $extusername))) {
        $ldapauth->ldap_close();
        return false;
    }

    $search_attribs = array();
    $attrmap = $ldapauth->ldap_attributes();
    foreach ($attrmap as $key => $values) {
        if (!is_array($values)) {
            $values = array($values);
        }
        foreach ($values as $value) {
            if (!in_array($value, $search_attribs)) {
                array_push($search_attribs, $value);
            }
        }
    }

    foreach ($entattributes as $value) {
        if (!in_array($value, $search_attribs)) {
            array_push($search_attribs, $value);
            // Add attributes to $attrmap so they are pulled down into final user object.
            $attrmap[$value] = strtolower($value);
        }
    }

    if ($options['verbose']) {
        mtrace("Getting $user_dn for ".implode(',', $search_attribs));
    }
    if (!$user_info_result = ldap_read($ldapconnection, $user_dn, '(objectClass=*)', $search_attribs)) {
        $ldapauth->ldap_close();
        return false;
    }

    $user_entry = ldap_get_entries_moodle($ldapconnection, $user_info_result);
    if (empty($user_entry)) {
        $ldapauth->ldap_close();
        return false; // Entry not found.
    }

    $result = array();
    foreach ($attrmap as $key => $values) {
        if (!is_array($values)) {
            $values = array($values);
        }
        $ldapval = NULL;
        foreach ($values as $value) {
            $entry = array_change_key_case($user_entry[0], CASE_LOWER);

            if (($value == 'dn') || ($value == 'distinguishedname')) {
                $result[$key] = $user_dn;
                continue;
            }

            if (!array_key_exists($value, $entry)) {
                if ($options['verbose']){
                    mtrace("Requested value $value but missing in record");
                }
                continue; // wrong data mapping!
            }

            if ($value == 'objectguid') 
            {
                if(strlen($entry[$value][0])==16) {
                    $tmp = bin2hex($entry[$value][0]);
                    $t = $tmp[6] .$tmp[7] .$tmp[4] .$tmp[5] .$tmp[2] .$tmp[3] .$tmp[0] .$tmp[1] .'-';
                    $t.= $tmp[10].$tmp[11].$tmp[8] .$tmp[9] .'-';
                    $t.= $tmp[14].$tmp[15].$tmp[12].$tmp[13] .'-';
                    $t.= substr($tmp,16,4) .'-';
                    $t.= substr($tmp,20);
                    $objectguid = $t;
                }
                $newval = $objectguid;
            } 
            else if ($value == 'entelevegroupes' && is_array($entry[$value])) {
                $newval = array();
                foreach($entry[$value] as $subkey => $subvalue) {
                    if($subkey !== 'count') {
                        $newval[] = core_text::convert($subvalue, $ldapauth->config->ldapencoding, 'utf-8');
                    }
                }
            } 
            else if (is_array($entry[$value])) {
                $newval = core_text::convert($entry[$value][0], $ldapauth->config->ldapencoding, 'utf-8');
            } 
            else {
                $newval = core_text::convert($entry[$value], $ldapauth->config->ldapencoding, 'utf-8');
            }

            if (!empty($newval)) { // Favour ldap entries that are set.
                $ldapval = $newval;
            }
        }
        if (!is_null($ldapval)) {
            $result[$key] = $ldapval;
        }
    }

    $ldapauth->ldap_close();
    return $result;
}

/**
 * Reads user information from ldap and returns it in an object
 *
 * @param object $ldapauth the ldap authentication instance
 * @param string $username username (with system magic quotes)
 * @param array $options an array with CLI input options
 * @return mixed object or false on error
 */
function local_ent_installer_get_userinfo_asobj($ldapauth, $username, $options = array()) {

    $user_array = local_ent_installer_get_userinfo($ldapauth, $username, $options);

    if ($user_array == false) {
        return false; //error or not found
    }

    $user_array = truncate_userinfo($user_array);
    $user = new stdClass();
    foreach ($user_array as $key => $value) {
        $user->{$key} = $value;
    }
    return $user;
}

/**
 * add user to cohort after creating cohort if missing and removing to eventual 
 * other cohort.
 * Cohorts are handled in the 'local_ent_installer' component scope and will NOT interfere
 * with locally manually created cohorts.
 * Old cohorts from a preceding session might be protected by switching their component
 * scope to somethin else than 'local_ent_installer'. This will help keeping students from preceding
 * sessions in those historical cohorts.
 * @param int $userid the user id
 * @param string $cohortidentifier a fully qualified cohort name (SDET compliant)
 * @param bool $unenroll take user off from other cohorts enrolments (default true)
 *
 * return cohort short name
 */
function local_ent_installer_check_cohort($userid, $cohortidentifier, $unenroll = true, $verbose = false) {
    global $DB;
    
    if(strpos($cohortidentifier, '$')) {
        list($fooinstitutionid, $cohortname) = explode('$', $cohortidentifier);
    }
    else {
        $cohortname = $cohortidentifier;
    }
    $institutionid = get_config('local_ent_installer', 'institution_id'); // nicer form
    $idnumber = $institutionid.'$'.$cohortname;

    $now = time();
    // If we have an explicit cohort prefix for the course session, add it to identifyng fields.
    $cohortix = get_config('local_ent_installer', 'cohort_ix');
    if (!empty($cohortix)) {
        $cohortname = $cohortix.'_'.$cohortname;
        $idnumber = $cohortix.'_'.$institutionid.'$'.$cohortname;
    }

    if (!$cohortid = $DB->get_field('cohort', 'id', array('name' => $cohortname))) {

        $systemcontext = context_system::instance();
        $cohort = new StdClass();
        $cohort->name = $cohortname;
        $cohort->contextid = $systemcontext->id;
        $cohort->idnumber = $idnumber;
        $cohort->description = '';
        $cohort->descriptionformat = 0;
        $cohort->component = 'local_ent_installer';
        $cohort->timecreated = $now;
        $cohort->timemodified = $now;
        $cohortid = $DB->insert_record('cohort', $cohort);
    }

    if($unenroll) {
        $sql = "DELETE FROM {cohort_members} WHERE userid = ? AND cohortid IN (SELECT id FROM {cohort} WHERE component = 'local_ent_installer')";
        $DB->execute($sql, array($userid));
    }
    else if ($timeadded = $DB->get_field('cohort_members', 'timeadded', array('userid' => $userid, 'cohortid' => $cohortid))) {
        if ($verbose) {
            mtrace("user with id $userid already added in cohort $idnumber (id : $cohortid - date : $timeadded)");
        }
        return $cohortname;   
    }

    $membership = new StdClass();
    $membership->cohortid = $cohortid;
    $membership->userid = $userid;
    $membership->timeadded = $now;
    
    // TODO : Reinforce weird cases of collisions with old cohorts if cohort prefix accidentally not set
    $DB->insert_record('cohort_members', $membership);

    return $cohortname;
}

function local_ent_installer_update_info_data($userid, $fieldid, $data) {
    global $DB;
    
    if(!isset($userid)) {
        mtrace('local_ent_installer_update_info_data : parameter -userid- is null !');
        return;
    }
    if(!isset($fieldid)) {
        mtrace('local_ent_installer_update_info_data : parameter -fieldid- is null !');
        return;
    }
    
    if (!$oldrec = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $fieldid))) {
        $userinfodata = new StdClass;
        $userinfodata->fieldid = $fieldid;
        $userinfodata->userid = $userid;
        $userinfodata->data = ''.$data; // protect against null fields
        $DB->insert_record('user_info_data', $userinfodata);
    } else {
        $oldrec->data = ''.$data;
        $DB->update_record('user_info_data', $oldrec);
    }
}

/**
 * make a course category for the teacher and give full control to it
 *
 *
 */
function local_ent_installer_make_teacher_category($user, $unassignteachercategoryrole = null) {
    global $DB, $CFG;

    require_once $CFG->dirroot.'/course/lib.php';
    require_once($CFG->dirroot.'/lib/coursecatlib.php');

    try {
        $institutionid = get_config('local_ent_installer', 'institution_id');
        $teacherstubcategory  = get_config('local_ent_installer', 'teacher_stub_category');

        if (!$teacherstubcategory) {
            mtrace("No stub");
            return;
        }
        $teachercategoryrole = $DB->get_record('role', array('shortname' => ENT_TEACHER_CATEGORY_ROLE));
        $teachercatidnum = $institutionid.'$'.$user->idnumber.'$CAT';
        $existingcategory = $DB->get_record('course_categories', array('idnumber' => $teachercatidnum));
        
        if ($existingcategory)
            $categorycontext = $DB->get_record('context', array('contextlevel'=>CONTEXT_COURSECAT, 'instanceid'=>$existingcategory->id));
        
        if (!empty($unassignteachercategoryrole)) {
            $rolestounassign = explode(',', $unassignteachercategoryrole);
            foreach($rolestounassign as $myrole) {
                $roletounassign = $DB->get_record('role', array('shortname' => $myrole));
                if(isset($roletounassign))
                    role_unassign($roletounassign->id, $user->id, $categorycontext->id);
            }
        }

        if(isset($categorycontext)){
            $roleassignedtoteachercategory = $DB->get_records('role_assignments', array('roleid'=>$teachercategoryrole->id, 'contextid'=>$categorycontext->id, 'userid'=>$user->id, 'component'=>"", 'itemid'=>0), 'id');
            
            if (!$roleassignedtoteachercategory) {
                role_assign($teachercategoryrole->id, $user->id, $categorycontext->id);
            }
        }
        else {
            if (!$existingcategory) {
                $newcategory = new StdClass();
                $newcategory->name = fullname($user);
                $newcategory->idnumber = $teachercatidnum;
                $newcategory->parent = $teacherstubcategory;
                $newcategory->visible = 1;
                
                $newcategory = coursecat::create($newcategory);
                fix_course_sortorder();
                $categorycontext = context_coursecat::instance($newcategory->id);
            }
            // Category exists with no context, might not happen clean up this
            else {
                context_helper::create_instances(CONTEXT_COURSECAT, true);
                $categorycontext = context_coursecat::instance($existingcategory->id);
            }
            role_assign($teachercategoryrole->id, $user->id, $categorycontext->id);
        }
    }
    catch (Exception $e) {
        mtrace("ERROR : exception into make_teacher_category"); 
        mtrace('ERROR MSG : '.$e->getMessage());
        if ($options['verbose']) {
            mtrace(' ###### ');
            mtrace("ERROR FILE : {$e->getFile()} : {$e->getLine()}");
            mtrace('ERROR TRACE : '.$e->getTraceAsString());
            mtrace(' ###### ');
        }
    }
}

/**
 * to remove diacritics
 *
 */
function toASCII($str) {
    return strtr(utf8_decode($str), 
        utf8_decode(
        'ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ'),
        'SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');
}

function trace_debug($ex) {
    global $DB;

    mtrace(' ###### ');
    mtrace('ERROR MSG : '.$ex->getMessage());
    mtrace("ERROR FILE : {$ex->getFile()} : {$ex->getLine()}");
    mtrace('ERROR TRACE : '.$ex->getTraceAsString());
    mtrace(' ###### ');
    mtrace('DML LAST ERROR : '.$DB->get_last_error());
    mtrace(' ###### ');
}
