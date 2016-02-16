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

// This allows 2 minutes synchronisation before trigerring an overtime.
define('OVERTIME_THRESHOLD', 120);

/**
* get strings from a special install file, whatever
* moodle active language is on
* @return the string or the marked key if missing
*
*/
function ent_installer_string($stringkey) {
    global $CFG;
    static $installstrings = null;

    if (empty($installstrings)) {
        require_once $CFG->dirroot.'/local/ent_installer/db/install_strings.php';
        $installstrings = $string; // Loads string array once.
    }

    if (!array_key_exists($stringkey, $installstrings)) {
        return "[[install::$stringkey]]";
    }
    return $installstrings[$stringkey];
}

function local_ent_installer_generate_email($user) {
    global $CFG;

    $fullname = strtolower($user->firstname.'.'.$user->lastname);
    $fakedomain = get_config('local_ent_installer', 'fake_email_domain');

    if (empty($fakedomain)) {
        $fakedomain = 'foomail.com';
    }

    return $fullname.'@'.$fakedomain;
}

function ent_installer_check_jquery() {
    global $PAGE, $OUTPUT, $JQUERYVERSION;

    $current = '1.8.2';

    if (empty($JQUERYVERSION)) {
        $JQUERYVERSION = '1.8.2';
        $PAGE->requires->js('/local/ent_installer/js/jquery-'.$current.'.min.js', true);
    } else {
        if ($JQUERYVERSION < $current) {
            debugging('the previously loaded version of jquery is lower than required. This may cause issues to tracker reports. Programmers might consider upgrading JQuery version in the component that preloads JQuery library.', DEBUG_DEVELOPER, array('notrace'));
        }
    }
}
