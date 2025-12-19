<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Set page title
$page_title = 'Business Analytics';
$base_path = '../..';

// Include database connection
require_once '../../config/db_connect.php';

// ========== CURRENT MONTH SUMMARY METRICS ==========
$conn = getDBConnection();

// Query 1: Total Revenue (Current Month, Completed Bookings)
$revenueQuery = "
    SELECT COALESCE(SUM(total_price), 0) AS total_revenue
    FROM Booking
    WHERE status = 'completed'
      AND MONTH(booking_date) = MONTH(CURRENT_DATE())
      AND YEAR(booking_date) = YEAR(CURRENT_DATE())
";
$revenueResult = $conn->query($revenueQuery);
if (!$revenueResult) {
    die("Revenue query failed: " . $conn->error);
}
$total_revenue = $revenueResult->fetch_assoc()['total_revenue'];

// Query 2: Total Commission Paid + Commission Ratio (Current Month, Completed Services)
// Commission = 10% of quoted_price from booking_service for completed services
$commissionQuery = "
    SELECT 
        COALESCE(SUM(bs.quoted_price), 0) * 0.10 AS total_commission,
        (SUM(bs.quoted_price) * 0.10) / NULLIF(SUM(b.total_price), 0) * 100 AS commission_ratio
    FROM Booking_Service bs
    JOIN Booking b ON bs.booking_id = b.booking_id
    WHERE bs.service_status = 'completed'
      AND MONTH(b.booking_date) = MONTH(CURRENT_DATE())
      AND YEAR(b.booking_date) = YEAR(CURRENT_DATE())
";
$commissionResult = $conn->query($commissionQuery);
if (!$commissionResult) {
    die("Commission query failed: " . $conn->error);
}
$commissionData = $commissionResult->fetch_assoc();
$total_commission = $commissionData['total_commission'];
$commission_ratio = $commissionData['commission_ratio'] ?? 0;

// Query 3: Total Booking Volume (Current Month, All Statuses)
$volumeQuery = "
    SELECT COUNT(*) AS total_volume
    FROM Booking
    WHERE MONTH(booking_date) = MONTH(CURRENT_DATE())
      AND YEAR(booking_date) = YEAR(CURRENT_DATE())
";
$volumeResult = $conn->query($volumeQuery);
if (!$volumeResult) {
    die("Volume query failed: " . $conn->error);
}
$total_volume = $volumeResult->fetch_assoc()['total_volume'];

// Get current month name for display
$current_month = date('F Y');

// ========== STAFF LEADERBOARD (Performance Ranking) ==========
// Query: Staff Performance Ranking matching Staff Module's ranking system
$leaderboardQuery = "
    SELECT 
        s.staff_email,
        CONCAT(s.first_name, ' ', s.last_name) AS full_name,
        COUNT(bs.booking_service_id) AS completed_count,
        COALESCE(SUM(bs.quoted_price), 0) AS revenue_generated,
        COALESCE(SUM(bs.quoted_price), 0) * 0.10 AS commission_earned
    FROM Staff s
    LEFT JOIN Booking_Service bs ON s.staff_email = bs.staff_email 
        AND bs.service_status = 'completed'
    LEFT JOIN Booking b ON bs.booking_id = b.booking_id
        AND MONTH(b.booking_date) = MONTH(CURRENT_DATE())
        AND YEAR(b.booking_date) = YEAR(CURRENT_DATE())
    WHERE s.is_active = 1 AND s.role != 'admin'
    GROUP BY s.staff_email, s.first_name, s.last_name
    ORDER BY revenue_generated DESC
";
$leaderboardResult = $conn->query($leaderboardQuery);
if (!$leaderboardResult) {
    die("Leaderboard query failed: " . $conn->error);
}
$staff_leaderboard = [];
while ($row = $leaderboardResult->fetch_assoc()) {
    $staff_leaderboard[] = $row;
}

$conn->close();

