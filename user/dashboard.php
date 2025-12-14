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

// Fetch user's favourites to display on the dashboard
 $favorites = [];


// Get customer email
$customerEmail = $_SESSION['customer_email'] ?? null;
$customerPhone = $_SESSION['customer_phone'] ?? null;

// Retrieve email from database if not in session
if (!$customerEmail && $customerPhone) {
    $emailQuery = "SELECT customer_email, first_name, last_name FROM customer WHERE phone = ? LIMIT 1";
    $emailStmt = $db->prepare($emailQuery);
    $emailStmt->execute([$customerPhone]);
    $customerRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
    if ($customerRow) {
        $customerEmail = $customerRow['customer_email'];
        $_SESSION['customer_email'] = $customerEmail;
        $_SESSION['customer_first_name'] = $customerRow['first_name'];
        $_SESSION['customer_last_name'] = $customerRow['last_name'];
    }
}

if (!$customerEmail) {
    header('Location: ../login.php');
    exit();
}

// Get customer details
$customerQuery = "SELECT * FROM customer WHERE customer_email = ? LIMIT 1";
$customerStmt = $db->prepare($customerQuery);
$customerStmt->execute([$customerEmail]);
$customerInfo = $customerStmt->fetch(PDO::FETCH_ASSOC);

// Map staff names to their specific images (based on user's requirements)
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

// Map staff names to their specific primary services text (override database values)
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

// Load favourite staff for this customer (if table exists)
try {
    $favQuery = "SELECT f.staff_email, st.first_name, st.last_name, st.staff_image, st.bio,
        GROUP_CONCAT(DISTINCT sv.service_name ORDER BY sv.service_name SEPARATOR ' & ') as primary_services
        FROM customer_favourites f
        JOIN staff st ON f.staff_email = st.staff_email
        LEFT JOIN staff_service ss ON st.staff_email = ss.staff_email AND ss.is_active = 1
        LEFT JOIN service sv ON ss.service_id = sv.service_id AND sv.is_active = 1
        WHERE f.customer_email = ?
        GROUP BY f.staff_email";
    $favStmt = $db->prepare($favQuery);
    $favStmt->execute([$customerEmail]);
    $favorites = $favStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $favorites = [];
}

// Get all bookings for this customer (matches your booking table structure)
$query = "SELECT 
    b.booking_id,
    b.customer_email,
    b.booking_date,
    b.start_time,
    b.expected_finish_time,
    b.status,
    b.remarks,
    b.promo_code,
    b.discount_amount,
    b.total_price,
    b.created_at,
    b.updated_at,
    COUNT(bs.booking_service_id) as service_count
FROM booking b
LEFT JOIN booking_service bs ON b.booking_id = bs.booking_id
WHERE b.customer_email = ?
GROUP BY b.booking_id
ORDER BY b.booking_date DESC, b.start_time DESC";

