# Master Calendar - Code Review

**Review Date**: 2025-12-17  
**Reviewer**: AI Code Reviewer  
**Feature**: Master Calendar (0004_PLAN.md)

---

## Executive Summary

The Master Calendar feature has been **mostly correctly implemented** according to the plan, with all core functionality present. However, there are **several critical bugs**, **data alignment issues**, and **code quality concerns** that need to be addressed before production deployment.

**Overall Status**: ⚠️ **Needs Fixes** - Functional but has bugs

---

## 1. Plan Implementation Compliance

### ✅ Correctly Implemented

1. **File Structure**: All required files exist and are in correct locations
   - ✅ `admin/calendar/master.php` - Main UI page
   - ✅ `admin/calendar/master.js` - Calendar logic (1,260 lines)
   - ✅ `api/admin/bookings/list.php` - Bookings API endpoint
   - ✅ `api/admin/bookings/details.php` - Booking details API endpoint
   - ✅ CSS styles in `admin/css/admin-style.css`

2. **Core Features**:
   - ✅ Day/Week/Month view modes implemented
   - ✅ Real-time timeline indicator (brown line with dot) for today's date
   - ✅ Booking cards with click-to-view icon
   - ✅ Staff schedule section
   - ✅ Booking details modal
   - ✅ Filter dropdowns (staff, status)
   - ✅ Date navigation (arrows, Today button)
   - ✅ Color coding for statuses (confirmed, completed, cancelled, no-show)

3. **API Endpoints**:
   - ✅ `/api/admin/bookings/list.php` - Returns bookings and staff_schedules
   - ✅ `/api/admin/bookings/details.php` - Returns full booking details with history

### ⚠️ Partially Implemented

1. **CSS Styles**: Calendar styles exist in `admin-style.css`, but some inline styles in `master.php` should be moved to CSS file for better maintainability

2. **Error Handling**: Good error handling in JavaScript, but API error responses could be more consistent

---

## 2. Critical Bugs

### 🐛 Bug #1: Data Alignment Issue - Missing `working_time` Field

**Location**: `admin/calendar/master.js:858`  
**Severity**: HIGH

**Issue**: The JavaScript code expects `schedule.working_time` but the API returns `start_time` and `end_time` separately.

```javascript
// master.js:858 - EXPECTS working_time
<p style="margin: 8px 0; font-size: 14px; color: #666;">${escapeHtml(
  schedule.working_time || ""
)}</p>
```

```php
// api/admin/bookings/list.php - RETURNS start_time and end_time separately
$schedule_row['start_time'] = ...;
$schedule_row['end_time'] = ...;
// NO working_time field!
```

**Impact**: Staff schedule cards will show empty time information.

**Fix Required**: Either:
1. Format `working_time` in the API: `$schedule_row['working_time'] = $schedule_row['start_time'] . ' - ' . $schedule_row['end_time'];`
2. Or construct it in JavaScript: `${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}`

**Recommended Fix**: Add to `api/admin/bookings/list.php` after line 245:
```php
$schedule_row['working_time'] = date('g:i A', strtotime($schedule_row['start_time'])) . ' - ' . date('g:i A', strtotime($schedule_row['end_time']));
```

---

### 🐛 Bug #2: Missing Database Connection Variable

**Location**: `api/admin/bookings/details.php:84`  
**Severity**: CRITICAL

**Issue**: The code uses `$conn` but never calls `getDBConnection()`.

```php
// Line 84 - $conn is undefined!
$stmt = $conn->prepare($sql);
```

**Impact**: Fatal PHP error - booking details modal will not work.

**Fix Required**: Add before line 84:
```php
$conn = getDBConnection();
```

---

### 🐛 Bug #3: Unused Legacy Functions

**Location**: `admin/calendar/master.js:4-136`  
**Severity**: LOW (but causes confusion)

**Issue**: Old drag-and-drop functions (`initCalendarInteractions`, `onDragStart`, `onDropReschedule`, `openBookingDetails`, `renderBookingModal`) exist at the top of the file but are never called. The actual implementation uses different function names (`viewBookingDetails`, `renderBookingDetails`).

**Impact**: Code confusion, potential maintenance issues.

**Fix Required**: Remove unused functions (lines 4-136) or document that they're for future drag-and-drop feature.

---

### 🐛 Bug #4: Potential XSS Vulnerability in Booking ID

**Location**: `admin/calendar/master.js:481-483`  
**Severity**: MEDIUM

**Issue**: Booking ID is inserted into HTML without escaping in onclick attribute.

```javascript
onclick="viewBookingDetails('${booking.booking_id}')"
```

