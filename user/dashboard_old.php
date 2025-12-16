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

$query = "SELECT first_name, last_name, phone, customer_email as email FROM customer WHERE phone = ?";
$stmt = $db->prepare($query);
$stmt->execute([$phone]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer) {
    $first = $customer['first_name'] ?? "Guest";
    $last = $customer['last_name'] ?? "";
    $email = $customer['email'] ?? "";
} else {
    $first = $_SESSION['first_name'] ?? "Guest";
    $last = $_SESSION['last_name'] ?? "";
    $email = $_SESSION['email'] ?? "";
}

$initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));

// Get customer_id from session or from phone
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    // Get customer_id from phone
    $customerQuery = "SELECT customer_id FROM customer WHERE phone = ? LIMIT 1";
    $customerStmt = $db->prepare($customerQuery);
    $customerStmt->execute([$phone]);
    $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
    $customerId = $customerData['customer_id'] ?? null;
    // Store in session for future use
    if ($customerId) {
        $_SESSION['customer_id'] = $customerId;
    }
}

// Fetch bookings using customer_email (booking table uses customer_email, not customer_id)
$bookingsQuery = "SELECT 
    b.booking_id,
    b.booking_date,
    b.start_time,
    b.expected_finish_time,
    b.status,
    b.total_price,
    GROUP_CONCAT(DISTINCT COALESCE(s.service_name, s.name, 'Service') SEPARATOR ', ') as services,
    GROUP_CONCAT(DISTINCT CONCAT(st.first_name, ' ', st.last_name) SEPARATOR ', ') as staff_names
FROM booking b
LEFT JOIN booking_service bs ON b.booking_id = bs.booking_id
LEFT JOIN service s ON bs.service_id = s.service_id
LEFT JOIN staff st ON bs.staff_email = st.staff_email
WHERE b.customer_email = ?
GROUP BY b.booking_id
ORDER BY b.booking_date DESC, b.start_time DESC
LIMIT 50";

try {
    // Always try to get email from phone to ensure we have the correct one
    $queryEmail = $email;
    if (!$queryEmail && isset($phone) && $phone) {
        $fallbackEmailQuery = "SELECT customer_email FROM customer WHERE phone = ? LIMIT 1";
        $fallbackEmailStmt = $db->prepare($fallbackEmailQuery);
        $fallbackEmailStmt->execute([$phone]);
        $fallbackEmailRow = $fallbackEmailStmt->fetch(PDO::FETCH_ASSOC);
        $queryEmail = $fallbackEmailRow['customer_email'] ?? null;
    }
    
    if ($queryEmail) {
        $bookingsStmt = $db->prepare($bookingsQuery);
        $bookingsStmt->execute([$queryEmail]);
        $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging
        error_log('Dashboard bookings query - Email: ' . $queryEmail . ', Found: ' . count($bookings) . ' bookings');
        if (count($bookings) > 0) {
            error_log('Sample booking IDs: ' . implode(', ', array_slice(array_column($bookings, 'booking_id'), 0, 3)));
        }
    } else {
        error_log('Dashboard - No email found for phone: ' . ($phone ?? 'NULL'));
        $bookings = [];
    }
} catch (Exception $e) {
    error_log('Bookings query failed: ' . $e->getMessage());
    error_log('Email used: ' . ($queryEmail ?? 'NULL') . ', Phone: ' . ($phone ?? 'NULL'));
    $bookings = [];
}

// Map staff names to their specific images (same as team.php)
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

