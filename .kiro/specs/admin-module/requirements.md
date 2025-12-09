# Requirements Document

## Introduction

The Admin Module is a web-based administrative interface for Lumière Beauty Salon that enables administrators to manage the salon's operations, including service offerings, staff accounts, booking oversight, and business analytics. This module centralizes administrative tasks that were previously handled manually through disparate channels, providing a unified platform for salon management and data-driven decision making.

## Glossary

- **Admin Portal**: The web-based interface accessible only to administrators for managing salon operations
- **Booking System**: The Lumière Beauty Salon Booking System, the overall web application
- **Service**: A beauty treatment offered by the salon (e.g., haircut, facial, manicure)
- **Staff Account**: A user account for salon employees (beauticians, receptionists) with access to staff-specific features
- **Master Calendar**: A comprehensive view displaying all salon bookings across all staff members and time slots
- **Business Analytics Dashboard**: A visual interface displaying operational metrics such as booking trends and idle hours
- **Sustainability Analytics Dashboard**: A visual interface displaying idle hour patterns for ESG (Environmental, Social, and Governance) reporting
- **XAMPP**: Local development environment running Apache web server, MySQL database, and PHP
- **Booking Status**: The current state of a booking (confirmed, completed, cancelled, no-show)
- **Operating Hours**: Daily time window from 10:00 AM to 10:00 PM during which bookings can be scheduled

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to securely access the admin portal, so that I can manage salon operations without unauthorized access.

#### Acceptance Criteria

1.1 WHEN an administrator navigates to the admin login page, THE Admin Portal SHALL display a login form requesting email and password credentials.

1.2 WHEN an administrator submits valid credentials, THE Admin Portal SHALL authenticate the user and grant access to the administrative interface within 3 seconds under normal internet connection.

1.3 IF an administrator submits invalid credentials, THEN THE Admin Portal SHALL display an error message and deny access to the administrative interface.

1.4 WHILE an administrator session is active, THE Admin Portal SHALL maintain authentication state across page navigation within the portal.

1.5 THE Admin Portal SHALL store administrator passwords using secure hashing algorithms.

### Requirement 2

**User Story:** As an administrator, I want to create new service offerings, so that customers can book newly available treatments.

#### Acceptance Criteria

2.1 THE Admin Portal SHALL provide a form to create a new service with fields for service category, sub-category, service name, duration in minutes, price, description, service image, and default cleanup minutes.

2.2 WHEN an administrator submits a completed service creation form with valid data, THE Admin Portal SHALL store the new service record in the Service table with a unique service_id and set is_active to true.

2.3 WHEN an administrator submits a service creation form with missing required fields, THE Admin Portal SHALL display validation error messages indicating which fields require completion.

2.4 WHEN a new service is successfully created, THE Admin Portal SHALL display a confirmation message and make the service immediately available for customer booking.

2.5 THE Admin Portal SHALL validate that service duration is a positive integer and price is a positive decimal value before accepting service creation.

### Requirement 3

**User Story:** As an administrator, I want to edit existing service details, so that I can update pricing, duration, or descriptions as business needs change.

#### Acceptance Criteria

3.1 THE Admin Portal SHALL display a list of all services with options to edit each service record.

3.2 WHEN an administrator selects a service to edit, THE Admin Portal SHALL populate a form with the current service details including category, name, duration, price, description, image, and cleanup minutes.

3.3 WHEN an administrator submits modified service details with valid data, THE Admin Portal SHALL update the corresponding service record in the Service table while preserving the original service_id and created_at timestamp.

3.4 WHEN service details are successfully updated, THE Admin Portal SHALL display a confirmation message and reflect the changes immediately in customer-facing service listings.

3.5 THE Admin Portal SHALL validate that updated duration values are positive integers and updated price values are positive decimal numbers before accepting modifications.

### Requirement 4

**User Story:** As an administrator, I want to deactivate or delete services, so that outdated or discontinued treatments are no longer available for booking.

#### Acceptance Criteria

4.1 THE Admin Portal SHALL provide an option to deactivate or delete each service from the service management interface.

4.2 WHEN an administrator deactivates a service, THE Admin Portal SHALL set the is_active field to false in the Service table while retaining the service record.

4.3 WHEN an administrator deletes a service, THE Admin Portal SHALL remove the service record from the Service table.

4.4 WHEN a service is deactivated or deleted, THE Admin Portal SHALL immediately remove the service from customer-facing service listings and prevent new bookings for that service.

4.5 IF a service has existing future bookings, THEN THE Admin Portal SHALL display a warning message before allowing deactivation or deletion.

### Requirement 5

**User Story:** As an administrator, I want to create new staff accounts, so that new employees can access the staff portal and be assigned to bookings.

#### Acceptance Criteria

5.1 THE Admin Portal SHALL provide a form to create a new staff account with fields for email, phone, password, first name, last name, bio, role, staff image, and is_active status.

5.2 WHEN an administrator submits a completed staff creation form with valid data, THE Admin Portal SHALL store the new staff record in the Staff table with staff_email as the primary key.

5.3 WHEN an administrator submits a staff creation form with a duplicate email address, THE Admin Portal SHALL display an error message indicating the email is already in use.

5.4 THE Admin Portal SHALL validate that the phone number follows a valid format and the password meets strong password requirements before accepting staff account creation.

5.5 WHEN a new staff account is successfully created, THE Admin Portal SHALL display a confirmation message and make the staff member immediately available for service assignment and booking scheduling.

### Requirement 6

**User Story:** As an administrator, I want to edit staff account details, so that I can update employee information, roles, or profile images as needed.

