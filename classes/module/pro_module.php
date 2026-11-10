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
 * H5P Pro tool-set (create/update/duplicate/import content). Registered into
 * the core registry by local_aiconnect\module\pro_loader only when the site
 * edition grants Pro. Capability-gated on mod_h5pactivity.
 *
 * @package    local_aiconnecthvp
 * @copyright  2026 Goldt <https://github.com/chgold>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiconnecthvp\module;

use local_aiconnect\module\module_base;

defined('MOODLE_INTERNAL') || die();

class pro_module extends module_base {
    /** @var string Tool namespace prefix. */
    protected string $module_name = 'hvp_pro';

    protected function register_tools(): void {
        $cap = 'mod_h5pactivity';

        $this->register_tool('createContent', [
            'description'         => 'Create a new H5P activity in a course (upload a package afterwards with importContent)',
            'input_schema'        => ['type' => 'object', 'required' => ['courseid', 'name'],
                'properties' => [
                    'courseid' => ['type' => 'integer'],
                    'name'     => ['type' => 'string'],
                    'intro'    => ['type' => 'string'],
                    'grade'    => ['type' => 'integer', 'description' => 'Max grade (default 100, 0 = no grade)'],
                    'section'  => ['type' => 'integer'],
                ]],
            'required_scope' => 'write', 'requires_capability' => $cap,
        ]);
        $this->register_tool('updateContent', [
            'description'         => 'Update an H5P activity configuration',
            'input_schema'        => ['type' => 'object', 'required' => ['h5pactivityid'],
                'properties' => [
                    'h5pactivityid' => ['type' => 'integer'],
                    'name'          => ['type' => 'string'],
                    'intro'         => ['type' => 'string'],
                    'enabletracking' => ['type' => 'boolean'],
                    'reviewmode'    => ['type' => 'integer'],
                ]],
            'required_scope' => 'write', 'requires_capability' => $cap,
        ]);
        $this->register_tool('duplicateContent', [
            'description'         => 'Duplicate an existing H5P activity within its course',
            'input_schema'        => ['type' => 'object', 'required' => ['h5pactivityid'],
                'properties' => ['h5pactivityid' => ['type' => 'integer'], 'name' => ['type' => 'string']]],
            'required_scope' => 'write', 'requires_capability' => $cap,
        ]);
        $this->register_tool('importContent', [
            'description'         => 'Attach a .h5p package (from a URL) to an H5P activity',
            'input_schema'        => ['type' => 'object', 'required' => ['h5pactivityid', 'package_url'],
                'properties' => [
                    'h5pactivityid' => ['type' => 'integer'],
                    'package_url'   => ['type' => 'string', 'description' => 'Absolute URL to a .h5p file'],
                ]],
            'required_scope' => 'write', 'requires_capability' => $cap,
        ]);
        $this->register_tool('uploadMediaAsset', [
            'description'         => 'Upload a media asset (from a URL) into an H5P activity file area',
            'input_schema'        => ['type' => 'object', 'required' => ['h5pactivityid', 'media_url'],
                'properties' => [
                    'h5pactivityid' => ['type' => 'integer'],
                    'media_url'     => ['type' => 'string', 'description' => 'Absolute URL to an image/audio/video file'],
                    'filename'      => ['type' => 'string'],
                ]],
            'required_scope' => 'write', 'requires_capability' => $cap,
        ]);
    }

    /**
     * Load an H5P activity + require manage capability on its course.
     */
    private function writable_activity(int $id, int $userid): \stdClass {
        global $DB;
        $activity = $DB->get_record('h5pactivity', ['id' => $id], '*', MUST_EXIST);
        require_capability('moodle/course:manageactivities', \context_course::instance((int) $activity->course), $userid);
        return $activity;
    }