// Include header
include '../includes/header.php';
?>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="analytics-page">
    <div class="analytics-header">
        <div>
            <h1 class="analytics-title">Business Analytics</h1>
            <p class="analytics-subtitle">Track your salon's performance metrics</p>
        </div>
        <div class="header-actions">
            <div class="filter-dropdowns">
                <select class="filter-select" id="period-select">
                    <option value="daily">Daily</option>
                    <option value="weekly" selected>Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <select class="filter-select" id="range-select">
                    <option value="7">Last 7 days</option>
                    <option value="14">Last 14 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </select>
            </div>
            <button class="btn-export" id="export-report">
                <i class="fas fa-download"></i> Export Report
            </button>
        </div>
    </div>

    <div id="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading analytics data...</p>
    </div>

    <div id="error-container"></div>

    <!-- ========== CURRENT MONTH SUMMARY CARDS ========== -->
    <div class="month-summary-section">
        <h2 class="section-title">Current Month Summary <span class="month-badge"><?php echo $current_month; ?></span></h2>
        <div class="summary-cards-grid">
            <!-- Total Revenue Card -->
            <div class="summary-card">
                <div class="summary-icon" style="background-color: rgba(194, 144, 118, 0.1); color: #c29076;">
                    <i class="fas fa-dollar-sign" aria-hidden="true"></i>
                </div>
                <div class="summary-info">
                    <h3>Total Revenue</h3>
                    <p class="summary-value">RM <?php echo number_format($total_revenue, 2); ?></p>
                    <p class="summary-label">Completed bookings</p>
                </div>
            </div>

            <!-- Commission Paid Card -->
            <div class="summary-card">
                <div class="summary-icon" style="background-color: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                    <i class="fas fa-money-bill-wave" aria-hidden="true"></i>
                </div>
                <div class="summary-info">
                    <h3>Commission Paid</h3>
                    <p class="summary-value">RM <?php echo number_format($total_commission, 2); ?></p>
                    <p class="summary-label">10% rate (<?php echo number_format($commission_ratio, 1); ?>% of revenue)</p>
                </div>
            </div>

            <!-- Booking Volume Card -->
            <div class="summary-card">
                <div class="summary-icon" style="background-color: rgba(33, 150, 243, 0.1); color: #2196F3;">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                </div>
                <div class="summary-info">
                    <h3>Booking Volume</h3>
                    <p class="summary-value"><?php echo number_format($total_volume); ?></p>
                    <p class="summary-label">All bookings this month</p>
                </div>
            </div>
        </div>
    </div>

    <div id="analytics-content" style="display: none;">
        <!-- ========== STAFF LEADERBOARD (Current Month) ========== -->
        <div class="staff-leaderboard-section">
            <h2 class="section-title">Staff Performance Leaderboard <span class="month-badge"><?php echo $current_month; ?></span></h2>
            <p class="section-subtitle">Ranked by revenue generated - matching Staff Module rankings</p>
            
            <div class="leaderboard-card">
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Rank</th>
                            <th>Staff Name</th>
                            <th style="text-align: center;">Completed Services</th>
                            <th style="text-align: right;">Revenue Generated</th>
                            <th style="text-align: right;">Commission Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_leaderboard)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #888; padding: 40px;">
                                    No staff performance data available for this month
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_leaderboard as $index => $staff): ?>
                                <tr class="leaderboard-row">
                                    <td>
                                        <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                            #<?php echo $index + 1; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="staff-name"><?php echo htmlspecialchars($staff['full_name']); ?></div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="metric-value"><?php echo number_format($staff['completed_count']); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <span class="metric-value">RM <?php echo number_format($staff['revenue_generated'], 2); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <span class="metric-value commission">RM <?php echo number_format($staff['commission_earned'], 2); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KPI Cards - New Design -->
        <div class="kpi-cards-grid">
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" style="background-color: rgba(212, 165, 116, 0.15); color: #D4A574;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <span class="kpi-trend positive" id="bookings-trend">
                        <i class="fas fa-arrow-up"></i> <span>0%</span>
                    </span>
                </div>
                <div class="kpi-body">
                    <p class="kpi-value" id="total-bookings">0</p>
                    <p class="kpi-label">Total Bookings</p>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" style="background-color: rgba(212, 165, 116, 0.15); color: #D4A574;">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <span class="kpi-trend positive" id="completion-trend">
                        <i class="fas fa-arrow-up"></i> <span>0%</span>
                    </span>
                </div>
                <div class="kpi-body">
                    <p class="kpi-value" id="completion-rate">0%</p>
                    <p class="kpi-label">Completion Rate</p>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" style="background-color: rgba(212, 165, 116, 0.15); color: #D4A574;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <span class="kpi-trend positive" id="revenue-trend">
                        <i class="fas fa-arrow-up"></i> <span>0%</span>
                    </span>
                </div>
                <div class="kpi-body">
                    <p class="kpi-value" id="total-revenue">RM 0.00</p>
                    <p class="kpi-label">Total Revenue</p>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" style="background-color: rgba(212, 165, 116, 0.15); color: #D4A574;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="kpi-body">
                    <p class="kpi-value" id="avg-booking">RM 0</p>
                    <p class="kpi-label">Avg Booking Value</p>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h2>Booking Trends</h2>
                </div>
                <div class="chart-body">
                    <div class="chart-container">
                        <canvas id="booking-trends-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h2>Popular Services</h2>
                </div>
                <div class="chart-body">
                    <div class="chart-container">
                        <canvas id="popular-services-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff Performance Table -->
        <div class="staff-performance-card">
            <div class="card-header">
                <h2>Staff Performance</h2>
            </div>
            <div class="card-body">
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Completed Sessions</th>
                            <th>Total Revenue</th>
                            <th>Avg per Session</th>
                        </tr>
                    </thead>
                    <tbody id="staff-performance-body">
                        <tr>
                            <td colspan="4" style="text-align: center; color: #666;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- html2pdf.js for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<!-- Page Specific JS -->