#### Acceptance Criteria

6.1 THE Admin Portal SHALL display a list of all staff accounts with options to edit each staff record.

6.2 WHEN an administrator selects a staff account to edit, THE Admin Portal SHALL populate a form with the current staff details including email, phone, first name, last name, bio, role, staff image, and is_active status.

6.3 WHEN an administrator submits modified staff details with valid data, THE Admin Portal SHALL update the corresponding staff record in the Staff table while preserving the staff_email and created_at timestamp.

6.4 WHEN staff details are successfully updated, THE Admin Portal SHALL display a confirmation message and reflect the changes immediately in staff-related interfaces.

6.5 THE Admin Portal SHALL prevent modification of the staff_email field to maintain referential integrity with related booking records.

### Requirement 7

**User Story:** As an administrator, I want to deactivate or delete staff accounts, so that former employees no longer have system access.

#### Acceptance Criteria

7.1 THE Admin Portal SHALL provide an option to deactivate or delete each staff account from the staff management interface.

7.2 WHEN an administrator deactivates a staff account, THE Admin Portal SHALL set the is_active field to false in the Staff table while retaining the staff record.

7.3 WHEN an administrator deletes a staff account, THE Admin Portal SHALL remove the staff record from the Staff table.

7.4 WHEN a staff account is deactivated, THE Admin Portal SHALL immediately revoke login access for that staff member and prevent new booking assignments.

7.5 IF a staff member has existing future bookings, THEN THE Admin Portal SHALL display a warning message before allowing deactivation or deletion.

### Requirement 8

**User Story:** As an administrator, I want to view a master calendar showing all salon bookings, so that I can monitor overall booking activity and identify scheduling conflicts.

#### Acceptance Criteria

8.1 THE Admin Portal SHALL display a master calendar interface showing all bookings across all staff members and time slots.

8.2 THE Admin Portal SHALL allow administrators to filter the master calendar view by date range, staff member, service type, or booking status.

8.3 WHEN an administrator selects a specific date on the master calendar, THE Admin Portal SHALL display all bookings scheduled for that date with details including customer name, service, staff member, time slot, and status.

8.4 THE Admin Portal SHALL visually distinguish bookings by status using color coding: green for confirmed, blue for completed, red for cancelled or no-show, and grey for available slots.

8.5 THE Admin Portal SHALL load and display the master calendar view within 3 seconds under normal internet connection.

### Requirement 9

**User Story:** As an administrator, I want to view business analytics dashboards, so that I can make data-driven decisions about salon operations.

#### Acceptance Criteria

9.1 THE Admin Portal SHALL provide a Business Analytics Dashboard displaying daily, weekly, and monthly booking trend visualizations.

9.2 THE Admin Portal SHALL calculate and display total bookings, completed bookings, cancelled bookings, and no-show bookings for selected time periods.

9.3 THE Admin Portal SHALL calculate and display idle hours by analyzing the difference between staff scheduled hours and booked hours for selected time periods.

9.4 THE Admin Portal SHALL allow administrators to filter analytics data by date range, staff member, or service category.

9.5 THE Admin Portal SHALL load and display the Business Analytics Dashboard within 5 seconds under normal internet connection.

### Requirement 10

**User Story:** As an administrator, I want to view sustainability analytics dashboards, so that I can analyze idle hours for ESG reporting and identify opportunities for resource optimization.

#### Acceptance Criteria

10.1 THE Admin Portal SHALL provide a Sustainability Analytics Dashboard displaying idle hour patterns across daily, weekly, and monthly time periods.

10.2 THE Admin Portal SHALL calculate idle hours by comparing staff scheduled work hours from Staff_Schedule table against actual booked hours from Booking and Booking_Service tables.

10.3 THE Admin Portal SHALL display idle hour metrics broken down by individual staff member and aggregate salon-wide totals.

10.4 THE Admin Portal SHALL visualize idle hour trends using charts or graphs to identify patterns and optimization opportunities.

10.5 THE Admin Portal SHALL allow administrators to export sustainability analytics data for external ESG reporting purposes.

### Requirement 11

**User Story:** As an administrator, I want the admin portal to be responsive across devices, so that I can manage salon operations from desktop, tablet, or mobile devices.

#### Acceptance Criteria

11.1 THE Admin Portal SHALL render correctly and maintain full functionality on desktop browsers including Chrome, Firefox, and Edge on Windows and macOS.

11.2 THE Admin Portal SHALL render correctly and maintain full functionality on mobile browsers including Chrome and Safari on Android and iOS devices.

11.3 THE Admin Portal SHALL render correctly and maintain full functionality on tablet devices including iPads.

11.4 THE Admin Portal SHALL adapt layout and navigation elements to screen size while maintaining usability across all supported devices.

11.5 THE Admin Portal SHALL load all pages within 3 seconds under normal internet connection regardless of device type.

### Requirement 12

**User Story:** As an administrator, I want to view detailed booking information from the master calendar, so that I can access customer details, service specifics, and booking history when needed.

#### Acceptance Criteria

12.1 WHEN an administrator clicks on a booking in the master calendar, THE Admin Portal SHALL display detailed booking information including customer contact details, selected services, assigned staff, time slot, pricing, and booking status.

12.2 THE Admin Portal SHALL display the complete booking history for a selected customer when viewing booking details.

12.3 THE Admin Portal SHALL display any special requests or remarks associated with the booking.

12.4 THE Admin Portal SHALL allow administrators to view the sequence of multiple services within a single booking appointment.

12.5 THE Admin Portal SHALL display promo code and discount information if applicable to the booking.
