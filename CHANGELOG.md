# Changelog

All notable changes to this project are documented in this file.
-
## v2.5.3 — 2026-02-03
- Release: Sync versions across package files, Fix Functionality, update docs, and minor fixes.

## v2.2.5 — 2026-02-02
- Fix: Correct request counts after Clear/Clear Except operations.
- Ajax responses now include updated counts so the UI stays in sync.
- JS: `updateStats()` added to refresh dashboard counts without full reload.
- Templates and ajax handlers updated to return counts on clear/toggle actions.

## v2.0.0 — 2026-02-02
- Feature: Replace soft-deletes with hard deletion and add audit trail table `wp_external_requests_deleted`.
  - Audit columns: `id`, `host`, `url_example`, `was_blocked`, `deleted_timestamp`, `deleted_by_user`.
  - Deleted entries admin page added (`templates/deleted.php`).
- Improvement: Capture response details (response code, response time, response body) more robustly.
  - Added `response_body` column (LONGTEXT) and truncation per setting.
  - Detail modal shows response data and offers "Download Full Response".
- Setting: `erm_pro_track_response` toggle and new `erm_pro_max_response_body_length` setting to control stored response size.
- Safety: Do not auto-run destructive DB upgrades on plugin load.
  - Added manual Database Updater UI with `ERM_Database::upgrade()` and an admin AJAX endpoint to trigger it.
  - Added `ERM_PRO_DB_VERSION` to decouple DB schema version from plugin release version.
- Misc: Admin notices for DB upgrades and host notices; side-panel ordering and CSS polish for Settings.

## Notes
- Before running the Database Updater on production, take a database backup.
- If you upgrade from older versions, run the Database Updater from the Settings page to apply schema changes (adds `response_body` column and creates deleted-audit table).

