<?php
session_start();
require_once '../config/database.php';

// If not logged in → redirect
if (!isset($_SESSION['customer_phone'])) {
    header("Location: ../login.php");
    exit();
}

// Load customer data from database
$database = new Database();
$db = $database->getConnection();
$phone = $_SESSION['customer_phone'];

$query = "SELECT first_name, last_name, phone, customer_email FROM customer WHERE phone = ?";
$stmt = $db->prepare($query);
$stmt->execute([$phone]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer) {
    $first = $customer['first_name'] ?? "Guest";
    $last = $customer['last_name'] ?? "";
    $email = $customer['customer_email'] ?? "";
} else {
    $first = $_SESSION['first_name'] ?? "Guest";
    $last = $_SESSION['last_name'] ?? "";
    $email = $_SESSION['customer_email'] ?? $_SESSION['user_email'] ?? "";
}

// Only show first 2 characters of first name (e.g., "Jisoo" -> "JI")
$initials = strtoupper(substr($first, 0, 2));

// Customer table uses customer_email as primary key, not customer_id
// We'll use customer_email for all queries

// Fetch bookings using customer_email (customer table uses email as primary key)
$bookingsQuery = "SELECT 
    b.*,
    COUNT(bs.booking_service_id) as service_count
FROM `booking` b
LEFT JOIN `booking_service` bs ON b.booking_id = bs.booking_id
WHERE LOWER(TRIM(b.customer_email)) = LOWER(TRIM(?))
GROUP BY b.booking_id
ORDER BY b.booking_date DESC, b.start_time DESC
LIMIT 50";

try {
    // Use customer_email (customer table uses email as primary key)
    $queryEmail = $email;
    if (!$queryEmail) {
        $queryEmail = $_SESSION['customer_email'] ?? $_SESSION['user_email'] ?? null;
    }
    if (!$queryEmail && isset($phone) && $phone) {
        $fallbackEmailQuery = "SELECT customer_email FROM `customer` WHERE phone = ? LIMIT 1";
        $fallbackEmailStmt = $db->prepare($fallbackEmailQuery);
        $fallbackEmailStmt->execute([$phone]);
        $fallbackEmailRow = $fallbackEmailStmt->fetch(PDO::FETCH_ASSOC);
        $queryEmail = $fallbackEmailRow['customer_email'] ?? null;
    }
    
    if ($queryEmail) {
        error_log('Dashboard - Querying bookings with customer_email: ' . $queryEmail);
        
        // Check count
        $checkQuery = "SELECT COUNT(*) as count FROM `booking` WHERE LOWER(TRIM(customer_email)) = LOWER(TRIM(?))";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([strtolower(trim($queryEmail))]);
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $totalCount = $checkResult['count'] ?? 0;
        error_log('Dashboard - Total bookings in DB for email [' . $queryEmail . ']: ' . $totalCount);
        
        if ($totalCount > 0) {
            $bookingsStmt = $db->prepare($bookingsQuery);
            $bookingsStmt->execute([strtolower(trim($queryEmail))]);
            $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log('Dashboard bookings query - Email: [' . $queryEmail . '], Found: ' . count($bookings) . ' bookings');
            
            if (count($bookings) > 0) {
                error_log('Sample booking IDs: ' . implode(', ', array_slice(array_column($bookings, 'booking_id'), 0, 3)));
            }
        } else {
            error_log('Dashboard - No bookings found in database for email: ' . $queryEmail);
            $bookings = [];
        }
    } else {
        error_log('Dashboard - No email found. Phone: ' . ($phone ?? 'NULL'));
        $bookings = [];
    }
    
    // Get booking services for each booking (if we have bookings)
    if (!empty($bookings)) {
            
        // Get booking services for each booking (matching booking history logic)
        $bookingServices = [];
        foreach($bookings as $index => $booking) {
                $serviceQuery = "SELECT 
                    bs.*,
                    COALESCE(s.service_name, s.name) as service_name,
                    st.first_name as staff_first_name,
                    st.last_name as staff_last_name
                FROM `booking_service` bs
                JOIN `service` s ON bs.service_id = s.service_id
                LEFT JOIN `staff` st ON (bs.staff_id = st.staff_id OR bs.staff_email = st.staff_email)
                WHERE bs.booking_id = ?";
                
                $serviceStmt = $db->prepare($serviceQuery);
                $serviceStmt->execute([$booking['booking_id']]);
                $bookingServices[$booking['booking_id']] = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Build services and staff_names strings for compatibility with existing display code
                $serviceNames = array_map(function($service) {
                    return $service['service_name'];
                }, $bookingServices[$booking['booking_id']]);
                $bookings[$index]['services'] = !empty($serviceNames) ? implode(', ', $serviceNames) : 'Service';
                
                $staffNames = array_filter(array_map(function($service) {
                    if (!empty($service['staff_first_name']) && !empty($service['staff_last_name'])) {
                        return $service['staff_first_name'] . ' ' . $service['staff_last_name'];
                    }
                    return null;
                }, $bookingServices[$booking['booking_id']]));
            $bookings[$index]['staff_names'] = !empty($staffNames) ? implode(', ', $staffNames) : 'Staff Member';
        }
    }
} catch (Exception $e) {
    error_log('Bookings query failed: ' . $e->getMessage());
    error_log('customer_id used: ' . ($customerId ?? 'NULL'));
    $bookings = [];
}

