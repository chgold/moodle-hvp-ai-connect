<?php
// This file is part of Moodle - http://moodle.org/ (GPL v3+). See LICENSE.

/**
 * Admin settings for local_aiconnecthvp.
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiconnecthvp', get_string('pluginname', 'local_aiconnecthvp'));
    $settings->add(new admin_setting_configtext('local_aiconnecthvp/license_key',
        get_string('license_key', 'local_aiconnecthvp'),
        get_string('license_key_desc', 'local_aiconnecthvp'), '', PARAM_RAW_TRIMMED));
    $ADMIN->add('localplugins', $settings);
}
