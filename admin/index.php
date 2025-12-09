<?php
// Include authentication check
require_once 'includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Include database connection
require_once '../php/connection.php';

// Set page title
$page_title = 'Dashboard';

// Fetch KPI data
$kpi_data = [
    'total_bookings_today' => 0,
    'total_services' => 0,
    'total_staff' => 0,
    'pending_bookings' => 0
];

try {
    // Get today's bookings
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Booking WHERE booking_date = ? AND status IN ('confirmed', 'completed')");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['total_bookings_today'] = $row['count'];
    }
    $stmt->close();
    
    // Get total active services
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Service WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['total_services'] = $row['count'];
    }
    $stmt->close();
    
    // Get total active staff
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Staff WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['total_staff'] = $row['count'];
    }
    $stmt->close();
    
    // Get pending bookings (confirmed status for future dates)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Booking WHERE booking_date >= ? AND status = 'confirmed'");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $kpi_data['pending_bookings'] = $row['count'];
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Dashboard KPI Error: " . $e->getMessage());
}

// Include header
include 'includes/header.php';
?>

<!-- Welcome Section -->
<div class="welcome-section">
    <h2>Welcome back, <?php echo htmlspecialchars($admin['first_name']); ?>! 👋</h2>
    <p>Here's what's happening with your salon today.</p>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-header">
            <div class="kpi-icon primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
        </div>
        <div class="kpi-value"><?php echo $kpi_data['total_bookings_today']; ?></div>
        <div class="kpi-label">Bookings Today</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <div class="kpi-icon success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 7h-9M14 17H5M17 12H3"></path>
                    <circle cx="17" cy="7" r="3"></circle>
                    <circle cx="7" cy="17" r="3"></circle>
                    <circle cx="20" cy="12" r="3"></circle>
                </svg>
            </div>
        </div>
        <div class="kpi-value"><?php echo $kpi_data['total_services']; ?></div>
        <div class="kpi-label">Active Services</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <div class="kpi-icon info">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
        </div>
        <div class="kpi-value"><?php echo $kpi_data['total_staff']; ?></div>
        <div class="kpi-label">Active Staff</div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-header">
            <div class="kpi-icon warning">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>
        <div class="kpi-value"><?php echo $kpi_data['pending_bookings']; ?></div>
        <div class="kpi-label">Upcoming Bookings</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <h3 style="margin-bottom: 20px; color: #333;">Quick Actions</h3>
    <div class="quick-actions">
        <a href="services/list.php" class="action-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 7h-9M14 17H5M17 12H3"></path>
                <circle cx="17" cy="7" r="3"></circle>
                <circle cx="7" cy="17" r="3"></circle>
                <circle cx="20" cy="12" r="3"></circle>
            </svg>
            <span>Manage Services</span>
        </a>
        
        <a href="staff/list.php" class="action-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Manage Staff</span>
        </a>
        
        <a href="calendar/master.php" class="action-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>View Calendar</span>
        </a>
        
        <a href="analytics/business.php" class="action-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="20" x2="12" y2="10"></line>
                <line x1="18" y1="20" x2="18" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="16"></line>
            </svg>
            <span>View Analytics</span>
        </a>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