<script src="business.js"></script>

<style>
/* ========== ANALYTICS PAGE STYLES ========== */
.analytics-page {
    padding: 0;
}

.analytics-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.analytics-title {
    font-size: 28px;
    font-weight: 600;
    color: #2d2d2d;
    margin-bottom: 4px;
}

.analytics-subtitle {
    color: #888;
    font-size: 14px;
    margin: 0;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.filter-dropdowns {
    display: flex;
    gap: 12px;
}

.filter-select {
    padding: 10px 16px;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    background: white;
    color: #333;
    font-size: 14px;
    cursor: pointer;
    min-width: 120px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.filter-select:hover {
    border-color: #D4A574;
}

.btn-export {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    color: #333;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-export:hover {
    border-color: #D4A574;
    color: #D4A574;
}

/* ========== MONTH SUMMARY SECTION ========== */
.month-summary-section {
    margin-bottom: 32px;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: #2d2d2d;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.month-badge {
    font-size: 13px;
    font-weight: 400;
    color: #888;
    background: #f5f5f5;
    padding: 4px 12px;
    border-radius: 20px;
}

.summary-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
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

.summary-icon i {
    font-size: 20px;
}

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

/* ========== STAFF LEADERBOARD SECTION ========== */
.staff-leaderboard-section {
    margin-bottom: 32px;
}

.leaderboard-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #f0f0f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
}

.leaderboard-table thead {
    background: #fafafa;
}

.leaderboard-table th {
    padding: 14px 24px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #f0f0f0;
}

.leaderboard-table td {
    padding: 18px 24px;
    font-size: 14px;
    color: #333;
    border-bottom: 1px solid #f5f5f5;
    vertical-align: middle;
}

.leaderboard-row:hover {
    background: #fafafa;
}

.leaderboard-table tbody tr:last-child td {
    border-bottom: none;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
}

.rank-badge.rank-1 {
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
}

.rank-badge.rank-2 {
    background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(192, 192, 192, 0.3);
}

.rank-badge.rank-3 {
    background: linear-gradient(135deg, #CD7F32 0%, #B8732D 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(205, 127, 50, 0.3);
}

.rank-badge:not(.rank-1):not(.rank-2):not(.rank-3) {
    background: #f5f5f5;
    color: #666;
}

.staff-name {
    font-weight: 500;
    color: #2d2d2d;
}

.metric-value {
    font-weight: 600;
    color: #2d2d2d;
}

.metric-value.commission {
    color: #4CAF50;
}

/* ========== SUSTAINABILITY SECTION ========== */
.section-subtitle {
    font-size: 14px;
    color: #888;
    margin-top: -12px;
    margin-bottom: 20px;
}

/* ========== KPI CARDS GRID ========== */
.kpi-cards-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.kpi-card {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    border: 1px solid #f0f0f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.kpi-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.kpi-icon i {
    font-size: 18px;
}

.kpi-trend {
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
}

.kpi-trend.positive {
    color: #22c55e;
}

.kpi-trend.negative {
    color: #ef4444;
}

.kpi-body {
    margin-top: 8px;
}

.kpi-value {
    font-size: 28px;
    font-weight: 700;
    color: #2d2d2d;
    margin-bottom: 4px;
    line-height: 1.2;
}

.kpi-label {
    font-size: 13px;
    color: #888;
    margin: 0;
}

/* ========== CHARTS GRID ========== */
.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}

.chart-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #f0f0f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.chart-header {
    padding: 20px 24px;
    border-bottom: 1px solid #f5f5f5;
}

