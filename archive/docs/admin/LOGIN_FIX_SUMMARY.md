# Admin Login Redirect Fix

## Issue
User cannot redirect to admin dashboard after login.

## Root Causes Identified
1. Session configuration conflicts between login.php and auth_check.php
2. Session cookie path might not be set correctly
3. Redirect URL might be incorrect or not accessible
4. Session might not be persisting properly

## Fixes Applied

### 1. Session Configuration (`api/admin/auth/login.php`)
- ✅ Added `configureSecureSession()` call BEFORE `session_start()`
- ✅ Ensured session configuration happens before session starts
- ✅ Added `session_write_close()` to ensure session is saved before redirect

### 2. Session Cookie Path (`admin/includes/security_utils.php`)
- ✅ Set `session.cookie_path` to `/` so session is accessible across entire site
- ✅ Changed `SameSite` from `Strict` to `Lax` for better compatibility
- ✅ Fixed `configureSecureSession()` to only configure when session hasn't started

### 3. Redirect URL (`admin/login.js`)
- ✅ Improved redirect URL handling with fallbacks
- ✅ Changed to use `window.location.replace()` instead of `window.location.href` to prevent back button issues
- ✅ Added better error logging for debugging
- ✅ Reduced redirect timeout from 1000ms to 500ms

### 4. Login Response (`api/admin/auth/login.php`)
- ✅ Added redirect URL at both `data.redirect` and top-level `redirect` for compatibility
- ✅ Ensured consistent redirect URL format

## Testing Steps

1. **Clear browser cookies and cache**
2. **Open admin login page**: `http://localhost/Lumiere-beauty-salon/admin/login.html`
3. **Enter credentials**:
   - Phone: `12 345 6789` or `60123456789`
   - Password: `Admin@123`
4. **Click "ADMIN LOGIN"**
5. **Should redirect to**: `http://localhost/Lumiere-beauty-salon/admin/index.php`

## Debugging

If redirect still doesn't work:

1. **Check browser console** for JavaScript errors
2. **Check Network tab** to see login API response
3. **Check if session cookie is set**:
   - Open DevTools > Application > Cookies
   - Look for `LUMIERE_ADMIN_SESSION` cookie
4. **Check PHP session files**:
   - Location: `C:\xampp\tmp\` (or your PHP session save path)
   - Look for files starting with `sess_`

## Common Issues

### Issue: Session not persisting
**Solution**: Check that `session.cookie_path` is set to `/` and cookies are enabled in browser

### Issue: Redirect loops
**Solution**: Check that `auth_check.php` is correctly detecting the session

### Issue: 404 on redirect
**Solution**: Verify the redirect URL path matches your actual file structure

## Files Modified

1. `api/admin/auth/login.php` - Session configuration and redirect URL
2. `admin/login.js` - Redirect handling
3. `admin/includes/security_utils.php` - Session cookie path configuration

