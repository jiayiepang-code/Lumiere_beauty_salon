<?php
require_once '../../config/config.php';
require_once '../includes/auth_check.php';
requireAdminAuth();

$pageTitle = "Business Analytics";
require_once '../includes/header.php';
?>

<div class="admin-container">
    <?php require_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Business Analytics</h1>
                <p class="subtitle">Monitor booking trends and revenue performance</p>
            </div>
            <div class="header-actions">
                <div class="date-filter-group">
                    <div class="btn-group">
                        <button class="btn btn-outline period-btn active" data-period="daily">Daily</button>
                        <button class="btn btn-outline period-btn" data-period="weekly">Weekly</button>
                        <button class="btn btn-outline period-btn" data-period="monthly">Monthly</button>
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
            <p>Loading analytics data...</p>
        </div>

        <div id="error-container"></div>

        <div id="analytics-content" style="display: none;">
            <!-- KPI Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(139, 71, 137, 0.1); color: #8B4789;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Bookings</h3>
                        <p class="stat-value" id="total-bookings">0</p>
                        <p class="stat-label">Completion Rate: <span id="completion-rate">0%</span></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Completed</h3>
                        <p class="stat-value" id="completed-bookings">0</p>
                        <p class="stat-label">Successfully served</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(244, 67, 54, 0.1); color: #F44336;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Cancelled</h3>
                        <p class="stat-value" id="cancelled-bookings">0</p>
                        <p class="stat-label">Customer cancellations</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stat-info">
                        <h3>No-Show</h3>
                        <p class="stat-value" id="no-show-bookings">0</p>
                        <p class="stat-label">Missed appointments</p>
                    </div>
                </div>
            </div>

            <!-- Revenue Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Revenue Overview</h2>
                </div>
                <div class="card-body">
                    <div class="revenue-summary" style="display: flex; justify-content: space-around; margin-bottom: 20px; text-align: center;">
                        <div>
                            <p class="text-muted">Total Revenue</p>
                            <h3 id="total-revenue" style="color: #8B4789;">RM 0.00</h3>
                        </div>
                        <div>
                            <p class="text-muted">Average Booking Value</p>
                            <h3 id="avg-booking" style="color: #2196F3;">RM 0.00</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid-2-col">
                <div class="card">
                    <div class="card-header">
                        <h2>Booking Trends</h2>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="booking-trends-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Popular Services</h2>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="popular-services-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Page Specific JS -->
<script src="business.js"></script>

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

.grid-2-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

@media (max-width: 1024px) {
    .grid-2-col {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>
