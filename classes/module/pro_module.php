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
 * H5P Pro tool-set (Moodle core H5P: mod_h5pactivity + core_h5p + content bank).
 *
 * Registered into the core registry by local_aiconnect\module\pro_loader only
 * when the site edition grants Pro. Every tool is capability-gated on
 * mod_h5pactivity (Capability-Aware Manifest, #803): absent from the manifest
 * and 424 on a direct call when the activity module is missing or disabled.
 *
 * API layer: every write wraps a native Moodle API — create_module /
 * update_moduleinfo / duplicate_module / course_delete_module /
 * set_coursemodule_visible / moveto_module for activities, h5pactivity grade
 * functions and mod_h5pactivity\local\attempt for attempts, core_h5p\api for
 * deployment and libraries, and the core_contentbank external functions for
 * the content bank. Permission checks are Moodle's own capabilities for the
 * acting user (the session user is the token's user).
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiconnecthvp\module;

use local_aiconnect\module\module_base;

defined('MOODLE_INTERNAL') || die();

/**
 * H5P Pro tools.
 */
class pro_module extends module_base {
    /** @var string Tool namespace prefix. */
    protected string $module_name = 'hvp_pro';

    /** Capability key (Capability-Aware Manifest #803). */
    private const CAP = 'mod_h5pactivity';

    /** Grading methods of mod_h5pactivity (manager::GRADE*). */
    private const GRADEMETHODS = [0 => 'manual', 1 => 'highest', 2 => 'average', 3 => 'last', 4 => 'first'];

    /**
     * Register one tool with the common capability key.
     *
     * @param string $name Short name.
     * @param string $description Description for the agent.
     * @param array $required Required parameter names.
     * @param array $props JSON-schema properties.
     * @param string $scope read | write | admin.
     */
    private function tool(string $name, string $description, array $required, array $props, string $scope): void {
        $schema = ['type' => 'object', 'properties' => $props ?: new \stdClass()];
        if ($required) {
            $schema['required'] = $required;
        }
        $this->register_tool($name, [
            'description'         => $description,
            'input_schema'        => $schema,
            'required_scope'      => $scope,
            'requires_capability' => self::CAP,
        ]);
    }

    protected function register_tools(): void {
        $id = ['type' => 'integer', 'description' => 'H5P activity id (h5pactivity.id)'];
        $cbid = ['type' => 'integer', 'description' => 'Content bank item id (contentbank_content.id)'];
        $libid = ['type' => 'integer', 'description' => 'H5P library id (h5p_libraries.id)'];
        $confirm = ['type' => 'boolean', 'description' => 'Must be true to actually run; without it the tool only returns a preview'];

        // Activities.
        $this->tool('createContent', 'Create an H5P activity in a course (grade item, events and completion through Moodle\'s own create_module). '
            . 'Optionally attach a package from package_url or copy one from the content bank (contentbankid).', ['courseid', 'name'], [
            'courseid' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'intro' => ['type' => 'string'],
            'section' => ['type' => 'integer', 'description' => 'Section number (default 0)'],
            'grade' => ['type' => 'integer', 'description' => 'Max grade (default 100, 0 = no grade)'],
            'grademethod' => ['type' => 'string', 'description' => 'highest | average | last | first | manual (default highest)'],
            'visible' => ['type' => 'boolean'], 'package_url' => ['type' => 'string', 'description' => 'Absolute URL to an .h5p file'],
            'contentbankid' => $cbid,
        ], 'write');
        $this->tool(
            'generateContent',
            'Build an H5P package from a content type and its parameters (see getContentTypeSchema) and attach it '
            . 'to an existing H5P activity. The package is validated and deployed by Moodle\'s H5P core; errors are returned.',
            ['h5pactivityid', 'library', 'params'],
            [
            'h5pactivityid' => $id,
            'library' => ['type' => 'string', 'description' => 'Content type, e.g. "H5P.TrueFalse 1.8" or "H5P.MultiChoice"'],
            'params' => ['type' => 'object', 'description' => 'Content parameters (content.json) following the library semantics'],
            'title' => ['type' => 'string'],
            ],
            'write'
        );
        $this->tool(
            'updateContent',
            'Update an H5P activity: name, description, attempt tracking and review mode (update_moduleinfo).',
            ['h5pactivityid'],
            [
            'h5pactivityid' => $id, 'name' => ['type' => 'string'], 'intro' => ['type' => 'string'],
            'enabletracking' => ['type' => 'boolean'], 'reviewmode' => ['type' => 'integer', 'description' => '0 = no review, 1 = learners review own attempts'],
            ],
            'write'
        );
        $this->tool(
            'updateGradingSettings',
            'Change the maximum grade and grading method of an H5P activity; the gradebook is updated and regraded.',
            ['h5pactivityid'],
            [
            'h5pactivityid' => $id, 'grade' => ['type' => 'integer', 'description' => 'Max grade (0 = no grade)'],
            'grademethod' => ['type' => 'string', 'description' => 'highest | average | last | first | manual'],
            ],
            'write'
        );
        $this->tool(
            'updateDisplayOptions',
            'Set the H5P display buttons of an activity: action bar (frame), download, embed, copyright.',
            ['h5pactivityid'],
            [
            'h5pactivityid' => $id, 'frame' => ['type' => 'boolean'], 'export' => ['type' => 'boolean'],
            'embed' => ['type' => 'boolean'], 'copyright' => ['type' => 'boolean'],
            ],
            'write'
        );
        $this->tool(
            'duplicateContent',
            'Duplicate an H5P activity (Moodle backup/restore of the module, package included), optionally into another section.',
            ['h5pactivityid'],
            ['h5pactivityid' => $id, 'section' => ['type' => 'integer', 'description' => 'Target section number'],
            'name' => ['type' => 'string']],
            'write'
        );
        $this->tool('importContent', 'Attach a .h5p package from a URL to an H5P activity and deploy it.', ['h5pactivityid', 'package_url'], [
            'h5pactivityid' => $id, 'package_url' => ['type' => 'string', 'description' => 'Absolute URL to an .h5p file'],
        ], 'write');
        $this->tool('updateContentVisibility', 'Show or hide an H5P activity for students.', ['h5pactivityid', 'visible'], [
            'h5pactivityid' => $id, 'visible' => ['type' => 'boolean'],
        ], 'write');
        $this->tool('moveContentToSection', 'Move an H5P activity to another section of its course.', ['h5pactivityid', 'section'], [
            'h5pactivityid' => $id, 'section' => ['type' => 'integer', 'description' => 'Section number'],
        ], 'write');
        $this->tool(
            'deleteContent',
            'Delete an H5P activity with its attempts and grades. Without confirm=true only a preview is returned.',
            ['h5pactivityid'],
            ['h5pactivityid' => $id, 'confirm' => $confirm],
            'write'
        );

        // Attempts and reports.
        $this->tool('getActivityGradeReport', 'Teacher report of an H5P activity: every participant (group-aware) with attempts count and '
            . 'grade, plus who has not attempted yet. Needs mod/h5pactivity:reviewattempts.', ['h5pactivityid'], [
            'h5pactivityid' => $id, 'groupid' => ['type' => 'integer'],
        ], 'read');
        $this->tool(
            'deleteUserAttempt',
            'Delete one attempt of an H5P activity and regrade the user. Without confirm=true only a preview is returned.',
            ['h5pactivityid', 'attemptid'],
            ['h5pactivityid' => $id, 'attemptid' => ['type' => 'integer'], 'confirm' => $confirm],
            'write'
        );
        $this->tool(
            'resetAllAttempts',
            'Delete all attempts of an H5P activity (or of one user) and reset the grades. Without confirm=true only a preview is returned.',
            ['h5pactivityid'],
            ['h5pactivityid' => $id, 'userid' => ['type' => 'integer'], 'confirm' => $confirm],
            'write'
        );

        // Content bank.
        $this->tool(
            'listContentBankItems',
            'List H5P items of the content bank in a course (or category / system context) that the user may see.',
            [],
            ['courseid' => ['type' => 'integer'], 'contextid' => ['type' => 'integer'], 'search' => ['type' => 'string']],
            'read'
        );
        $this->tool(
            'createContentBankItem',
            'Add an H5P item to a course content bank, from an .h5p URL or generated from library + params.',
            ['courseid'],
            ['courseid' => ['type' => 'integer'], 'package_url' => ['type' => 'string'],
            'library' => ['type' => 'string'],
            'params' => ['type' => 'object'],
            'name' => ['type' => 'string']],
            'write'
        );
        $this->tool('renameContentBankItem', 'Rename a content bank item.', ['contentbankid', 'name'], [
            'contentbankid' => $cbid, 'name' => ['type' => 'string'],
        ], 'write');
        $this->tool('copyContentBankItem', 'Copy a content bank item (same context) under a new name.', ['contentbankid', 'name'], [
            'contentbankid' => $cbid, 'name' => ['type' => 'string'],
        ], 'write');
        $this->tool('setContentBankVisibility', 'Make a content bank item public or unlisted.', ['contentbankid', 'visibility'], [
            'contentbankid' => $cbid, 'visibility' => ['type' => 'string', 'description' => 'public | unlisted'],
        ], 'write');
        $this->tool(
            'deleteContentBankItem',
            'Delete a content bank item. Without confirm=true only a preview (incl. where it is used) is returned.',
            ['contentbankid'],
            ['contentbankid' => $cbid, 'confirm' => $confirm],
            'write'
        );

        // Content types and libraries.
        $this->tool(
            'getContentTypeSchema',
            'Return the semantics (parameter schema) of an installed H5P content type, needed to build params for generateContent.',
            ['library'],
            ['library' => ['type' => 'string', 'description' => 'e.g. "H5P.TrueFalse" or "H5P.TrueFalse 1.8"']],
            'read'
        );
        $this->tool(
            'listLibraries',
            'Site admin: list installed H5P libraries with version, enabled flag and how much content uses each.',
            [],
            ['runnable' => ['type' => 'boolean', 'description' => 'Only content types (default false = all)']],
            'read'
        );
        $this->tool(
            'getLibraryDetails',
            'Site admin: one H5P library with its dependencies, the libraries that depend on it and its content usage.',
            ['libraryid'],
            ['libraryid' => $libid],
            'read'
        );
        $this->tool('enableLibrary', 'Site admin: enable an H5P content type.', ['libraryid'], ['libraryid' => $libid], 'admin');
        $this->tool(
            'disableLibrary',
            'Site admin: disable an H5P content type (existing content of that type stops displaying).',
            ['libraryid'],
            ['libraryid' => $libid],
            'admin'
        );
        $this->tool('deleteLibrary', 'Site admin: delete an UNUSED H5P library (refused while content or other libraries use it). '
            . 'Without confirm=true only a preview is returned.', ['libraryid'], ['libraryid' => $libid, 'confirm' => $confirm], 'admin');
    }

    // ------------------------------------------------------------------
    // Helpers.
    // ------------------------------------------------------------------

    /**
     * Load an H5P activity with course, cm and context.
     *
     * @param int $id h5pactivity id.
     * @return array [instance, course, cm_info, context_module]
     */
    private function load_activity(int $id): array {
        global $DB;
        $instance = $DB->get_record('h5pactivity', ['id' => $id], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($instance, 'h5pactivity');
        return [$instance, $course, $cm, \context_module::instance($cm->id)];
    }

    /**
     * Load an activity the acting user may manage (moodle/course:manageactivities).
     *
     * @param int $id h5pactivity id.
     * @return array [instance, course, cm_info, context_module]
     */
    private function managed_activity(int $id): array {
        $loaded = $this->load_activity($id);
        require_capability('moodle/course:manageactivities', $loaded[3]);
        return $loaded;
    }

    /**
     * Update an activity through update_moduleinfo with the full current form data.
     *
     * @param \cm_info $cm Course module.
     * @param \stdClass $course Course.
     * @param callable $apply function(\stdClass $data): void — changes the form data.
     */
    private function update_activity(\cm_info $cm, \stdClass $course, callable $apply): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $cmrecord = get_coursemodule_from_id('h5pactivity', $cm->id, 0, false, MUST_EXIST);
        [$cmrecord, , , $data] = get_moduleinfo_data($cmrecord, $course);
        $apply($data);
        update_moduleinfo($cmrecord, $data, $course, null);
    }

    /**
     * Parse a grading method name.
     *
     * @param string $name Method name.
     * @return int manager::GRADE* constant.
     */
    private static function grademethod(string $name): int {
        $key = array_search(strtolower(trim($name)), self::GRADEMETHODS, true);
        if ($key === false) {
            throw new \invalid_parameter_exception('grademethod must be one of: ' . implode(', ', self::GRADEMETHODS));
        }
        return (int) $key;
    }

    /**
     * Resolve "H5P.TrueFalse 1.8" / "H5P.TrueFalse" to the newest matching installed runnable library.
     *
     * @param string $library Library string.
     * @return \stdClass h5p_libraries record.
     */
    private static function find_library(string $library): \stdClass {
        global $DB;
        if (!preg_match('/^\s*([A-Za-z0-9._-]+)(?:\s+(\d+)\.(\d+))?\s*$/', $library, $m)) {
            throw new \invalid_parameter_exception('library must look like "H5P.TrueFalse" or "H5P.TrueFalse 1.8"');
        }
        $params = ['name' => $m[1]];
        $where = 'machinename = :name';
        if (isset($m[2]) && $m[2] !== '') {
            $where .= ' AND majorversion = :major AND minorversion = :minor';
            $params['major'] = (int) $m[2];
            $params['minor'] = (int) $m[3];
        }
        $libs = $DB->get_records_select(
            'h5p_libraries',
            $where,
            $params,
            'majorversion DESC, minorversion DESC, patchversion DESC',
            '*',
            0,
            1
        );
        if (!$libs) {
            throw new \moodle_exception('invalidparameter', 'debug', '', null, "H5P library {$library} is not installed on this site");
        }
        return reset($libs);
    }

    /**
     * Build an .h5p package (ZIP) from a library and its parameters.
     *
     * @param \stdClass $lib h5p_libraries record (main library).
     * @param array $params content.json parameters.
     * @param string $title Content title.
     * @return string ZIP bytes.
     */
    private static function build_package(\stdClass $lib, array $params, string $title): string {
        $h5pjson = [
            'title' => $title,
            'language' => 'und',
            'mainLibrary' => $lib->machinename,
            'embedTypes' => ['iframe'],
            'license' => 'U',
            'preloadedDependencies' => [[
                'machineName' => $lib->machinename,
                'majorVersion' => (string) $lib->majorversion,
                'minorVersion' => (string) $lib->minorversion,
            ]],
        ];
        $tmp = make_request_directory() . '/generated.h5p';
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \moodle_exception('cannotcreatefile', 'error');
        }
        $zip->addFromString('h5p.json', json_encode($h5pjson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('content/content.json', json_encode($params ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    /**
     * Upload size limit for a course, as Moodle's own file picker applies it.
     *
     * @param int $courseid Course id.
     * @return int Max bytes (a 512 MB hard ceiling for users without a limit).
     */
    private function course_max_bytes(int $courseid): int {
        global $CFG, $USER;
        $course = get_course($courseid);
        $max = get_user_max_upload_file_size(\context_course::instance($courseid), $CFG->maxbytes, $course->maxbytes, 0, $USER->id);
        return ($max > 0) ? (int) $max : 512 * 1024 * 1024;
    }

    /**
     * Download an agent-supplied URL through Moodle's \curl (curl_security_helper
     * checks host/port on every redirect hop) with a timeout and a size cap, and
     * accept only an .h5p (ZIP) package.
     *
     * @param string $url Agent-supplied URL.
     * @param int $maxbytes Size cap.
     * @return array [string|null $contents, string|null $error]
     */
    private function fetch_package(string $url, int $maxbytes): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $securityhelper = new \core\files\curl_security_helper();
        if ($securityhelper->url_is_blocked($url)) {
            return [null, 'URL rejected: ' . $securityhelper->get_blocked_url_string()];
        }
        $tmp = make_request_directory() . '/download';
        $c = new \curl();
        $c->setopt([
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_CONNECTTIMEOUT' => 20,
            'CURLOPT_MAXFILESIZE' => $maxbytes,
            'CURLOPT_NOPROGRESS' => false,
            'CURLOPT_PROGRESSFUNCTION' => static fn($ch, $dltotal, $dlnow) => ($dlnow > $maxbytes) ? 1 : 0,
        ]);
        $ok = $c->download_one($url, null, ['filepath' => $tmp]);
        $code = (int) ($c->get_info()['http_code'] ?? 0);
        if (in_array((int) $c->get_errno(), [42, 63], true) || (is_file($tmp) && filesize($tmp) > $maxbytes)) {
            return [null, 'File exceeds the ' . display_size($maxbytes) . ' upload limit for this course'];
        }
        if ($ok !== true || $c->get_errno() || $code !== 200 || !is_file($tmp)) {
            return [null, 'Could not download the package from the URL (HTTP ' . $code . ($c->get_errno() ? ', ' . $c->error : '') . ')'];
        }
        if (filesize($tmp) > $maxbytes) {
            return [null, 'File exceeds the ' . display_size($maxbytes) . ' upload limit for this course'];
        }
        $contents = (string) file_get_contents($tmp);
        @unlink($tmp);
        // An .h5p package is a ZIP archive: check the magic bytes and the sniffed type.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (strncmp($contents, "PK\x03\x04", 4) !== 0 || !in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
            return [null, 'The URL did not return an .h5p (ZIP) package'];
        }
        return [$contents, null];
    }

    /**
     * Package filename from a URL path, always ending in .h5p.
     *
     * @param string $url URL.
     * @return string File name.
     */
    private static function package_filename(string $url): string {
        $filename = clean_param(basename(parse_url($url, PHP_URL_PATH) ?: 'content.h5p'), PARAM_FILE) ?: 'content.h5p';
        return (substr($filename, -4) === '.h5p') ? $filename : $filename . '.h5p';
    }

    /**
     * Replace the package of an activity and deploy it with Moodle's H5P core.
     *
     * @param \stdClass $instance h5pactivity record.
     * @param \context_module $ctx Module context.
     * @param string $filename Package file name.
     * @param string $contents ZIP bytes.
     * @return array Deployment result.
     */
    private function attach_and_deploy(\stdClass $instance, \context_module $ctx, string $filename, string $contents): array {
        global $USER;
        $fs = get_file_storage();
        $fs->delete_area_files($ctx->id, 'mod_h5pactivity', 'package', 0);
        $file = $fs->create_file_from_string([
            'contextid' => $ctx->id, 'component' => 'mod_h5pactivity', 'filearea' => 'package',
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename, 'userid' => $USER->id,
        ], $contents);
        return ['filename' => $filename] + $this->deploy($file, (int) $instance->displayoptions);
    }

    /**
     * Validate + deploy an H5P file (core_h5p\api::create_content_from_pluginfile_url).
     *
     * @param \stored_file $file The .h5p file.
     * @param int $displayoptions Display options bitmask.
     * @return array ['deployed' => bool, 'h5pid' => ?int, 'errors' => string[]]
     */
    private function deploy(\stored_file $file, int $displayoptions): array {
        $factory = new \core_h5p\factory();
        $core = $factory->get_core();
        $config = \core_h5p\helper::decode_display_options($core, $displayoptions);
        $url = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
        $messages = new \stdClass();
        [, $h5pid] = \core_h5p\api::create_content_from_pluginfile_url($url->out(false), $config, $factory, $messages, true, true);
        $messages = \core_h5p\helper::get_messages($messages, $factory);
        $errors = [];
        foreach ((array) ($messages->error ?: []) as $err) {
            $errors[] = is_object($err) ? (string) ($err->message ?? json_encode($err)) : (string) $err;
        }
        return ['deployed' => (bool) $h5pid, 'h5pid' => $h5pid ? (int) $h5pid : null, 'errors' => $errors];
    }

    /**
     * Regrade all users of an activity (gradebook sync).
     *
     * @param \stdClass $instance h5pactivity record.
     * @param \cm_info $cm Course module.
     */
    private static function regrade(\stdClass $instance, \cm_info $cm): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/h5pactivity/lib.php');
        $fresh = $DB->get_record('h5pactivity', ['id' => $instance->id], '*', MUST_EXIST);
        $fresh->cmidnumber = $cm->idnumber;
        h5pactivity_update_grades($fresh);
    }

    /**
     * Load a content bank item and its content-type instance.
     *
     * @param int $id contentbank_content id.
     * @return array [\core_contentbank\content, \context]
     */
    private static function load_cb_item(int $id): array {
        $cb = new \core_contentbank\contentbank();
        $content = $cb->get_content_from_id($id);
        return [$content, \context::instance_by_id((int) $content->get_contextid())];
    }

    /**
     * Export a content bank item.
     *
     * @param \core_contentbank\content $content Item.
     * @return array
     */
    private static function cb_export(\core_contentbank\content $content): array {
        $rec = $content->get_content();
        return [
            'id' => (int) $rec->id, 'name' => $rec->name, 'contenttype' => $rec->contenttype,
            'contextid' => (int) $rec->contextid,
            'visibility' => ((int) $content->get_visibility() === \core_contentbank\content::VISIBILITY_UNLISTED) ? 'unlisted' : 'public',
            'usercreated' => (int) $rec->usercreated, 'timemodified' => (int) $rec->timemodified,
        ];
    }

    /**
     * Turn the warnings of a core_contentbank external call into a tool error.
     *
     * @param array $result External result (result + warnings).
     * @return array|null Error array, or null when it succeeded.
     */
    private function external_error(array $result): ?array {
        if (!empty($result['result']) || !empty($result['id'])) {
            return null;
        }
        $msg = [];
        foreach ($result['warnings'] ?? [] as $w) {
            $msg[] = ($w['message'] ?? '') ?: ($w['warningcode'] ?? 'refused');
        }
        return $this->error('contentbank_refused', $msg ? implode('; ', $msg) : 'The content bank refused this action');
    }

    /**
     * Require the H5P library-admin capability (same as /h5p/libraries.php).
     */
    private static function require_library_admin(): void {
        require_capability('moodle/h5p:updatelibraries', \context_system::instance());
    }

    /**
     * Content usage + dependants of a library.
     *
     * @param int $libraryid Library id.
     * @return array ['contentcount' => int, 'dependents' => array]
     */
    private static function library_usage(int $libraryid): array {
        global $DB;
        // Deployed content uses a library as its main library (h5p.mainlibraryid) or as a
        // dependency (h5p_contents_libraries, filled when the content is first rendered).
        $count = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT x.h5pid) FROM (
                 SELECT h5pid FROM {h5p_contents_libraries} WHERE libraryid = ?
                 UNION SELECT id AS h5pid FROM {h5p} WHERE mainlibraryid = ?) x',
            [$libraryid, $libraryid]
        );
        $deps = $DB->get_records_sql(
            "SELECT DISTINCT l.id, l.machinename, l.majorversion, l.minorversion
               FROM {h5p_library_dependencies} d JOIN {h5p_libraries} l ON l.id = d.libraryid
              WHERE d.requiredlibraryid = ?",
            [$libraryid]
        );
        return ['contentcount' => $count, 'dependents' => array_values(array_map(static fn($l) => [
            'id' => (int) $l->id, 'library' => "{$l->machinename} {$l->majorversion}.{$l->minorversion}"], $deps))];
    }

    /**
     * Export a library row.
     *
     * @param \stdClass $l h5p_libraries record.
     * @return array
     */
    private static function lib_export(\stdClass $l): array {
        return [
            'id' => (int) $l->id, 'machinename' => $l->machinename, 'title' => $l->title,
            'version' => "{$l->majorversion}.{$l->minorversion}.{$l->patchversion}",
            'runnable' => (bool) $l->runnable, 'enabled' => \core_h5p\api::is_library_enabled($l),
        ];
    }

    /**
     * Preview response for a destructive tool called without confirm=true.
     *
     * @param string $action What would happen.
     * @param array $details Facts about the target.
     * @return array
     */
    private static function preview(string $action, array $details): array {
        return ['executed' => false, 'confirmation_required' => true,
            'preview' => $action, 'details' => $details,
            'message' => 'Nothing was changed. Show this to the user and call again with confirm=true only after the user agrees.'];
    }

    // ------------------------------------------------------------------
    // Activities.
    // ------------------------------------------------------------------

    protected function execute_createContent(array $params, array $context = []): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $courseid = (int) $this->clean_required($params, 'courseid', PARAM_INT);
        $name = trim((string) $this->clean_required($params, 'name', PARAM_TEXT));
        if ($name === '') {
            return $this->error('invalid_name', 'name must not be empty');
        }
        $grade = (int) $this->clean_optional($params, 'grade', PARAM_INT, 100);
        $grademethod = self::grademethod((string) $this->clean_optional($params, 'grademethod', PARAM_ALPHA, 'highest'));
        $packageurl = (string) $this->clean_optional($params, 'package_url', PARAM_URL, '');
        $cbid = (int) $this->clean_optional($params, 'contentbankid', PARAM_INT, 0);

        $course = get_course($courseid);
        $coursectx = \context_course::instance($courseid);
        require_capability('mod/h5pactivity:addinstance', $coursectx);

        // Resolve the content source before creating anything.
        $source = null;
        if ($cbid) {
            [$content, $cbctx] = self::load_cb_item($cbid);
            if (!$content->is_view_allowed() || !has_capability('moodle/contentbank:access', $cbctx)) {
                return $this->error('contentbank_denied', 'You cannot use this content bank item');
            }
            $file = $content->get_file();
            if (!$file) {
                return $this->error('contentbank_empty', 'The content bank item has no file');
            }
            $source = [$file->get_filename(), $file->get_content()];
        } else if ($packageurl !== '') {
            [$bytes, $err] = $this->fetch_package($packageurl, $this->course_max_bytes($courseid));
            if ($bytes === null) {
                return $this->error('package_download_failed', (string) $err);
            }
            $source = [self::package_filename($packageurl), $bytes];
        }

        $core = (new \core_h5p\factory())->get_core();
        $mi = (object) [
            'modulename' => 'h5pactivity',
            'course' => $courseid,
            'section' => (int) $this->clean_optional($params, 'section', PARAM_INT, 0),
            'visible' => (int) (bool) $this->clean_optional($params, 'visible', PARAM_BOOL, true),
            'name' => $name,
            'introeditor' => ['text' => (string) $this->clean_optional($params, 'intro', PARAM_CLEANHTML, ''),
                'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
            'grade' => max(0, $grade),
            'grademethod' => $grademethod,
            'enabletracking' => 1,
            'reviewmode' => 1,
            'displayoptions' => \core_h5p\helper::get_display_options($core, \core_h5p\helper::decode_display_options($core)),
            'cmidnumber' => '',
        ];
        $created = create_module($mi);
        [$instance, , , $ctx] = $this->load_activity((int) $created->instance);

        $result = ['h5pactivityid' => (int) $instance->id, 'cmid' => (int) $created->coursemodule, 'name' => $name,
            'courseid' => $courseid, 'grade' => (int) $instance->grade,
            'gradeitem' => (bool) \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'h5pactivity',
                'iteminstance' => $instance->id, 'courseid' => $courseid])];
        if ($source) {
            $result['package'] = $this->attach_and_deploy($instance, $ctx, $source[0], $source[1]);
        } else {
            $result['note'] = 'Empty activity — add content with generateContent or importContent.';
        }
        return $result;
    }

    protected function execute_generateContent(array $params, array $context = []): array {
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $lib = self::find_library((string) $this->clean_required($params, 'library', PARAM_TEXT));
        if (!$lib->runnable) {
            return $this->error('not_a_content_type', "{$lib->machinename} is a helper library, not a content type");
        }
        $contentparams = $params['params'] ?? null;
        if (is_string($contentparams)) {
            $contentparams = json_decode($contentparams, true);
        }
        if (!is_array($contentparams)) {
            return $this->error('invalid_params', 'params must be an object with the content parameters');
        }
        [$instance, , , $ctx] = $this->managed_activity($id);
        if (!has_capability('moodle/h5p:deploy', $ctx)) {
            return $this->error('nopermission', 'You need moodle/h5p:deploy to publish H5P content here');
        }
        $title = (string) $this->clean_optional($params, 'title', PARAM_TEXT, $instance->name);
        $bytes = self::build_package($lib, $contentparams, $title);
        $slug = clean_param(strtolower(str_replace('.', '-', $lib->machinename)), PARAM_FILE);
        $deploy = $this->attach_and_deploy($instance, $ctx, "{$slug}-generated.h5p", $bytes);
        return ['h5pactivityid' => $id, 'library' => "{$lib->machinename} {$lib->majorversion}.{$lib->minorversion}"] + $deploy;
    }

    protected function execute_updateContent(array $params, array $context = []): array {
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        [, $course, $cm] = $this->managed_activity($id);
        $changed = [];
        $this->update_activity($cm, $course, function (\stdClass $data) use ($params, &$changed) {
            if (array_key_exists('name', $params)) {
                $name = trim((string) clean_param($params['name'], PARAM_TEXT));
                if ($name === '') {
                    throw new \invalid_parameter_exception('name must not be empty');
                }
                $data->name = $name;
                $changed[] = 'name';
            }
            if (array_key_exists('intro', $params)) {
                $data->introeditor['text'] = (string) clean_param($params['intro'], PARAM_CLEANHTML);
                $changed[] = 'intro';
            }
            if (array_key_exists('enabletracking', $params)) {
                $data->enabletracking = (int) (bool) clean_param($params['enabletracking'], PARAM_BOOL);
                $changed[] = 'enabletracking';
            }
            if (array_key_exists('reviewmode', $params)) {
                $data->reviewmode = ((int) clean_param($params['reviewmode'], PARAM_INT)) ? 1 : 0;
                $changed[] = 'reviewmode';
            }
        });
        [$fresh] = $this->load_activity($id);
        return ['h5pactivityid' => $id, 'updated' => $changed, 'name' => $fresh->name,
            'enabletracking' => (int) $fresh->enabletracking, 'reviewmode' => (int) $fresh->reviewmode];
    }

    protected function execute_updateGradingSettings(array $params, array $context = []): array {
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        [, $course, $cm, $ctx] = $this->managed_activity($id);
        require_capability('moodle/grade:manage', \context_course::instance($course->id));
        $grade = array_key_exists('grade', $params) ? max(0, (int) clean_param($params['grade'], PARAM_INT)) : null;
        $method = array_key_exists('grademethod', $params) ? self::grademethod((string) $params['grademethod']) : null;
        if ($grade === null && $method === null) {
            return $this->error('nothing_to_change', 'Give grade and/or grademethod');
        }
        $this->update_activity($cm, $course, function (\stdClass $data) use ($grade, $method) {
            if ($grade !== null) {
                $data->grade = $grade;
            }
            if ($method !== null) {
                $data->grademethod = $method;
            }
        });
        [$fresh] = $this->load_activity($id);
        self::regrade($fresh, $cm);
        return ['h5pactivityid' => $id, 'grade' => (int) $fresh->grade,
            'grademethod' => self::GRADEMETHODS[(int) $fresh->grademethod] ?? (string) $fresh->grademethod, 'regraded' => true];
    }

    protected function execute_updateDisplayOptions(array $params, array $context = []): array {
        global $DB;
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        [$instance, $course, $cm, $ctx] = $this->managed_activity($id);
        require_capability('moodle/h5p:setdisplayoptions', $ctx);
        $core = (new \core_h5p\factory())->get_core();
        $current = $core->getDisplayOptionsForEdit((int) $instance->displayoptions);
        $config = new \stdClass();
        foreach (['frame', 'export', 'embed', 'copyright'] as $k) {
            $config->{$k} = array_key_exists($k, $params)
                ? (int) (bool) clean_param($params[$k], PARAM_BOOL)
                : (int) !empty($current[$k]);
        }
        $value = \core_h5p\helper::get_display_options($core, $config);
        $this->update_activity($cm, $course, function (\stdClass $data) use ($value) {
            $data->displayoptions = $value;
        });
        // Keep an already deployed package in sync, as the player would on the next view.
        $fs = get_file_storage();
        foreach ($fs->get_area_files($ctx->id, 'mod_h5pactivity', 'package', 0, 'id', false) as $f) {
            $DB->set_field('h5p', 'displayoptions', $value, ['pathnamehash' => $f->get_pathnamehash()]);
        }
        $out = ['h5pactivityid' => $id, 'displayoptions' => $value];
        foreach ($core->getDisplayOptionsForEdit($value) as $k => $v) {
            $out[$k] = (bool) $v;
        }
        return $out;
    }

    protected function execute_duplicateContent(array $params, array $context = []): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        [$instance, $course, $cm] = $this->managed_activity($id);
        $coursectx = \context_course::instance($course->id);
        require_capability('mod/h5pactivity:addinstance', $coursectx);
        require_capability('moodle/backup:backuptargetimport', $coursectx);
        require_capability('moodle/restore:restoretargetimport', $coursectx);
        $sectionid = null;
        if (array_key_exists('section', $params)) {
            $num = max(0, (int) clean_param($params['section'], PARAM_INT));
            course_create_sections_if_missing($course, $num);
            $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => $num], MUST_EXIST);
        }
        $cmrecord = get_coursemodule_from_id('h5pactivity', $cm->id, 0, false, MUST_EXIST);
        $newcm = duplicate_module($course, $cmrecord, $sectionid);
        if (!$newcm) {
            return $this->error('duplicate_failed', 'Moodle could not duplicate this activity');
        }
        if (array_key_exists('name', $params)) {
            $name = trim((string) clean_param($params['name'], PARAM_TEXT));
            if ($name !== '') {
                [, $course2, $cm2] = $this->load_activity((int) $newcm->instance);
                $this->update_activity($cm2, $course2, function (\stdClass $data) use ($name) {
                    $data->name = $name;
                });
            }
        }
        [$copy] = $this->load_activity((int) $newcm->instance);
        return ['h5pactivityid' => (int) $copy->id, 'cmid' => (int) $newcm->id, 'name' => $copy->name,
            'section' => (int) $DB->get_field('course_sections', 'section', ['id' => $newcm->section]), 'source' => $id];
    }

    protected function execute_importContent(array $params, array $context = []): array {
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $url = (string) $this->clean_required($params, 'package_url', PARAM_URL);
        [$instance, $course, , $ctx] = $this->managed_activity($id);
        [$bytes, $err] = $this->fetch_package($url, $this->course_max_bytes((int) $course->id));
        if ($bytes === null) {
            return ['h5pactivityid' => $id, 'imported' => false, 'note' => $err];
        }
        $deploy = $this->attach_and_deploy($instance, $ctx, self::package_filename($url), $bytes);
        return ['h5pactivityid' => $id, 'imported' => true] + $deploy;
    }

    protected function execute_updateContentVisibility(array $params, array $context = []): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $visible = (int) (bool) $this->clean_required($params, 'visible', PARAM_BOOL);
        [, , $cm, $ctx] = $this->managed_activity($id);
        require_capability('moodle/course:activityvisibility', $ctx);
        set_coursemodule_visible($cm->id, $visible);
        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('h5pactivity', $cm->id, 0, false, MUST_EXIST))->trigger();
        return ['h5pactivityid' => $id, 'cmid' => (int) $cm->id, 'visible' => (bool) $visible];
    }

    protected function execute_moveContentToSection(array $params, array $context = []): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $num = (int) $this->clean_required($params, 'section', PARAM_INT);
        [, $course, $cm] = $this->managed_activity($id);
        require_capability('moodle/course:manageactivities', \context_course::instance($course->id));
        course_create_sections_if_missing($course, max(0, $num));
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => max(0, $num)], '*', MUST_EXIST);
        moveto_module(get_coursemodule_from_id('h5pactivity', $cm->id, 0, false, MUST_EXIST), $section);
        return ['h5pactivityid' => $id, 'cmid' => (int) $cm->id, 'section' => $num];
    }

    protected function execute_deleteContent(array $params, array $context = []): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        [$instance, $course, $cm] = $this->managed_activity($id);
        if (!$this->clean_optional($params, 'confirm', PARAM_BOOL, false)) {
            return self::preview("Delete the H5P activity \"{$instance->name}\" from course \"{$course->fullname}\"", [
                'h5pactivityid' => $id, 'cmid' => (int) $cm->id,
                'attempts' => (int) $DB->count_records('h5pactivity_attempts', ['h5pactivityid' => $id])]);
        }
        course_delete_module($cm->id);
        return ['executed' => true, 'deleted' => true, 'h5pactivityid' => $id, 'cmid' => (int) $cm->id];
    }

    // ------------------------------------------------------------------
    // Attempts and reports.
    // ------------------------------------------------------------------

    protected function execute_getActivityGradeReport(array $params, array $context = []): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        [$instance, $course, $cm, $ctx] = $this->load_activity($id);
        require_capability('mod/h5pactivity:reviewattempts', $ctx);
        $manager = \mod_h5pactivity\local\manager::create_from_coursemodule($cm);

        // Group: Moodle's own rule — with separate groups a user without
        // accessallgroups only sees their own group(s).
        $groupid = (int) $this->clean_optional($params, 'groupid', PARAM_INT, 0);
        $groupmode = groups_get_activity_groupmode($cm);
        $allgroups = has_capability('moodle/site:accessallgroups', $ctx);
        if ($groupid && $groupmode == SEPARATEGROUPS && !$allgroups && !groups_is_member($groupid)) {
            return $this->error('group_denied', 'You are not a member of that group');
        }
        if (!$groupid && $groupmode == SEPARATEGROUPS && !$allgroups) {
            $groupid = (int) groups_get_activity_group($cm, false);
        }
        $join = $manager->get_active_users_join(true, $groupmode == NOGROUPS ? false : $groupid);
        $users = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname FROM {user} u {$join->joins}
                                        WHERE {$join->wheres} ORDER BY u.lastname, u.firstname", $join->params);
        $counts = $manager->count_users_attempts();
        $grades = grade_get_grades($course->id, 'mod', 'h5pactivity', $instance->id, array_keys($users));
        $gradeitems = $grades->items ?? [];
        $item = $gradeitems ? reset($gradeitems) : null;
        $rows = [];
        $notattempted = 0;
        foreach ($users as $u) {
            $n = (int) ($counts[$u->id] ?? 0);
            if (!$n) {
                $notattempted++;
            }
            $g = $item->grades[$u->id] ?? null;
            $rows[] = ['userid' => (int) $u->id, 'fullname' => fullname($u), 'attempts' => $n,
                'grade' => ($g && $g->grade !== null) ? (float) $g->grade : null];
        }
        return ['h5pactivityid' => $id, 'name' => $instance->name, 'maxgrade' => (int) $instance->grade,
            'grademethod' => self::GRADEMETHODS[(int) $instance->grademethod] ?? null,
            'participants' => count($rows), 'not_attempted' => $notattempted, 'users' => $rows];
    }

    protected function execute_deleteUserAttempt(array $params, array $context = []): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/h5pactivity/lib.php');
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $attemptid = (int) $this->clean_required($params, 'attemptid', PARAM_INT);
        [$instance, , $cm, $ctx] = $this->managed_activity($id);
        require_capability('mod/h5pactivity:reviewattempts', $ctx);
        $record = $DB->get_record('h5pactivity_attempts', ['id' => $attemptid, 'h5pactivityid' => $id], '*', MUST_EXIST);
        if (!$this->clean_optional($params, 'confirm', PARAM_BOOL, false)) {
            $user = \core_user::get_user($record->userid);
            return self::preview("Delete attempt #{$record->attempt} of " . ($user ? fullname($user) : "user {$record->userid}")
                . " in \"{$instance->name}\" and regrade", ['attemptid' => $attemptid, 'userid' => (int) $record->userid,
                'rawscore' => (int) $record->rawscore, 'maxscore' => (int) $record->maxscore]);
        }
        \mod_h5pactivity\local\attempt::delete_attempt(new \mod_h5pactivity\local\attempt($record));
        $fresh = $DB->get_record('h5pactivity', ['id' => $id], '*', MUST_EXIST);
        $fresh->cmidnumber = $cm->idnumber;
        h5pactivity_update_grades($fresh, (int) $record->userid);
        return ['executed' => true, 'deleted' => true, 'attemptid' => $attemptid, 'userid' => (int) $record->userid];
    }

    protected function execute_resetAllAttempts(array $params, array $context = []): array {
        global $DB;
        $id = (int) $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $userid = (int) $this->clean_optional($params, 'userid', PARAM_INT, 0);
        [$instance, , $cm, $ctx] = $this->managed_activity($id);
        require_capability('mod/h5pactivity:reviewattempts', $ctx);
        $where = ['h5pactivityid' => $id] + ($userid ? ['userid' => $userid] : []);
        $count = $DB->count_records('h5pactivity_attempts', $where);
        if (!$this->clean_optional($params, 'confirm', PARAM_BOOL, false)) {
            return self::preview("Delete {$count} attempt(s) " . ($userid ? "of user {$userid} " : 'of ALL users ')
                . "in \"{$instance->name}\" and reset the grades", ['h5pactivityid' => $id, 'attempts' => $count, 'userid' => $userid ?: null]);
        }
        $user = $userid ? \core_user::get_user($userid, '*', MUST_EXIST) : null;
        \mod_h5pactivity\local\attempt::delete_all_attempts(get_coursemodule_from_id('h5pactivity', $cm->id, 0, false, MUST_EXIST), $user);
        self::regrade($instance, $cm);
        return ['executed' => true, 'deleted_attempts' => $count, 'h5pactivityid' => $id, 'userid' => $userid ?: null];
    }

    // ------------------------------------------------------------------
    // Content bank.
    // ------------------------------------------------------------------

    protected function execute_listContentBankItems(array $params, array $context = []): array {
        $courseid = (int) $this->clean_optional($params, 'courseid', PARAM_INT, 0);
        $contextid = (int) $this->clean_optional($params, 'contextid', PARAM_INT, 0);
        $search = (string) $this->clean_optional($params, 'search', PARAM_TEXT, '');
        if ($courseid) {
            $ctx = \context_course::instance($courseid);
        } else if ($contextid) {
            $ctx = \context::instance_by_id($contextid);
        } else {
            return $this->error('missing_context', 'Give courseid or contextid');
        }
        require_capability('moodle/contentbank:access', $ctx);
        $cb = new \core_contentbank\contentbank();
        $items = [];
        foreach ($cb->search_contents($search ?: null, $ctx->id, ['h5p']) as $content) {
            if ($content->is_view_allowed()) {
                $items[] = self::cb_export($content);
            }
        }
        return ['contextid' => $ctx->id, 'count' => count($items), 'items' => $items];
    }

    protected function execute_createContentBankItem(array $params, array $context = []): array {
        global $USER;
        $courseid = (int) $this->clean_required($params, 'courseid', PARAM_INT);
        $ctx = \context_course::instance($courseid);
        require_capability('moodle/contentbank:access', $ctx);
        require_capability('moodle/contentbank:upload', $ctx);
        $contenttype = new \contenttype_h5p\contenttype($ctx);
        if (!$contenttype->can_upload()) {
            return $this->error('nopermission', 'You cannot add H5P content to this content bank');
        }
        $packageurl = (string) $this->clean_optional($params, 'package_url', PARAM_URL, '');
        if ($packageurl !== '') {
            [$bytes, $err] = $this->fetch_package($packageurl, $this->course_max_bytes($courseid));
            if ($bytes === null) {
                return $this->error('package_download_failed', (string) $err);
            }
            $filename = self::package_filename($packageurl);
        } else if (!empty($params['library'])) {
            $lib = self::find_library((string) clean_param($params['library'], PARAM_TEXT));
            $contentparams = is_string($params['params'] ?? null) ? json_decode($params['params'], true) : ($params['params'] ?? null);
            if (!$lib->runnable || !is_array($contentparams)) {
                return $this->error('invalid_params', 'library must be a content type and params an object');
            }
            $title = (string) $this->clean_optional($params, 'name', PARAM_TEXT, $lib->title);
            $bytes = self::build_package($lib, $contentparams, $title);
            $filename = clean_param(strtolower(str_replace('.', '-', $lib->machinename)), PARAM_FILE) . '-generated.h5p';
        } else {
            return $this->error('missing_source', 'Give package_url, or library + params');
        }
        // Stage in the user's draft area, then let the content bank import it.
        $fs = get_file_storage();
        $draft = $fs->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(), 'filepath' => '/', 'filename' => $filename, 'userid' => $USER->id,
        ], $bytes);
        $cb = new \core_contentbank\contentbank();
        $content = $cb->create_content_from_file($ctx, $USER->id, $draft);
        $draft->delete();
        if (!$content) {
            return $this->error('contentbank_failed', 'The content bank could not store this package');
        }
        $name = (string) $this->clean_optional($params, 'name', PARAM_TEXT, '');
        if ($name !== '') {
            $content->set_name($name);
        }
        $out = self::cb_export($content);
        $file = $content->get_file();
        $out['deploy'] = $file ? $this->deploy($file, 0) : ['deployed' => false, 'h5pid' => null, 'errors' => ['no file']];
        return $out;
    }

    protected function execute_renameContentBankItem(array $params, array $context = []): array {
        $cbid = (int) $this->clean_required($params, 'contentbankid', PARAM_INT);
        $name = trim((string) $this->clean_required($params, 'name', PARAM_TEXT));
        $res = \core_contentbank\external\rename_content::execute($cbid, $name);
        if ($err = $this->external_error($res)) {
            return $err;
        }
        [$content] = self::load_cb_item($cbid);
        return ['renamed' => true] + self::cb_export($content);
    }

    protected function execute_copyContentBankItem(array $params, array $context = []): array {
        $cbid = (int) $this->clean_required($params, 'contentbankid', PARAM_INT);
        $name = trim((string) $this->clean_required($params, 'name', PARAM_TEXT));
        $res = \core_contentbank\external\copy_content::execute($cbid, $name);
        if ($err = $this->external_error($res)) {
            return $err;
        }
        [$content] = self::load_cb_item((int) $res['id']);
        return ['copied' => true, 'source' => $cbid] + self::cb_export($content);
    }

    protected function execute_setContentBankVisibility(array $params, array $context = []): array {
        $cbid = (int) $this->clean_required($params, 'contentbankid', PARAM_INT);
        $vis = strtolower((string) $this->clean_required($params, 'visibility', PARAM_ALPHA));
        $map = ['public' => \core_contentbank\content::VISIBILITY_PUBLIC, 'unlisted' => \core_contentbank\content::VISIBILITY_UNLISTED];
        if (!isset($map[$vis])) {
            return $this->error('invalid_visibility', 'visibility must be public or unlisted');
        }
        $res = \core_contentbank\external\set_content_visibility::execute($cbid, $map[$vis]);
        if ($err = $this->external_error($res)) {
            return $err;
        }
        [$content] = self::load_cb_item($cbid);
        return ['updated' => true] + self::cb_export($content);
    }

    protected function execute_deleteContentBankItem(array $params, array $context = []): array {
        $cbid = (int) $this->clean_required($params, 'contentbankid', PARAM_INT);
        [$content, $ctx] = self::load_cb_item($cbid);
        if (!$content->get_content_type_instance()->can_delete($content)) {
            return $this->error('nopermission', 'You cannot delete this content bank item');
        }
        if (!$this->clean_optional($params, 'confirm', PARAM_BOOL, false)) {
            return self::preview(
                "Delete the content bank item \"{$content->get_name()}\"",
                self::cb_export($content) + ['used_in' => count($content->get_uses() ?? [])]
            );
        }
        $res = \core_contentbank\external\delete_content::execute([$cbid]);
        if ($err = $this->external_error($res)) {
            return $err;
        }
        return ['executed' => true, 'deleted' => true, 'contentbankid' => $cbid];
    }

    // ------------------------------------------------------------------
    // Content types and libraries.
    // ------------------------------------------------------------------

    protected function execute_getContentTypeSchema(array $params, array $context = []): array {
        $lib = self::find_library((string) $this->clean_required($params, 'library', PARAM_TEXT));
        $semantics = json_decode((string) $lib->semantics, true);
        return ['library' => "{$lib->machinename} {$lib->majorversion}.{$lib->minorversion}", 'title' => $lib->title,
            'runnable' => (bool) $lib->runnable, 'enabled' => \core_h5p\api::is_library_enabled($lib),
            'semantics' => is_array($semantics) ? $semantics : [],
            'note' => 'Build params as an object whose keys are the semantics field names (content.json).'];
    }

    protected function execute_listLibraries(array $params, array $context = []): array {
        global $DB;
        self::require_library_admin();
        $runnable = (bool) $this->clean_optional($params, 'runnable', PARAM_BOOL, false);
        $libs = $DB->get_records('h5p_libraries', $runnable ? ['runnable' => 1] : [], 'machinename, majorversion DESC, minorversion DESC');
        $usage = $DB->get_records_sql_menu('SELECT x.libraryid, COUNT(DISTINCT x.h5pid) FROM (
                SELECT libraryid, h5pid FROM {h5p_contents_libraries}
                UNION SELECT mainlibraryid AS libraryid, id AS h5pid FROM {h5p}) x GROUP BY x.libraryid');
        $out = [];
        foreach ($libs as $l) {
            $out[] = self::lib_export($l) + ['contentcount' => (int) ($usage[$l->id] ?? 0)];
        }
        return ['count' => count($out), 'libraries' => $out];
    }

    protected function execute_getLibraryDetails(array $params, array $context = []): array {
        global $DB;
        self::require_library_admin();
        $libid = (int) $this->clean_required($params, 'libraryid', PARAM_INT);
        $lib = $DB->get_record('h5p_libraries', ['id' => $libid], '*', MUST_EXIST);
        $requires = $DB->get_records_sql(
            "SELECT DISTINCT l.id, l.machinename, l.majorversion, l.minorversion, d.dependencytype
               FROM {h5p_library_dependencies} d JOIN {h5p_libraries} l ON l.id = d.requiredlibraryid
              WHERE d.libraryid = ?",
            [$libid]
        );
        return self::lib_export($lib) + self::library_usage($libid) + [
            'requires' => array_values(array_map(static fn($l) => ['id' => (int) $l->id,
                'library' => "{$l->machinename} {$l->majorversion}.{$l->minorversion}", 'type' => $l->dependencytype], $requires)),
        ];
    }

    protected function execute_enableLibrary(array $params, array $context = []): array {
        return $this->set_library_enabled((int) $this->clean_required($params, 'libraryid', PARAM_INT), true);
    }

    protected function execute_disableLibrary(array $params, array $context = []): array {
        return $this->set_library_enabled((int) $this->clean_required($params, 'libraryid', PARAM_INT), false);
    }

    /**
     * Enable / disable a library (core_h5p\api::set_library_enabled).
     *
     * @param int $libid Library id.
     * @param bool $enabled New state.
     * @return array
     */
    private function set_library_enabled(int $libid, bool $enabled): array {
        global $DB;
        self::require_library_admin();
        $lib = $DB->get_record('h5p_libraries', ['id' => $libid], '*', MUST_EXIST);
        \core_h5p\api::set_library_enabled($libid, $enabled);
        return self::lib_export($DB->get_record('h5p_libraries', ['id' => $libid], '*', MUST_EXIST));
    }

    protected function execute_deleteLibrary(array $params, array $context = []): array {
        global $DB;
        self::require_library_admin();
        $libid = (int) $this->clean_required($params, 'libraryid', PARAM_INT);
        $lib = $DB->get_record('h5p_libraries', ['id' => $libid], '*', MUST_EXIST);
        $usage = self::library_usage($libid);
        if ($usage['contentcount'] > 0 || $usage['dependents']) {
            return $this->error(
                'library_in_use',
                'This library is used by content or by other libraries; it was not deleted',
                self::lib_export($lib) + $usage
            );
        }
        if (!$this->clean_optional($params, 'confirm', PARAM_BOOL, false)) {
            return self::preview(
                "Delete the unused H5P library {$lib->machinename} {$lib->majorversion}.{$lib->minorversion}",
                self::lib_export($lib) + $usage
            );
        }
        \core_h5p\api::delete_library(new \core_h5p\factory(), $lib);
        return ['executed' => true, 'deleted' => true, 'libraryid' => $libid,
            'library' => "{$lib->machinename} {$lib->majorversion}.{$lib->minorversion}.{$lib->patchversion}"];
    }
}
