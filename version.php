<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful, but WITHOUT
// ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
// FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
// for more details. You should have received a copy of the GNU GPL along
// with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Version metadata for local_aiconnecthvp (H5P Pro tool-set).
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'local_aiconnecthvp';
$plugin->version      = 2026111000;
$plugin->requires     = 2023100900;
$plugin->maturity     = MATURITY_STABLE;
$plugin->release      = '1.0.1';
$plugin->dependencies = ['local_aiconnect' => 2026070300];
