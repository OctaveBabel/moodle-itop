<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 2 of the License, or
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
 * Native sqlsrv class representing moodle database interface.
 *
 * @package    core_dml
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/sqlsrv_native_moodle_database.php');
require_once(__DIR__.'/sqlsrv_native_moodle_recordset.php');
require_once(__DIR__.'/sqlsrv_native_moodle_temptables.php');

/**
 * Native sqlsrv class representing moodle database interface.
 *
 * @package    core_dml
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */
class sqlsrv_itop_moodle_database extends sqlsrv_native_moodle_database {

    /**
     * Constructor - instantiates the database, specifying if it's external (connect to other systems) or no (Moodle DB)
     *              note this has effect to decide if prefix checks must be performed or no
     * @param bool true means external database used
     */
    public function __construct($external=false) {
        parent::__construct($external);
    }

    public function drop_database($dbname) {
        return $this->do_query("DROP DATABASE [$dbname]", null, SQL_QUERY_STRUCTURE);
    }

    public function create_database($dbhost=null, $dbuser=null, $dbpass=null, $dbname, array $dboptions=null) {
        return $this->do_query("CREATE DATABASE [$dbname]", null, SQL_QUERY_STRUCTURE);
    }

    public function execute_query($sql, $cursortype = SQLSRV_CURSOR_FORWARD) {
        $sqldirective = strtoupper(substr($sql, 0, strpos($sql, ' ')));
        
        switch($sqldirective) {
            case 'SELECT';
                $type = SQL_QUERY_SELECT;
                break;
            case 'INSERT';
                $type = SQL_QUERY_INSERT;
                break;
            case 'UPDATE';
                $type = SQL_QUERY_UPDATE;
                break;
            case 'DELETE';
                $type = SQL_QUERY_UPDATE;
                break;
            default:
                print_error('unknownsqldirective', 'block_vmoodle');
                return false;
        }
        $this->query_start($sql, null, $type);
        $result = sqlsrv_query($this->sqlsrv, $sql, null, array('Scrollable' => $cursortype));
        $this->query_end($result);
        return $result;
    }

    public function get_all_results_array($result) {
        $return_array = array();
        while($tab = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $return_array[] = $tab;
        }
        return $return_array;
    }

    public function get_num_rows($result) {
        return sqlsrv_num_rows($result);
    }

    public function get_last_request_type() {
        return $this->last_type; 
    }
}
