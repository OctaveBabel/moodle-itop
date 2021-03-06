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

$string['user_mnet_hosts:myaddinstance'] = 'Can add instance to my pages';
$string['user_mnet_hosts:addinstance'] = 'Can add instance';
$string['user_mnet_hosts:accessall'] = 'Can access all nodes';

$string['accesscategory'] = 'Authorized Platforms';
$string['accesscategory'] = 'Category name:';
$string['accesscategory_desc'] = 'Category name for access user fields';
$string['accessfieldname'] = 'acces{$a}';
$string['admincat'] = 'Network protocols';
$string['adminpage'] = 'Access Fields Refresh';
$string['admintitle'] = 'Setup Network Access Field Definitions';
$string['backsettings'] = 'Back to the settings page';
$string['configdisplaylimit'] = 'Set the max number of links that can be shown before asking to filter';
$string['configmaharapassthru'] = 'If enabled, all mnet activated users can pass to any registered Mahara site. If disabled, profile field base validation is active even for Mahara sites.';
$string['createdfields'] = 'Number of fields successfully created: ';
$string['displaylimit'] = 'Display limit';
$string['dosync'] = 'Synchronize access fields';
$string['errorlocaladminconstrainted'] = 'A local administrator of a virtual moodle cannot roam to other nodes';
$string['errormnetauthdisabled'] = 'Mnet authentication plugin is not enabled';
$string['errornocapacitytologremote'] = 'You have no capability to login to remote hosts';
$string['failedfields'] = 'Number of failures: ';
$string['fieldkey'] = 'Field short name';
$string['fieldname'] = 'Access to platform ';
$string['filter'] = 'Filter';
$string['ignoredfields'] = 'Number of hosts ignored: ';
$string['maharapassthru'] = 'Mahara pass through';
$string['mnetaccess_description'] = 'If published to a remote Moodle, this will allow the remote moodle to ask for local access. If subcribed by moodle, this allows asking a remote moodle for access check.';
$string['mnetaccess_name'] = 'Mnet Access Service';
$string['mnetaccess_service_name'] = 'Mnet Access Service';
$string['nohostsforyou'] = 'No host you can reach';
$string['pluginname'] = 'InterMnet accesses';
$string['resync'] = 'Resync all fields';
$string['synchonizingaccesses'] = 'Synchronising access control fields to the network configuration';
$string['syncplatforms'] = 'If you have added or defined some new MNET peers, you may resynchronize the defintion of access fields so your users can see the new destinations in the "User Mnet Host" block';
$string['usefiltertoreduce'] = '... more hosts not shown. Use filter...';
$string['user_mnet_hosts'] = 'My Moodle hosts';

$string['resync_help'] = '
<h2>Controlled roaming in MNET</h2>
<h3>Resynchroniation of access control fields</h3>

<p>To ensure users can roam properly between nodes and be able to control
the accesses, each user needs to be marked in his profile for each accessible 
node in the network.</p>
<p>Those marks are custom profile fields that will be added to the general user profile. 
The format of those fields has to follow specified rules. This script will facilitate the
setup of required fields by exploring the accessible network.</p>
';

$string['single_full'] = 'Network access control';
$string['single_short'] = 'Synchronize peers access fields'; 
