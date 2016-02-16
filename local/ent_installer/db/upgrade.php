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
 * This file keeps track of upgrades to the ltiprovider plugin
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_ent_installer_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014061600) {

        $table = new xmldb_table('local_ent_installer');

        // Adding fields to table tool_ent_installer stat information.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, 11, null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timerun', XMLDB_TYPE_INTEGER, 11, null, XMLDB_NOTNULL, null, 0);
        $table->add_field('added', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $table->add_field('updated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $table->add_field('inserterrors', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $table->add_field('updateerrors', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);

        // Adding keys to table tool_ent_installer.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for tool_ent_installer.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2014061600, 'local', 'ent_installer');
    }

    return true;
}