**Impact**: If booking_id contains malicious characters (e.g., `'`), it could break the HTML or cause XSS.

**Fix Required**: Escape booking_id:
```javascript
onclick="viewBookingDetails('${escapeHtml(booking.booking_id)}')"
```

**Note**: Similar issue exists in multiple places (lines 696, 779, 806).

---

## 3. Data Alignment Issues

### Issue #1: Staff Schedule Data Structure Mismatch

**Expected by JavaScript**:
```javascript
{
  staff_name: "Jane Smith",
  working_time: "10:00 AM - 6:00 PM",  // ❌ Missing
  status: "Working"
}
```

**Returned by API**:
```php
{
  staff_name: "Jane Smith",
  start_time: "10:00:00",  // ✅ Exists
  end_time: "18:00:00",     // ✅ Exists
  work_date: "2025-12-17",  // ✅ Exists
  status: "working"          // ⚠️ Lowercase, not "Working"
}
```

**Fix**: See Bug #1 above. Also normalize status to match expected format.

---

### Issue #2: Status Case Sensitivity

**Location**: `admin/calendar/master.js:861`  
**Issue**: JavaScript checks for `schedule.status === "Working"` but API returns lowercase `"working"`.

```javascript
schedule.status === "Working" ? "#4CAF50" : "#9E9E9E"
```

**Fix**: Use case-insensitive comparison or normalize in API:
```javascript
schedule.status?.toLowerCase() === "working" ? "#4CAF50" : "#9E9E9E"
```

---

### Issue #3: Service Data Structure

**Expected**: `service.service_name` (snake_case)  
**Returned**: ✅ Correct - API returns `service_name`

**Status**: ✅ No issue - data alignment is correct here.

---

## 4. Code Quality Issues

### Issue #1: Inline Styles in PHP File

**Location**: `admin/calendar/master.php` (throughout)  
**Severity**: LOW

**Issue**: Extensive inline styles (e.g., lines 20-54, 59-75, 97-102) should be moved to CSS file for better maintainability.

**Recommendation**: Move inline styles to `admin/css/admin-style.css` and use classes.

---

### Issue #2: Large JavaScript File

**Location**: `admin/calendar/master.js` (1,260 lines)  
**Severity**: MEDIUM

**Issue**: Single file contains all calendar logic. Consider splitting into:
- `calendar-core.js` - Core state and initialization
- `calendar-views.js` - Day/Week/Month view rendering
- `calendar-modal.js` - Booking details modal
- `calendar-utils.js` - Utility functions

**Recommendation**: Refactor for better maintainability (future enhancement).

---

### Issue #3: Duplicate Code

**Location**: `admin/calendar/master.js`  
**Severity**: LOW

**Examples**:
- Button style updates repeated in multiple functions (lines 208-217, 1098-1107, 1179-1188)
- Date formatting logic could be extracted to utility function
- Status class/color mapping duplicated

**Recommendation**: Extract to helper functions.

---

### Issue #4: Missing Error Handling

**Location**: `admin/calendar/master.js:197-205`  
**Severity**: MEDIUM

**Issue**: No null checks before accessing `getElementById` results.

```javascript
document.getElementById("viewDay").classList.toggle(...);
// What if element doesn't exist?
```

**Fix**: Add null checks or use optional chaining:
```javascript
document.getElementById("viewDay")?.classList.toggle(...);
```

---

### Issue #5: Inconsistent Error Handling

**Location**: API endpoints  
**Severity**: LOW

**Issue**: `api/admin/bookings/list.php` uses `ErrorHandler` class, but `api/admin/bookings/details.php` uses manual error handling. Should be consistent.

**Recommendation**: Standardize on `ErrorHandler` class for all API endpoints.

---

## 5. Syntax and Style Issues

### Issue #1: Inconsistent Quote Usage

**Location**: Throughout JavaScript files  
**Severity**: LOW

**Issue**: Mix of single and double quotes. Should standardize (prefer single quotes per plan).

**Example**: 
- Line 481: Uses double quotes in template literal
- Line 506: Uses single quotes in template literal

---

### Issue #2: Missing Semicolons

**Location**: `admin/calendar/master.js`  
**Severity**: LOW

**Issue**: Some lines missing semicolons (e.g., line 527). While JavaScript allows this, it's inconsistent with the rest of the codebase.

---

### Issue #3: Magic Numbers

**Location**: `admin/calendar/master.js:606`  
**Severity**: LOW

**Issue**: Hard-coded pixel value `left: 78px` for timeline dot position.

```javascript
left: 78px;  // Magic number - what if layout changes?
```

**Recommendation**: Use CSS variable or calculate dynamically.

---

## 6. Performance Concerns

