-- Lumière Beauty Salon Database Schema

CREATE DATABASE IF NOT EXISTS lumiere_salon;
USE lumiere_salon;

-- Staff table
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    profile_image VARCHAR(255),
    role VARCHAR(50) DEFAULT 'stylist',
    hire_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Services table
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers table
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Appointments table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    customer_id INT NOT NULL,
    service_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration_minutes INT NOT NULL,
    status ENUM('confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'confirmed',
    notes TEXT,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Customer ratings table
CREATE TABLE customer_ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    staff_id INT NOT NULL,
    customer_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Sample data
INSERT INTO staff (first_name, last_name, email, phone, password_hash, role, hire_date) VALUES
('Sarah', 'Mitchell', 'sarah.mitchell@lumiere.com', '+1 (555) 123-4567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'senior_stylist', '2020-03-15'),
('Emma', 'Johnson', 'emma.johnson@lumiere.com', '+1 (555) 234-5678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'stylist', '2021-06-01'),
('Michael', 'Chen', 'michael.chen@lumiere.com', '+1 (555) 345-6789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'stylist', '2022-01-10');

INSERT INTO services (name, description, duration_minutes, price, category) VALUES
('Haircut & Styling', 'Professional haircut with blow-dry and styling', 60, 85.00, 'Haircut'),
('Hair Color Treatment', 'Full hair color application with consultation', 90, 120.00, 'Color'),
('Balayage Highlights', 'Hand-painted highlights for natural look', 120, 180.00, 'Color'),
('Hair Treatment', 'Deep conditioning and repair treatment', 45, 65.00, 'Treatment'),
('Special Occasion Styling', 'Updo or formal styling for events', 75, 95.00, 'Styling');

INSERT INTO customers (first_name, last_name, email, phone) VALUES
('Emma', 'Johnson', 'emma.johnson@email.com', '+1 (555) 111-2222'),
('Sophia', 'Chen', 'sophia.chen@email.com', '+1 (555) 222-3333'),
('Isabella', 'Rodriguez', 'isabella.rodriguez@email.com', '+1 (555) 333-4444'),
('Olivia', 'Davis', 'olivia.davis@email.com', '+1 (555) 444-5555'),
('Ava', 'Wilson', 'ava.wilson@email.com', '+1 (555) 555-6666');

INSERT INTO appointments (staff_id, customer_id, service_id, appointment_date, appointment_time, duration_minutes, status, total_price) VALUES
(1, 1, 1, '2025-12-05', '09:00:00', 60, 'completed', 85.00),
(1, 2, 2, '2025-12-05', '10:30:00', 90, 'confirmed', 120.00),
(1, 3, 3, '2025-12-05', '14:00:00', 120, 'confirmed', 180.00),
(1, 4, 1, '2025-12-05', '16:30:00', 60, 'cancelled', 65.00),
(1, 5, 4, '2025-12-06', '09:30:00', 45, 'confirmed', 65.00);

INSERT INTO customer_ratings (appointment_id, staff_id, customer_id, rating, comment) VALUES
(1, 1, 1, 5, 'Excellent service! Sarah did an amazing job with my haircut.'),
(2, 1, 2, 5, 'Love my new hair color. Very professional and friendly.'),
(3, 1, 3, 4, 'Great balayage technique. Will definitely book again.');

-- Create indexes for better performance
CREATE INDEX idx_appointments_staff_date ON appointments(staff_id, appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_customer_ratings_staff ON customer_ratings(staff_id);
CREATE INDEX idx_staff_email ON staff(email);
CREATE INDEX idx_customers_email ON customers(email);