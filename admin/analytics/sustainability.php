<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Set page title
$page_title = 'Sustainability Analytics';
$base_path = '../..';

// Include database connection
require_once '../../config/db_connect.php';

// ========== SUSTAINABILITY METRICS (IDLE HOURS) ==========
$conn = getDBConnection();

// Query 1: Total Staff Capacity (Minutes) - Current Month
// Calculate sum of scheduled time from staff_schedule where status is 'working' or 'off'
$capacityQuery = "
    SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) AS total_capacity_minutes
    FROM staff_schedule
    WHERE (status = 'working' OR status = 'off')
      AND MONTH(work_date) = MONTH(CURRENT_DATE())
      AND YEAR(work_date) = YEAR(CURRENT_DATE())
";
$capacityResult = $conn->query($capacityQuery);
$total_capacity_minutes = $capacityResult->fetch_assoc()['total_capacity_minutes'];

// Query 2: Total Utilized Time (Minutes) - Current Month
// Sum of (quoted_duration_minutes + quoted_cleanup_minutes) for confirmed/completed bookings
$utilizedQuery = "
    SELECT COALESCE(SUM(bs.quoted_duration_minutes + bs.quoted_cleanup_minutes), 0) AS total_utilized_minutes
    FROM booking_service bs
    JOIN booking b ON bs.booking_id = b.booking_id
    WHERE (b.status = 'confirmed' OR b.status = 'completed')
      AND MONTH(b.created_at) = MONTH(CURRENT_DATE())
      AND YEAR(b.created_at) = YEAR(CURRENT_DATE())
";
$utilizedResult = $conn->query($utilizedQuery);
$total_utilized_minutes = $utilizedResult->fetch_assoc()['total_utilized_minutes'];

// Calculate Idle Hours and Utilization Rate
$total_idle_minutes = $total_capacity_minutes - $total_utilized_minutes;
$total_idle_hours = $total_idle_minutes / 60;
$total_scheduled_hours = $total_capacity_minutes / 60;
$total_booked_hours = $total_utilized_minutes / 60;

// Calculate Utilization Rate (avoid division by zero)
$utilization_rate = $total_capacity_minutes > 0 
    ? ($total_utilized_minutes / $total_capacity_minutes) * 100 
    : 0;

// Get current month name for display
$current_month = date('F Y');

$conn->close();

