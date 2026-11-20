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
 * Language strings for local_aiconnecthvp (Hebrew). Missing strings fall back to English.
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['confirm_deleteattempt'] = 'למחוק את הניסיון של {$a->user} ב-"{$a->name}"';
$string['confirm_deleteattempt_detail'] = 'הניסיון (ציון {$a->score}) יימחק והציון יחושב מחדש.';
$string['confirm_deletecb'] = 'למחוק את הפריט "{$a->name}" ממאגר התוכן';
$string['confirm_deletecb_detail'] = 'הוא משמש ב-{$a->uses} מקומות; הם יפסיקו להציג אותו.';
$string['confirm_deletecontent'] = 'למחוק את פעילות ה-H5P "{$a->name}" מהקורס "{$a->course}"';
$string['confirm_deletecontent_detail'] = '{$a->attempts} הניסיונות שלה והציונים שלהם יימחקו.';
$string['confirm_deletelibrary'] = 'למחוק את ספריית ה-H5P שאינה בשימוש {$a}';
$string['confirm_deletelibrary_detail'] = 'לא יהיה אפשר להשתמש בסוג התוכן הזה באתר עד שיותקן שוב.';
$string['confirm_resetattempts_all'] = 'למחוק את כל {$a->count} הניסיונות של כל המשתמשים ב-"{$a->name}"';
$string['confirm_resetattempts_detail'] = 'הציונים יאופסו.';
$string['confirm_resetattempts_user'] = 'למחוק את {$a->count} הניסיונות של {$a->user} ב-"{$a->name}"';
$string['pluginname'] = 'AI Connect H5P (Pro)';
