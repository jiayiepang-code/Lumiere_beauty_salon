# Leave Request Approval - Conflict Detection Test Plan

## Overview
This test plan verifies the conflict detection and customer notification system when approving staff leave requests.

---

## Prerequisites

### Test Data Setup
1. **Staff Member**: Nisha Kumar (staff_email must exist in `Staff` table)
2. **Test Dates**: December 17, 2025 (or current date + appropriate offset)
3. **Test Customer**: Your own email address (for receiving conflict notification emails)
4. **Admin Account**: Logged in admin user with permission to approve leave requests

### Pre-Test Checklist
- [ ] Admin portal is accessible and functional
- [ ] Leave Requests page loads correctly
- [ ] Email service is configured and working (check `config/email_config.php`)
- [ ] Database connection is working
- [ ] SMTP credentials are valid (for email testing)

---

## Test Scenarios

### Test Case 1: Conflict Detection - Warning Dialog Display

**Objective**: Verify that admin sees a warning dialog when approving leave with conflicting bookings.

**Steps**:
1. **Create a Confirmed Booking**:
   - Create a booking for Nisha Kumar on December 17, 2025
   - Set booking status to `'confirmed'`
   - Assign yourself as the customer (use your email)
   - Note the booking ID and service details (time, services)

2. **Create Leave Request**:
   - As Nisha Kumar (staff member), submit a leave request for December 17, 2025
   - Set status to `'pending'`
   - Note the leave request ID

3. **Attempt Approval**:
   - Log in as Admin
   - Navigate to Leave Requests page (`admin/leave_requests/index.php`)
   - Locate Nisha Kumar's pending leave request for Dec 17
   - Click the **"Approve"** button

**Expected Results**:
- [ ] **Before confirmation dialog**: A conflict check API call is made to `check_conflicts.php`
- [ ] **Warning Dialog Appears**: SweetAlert2 dialog shows:
  - Title: "⚠️ Conflicting Bookings Detected" (or similar)
  - List of conflicting bookings with:
    - Customer name (your name)
    - Booking date: December 17, 2025
    - Booking time
    - Services booked
  - Message explaining customers will be notified
  - Two buttons: "Proceed Anyway" and "Cancel"

**Verification Points**:
- [ ] Dialog is styled consistently with admin portal
- [ ] All booking details are accurate and readable
- [ ] No JavaScript errors in browser console
- [ ] Network tab shows successful API call to `check_conflicts.php`

---

### Test Case 2: Approval with Conflicts - Database Updates

**Objective**: Verify that approving leave with conflicts correctly updates all database records.

**Steps**:
1. Follow steps from Test Case 1 to reach the warning dialog
2. Click **"Proceed Anyway"** (or equivalent) in the warning dialog
3. Confirm the approval in the confirmation dialog

**Expected Results**:
- [ ] **Leave Request Status**: `leave_requests.status` changes from `'pending'` to `'approved'`
- [ ] **Staff Schedule**: New record(s) in `staff_schedule` table:
  - `staff_email` = Nisha Kumar's email
  - `work_date` = 2025-12-17
  - `start_time` = 10:00:00
  - `end_time` = 22:00:00
  - `status` = 'leave'
- [ ] **Booking Remarks**: Conflicting booking's `remarks` field is updated with:
  - Text containing "Staff on leave" or "needs rescheduling/reassignment"
- [ ] **Booking Status**: If booking was `'confirmed'`, it changes to `'needs_reschedule'`
- [ ] **Transaction Integrity**: All updates happen atomically (no partial updates)

**Verification SQL Queries**:
```sql
-- Check leave request status
SELECT id, status, staff_email, start_date, end_date 
FROM leave_requests 
WHERE id = [leave_request_id];

-- Check staff schedule
SELECT * FROM staff_schedule 
WHERE staff_email = 'nisha@example.com' 
AND work_date = '2025-12-17';

-- Check booking remarks and status
SELECT booking_id, status, remarks, customer_email 
FROM Booking 
WHERE booking_id = [booking_id];
```

---

### Test Case 3: Email Notification - Customer Receives Email

**Objective**: Verify that customer receives conflict notification email.

**Steps**:
1. Complete Test Case 2 (approve leave with conflicts)
2. Check your email inbox (the email used in the booking)

**Expected Results**:
- [ ] **Email Received**: Email arrives within 1-2 minutes
- [ ] **Email Subject**: Contains "Booking Update" or "Rescheduling Required" (or similar)
- [ ] **Email Content Includes**:
  - Your name (customer name)
  - Booking details:
    - Date: December 17, 2025
    - Time: [your booking time]
    - Services: [services you booked]
  - Staff member name: Nisha Kumar
  - Leave date range: December 17, 2025
  - Message explaining booking needs rescheduling/reassignment
  - Salon contact information (phone, address, location)
