<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

// Include authentication check
require_once '../includes/auth_check.php';

// Check if user is authenticated
if (!isAdminAuthenticated()) {
    header('Location: ../login.html');
    exit;
}

// Check session timeout
if (!checkSessionTimeout()) {
    session_destroy();
    header('Location: ../login.html');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sustainability Analytics - Lumière Beauty Salon Admin</title>
    <link rel="stylesheet" href="../css/admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .analytics-container {
            padding: 20px;
        }
        
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .analytics-header h1 {
            margin: 0;
            color: #333;
        }
        
        .period-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .period-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .period-btn.active {
            background: #8B4789;
            color: white;
            border-color: #8B4789;
        }
        
        .period-btn:hover:not(.active) {
            background: #f5f5f5;
        }
        
        .date-range-picker {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-range-picker input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .date-range-picker button {
            padding: 8px 16px;
            background: #8B4789;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .date-range-picker button:hover {
            background: #6d3669;
        }
        
        .export-btn {
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .export-btn:hover {
            background: #45a049;
        }
        
        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .kpi-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: normal;
        }
        
        .kpi-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .kpi-card.idle .kpi-value {
            color: #FF9800;
        }
        
        .kpi-card.utilization .kpi-value {
            color: #4CAF50;
        }
        
        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .chart-card h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 18px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        
        .staff-breakdown {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .staff-breakdown h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 18px;
        }
        
        .staff-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .staff-table th,
        .staff-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .staff-table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
        }
        
        .staff-table tr:hover {
            background: #f9f9f9;
        }
        
        .utilization-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .utilization-high {
            background: #c8e6c9;
            color: #2e7d32;
        }
        
        .utilization-medium {
            background: #fff9c4;
            color: #f57f17;
        }
        
        .utilization-low {
            background: #ffccbc;
            color: #d84315;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .analytics-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .period-selector {
                flex-direction: column;
                width: 100%;
            }
            
            .date-range-picker {
                flex-direction: column;
                width: 100%;
            }
            
            .date-range-picker input,
            .date-range-picker button {
                width: 100%;
            }
            
            .staff-table {
                font-size: 14px;
            }
            
            .staff-table th,
            .staff-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="analytics-container">
                <div class="analytics-header">
                    <h1>Sustainability Analytics</h1>
                    <div class="period-selector">
                        <button class="period-btn" data-period="daily">Daily</button>
                        <button class="period-btn" data-period="weekly">Weekly</button>
                        <button class="period-btn active" data-period="monthly">Monthly</button>
                        <div class="date-range-picker">
                            <input type="date" id="start-date" placeholder="Start Date">
                            <input type="date" id="end-date" placeholder="End Date">
                            <button id="apply-range">Apply</button>
                        </div>
                        <button class="export-btn" id="export-pdf">Export ESG Report</button>
                    </div>
                </div>
                
                <div id="loading" class="loading">Loading sustainability data...</div>
                <div id="error-container"></div>
                
                <div id="analytics-content" style="display: none;">
                    <div class="kpi-cards">
                        <div class="kpi-card">
                            <h3>Total Scheduled Hours</h3>
                            <p class="kpi-value" id="scheduled-hours">0</p>
                        </div>
                        <div class="kpi-card">
                            <h3>Total Booked Hours</h3>
                            <p class="kpi-value" id="booked-hours">0</p>
                        </div>
                        <div class="kpi-card idle">
                            <h3>Total Idle Hours</h3>
                            <p class="kpi-value" id="idle-hours">0</p>
                        </div>
                        <div class="kpi-card utilization">
                            <h3>Utilization Rate</h3>
                            <p class="kpi-value" id="utilization-rate">0%</p>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h2>Idle Hours Trend</h2>
                        <div class="chart-wrapper">
                            <canvas id="idle-hours-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="staff-breakdown">
                        <h2>Staff Idle Hours Breakdown</h2>
                        <table class="staff-table">
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Scheduled Hours</th>
                                    <th>Booked Hours</th>
                                    <th>Idle Hours</th>
                                    <th>Utilization Rate</th>
                                </tr>
                            </thead>
                            <tbody id="staff-breakdown-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="sustainability.js"></script>
</body>
</html>
