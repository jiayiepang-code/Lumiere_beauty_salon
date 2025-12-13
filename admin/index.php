<?php
/**
 * Admin Dashboard
 * File: admin/index.php
 */

// Include authentication check
require_once 'includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Include database connection
require_once '../config/config.php';

// Get current admin data
$admin = getCurrentAdmin();

// Set page title
$page_title = 'Dashboard';
$base_path = '..';

// Fetch KPI data
$kpi_data = [
    'total_bookings' => 0,
    'active_staff' => 0,
    'todays_income' => 0.00,
    'pending_requests' => 0
];

try {
    $conn = getDBConnection();
    $today = date('Y-m-d');
    
    // 1. Total Bookings (All Time)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Booking");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['total_bookings'] = $row['count'];
    }
    $stmt->close();
    
    // 2. Active Staff
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Staff WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['active_staff'] = $row['count'];
    }
    $stmt->close();
    
    // 3. Today's Income
    $stmt = $conn->prepare("SELECT SUM(total_price) as total FROM Booking WHERE booking_date = ? AND status IN ('confirmed', 'completed')");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['todays_income'] = $row['total'] ?? 0.00;
    }
    $stmt->close();
    
    // 4. Pending Requests (Upcoming Confirmed Bookings)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Booking WHERE booking_date >= ? AND status = 'confirmed'");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['pending_requests'] = $row['count'];
    }
    $stmt->close();
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Dashboard KPI Error: " . $e->getMessage());
}

// Include header
include 'includes/header.php';
?>

<div class="dashboard-header">
    <h1 class="dashboard-title">Dashboard Overview</h1>
    <p class="dashboard-subtitle">Welcome back, <?php echo htmlspecialchars($admin['first_name']); ?>! Here's what's happening today.</p>
</div>

<div class="stats-grid">
    <!-- Total Bookings -->
    <div class="stat-card">
        <div class="stat-info">
            <h3>Total Bookings</h3>
            <div class="stat-value"><?php echo number_format($kpi_data['total_bookings']); ?></div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
        </div>
    </div>

    <!-- Active Staff -->
    <div class="stat-card">
        <div class="stat-info">
            <h3>Active Staff</h3>
            <div class="stat-value"><?php echo number_format($kpi_data['active_staff']); ?></div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
    </div>

    <!-- Today's Income -->
    <div class="stat-card">
        <div class="stat-info">
            <h3>Today's Income</h3>
            <div class="stat-value">RM <?php echo number_format($kpi_data['todays_income'], 2); ?></div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
    </div>

    <!-- Pending Requests -->
    <div class="stat-card">
        <div class="stat-info">
            <h3>Pending Requests</h3>
            <div class="stat-value"><?php echo number_format($kpi_data['pending_requests']); ?></div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
    </div>
</div>

<!-- Today's Appointments -->
<div class="dashboard-section">
    <h2 class="section-title">Today's Appointments</h2>
    <div class="appointments-list">
        <div class="appointment-item">
            <div class="appointment-avatar">AW</div>
            <div class="appointment-info">
                <h4>Amanda Wong</h4>
                <p>Signature Blowout</p>
            </div>
            <div class="appointment-time">
                <span class="time">10:00 - 10:45</span>
                <span class="staff-name">Sarah Chen</span>
            </div>
            <span class="status-badge confirmed">confirmed</span>
        </div>

        <div class="appointment-item">
            <div class="appointment-avatar">LT</div>
            <div class="appointment-info">
                <h4>Lisa Tan</h4>
                <p>Balayage Highlights, Signature Blowout</p>
            </div>
            <div class="appointment-time">
                <span class="time">11:00 - 14:45</span>
                <span class="staff-name">Sarah Chen</span>
            </div>
            <span class="status-badge confirmed">confirmed</span>
        </div>

        <div class="appointment-item">
            <div class="appointment-avatar">RL</div>
            <div class="appointment-info">
                <h4>Rachel Lim</h4>
                <p>Gel Manicure</p>
            </div>
            <div class="appointment-time">
                <span class="time">09:00 - 10:00</span>
                <span class="staff-name">Aisha Rahman</span>
            </div>
            <span class="status-badge completed">completed</span>
        </div>
    </div>
</div>

<!-- Two Column Section: Recent Activity & Top Services -->
<div class="dashboard-grid-2">
    <!-- Recent Activity -->
    <div class="dashboard-section">
        <h2 class="section-title">Recent Activity</h2>
        <div class="activity-list">
            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-content">
                    <h5>New booking</h5>
                    <p>Amanda Wong - Signature Blowout</p>
                </div>
                <span class="activity-time">10 mins ago</span>
            </div>

            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-content">
                    <h5>Completed</h5>
                    <p>Rachel Lim - Gel Manicure</p>
                </div>
                <span class="activity-time">1 hour ago</span>
            </div>

            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-content">
                    <h5>Cancellation</h5>
                    <p>Michelle Yap - Precision Haircut</p>
                </div>
                <span class="activity-time">2 hours ago</span>
            </div>
        </div>
    </div>

    <!-- Top Services Today -->
    <div class="dashboard-section">
        <h2 class="section-title">Top Services Today</h2>
        <div class="services-list">
            <div class="service-item">
                <h5>Signature Blowout</h5>
                <div class="service-bar">
                    <div class="bar-fill" style="width: 80%;"></div>
                </div>
                <span class="service-count">8</span>
            </div>

            <div class="service-item">
                <h5>Gel Manicure</h5>
                <div class="service-bar">
                    <div class="bar-fill" style="width: 60%;"></div>
                </div>
                <span class="service-count">6</span>
            </div>

            <div class="service-item">
                <h5>Balayage Highlights</h5>
                <div class="service-bar">
                    <div class="bar-fill" style="width: 40%;"></div>
                </div>
                <span class="service-count">4</span>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