### Issue #1: Multiple DOM Queries

**Location**: `admin/calendar/master.js`  
**Severity**: LOW

**Issue**: `getElementById` called multiple times for same element (e.g., `viewDay`, `viewWeek`, `viewMonth` in `switchView` function).

**Recommendation**: Cache DOM references.

---

### Issue #2: Large HTML String Building

**Location**: `admin/calendar/master.js:442-523`  
**Severity**: LOW

**Issue**: Building large HTML strings with template literals. For very large datasets, this could be slow.

**Recommendation**: Consider using DocumentFragment or virtual DOM for better performance (future enhancement).

---

## 7. Security Concerns

### Issue #1: XSS in Booking ID (See Bug #4)

**Severity**: MEDIUM  
**Status**: Needs fixing

---

### Issue #2: SQL Injection Protection

**Status**: ✅ GOOD - All queries use prepared statements with parameter binding.

---

### Issue #3: Authentication Checks

**Status**: ✅ GOOD - All API endpoints check authentication before processing.

---

## 8. Testing Gaps

### Missing Test Coverage

1. **Edge Cases**:
   - What happens when no bookings exist?
   - What happens when staff_schedules is empty?
   - What happens when API returns error?
   - What happens when booking_id is invalid?

2. **Date Edge Cases**:
   - Month view with bookings spanning month boundaries
   - Week view with bookings at week boundaries
   - Timezone issues (currently handled with `T12:00:00` workaround)

3. **Filter Combinations**:
   - Multiple filters applied simultaneously
   - Filter cleared after being set

---

## 9. Documentation Issues

### Missing Documentation

1. **API Response Format**: Not fully documented in code comments
2. **Function JSDoc**: Most functions lack JSDoc comments
3. **Error Codes**: Error codes not documented in API responses

---

## 10. Recommendations

### High Priority Fixes

1. ✅ **Fix Bug #2**: Add `$conn = getDBConnection();` in `details.php`
2. ✅ **Fix Bug #1**: Add `working_time` formatting in API or JavaScript
3. ✅ **Fix Bug #4**: Escape booking_id in all onclick handlers
4. ✅ **Fix Issue #2**: Normalize status comparison (case-insensitive)

### Medium Priority

5. Move inline styles to CSS file
6. Add null checks for DOM elements
7. Standardize error handling across API endpoints

### Low Priority (Future Enhancements)

8. Split large JavaScript file into modules
9. Extract duplicate code to helper functions
10. Add JSDoc comments
11. Implement caching for DOM references
12. Add unit tests

---

## 11. Positive Aspects

### ✅ Good Practices

1. **Error Handling**: Comprehensive error handling in JavaScript with user-friendly messages
2. **Security**: SQL injection protection via prepared statements
3. **Authentication**: Proper authentication checks in all API endpoints
4. **Code Organization**: Logical function grouping and clear naming
5. **Responsive Design**: Mobile-friendly CSS with media queries
6. **Accessibility**: ARIA attributes and semantic HTML
7. **Real-time Features**: Timeline indicator implementation is well done

---

## 12. Conclusion

The Master Calendar feature is **functionally complete** and follows the plan well. However, **critical bugs** (especially Bug #2 - missing database connection) must be fixed before deployment. The data alignment issues (Bug #1) will cause display problems that need immediate attention.

**Recommendation**: Fix high-priority bugs (#1, #2, #4) before merging to main branch. Medium and low-priority issues can be addressed in follow-up PRs.

**Estimated Fix Time**: 2-4 hours for high-priority fixes.

---

## Appendix: Code Snippets for Fixes

### Fix #1: Add working_time to API

```php
// In api/admin/bookings/list.php, after line 245:
$schedule_row['staff_name'] = $schedule_row['staff_first_name'] . ' ' . $schedule_row['staff_last_name'];
$schedule_row['working_time'] = date('g:i A', strtotime($schedule_row['start_time'])) . ' - ' . date('g:i A', strtotime($schedule_row['end_time']));
unset($schedule_row['staff_first_name']);
unset($schedule_row['staff_last_name']);
```

### Fix #2: Add database connection

```php
// In api/admin/bookings/details.php, before line 84:
$conn = getDBConnection();
$stmt = $conn->prepare($sql);
```

### Fix #3: Escape booking_id

```javascript
// Replace all instances of:
onclick="viewBookingDetails('${booking.booking_id}')"
// With:
onclick="viewBookingDetails('${escapeHtml(booking.booking_id)}')"
```

### Fix #4: Case-insensitive status check

```javascript
// In renderStaffSchedules, line 861:
schedule.status?.toLowerCase() === "working" ? "#4CAF50" : "#9E9E9E"
```

