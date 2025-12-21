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
// Extract date filtering logic for reusability
$current_month_num = (int)date('m');
$current_year_num = (int)date('Y');

// Initialize variables with defaults
$total_revenue = 0;
$total_commission = 0;
$commission_ratio = 0;
$total_volume = 0;
$staff_leaderboard = [];
$error_message = '';

try {
    $conn = getDBConnection();

    // Query 1: Total Revenue (Current Month, Completed Bookings)
    $revenueQuery = "
        SELECT COALESCE(SUM(total_price), 0) AS total_revenue
        FROM Booking
        WHERE status = 'completed'
          AND MONTH(booking_date) = ?
          AND YEAR(booking_date) = ?
    ";
    $stmt = $conn->prepare($revenueQuery);
    if ($stmt) {
        $stmt->bind_param("ii", $current_month_num, $current_year_num);
        $stmt->execute();
        $revenueResult = $stmt->get_result();
        if ($revenueResult) {
            $row = $revenueResult->fetch_assoc();
            $total_revenue = (float)($row['total_revenue'] ?? 0);
        } else {
            error_log("Business Analytics: Revenue query result failed");
        }
        $stmt->close();
    } else {
        error_log("Business Analytics: Revenue query prepare failed: " . $conn->error);
    }

    // Query 2: Total Commission Paid + Commission Ratio (Current Month, Completed Services)
    // Commission = 10% of quoted_price from booking_service for completed services
    $commissionQuery = "
        SELECT 
            COALESCE(SUM(bs.quoted_price), 0) * 0.10 AS total_commission,
            (SUM(bs.quoted_price) * 0.10) / NULLIF(SUM(b.total_price), 0) * 100 AS commission_ratio
        FROM Booking_Service bs
        JOIN Booking b ON bs.booking_id = b.booking_id
        WHERE bs.service_status = 'completed'
          AND MONTH(b.booking_date) = ?
          AND YEAR(b.booking_date) = ?
    ";
    $stmt = $conn->prepare($commissionQuery);
    if ($stmt) {
        $stmt->bind_param("ii", $current_month_num, $current_year_num);
        $stmt->execute();
        $commissionResult = $stmt->get_result();
        if ($commissionResult) {
            $commissionData = $commissionResult->fetch_assoc();
            $total_commission = (float)($commissionData['total_commission'] ?? 0);
            $commission_ratio = (float)($commissionData['commission_ratio'] ?? 0);
        } else {
            error_log("Business Analytics: Commission query result failed");
        }
        $stmt->close();
    } else {
        error_log("Business Analytics: Commission query prepare failed: " . $conn->error);
    }

    // Query 3: Total Booking Volume (Current Month, All Statuses)
    $volumeQuery = "
        SELECT COUNT(*) AS total_volume
        FROM Booking
        WHERE MONTH(booking_date) = ?
          AND YEAR(booking_date) = ?
    ";
    $stmt = $conn->prepare($volumeQuery);
    if ($stmt) {
        $stmt->bind_param("ii", $current_month_num, $current_year_num);
        $stmt->execute();
        $volumeResult = $stmt->get_result();
        if ($volumeResult) {
            $row = $volumeResult->fetch_assoc();
            $total_volume = (int)($row['total_volume'] ?? 0);
        } else {
            error_log("Business Analytics: Volume query result failed");
        }
        $stmt->close();
    } else {
        error_log("Business Analytics: Volume query prepare failed: " . $conn->error);
    }

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
            AND MONTH(b.booking_date) = ?
            AND YEAR(b.booking_date) = ?
        WHERE s.is_active = 1 AND s.role != 'admin'
        GROUP BY s.staff_email, s.first_name, s.last_name
        ORDER BY revenue_generated DESC
    ";
    $stmt = $conn->prepare($leaderboardQuery);
    if ($stmt) {
        $stmt->bind_param("ii", $current_month_num, $current_year_num);
        $stmt->execute();
        $leaderboardResult = $stmt->get_result();
        if ($leaderboardResult) {
            while ($row = $leaderboardResult->fetch_assoc()) {
                $staff_leaderboard[] = $row;
            }
        } else {
            error_log("Business Analytics: Leaderboard query result failed");
        }
        $stmt->close();
    } else {
        error_log("Business Analytics: Leaderboard query prepare failed: " . $conn->error);
    }

    $conn->close();
} catch (Exception $e) {
    error_log("Business Analytics Error: " . $e->getMessage());
    $error_message = "An error occurred while loading data. Please try again.";
    if (isset($conn)) {
        $conn->close();
    }
}

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
            <div class="filter-section">
                <div class="filter-group">
                    <label for="date-range-select" style="font-size: 12px; color: #666; margin-bottom: 4px; display: block;">
                        <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> Date Range
                    </label>
                    <select class="filter-select" id="date-range-select" title="Select date range for analytics">
                        <option value="thismonth" selected>This Month</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="last7days">Last 7 Days</option>
                        <option value="last30days">Last 30 Days</option>
                        <option value="thisweek">This Week</option>
                        <option value="lastmonth">Last Month</option>
                        <option value="thisyear">This Year</option>
                        <option value="custom">Custom Range...</option>
                    </select>
                </div>
                <div class="filter-group" id="custom-date-range" style="display: none;">
                    <label style="font-size: 12px; color: #666; margin-bottom: 4px; display: block;">Custom Dates</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="date" id="custom-start-date" class="filter-select" style="min-width: 140px;">
                        <input type="date" id="custom-end-date" class="filter-select" style="min-width: 140px;">
                    </div>
                </div>
            </div>
            <!-- PDF export button -->
            <button type="button" id="export-business-pdf" class="btn btn-export-pdf">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </button>
            <!-- Excel/CSV export button -->
            <button type="button" id="export-business-csv" class="btn btn-secondary">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <div id="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading analytics data...</p>
    </div>

    <div id="error-container"></div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger" style="background: #fef2f2; color: #dc2626; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca;">
            <p><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <!-- ========== FILTERED SUMMARY CARDS ========== -->
    <div class="filtered-summary-section" id="filtered-summary-section" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 class="section-title">Analytics Summary</h2>
            <div id="date-range-indicator" style="font-size: 13px; color: #666; background: #f5f5f5; padding: 6px 12px; border-radius: 20px;">
                <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> Loading...
                </div>
                </div>
        <div class="summary-cards-grid" id="summary-cards-container">
            <!-- Will be populated by JavaScript -->
        </div>
    </div>

    <div id="analytics-content" style="display: none;">
        <!-- Charts Row - Moved before Staff Leaderboard -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Booking Trends</h2>
                    <div id="chart-date-indicator" style="font-size: 12px; color: #666; background: #f5f5f5; padding: 4px 10px; border-radius: 12px;">
                        <i class="fas fa-chart-line" style="margin-right: 4px;"></i> Based on selected filter
                    </div>
                </div>
                <div class="chart-body">
                    <div class="chart-container">
                        <canvas id="booking-trends-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Popular Services</h2>
                    <div id="popular-chart-date-indicator" style="font-size: 12px; color: #666; background: #f5f5f5; padding: 4px 10px; border-radius: 12px;">
                        <i class="fas fa-chart-line" style="margin-right: 4px;"></i> Based on selected filter
                    </div>
                </div>
                <div class="chart-body">
                    <div class="chart-container">
                        <canvas id="popular-services-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== STAFF LEADERBOARD (Filtered) - Moved after Charts ========== -->
        <div class="staff-leaderboard-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <h2 class="section-title">Staff Performance Leaderboard</h2>
                <div id="leaderboard-date-indicator" style="font-size: 13px; color: #666; background: #f5f5f5; padding: 6px 12px; border-radius: 20px;">
                    <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> Loading...
            </div>
            </div>
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
                    <tbody id="staff-leaderboard-body">
                        <tr>
                            <td colspan="5" style="text-align: center; color: #888; padding: 40px;">
                                Loading staff performance data...
                            </td>
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
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
}

.filter-section {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-dropdowns {
    display: flex;
    gap: 16px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
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

.filter-select[type="date"] {
    background-image: none;
    padding-right: 16px;
}

.filter-select:hover {
    border-color: #D4A574;
}

.filter-select[type="date"] {
    background-image: none;
    padding-right: 16px;
    cursor: text;
}

.btn-secondary {
    padding: 10px 20px;
    background: #6c757d;
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-secondary:hover {
    background: #5a6268;
    color: white;
}

.btn-secondary:disabled {
    background: #6c757d;
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary i {
    font-size: 14px;
}

/* Dedicated styling for PDF export button (blue, prominent) */
.btn-export-pdf {
    padding: 10px 20px;
    background: #1976d2;
    border: none;
    border-radius: 8px;
    color: #ffffff;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease, box-shadow 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-export-pdf:hover {
    background: #1565c0;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(21, 101, 192, 0.4);
}

.btn-export-pdf:disabled {
    background: #90caf9;
    cursor: not-allowed;
    box-shadow: none;
}

.btn-export-pdf i {
    font-size: 14px;
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
    grid-template-columns: repeat(4, 1fr);
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
    height: 320px;
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
        grid-template-columns: repeat(2, 1fr);
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