// Fetch favorite staff (use customer_email, not customer_phone)
$favorites = [];
try {
    // Get customer email first
    $queryEmail = $email;
    if (!$queryEmail && isset($phone) && $phone) {
        $fallbackEmailQuery = "SELECT customer_email FROM customer WHERE phone = ? LIMIT 1";
        $fallbackEmailStmt = $db->prepare($fallbackEmailQuery);
        $fallbackEmailStmt->execute([$phone]);
        $fallbackEmailRow = $fallbackEmailStmt->fetch(PDO::FETCH_ASSOC);
        $queryEmail = $fallbackEmailRow['customer_email'] ?? null;
    }
    
    if ($queryEmail) {
        $favoritesQuery = "SELECT 
            f.staff_email,
            s.first_name,
            s.last_name,
            s.role,
            s.staff_image,
            s.bio,
            GROUP_CONCAT(DISTINCT sv.service_name ORDER BY ss.proficiency_level DESC SEPARATOR ' & ') as primary_services
        FROM customer_favourites f
        JOIN staff s ON f.staff_email = s.staff_email
        LEFT JOIN staff_service ss ON s.staff_email = ss.staff_email AND ss.is_active = 1
        LEFT JOIN service sv ON ss.service_id = sv.service_id AND sv.is_active = 1
        WHERE f.customer_email = ? AND s.is_active = 1
        GROUP BY f.staff_email, s.first_name, s.last_name, s.role, s.staff_image, s.bio";

        $favoritesStmt = $db->prepare($favoritesQuery);
        $favoritesStmt->execute([$queryEmail]);
        $favorites = $favoritesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Dashboard favorites - Email: ' . $queryEmail . ', Found: ' . count($favorites) . ' favorites');
    }
} catch (Exception $e) {
    // If table doesn't exist or query fails, just use empty array
    // Log error for debugging
    error_log('Favorites query failed: ' . $e->getMessage());
    $favorites = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Lumière</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../user/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .dash-section { 
            display: none; 
            width: 100%;
            max-width: 100%;
        }
        .dash-section.active { 
            display: block !important; 
            width: 100%;
            max-width: 100%;
            visibility: visible !important;
            opacity: 1 !important;
        }
        .dash-nav a.active { background-color: var(--primary-brown); color: white; }
        
        /* Ensure main content area is properly positioned */
        .dash-main {
            position: relative;
            width: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .dash-section {
            width: 100%;
            align-self: flex-start;
        }
        
        /* =========================================
           BOOKINGS PAGE REDESIGN
        ========================================= */
        
        .bookings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .bookings-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #5c4e4b;
            margin: 0;
        }
        
        .btn-new-booking {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #c29076;
            color: white;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-new-booking:hover {
            background: #a87b65;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(194, 144, 118, 0.3);
            color: white;
        }
        
        .bookings-section {
            margin-bottom: 3rem;
        }
        
        .section-subtitle {
            font-size: 1.3rem;
            font-weight: 600;
            color: #5c4e4b;
            margin-bottom: 1.5rem;
        }
        
        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        .booking-card-new {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .booking-card-new:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }
        
        .booking-card-cancelled {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 1.5rem;
            opacity: 0.75;
            transition: all 0.3s ease;
        }
        
        .booking-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .booking-id-text {
            font-size: 0.85rem;
            font-family: monospace;
            color: #8a8a95;
        }
        
        .badge-confirmed {
            padding: 0.4rem 0.75rem;
            background: #d4edda;
            color: #155724;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
        }
        
        .badge-cancelled {
            padding: 0.4rem 0.75rem;
            background: #f8d7da;
            color: #721c24;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
        }
        
        .badge-completed {
            padding: 0.4rem 0.75rem;
            background: #e2e3e5;
            color: #383d41;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
        }
        
        .booking-card-content {
            margin-bottom: 1.5rem;
        }
        
        .info-section {
            margin-bottom: 1.2rem;
        }
        
        .info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #8a8a95;
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #5c4e4b;
            margin: 0.2rem 0;
        }
        
        .info-value-small {
            font-size: 0.9rem;
            color: #5c4e4b;
            margin: 0;
        }
        
        .info-section-total {
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
            margin-bottom: 1rem;
        }
        
        .price-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #c29076;
            margin: 0.3rem 0 0 0;
        }
        
        .booking-card-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-view {
            flex: 1;
            padding: 0.6rem 1rem;
            border: 1px solid #c29076;
            background: white;
            color: #c29076;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-view:hover {
            background: #f5e9e4;
        }
        
        .btn-cancel {
            flex: 1;
            padding: 0.6rem 1rem;
            border: 1px solid #dc3545;
            background: white;
            color: #dc3545;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #fff5f5;
        }
        
        .btn-book-again {
            flex: 1;
            padding: 0.6rem 1rem;
            border: none;
            background: #c29076;
            color: white;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-book-again:hover {
            background: #a87b65;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 3rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            width: 100%;
            margin: 0;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #c29076;
            margin-bottom: 1.5rem;
        }
        
        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #5c4e4b;
            margin: 0 0 1rem 0;
        }
        
        .empty-state p {
            font-size: 1rem;
            color: #8a8a95;
            margin: 0 0 2rem 0;
            line-height: 1.6;
        }
        
        .empty-state-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #c29076;
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .empty-state-btn:hover {
            background: #a87b65;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(194, 144, 118, 0.3);
            color: white;
        }
        
        /* =========================================
           DASHBOARD OVERVIEW
        ========================================= */
        
        .overview-stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }
        
        .overview-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(194, 144, 118, 0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #8a8a95;
            margin: 0 0 5px 0;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #5c4e4b;
            margin: 0;
            font-family: 'Playfair Display', serif;
        }
        
        .upcoming-appointment-banner {
            background: linear-gradient(135deg, #c29076, #a87b65);
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 6px 20px rgba(194, 144, 118, 0.3);
        }
        
        .action-card {
            background: linear-gradient(135deg, #ffffff, #FFF8F0);
            border-radius: 16px;
            padding: 1.8rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(194, 144, 118, 0.2);
            background: linear-gradient(135deg, #FFF8F0, #f5e9e4);
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #c29076, #a87b65);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .action-content {
            flex: 1;
        }
        
        .action-content h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #5c4e4b;
            margin: 0 0 5px 0;
            font-family: 'Playfair Display', serif;
        }
        
        .action-content p {
            font-size: 0.9rem;
            color: #8a8a95;
            margin: 0;
        }
        
        .action-arrow {
            font-size: 1.3rem;
            color: #c29076;
            transition: transform 0.3s ease;
        }
        
        .action-card:hover .action-arrow {
            transform: translateX(5px);
        }
        
        .recent-appointments-section {
            margin-top: 2rem;
        }
        
        .recent-appointment-item {
            background: white;
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }
        
        .recent-appointment-item:hover {
            box-shadow: 0 4px 12px rgba(194, 144, 118, 0.15);
            transform: translateX(5px);
        }
        
        .recent-icon {
            width: 50px;
            height: 50px;
            background: #f5e9e4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c29076;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .recent-details {
            flex: 1;
        }
        
        .recent-details h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #5c4e4b;
            margin: 0 0 3px 0;
        }
        
        .recent-details p {
            font-size: 0.9rem;
            color: #8a8a95;
            margin: 0;
        }
        
        .recent-date {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        
        .recent-date span {
            font-size: 0.9rem;
            color: #8a8a95;
            font-weight: 500;
        }
        
        /* =========================================
           PROFILE PAGE REDESIGN
        ========================================= */
        
        .profile-info-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #5c4e4b;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: #5c4e4b;
            margin-bottom: 0.5rem;
        }
        
        .form-control-custom {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control-custom:focus {
            outline: none;
            border-color: #c29076;
            box-shadow: 0 0 0 3px rgba(194, 144, 118, 0.1);
        }
        
        .form-control-custom:read-only {
            background: #f9f9f9;
            cursor: not-allowed;
        }
        
        .btn-custom {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary-custom {
            background: #c29076;
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: #a87b65;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(194, 144, 118, 0.3);
        }
        
        .btn-success-custom {
            background: #5cb85c;
            color: white;
        }
        
        .btn-success-custom:hover {
            background: #4cae4c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(92, 184, 92, 0.3);
        }
        
        .btn-secondary-custom {
            background: #8a8a95;
            color: white;
        }
        
        .btn-secondary-custom:hover {
            background: #6c757d;
        }
        
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: #f9f9f9;
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: #f5f5f5;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #5c4e4b;
            margin: 0 0 0.3rem 0;
        }
        
        .notification-desc {
            font-size: 0.9rem;
            color: #8a8a95;
            margin: 0;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background-color: #c29076;
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        /* Back arrow styling */
        .back-link {
            color: #c29076;
            text-decoration: none;
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: inline-block;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #a87b65;
        }
        
        /* =========================================
           FAVOURITES STAFF PAGE
        ========================================= */
        
        .favourites-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .favourites-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #5c4e4b;
            margin: 0;
        }
        
        .btn-browse-staff {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #c29076;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-browse-staff:hover {
            background: #a87b65;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(194, 144, 118, 0.3);
            color: white;
        }
        
        .favourites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .staff-favourite-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .staff-favourite-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }
        
        .staff-favourite-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #d4af37;
            margin-bottom: 1rem;
        }
        
        .staff-favourite-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #833E18;
            margin: 0.5rem 0;
        }
        
        .staff-favourite-services {
            margin: 1rem 0;
            width: 100%;
        }
        
        .staff-favourite-services-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #8a8a95;
            margin-bottom: 0.3rem;
        }
        
        .staff-favourite-services-value {
            font-size: 1rem;
            color: #5c4e4b;
            margin: 0;
        }
        
        .staff-favourite-bio {
            font-size: 0.9rem;
            color: #8a8a95;
            margin: 0.5rem 0 1.5rem 0;
            font-style: italic;
        }
        
        .staff-favourite-actions {
            display: flex;
            gap: 0.75rem;
            width: 100%;
            margin-top: auto;
        }
        
        .btn-book-now {
            flex: 1;
            padding: 0.75rem 1rem;
            background: #c29076;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-book-now:hover {
            background: #a87b65;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(194, 144, 118, 0.3);
        }
        
        .btn-remove-favourite {
            flex: 1;
            padding: 0.75rem 1rem;
            background: white;
            color: #dc3545;
            border: 2px solid #f8d7da;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-remove-favourite:hover {
            background: #fff5f5;
            border-color: #dc3545;
        }
        
        .favourites-empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            color: #8a8a95;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .favourites-empty-state h3 {
            font-family: 'Playfair Display', serif;
            color: #5c4e4b;
            margin-bottom: 1rem;
        }
        
        .favourites-empty-state p {
            margin-bottom: 2rem;
        }
        
        /* =========================================
           HELP & SUPPORT PAGE
        ========================================= */
        
        .help-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .help-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .help-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #5c4e4b;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
        }
        
        .help-card-desc {
            font-size: 0.95rem;
            color: #8a8a95;
            margin: 0 0 1.5rem 0;
            line-height: 1.6;
        }
        
        .help-contact-form {
            margin-top: 1.5rem;
        }
        
        .help-contact-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .help-contact-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .help-contact-form label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #5c4e4b;
            margin-bottom: 0.5rem;
        }
        
        .help-contact-form textarea.form-control-custom {
            resize: vertical;
            min-height: 120px;
        }
        
        .help-submit-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #c29076;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .help-submit-btn:hover {
            background: #a87b65;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(194, 144, 118, 0.3);
        }
        
        /* FAQ Styles */
        .faq-list {
            margin-top: 1.5rem;
        }
        
        .faq-item {
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 0;
        }
        
        .faq-item:last-child {
            border-bottom: none;
        }
        
        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .faq-question:hover {
            color: #c29076;
        }
        
        .faq-question span {
            font-size: 1rem;
            font-weight: 600;
            color: #5c4e4b;
        }
        
        .faq-question i {
            color: #c29076;
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }
        
        .faq-item.active .faq-question i.fa-minus {
            display: inline-block;
        }
        
        .faq-item.active .faq-question i.fa-plus {
            display: none;
        }
        
        .faq-item:not(.active) .faq-question i.fa-minus {
            display: none;
        }
        
        .faq-item:not(.active) .faq-question i.fa-plus {
            display: inline-block;
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            padding: 0 0;
        }
        
        .faq-answer.active {
            max-height: 500px;
            padding: 0 0 1.25rem 0;
        }
        
        .faq-answer p {
            font-size: 0.95rem;
            color: #8a8a95;
            line-height: 1.6;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .help-contact-form .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="dashboard-container">

    <!-- SIDEBAR -->
    <aside class="dash-sidebar">

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="sidebar-header">
            <div class="avatar-circle-lg"><?= $initials ?></div>

            <div class="user-meta">
                <p>Welcome back,</p>
                <h3><?= htmlspecialchars($first . " " . $last) ?></h3>
                <span class="member-badge">Customer</span>
            </div>
        </div>

        <ul class="dash-nav">
            <li><a href="#" onclick="switchSection('overview', this)" class="active">Overview</a></li>
            <li><a href="#" onclick="switchSection('bookings', this)">My Bookings</a></li>
            <li><a href="#" onclick="switchSection('profile', this)">My Profile</a></li>
            <li><a href="#" onclick="switchSection('favourites', this)">Favourites Staff</a></li>
            <li><a href="#" onclick="switchSection('help', this)">Help & Support</a></li>
        </ul>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="dash-main">

        <!-- ========== SECTION 1: DASHBOARD OVERVIEW ========== -->
        <div id="section-overview" class="dash-section active">
            <h1 class="favourites-title" style="margin-bottom: 30px;">Dashboard Overview</h1>

            <?php
            // Calculate stats for overview
            $completedCount = 0;
            $nextAppointment = null;
            $upcomingAppointment = null;
            $recentAppointments = [];
            $today = new DateTime();
            
            foreach ($bookings as $booking) {
                $bookingDate = new DateTime($booking['booking_date']);
                
                if ($booking['status'] === 'completed') {
                    $completedCount++;
                }
                
                // Find next upcoming appointment
                if (($booking['status'] === 'confirmed') && $bookingDate >= $today) {
                    if (!$nextAppointment || $bookingDate < new DateTime($nextAppointment['booking_date'])) {
                        $nextAppointment = $booking;
                        $upcomingAppointment = $booking;
                    }
                }
            }
            
            // Get recent 3 appointments (past ones)
            $recentAppointments = array_slice(array_filter($bookings, function($b) use ($today) {
                $date = new DateTime($b['booking_date']);
                return $date < $today || $b['status'] === 'completed';
            }), 0, 3);
            
            // Calculate days until next appointment
            $daysUntilNext = null;
            if ($nextAppointment) {
                $nextDate = new DateTime($nextAppointment['booking_date']);
                $interval = $today->diff($nextDate);
                $daysUntilNext = $interval->days;
            }
            
            // Get first favorite staff
            $favoriteStaff = !empty($favorites) ? $favorites[0]['first_name'] . ' ' . $favorites[0]['last_name'] : 'None';
            ?>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <!-- Next Appointment -->
                <div class="col-md-4">
                    <div class="overview-stat-card">
                        <div class="stat-icon" style="background: #f5e9e4;">
                            <i class="fas fa-calendar-alt" style="color: #c29076;"></i>
                        </div>
                        <div class="stat-content">
                            <p class="stat-label">Next Appointment</p>
                            <h3 class="stat-value">
                                <?php if ($daysUntilNext !== null): ?>
                                    <?php if ($daysUntilNext == 0): ?>
                                        Today
                                    <?php elseif ($daysUntilNext == 1): ?>
                                        Tomorrow
                                    <?php else: ?>
                                        In <?= $daysUntilNext ?> days
                                    <?php endif; ?>
                                <?php else: ?>
                                    No upcoming
                                <?php endif; ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- Completed Visits -->
                <div class="col-md-4">
                    <div class="overview-stat-card">
                        <div class="stat-icon" style="background: #f5e9e4;">
                            <i class="fas fa-check-circle" style="color: #5cb85c;"></i>
                        </div>
                        <div class="stat-content">
                            <p class="stat-label">Completed</p>
                            <h3 class="stat-value"><?= $completedCount ?> visits</h3>
                        </div>
                    </div>
                </div>

                <!-- Favorite Staff -->
                <div class="col-md-4">
                    <div class="overview-stat-card">
                        <div class="stat-icon" style="background: #f5e9e4;">
                            <i class="fas fa-star" style="color: #d4af37;"></i>
                        </div>
                        <div class="stat-content">
                            <p class="stat-label">Favorite Staff</p>
                            <h3 class="stat-value" style="font-size: 1.3rem;"><?= htmlspecialchars($favoriteStaff) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointment Banner -->
            <?php if ($upcomingAppointment): 
                $upcomingDate = new DateTime($upcomingAppointment['booking_date']);
                $upcomingTime = new DateTime($upcomingAppointment['start_time']);
                $upcomingServices = explode(', ', $upcomingAppointment['services'] ?? 'Service');
                $upcomingStaff = $upcomingAppointment['staff_names'] ?? 'Staff Member';
            ?>
            <div class="upcoming-appointment-banner">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 style="color: white; font-family: 'Playfair Display', serif; margin-bottom: 5px;">
                            <i class="fas fa-calendar-check me-2"></i>Upcoming Appointment
                        </h2>
                        <p style="color: rgba(255,255,255,0.9); margin: 0;">Your next visit is coming up soon!</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 5px;">Service</p>
                        <h4 style="color: white; font-weight: 600;"><?= htmlspecialchars($upcomingServices[0]) ?></h4>
                    </div>
                    <div class="col-md-3">
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 5px;">Staff Member</p>
                        <h4 style="color: white; font-weight: 600;">
                            <i class="fas fa-user-circle me-2"></i><?= htmlspecialchars(explode(',', $upcomingStaff)[0]) ?>
                        </h4>
                    </div>
                    <div class="col-md-3">
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 5px;">Date</p>
                        <h4 style="color: white; font-weight: 600;"><?= $upcomingDate->format('M d, Y') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 5px;">Time</p>
                        <h4 style="color: white; font-weight: 600;"><?= $upcomingTime->format('g:i A') ?></h4>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-4">
                    <button class="btn btn-light" style="flex: 1; border-radius: 12px; font-weight: 600;">
                        <i class="fas fa-edit me-2"></i>Reschedule
                    </button>
                    <button class="btn btn-outline-light" onclick="cancelBooking('<?= htmlspecialchars($upcomingAppointment['booking_id'], ENT_QUOTES) ?>')" style="flex: 1; border-radius: 12px; font-weight: 600; border: 2px solid white;">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Cards -->
            <div class="row g-4 mt-2 mb-4">
                <div class="col-md-6">
                    <div class="action-card" onclick="window.location.href='../booking.php'">
                        <div class="action-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="action-content">
                            <h3>Book New Appointment</h3>
                            <p>Schedule your next beauty session</p>
                        </div>
                        <i class="fas fa-arrow-right action-arrow"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="action-card" onclick="switchSection('bookings', null)">
                        <div class="action-icon" style="background: #f5e9e4;">
                            <i class="fas fa-history" style="color: #c29076;"></i>
                        </div>
                        <div class="action-content">
                            <h3>View All Bookings</h3>
                            <p>See your appointment history</p>
                        </div>
                        <i class="fas fa-arrow-right action-arrow"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Appointments -->
            <?php if (!empty($recentAppointments)): ?>
            <div class="recent-appointments-section">
                <h2 style="font-family: 'Playfair Display', serif; color: #5c4e4b; margin-bottom: 20px;">Recent Appointments</h2>
                
                <?php foreach ($recentAppointments as $recent): 
                    $recentDate = new DateTime($recent['booking_date']);
                    $recentServices = explode(', ', $recent['services'] ?? 'Service');
                    $recentStaff = explode(', ', $recent['staff_names'] ?? 'Staff');
                ?>
                <div class="recent-appointment-item">
                    <div class="recent-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="recent-details">
                        <h4><?= htmlspecialchars($recentServices[0]) ?></h4>
                        <p>with <?= htmlspecialchars($recentStaff[0]) ?></p>
                    </div>
                    <div class="recent-date">
                        <span><?= $recentDate->format('M d, Y') ?></span>
                        <button class="btn btn-sm" onclick="window.location.href='../booking.php'" style="background: #c29076; color: white; border-radius: 8px; padding: 4px 12px; font-size: 0.85rem;">Book Again</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ========== SECTION 2: PROFILE ========== -->
        <div id="section-profile" class="dash-section">
            <h1 class="favourites-title" style="margin-bottom: 30px;">My Profile</h1>

            <!-- Personal Information Card -->
            <div class="profile-info-card">
                <h2 class="card-title">Personal Information</h2>
                <form id="profile-form">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" id="profile-firstname" class="form-control-custom" value="<?= htmlspecialchars($first) ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" id="profile-lastname" class="form-control-custom" value="<?= htmlspecialchars($last) ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control-custom" value="<?= htmlspecialchars($phone) ?>" readonly disabled style="background: #f5f5f5;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" id="profile-email" class="form-control-custom" value="<?= htmlspecialchars($email) ?>" readonly>
                    </div>

                    <button type="button" class="btn-custom btn-primary-custom" id="edit-profile-btn">
                        <i class="fas fa-edit me-2"></i>Edit Details
                    </button>
                    <button type="button" class="btn-custom btn-success-custom" id="save-profile-btn" style="display:none;">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                    <button type="button" class="btn-custom btn-secondary-custom" id="cancel-profile-btn" style="display:none; margin-left:10px;">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </form>
                <div id="profile-message" style="margin-top:15px;"></div>
            </div>

            <!-- Security Card -->
            <div class="profile-info-card" style="margin-top: 25px;">
                <h2 class="card-title">Security</h2>
                <form id="password-form">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" id="current-password" class="form-control-custom" required placeholder="Enter current password">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" id="new-password" class="form-control-custom" required minlength="6" placeholder="Enter new password">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" id="confirm-password" class="form-control-custom" required minlength="6" placeholder="Confirm new password">
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-custom btn-primary-custom" id="update-password-btn">
                        <i class="fas fa-key me-2"></i>Update Password
                    </button>
                </form>
                <div id="password-message" style="margin-top:15px;"></div>
            </div>

            <!-- Notifications Card -->
            <div class="profile-info-card" style="margin-top: 25px;">
                <h2 class="card-title">Notifications</h2>

                <div class="notification-item">
                    <div class="notification-content">
                        <h4 class="notification-title">Email Reminders</h4>
                        <p class="notification-desc">Receive booking confirmation emails</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="notification-item">
                    <div class="notification-content">
                        <h4 class="notification-title">Promotional Offers</h4>
                        <p class="notification-desc">Receive updates and promotions</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- ========== SECTION 2: BOOKINGS ========== -->
        <div id="section-bookings" class="dash-section">
            <!-- Header -->
            <div class="bookings-header">
                <h1 class="bookings-title">Your Bookings</h1>
            </div>

            <?php 
            // Debug: Log bookings array
            error_log('Dashboard - Bookings array count: ' . count($bookings));
            error_log('Dashboard - Bookings array: ' . json_encode($bookings));
            if (empty($bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-alt empty-state-icon"></i>
                    <h3>No bookings yet</h3>
                    <p>You haven't made any bookings yet. Start by booking your first appointment!</p>
                    <a href="../booking.php" class="empty-state-btn">
                        <i class="fas fa-plus"></i>
                        Book Appointment
                    </a>
                </div>
            <?php else: ?>
                <?php 
                // Separate upcoming and history bookings
                $upcomingBookings = [];
                $historyBookings = [];
                $today = new DateTime('today');
                
                foreach ($bookings as $booking) {
                    $bookingDate = new DateTime($booking['booking_date']);
                    if ($booking['status'] === 'confirmed' && $bookingDate >= $today) {
                        $upcomingBookings[] = $booking;
                    } else {
                        $historyBookings[] = $booking;
                    }
                }
                ?>

                <!-- Upcoming Bookings -->
                <?php if (!empty($upcomingBookings)): ?>
                <div class="bookings-section">
                    <h2 class="section-subtitle">Upcoming Bookings</h2>
                    <div class="bookings-grid">
                        <?php foreach ($upcomingBookings as $booking): 
                            $bookingDate = new DateTime($booking['booking_date']);
                            $startTime = new DateTime($booking['start_time']);
                            // Use end_time if available, otherwise fallback to expected_finish_time
                            $endTimeValue = $booking['end_time'] ?? $booking['expected_finish_time'] ?? null;
                            $endTime = $endTimeValue ? new DateTime($endTimeValue) : null;
                            $services = $booking['services'] ?? 'Service';
                            $servicesArray = explode(', ', $services);
                            $serviceCount = $booking['service_count'] ?? count($servicesArray);
                            $status = $booking['status'] ?? 'confirmed';
                            // Use reference_id if available, otherwise fallback to booking_id
                            $referenceId = $booking['reference_id'] ?? $booking['booking_id'] ?? '';
                            $bookingId = $booking['booking_id'] ?? '';
                            // Use grand_total if available, otherwise fallback to total_price
                            $totalPrice = $booking['grand_total'] ?? $booking['total_price'] ?? 0;
                            
                            $dateFormatted = $bookingDate->format('d M Y');
                            $timeFormatted = $startTime->format('h:i A');
                            if ($endTime) {
                                $timeFormatted .= ' - ' . $endTime->format('h:i A');
                            }
                        ?>
                            <div class="booking-card-new">
                                <div class="booking-card-header">
                                    <div class="booking-id-text"><?= htmlspecialchars($referenceId) ?></div>
                                    <span class="badge-confirmed"><?= ucfirst($status) ?></span>
                                </div>

                                <div class="booking-card-content">
                                    <div class="info-section">
                                        <h3 class="info-label">Date & Time:</h3>
                                        <p class="info-value"><?= $dateFormatted ?></p>
                                        <p class="info-value"><?= $timeFormatted ?></p>
                                    </div>

                                    <div class="info-section">
                                        <h3 class="info-label">Services: (<?= $serviceCount ?>)</h3>
                                        <p class="info-value-small"><?= htmlspecialchars($services) ?></p>
                                    </div>

                                    <div class="info-section-total">
                                        <h3 class="info-label">Total:</h3>
                                        <p class="price-value">RM <?= number_format($totalPrice, 2) ?></p>
                                    </div>
                                </div>

                                <div class="booking-card-actions">
                                    <button class="btn-view" onclick="viewBookingDetails('<?= htmlspecialchars($booking['booking_id'], ENT_QUOTES) ?>')" data-bs-toggle="modal" data-bs-target="#bookingModal">
                                        View Details
                                    </button>
                                    <button class="btn-cancel" onclick="cancelBooking('<?= htmlspecialchars($booking['booking_id'], ENT_QUOTES) ?>')">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Booking History -->
                <?php if (!empty($historyBookings)): ?>
                <div class="bookings-section">
                    <h2 class="section-subtitle">Booking History</h2>
                    <div class="bookings-grid">
                        <?php foreach ($historyBookings as $booking): 
                            $bookingDate = new DateTime($booking['booking_date']);
                            $startTime = new DateTime($booking['start_time']);
                            // Use end_time if available, otherwise fallback to expected_finish_time
                            $endTimeValue = $booking['end_time'] ?? $booking['expected_finish_time'] ?? null;
                            $endTime = $endTimeValue ? new DateTime($endTimeValue) : null;
                            $services = $booking['services'] ?? 'Service';
                            $servicesArray = explode(', ', $services);
                            $serviceCount = $booking['service_count'] ?? count($servicesArray);
                            $status = $booking['status'] ?? 'confirmed';
                            // Use reference_id if available, otherwise fallback to booking_id
                            $referenceId = $booking['reference_id'] ?? $booking['booking_id'] ?? '';
                            $bookingId = $booking['booking_id'] ?? '';
                            // Use grand_total if available, otherwise fallback to total_price
                            $totalPrice = $booking['grand_total'] ?? $booking['total_price'] ?? 0;
                            
                            $dateFormatted = $bookingDate->format('d M Y');
                            $timeFormatted = $startTime->format('h:i A');
                            if ($endTime) {
                                $timeFormatted .= ' - ' . $endTime->format('h:i A');
                            }
                            
                            $badgeClass = $status === 'cancelled' ? 'badge-cancelled' : ($status === 'completed' ? 'badge-completed' : 'badge-confirmed');
                            $badgeText = ucfirst($status);
                            $cardClass = $status === 'cancelled' ? 'booking-card-cancelled' : 'booking-card-new';
                        ?>
                            <div class="<?= $cardClass ?>">
                                <div class="booking-card-header">
                                    <div class="booking-id-text"><?= htmlspecialchars($referenceId) ?></div>
                                    <span class="<?= $badgeClass ?>"><?= $badgeText ?></span>
                                </div>

                                <div class="booking-card-content">
                                    <div class="info-section">
                                        <h3 class="info-label">Date & Time:</h3>
                                        <p class="info-value"><?= $dateFormatted ?></p>
                                        <p class="info-value"><?= $timeFormatted ?></p>
                                    </div>

                                    <div class="info-section">
                                        <h3 class="info-label">Services: (<?= $serviceCount ?>)</h3>
                                        <p class="info-value-small"><?= htmlspecialchars($services) ?></p>
                                    </div>

                                    <div class="info-section-total">
                                        <h3 class="info-label">Total:</h3>
                                        <p class="price-value">RM <?= number_format($totalPrice, 2) ?></p>
                                    </div>
                                    
                                    <?php if (!empty($booking['special_requests'])): ?>
                                    <div class="info-section">
                                        <h3 class="info-label">Special Requests:</h3>
                                        <p class="info-value-small"><?= htmlspecialchars($booking['special_requests']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="booking-card-actions">
                                    <button class="btn-view" onclick="viewBookingDetails('<?= htmlspecialchars($bookingId, ENT_QUOTES) ?>')" data-bs-toggle="modal" data-bs-target="#bookingModal">
                                        View Details
                                    </button>
                                    <?php if ($status === 'pending' || $status === 'confirmed'): ?>
                                        <button class="btn-cancel" onclick="cancelBooking('<?= htmlspecialchars($bookingId, ENT_QUOTES) ?>')" <?= $bookingDate < new DateTime('today') ? 'disabled' : '' ?>>
                                            Cancel
                                        </button>
                                    <?php elseif ($status === 'completed'): ?>
                                        <button class="btn-book-again" onclick="window.location.href='../booking.php'">
                                            Book Again
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (isset($_GET['section']) && $_GET['section'] === 'bookings'): ?>
                <script>
                    // Force show bookings section when coming from confirmation
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(function() {
                            const bookingsSection = document.getElementById('section-bookings');
                            const profileSection = document.getElementById('section-profile');
                            if (bookingsSection && profileSection) {
                                profileSection.classList.remove('active');
                                bookingsSection.classList.add('active');
                                // Also update nav link
                                document.querySelectorAll('.dash-nav a').forEach(a => {
                                    a.classList.remove('active');
                                    if (a.getAttribute('onclick') && a.getAttribute('onclick').includes("'bookings'")) {
                                        a.classList.add('active');
                                    }
                                });
                            }
                        }, 50);
                    });
                </script>
            <?php endif; ?>
        </div>
        
        <!-- ========== SECTION 4: FAVOURITES STAFF ========== -->
        <div id="section-favourites" class="dash-section">
            <!-- Header -->
            <div class="favourites-header">
                <h1 class="favourites-title">My Favourite Staff</h1>
            </div>

            <?php if (empty($favorites)): ?>
                <div class="favourites-empty-state">
                    <h3>No Favourite Staff Yet</h3>
                    <p>Start adding your favourite staff members from the team page!</p>
                    <a href="team.php" class="btn-browse-staff">
                        <i class="fas fa-users"></i>
                        Browse Staff
                    </a>
                </div>
            <?php else: ?>
                <div class="favourites-grid">
                    <?php 
                    // Staff image mapping (same as team.php)
                    $staffImageMap = [
                        'Jay' => '../images/42.png',
                        'Mei' => '../images/47.png',
                        'Ken' => '../images/48.png',
                        'Chloe' => '../images/60.png',
                        'Sarah' => '../images/65.png',
                        'Nisha' => '../images/66.png',
                        'Rizal' => '../images/67.png',
                        'Siti' => '../images/68.png',
                        'Jessica' => '../images/69.png',
                        'Yuna' => '../images/71.png'
                    ];
                    
                    // Staff primary services mapping
                    $staffPrimaryServicesMap = [
                        'Jay' => 'Haircuts & Hair Styling',
                        'Mei' => 'Hair Styling & Hair Colouring',
                        'Ken' => 'Technical Cuts & Hair Treatments',
                        'Chloe' => 'Anti-Aging & Brightening',
                        'Sarah' => 'Deep Cleansing & Hydrating',
                        'Nisha' => 'Aromatherapy Massage & Hot Stone Massage',
                        'Rizal' => 'Deep Tissue Massage & Traditional Massage',
                        'Jessica' => 'Nail Extensions & Nail Gelish',
                        'Siti' => 'Classic Manicure & Add-ons',
                        'Yuna' => 'Nail Art Design & Gelish'
                    ];
                    
                    // Function to determine category name from services
                    function getCategoryName($primaryServices) {
                        $services = strtolower($primaryServices);
                        
                        // Check for hair-related services
                        if (strpos($services, 'hair') !== false || 
                            strpos($services, 'cut') !== false || 
                            strpos($services, 'styling') !== false || 
                            strpos($services, 'colouring') !== false || 
                            strpos($services, 'treatment') !== false) {
                            return 'Hair Stylist';
                        }
                        
                        // Check for beauty/facial services
                        if (strpos($services, 'facial') !== false || 
                            strpos($services, 'anti-aging') !== false || 
                            strpos($services, 'cleansing') !== false || 
                            strpos($services, 'hydrating') !== false || 
                            strpos($services, 'brightening') !== false) {
                            return 'Beautician';
                        }
                        
                        // Check for nail services
                        if (strpos($services, 'nail') !== false || 
                            strpos($services, 'manicure') !== false || 
                            strpos($services, 'pedicure') !== false || 
                            strpos($services, 'gelish') !== false || 
                            strpos($services, 'extension') !== false) {
                            return 'Nail Technician';
                        }
                        
                        // Check for massage services
                        if (strpos($services, 'massage') !== false || 
                            strpos($services, 'aromatherapy') !== false || 
                            strpos($services, 'hot stone') !== false || 
                            strpos($services, 'tissue') !== false || 
                            strpos($services, 'traditional') !== false) {
                            return 'Massage Therapist';
                        }
                        
                        // Default fallback
                        return 'Staff Member';
                    }
                    
                    foreach ($favorites as $fav): 
                        $staffName = htmlspecialchars($fav['first_name']);
                        $fullName = htmlspecialchars($fav['first_name'] . ' ' . $fav['last_name']);
                        $staffEmail = htmlspecialchars($fav['staff_email']);
                        
                        // Use mapped primary services if available, otherwise use from database
                        $primaryServices = $staffPrimaryServicesMap[$staffName] ?? htmlspecialchars($fav['primary_services'] ?? 'Various Services');
                        
                        // Get category name based on services
                        $categoryName = getCategoryName($primaryServices);
                        
                        // Use mapped image if available, otherwise use staff_image from DB, fallback to default
                        $image = $staffImageMap[$staffName] ?? ($fav['staff_image'] ?? '../images/42.png');
                    ?>
                        <div class="staff-favourite-card" data-staff-email="<?= $staffEmail ?>">
                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= $staffName ?>" class="staff-favourite-image" onerror="this.src='../images/42.png'">
                            <h3 class="staff-favourite-name"><?= $staffName ?></h3>
                            
                            <div class="staff-favourite-services">
                                <p class="staff-favourite-services-label">Primary Services:</p>
                                <p class="staff-favourite-services-value"><?= $primaryServices ?></p>
                            </div>
                            
                            <p class="staff-favourite-bio"><?= $categoryName ?></p>
                            
                            <div class="staff-favourite-actions">
                                <button class="btn-book-now" onclick="window.location.href='../booking.php'">
                                    <i class="fas fa-calendar"></i>
                                    Book Now
                                </button>
                                <button class="btn-remove-favourite" onclick="removeFavorite('<?= $staffEmail ?>', this)">
                                    <i class="fas fa-heart-broken"></i>
                                    Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ========== SECTION 5: HELP & SUPPORT ========== -->
        <div id="section-help" class="dash-section">
            <h1 class="favourites-title" style="margin-bottom: 30px;">Help & Support</h1>
            
            <div class="help-container">
                <!-- Contact Form Section -->
                <div class="help-card">
                    <h2 class="help-card-title">
                        <i class="fas fa-envelope" style="color: #c29076; margin-right: 10px;"></i>
                        Contact Us
                    </h2>
                    <p class="help-card-desc">Have a question or need assistance? Send us a message and we'll get back to you soon.</p>
                    
                    <form class="help-contact-form" id="helpContactForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="help-name">Name</label>
                                <input type="text" id="help-name" name="name" class="form-control-custom" placeholder="John Carter" required>
                            </div>
                            <div class="form-group">
                                <label for="help-email">Email</label>
                                <input type="email" id="help-email" name="email" class="form-control-custom" placeholder="example@youremail.com" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="help-phone">Phone</label>
                            <input type="tel" id="help-phone" name="phone" class="form-control-custom" placeholder="(123) 346 - 2386">
                        </div>
                        
                        <div class="form-group">
                            <label for="help-subject">Subject</label>
                            <input type="text" id="help-subject" name="subject" class="form-control-custom" placeholder="ex. Manicure" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="help-message">Message</label>
                            <textarea id="help-message" name="message" class="form-control-custom" rows="6" placeholder="Please type your message here..." required></textarea>
                        </div>
                        
                        <button type="submit" class="help-submit-btn">
                            <i class="fas fa-paper-plane"></i>
                            Send Message
                        </button>
                    </form>
                </div>
                
                <!-- FAQ Section -->
                <div class="help-card">
                    <h2 class="help-card-title">
                        <i class="fas fa-question-circle" style="color: #c29076; margin-right: 10px;"></i>
                        Frequently Asked Questions
                    </h2>
                    <p class="help-card-desc">Find answers to common questions about our booking system and services.</p>
                    
                    <div class="faq-list">
                        <div class="faq-item active">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>How do I cancel or reschedule my appointment?</span>
                                <i class="fas fa-minus"></i>
                            </div>
                            <div class="faq-answer active">
                                <p>To make changes to your booking, you need to cancel your current appointment and create a new one. Go to 'My Bookings', click 'Cancel' on the appointment, then book a new slot.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>What is the cancellation policy?</span>
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>You can cancel your appointment up to 24 hours before the scheduled time without any penalty. Cancellations made less than 24 hours in advance may be subject to a cancellation fee.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>What payment methods do you accept?</span>
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>We accept cash and card payments at the salon. Payment is required when you arrive for your appointment.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>Can I book multiple services at once?</span>
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Yes, you can book multiple services in a single appointment. Simply select all the services you'd like during the booking process, and we'll schedule them accordingly.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>How will I receive appointment reminders?</span>
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>You will receive appointment reminders via email and SMS 24 hours before your scheduled appointment time.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>Can I request a specific staff member?</span>
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Yes, you can select your preferred staff member during the booking process. You can also add staff members to your favorites for easier booking in the future.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Details Modal -->
        <div class="modal fade" id="bookingModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Booking Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="bookingDetailsContent">
                        <!-- Content will be loaded by JavaScript -->
                    </div>
                </div>
            </div>
        </div>


    </main>
</div>

<script>
function switchSection(section, link) {
    // Hide all sections
    document.querySelectorAll('.dash-section').forEach(s => s.classList.remove('active'));

    // Show target section
    const targetSection = document.getElementById('section-' + section);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // Remove active from all links
    document.querySelectorAll('.dash-nav a').forEach(a => a.classList.remove('active'));

    // Activate clicked link if provided
    if (link) {
        link.classList.add('active');
    } else {
        // Find and activate the corresponding nav link
        const navLinks = document.querySelectorAll('.dash-nav a');
        navLinks.forEach(a => {
            if (a.getAttribute('onclick') && a.getAttribute('onclick').includes("'" + section + "'")) {
                a.classList.add('active');
            }
        });
    }
}

// Profile Edit Functionality
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section');
    
    if (section) {
        // Small delay to ensure DOM is ready
        setTimeout(function() {
            switchSection(section, null);
        }, 100);
    } else {
        // Default to overview section
        const overviewSection = document.getElementById('section-overview');
        if (overviewSection) {
            overviewSection.classList.add('active');
        }
    }
    
    // Profile Edit
    const editBtn = document.getElementById('edit-profile-btn');
    const saveBtn = document.getElementById('save-profile-btn');
    const cancelBtn = document.getElementById('cancel-profile-btn');
    const firstNameInput = document.getElementById('profile-firstname');
    const lastNameInput = document.getElementById('profile-lastname');
    const emailInput = document.getElementById('profile-email');
    const profileMessage = document.getElementById('profile-message');
    
    let originalValues = {};
    
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            originalValues = {
                firstname: firstNameInput.value,
                lastname: lastNameInput.value,
                email: emailInput.value
            };
            
            firstNameInput.removeAttribute('readonly');
            lastNameInput.removeAttribute('readonly');
            emailInput.removeAttribute('readonly');
            
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-block';
            cancelBtn.style.display = 'inline-block';
            profileMessage.innerHTML = '';
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            firstNameInput.value = originalValues.firstname;
            lastNameInput.value = originalValues.lastname;
            emailInput.value = originalValues.email;
            
            firstNameInput.setAttribute('readonly', 'readonly');
            lastNameInput.setAttribute('readonly', 'readonly');
            emailInput.setAttribute('readonly', 'readonly');
            
            editBtn.style.display = 'inline-block';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
            profileMessage.innerHTML = '';
        });
    }
    
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            const data = {
                firstName: firstNameInput.value.trim(),
                lastName: lastNameInput.value.trim(),
                email: emailInput.value.trim()
            };
            
            if (!data.firstName || !data.lastName) {
                profileMessage.innerHTML = '<p style="color: red;">First name and last name are required</p>';
                return;
            }
            
            fetch('../update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    profileMessage.innerHTML = '<p style="color: green;">Profile updated successfully!</p>';
                    firstNameInput.setAttribute('readonly', 'readonly');
                    lastNameInput.setAttribute('readonly', 'readonly');
                    emailInput.setAttribute('readonly', 'readonly');
                    editBtn.style.display = 'inline-block';
                    saveBtn.style.display = 'none';
                    cancelBtn.style.display = 'none';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    profileMessage.innerHTML = '<p style="color: red;">' + data.message + '</p>';
                }
            })
            .catch(err => {
                profileMessage.innerHTML = '<p style="color: red;">Error updating profile</p>';
            });
        });
    }
    
    // Password Update
    const updatePasswordBtn = document.getElementById('update-password-btn');
    const passwordMessage = document.getElementById('password-message');
    
    if (updatePasswordBtn) {
        updatePasswordBtn.addEventListener('click', function() {
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                passwordMessage.innerHTML = '<p style="color: red;">All fields are required</p>';
                return;
            }
            
            if (newPassword !== confirmPassword) {
                passwordMessage.innerHTML = '<p style="color: red;">New passwords do not match</p>';
                return;
            }
            
            if (newPassword.length < 6) {
                passwordMessage.innerHTML = '<p style="color: red;">Password must be at least 6 characters</p>';
                return;
            }
            
            fetch('../update_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    currentPassword: currentPassword,
                    newPassword: newPassword,
                    confirmPassword: confirmPassword
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    passwordMessage.innerHTML = '<p style="color: green;">Password updated successfully!</p>';
                    document.getElementById('password-form').reset();
                    setTimeout(() => passwordMessage.innerHTML = '', 3000);
                } else {
                    passwordMessage.innerHTML = '<p style="color: red;">' + data.message + '</p>';
                }
            })
            .catch(err => {
                passwordMessage.innerHTML = '<p style="color: red;">Error updating password</p>';
            });
        });
    }
});

