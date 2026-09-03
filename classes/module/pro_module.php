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
        $id = $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $url = $this->clean_required($params, 'package_url', PARAM_URL);
        $this->writable_activity($id, (int) ($context['userid'] ?? 0));
        $cm = get_coursemodule_from_instance('h5pactivity', $id, 0, false, MUST_EXIST);
        $ctx = \context_module::instance($cm->id);

        $contents = @\download_file_content($url);
        if ($contents === false || $contents === null || $contents === '') {
            return ['h5pactivityid' => $id, 'imported' => false, 'note' => 'Could not download package from URL'];
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
        $id = $this->clean_required($params, 'h5pactivityid', PARAM_INT);
        $url = $this->clean_required($params, 'media_url', PARAM_URL);
        $this->writable_activity($id, (int) ($context['userid'] ?? 0));
        $cm = get_coursemodule_from_instance('h5pactivity', $id, 0, false, MUST_EXIST);
        $ctx = \context_module::instance($cm->id);

        $contents = @\download_file_content($url);
        if ($contents === false || $contents === null || $contents === '') {
            return ['h5pactivityid' => $id, 'uploaded' => false, 'note' => 'Could not download media from URL'];
        }
        $filename = (string) $this->clean_optional($params, 'filename', PARAM_FILE,
            basename(\parse_url($url, PHP_URL_PATH) ?: 'asset.bin'));
        $filename = \clean_param($filename, PARAM_FILE) ?: 'asset.bin';
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $ctx->id, 'component' => 'mod_h5pactivity', 'filearea' => 'media',
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
        ], $contents);
        return ['h5pactivityid' => $id, 'uploaded' => true, 'filename' => $filename];
    }
}
