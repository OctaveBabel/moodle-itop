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
 * function.php
 * 
 * Group list of functions referred by Moodle core patched components
 *
 * @package    local_ent_installer
 * @category   local
 * @copyright  2015 Gabriel Rosset (gabriel.rosset@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Route teachers to their category if none is specified
 * Files fixed : 
 * course/index.php
 * 
 * @return bool Request has been marked for rerouting
 */
function ent_installer_route_teacher_category() {
    global $CFG, $DB, $USER;
    
    // Exit if a category is already requested
    $categoryid = optional_param('categoryid', 0, PARAM_INT); 
    if ($categoryid) {
        return false;
    }

    require_once($CFG->dirroot.'/user/profile/lib.php');
    $myuser = $DB->get_record('user', array('id' => $USER->id));
    profile_load_data($myuser);
    
    // Exit if current user is not a teacher
    if(!isset($myuser->profile_field_enseignant) || !$myuser->profile_field_enseignant) {
        return false;
    }

    $institutionid = get_config('local_ent_installer', 'institution_id');
    $teachercatidnum = $institutionid.'$'.$myuser->idnumber.'$CAT';
    $existingcategory = $DB->get_record('course_categories', array('idnumber' => $teachercatidnum));

    // Exit if category cannot be found
    if (!$existingcategory) {
        return false; 
    }
    
    //Let Moodle core course index trust that this category is requested
    $_GET['categoryid'] = $existingcategory->id;
    return true;     
}

/**
 * Replace 1 or more slashes or backslashes to 1 slash except both ones if present
 * Files fixed : 
 * lib/moodlelib.php
 *
 * @param string $path The path to strip
 * @return string The path with double slashes removed
 */
function ent_installer_clear_double_slashes ($path) {
    if (strlen($path) > 2 && (substr($path, 0, 2) == "\\\\" || substr($path, 0, 2) == "//")) {
        return substr($path, 0, 2).preg_replace('/(\/|\\\){1,}/', '/', substr($path, 2));
    }
    else {
        return preg_replace('/(\/|\\\){1,}/', '/', $path);
    }
}
