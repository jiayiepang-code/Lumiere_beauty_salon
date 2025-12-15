# Quick Test Checklist - Conflict Detection Feature

## Your Test Scenario (As You Described)

### Step 1: Create Booking ✅
- [ ] Create a booking for **Nisha Kumar** on **December 17, 2025**
- [ ] Use **your own email** as customer
- [ ] Set booking status to **'confirmed'**
- [ ] Note: Booking time, services, booking ID

### Step 2: Create Leave Request ✅
- [ ] As Nisha Kumar, submit leave request for **December 17, 2025**
- [ ] Set status to **'pending'**
- [ ] Note: Leave request ID

### Step 3: Test Approval Flow ✅
- [ ] Log in as Admin
- [ ] Go to **Leave Requests** page
- [ ] Find Nisha's pending request for Dec 17
- [ ] Click **"Approve"** button

### Step 4: Verify Warning Dialog ✅
- [ ] **Warning dialog appears** (SweetAlert2)
- [ ] Shows **your booking details**:
  - [ ] Your name
  - [ ] Date: Dec 17, 2025
  - [ ] Time
  - [ ] Services
- [ ] Message explains customers will be notified
- [ ] Two buttons: "Proceed Anyway" and "Cancel"

### Step 5: Proceed with Approval ✅
- [ ] Click **"Proceed Anyway"** (or "Confirm")
- [ ] Confirm in final confirmation dialog

### Step 6: Verify Database Updates ✅
Run these SQL queries to verify:

```sql
-- 1. Check leave request is approved
SELECT status FROM leave_requests WHERE id = [your_leave_request_id];
-- Expected: status = 'approved'

-- 2. Check staff schedule is updated
SELECT * FROM staff_schedule 
WHERE staff_email = 'nisha@example.com' 
AND work_date = '2025-12-17';
-- Expected: One row with status = 'leave', times 10:00-22:00

-- 3. Check booking remarks updated
SELECT remarks, status FROM Booking WHERE booking_id = [your_booking_id];
-- Expected: remarks contains "Staff on leave" or similar, status may be 'needs_reschedule'
```

### Step 7: Verify Email Received ✅
- [ ] Check **your email inbox** (the one used in booking)
- [ ] Email arrives within **1-2 minutes**
- [ ] Email subject mentions booking/rescheduling
- [ ] Email contains:
  - [ ] Your name
  - [ ] Booking date: Dec 17, 2025
  - [ ] Booking time
  - [ ] Services booked
  - [ ] Staff name: Nisha Kumar
  - [ ] Leave date: Dec 17, 2025
  - [ ] Message about rescheduling/reassignment
  - [ ] Salon contact info

### Step 8: Verify Success Message ✅
- [ ] Success dialog appears after approval
- [ ] Message says: "Leave request has been approved."
- [ ] Message says: "1 customer(s) have been notified via email."
- [ ] Leave request row **disappears** from pending table
- [ ] KPI cards update (Pending decreases, Approved increases)

---

## Additional Quick Tests

### Test: No Conflicts (Normal Approval)
- [ ] Create leave request for a date with **NO bookings**
- [ ] Click Approve
- [ ] **Expected**: No warning dialog, goes straight to confirmation
- [ ] **Expected**: Simple success message (no conflict count)

### Test: Rejection (No Conflict Check)
- [ ] Create leave request with conflicts
- [ ] Click **"Reject"** instead of Approve
- [ ] **Expected**: No warning dialog
- [ ] **Expected**: No emails sent
- [ ] **Expected**: Only leave_requests.status changes to 'rejected'

### Test: Cancel Warning Dialog
- [ ] Click Approve, see warning dialog
- [ ] Click **"Cancel"** in warning dialog
- [ ] **Expected**: Dialog closes, leave request still pending
- [ ] **Expected**: No database changes

---

## What to Check If Something Goes Wrong

### ❌ Warning Dialog Not Showing?
- Open browser **Developer Tools** (F12)
- Check **Console** tab for JavaScript errors
- Check **Network** tab - look for call to `check_conflicts.php`
- Verify API returns JSON with `has_conflicts: true`

### ❌ Email Not Received?
- Check **Spam/Junk** folder
- Wait 2-3 minutes (SMTP can be slow)
- Check `config/email_config.php` has correct SMTP settings
- Verify your email address in booking record is correct

### ❌ Database Not Updating?
- Check browser console for errors
- Check Network tab - API call to `update.php` should return success
- Verify staff email matches between leave request and booking
- Check database connection is working

### ❌ Booking Not Marked?
- Run SQL: `SELECT remarks, status FROM Booking WHERE booking_id = [id]`
- Verify booking status was 'confirmed' before approval
- Check if booking date matches leave date exactly

---

## Quick Verification Commands

### Check All Conflicts for a Leave Request
```sql
SELECT 
    b.booking_id,
    b.customer_email,
    b.status,
    bs.service_date,
    bs.service_time,
    bs.staff_email
FROM Booking b
JOIN Booking_Service bs ON b.booking_id = bs.booking_id
WHERE bs.staff_email = 'nisha@example.com'
AND bs.service_date = '2025-12-17'
AND b.status IN ('confirmed', 'completed');
```

### Check Leave Request Status
```sql
SELECT id, status, staff_email, start_date, end_date 
FROM leave_requests 
WHERE staff_email = 'nisha@example.com' 
ORDER BY created_at DESC 
LIMIT 5;
```

### Check Staff Schedule
```sql
SELECT * FROM staff_schedule 
WHERE staff_email = 'nisha@example.com' 
AND work_date >= '2025-12-17' 
ORDER BY work_date;
```

---

## Test Results Template

**Date**: _______________
**Tester**: _______________

| Test | Expected | Actual | Pass/Fail | Notes |
|------|----------|--------|-----------|-------|
| Warning Dialog Shows | ✅ | ⬜ | ⬜ | |
| Booking Details Correct | ✅ | ⬜ | ⬜ | |
| Database Updates | ✅ | ⬜ | ⬜ | |
| Email Received | ✅ | ⬜ | ⬜ | |
| Email Content Correct | ✅ | ⬜ | ⬜ | |
| Success Message Shows | ✅ | ⬜ | ⬜ | |
| UI Updates Correctly | ✅ | ⬜ | ⬜ | |

**Issues Found**: 
- 

**Screenshots**: (attach if needed)

---

## Next Steps After Testing

1. ✅ If all tests pass → Feature is working correctly!
2. ❌ If issues found → Document them and report
3. 🔄 If edge cases discovered → Add to test plan
4. 📝 Update test results in this checklist

