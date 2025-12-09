# Admin Module Design Document

## Overview

The Admin Module is a secure web-based interface for Lumière Beauty Salon administrators to manage salon operations. Built using PHP, MySQL, HTML, CSS, and JavaScript, it follows a three-tier architecture pattern with clear separation between presentation, business logic, and data layers. The module integrates with the existing XAMPP-based infrastructure and leverages the established database schema.

### Key Design Principles

- **Security First**: Role-based access control with secure authentication
- **Responsive Design**: Mobile-first approach supporting desktop, tablet, and mobile devices
- **Performance**: Page loads under 3 seconds, optimized database queries
- **Maintainability**: Modular code structure with reusable components
- **User Experience**: Intuitive interfaces with clear visual feedback

## Architecture

### System Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Client Layer                          │
│  (Browser: Chrome, Firefox, Edge, Safari)               │
│  - HTML5 + CSS3 (Responsive)                            │
│  - JavaScript (Vanilla JS for interactions)             │
└─────────────────┬───────────────────────────────────────┘
                  │ HTTP/HTTPS
┌─────────────────▼───────────────────────────────────────┐
│              Application Layer                           │
│  (Apache Web Server via XAMPP)                          │
│  - PHP 7.4+ (Server-side logic)                         │
│  - Session Management                                    │
│  - Authentication & Authorization                        │
│  - Business Logic Controllers                            │
└─────────────────┬───────────────────────────────────────┘
                  │ MySQLi
┌─────────────────▼───────────────────────────────────────┐
│                 Data Layer                               │
│  (MySQL Database via XAMPP)                             │
│  - Service, Staff, Booking tables                       │
│  - Stored procedures for analytics                       │
└─────────────────────────────────────────────────────────┘
```

### Directory Structure

```
admin/
├── index.php                    # Dashboard landing page
├── login.html                   # Admin authentication (existing)
├── login.js                     # Login validation (existing)
├── login.css                    # Login styling (existing)
├── services/
│   ├── list.php                # Service listing page
│   ├── create.php              # Service creation form
│   ├── edit.php                # Service edit form
│   └── delete.php              # Service deletion handler
├── staff/
│   ├── list.php                # Staff listing page
│   ├── create.php              # Staff creation form
│   ├── edit.php                # Staff edit form
│   └── manage.php              # Staff activation/deactivation
├── calendar/
│   ├── master.php              # Master calendar view
│   └── booking_details.php    # Booking detail modal
├── analytics/
│   ├── business.php            # Business analytics dashboard
│   └── sustainability.php      # Sustainability analytics dashboard
└── includes/
    ├── header.php              # Common header
    ├── sidebar.php             # Navigation sidebar
    └── footer.php              # Common footer

api/admin/
├── auth/
│   ├── login.php               # Authentication endpoint
│   └── logout.php              # Session termination
├── services/
│   ├── create.php              # Service creation API
│   ├── update.php              # Service update API
│   ├── delete.php              # Service deletion API
│   └── list.php                # Service retrieval API
├── staff/
│   ├── create.php              # Staff creation API
│   ├── update.php              # Staff update API
│   ├── toggle_active.php       # Staff activation toggle
│   └── list.php                # Staff retrieval API
├── bookings/
│   ├── list.php                # Booking retrieval for calendar
│   └── details.php             # Detailed booking information
└── analytics/
    ├── booking_trends.php      # Booking trend calculations
    └── idle_hours.php          # Idle hour calculations
```

## Components and Interfaces

### 1. Authentication Component

**Purpose**: Secure admin login and session management

**Files**:

- `admin/login.html` (existing, needs PHP integration)
- `api/admin/auth/login.php`
- `api/admin/auth/logout.php`

**Interface**:

```php
// POST /api/admin/auth/login.php
Request: {
    "phone": "60123456789",
    "password": "SecurePass123"
}

