# Implementation Plan - System-Wide Auditing, Error Fixes & Deployment Best Practices

Audit all functions, APIs, and database calls across the **aBility** codebase to eliminate errors, resolve security vulnerabilities, centralize database connections, and ensure deployment readiness.

## User Review Required

> [!IMPORTANT]
> **Database Credentials & Environment Configuration**: We will standardize database connection parameters to draw from `config.php` constants (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) rather than duplicating hardcoded `'localhost'`, `'root'`, `''` strings across 30+ endpoint files. Ensure your local database settings in `config.php` are accurate.

> [!NOTE]
> **CSRF Protection on POST APIs**: Standardizing CSRF token verification across AJAX and API forms (`submit_batch.php`, `approve.php`, `verify.php`). Valid requests from the UI will pass the session CSRF token.

---

## Open Questions

* No open questions at this stage.

---

## Proposed Changes

### Database Core & Connection Centralization

#### [MODIFY] [includes/db_connect.php](file:///c:/xampp/htdocs/ability_app_main/includes/db_connect.php)
* Update `Database` class constructor to pull from defined constants (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) instead of hardcoded strings.
* Add helper methods for safe prepared queries with automatic parameter binding.

#### [MODIFY] [config.php](file:///c:/xampp/htdocs/ability_app_main/config.php) / [bootstrap.php](file:///c:/xampp/htdocs/ability_app_main/bootstrap.php)
* Add global security headers (`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`).
* Ensure consistent session configuration and output buffering across entry points.

---

### API & AJAX Endpoint Security & Query Hardening

#### [MODIFY] [api/submit_batch.php](file:///c:/xampp/htdocs/ability_app_main/api/submit_batch.php)
* Replace duplicate `new mysqli(...)` with central `getConnection()`.
* Enforce PDO/MySQLi prepared statements for inserting batch items and movement records.
* Ensure response always sets `Content-Type: application/json`.

#### [MODIFY] [api/batches/approve.php](file:///c:/xampp/htdocs/ability_app_main/api/batches/approve.php)
* Centralize database connection.
* Replace raw SQL queries with prepared statement bindings.

#### [MODIFY] [api/batches/driver_verify.php](file:///c:/xampp/htdocs/ability_app_main/api/batches/driver_verify.php)
* Replace hardcoded `mysqli` instantiation with shared database connection.
* Sanitize and bind driver verification parameters.

#### [MODIFY] [api/batches/stats.php](file:///c:/xampp/htdocs/ability_app_main/api/batches/stats.php)
* Parameterize date range filters (`$date_from`, `$date_to`) to prevent SQL injection in statistics queries.

#### [MODIFY] [api/technicians/verify.php](file:///c:/xampp/htdocs/ability_app_main/api/technicians/verify.php)
* Replace string interpolation in login attempt counters with parameterized queries.

#### [MODIFY] [ajax/approve_batch.php](file:///c:/xampp/htdocs/ability_app_main/ajax/approve_batch.php)
#### [MODIFY] [ajax/bulk_approve.php](file:///c:/xampp/htdocs/ability_app_main/ajax/bulk_approve.php)
#### [MODIFY] [ajax/get_batch_details.php](file:///c:/xampp/htdocs/ability_app_main/ajax/get_batch_details.php)
#### [MODIFY] [ajax/get_batch_items.php](file:///c:/xampp/htdocs/ability_app_main/ajax/get_batch_items.php)
* Convert hardcoded `new mysqli('localhost', 'root', '', 'ability_db')` to central database connection helper.
* Standardize error handling and JSON output.

---

### Utility Scripts & Cleanup

#### [MODIFY] [get_batch_items.php](file:///c:/xampp/htdocs/ability_app_main/get_batch_items.php)
#### [MODIFY] [batch_details.php](file:///c:/xampp/htdocs/ability_app_main/batch_details.php)
#### [MODIFY] [batch_view.php](file:///c:/xampp/htdocs/ability_app_main/batch_view.php)
#### [MODIFY] [check_batches.php](file:///c:/xampp/htdocs/ability_app_main/check_batches.php)
#### [MODIFY] [check_driver.php](file:///c:/xampp/htdocs/ability_app_main/check_driver.php)
* Clean up hardcoded database connections.

---

## Verification Plan

### Automated Tests
* Run syntax verification across all modified PHP files:
  ```powershell
  Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
  ```

### Manual Verification
* Test login flow (`login.php` & `technician_login.php`).
* Test scanning and batch submission (`scan_bulk.php` -> `api/submit_batch.php`).
* Test dual-signature batch approval (`api/batches/approve.php`).
* Test driver verification (`api/batches/driver_verify.php`).
* Verify JSON API endpoint responses have valid `Content-Type: application/json` headers and proper HTTP status codes.
