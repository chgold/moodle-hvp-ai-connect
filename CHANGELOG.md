# Changelog — local_aiconnecthvp

## [1.0.1] — 2026-11-10

### Security
- `importContent` / `uploadMediaAsset`: downloads go through Moodle's `\curl`
  (host/port checked on every redirect hop) with a 60 s timeout and the
  course's upload size limit (`get_user_max_upload_file_size`), instead of
  `download_file_content()` (no size cap, 300 s timeout).
- `importContent` only accepts an .h5p (ZIP) package; `uploadMediaAsset` only
  accepts image/audio/video media by extension **and** sniffed content type
  (SVG and scripts refused).
- Explicit `moodle/course:manageactivities` check in the module context for
  import/upload, and `mod/h5pactivity:addinstance` for `duplicateContent`
  (which now also respects the course upload limit).