Response: {
    "success": true,
    "admin_email": "admin@lumiere.com",
    "redirect": "/admin/index.php"
}
```

**Implementation Details**:

- Validate phone number format (Malaysia: 01X-XXXXXXX or 60XXXXXXXXX)
- Hash password using `password_hash()` with PASSWORD_BCRYPT
- Create PHP session with admin email and role
- Set session timeout to 30 minutes of inactivity
- Implement CSRF token for form submissions

**Security Measures**:

- Rate limiting: Max 5 login attempts per 15 minutes per IP
- Secure session configuration: `session.cookie_httponly = true`, `session.cookie_secure = true`
- Password requirements: Min 8 chars, 1 uppercase, 1 number, 1 symbol

---

### 2. Service Management Component

**Purpose**: CRUD operations for salon services

**Files**:

- `admin/services/list.php`
- `admin/services/create.php`
- `admin/services/edit.php`
- `api/admin/services/*.php`

**Interface**:

```php
// GET /api/admin/services/list.php
Response: {
    "services": [
        {
            "service_id": 1,
            "service_category": "Hair",
            "sub_category": "Styling",
            "service_name": "Haircut & Blow Dry",
            "current_duration_minutes": 60,
            "current_price": 80.00,
            "description": "Professional haircut with styling",
            "service_image": "haircut.jpg",
            "default_cleanup_minutes": 15,
            "is_active": true,
            "created_at": "2024-01-15 10:30:00"
        }
    ]
}

// POST /api/admin/services/create.php
Request: {
    "service_category": "Hair",
    "sub_category": "Styling",
    "service_name": "Haircut & Blow Dry",
    "current_duration_minutes": 60,
    "current_price": 80.00,
    "description": "Professional haircut with styling",
    "service_image": "haircut.jpg",
    "default_cleanup_minutes": 15
}

Response: {
    "success": true,
    "service_id": 1,
    "message": "Service created successfully"
}
```

**UI Components**:

- Service listing table with search/filter by category
- Create/Edit modal forms with image upload
- Delete confirmation dialog with warning for existing bookings
- Toggle active/inactive status button

**Validation Rules**:

- `service_name`: Required, max 100 chars, unique within category
- `current_duration_minutes`: Required, integer, min 15, max 480
- `current_price`: Required, decimal(10,2), min 0.01
- `service_image`: Optional, jpg/png, max 2MB
- `default_cleanup_minutes`: Required, integer, min 0, max 60

---

### 3. Staff Management Component

**Purpose**: CRUD operations for staff accounts

**Files**:

- `admin/staff/list.php`
- `admin/staff/create.php`
- `admin/staff/edit.php`
- `api/admin/staff/*.php`

**Interface**:

```php
// GET /api/admin/staff/list.php
Response: {
    "staff": [
        {
            "staff_email": "stylist@lumiere.com",
            "phone": "60123456789",
            "first_name": "Jane",
            "last_name": "Doe",
            "bio": "Senior stylist with 10 years experience",
            "role": "Stylist",
            "staff_image": "jane.jpg",
            "is_active": true,
            "created_at": "2024-01-10 09:00:00"
        }
    ]
}

// POST /api/admin/staff/create.php
Request: {
    "staff_email": "stylist@lumiere.com",
    "phone": "60123456789",
    "password": "SecurePass123",
    "first_name": "Jane",
    "last_name": "Doe",
    "bio": "Senior stylist",
    "role": "Stylist",
    "staff_image": "jane.jpg"
}

Response: {
    "success": true,
    "staff_email": "stylist@lumiere.com",
    "message": "Staff account created successfully"
}
```

**UI Components**:

- Staff listing table with profile images
- Create/Edit forms with image upload and role dropdown
- Activation toggle switch
- Delete confirmation with future booking warning

**Validation Rules**:

- `staff_email`: Required, valid email format, unique
- `phone`: Required, Malaysia format, unique
- `password`: Required on create, min 8 chars, 1 uppercase, 1 number, 1 symbol
- `first_name`, `last_name`: Required, max 50 chars each
- `role`: Required, enum ('Stylist', 'Receptionist', 'Manager')
- `staff_image`: Optional, jpg/png, max 2MB

---

### 4. Master Calendar Component

**Purpose**: Comprehensive view of all salon bookings

**Files**:

- `admin/calendar/master.php`
- `admin/calendar/booking_details.php`
- `api/admin/bookings/list.php`
- `api/admin/bookings/details.php`

**Interface**:

```php
// GET /api/admin/bookings/list.php?date=2024-12-07
Response: {
    "bookings": [
        {
            "booking_id": 1,
            "customer_name": "John Smith",
            "customer_email": "john@example.com",
            "booking_date": "2024-12-07",
            "start_time": "10:00:00",
            "expected_finish_time": "11:30:00",
            "status": "confirmed",
            "services": [
                {
                    "service_name": "Haircut",
                    "staff_name": "Jane Doe",
                    "staff_email": "jane@lumiere.com"
                }
            ],
            "total_price": 80.00
        }
    ],
    "staff_schedules": [
        {
            "staff_email": "jane@lumiere.com",
            "staff_name": "Jane Doe",
            "work_date": "2024-12-07",
            "start_time": "10:00:00",
            "end_time": "18:00:00",
            "status": "scheduled"
        }
    ]
}
```

**UI Components**:

- Calendar grid view (day/week/month views)
- Color-coded booking blocks:
  - Green (#4CAF50): Confirmed
  - Blue (#2196F3): Completed
  - Red (#F44336): Cancelled/No-show
  - Grey (#9E9E9E): Available slot
- Filter controls: Date range, staff member, service type, status
- Click booking to view details modal
- Staff timeline view showing scheduled hours and bookings

**Calendar Library**: Use FullCalendar.js or custom implementation with CSS Grid

---

### 5. Business Analytics Component

**Purpose**: Operational insights and booking trends

**Files**:

- `admin/analytics/business.php`
- `api/admin/analytics/booking_trends.php`

**Interface**:

```php
// GET /api/admin/analytics/booking_trends.php?period=weekly&start_date=2024-12-01
Response: {
    "period": "weekly",
    "start_date": "2024-12-01",
    "end_date": "2024-12-07",
    "metrics": {
        "total_bookings": 45,
        "completed_bookings": 38,
        "cancelled_bookings": 5,
        "no_show_bookings": 2,
        "total_revenue": 3420.00,
        "average_booking_value": 76.00
    },
    "daily_breakdown": [
        {
            "date": "2024-12-01",
            "bookings": 8,
            "completed": 7,
            "cancelled": 1,
            "revenue": 560.00
        }
    ],
    "popular_services": [
        {
            "service_name": "Haircut & Blow Dry",
            "booking_count": 15,
            "revenue": 1200.00
        }
    ],
    "staff_performance": [
        {
            "staff_name": "Jane Doe",
            "completed_sessions": 12,
            "total_revenue": 960.00
        }
    ]
}
```

**UI Components**:

- Period selector: Daily, Weekly, Monthly
- Date range picker
- KPI cards: Total bookings, completion rate, revenue
- Line chart: Booking trends over time
- Bar chart: Popular services
- Table: Staff performance summary
- Export button: Download as PDF/CSV

**Chart Library**: Chart.js for visualizations

---

### 6. Sustainability Analytics Component

**Purpose**: Idle hour analysis for ESG reporting

**Files**:

- `admin/analytics/sustainability.php`
- `api/admin/analytics/idle_hours.php`

**Interface**:

```php
// GET /api/admin/analytics/idle_hours.php?period=monthly&start_date=2024-12-01
Response: {
    "period": "monthly",
    "start_date": "2024-12-01",
    "end_date": "2024-12-31",
    "salon_metrics": {
        "total_scheduled_hours": 640,
        "total_booked_hours": 480,
        "total_idle_hours": 160,
        "utilization_rate": 75.0
    },
    "staff_breakdown": [
        {
            "staff_name": "Jane Doe",
            "scheduled_hours": 160,
            "booked_hours": 135,
            "idle_hours": 25,
            "utilization_rate": 84.4
        }
    ],
    "daily_idle_pattern": [
        {
            "date": "2024-12-01",
            "idle_hours": 6,
            "utilization_rate": 70.0
        }
    ]
}
```

**Calculation Logic**:

```
Idle Hours = Scheduled Hours - Booked Hours

Where:
- Scheduled Hours = SUM(Staff_Schedule.end_time - Staff_Schedule.start_time)
  for status = 'scheduled'
- Booked Hours = SUM(Booking_Service.quoted_duration_minutes +
  Booking_Service.quoted_cleanup_minutes) / 60
  for service_status IN ('confirmed', 'completed')
```

**UI Components**:

- Period selector: Daily, Weekly, Monthly
- Date range picker
- KPI cards: Total idle hours, utilization rate
- Area chart: Idle hours trend
- Table: Staff-wise idle hour breakdown
- Export button: Download ESG report as PDF

---

## Data Models

### Database Schema (Existing)

The admin module interacts with the following tables:

**Staff Table**:

```sql
CREATE TABLE Staff (
    staff_email VARCHAR(100) PRIMARY KEY,
    phone VARCHAR(18) NOT NULL UNIQUE,
    password VARCHAR(80) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    bio VARCHAR(500),
    role ENUM('staff', 'admin') NOT NULL,
    staff_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Service Table**:

```sql
CREATE TABLE Service (
    service_id VARCHAR(4) AUTO_INCREMENT PRIMARY KEY,
    service_category VARCHAR(50) NOT NULL,
    sub_category VARCHAR(50),
    service_name VARCHAR(100) NOT NULL,
    current_duration_minutes INT NOT NULL,
    current_price DECIMAL(6,2) NOT NULL,
    description TEXT,
    service_image VARCHAR(255),
    default_cleanup_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);
```

**Booking Table**:

```sql
CREATE TABLE Booking (
    booking_id VARCHAR(8) AUTO_INCREMENT PRIMARY KEY,
    customer_email VARCHAR(100) NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    expected_finish_time TIME NOT NULL,
    status ENUM('confirmed', 'completed', 'cancelled', 'no-show','available') DEFAULT 'confirmed',
    remarks TEXT,
    promo_code VARCHAR(10),
    discount_amount DECIMAL(6,2) DEFAULT 0,
    total_price DECIMAL(7,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_email) REFERENCES Customer(customer_email)
);
```

**Booking_Service Table**:

```sql
CREATE TABLE Booking_Service (
    booking_service_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id VARCHAR(8) NOT NULL,
    service_id VARCHAR(4) NOT NULL,
    staff_email VARCHAR(100) NOT NULL,
    quoted_price DECIMAL(6,2) NOT NULL,
    quoted_duration_minutes INT NOT NULL,
    quoted_cleanup_minutes INT NOT NULL,
    quantity INT DEFAULT 1,
    sequence_order INT NOT NULL,
    service_status ENUM('confirmed', 'completed', 'cancelled', 'no-show') DEFAULT 'confirmed',
    special_request TEXT,
    FOREIGN KEY (booking_id) REFERENCES Booking(booking_id),
    FOREIGN KEY (service_id) REFERENCES Service(service_id),
    FOREIGN KEY (staff_email) REFERENCES Staff(staff_email)
);
```

**Staff_Schedule Table**:

```sql
CREATE TABLE Staff_Schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_email VARCHAR(100) NOT NULL,
    work_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('working', 'off', 'leave') DEFAULT 'working',
    FOREIGN KEY (staff_email) REFERENCES Staff(staff_email)
);
```

### Admin Session Data Model

```php
$_SESSION['admin'] = [
    'email' => 'admin@lumiere.com',
    'role' => 'Admin',
    'first_name' => 'Admin',
    'last_name' => 'User',
    'login_time' => time(),
    'csrf_token' => bin2hex(random_bytes(32))
];
```

---

## Error Handling

### Error Response Format

All API endpoints return consistent error responses:

```php
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Invalid input data",
        "details": {
            "service_name": "Service name is required",
            "current_price": "Price must be greater than 0"
        }
    }
}
```

### Error Codes

- `AUTH_REQUIRED`: User not authenticated
- `AUTH_FAILED`: Invalid credentials
- `PERMISSION_DENIED`: Insufficient privileges
- `VALIDATION_ERROR`: Input validation failed
- `NOT_FOUND`: Resource not found
- `DUPLICATE_ENTRY`: Unique constraint violation
- `DATABASE_ERROR`: Database operation failed
- `FILE_UPLOAD_ERROR`: Image upload failed
- `RATE_LIMIT_EXCEEDED`: Too many requests

### Error Handling Strategy

**Client-Side**:

- Display user-friendly error messages in modal/toast notifications
- Highlight invalid form fields with red borders and error text
- Log errors to browser console for debugging

**Server-Side**:

- Log all errors to `logs/admin_errors.log` with timestamp, user, and stack trace
- Return appropriate HTTP status codes (400, 401, 403, 404, 500)
- Sanitize error messages to avoid exposing sensitive information
- Implement try-catch blocks around database operations

**Example PHP Error Handler**:

```php
function handleError($e, $context = []) {
    $error_log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['admin']['email'] ?? 'anonymous',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'context' => $context
    ];

    error_log(json_encode($error_log), 3, '../logs/admin_errors.log');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'An error occurred. Please try again.'
        ]
    ]);
}
```

---

## Testing Strategy

### Unit Testing

**Scope**: Individual PHP functions and API endpoints

**Tools**: PHPUnit (optional for this project scope)

**Test Cases**:

- Authentication: Valid/invalid credentials, session management
- Service CRUD: Create with valid/invalid data, update, delete, toggle active
- Staff CRUD: Create with duplicate email, password hashing, update
- Validation functions: Phone format, email format, password strength
- Analytics calculations: Booking trends, idle hours computation

**Example Test**:

```php
// Test service creation validation
function testServiceCreationValidation() {
    $result = validateServiceData([
        'service_name' => '',
        'current_price' => -10
    ]);

    assert($result['valid'] === false);
    assert(isset($result['errors']['service_name']));
    assert(isset($result['errors']['current_price']));
}
```

### Integration Testing

**Scope**: End-to-end workflows through the UI

**Test Cases**:

1. **Admin Login Flow**:
   - Navigate to login page
   - Enter valid credentials
   - Verify redirect to dashboard
   - Verify session created

2. **Service Management Flow**:
   - Login as admin
   - Navigate to services page
   - Create new service with image upload
   - Verify service appears in list
   - Edit service details
   - Deactivate service
   - Verify service hidden from customer view

3. **Staff Management Flow**:
   - Create new staff account
   - Verify staff can login
   - Edit staff profile
   - Deactivate staff account
   - Verify staff cannot login

4. **Calendar View Flow**:
   - Navigate to master calendar
   - Filter by date range
   - Click on booking
   - Verify booking details modal displays correct information

5. **Analytics Flow**:
   - Navigate to business analytics
   - Select weekly period
   - Verify charts render with data
   - Export report as PDF
   - Verify PDF contains correct data

### Browser Compatibility Testing

**Browsers to Test**:

- Chrome (latest)
- Firefox (latest)
- Edge (latest)
- Safari (latest on macOS/iOS)

**Devices to Test**:

- Desktop: 1920x1080, 1366x768
- Tablet: iPad (1024x768), Android tablet (800x1280)
- Mobile: iPhone (375x667), Android phone (360x640)

**Test Checklist**:

- [ ] All pages render correctly
- [ ] Forms submit successfully
- [ ] Modals open and close properly
- [ ] Charts display correctly
- [ ] Images upload and display
- [ ] Navigation works on all screen sizes
- [ ] Touch interactions work on mobile

### Performance Testing

**Metrics to Measure**:

- Page load time (target: < 3 seconds)
- API response time (target: < 1 second)
- Database query execution time
- Image upload time

**Test Scenarios**:

1. Load dashboard with 100 bookings
2. Load master calendar with 500 bookings
3. Generate analytics report for 1 year of data
4. Upload 2MB service image
5. Concurrent admin users (simulate 5 admins)

**Tools**: Browser DevTools Network tab, MySQL slow query log

### Security Testing

**Test Cases**:

- [ ] SQL injection attempts in all input fields
- [ ] XSS attempts in text fields
- [ ] CSRF token validation on all forms
- [ ] Session hijacking prevention
- [ ] Password strength enforcement
- [ ] File upload validation (type, size)
- [ ] Direct URL access without authentication
- [ ] Privilege escalation attempts

---

## Security Considerations

### Authentication & Authorization

1. **Password Security**:
   - Hash passwords using `password_hash()` with PASSWORD_BCRYPT
   - Enforce strong password policy (min 8 chars, uppercase, number, symbol)
   - Never store plain text passwords
   - Implement password reset functionality with time-limited tokens

2. **Session Management**:
   - Use PHP sessions with secure configuration
   - Regenerate session ID after login: `session_regenerate_id(true)`
   - Set session timeout: 30 minutes of inactivity
   - Clear session on logout
   - Store minimal data in session (email, role, login time)

3. **Access Control**:
   - Check authentication on every admin page
   - Verify admin role before allowing access
   - Implement CSRF tokens for all state-changing operations
   - Use prepared statements for all database queries

### Input Validation & Sanitization

1. **Server-Side Validation**:
   - Validate all input on the server (never trust client-side validation)
   - Use whitelist approach for allowed values
   - Validate data types, lengths, formats
   - Reject requests with invalid data

2. **SQL Injection Prevention**:
   - Use prepared statements with parameterized queries
   - Never concatenate user input into SQL queries
   - Escape output when displaying user-generated content

3. **XSS Prevention**:
   - Use `htmlspecialchars()` when outputting user data to HTML
   - Set Content-Security-Policy headers
   - Validate and sanitize rich text input

4. **File Upload Security**:
   - Validate file type using MIME type checking
   - Limit file size (max 2MB for images)
   - Rename uploaded files to prevent directory traversal
   - Store uploads outside web root or in protected directory
   - Scan for malware if possible

### Data Protection

1. **Database Security**:
   - Use least privilege principle for database user
   - Encrypt sensitive data at rest (if required)
   - Regular database backups
   - Audit logging for sensitive operations

2. **HTTPS**:
   - Enforce HTTPS in production
   - Set secure cookie flags: `session.cookie_secure = true`
   - Implement HSTS headers

3. **Error Handling**:
   - Never expose stack traces or database errors to users
   - Log detailed errors server-side
   - Return generic error messages to client

---

## Performance Optimization

### Database Optimization

1. **Indexing Strategy**:

```sql
-- Indexes for common queries
CREATE INDEX idx_booking_date ON Booking(booking_date);
CREATE INDEX idx_booking_status ON Booking(status);
CREATE INDEX idx_service_active ON Service(is_active);
CREATE INDEX idx_staff_active ON Staff(is_active);
CREATE INDEX idx_staff_schedule_date ON Staff_Schedule(work_date);
```

2. **Query Optimization**:
   - Use JOINs instead of multiple queries
   - Limit result sets with LIMIT clause
   - Use aggregate functions (COUNT, SUM) in database
   - Cache frequently accessed data (service list, staff list)

3. **Connection Pooling**:
   - Reuse database connections
   - Close connections after use
   - Use persistent connections for high-traffic scenarios

### Frontend Optimization

1. **Asset Optimization**:
   - Minify CSS and JavaScript files
   - Compress images (use WebP format if supported)
   - Lazy load images below the fold
   - Use CSS sprites for icons

2. **Caching Strategy**:
   - Set appropriate cache headers for static assets
   - Use browser caching for images, CSS, JS
   - Implement server-side caching for analytics data (5-minute cache)

3. **Code Splitting**:
   - Load Chart.js only on analytics pages
   - Load calendar library only on calendar page
   - Defer non-critical JavaScript

### Server Optimization

1. **PHP Configuration**:
   - Enable OPcache for PHP bytecode caching
   - Increase memory_limit if needed for large datasets
   - Set appropriate max_execution_time

2. **Apache Configuration**:
   - Enable gzip compression
   - Configure KeepAlive for persistent connections
   - Set appropriate cache headers

---

## Deployment Considerations

### XAMPP Local Development

1. **Setup Steps**:
   - Install XAMPP with PHP 7.4+ and MySQL 5.7+
   - Start Apache and MySQL services
   - Import database schema
   - Configure `config/config.php` with local credentials
   - Set file permissions for upload directories

2. **Development Workflow**:
   - Use version control (Git) for code management
   - Test on multiple browsers during development
   - Use browser DevTools for debugging
   - Check PHP error logs in XAMPP

### Production Deployment (Future)

1. **Hosting Requirements**:
   - PHP 7.4+ with MySQLi extension
   - MySQL 5.7+ or MariaDB 10.3+
   - HTTPS support
   - Cron job support for scheduled tasks
   - Minimum 512MB RAM, 10GB storage

2. **Deployment Checklist**:
   - [ ] Update database credentials in config
   - [ ] Set `display_errors = Off` in php.ini
   - [ ] Enable error logging to file
   - [ ] Set secure session configuration
   - [ ] Configure HTTPS and SSL certificate
   - [ ] Set up automated database backups
   - [ ] Configure cron jobs for analytics
   - [ ] Test all functionality in production environment

3. **Monitoring**:
   - Monitor error logs daily
   - Track page load times
   - Monitor database performance
   - Set up uptime monitoring (target: 99% during operating hours)

---

## Responsive Design Strategy

### Breakpoints

```css
/* Mobile First Approach */
/* Mobile: 320px - 767px (default) */
/* Tablet: 768px - 1023px */
@media (min-width: 768px) { ... }

