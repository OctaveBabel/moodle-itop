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

function user_mnet_hosts_make_accesskey($wwwroot, $full = false) {

    $accesskey = preg_replace('/https?:\/\//', '', $wwwroot);
    $accesskey = str_replace('-', '', $accesskey);
    $accesskeyparts = explode('.', $accesskey);
    if (count($accesskeyparts) > 2) {
        array_pop($accesskeyparts);    // remove ext
        array_pop($accesskeyparts);    // remove main domain
    }

    $accesstoken = strtoupper(implode('', $accesskeyparts));

    if ($full) {
        return 'access'.$accesstoken;
    }

    return $accesstoken;
}

/**
 * set or unset an access to some peer
 * @param int $userid
 * @param bool $access true or false
 * @param string $wwwroot optional host root to give acces to. the access filed name is computed from the wwwroot url
 */
function user_mnet_hosts_set_access($userid, $access, $wwwroot = null) {
    global $CFG, $DB;

    if (!$wwwroot) {
        $wwwroot = $CFG->wwwroot;
    }

    $accesskey = user_mnet_hosts_make_accesskey($wwwroot, true);

    if (!$field = $DB->get_record('user_info_field', array('shortname' => $accesskey))) {
        // Try to setup install if results not having done before.
        if ($wwwroot == $CFG->wwwroot) {
            require_once($CFG->dirroot.'/blocks/user_mnet_hosts/db/install.php');
            xmldb_block_user_mnet_hosts_install();
            mtrace("Second chance $accesskey ");
            $field = $DB->get_record('user_info_field', array('shortname' => $accesskey));
        } else {
            return false;
        }
    }

    if ($data = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $field->id))) {
        $data->data = $access;
        $DB->update_record('user_info_data', $data);
    } else {
        $data = new StdClass();
        $data->userid = $userid;
        $data->fieldid = $field->id;
        $data->data = $access;
        $DB->insert_record('user_info_data', $data);
    }
}

function user_mnet_hosts_get_hosts() {
    global $DB, $CFG;

    // get the hosts and whether we are doing SSO with them
    $sql = "
        SELECT DISTINCT 
            h.id, 
            h.name,
            h.wwwroot,
            a.name as application,
            a.display_name
        FROM 
            {mnet_host} h,
            {mnet_application} a,
            {mnet_host2service} h2s_IDP,
            {mnet_service} s_IDP,
            {mnet_host2service} h2s_SP,
            {mnet_service} s_SP
        WHERE
            h.id != ? AND
            h.id = h2s_IDP.hostid AND
            h.deleted = 0 AND
            h.applicationid = a.id AND
            h2s_IDP.serviceid = s_IDP.id AND
            s_IDP.name = 'sso_idp' AND
            h2s_IDP.publish = '1' AND
            h.id = h2s_SP.hostid AND
            h2s_SP.serviceid = s_SP.id AND
            s_SP.name = 'sso_idp' AND
            h2s_SP.publish = '1'
        ORDER BY
             a.display_name,
             h.name";

    $hosts = $DB->get_records_sql($sql, array($CFG->mnet_localhost_id));
    return $hosts;
}

function user_mnet_hosts_get_access_fields() {
    global $DB, $USER;

    // if mnet access profile does not exist, setup profile
    if (!$DB->get_records_select('user_info_field', " name LIKE 'access%' ")) {
       // TODO : Initialize mnetaccess profile data
    }

    // get user profile fields for access to hosts
    $sql = "
        SELECT
            uif.shortname,
            data
        FROM
            {user_info_data} uid,
            {user_info_field} uif
        WHERE
            uid.userid = ? AND
            uid.fieldid = uif.id AND
            uif.shortname LIKE 'access%'
    ";

    $mnet_accesses = array();

    if ($usermnetaccessfields = $DB->get_records_sql_menu($sql, array($USER->id))) {
        foreach($usermnetaccessfields as $key => $datum) {
            $key = str_replace('access', '', $key);
            $mnet_accesses[str_replace('-', '', strtolower($key))] = str_replace('-', '', $datum);
        }
    }

    return $mnet_accesses;
}