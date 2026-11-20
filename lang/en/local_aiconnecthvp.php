<?php
// This file is part of Moodle - http://moodle.org/ (GPL v3+). See LICENSE.

/**
 * Strings for local_aiconnecthvp.
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'AI Connect H5P (Pro)';
$string['privacy:metadata'] = 'The AI Connect H5P plugin does not store any personal data; it exposes tool-calls that operate on existing Moodle H5P content.';
$string['license_key']      = 'License key';
$string['license_key_desc'] = 'Your Goldnat AI Connect license key. Purchase or manage at https://goldnat.ai/. Leave empty to disable Pro tools on this site.';

// User confirmation flow (user-confirm-v1).
$string['confirm_deleteattempt'] = 'Delete the attempt of {$a->user} in "{$a->name}"';
$string['confirm_deleteattempt_detail'] = 'The attempt (score {$a->score}) is removed and the grade is recalculated.';
$string['confirm_deletecb'] = 'Delete the content bank item "{$a->name}"';
$string['confirm_deletecb_detail'] = 'It is used in {$a->uses} places; they stop showing it.';
$string['confirm_deletecontent'] = 'Delete the H5P activity "{$a->name}" from the course "{$a->course}"';
$string['confirm_deletecontent_detail'] = 'Its {$a->attempts} attempts and their grades are removed.';
$string['confirm_deletelibrary'] = 'Delete the unused H5P library {$a}';
$string['confirm_deletelibrary_detail'] = 'The content type can no longer be used on this site until it is installed again.';
$string['confirm_resetattempts_all'] = 'Delete all {$a->count} attempts of all users in "{$a->name}"';
$string['confirm_resetattempts_detail'] = 'The grades are reset.';
$string['confirm_resetattempts_user'] = 'Delete the {$a->count} attempts of {$a->user} in "{$a->name}"';