- [ ] **Email Format**: HTML email renders correctly (check in email client)
- [ ] **Plain Text Version**: Plain text version is also included

**Verification Points**:
- [ ] Check spam/junk folder if email not received
- [ ] Verify email sender is correct (from `config/email_config.php`)
- [ ] Check email headers for proper delivery

---

### Test Case 4: Success Message - Admin Feedback

**Objective**: Verify admin receives appropriate success feedback after approval.

**Steps**:
1. Complete Test Case 2 (approve leave with conflicts)
2. Observe the success message displayed

**Expected Results**:
- [ ] **Success Dialog**: SweetAlert2 success dialog appears
- [ ] **Message Content**:
  - "Leave request has been approved."
  - "X customer(s) have been notified via email." (where X = number of conflicts)
  - If emails failed: "⚠️ X email(s) failed to send."
- [ ] **UI Update**: Leave request row is removed from the pending table
- [ ] **KPI Cards Update**: Approved count increases, Pending count decreases

**Verification Points**:
- [ ] Message is clear and informative
- [ ] Conflict count matches actual number of conflicting bookings
- [ ] Email count matches number of emails sent

---

### Test Case 5: Approval Without Conflicts - Normal Flow

**Objective**: Verify normal approval flow when no conflicts exist.

**Steps**:
1. **Create Leave Request**:
   - Create a leave request for Nisha Kumar on a date with NO existing bookings (e.g., December 20, 2025)
   - Set status to `'pending'`

2. **Approve Leave**:
   - Navigate to Leave Requests page
   - Click **"Approve"** on the request

**Expected Results**:
- [ ] **No Warning Dialog**: Conflict check returns no conflicts, proceeds directly to confirmation
- [ ] **Standard Confirmation**: Normal confirmation dialog appears (not conflict warning)
- [ ] **Database Updates**: Leave request and staff_schedule updated correctly
- [ ] **Success Message**: Simple success message (no conflict/email count)
- [ ] **No Email Sent**: No customer emails sent (no conflicts)

**Verification Points**:
- [ ] Approval process is faster (no conflict check delay)
- [ ] No unnecessary API calls to conflict endpoint

---

### Test Case 6: Rejection - No Conflict Check

**Objective**: Verify that rejecting leave does not trigger conflict checks or emails.

**Steps**:
1. Create a leave request with conflicts (same as Test Case 1)
2. Navigate to Leave Requests page
3. Click **"Reject"** button

**Expected Results**:
- [ ] **No Conflict Check**: No API call to `check_conflicts.php`
- [ ] **Standard Confirmation**: Normal confirmation dialog
- [ ] **Database Update**: Only `leave_requests.status` changes to `'rejected'`
- [ ] **No Schedule Changes**: `staff_schedule` table unchanged
- [ ] **No Booking Changes**: Conflicting bookings remain unchanged
- [ ] **No Emails Sent**: No customer notifications

**Verification Points**:
- [ ] Network tab shows no conflict check API call
- [ ] Booking status and remarks remain unchanged

---

### Test Case 7: Multiple Conflicts - Multiple Bookings

**Objective**: Verify system handles multiple conflicting bookings correctly.

**Steps**:
1. **Create Multiple Bookings**:
   - Create 3-5 confirmed bookings for Nisha Kumar on December 17, 2025
   - Use different customer emails (or same email with different times)
   - Ensure all are `'confirmed'` status

2. **Create Leave Request**:
   - Create leave request for Nisha Kumar on December 17, 2025

3. **Approve Leave**:
   - Click "Approve" and proceed through warning dialog

**Expected Results**:
- [ ] **Warning Dialog**: Shows all conflicting bookings in the list
- [ ] **All Bookings Marked**: All conflicting bookings have remarks updated
- [ ] **All Emails Sent**: Emails sent to all affected customers
- [ ] **Success Message**: Shows correct total conflict count (e.g., "5 customer(s) have been notified")

**Verification Points**:
- [ ] All bookings are listed in warning dialog
- [ ] All customer emails are sent successfully
- [ ] Database shows all bookings marked correctly

---

### Test Case 8: Date Range Conflicts - Multiple Days

**Objective**: Verify conflict detection works for multi-day leave requests.

**Steps**:
1. **Create Bookings Across Date Range**:
   - Create bookings for Nisha Kumar on:
     - December 17, 2025 (start of leave)
     - December 18, 2025 (middle of leave)
     - December 19, 2025 (end of leave)

2. **Create Leave Request**:
   - Create leave request for December 17-19, 2025 (3 days)

3. **Approve Leave**:
   - Click "Approve" and proceed

**Expected Results**:
- [ ] **Warning Dialog**: Shows all bookings across all dates in the range
- [ ] **Schedule Updates**: `staff_schedule` has entries for all 3 dates (Dec 17, 18, 19)
- [ ] **All Bookings Marked**: All conflicting bookings across all dates are marked
- [ ] **All Emails Sent**: Emails sent for all affected bookings

