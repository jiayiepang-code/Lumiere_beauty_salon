<?php
require_once '../../config/config.php';
require_once '../includes/auth_check.php';
requireAdminAuth();

$pageTitle = "Sustainability Analytics";
require_once '../includes/header.php';
?>

<div class="admin-container">
    <?php require_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Sustainability Analytics</h1>
                <p class="subtitle">Monitor resource utilization and idle hours</p>
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
    </main>
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
</style>

<?php require_once '../includes/footer.php'; ?>