/* Desktop: 1024px+ */
@media (min-width: 1024px) { ... }
```

### Layout Adaptations

1. **Navigation**:
   - Mobile: Hamburger menu with slide-out sidebar
   - Tablet/Desktop: Fixed sidebar navigation

2. **Tables**:
   - Mobile: Card-based layout with stacked information
   - Tablet/Desktop: Traditional table layout

3. **Forms**:
   - Mobile: Full-width inputs, stacked labels
   - Desktop: Two-column layout for efficiency

4. **Calendar**:
   - Mobile: Day view only
   - Tablet: Week view
   - Desktop: Month view with day/week options

5. **Charts**:
   - Mobile: Simplified charts, one per screen
   - Desktop: Multiple charts in grid layout

### Touch Optimization

- Minimum touch target size: 44x44px
- Adequate spacing between interactive elements
- Swipe gestures for calendar navigation on mobile
- Pull-to-refresh for data updates (optional)

---

## Future Enhancements

1. **Real-time Updates**: WebSocket integration for live calendar updates
2. **Advanced Analytics**: Predictive analytics for booking trends
3. **Multi-language Support**: Internationalization for English/Malay
4. **Mobile App**: Native iOS/Android apps
5. **API Documentation**: Swagger/OpenAPI documentation for API endpoints
6. **Automated Testing**: CI/CD pipeline with automated tests
7. **Role-based Permissions**: Granular permissions for different admin roles
8. **Audit Trail**: Comprehensive logging of all admin actions
