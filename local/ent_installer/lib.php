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

/**
 * this function schedules the user synchronisation updates
 *
 * Implementation specific : Generic
 */
function local_ent_installer_cron() {
    global $CFG;

    if (!get_config('local_ent_installer', 'cron_enable')) {
        return;
    }

    $now = time();
    $needscron = false;

    $chour = 0 + get_config('local_ent_installer', 'cron_hour');
    $cmin = 0 + get_config('local_ent_installer', 'cron_min');
    $cfreq = get_config('local_ent_installer', 'cron_enable');

    $now = time();
    $nowdt = getdate($now);
    $expectedtime = get_config('local_ent_installer', 'last_sync_date') + $cfreq - HOURSEC;

    $crondebug = optional_param('crondebug', false, PARAM_BOOL);

    if ($now < $expectedtime && !$crondebug) {
        return;
    }

    if (!empty($CFG->ent_installer_running)) {
        return;
    }

    if ((($nowdt['hours'] * 60 + $nowdt['minutes']) >= ($chour * 60 + $cmin)) || $crondebug) {
        set_config('ent_installer_running', 1);
        set_config('last_sync_date', $now, 'local_ent_installer');

        // Get ldap params from real ldap plugin.
        $ldapauth = get_auth_plugin('ldap');
    
        $options = array('host' => $CFG->wwwroot);
    
        // Run the customised synchro.
        local_ent_installer_sync_users($ldapauth, $options);

        set_config('ent_installer_running', null);
    } else {
        mtrace('waiting for valid time ');
    }
}