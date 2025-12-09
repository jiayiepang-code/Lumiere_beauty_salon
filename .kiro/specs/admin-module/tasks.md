# Implementation Plan

- [x] 1. Set up authentication system

  - Integrate existing login.html with PHP backend authentication
  - Create login API endpoint with phone number and password validation
  - Implement secure password hashing using password_hash()
  - Set up PHP session management with security configurations
  - Add CSRF token generation and validation
  - Implement rate limiting for login attempts
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 2. Create admin dashboard layout and navigation

  - Build dashboard landing page (admin/index.php) with KPI summary cards
  - Create reusable header component with admin profile and logout
  - Build responsive sidebar navigation with menu items for services, staff, calendar, and analytics
  - Implement mobile hamburger menu for responsive navigation
  - Add authentication check middleware for all admin pages
  - _Requirements: 1.4, 11.1, 11.2, 11.3, 11.4_

- [x] 3. Implement service management functionality

  - Create service listing page with search and filter by category
  - Build service creation form with validation for all fields
  - Implement image upload functionality with file type and size validation
  - Create service edit form pre-populated with existing data
  - Add service activation/deactivation toggle
  - Build service deletion with warning for existing bookings
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 3.1 Create service management API endpoints

  - Build POST /api/admin/services/create.php with validation
  - Build GET /api/admin/services/list.php with filtering
  - Build PUT /api/admin/services/update.php with validation
  - Build DELETE /api/admin/services/delete.php with booking check
  - Implement prepared statements for all database queries
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.3, 3.4, 4.2, 4.3_

- [x] 4. Implement staff management functionality

  - Create staff listing page with profile images and status indicators
  - Build staff creation form with email, phone, password, and role fields
  - Implement password strength validation and secure hashing
  - Create staff edit form with all fields except email (read-only)
  - Add staff activation/deactivation toggle
  - Build staff deletion with warning for existing bookings
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 4.1 Create staff management API endpoints

  - Build POST /api/admin/staff/create.php with duplicate email check
  - Build GET /api/admin/staff/list.php
  - Build PUT /api/admin/staff/update.php preventing email modification
  - Build POST /api/admin/staff/toggle_active.php for activation toggle
  - Implement validation for phone format and password requirements
  - _Requirements: 5.2, 5.3, 5.4, 6.3, 6.5, 7.2, 7.4_

- [x] 5. Build master calendar interface

  - Create calendar grid layout with day/week/month view options
  - Implement color-coded booking display (green, blue, red, grey)
  - Add date range filter and staff member filter
  - Build booking details modal triggered by clicking bookings
  - Display staff schedules alongside bookings
  - Integrate with booking list API to fetch data
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 12.1, 12.2, 12.3, 12.4, 12.5_

- [x] 5.1 Create calendar data API endpoints

  - Build GET /api/admin/bookings/list.php with date and staff filters
  - Build GET /api/admin/bookings/details.php for detailed booking information
  - Join Booking, Booking_Service, Service, Staff, and Customer tables
  - Optimize queries with appropriate indexes
  - Return data in JSON format for frontend consumption
  - _Requirements: 8.1, 8.2, 8.3, 12.1, 12.2, 12.3, 12.4, 12.5_

- [x] 6. Implement business analytics dashboard

  - Create analytics page layout with period selector (daily/weekly/monthly)
  - Build KPI cards for total bookings, completion rate, and revenue
  - Implement line chart for booking trends using Chart.js
  - Create bar chart for popular services
  - Build staff performance table with completed sessions and revenue
  - Add date range picker for custom period selection
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [x] 6.1 Create business analytics API endpoint

  - Build GET /api/admin/analytics/booking_trends.php
  - Calculate total, completed, cancelled, and no-show bookings
  - Compute total revenue and average booking value
  - Generate daily breakdown for selected period
  - Aggregate popular services by booking count and revenue
  - Calculate staff performance metrics
  - Implement caching for analytics data (5-minute cache)
  - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [x] 7. Implement sustainability analytics dashboard

  - Create sustainability analytics page with period selector
  - Build KPI cards for total idle hours and utilization rate
  - Implement area chart for idle hours trend using Chart.js
  - Create staff breakdown table showing idle hours per staff member
  - Add export functionality for ESG report as PDF
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 7.1 Create sustainability analytics API endpoint

  - Build GET /api/admin/analytics/idle_hours.php
  - Calculate scheduled hours from Staff_Schedule table
  - Calculate booked hours from Booking_Service table
  - Compute idle hours (scheduled - booked) for salon and per staff
  - Calculate utilization rate percentage
  - Generate daily idle hour patterns
  - _Requirements: 10.1, 10.2, 10.3, 10.4_

- [ ]\* 7.2 Implement PDF export for sustainability report

  - Integrate PDF generation library (TCPDF or FPDF)
  - Create PDF template with salon branding
  - Include idle hours metrics and charts
  - Add staff breakdown table to PDF
  - Implement download functionality
  - _Requirements: 10.5_

- [x] 8. Add responsive design and mobile optimization

  - Implement CSS media queries for mobile, tablet, and desktop breakpoints
  - Convert tables to card layout on mobile devices
  - Create hamburger menu for mobile navigation
  - Optimize calendar for mobile (day view only)
  - Ensure touch targets are minimum 44x44px
  - Test on Chrome, Firefox, Edge, Safari across devices
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [-] 9. Implement error handling and validation

  - Create consistent error response format for all API endpoints
  - Add server-side validation for all form inputs
  - Implement client-side validation with user-friendly error messages
  - Set up error logging to logs/admin_errors.log
  - Add try-catch blocks around database operations
  - Display validation errors on forms with red borders and error text
  - _Requirements: All requirements (cross-cutting concern)_

- [ ] 10. Optimize performance and add security measures

  - Add database indexes for common queries (booking_date, status, is_active)
  - Implement prepared statements for all SQL queries to prevent SQL injection
  - Add XSS prevention using htmlspecialchars() for output
  - Configure secure session settings (httponly, secure cookies)
  - Implement file upload validation for images (type, size, rename)
  - Add page load time optimization (minify CSS/JS, compress images)
  - _Requirements: 1.5, 8.5, 9.5, 11.5_

- [ ]\* 11. Write integration tests for critical workflows

  - Test admin login flow with valid and invalid credentials
  - Test service creation, edit, and deletion workflow
  - Test staff creation, edit, and activation toggle workflow
  - Test calendar filtering and booking details display
  - Test analytics data calculation and chart rendering
  - _Requirements: All requirements (quality assurance)_

- [ ]\* 12. Create admin user documentation
  - Write user guide for service management
  - Document staff account creation process
  - Explain calendar navigation and filtering
  - Describe analytics dashboard interpretation
  - Include troubleshooting section for common issues
  - _Requirements: All requirements (documentation)_
