# Changelog — local_aiconnecthvp

## [1.1.0] — 2026-11-19

### Added (Deep Research round 2026-11-19)
- 21 new tools (25 in total): generateContent (build + deploy an H5P package from a content type and its
  parameters), getContentTypeSchema, updateGradingSettings, updateDisplayOptions, updateContentVisibility,
  moveContentToSection, deleteContent, getActivityGradeReport, deleteUserAttempt, resetAllAttempts,
  content bank (listContentBankItems, createContentBankItem, renameContentBankItem, copyContentBankItem,
  setContentBankVisibility, deleteContentBankItem) and library admin (listLibraries, getLibraryDetails,
  enableLibrary, disableLibrary, deleteLibrary).
- Destructive tools return a preview and run only with confirm=true.

### Changed
- createContent / updateContent / duplicateContent now go through Moodle's own create_module,
  update_moduleinfo and duplicate_module: grade item, events, calendar and completion are created
  correctly; duplicates can go to another section.
- Packages are validated and deployed by core_h5p on import; validation errors are returned.

### Removed
- uploadMediaAsset: it wrote to a file area mod_h5pactivity never serves, so the files were unusable.

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
