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
 * Version details
 *
 * @package    block_vmoodle
 * @category blocks
 * @copyright  2015 Gabriel Rosset (gabriel.rosset@gmail.com)
 * @copyright  2013 Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2015102700;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2014111001;        // Requires Moodle version 2.4
$plugin->component = 'block_vmoodle'; // Full name of the plugin (used for diagnostics)
$plugin->maturity = MATURITY_RC;
$plugin->release = "2.9 (Build 20151023)";
$plugin->dependencies = array('local_vmoodle' => 2015062000);