    /** Allowed media extensions for uploadMediaAsset (no SVG, no scripts/HTML). */
    private const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'm4a', 'ogg', 'wav', 'mp4', 'webm'];

    /**
     * Upload size limit for a course, as Moodle's own file picker applies it.
     *
     * @param int $courseid Course id.
     * @param int $userid   Acting user.
     * @return int Max bytes (a 512 MB hard ceiling for users without a limit).
     */
    private function course_max_bytes(int $courseid, int $userid): int {
        global $CFG;
        $course = get_course($courseid);
        $max = get_user_max_upload_file_size(\context_course::instance($courseid), $CFG->maxbytes, $course->maxbytes, 0, $userid);
        return ($max > 0) ? (int) $max : 512 * 1024 * 1024;
    }

    /**
     * Download an agent-supplied URL through Moodle's \curl (curl_security_helper
     * checks host/port on every redirect hop) with a timeout and a size cap.
     *
     * @param string $url      Agent-supplied URL.
     * @param int    $maxbytes Size cap.
     * @return array [string|null $contents, string|null $error]
     */
    private function fetch_limited(string $url, int $maxbytes): array {
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
        if ($ok !== true || $c->get_errno() || $code !== 200 || !is_file($tmp)) {
            return [null, 'Could not download from URL (or it exceeds the ' . display_size($maxbytes) . ' upload limit)'];
        }
        if (filesize($tmp) > $maxbytes) {
            return [null, 'File exceeds the ' . display_size($maxbytes) . ' upload limit for this course'];
        }
        $contents = (string) file_get_contents($tmp);
        @unlink($tmp);
        return [$contents === '' ? null : $contents, $contents === '' ? 'Downloaded file is empty' : null];
    }

    protected function execute_createContent(array $params, array $context = []): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $courseid = $this->clean_required($params, 'courseid', PARAM_INT);
        $name = $this->clean_required($params, 'name', PARAM_TEXT);
        $intro = (string) $this->clean_optional($params, 'intro', PARAM_CLEANHTML, '');
        $grade = (int) $this->clean_optional($params, 'grade', PARAM_INT, 100);
        $section = (int) $this->clean_optional($params, 'section', PARAM_INT, 0);
        $userid = (int) ($context['userid'] ?? 0);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        require_capability('moodle/course:manageactivities', \context_course::instance($courseid), $userid);
        $module = $DB->get_record('modules', ['name' => 'h5pactivity'], '*', MUST_EXIST);

        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();
            $h5prow = (object) [
                'course'         => $courseid,
                'name'           => $name,
                'timecreated'    => $now,
                'timemodified'   => $now,
                'intro'          => $intro,
                'introformat'    => FORMAT_HTML,
                'grade'          => $grade,
                'displayoptions' => 15,
                'enabletracking' => 1,
                'grademethod'    => 1,
                'reviewmode'     => 1,
            ];
            $h5pid = $DB->insert_record('h5pactivity', $h5prow);

            $cm = (object) [
                'course' => $courseid, 'module' => $module->id, 'instance' => $h5pid,
                'section' => $section, 'idnumber' => '', 'added' => $now, 'score' => 0, 'indent' => 0,
                'visible' => 1, 'visibleoncoursepage' => 1, 'visibleold' => 1, 'groupmode' => 0,
                'groupingid' => 0, 'completion' => 0, 'completionview' => 0, 'completionexpected' => 0,
                'showdescription' => 0, 'availability' => null, 'deletioninprogress' => 0,
            ];
            $cmid = $DB->insert_record('course_modules', $cm);
            course_add_cm_to_section($course, $cmid, $section);
            \context_module::instance($cmid);
            rebuild_course_cache($courseid, true);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        return ['h5pactivityid' => (int) $h5pid, 'cmid' => (int) $cmid, 'name' => $name, 'courseid' => $courseid,
            'note' => 'Empty H5P activity created — attach content with hvp_pro.importContent.'];
    }

    protected function execute_updateContent(array $params, array $context = []): array {
        global $DB;
        $id = $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $this->writable_activity($id, (int) ($context['userid'] ?? 0));
        $upd = (object) ['id' => $id, 'timemodified' => time()];
        if (array_key_exists('name', $params)) {
            $upd->name = $this->clean_optional($params, 'name', PARAM_TEXT, '');
        }
        if (array_key_exists('intro', $params)) {
            $upd->intro = $this->clean_optional($params, 'intro', PARAM_CLEANHTML, '');
        }
        if (array_key_exists('enabletracking', $params)) {
            $upd->enabletracking = (int) (bool) $this->clean_optional($params, 'enabletracking', PARAM_BOOL, true);
        }
        if (array_key_exists('reviewmode', $params)) {
            $upd->reviewmode = (int) $this->clean_optional($params, 'reviewmode', PARAM_INT, 1);
        }
        $DB->update_record('h5pactivity', $upd);
        return ['h5pactivityid' => $id, 'updated' => true];
    }

    protected function execute_duplicateContent(array $params, array $context = []): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $id = $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $source = $this->writable_activity($id, (int) ($context['userid'] ?? 0));
        $newname = (string) $this->clean_optional($params, 'name', PARAM_TEXT, $source->name . ' (copy)');
        $sourcecm = get_coursemodule_from_instance('h5pactivity', $id, 0, false, MUST_EXIST);
        $userid = (int) ($context['userid'] ?? 0);
        require_capability('mod/h5pactivity:addinstance', \context_course::instance((int) $source->course), $userid);
        $maxbytes = $this->course_max_bytes((int) $source->course, $userid);

        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();
            $row = clone $source;
            unset($row->id);
            $row->name = $newname;
            $row->timecreated = $now;
            $row->timemodified = $now;
            $newid = $DB->insert_record('h5pactivity', $row);

            $module = $DB->get_record('modules', ['name' => 'h5pactivity'], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $source->course], '*', MUST_EXIST);
            $cm = (object) [
                'course' => $source->course, 'module' => $module->id, 'instance' => $newid,
                'section' => 0, 'idnumber' => '', 'added' => $now, 'score' => 0, 'indent' => 0,
                'visible' => 1, 'visibleoncoursepage' => 1, 'visibleold' => 1, 'groupmode' => 0,
                'groupingid' => 0, 'completion' => 0, 'completionview' => 0, 'completionexpected' => 0,
                'showdescription' => 0, 'availability' => null, 'deletioninprogress' => 0,
            ];
            $newcmid = $DB->insert_record('course_modules', $cm);
            course_add_cm_to_section($course, $newcmid, 0);
            $newctx = \context_module::instance($newcmid);

            // Copy the packaged .h5p file so the duplicate is functional.
            $fs = get_file_storage();
            $srcctx = \context_module::instance($sourcecm->id);
            foreach ($fs->get_area_files($srcctx->id, 'mod_h5pactivity', 'package', 0, 'id', false) as $f) {
                if ($f->get_filesize() > $maxbytes) {
                    throw new \moodle_exception('maxbytesfile', 'error', '', ['file' => $f->get_filename(), 'size' => display_size($maxbytes)]);
                }
                $fs->create_file_from_storedfile([
                    'contextid' => $newctx->id, 'component' => 'mod_h5pactivity',
                    'filearea' => 'package', 'itemid' => 0,
                    'filepath' => $f->get_filepath(), 'filename' => $f->get_filename(),
                ], $f);
            }
            rebuild_course_cache($source->course, true);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }
        return ['h5pactivityid' => (int) $newid, 'cmid' => (int) $newcmid, 'name' => $newname];
    }

    protected function execute_importContent(array $params, array $context = []): array {
        global $CFG;
        $id = $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $url = $this->clean_required($params, 'package_url', PARAM_URL);
        $this->writable_activity($id, (int) ($context['userid'] ?? 0));
        $cm = get_coursemodule_from_instance('h5pactivity', $id, 0, false, MUST_EXIST);
        $ctx = \context_module::instance($cm->id);
        $userid = (int) ($context['userid'] ?? 0);
        require_capability('moodle/course:manageactivities', $ctx, $userid);

        [$contents, $error] = $this->fetch_limited($url, $this->course_max_bytes((int) $cm->course, $userid));
        if ($contents === null) {
            return ['h5pactivityid' => $id, 'imported' => false, 'note' => $error];
        }
        // An .h5p package is a ZIP archive — refuse anything else.
        if (strncmp($contents, "PK\x03\x04", 4) !== 0) {
            return ['h5pactivityid' => $id, 'imported' => false, 'note' => 'The URL did not return an .h5p (ZIP) package'];
        }
        $fs = get_file_storage();
        $fs->delete_area_files($ctx->id, 'mod_h5pactivity', 'package', 0);
        $filename = \clean_param(basename(\parse_url($url, PHP_URL_PATH) ?: 'content.h5p'), PARAM_FILE) ?: 'content.h5p';
        if (substr($filename, -4) !== '.h5p') {
            $filename .= '.h5p';
        }
        $fs->create_file_from_string([
            'contextid' => $ctx->id, 'component' => 'mod_h5pactivity', 'filearea' => 'package',
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
        ], $contents);
        return ['h5pactivityid' => $id, 'imported' => true, 'filename' => $filename,
            'note' => 'Package attached; Moodle deploys it to the H5P library cache on first view.'];
    }

    protected function execute_uploadMediaAsset(array $params, array $context = []): array {
        global $CFG;
        $id = $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $url = $this->clean_required($params, 'media_url', PARAM_URL);
        $this->writable_activity($id, (int) ($context['userid'] ?? 0));
        $cm = get_coursemodule_from_instance('h5pactivity', $id, 0, false, MUST_EXIST);
        $ctx = \context_module::instance($cm->id);
        $userid = (int) ($context['userid'] ?? 0);
        require_capability('moodle/course:manageactivities', $ctx, $userid);

        [$contents, $error] = $this->fetch_limited($url, $this->course_max_bytes((int) $cm->course, $userid));
        if ($contents === null) {
            return ['h5pactivityid' => $id, 'uploaded' => false, 'note' => $error];
        }
        $filename = (string) $this->clean_optional(
            $params,
            'filename',
            PARAM_FILE,
            basename(\parse_url($url, PHP_URL_PATH) ?: 'asset.bin')
        );
        $filename = \clean_param($filename, PARAM_FILE) ?: 'asset.bin';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $mediamime = preg_match('#^(image|audio|video)/#', $mime) && strpos($mime, 'svg') === false;
        if (!in_array($ext, self::MEDIA_EXTENSIONS, true) || !$mediamime) {
            return ['h5pactivityid' => $id, 'uploaded' => false,
                'note' => 'Only image/audio/video media (' . implode(', ', self::MEDIA_EXTENSIONS) . ') can be uploaded; got '
                    . ($ext ?: 'no extension') . ' / ' . $mime];
        }
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $ctx->id, 'component' => 'mod_h5pactivity', 'filearea' => 'media',
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
        ], $contents);
        return ['h5pactivityid' => $id, 'uploaded' => true, 'filename' => $filename];
    }
}