// Map staff names to their specific primary services text
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
        .dash-section { display: none; }
        .dash-section.active { display: block; }
        .dash-nav a.active { background-color: var(--primary-brown); color: white; }
        
        /* =========================================
           BOOKING HISTORY CARDS
        ========================================= */
        
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--warm-brown);
        }
        
        .booking-card {
            background: linear-gradient(135deg, #ffffff, #FFF8F0);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .booking-id {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 0.95rem;
        }
        
        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status.confirmed {
            background-color: #28a745;
            color: #fff;
        }
        
        .status.cancelled {
            background-color: #dc3545;
            color: #fff;
        }
        
        .status.completed {
            background-color: #007bff;
            color: #fff;
        }
        
        .booking-body p {
            margin-bottom: 0.3rem;
            font-size: 0.95rem;
        }
        
        .booking-body .label {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .booking-body .count {
            color: var(--text-light);
            font-weight: normal;
        }
        
        .total {
            margin-top: 1.2rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total span {
            color: var(--accent-gold);
            font-size: 1.1rem;
        }
        
        .booking-footer {
            margin-top: auto;
            display: flex;
            gap: 10px;
        }
        
        .booking-footer .btn {
            flex: 1;
            border-radius: 12px;
            font-weight: 600;
        }
        
        /* =========================================
           FAVOURITE STAFF CARDS
        ========================================= */
        
        .staff-fav-card {
            background: linear-gradient(135deg, #ffffff, #FFF8F0);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .staff-fav-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .staff-fav-image {
            width: 100%;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .staff-fav-image img {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--accent-gold);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .staff-fav-body {
            padding: 1.5rem;
            flex-grow: 1;
        }
        
        .staff-fav-body h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--warm-brown);
            margin-bottom: 0.5rem;
        }
        
        .staff-fav-body .staff-services {
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
        }
        
        .staff-fav-body .staff-bio {
            font-size: 0.85rem;
            color: var(--text-light);
            line-height: 1.5;
        }
        
        .staff-fav-footer {
            padding: 1rem 1.5rem;
            display: flex;
            gap: 10px;
            border-top: 1px solid #e0e0e0;
        }
        
        .staff-fav-footer .btn {
            flex: 1;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
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
            <li><a href="#" onclick="switchSection('profile', this)" class="active">My Profile</a></li>
            <li><a href="#" onclick="switchSection('bookings', this)">My Bookings</a></li>
            <li><a href="#" onclick="switchSection('favourites', this)">Favourites Staff</a></li>
            <li><a href="#" onclick="switchSection('settings', this)">Settings</a></li>
            <li><a href="#" onclick="switchSection('about', this)">About Us</a></li>
        </ul>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="dash-main">

        <!-- ========== SECTION 1: PROFILE ========== -->
        <div id="section-profile" class="dash-section active">
            <h1>My Profile</h1>

            <div class="profile-card">
                <form id="profile-form">
                    <label>First Name</label>
                    <input type="text" id="profile-firstname" class="dash-input" value="<?= htmlspecialchars($first) ?>" readonly>

                    <label>Last Name</label>
                    <input type="text" id="profile-lastname" class="dash-input" value="<?= htmlspecialchars($last) ?>" readonly>

                    <label>Phone</label>
                    <input type="text" class="dash-input" value="<?= htmlspecialchars($phone) ?>" readonly disabled>

                    <label>Email</label>
                    <input type="email" id="profile-email" class="dash-input" value="<?= htmlspecialchars($email) ?>" readonly>

                    <button type="button" class="edit-btn" id="edit-profile-btn">Edit Details</button>
                    <button type="button" class="book-btn" id="save-profile-btn" style="display:none; margin-top:10px;">Save Changes</button>
                    <button type="button" class="cancel-btn" id="cancel-profile-btn" style="display:none; margin-top:10px; margin-left:10px;">Cancel</button>
                </form>
                <div id="profile-message" style="margin-top:15px;"></div>
            </div>
        </div>

        <!-- ========== SECTION 2: BOOKINGS ========== -->
        <div id="section-bookings" class="dash-section">
            <div class="container my-5">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">Your Bookings</h2>
                </div>

                <!-- Booking Cards -->
                <div class="row g-4">
                    <?php if (empty($bookings)): ?>
                        <div class="col-12">
                            <div class="booking-card text-center">
                                <p style="color: #8f8986; padding: 40px;">No bookings found. <a href="../booking.php">Book an appointment</a></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): 
                            $bookingDate = new DateTime($booking['booking_date']);
                            $startTime = new DateTime($booking['start_time']);
                            $endTime = null;
                            if (isset($booking['expected_finish_time'])) {
                                $endTime = new DateTime($booking['expected_finish_time']);
                            }
                            $services = $booking['services'] ?? 'Service';
                            $servicesArray = explode(', ', $services);
                            $serviceCount = count($servicesArray);
                            $status = $booking['status'] ?? 'confirmed';
                            $bookingId = $booking['booking_id'] ?? '';
                            $totalPrice = $booking['total_price'] ?? 0;
                            $canCancel = ($status === 'confirmed' && $bookingDate >= new DateTime('today'));
                            
                            // Format date and time
                            $dateFormatted = $bookingDate->format('d M Y');
                            $timeFormatted = $startTime->format('g:i A');
                            if ($endTime) {
                                $timeFormatted .= ' - ' . $endTime->format('g:i A');
                            }
                            
                            // Determine status class
                            $statusClass = 'confirmed';
                            if ($status === 'cancelled') {
                                $statusClass = 'cancelled';
                            } elseif ($status === 'completed') {
                                $statusClass = 'completed';
                            }
                        ?>
                            <div class="col-md-4">
                                <div class="booking-card">
                                    <div class="booking-header">
                                        <span class="booking-id"><?= htmlspecialchars($bookingId) ?></span>
                                        <span class="status <?= $statusClass ?>"><?= ucfirst($status) ?></span>
                                    </div>

                                    <div class="booking-body">
                                        <p class="label">Date & Time:</p>
                                        <p><?= $dateFormatted ?></p>
                                        <p><?= $timeFormatted ?></p>

                                        <p class="label mt-3">Services: <span class="count">(<?= $serviceCount ?>)</span></p>
                                        <p><?= htmlspecialchars($services) ?></p>

                                        <p class="total">
                                            Total:
                                            <span>RM <?= number_format($totalPrice, 2) ?></span>
                                        </p>
                                    </div>

                                    <div class="booking-footer">
                                        <button class="btn btn-outline-primary"
                                                onclick="viewBookingDetails('<?= htmlspecialchars($booking['booking_id'], ENT_QUOTES) ?>')"
                                                data-bs-toggle="modal"
                                                data-bs-target="#bookingModal">
                                            View Details
                                        </button>
                                        <?php if ($status === 'confirmed' && $canCancel): ?>
                                            <button class="btn btn-outline-danger"
                                                    onclick="cancelBooking('<?= htmlspecialchars($booking['booking_id'], ENT_QUOTES) ?>')">
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
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

        <!-- ========== SECTION 3: FAVOURITES ========== -->
        <div id="section-favourites" class="dash-section">
            <div class="container my-5">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">My Favourite Staff</h2>
                    <a href="team.php" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i>Browse Staff
                    </a>
                </div>

                <!-- Staff Cards -->
                <div class="row g-4" id="favorites-list">
                    <?php if (empty($favorites)): ?>
                        <div class="col-12">
                            <div class="profile-card text-center">
                                <p style="color: #8f8986; padding: 40px;">No favorite staff yet. <a href="team.php">Browse our team</a> and add them to favorites.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($favorites as $staff): 
                            $staffName = htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']);
                            $firstName = htmlspecialchars($staff['first_name']);
                            $bio = htmlspecialchars($staff['bio'] ?? 'Professional staff member');
                            
                            // Use mapped primary services if available, otherwise use from database
                            $primaryServices = $staffPrimaryServicesMap[$firstName] ?? htmlspecialchars($staff['primary_services'] ?? 'Various Services');
                            
                            // Use mapped image if available, otherwise use staff_image from DB, fallback to default
                            $image = $staffImageMap[$firstName] ?? ($staff['staff_image'] ?? '../images/42.png');
                            
                            $staffEmail = $staff['staff_email'];
                        ?>
                            <div class="col-md-4">
                                <div class="staff-fav-card">
                                    <div class="staff-fav-image">
                                        <img src="<?= htmlspecialchars($image) ?>" alt="<?= $staffName ?>" onerror="this.src='../images/42.png'">
                                    </div>
                                    <div class="staff-fav-body">
                                        <h3><?= $firstName ?></h3>
                                        <p class="staff-services"><strong>Primary Services:</strong><br><?= $primaryServices ?></p>
                                        <p class="staff-bio"><?= $bio ?></p>
                                    </div>
                                    <div class="staff-fav-footer">
                                        <button class="btn btn-primary" onclick="window.location.href='../booking.php'">
                                            <i class="fas fa-calendar-check me-2"></i>Book Now
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="removeFavoriteFromDashboard('<?= htmlspecialchars($staffEmail, ENT_QUOTES) ?>', this)">
                                            <i class="fas fa-heart-broken me-2"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ========== SECTION 4: SETTINGS ========== -->
        <div id="section-settings" class="dash-section">
            <h1>Account Settings</h1>

            <div class="profile-card">
                <h3>Security</h3>
                <form id="password-form">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" id="current-password" class="dash-input" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" id="new-password" class="dash-input" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" id="confirm-password" class="dash-input" required minlength="6">
                        </div>
                    </div>

                    <button type="button" class="book-btn" id="update-password-btn">Update Password</button>
                </form>
                <div id="password-message" style="margin-top:15px;"></div>
            </div>

            <div class="profile-card" style="margin-top:25px;">
                <h3>Notifications</h3>

                <div class="toggle-row">
                    <div>
                        <p class="toggle-title">Email Reminders</p>
                        <p class="toggle-desc">Receive booking confirmation emails.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-row">
                    <div>
                        <p class="toggle-title">Promotional Offers</p>
                        <p class="toggle-desc">Receive updates and promotions.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- ========== SECTION 5: ABOUT ========== -->
        <div id="section-about" class="dash-section">
            <h1>About Lumière</h1>

            <div class="profile-card about-card">
                <img src="../images/16.png" class="about-logo">

                <h2>Lumière Beauty Salon</h2>
                <p class="version-text">System Version 1.0.0</p>

                <hr class="divider">

                <div class="contact-info">
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>Ground Floor Block B, Phase 2, Jln Lintas, Kota Kinabalu</p>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <p>+60 12-345 6789</p>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <p>support@lumiere.com</p>
                    </div>
                </div>

                <div class="legal-links">
                    <a href="#">Privacy Policy</a> • 
                    <a href="#">Terms of Service</a>
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

// Remove Favorite from Dashboard
function removeFavoriteFromDashboard(staffEmail, button) {
    if (!confirm('Remove this staff member from your favourites?')) {
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
            const card = button.closest('.col-md-4');
            if (card) {
                card.remove();
            }
            
            // Check if there are any favorites left
            const favoritesContainer = document.getElementById('favorites-list');
            if (favoritesContainer && favoritesContainer.querySelectorAll('.col-md-4').length === 0) {
                favoritesContainer.innerHTML = '<div class="col-12"><div class="profile-card text-center"><p style="color: #8f8986; padding: 40px;">No favorite staff yet. <a href="team.php">Browse our team</a> and add them to favorites.</p></div></div>';
            }
            
            alert('Staff removed from favourites');
        } else {
            alert('Error: ' + (data.message || 'Failed to remove favourite'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error removing favourite');
    });
}
</script>

</body>
</html>
