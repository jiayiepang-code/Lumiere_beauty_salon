# Archive Folder

This folder contains files that have been archived because they are:
- Old/replaced implementations
- Test/debug files (not needed in production)
- One-time setup scripts (no longer needed after initial setup)
- Duplicate/redundant API endpoints
- Documentation files (reference only, not needed for runtime)

## Folder Structure

### `/old_connections/`
Contains old database connection files that have been replaced by `config/db_connect.php`:
- `php/connection.php` - Old connection file
- `php/db.php` - Old connection file

### `/test_files/`
Development and debugging files:
- `test_login_debug.php` - Login debugging script
- `admin/test_admin_login.php` - Admin login test script
- `admin/debug_login.html` - Debug login page
- `admin/test-responsive.html` - Responsive design test
- `admin/test-validation.html` - Validation test
- `check_admin.php` - Admin verification script
- `check_table_structure.php` - Database structure checker
- `verify_admin.php` - Admin verification utility
- `admin/includes/test_auth.php` - Authentication test script

### `/duplicate_apis/`
Old/redundant API implementations replaced by newer versions:
- `api/admin/staff/crud.php` - Replaced by individual create.php, update.php, delete.php, details.php
- `api/admin/services/crud.php` - Replaced by individual create.php, update.php, delete.php, list.php
- `api/admin/staff/toggle_active.php` - Duplicate of toggle_status.php
- `api/admin/services/toggle_active.php` - Duplicate of toggle_status.php

### `/setup_files/`
One-time setup scripts (not needed after initial setup):
- `admin/includes/setup_auth.php` - Authentication setup script
- `admin/includes/setup_auth_tables.sql` - Auth tables SQL schema
- `admin/includes/hash_password.php` - Password hashing utility
- `setup_admin_auth.php` - Admin auth setup script

### `/old_scripts/`
Old script versions:
- `send_reminder_script.php` - Old reminder email script (replaced by `cron/send_reminder_emails.php`)

### `/docs/`
Documentation files (reference only):
- `admin/LOGIN_FIX_SUMMARY.md` - Login fix documentation
- `admin/RESPONSIVE_DESIGN_GUIDE.md` - Responsive design guide
- `admin/UI_IMPROVEMENTS_SUMMARY.md` - UI improvements summary
- `admin/includes/AUTH_SETUP_README.md` - Auth setup documentation
- `admin/includes/ERROR_HANDLING_GUIDE.md` - Error handling guide
- `admin/includes/IMPLEMENTATION_SUMMARY.md` - Implementation summary
- `admin/includes/PERFORMANCE_SECURITY_GUIDE.md` - Performance and security guide

## Note

These files are kept for reference purposes only. They should not be used in the active codebase. If you need to restore any file, you can move it back from this archive folder.

**Archived on:** $(Get-Date -Format "yyyy-MM-dd")


