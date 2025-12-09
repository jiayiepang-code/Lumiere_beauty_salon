# Admin Authentication System Setup Guide

## Overview

The admin authentication system provides secure login functionality with the following features:

- **Secure Password Hashing**: Uses bcrypt (PASSWORD_BCRYPT) for password storage
- **Session Management**: 30-minute session timeout with automatic renewal
- **CSRF Protection**: Token-based CSRF validation for all state-changing operations
- **Rate Limiting**: Maximum 5 login attempts per 15 minutes per phone number
- **Login Logging**: Tracks all successful admin logins with IP addresses

## Installation Steps

### 1. Set Up Database Tables

Run the setup script to create required tables:

**Option A: Using PHP Script (Recommended)**

```
Navigate to: http://localhost/admin/includes/setup_auth.php
```

**Option B: Using MySQL Command Line**

```bash
mysql -u root -p lumiere_salon_db < admin/includes/setup_auth_tables.sql
```

This will create:

- `Login_Attempts` table (for rate limiting)
- `Admin_Login_Log` table (for login tracking)
- Default admin account with credentials:
  - Phone: +60 12 345 6789
  - Password: Admin@123

### 2. Verify Database Connection

Ensure `php/connection.php` has correct database credentials:

```php
$servername = "localhost";
$username = "root";
$password = ""; // XAMPP default
$dbname = "lumiere_salon_db";
```

### 3. Test Login

1. Navigate to: `http://localhost/admin/login.html`
2. Enter default credentials:
   - Phone: 12 345 6789 (or 60123456789)
   - Password: Admin@123
3. Click "ADMIN LOGIN"
4. You should be redirected to the admin dashboard

### 4. Change Default Password

**IMPORTANT**: Change the default admin password immediately!

Use the password hashing utility:

```
Navigate to: http://localhost/admin/includes/hash_password.php
```

Then update the database:

```sql
UPDATE Staff
SET password = '<generated_hash>'
WHERE staff_email = 'admin@lumiere.com';
```

## File Structure

```
admin/
├── login.html              # Admin login page
├── login.js                # Login form handling and API calls
├── index.php               # Admin dashboard (requires authentication)
├── includes/
│   ├── auth_check.php      # Session management and auth helpers
│   ├── setup_auth.php      # Database setup script
│   ├── setup_auth_tables.sql  # SQL schema for auth tables
│   ├── hash_password.php   # Password hashing utility
│   └── AUTH_SETUP_README.md   # This file

api/admin/
├── auth/
│   ├── login.php           # Login API endpoint
│   └── logout.php          # Logout API endpoint
└── includes/
    └── csrf_validation.php # CSRF token validation helpers
```

## Usage in Admin Pages

### Protecting Admin Pages

Add this to the top of any admin PHP page:

```php
<?php
require_once 'includes/auth_check.php';
requireAdminAuth();

// Get current admin data
$admin = getCurrentAdmin();
$csrf_token = getCSRFToken();
?>
```

### Making Authenticated API Calls

Include CSRF token in all API requests:

```javascript
const csrfToken = sessionStorage.getItem("csrf_token");

fetch("/api/admin/services/create.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-CSRF-Token": csrfToken,
  },
  body: JSON.stringify({
    service_name: "Haircut",
    // ... other data
  }),
});
```

### Protecting API Endpoints

Add this to the top of API endpoints:

```php
<?php
require_once '../includes/csrf_validation.php';

// Require authentication
requireAdminAuthAPI();

// Require CSRF token for POST/PUT/DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireCSRFToken();
}

// Your API logic here
?>
```

## Security Features

### 1. Password Requirements

Passwords must meet these criteria:

- Minimum 8 characters
- At least 1 uppercase letter
- At least 1 number
- At least 1 symbol (.\_-?@#$%^)

### 2. Rate Limiting

- Maximum 5 failed login attempts per 15 minutes
- Tracked by phone number
- Automatic cleanup of old attempts
- Returns HTTP 429 (Too Many Requests) when limit exceeded

### 3. Session Security

- Session timeout: 30 minutes of inactivity
- Session ID regeneration on login
- HTTP-only cookies (prevents XSS attacks)
- Secure cookies in production (HTTPS only)
- Strict session mode enabled

### 4. CSRF Protection

- Unique token generated on login
- Token stored in session and returned to client
- Must be included in all state-changing requests
- Validated using timing-safe comparison

### 5. Input Validation

- Phone number format validation (Malaysia format)
- SQL injection prevention (prepared statements)
- XSS prevention (htmlspecialchars on output)

## Troubleshooting

### Login fails with "Invalid credentials"

1. Verify phone number format (01X-XXXXXXX or 60XXXXXXXXX)
2. Check if admin account exists in Staff table
3. Verify password hash is correct
4. Check if account is active (is_active = TRUE)
5. Verify role is set to 'admin'

### "Too many login attempts" error

Wait 15 minutes or manually clear attempts:

```sql
DELETE FROM Login_Attempts WHERE phone = '60123456789';
```

### Session expires immediately

1. Check PHP session configuration
2. Verify session files directory is writable
3. Check if cookies are enabled in browser
4. Verify session timeout settings

### CSRF validation fails

1. Ensure CSRF token is stored in sessionStorage
2. Include token in request headers or body
3. Check if session is still active
4. Verify token matches session token

## Production Deployment

Before deploying to production:

1. **Enable HTTPS**:

   ```php
   ini_set('session.cookie_secure', 1);
   ```

2. **Update database credentials** in `php/connection.php`

3. **Change default admin password**

4. **Set up error logging**:

   ```php
   ini_set('display_errors', 0);
   ini_set('log_errors', 1);
   ini_set('error_log', '/path/to/logs/php_errors.log');
   ```

5. **Configure session storage** (use database or Redis for multiple servers)

6. **Set up automated cleanup** for old login attempts:
   ```sql
   -- Run daily via cron
   DELETE FROM Login_Attempts
   WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 DAY);
   ```

## API Reference

### POST /api/admin/auth/login.php

**Request:**

```json
{
  "phone": "60123456789",
  "password": "Admin@123"
}
```

**Success Response (200):**

```json
{
  "success": true,
  "admin_email": "admin@lumiere.com",
  "first_name": "Admin",
  "last_name": "User",
  "csrf_token": "abc123...",
  "redirect": "/admin/index.php"
}
```

**Error Response (401):**

```json
{
  "success": false,
  "error": {
    "code": "AUTH_FAILED",
    "message": "Invalid credentials"
  }
}
```

### POST /api/admin/auth/logout.php

**Success Response (200):**

```json
{
  "success": true,
  "message": "Logged out successfully",
  "redirect": "/admin/login.html"
}
```

## Support

For issues or questions, refer to:

- Design document: `.kiro/specs/admin-module/design.md`
- Requirements: `.kiro/specs/admin-module/requirements.md`
- Tasks: `.kiro/specs/admin-module/tasks.md`