// Include header
include '../includes/header.php';
?>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="analytics-page">
    <div class="analytics-header">
        <div>
            <h1 class="analytics-title">Sustainability Analytics</h1>
            <p class="analytics-subtitle">Monitor resource utilization and idle hours</p>
        </div>
        <div class="header-actions">
                <button id="export-pdf" class="btn btn-outline">
                    <i class="fas fa-download"></i> Export Report
                </button>
                <div class="date-filter-group">
                    <div class="btn-group">
                        <button class="btn btn-outline period-btn active" data-period="monthly">Monthly</button>
                        <button class="btn btn-outline period-btn" data-period="weekly">Weekly</button>
                        <button class="btn btn-outline period-btn" data-period="daily">Daily</button>
                    </div>
                    <div class="date-range-picker">
                        <input type="date" id="start-date" class="form-control" placeholder="Start Date">
                        <span class="separator">to</span>
                        <input type="date" id="end-date" class="form-control" placeholder="End Date">
                        <button id="apply-range" class="btn btn-primary">Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="loading" class="loading-state">
            <div class="spinner"></div>
            <p>Loading sustainability data...</p>
        </div>

        <div id="error-container"></div>

        <!-- ========== CURRENT MONTH SUSTAINABILITY METRICS ========== -->
        <div class="month-summary-section">
            <h2 class="section-title">Sustainability <span class="month-badge"><?php echo $current_month; ?></span></h2>
            <p class="section-subtitle">Monitor resource utilization and efficiency</p>
            <div class="summary-cards-grid sustainability-grid">
                <!-- Total Scheduled Hours Card -->
                <div class="summary-card">
                    <div class="summary-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #FFC107;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Total Scheduled Hours</h3>
                        <p class="summary-value"><?php echo number_format($total_scheduled_hours, 0); ?>h</p>
                        <p class="summary-label">Total staff capacity</p>
                    </div>
                </div>

                <!-- Booked Hours Card -->
                <div class="summary-card">
                    <div class="summary-icon" style="background-color: rgba(139, 195, 74, 0.1); color: #8BC34A;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Booked Hours</h3>
                        <p class="summary-value"><?php echo number_format($total_booked_hours, 0); ?>h</p>
                        <p class="summary-label trend-indicator positive">
                            <i class="fas fa-arrow-up"></i> 5%
                        </p>
                    </div>
                </div>

                <!-- Idle Hours Card -->
                <div class="summary-card">
                    <div class="summary-icon" style="background-color: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Idle Hours</h3>
                        <p class="summary-value"><?php echo number_format($total_idle_hours, 0); ?>h</p>
                        <p class="summary-label trend-indicator positive">
                            <i class="fas fa-arrow-down"></i> 8%
                        </p>
                    </div>
                </div>

                <!-- Utilization Rate Card -->
                <div class="summary-card">
                    <div class="summary-icon" style="background-color: rgba(212, 165, 116, 0.1); color: #D4A574;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Utilization Rate</h3>
                        <p class="summary-value"><?php echo number_format($utilization_rate, 1); ?>%</p>
                        <div class="utilization-bar">
                            <div class="utilization-fill" style="width: <?php echo min($utilization_rate, 100); ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="analytics-content" style="display: none;">
            <!-- KPI Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(33, 150, 243, 0.1); color: #2196F3;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Scheduled Hours</h3>
                        <p class="stat-value" id="scheduled-hours">0 hrs</p>
                        <p class="stat-label">Total staff capacity</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Booked Hours</h3>
                        <p class="stat-value" id="booked-hours">0 hrs</p>
                        <p class="stat-label">Actual service time</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Idle Hours</h3>
                        <p class="stat-value" id="idle-hours">0 hrs</p>
                        <p class="stat-label">Unutilized capacity</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(139, 71, 137, 0.1); color: #8B4789;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Utilization Rate</h3>
                        <p class="stat-value" id="utilization-rate">0%</p>
                        <p class="stat-label">Efficiency score</p>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Idle Hours Trend</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 350px;">
                        <canvas id="idle-hours-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Staff Breakdown Table -->
            <div class="card">
                <div class="card-header">
                    <h2>Staff Utilization Breakdown</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Scheduled Hours</th>
                                    <th>Booked Hours</th>
                                    <th>Idle Hours</th>
                                    <th>Utilization</th>
                                </tr>
                            </thead>
                            <tbody id="staff-breakdown-body">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Page Specific JS -->
<script src="sustainability.js"></script>

<style>
.date-filter-group {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-group {
    display: flex;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
}

.btn-group .btn {
    border: none;
    border-radius: 0;
    border-right: 1px solid #ddd;
    padding: 8px 16px;
}

.btn-group .btn:last-child {
    border-right: none;
}

.btn-group .btn.active {
    background-color: #8B4789;
    color: white;
}

.date-range-picker {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 5px 10px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.date-range-picker input {
    border: 1px solid #eee;
    padding: 5px;
    border-radius: 4px;
}

.utilization-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
}

.utilization-high {
    background-color: rgba(76, 175, 80, 0.1);
    color: #4CAF50;
}

.utilization-medium {
    background-color: rgba(255, 152, 0, 0.1);
    color: #FF9800;
}

.utilization-low {
    background-color: rgba(244, 67, 54, 0.1);
    color: #F44336;
}

/* Month Summary Section */
.month-summary-section {
    margin-bottom: 30px;
    animation: slideUp 0.6s ease-out;
}

.section-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.month-badge {
    background: linear-gradient(135deg, #D4A574, #C4956A);
    color: white;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.section-subtitle {
    color: #666;
    font-size: 1rem;
    margin-bottom: 20px;
}

.summary-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
}

.summary-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.summary-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.summary-info h3 {
    font-size: 0.875rem;
    color: #666;
    font-weight: 500;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.summary-label {
    font-size: 0.875rem;
    color: #999;
}

.trend-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
}

.trend-indicator.positive {
    color: #4CAF50;
}

.trend-indicator.negative {
    color: #F44336;
}

.sustainability-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.utilization-bar {
    width: 100%;
    height: 8px;
    background-color: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 8px;
}

.utilization-fill {
    height: 100%;
    background: linear-gradient(90deg, #D4A574, #C4956A);
    border-radius: 4px;
    transition: width 1s ease-out;
}
</style>

<?php require_once '../includes/footer.php'; ?>
