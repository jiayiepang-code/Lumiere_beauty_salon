-- ============================================
-- Database Indexes for Performance Optimization
-- Task 10: Optimize performance and add security measures
-- ============================================

-- Indexes for Booking table (most frequently queried)
CREATE INDEX IF NOT EXISTS idx_booking_date ON Booking(booking_date);
CREATE INDEX IF NOT EXISTS idx_booking_status ON Booking(status);
CREATE INDEX IF NOT EXISTS idx_booking_customer_email ON Booking(customer_email);
CREATE INDEX IF NOT EXISTS idx_booking_date_status ON Booking(booking_date, status);
CREATE INDEX IF NOT EXISTS idx_booking_created_at ON Booking(created_at);

-- Indexes for Service table
CREATE INDEX IF NOT EXISTS idx_service_active ON Service(is_active);
CREATE INDEX IF NOT EXISTS idx_service_category ON Service(service_category);
CREATE INDEX IF NOT EXISTS idx_service_category_active ON Service(service_category, is_active);

-- Indexes for Staff table
CREATE INDEX IF NOT EXISTS idx_staff_active ON Staff(is_active);
CREATE INDEX IF NOT EXISTS idx_staff_role ON Staff(role);
CREATE INDEX IF NOT EXISTS idx_staff_role_active ON Staff(role, is_active);
CREATE INDEX IF NOT EXISTS idx_staff_phone ON Staff(phone);

-- Indexes for Booking_Service table (join table)
CREATE INDEX IF NOT EXISTS idx_booking_service_booking_id ON Booking_Service(booking_id);
CREATE INDEX IF NOT EXISTS idx_booking_service_service_id ON Booking_Service(service_id);
CREATE INDEX IF NOT EXISTS idx_booking_service_staff_email ON Booking_Service(staff_email);
CREATE INDEX IF NOT EXISTS idx_booking_service_status ON Booking_Service(service_status);
CREATE INDEX IF NOT EXISTS idx_booking_service_composite ON Booking_Service(booking_id, service_id, staff_email);

-- Indexes for Staff_Schedule table
CREATE INDEX IF NOT EXISTS idx_staff_schedule_staff_email ON Staff_Schedule(staff_email);
CREATE INDEX IF NOT EXISTS idx_staff_schedule_work_date ON Staff_Schedule(work_date);
CREATE INDEX IF NOT EXISTS idx_staff_schedule_status ON Staff_Schedule(status);
CREATE INDEX IF NOT EXISTS idx_staff_schedule_composite ON Staff_Schedule(staff_email, work_date);

-- Indexes for Customer table (if exists and used in admin)
CREATE INDEX IF NOT EXISTS idx_customer_email ON Customer(customer_email);

-- Indexes for Login_Attempts table (security), i accidentally removed these in MySQL database earlier
CREATE INDEX IF NOT EXISTS idx_login_attempts_phone ON Login_Attempts(phone);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON Login_Attempts(ip_address);
CREATE INDEX IF NOT EXISTS idx_login_attempts_created_at ON Login_Attempts(created_at);

-- Indexes for Admin_Login_Log table (audit), i accidentally removed these in MySQL database earlier
CREATE INDEX IF NOT EXISTS idx_admin_login_log_staff_email ON Admin_Login_Log(staff_email);
CREATE INDEX IF NOT EXISTS idx_admin_login_log_login_time ON Admin_Login_Log(login_time);

-- ============================================
-- Notes:
-- 1. These indexes improve query performance for common operations:
--    - Filtering bookings by date and status
--    - Finding active services/staff
--    - Joining Booking with Booking_Service
--    - Searching by customer email
--
-- 2. Composite indexes are created for frequently combined filters
--
-- 3. Indexes on foreign keys improve JOIN performance
--
-- 4. Monitor query performance and adjust indexes as needed
-- ============================================

