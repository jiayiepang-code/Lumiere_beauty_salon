# Creative Brief: Lumière Beauty Salon Booking System

## 1. Project Overview & Vision

**Product:** A centralized, web-based appointment booking and management platform for Lumière Beauty Salon.

**The Vision:** To elevate the operational efficiency of Lumière Beauty Salon to match its high-end brand image. We are transitioning the salon from disjointed manual processes (WhatsApp, paper, phone calls) to a seamless digital ecosystem. The goal is to make booking effortless for customers, scheduling clear for staff, and business management data-driven for the administration.

## 2. The Problem & Opportunity

- **The Current Reality:** Reliance on manual booking via messaging apps leads to high operational costs, communication delays, risk of human error (double-bookings), and a lack of centralized data for business insights. It also generates unnecessary paper waste.
- **The Opportunity:** By digitizing the core workflow, we can provide a 24/7 self-service booking experience that delights customers, reduce admin workload by automating confirmations, and provide the owner with powerful analytics to grow the business sustainably.

## 3. Target Audience & Core Needs

| Audience Role              | Primary Goal                                                               | Key Needs                                                                                                         |
| :------------------------- | :------------------------------------------------------------------------- | :---------------------------------------------------------------------------------------------------------------- |
| **The Customer**           | "I want to book a service quickly, anytime, without back-and-forth texts." | Ease of use (mobile-first), real-time availability visibility, instant confirmation.                              |
| **The Staff (Beautician)** | "I need to know exactly what my day looks like when I arrive."             | Clear, visual daily roster (color-coded statuses), easy way to mark bookings as complete.                         |
| **The Admin (Owner)**      | "I need total oversight of my business performance and resources."         | Ability to manage staff and services easily, a master view of the calendar, and data on booking trends/idle time. |

## 4. Design Directive & Brand Aesthetic

The look and feel must reflect the name "Lumière" (Light) and the salon's positioning as a premium service provider.

- **Keywords:** Minimal, Classy, Elegant, Clean, Intuitive, Sophisticated.
- **Visual Style:** Generous use of whitespace (breathing room), refined typography (Serif headers, clean Sans-Serif body), and subtle card-based layouts with soft shadows.
  **Definitive Color Palette (Brown/Earthy Tones):**
  _ **Customer Primary Accent:** `#c29076` (Brown)
  _ **Staff Accent:** `#968073` (Muted Brown)
  _ **Admin Accent (Sidebar Gradient):** `linear-gradient(135deg, #b18776, #9e7364)` (Revised for modern feel)
  _ **Text & UI:** `#5c4e4b` (Dark Brown/Charcoal) and `#e6d9d2` (Border/Light Accent).

## 5. Core Functional Pillars (The Bigger Picture)

1.  **Centralized Scheduling Engine:** The single source of truth for all appointments, preventing overlaps and respecting operating hours (10 AM - 10 PM).
2.  **Role-Based Access Control (RBAC):** Secure separation of duties. Customers see only booking options; Staff see rosters; Admins see everything.
3.  **Automated Communication Loop:** Removing the need for manual follow-ups through system-triggered emails for confirmations and reminders.
4.  **Data-Driven Insights:** Moving from intuition-based management to decisions based on actual booking data (overall revenue trends),commission earnings, and staff utilization metrics.

---

## 6. Team Responsibilities & Focus Areas

To achieve this vision, the project is divided into specialized modules overseen by specific team members.

### **My (S) Area of Responsibility: Admin Core & Automation Infrastructure**

Your role is pivotal in building the "brain" of the operation—providing the tools for the business owner to manage the salon and setting up the automated communication backbone that ties the whole system together.

**Key Deliverables:**

1.  **The Administrator Portal:**

    - **Design & UX:** Implementing the "Lumière" aesthetic into a clean, functional dashboard that gives the admin a feeling of control without overwhelm.
    - **Resource Management (CRUD):** Building robust, validated forms for creating and managing Staff profiles (including photos/bios) and the Service Catalog (names, prices, durations).Need to let admin to able to upload photos and store in MySQL database.
    - **Leave Management:** Building the interface to **approve or reject staff leave requests** (updating the `staff_schedule` status).
    - **Master Calendar View:** Developing a comprehensive, bird's-eye view of all bookings across all staff members for any given day or month.

2.  **Analytics & Reporting Dashboard:**

    - Translating raw database data into actionable visual insights.
    - **Business Metrics:** Displaying booking volumes, popular services, and revenue trends.
    - **Commission Analytics:** Calculating and displaying staff commission rates and total monthly earnings based on services completed.
    - **Sustainability/Efficiency Metrics:** Calculating and visualizing "idle hours" (gaps in schedules) to help optimize staff rostering and report on ESG goals at another section.
    - **Customer Analytics View:** Providing a read-only customer management interface for viewing customer data, booking history, spending patterns, and export capabilities. Note: This is an analytics/reporting tool only—customer CRUD operations (create/edit/delete) are not in scope for this phase.

3.  **Automated Email Notification System:**
    - **Infrastructure:** Setting up the backend PHP mail functionality to trigger emails reliably based on system events.
    - **Transactional Emails:**
      - _Instant Confirmation:_ Sent immediately upon successful booking by a customer.
      - _24-Hour Reminder:_ A scheduled task (cron job) that checks upcoming appointments and sends reminder alerts to reduce no-shows.

_(For Context: P is leading Database architecture & Staff Portal; J is leading Frontend UX & Customer Booking Portal)_
