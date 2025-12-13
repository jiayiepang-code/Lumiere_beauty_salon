# 🛠️ Project Technology Stack (TECH_STACK.md)

This document defines the core technologies and guidelines for the Lumière Beauty Salon Booking System. All team members must adhere to these standards to ensure seamless integration, security, and performance.

## 1. Core Technology Foundation (Locked)

| Layer               | Technology              | Version / Tool      | Rationale                                                            |
| :------------------ | :---------------------- | :------------------ | :------------------------------------------------------------------- |
| **Frontend**        | HTML5, CSS3, JavaScript | Vanilla             | [cite_start]Required for a responsive web application[cite: 37, 51]. |
| **Backend**         | PHP                     | Native / Procedural | [cite_start]Server-side programming for application logic[cite: 51]. |
| **Database**        | MySQL                   |                     | [cite_start]Primary data management system[cite: 51].                |
| **Environment**     | XAMPP                   | Apache              | [cite_start]Local development and hosting environment[cite: 52].     |
| **Design Standard** | CSS Variables           |                     | Defines the definitive Brown/Earthy color palette for consistency.   |

## 2. Implementation Recommendations

### 2.1 Security & Data Integrity

- **Prepared Statements (MySQLi):** **Mandatory.** All database interactions involving user-provided data (login, registration, CRUD forms) must use prepared statements to prevent SQL injection attacks.
- **Password Hashing:** Use PHP's built-in `password_hash()` (Bcrypt) for secure storage of passwords in the `customer` and `staff` tables.
- **Centralized Connection:** The single `config/db_connect.php` file must be used by all team members for database access (`$conn` object).
- **Input Validation:** Implement server-side validation to ensure data types (e.g., price is a decimal, duration is an integer) and formats (email, phone number) are correct before processing or insertion.

### 2.2 Performance & Readability

- **Caching:** Avoid unnecessary database queries on page load. Fetch only the data required for the view.
- **Query Efficiency:** Use efficient SQL techniques (e.g., JOINs instead of multiple sequential queries) when fetching data for reports (like the Commission Analytics).

### 2.3 Required Tooling (Shared Access)

- **Code Collaboration:** **GitHub** is strongly recommended for version control, file sharing, and managing code merging (especially when integrating Customer, Staff, and Admin modules).
- **UI Enhancements (CDN):** **SweetAlert2** should be used for all success, error, and confirmation pop-up reminders, as it provides a modern, high-end look consistent with the Lumière aesthetic.
- [cite_start]**Email Sending:** Use a standard **PHP `mail()` function** for the instant confirmation and 24-hour reminder emails, as integration with external third-party APIs (like SendGrid or Stripe) is excluded from the project scope[cite: 55].

### 2.4 Project Structure Guideline

Adhere strictly to the defined folder structure to prevent file conflicts during integration:

- `/admin`: S's module (Admin CRUD, Analytics)
- `/staff`: P's module (Staff Roster, Booking Actions)
- `/customer`: J's module (Customer History, Profile)
- `/config`: Shared folder for `db_connect.php`.
- `/assets`: Shared folder for `style.css` and images.
