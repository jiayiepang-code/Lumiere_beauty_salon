# Product Brief: Lumière Beauty Salon Booking System

## Project Overview

A centralized, web-based appointment booking and management platform for Lumière Beauty Salon. The system transitions the salon from manual processes (WhatsApp, paper, phone calls) to a seamless digital ecosystem, making booking effortless for customers, scheduling clear for staff, and business management data-driven for administration.

**Vision:** Elevate operational efficiency to match the salon's high-end brand image through digitization and automation.

## Target Audience

| Role                   | Primary Goal                                                         | Key Needs                                                                                     |
| :--------------------- | :------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------- |
| **Customer**           | Book services quickly, anytime, without back-and-forth communication | Mobile-first ease of use, real-time availability visibility, instant confirmation             |
| **Staff (Beautician)** | Know exactly what their day looks like when they arrive              | Clear, visual daily roster with color-coded statuses, easy way to mark bookings as complete   |
| **Admin (Owner)**      | Total oversight of business performance and resources                | Easy staff and service management, master calendar view, data on booking trends and idle time |

## Primary Benefits

**For Customers:**

- 24/7 self-service booking experience
- Real-time availability visibility
- Instant booking confirmation

**For Staff:**

- Clear, visual daily schedules
- Reduced communication overhead
- Easy booking status management

**For Administration:**

- Automated confirmations and reminders (reduced manual workload)
- Centralized scheduling prevents double-bookings
- Data-driven insights for business growth (revenue trends, commission analytics, staff utilization metrics)
- Reduced operational costs and paper waste

## High-Level Tech/Architecture

**Architecture Pattern:** Web-based, role-based access control (RBAC) with three distinct user portals (Customer, Staff, Admin).

**Core Components:**

1. **Centralized Scheduling Engine:** Single source of truth for all appointments, preventing overlaps and respecting operating hours (10 AM - 10 PM)
2. **Role-Based Access Control:** Secure separation of duties with role-specific views and permissions
3. **Automated Communication System:** PHP-based email infrastructure for transactional emails (instant confirmations, 24-hour reminders via cron jobs)
4. **Analytics & Reporting:** Data visualization for business metrics, commission tracking, and efficiency analysis

**Technology Stack:**

- Backend: PHP with MySQL database
- Frontend: HTML, CSS, JavaScript
- Email: PHPMailer for transactional emails
- Scheduling: Cron jobs for automated reminders

**Design Aesthetic:** Minimal, elegant, premium feel with brown/earth-tone color palette, generous whitespace, and card-based layouts reflecting the "Lumière" (Light) brand identity.