$stmt = $db->prepare($query);
$stmt->execute([$customerEmail]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get services for each booking (matches your booking_service table structure)
$bookingServices = [];
foreach($bookings as $booking) {
    $servicesQuery = "SELECT 
        bs.booking_service_id,
        bs.booking_id,
        bs.service_id,
        bs.staff_email,
        bs.quoted_price,
        bs.quoted_duration_minutes,
        s.service_name,
        s.service_category,
        s.description,
        st.first_name as staff_first_name,
        st.last_name as staff_last_name
    FROM booking_service bs
    JOIN service s ON bs.service_id = s.service_id
    LEFT JOIN staff st ON bs.staff_email = st.staff_email
    WHERE bs.booking_id = ?";
    
    $servicesStmt = $db->prepare($servicesQuery);
    $servicesStmt->execute([$booking['booking_id']]);
    $bookingServices[$booking['booking_id']] = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Separate upcoming and past bookings
$today = date('Y-m-d');
$upcomingBookings = [];
$pastBookings = [];

foreach($bookings as $booking) {
    // Upcoming: only confirmed bookings with future dates
    if ($booking['booking_date'] >= $today && strtolower($booking['status']) === 'confirmed') {
        $upcomingBookings[] = $booking;
    } else {
        // History: all other bookings (past dates, cancelled, completed, etc.)
        // This ensures confirmed bookings only appear in upcoming section, not in history
        $pastBookings[] = $booking;
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        <!-- bookings moved into main to avoid layout constraints in sidebar -->

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
                            <p class="stat-label">Next Visit In</p>
                            <h3 class="stat-value">
                                <?php if ($daysUntilNext !== null): ?>
                                    <?php if ($daysUntilNext == 0): ?>
                                        Today
                                    <?php elseif ($daysUntilNext == 1): ?>
                                        1 Day
                                    <?php else: ?>
                                        <?= $daysUntilNext ?> Days
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
                            <p class="stat-label">Favourite Staff</p>
                            <h3 class="stat-value" style="font-size: 1.3rem;"><?= htmlspecialchars($favoriteStaff) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointment Banner (moved to Overview) -->
            <?php if ($upcomingAppointment): 
                $upcomingDate = new DateTime($upcomingAppointment['booking_date']);
                $upcomingTime = new DateTime($upcomingAppointment['start_time']);
                
                // Get the first service and staff from booking services
                $firstService = 'Service';
                $firstStaffName = 'Staff Member';
                $firstStaffFirstName = '';
                $firstStaffLastName = '';
                
                if (isset($bookingServices[$upcomingAppointment['booking_id']]) && !empty($bookingServices[$upcomingAppointment['booking_id']])) {
                    $firstServiceData = $bookingServices[$upcomingAppointment['booking_id']][0];
                    $firstService = $firstServiceData['service_name'] ?? 'Service';
                    
                    if (!empty($firstServiceData['staff_first_name']) || !empty($firstServiceData['staff_last_name'])) {
                        $firstStaffFirstName = $firstServiceData['staff_first_name'] ?? '';
                        $firstStaffLastName = $firstServiceData['staff_last_name'] ?? '';
                        $firstStaffName = trim($firstStaffFirstName . ' ' . $firstStaffLastName);
                    } else {
                        $firstStaffName = 'No Preference';
                    }
                }
            ?>
            <div class="upcoming-appointment-banner">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 style="color: white; font-family: 'Playfair Display', serif; margin-bottom: 5px;">
                            <i class="fas fa-calendar-check me-2"></i>Next Booking
                        </h2>
                        <p style="color: rgba(255,255,255,0.9); margin: 0;">Your next visit is coming up soon!</p>
                    </div>
                </div>

                <div class="upcoming-inner">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <p class="upcoming-label">Service</p>
                            <h4 class="upcoming-value"><?= htmlspecialchars($firstService) ?></h4>
                        </div>
                        <div class="col-md-3">
                            <p class="upcoming-label">Staff Member</p>
                            <h4 class="upcoming-value"><i class="fas fa-user-circle me-2"></i><?= htmlspecialchars($firstStaffName) ?></h4>
                        </div>
                        <div class="col-md-3">
                            <p class="upcoming-label">Date</p>
                            <h4 class="upcoming-value"><?= $upcomingDate->format('M d, Y') ?></h4>
                        </div>
                        <div class="col-md-3">
                            <p class="upcoming-label">Time</p>
                            <h4 class="upcoming-value"><?= $upcomingTime->format('g:i A') ?></h4>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button class="btn btn-upcoming btn-light" style="flex: 1;" onclick="viewDetails('<?= htmlspecialchars($upcomingAppointment['booking_id'], ENT_QUOTES) ?>')">
                            <i class="fas fa-eye me-2"></i>View Details
                        </button>
                        <button class="btn btn-upcoming btn-outline-light" onclick="cancelBooking('<?= htmlspecialchars($upcomingAppointment['booking_id'], ENT_QUOTES) ?>')" style="flex: 1;">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                    </div>
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
                            <h3>Book New Booking</h3>
                            <p>Schedule your next beauty session</p>
                        </div>
                        <i class="fas fa-arrow-right action-arrow"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="action-card" onclick="switchSection('bookings', null)">
                        <div class="action-icon" style="background: var(--primary-brown-light);">
                            <i class="fas fa-history" style="color: var(--primary-brown);"></i>
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
                <h2 style="font-family: 'Playfair Display', serif; color: var(--dark-brown); margin-bottom: 20px;">Recent Appointments</h2>
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
                        <a href="../booking.php" class="book-again">Book Again</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- ========== SECTION: ALL BOOKINGS (separate page) ========== -->
        <div id="section-bookings" class="dash-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="bookings-page-title mb-0">Your Bookings</h1>
                <?php if (!empty($upcomingBookings)): ?>
                    <a href="../booking.php" class="btn btn-primary new-booking-btn" style="background: linear-gradient(to bottom, #D7BB91, #B59267); border: 1px solid #8A6E4D; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                        <i class="fas fa-plus me-2"></i>New Booking
                    </a>
                <?php endif; ?>
            </div>

            <div class="dashboard-header rounded-3 mb-3">
                <h3 class="section-title mb-0 ps-4">
                    <i class="fas fa-calendar-alt me-2"></i>Upcoming Bookings
                    <span class="badge bg-primary ms-2"><?php echo count($upcomingBookings); ?></span>
                </h3>
                <?php if (count($upcomingBookings) > 3): ?>
                    <a href="#" id="toggle-view-all-link" class="view-all-link" style="margin-left:12px;">View all</a>
                <?php endif; ?>
            </div>

            <?php if(empty($upcomingBookings)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
                    <h3>No bookings yet</h3>
                    <p class="text-muted mb-4">You haven't made any bookings yet. Start by booking your first appointment!</p>
                    <a href="../booking.php" class="btn empty-state-btn">
                        <i class="fas fa-plus me-2"></i>Book Appointment
                    </a>
                </div>
            <?php else: ?>
                <div class="bookings-grid">
                    <?php foreach($upcomingBookings as $booking): ?>
                        <div class="booking-item">
                            <div class="card booking-card h-100">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <span class="fw-bold" style="color: #c29076;"><?php echo htmlspecialchars($booking['booking_id']); ?></span>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2"><i class="fas fa-calendar me-2"></i>Date & Time</h6>
                                        <p class="mb-0 fw-semibold"><?php echo date('l, d M Y', strtotime($booking['booking_date'])); ?></p>
                                        <p class="mb-0 text-muted small"><?php echo date('h:i A', strtotime($booking['start_time'])); ?> - <?php echo date('h:i A', strtotime($booking['expected_finish_time'])); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2"><i class="fas fa-scissors me-2"></i>Services <span class="badge bg-secondary ms-1"><?php echo $booking['service_count']; ?></span></h6>
                                        <div class="service-list">
                                            <?php 
                                            $services = $bookingServices[$booking['booking_id']];
                                            $totalServices = count($services);
                                            $displayServices = array_slice($services, 0, 2);
                                            foreach($displayServices as $service): ?>
                                                <div class="service-item">
                                                    <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                                    <div class="small text-muted"><i class="fas fa-clock"></i> <?php echo $service['quoted_duration_minutes']; ?> min <?php if($service['staff_first_name']): ?> | <i class="fas fa-user"></i> <?php echo htmlspecialchars($service['staff_first_name'] . ' ' . $service['staff_last_name']); ?><?php endif; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if($totalServices > 2): ?>
                                                <div class="mt-2">
                                                    <a href="#" class="text-decoration-none fw-semibold" style="color: #c29076;" onclick="viewDetails('<?php echo htmlspecialchars($booking['booking_id'], ENT_QUOTES); ?>'); return false;">
                                                        <i class="fas fa-chevron-down me-1"></i>View More (<?php echo ($totalServices - 2); ?> more)
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <span class="text-muted">Total:</span>
                                        <strong class="fs-5" style="color: #c29076;">RM <?php echo number_format($booking['total_price'], 2); ?></strong>
                                    </div>
                                </div>
                                <div class="card-footer bg-white d-flex gap-2" style="padding: 1rem;">
                                    <button class="btn btn-sm btn-outline-primary booking-action-btn" style="flex: 1 1 0; min-width: 0; padding: 0.5rem 1rem; border: 1px solid #0d6efd; color: #0d6efd;" onclick="viewDetails('<?php echo htmlspecialchars($booking['booking_id'], ENT_QUOTES); ?>')"><i class="fas fa-eye"></i> View Details</button>
                                    <button class="btn btn-sm btn-outline-danger booking-action-btn" style="flex: 1 1 0; min-width: 0; padding: 0.5rem 1rem; border: 1px solid #dc3545; color: #dc3545;" onclick="cancelBooking('<?php echo htmlspecialchars($booking['booking_id'], ENT_QUOTES); ?>')"><i class="fas fa-times"></i> Cancel</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($pastBookings)): ?>
            <div class="mt-5">
            <div class="booking-history-header">
              <h3 class="section-title">
                <i class="fas fa-history me-2"></i>Bookings History
                <span class="badge bg-secondary ms-2"><?php echo count($pastBookings); ?></span>
              </h3>
              <?php if (count($pastBookings) > 3): ?>
                  <a href="#" id="toggle-history-view-all-link" class="view-all-link" style="margin-left:12px;">View all</a>
              <?php endif; ?>
            </div>
                <div class="bookings-grid booking-history-grid mt-3">
                    <?php foreach($pastBookings as $booking): ?>
                        <div class="booking-history-item">
                            <div class="card booking-card h-100" style="opacity: 0.95;">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><?php echo htmlspecialchars($booking['booking_id']); ?></span>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3"><h6 class="text-muted mb-2"><i class="fas fa-calendar me-2"></i>Date & Time</h6><p class="mb-0"><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></p><p class="mb-0 small text-muted"><?php echo date('h:i A', strtotime($booking['start_time'])); ?></p></div>
                                    <div class="mb-3"><h6 class="text-muted mb-2"><i class="fas fa-scissors me-2"></i>Services</h6><ul class="list-unstyled small mb-0"><?php foreach($bookingServices[$booking['booking_id']] as $service): ?><li>• <?php echo htmlspecialchars($service['service_name']); ?></li><?php endforeach; ?></ul></div>
                                    <div class="border-top pt-2"><strong>RM <?php echo number_format($booking['total_price'], 2); ?></strong></div>
                                </div>
                                <div class="card-footer bg-white">
                                    <button class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="viewDetails('<?php echo htmlspecialchars($booking['booking_id'], ENT_QUOTES); ?>')"><i class="fas fa-eye"></i> View Details</button>
                                    <?php if(strtolower($booking['status']) === 'completed'): ?>
                                        <button class="btn btn-sm btn-outline-success w-100" onclick="openCommentModal('<?php echo htmlspecialchars($booking['booking_id'], ENT_QUOTES); ?>')"><i class="fas fa-comment"></i> Add Comment</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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

        <!-- ========== SECTION 4: FAVOURITES STAFF ========== -->
<div id="section-favourites" class="dash-section">

    <h1 class="favourites-title">My Favourite Staff</h1>

    <?php if (empty($favorites)): ?>
        <div class="empty-state" style="background: white; border-radius: 20px;">
            <div class="empty-state-icon">
                <i class="fas fa-users"></i>
            </div>
            <h3>No Favourite Staff Yet</h3>
            <p>Start adding your favourite staff members from the team page!</p>
            <a href="team.php" class="empty-state-btn">
                <i class="fas fa-users me-2"></i>Browse Staff
            </a>
        </div>
    <?php else: ?>

        <div class="favourites-grid">
            <?php foreach ($favorites as $staff): ?>
                <?php
                    $staffEmail = $staff['staff_email'] ?? '';
                    $firstName = trim($staff['first_name'] ?? '');
                    $lastName = trim($staff['last_name'] ?? '');
                    
                    // Debug: Log staff info
                    error_log("Dashboard Favourites - Processing: $firstName $lastName ($staffEmail)");
                    
                    // Get image - priority: mapped by first name > database > default
                    if (!empty($firstName) && isset($staffImageMap[$firstName])) {
                        $img = $staffImageMap[$firstName];
                        error_log("Dashboard - Using mapped image for $firstName: $img");
                    } elseif (!empty($staff['staff_image'])) {
                        $img = $staff['staff_image'];
                        // Ensure the path is correct (if it doesn't start with ../, add it)
                        if (strpos($img, '../') !== 0 && strpos($img, 'http') !== 0) {
                            $img = '../' . ltrim($img, '/');
                        }
                        error_log("Dashboard - Using database image for $firstName: $img");
                    } else {
                        $img = '../images/42.png';
                        error_log("Dashboard - Using default image for $firstName");
                    }

                    // Get services - priority: mapped by first name > limited database services
                    if (!empty($firstName) && isset($staffPrimaryServicesMap[$firstName])) {
                        $services = $staffPrimaryServicesMap[$firstName];
                    } else {
                        // Limit to first 2 services from database
                        $dbServices = $staff['primary_services'] ?? 'Various Services';
                        $serviceArray = explode(' & ', $dbServices);
                        $services = implode(' & ', array_slice($serviceArray, 0, 2));
                    }

                    // Get bio
                    $bio = $staff['bio'] ?? '';
                    $bio = trim($bio) !== '' ? $bio : 'Professional staff member';
                    
                    // Display full name
                    $displayName = $firstName;
                    if (!empty($lastName)) {
                        $displayName = $firstName . ' ' . $lastName;
                    }
                    
                    error_log("Dashboard - Final image path: $img for $displayName");
                ?>

                <div class="staff-favourite-card" data-staff-email="<?= htmlspecialchars($staffEmail) ?>">

                    <img
                        src="<?= htmlspecialchars($img) ?>"
                        alt="<?= htmlspecialchars($displayName) ?>"
                        class="staff-favourite-image"
                        onerror="this.onerror=null; this.src='../images/42.png'; console.error('Image failed to load: <?= htmlspecialchars($img) ?>');"
                    >

                    <h3 class="staff-favourite-name"><?= htmlspecialchars($displayName) ?></h3>

                    <div class="staff-favourite-services">
                        <p class="staff-favourite-services-label">Primary Services:</p>
                        <p class="staff-favourite-services-value"><?= htmlspecialchars($services) ?></p>
                    </div>

                    <p class="staff-favourite-bio"><?= htmlspecialchars($bio) ?></p>

                    <div class="staff-favourite-actions">
                        <a class="btn-fav-book" href="../booking.php?staff=<?= urlencode($staffEmail) ?>">
    <i class="fas fa-calendar"></i> Book Now
</a>

<button class="btn-fav-remove" onclick="removeFavorite('<?= htmlspecialchars($staffEmail, ENT_QUOTES) ?>', this)">
    <i class="fas fa-heart-broken"></i> Remove
</button>

                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>
        <!-- ========== SECTION 5: HELP & SUPPORT ========== -->
        <!-- FAQ Section -->
        <div id="section-help" class="dash-section">

            <h1 class="favourites-title">Help & Support</h1>
            <div class="faq-card">

    <div class="faq-header">
        <div class="faq-icon">?</div>
        <div>
            <h2>Frequently Asked Questions</h2>
            <p>Find answers to common questions about our booking system and services.</p>
        </div>
    </div>

    <div class="faq-list">

        <div class="faq-item">
            <button class="faq-question">
                How do I cancel or reschedule my appointment?
                <span class="faq-toggle">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    To make changes to your booking, you need to cancel your current appointment and create a new one.
                    Go to <strong>My Bookings</strong>, click <strong>Cancel</strong> on the appointment, then book a new slot.
                </p>
            </div>
        </div>

        <div class="faq-item">
            <button class="faq-question">
                What is the cancellation policy?
                <span class="faq-toggle">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    Appointments can be cancelled up to 24 hours before the scheduled time without penalty.
                </p>
            </div>
        </div>

    <div class="faq-item">
            <button class="faq-question">
                What payment methods do you accept?
                <span class="faq-toggle">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    We accept cash and card payments at the salon. Payment is required when you arrive for your appointment.
                </p>
            </div>
        </div>

    <div class="faq-item">
            <button class="faq-question">
                Can I book multiple services at once?
                <span class="faq-toggle">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    Yes! You can select multiple services when making a booking. The system will calculate the total duration and price for you.
                </p>
            </div>
        </div>

    <div class="faq-item">
            <button class="faq-question">
                How will I receive appointment reminders?
                <span class="faq-toggle">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    If you've enabled email notifications in your profile settings, you'll receive booking confirmations and reminders via email.
            </div>
        </div>

    <div class="faq-item">
            <button class="faq-question">
                Can I request a specific staff member?
                <span class="faq-toggle">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    Absolutely! When booking, you can choose your preferred staff member. You can also save favourite staff in the 'Favourite Staff' section for quick access
            </div>
        </div>

    </main>
</div>

<!-- Booking Details Modal - Outside main to ensure it's always accessible -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingModalLabel">Booking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="bookingDetailsContent">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading booking details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Comment Modal for Completed Bookings -->
<div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commentModalLabel">Add Comment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="commentForm">
                    <input type="hidden" id="commentBookingId" name="booking_id">
                    <div class="mb-3">
                        <label for="commentText" class="form-label">Your Comment / Review</label>
                        <textarea class="form-control" id="commentText" name="comment" rows="5" placeholder="Share your experience with this booking..." required></textarea>
                        <small class="form-text text-muted">Your feedback helps us improve our services.</small>
                    </div>
                    <div id="commentMessage" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitComment()">Submit Comment</button>
            </div>
        </div>
    </div>
</div>

<script src="dashboard.js"></script>

</body>
</html>