**Verification Points**:
- [ ] Date range is correctly interpreted
- [ ] All dates in range are covered in schedule
- [ ] Conflicts detected for all dates, not just start date

---

### Test Case 9: Email Failure Handling

**Objective**: Verify system handles email sending failures gracefully.

**Steps**:
1. **Simulate Email Failure** (optional - if possible):
   - Temporarily break SMTP configuration
   - OR use invalid email addresses in bookings

2. **Approve Leave with Conflicts**:
   - Follow Test Case 2 steps

**Expected Results**:
- [ ] **Database Updates**: Leave approval and booking marking still succeed
- [ ] **Transaction Commits**: Database transaction commits successfully
- [ ] **Error Handling**: Email failures are logged but don't block approval
- [ ] **Success Message**: Shows "⚠️ X email(s) failed to send" if applicable
- [ ] **Partial Success**: If some emails succeed and some fail, counts are accurate

**Verification Points**:
- [ ] System remains functional even if emails fail
- [ ] Admin is informed of email failures
- [ ] Database integrity is maintained

---

### Test Case 10: UI Responsiveness and Edge Cases

**Objective**: Verify UI handles edge cases and remains responsive.

**Edge Cases to Test**:
1. **Cancel Warning Dialog**:
   - Click "Cancel" in conflict warning dialog
   - Verify: Leave request remains pending, no changes made

2. **Rapid Clicks**:
   - Click "Approve" multiple times rapidly
   - Verify: Only one API call is made, buttons are disabled during processing

3. **Empty Conflict List** (shouldn't happen, but verify):
   - If conflict check returns empty array
   - Verify: Proceeds normally without warning dialog

4. **Very Long Booking List**:
   - Create 10+ conflicting bookings
   - Verify: Warning dialog scrolls or handles long lists gracefully

5. **Network Error**:
   - Disconnect network during approval
   - Verify: Error message shown, UI state restored

**Expected Results**:
- [ ] UI remains responsive during all operations
- [ ] Loading states are shown appropriately
- [ ] Error messages are user-friendly
- [ ] No UI glitches or broken layouts

---

## Test Execution Checklist

### Before Testing
- [ ] All prerequisites met
- [ ] Test data prepared
- [ ] Email inbox accessible
- [ ] Database access available for verification

### During Testing
- [ ] Execute Test Cases 1-10 in order
- [ ] Document any issues or unexpected behavior
- [ ] Take screenshots of key interactions (warning dialogs, success messages)
- [ ] Note any console errors or warnings
- [ ] Verify email delivery times

### After Testing
- [ ] Clean up test data (optional):
  - Delete test bookings
  - Delete test leave requests
  - Reset staff_schedule entries
- [ ] Document test results
- [ ] Report any bugs or issues found

---

## Expected Test Results Summary

| Test Case | Expected Outcome | Pass/Fail | Notes |
|-----------|-----------------|-----------|-------|
| TC1: Warning Dialog | Dialog shows with booking details | ⬜ | |
| TC2: Database Updates | All tables updated correctly | ⬜ | |
| TC3: Email Notification | Customer receives email | ⬜ | |
| TC4: Success Message | Admin sees conflict count | ⬜ | |
| TC5: No Conflicts | Normal approval flow | ⬜ | |
| TC6: Rejection | No conflict check, no emails | ⬜ | |
| TC7: Multiple Conflicts | All bookings handled | ⬜ | |
| TC8: Date Range | Conflicts detected across range | ⬜ | |
| TC9: Email Failure | Graceful error handling | ⬜ | |
| TC10: Edge Cases | UI handles all scenarios | ⬜ | |

---

## Troubleshooting Guide

### Issue: Warning Dialog Not Appearing
- **Check**: Browser console for JavaScript errors
- **Check**: Network tab for `check_conflicts.php` API call
- **Check**: API endpoint returns correct JSON format
- **Verify**: Leave request has valid `request_id`

### Issue: Email Not Received
- **Check**: Spam/junk folder
- **Check**: SMTP configuration in `config/email_config.php`
- **Check**: Email address is valid in booking record
- **Check**: Server logs for email sending errors
- **Verify**: `EmailService->sendBookingConflictEmail()` is being called

### Issue: Database Not Updating
- **Check**: Database transaction is committing
- **Check**: No foreign key constraint violations
- **Check**: Staff email matches between leave request and bookings
- **Verify**: Date formats match (YYYY-MM-DD)

### Issue: Booking Status Not Changing
- **Check**: Booking status is `'confirmed'` before approval
- **Check**: SQL UPDATE query is executing
- **Verify**: `remarks` field is being updated correctly

---

## Notes
- Test dates should be adjusted based on current date
- Email delivery may take 1-2 minutes depending on SMTP server
- Some tests may require manual database verification
- Keep test data separate from production data

