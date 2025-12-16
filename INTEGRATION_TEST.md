# Integration Test Checklist

**Before Presentation - Quick Smoke Tests**

## Prerequisites

- [ ] XAMPP Apache and MySQL running
- [ ] Database `salon` exists with tables: `Staff`, `Customers`, etc.
- [ ] Clear browser cookies for `localhost` (to test fresh sessions)

---

## 1. User Module (J's Module)

### Homepage & Registration

- [ ] Open: http://localhost/Lumiere-beauty-salon/user/index.php
  - [ ] Page loads without errors
  - [ ] Floating buttons (Staff/Admin) visible and clickable
  - [ ] Responsive layout works (resize to 375px, 768px)

### User Login/Registration

- [ ] Open: http://localhost/Lumiere-beauty-salon/user/login.html
  - [ ] Registration form (4 steps) loads
  - [ ] Phone number formatter works (type digits, see formatting)
  - [ ] Password strength indicator works
  - [ ] Can submit registration
  - [ ] Login flow works after registration

---

## 2. Staff Module (P's Module)

### Staff Login

- [ ] Open: http://localhost/Lumiere-beauty-salon/staff/login.php
  - [ ] Hard refresh (Ctrl+F5)
  - [ ] Phone input formats correctly (no leading 0)
  - [ ] Login button calls `validateStaffLogin()` without errors
  - [ ] Successful login redirects to `dashboard.html`

### Staff Dashboard & Performance

- [ ] After login, open: http://localhost/Lumiere-beauty-salon/staff/performance.html
  - [ ] No 401 errors in console
  - [ ] APIs return data:
    - `staff/api/staff.php` (profile)
    - `staff/api/performance.php?period=month`
    - `staff/api/dashboard.php` (notifications)
  - [ ] Charts/data load correctly

---

## 3. Admin Module (Your Module)

### Admin Login (.php version)

- [ ] Open: http://localhost/Lumiere-beauty-salon/admin/login.php
  - [ ] Hard refresh (Ctrl+F5)
  - [ ] Phone input (`#loginPhone`) formats correctly
  - [ ] Login button works without "null value" errors
  - [ ] Successful login redirects to admin dashboard

### Admin Login (.html version - if used)

- [ ] Open: http://localhost/Lumiere-beauty-salon/admin/login.html
  - [ ] Hard refresh (Ctrl+F5)
  - [ ] Phone input (`#loginPhone`) formats correctly
  - [ ] Login button works (now loads `admin/login.js`)
  - [ ] No "Cannot read properties of null" errors

### Admin Dashboard

- [ ] Open: http://localhost/Lumiere-beauty-salon/admin/dashboard.php (or index.php)
  - [ ] No "SyntaxError: Unexpected token '<'" errors
  - [ ] APIs return JSON (not HTML errors):
    - Today's appointments
    - Recent activity
    - Top services
  - [ ] KPI cards load data
  - [ ] Navigation sidebar works

---

## 4. Cross-Module Integration

### Parallel Login Sessions

- [ ] Open two tabs:
  - Tab 1: Admin login → successful
  - Tab 2: Staff login → successful
  - [ ] Both sessions work simultaneously (no logout from one affects the other)

### Responsive Design

- [ ] Resize browser to:
  - [ ] 375px (mobile) - CSS applies mobile styles, no parser errors
  - [ ] 768px (tablet) - layout adjusts
  - [ ] 1920px (desktop) - full layout
- [ ] Check console for CSS errors (none expected after fixing duplicate media queries)

---

## 5. Quick API Smoke Tests

### Admin API Auth

- [ ] After admin login, check Network tab:
  - [ ] Cookie `admin_session` is sent
  - [ ] Admin APIs recognize session (no 401)

### Staff API Auth

- [ ] After staff login, check Network tab:
  - [ ] Cookie `staff_session` is sent
  - [ ] Staff APIs recognize session (no 401)

---

## Known Non-Issues (Ignore)

- ✅ `/hybridaction/zybTrackerStatisticsAction ... 404` - Browser extension noise, not your app
- ✅ `PC plat undefined` - Browser extension logging, safe to ignore

---

## Pre-Presentation Quick Run (5 min)

1. Start XAMPP (Apache, MySQL)
2. Open 3 tabs:
   - Admin login → dashboard
   - Staff login → performance page
   - User homepage → registration
3. Verify no console errors (except browser extension noise)
4. Test one booking flow end-to-end (user books → staff sees appointment → admin sees analytics)
5. Resize browser to mobile view, confirm responsive layout works

---

## If Issues Found

- **Admin login null error**: Check `#loginPhone` ID exists in HTML, `admin/login.js` loaded
- **Staff 401 errors**: Verify `staff/config.php` uses `session_name('staff_session')`
- **CSS parse error**: Check no duplicate `@media (max-width: 768px)` blocks in `admin/css/responsive-mobile.css`
- **Dashboard JSON errors**: Check admin API auth headers, session alignment

---

**Status**: Integration branch `integration_2.0` pushed and ready for testing.
**Next**: Refine admin module UI/features while integration base is stable.