// View Booking Details
function viewBookingDetails(bookingId) {
    // Show modal first
    const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
    modal.show();
    
    // Load booking details
    fetch('../get_booking_details.php?booking_id=' + encodeURIComponent(bookingId))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('bookingDetailsContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading booking details:', error);
            document.getElementById('bookingDetailsContent').innerHTML = '<p class="text-danger">Failed to load booking details. Please try again.</p>';
        });
}

// Cancel Booking
function cancelBooking(bookingId) {
    if (!confirm('Do you want to cancel this booking?')) {
        return;
    }
    
    fetch('../cancel_booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_id: bookingId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Booking cancelled successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to cancel booking'));
        }
    })
    .catch(err => {
        alert('Error cancelling booking');
    });
}

// Remove Favorite Staff
function removeFavorite(staffEmail, buttonElement) {
    if (!confirm('Do you want to remove this staff member from your favourites?')) {
        return;
    }
    
    fetch('../remove_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ staff_email: staffEmail })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Remove the card from the DOM
            const card = buttonElement.closest('.staff-favourite-card');
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    // Check if no more favorites, reload to show empty state
                    const remainingCards = document.querySelectorAll('.staff-favourite-card');
                    if (remainingCards.length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to remove favorite'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error removing favorite');
    });
}