.chart-header h2 {
    font-size: 16px;
    font-weight: 600;
    color: #2d2d2d;
    margin: 0;
}

.chart-body {
    padding: 24px;
}

.chart-container {
    height: 280px;
    position: relative;
}

/* ========== STAFF PERFORMANCE TABLE ========== */
.staff-performance-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #f0f0f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.staff-performance-card .card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #f5f5f5;
}

.staff-performance-card .card-header h2 {
    font-size: 16px;
    font-weight: 600;
    color: #2d2d2d;
    margin: 0;
}

.staff-performance-card .card-body {
    padding: 0;
}

.performance-table {
    width: 100%;
    border-collapse: collapse;
}

.performance-table thead {
    background: #fafafa;
}

.performance-table th {
    padding: 14px 24px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #f0f0f0;
}

.performance-table th:last-child {
    text-align: right;
}

.performance-table td {
    padding: 16px 24px;
    font-size: 14px;
    color: #333;
    border-bottom: 1px solid #f5f5f5;
}

.performance-table td:last-child {
    text-align: right;
}

.performance-table tbody tr:hover {
    background: #fafafa;
}

.performance-table tbody tr:last-child td {
    border-bottom: none;
}

/* ========== LOADING STATE ========== */
.loading-state {
    text-align: center;
    padding: 60px 20px;
    color: #888;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f0f0f0;
    border-top-color: #D4A574;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 1200px) {
    .kpi-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .sustainability-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 1024px) {
    .summary-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .sustainability-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .analytics-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }
    
    .filter-dropdowns {
        width: 100%;
    }
    
    .filter-select {
        flex: 1;
    }
    
    .kpi-cards-grid {
        grid-template-columns: 1fr;
    }
}

/* ========== CHART ANIMATION ========== */
.chart-card.animate-in .chart-container {
    animation: fadeSlideUp 0.6s ease-out forwards;
}

@keyframes fadeSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.kpi-card.animate-in {
    animation: fadeSlideUp 0.4s ease-out forwards;
}

.kpi-card:nth-child(1) { animation-delay: 0s; }
.kpi-card:nth-child(2) { animation-delay: 0.1s; }
.kpi-card:nth-child(3) { animation-delay: 0.2s; }
.kpi-card:nth-child(4) { animation-delay: 0.3s; }
</style>

<?php require_once '../includes/footer.php'; ?>
