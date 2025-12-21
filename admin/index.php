<?php
/**
 * Admin Dashboard
 * File: admin/index.php
 */

// Include authentication check
require_once 'includes/auth_check.php';

// #region agent log
error_log(json_encode(['location'=>'admin/index.php:11','message'=>'Dashboard accessed','data'=>['session_name'=>session_name(),'session_id'=>session_id(),'has_admin_session'=>isset($_SESSION['admin']),'admin_data'=>$_SESSION['admin']??null,'_SERVER_REQUEST_URI'=>$_SERVER['REQUEST_URI']??null],'timestamp'=>time(),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C']));
// #endregion

// Require admin authentication
requireAdminAuth();

// Include database connection
require_once '../config/config.php';
require_once '../config/db_connect.php';

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
    
    // 1. Total Bookings (Today)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Booking WHERE booking_date = ?");
    $stmt->bind_param("s", $today);
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
    
    // 4. Upcoming Bookings (booking_date >= today AND status='confirmed')
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

<div class="summary-cards-grid">
    <!-- Total Bookings Today -->
    <div class="summary-card">
        <div class="summary-icon" style="background-color: rgba(212, 165, 116, 0.1); color: #D4A574;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
        </div>
        <div class="summary-info">
            <h3>Total Bookings Today</h3>
            <div class="summary-value"><?php echo number_format($kpi_data['total_bookings']); ?></div>
            <p class="stat-desc">Bookings scheduled for <?php echo date('d M Y'); ?>.</p>
        </div>
    </div>

    <!-- Active Staff -->
    <div class="summary-card">
        <div class="summary-icon" style="background-color: rgba(194, 144, 118, 0.1); color: #c29076;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="summary-info">
            <h3>Active Staff</h3>
            <div class="summary-value"><?php echo number_format($kpi_data['active_staff']); ?></div>
            <p class="stat-desc">Active staff accounts including Admin roles.</p>
        </div>
    </div>

    <!-- Today's Income -->
    <div class="summary-card">
        <div class="summary-icon" style="background-color: rgba(76, 175, 80, 0.1); color: #4CAF50;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
        <div class="summary-info">
            <h3>Today's Income</h3>
            <div class="summary-value">RM <?php echo number_format($kpi_data['todays_income'], 2); ?></div>
            <p class="stat-desc">Sum of confirmed and completed bookings for today.</p>
        </div>
    </div>

    <!-- Upcoming Bookings -->
    <div class="summary-card">
        <div class="summary-icon" style="background-color: rgba(33, 150, 243, 0.1); color: #2196F3;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
        <div class="summary-info">
            <h3>Upcoming Bookings</h3>
            <div class="summary-value"><?php echo number_format($kpi_data['pending_requests']); ?></div>
            <p class="stat-desc">Confirmed bookings from today onward (next 30 days).</p>
        </div>
    </div>
</div>

<style>
/* Dashboard KPI descriptions */
.stat-desc { margin-top: 6px; color: #7a7a7a; font-size: 12px; }

/* Summary card layout (reuse Business Analytics style) */
.summary-cards-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-bottom: 24px;
}
.summary-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: flex-start;
    gap: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #f0f0f0;
}
.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.summary-icon svg { width: 20px; height: 20px; }
.summary-info h3 {
    font-size: 12px;
    color: #888;
    font-weight: 500;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.summary-value {
    font-size: 24px;
    font-weight: 700;
    color: #2d2d2d;
    margin-bottom: 4px;
    line-height: 1.2;
}
.summary-label {
    font-size: 12px;
    color: #aaa;
    margin: 0;
}

@media (max-width: 1024px) {
    .summary-cards-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .summary-cards-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Today's Appointments -->
<div class="dashboard-section">
    <h2 class="section-title">Today's Appointments</h2>
    <div class="appointments-list" id="appointments-list-container">
        <!-- Loading state - will be replaced by JavaScript -->
        <div class="loading-spinner">Loading appointments...</div>
    </div>
</div>

<!-- Two Column Section: Recent Activity & Top Services -->
<div class="dashboard-grid-2">
    <!-- Recent Activity -->
    <div class="dashboard-section">
        <h2 class="section-title">Recent Activity</h2>
        <div class="activity-list" id="activity-list-container">
            <!-- Loading state - will be replaced by JavaScript -->
            <div class="loading-spinner">Loading activity...</div>
        </div>
    </div>

    <!-- Top Services Today -->
    <div class="dashboard-section">
        <h2 class="section-title">Top Services Today</h2>
        <div class="services-list" id="services-list-container">
            <!-- Loading state - will be replaced by JavaScript -->
            <div class="loading-spinner">Loading services...</div>
        </div>
    </div>
</div>

<script>
    // Pass base path to dashboard.js
    const DASHBOARD_BASE_PATH = '<?php echo isset($base_path) ? $base_path : ".."; ?>';
</script>
<script src="js/dashboard.js"></script>
<?php
// Include footer
include 'includes/footer.php';
?>