// FAQ Toggle Function
function toggleFaq(element) {
    const faqItem = element.closest('.faq-item');
    const isActive = faqItem.classList.contains('active');
    
    // Close all FAQ items
    document.querySelectorAll('.faq-item').forEach(item => {
        item.classList.remove('active');
        const answer = item.querySelector('.faq-answer');
        if (answer) {
            answer.classList.remove('active');
        }
    });
    
    // Toggle current item if it wasn't active
    if (!isActive) {
        faqItem.classList.add('active');
        const answer = faqItem.querySelector('.faq-answer');
        if (answer) {
            answer.classList.add('active');
        }
    }
}

// Help Contact Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const helpForm = document.getElementById('helpContactForm');
    if (helpForm) {
        helpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = {
                name: document.getElementById('help-name').value,
                email: document.getElementById('help-email').value,
                phone: document.getElementById('help-phone').value,
                subject: document.getElementById('help-subject').value,
                message: document.getElementById('help-message').value
            };
            
            // Here you would typically send the data to a server
            // For now, we'll just show a success message
            alert('Thank you for your message! We will get back to you soon.');
            
            // Reset form
            helpForm.reset();
        });
    }
    
    // Initialize first FAQ item as active
    const firstFaqItem = document.querySelector('.faq-item');
    if (firstFaqItem) {
        firstFaqItem.classList.add('active');
        const firstAnswer = firstFaqItem.querySelector('.faq-answer');
        if (firstAnswer) {
            firstAnswer.classList.add('active');
        }
    }
});

</script>

</body>
</html>
