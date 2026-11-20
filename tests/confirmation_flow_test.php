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
 * Destructive tools of local_aiconnecthvp declare the core user confirmation flow (user-confirm-v1).
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiconnecthvp;

use local_aiconnecthvp\module\pro_module;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_aiconnecthvp\module\pro_module
 */
final class confirmation_flow_test extends \advanced_testcase {
    public function test_gated_tools_declare_confirmation(): void {
        $this->resetAfterTest();
        $tools = (new pro_module())->get_registered_tools();
        $this->assertNotEmpty(pro_module::GATED_TOOLS);
        foreach (pro_module::GATED_TOOLS as $name) {
            $this->assertArrayHasKey($name, $tools, $name);
            $this->assertNotEmpty($tools[$name]['confirmation'] ?? null, $name);
            $this->assertSame('destructive', $tools[$name]['confirmation']['risk'], $name);
            $props = (array) ($tools[$name]['input_schema']['properties'] ?? []);
            $this->assertArrayNotHasKey('confirm', $props, $name . ': the agent-side confirm flag is gone');
            $this->assertTrue(method_exists(pro_module::class, (string) $tools[$name]['confirmation']['preview']), $name);
        }
        foreach ($tools as $name => $t) {
            if (!in_array($name, pro_module::GATED_TOOLS, true)) {
                $this->assertArrayNotHasKey('confirmation', $t, $name);
            }
        }
    }

    public function test_every_gated_tool_has_a_translated_summary(): void {
        $this->resetAfterTest();
        $dir = dirname(__DIR__) . '/lang';
        $en = self::lang_file($dir . '/en/local_aiconnecthvp.php');
        $he = self::lang_file($dir . '/he/local_aiconnecthvp.php');
        $a = (object) ['name' => 'N', 'user' => 'U', 'role' => 'R', 'where' => 'W', 'course' => 'C', 'group' => 'G',
            'room' => 'R', 'who' => 'W', 'count' => 2, 'grade' => 5, 'recordings' => 1, 'logs' => 1, 'attempts' => 1,
            'uses' => 1, 'score' => '1/2', 'cohort' => 'K', 'target' => 'T', 'old' => 'o', 'new' => 'n', 'value' => 'v'];
        foreach (\local_aiconnecthvp\module\pro_module::GATED_TOOLS as $tool) {
            $keys = \local_aiconnecthvp\local\confirm_text::TEXTS[$tool] ?? [];
            $this->assertNotEmpty($keys, $tool);
            foreach ($keys as $k) {
                $this->assertArrayHasKey($k, $en, $tool . ' en ' . $k);
                $this->assertArrayHasKey($k, $he, $tool . ' he ' . $k);
                $this->assertNotSame($en[$k], $he[$k], $tool . ' he is translated ' . $k);
            }
            $built = \local_aiconnecthvp\local\confirm_text::build($tool, $a);
            $this->assertNotSame('', trim($built['summary']), $tool);
            $this->assertDoesNotMatchRegularExpression('/confirmation_id|Not executed|Do NOT|confirm=true/i', $built['summary']);
        }
    }

    /**
     * Strings of one language file.
     *
     * @param string $file Path.
     * @return array
     */
    private static function lang_file(string $file): array {
        $string = [];
        include($file);
        return $string;
    }
}
