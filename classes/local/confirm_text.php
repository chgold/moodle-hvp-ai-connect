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
 * The sentences a user reads before confirming a destructive tool (user-confirm-v1).
 *
 * One entry per gated tool: the language strings its confirmation may use. The
 * preview methods of the module build the arguments (real names, numbers, what is
 * lost) after the tool's own permission checks; the text itself lives in
 * lang/{en,he}/local_aiconnecthvp.php and is shown in the user's language.
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiconnecthvp\local;

/**
 * Confirmation texts (summary + details) per gated tool.
 */
class confirm_text {
    /** @var string Component that owns the strings. */
    public const COMPONENT = 'local_aiconnecthvp';

    /** @var array<string, array<int, string>> Gated tool => language strings it may use. */
    public const TEXTS = [
        'deleteContent' => ['confirm_deletecontent', 'confirm_deletecontent_detail'],
        'deleteContentBankItem' => ['confirm_deletecb', 'confirm_deletecb_detail'],
        'deleteLibrary' => ['confirm_deletelibrary', 'confirm_deletelibrary_detail'],
        'deleteUserAttempt' => ['confirm_deleteattempt', 'confirm_deleteattempt_detail'],
        'resetAllAttempts' => ['confirm_resetattempts_all', 'confirm_resetattempts_detail', 'confirm_resetattempts_user'],
    ];

    /**
     * The one sentence the user reads for a tool.
     *
     * @param string $tool Tool short name (must be listed in TEXTS).
     * @param string $key  Language string of that tool.
     * @param mixed  $a    Placeholders.
     * @return string
     */
    public static function summary(string $tool, string $key, $a = null): string {
        if (!in_array($key, self::TEXTS[$tool] ?? [], true)) {
            throw new \coding_exception("No confirmation text {$key} for {$tool}");
        }
        return get_string($key, self::COMPONENT, $a);
    }

    /**
     * A detail line ("what will happen").
     *
     * @param string $key Language string.
     * @param mixed  $a   Placeholders.
     * @return string
     */
    public static function line(string $key, $a = null): string {
        return get_string($key, self::COMPONENT, $a);
    }

    /**
     * Build summary + details (used by tests and by callers that need both).
     *
     * @param string $tool Tool short name.
     * @param mixed  $a    Placeholders.
     * @return array{summary: string, details: array<int, string>}
     */
    public static function build(string $tool, $a = null): array {
        $keys = self::TEXTS[$tool] ?? [];
        if (!$keys) {
            throw new \coding_exception("No confirmation text for {$tool}");
        }
        $details = [];
        foreach (array_slice($keys, 1) as $k) {
            if (substr($k, -7) === '_detail') {
                $details[] = get_string($k, self::COMPONENT, $a);
            }
        }
        return ['summary' => get_string($keys[0], self::COMPONENT, $a), 'details' => $details];
    }
}
