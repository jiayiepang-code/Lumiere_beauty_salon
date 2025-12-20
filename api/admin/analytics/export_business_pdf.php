<?php
/**
 * Business Analytics PDF Export
 * Generates professional PDF report for business analytics
 */

// Start output buffering
ob_start();

// Disable error display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_name('admin_session');
session_start();

// Include dependencies
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';
require_once 'mpdf_helper.php';

// Clear any output
ob_clean();

// Check authentication
if (!isAdminAuthenticated()) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: text/plain');
    die('Unauthorized access');
}

// Check session timeout
if (!checkSessionTimeout()) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: text/plain');
    die('Session expired');
}

// Handle GET request only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die('Method not allowed');
}

// Get parameters
$period = isset($_GET['period']) ? trim($_GET['period']) : 'monthly';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;

// Validate period
$valid_periods = ['daily', 'weekly', 'monthly'];
if (!in_array($period, $valid_periods)) {
    $period = 'monthly';
}

// Calculate date range if not provided
if ($start_date === null || $start_date === '') {
    switch ($period) {
        case 'daily':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'weekly':
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'monthly':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            break;
    }
}

if ($end_date === null || $end_date === '') {
    $end_date = $start_date;
}

try {
    // Get database connection
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Failed to connect to database');
    }
    
    // Test connection
    if ($conn->connect_error) {
        throw new Exception('Database connection error: ' . $conn->connect_error);
    }
    
    // Fetch analytics data (similar to booking_trends.php)
    // Metrics
    $metrics_sql = "SELECT 
                        COUNT(*) as total_bookings,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                        SUM(CASE WHEN status = 'no-show' THEN 1 ELSE 0 END) as no_show_bookings,
                        SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as total_revenue
                    FROM Booking
                    WHERE booking_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($metrics_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare metrics query: ' . $conn->error);
    }
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute metrics query: ' . $stmt->error);
    }
    $metrics_result = $stmt->get_result();
    if (!$metrics_result) {
        throw new Exception('Failed to get metrics result: ' . $conn->error);
    }
    $metrics = $metrics_result->fetch_assoc();
    if (!$metrics) {
        // Initialize with zeros if no data
        $metrics = [
            'total_bookings' => 0,
            'completed_bookings' => 0,
            'cancelled_bookings' => 0,
            'no_show_bookings' => 0,
            'total_revenue' => 0
        ];
    }
    $stmt->close();
    
    // Calculate average booking value
    $average_booking_value = 0;
    if (isset($metrics['completed_bookings']) && $metrics['completed_bookings'] > 0) {
        $average_booking_value = $metrics['total_revenue'] / $metrics['completed_bookings'];
    }
    
    // Calculate commission
    $commission_sql = "SELECT 
                        COALESCE(SUM(bs.quoted_price), 0) * 0.10 AS total_commission,
                        (SUM(bs.quoted_price) * 0.10) / NULLIF(SUM(b.total_price), 0) * 100 AS commission_ratio
                      FROM Booking_Service bs
                      JOIN Booking b ON bs.booking_id = b.booking_id
                      WHERE bs.service_status = 'completed'
                        AND b.booking_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($commission_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare commission query: ' . $conn->error);
    }
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute commission query: ' . $stmt->error);
    }
    $commission_result = $stmt->get_result();
    if (!$commission_result) {
        throw new Exception('Failed to get commission result: ' . $conn->error);
    }
    $commission_data = $commission_result->fetch_assoc();
    $stmt->close();
    
    $total_commission = isset($commission_data['total_commission']) ? (float)$commission_data['total_commission'] : 0;
    $commission_ratio = isset($commission_data['commission_ratio']) ? (float)$commission_data['commission_ratio'] : 0;
    
    // Staff performance
    $staff_sql = "SELECT 
                      s.staff_email,
                      CONCAT(s.first_name, ' ', s.last_name) AS full_name,
                      COUNT(bs.booking_service_id) AS completed_count,
                      COALESCE(SUM(bs.quoted_price), 0) AS revenue_generated,
                      COALESCE(SUM(bs.quoted_price), 0) * 0.10 AS commission_earned
                  FROM Staff s
                  LEFT JOIN Booking_Service bs ON s.staff_email = bs.staff_email 
                      AND bs.service_status = 'completed'
                  LEFT JOIN Booking b ON bs.booking_id = b.booking_id
                      AND b.booking_date BETWEEN ? AND ?
                  WHERE s.is_active = 1 AND s.role != 'admin'
                  GROUP BY s.staff_email, s.first_name, s.last_name
                  ORDER BY revenue_generated DESC
                  LIMIT 10";
    
    $stmt = $conn->prepare($staff_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare staff query: ' . $conn->error);
    }
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute staff query: ' . $stmt->error);
    }
    $staff_result = $stmt->get_result();
    if (!$staff_result) {
        throw new Exception('Failed to get staff result: ' . $conn->error);
    }
    
    $staff_performance = [];
    $rank = 1;
    while ($row = $staff_result->fetch_assoc()) {
        $staff_performance[] = [
            'rank' => $rank++,
            'name' => $row['full_name'] ?? 'Unknown',
            'completed' => (int)($row['completed_count'] ?? 0),
            'revenue' => (float)($row['revenue_generated'] ?? 0),
            'commission' => (float)($row['commission_earned'] ?? 0)
        ];
    }
    $stmt->close();
    
    // Popular services
    $services_sql = "SELECT 
                         s.service_name,
                         COUNT(bs.booking_service_id) as booking_count,
                         SUM(CASE WHEN b.status = 'completed' THEN bs.quoted_price * bs.quantity ELSE 0 END) as revenue
                     FROM Booking_Service bs
                     INNER JOIN Service s ON bs.service_id = s.service_id
                     INNER JOIN Booking b ON bs.booking_id = b.booking_id
                     WHERE b.booking_date BETWEEN ? AND ?
                     GROUP BY s.service_id, s.service_name
                     ORDER BY booking_count DESC
                     LIMIT 10";
    
    $stmt = $conn->prepare($services_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare services query: ' . $conn->error);
    }
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute services query: ' . $stmt->error);
    }
    $services_result = $stmt->get_result();
    if (!$services_result) {
        throw new Exception('Failed to get services result: ' . $conn->error);
    }
    
    $popular_services = [];
    while ($row = $services_result->fetch_assoc()) {
        $popular_services[] = [
            'name' => $row['service_name'] ?? 'Unknown',
            'bookings' => (int)($row['booking_count'] ?? 0),
            'revenue' => (float)($row['revenue'] ?? 0)
        ];
    }
    $stmt->close();
    
    // Daily breakdown
    $daily_sql = "SELECT 
                      booking_date as date,
                      COUNT(*) as bookings,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                      SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as revenue
                  FROM Booking
                  WHERE booking_date BETWEEN ? AND ?
                  GROUP BY booking_date
                  ORDER BY booking_date ASC
                  LIMIT 30";
    
    $stmt = $conn->prepare($daily_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare daily breakdown query: ' . $conn->error);
    }
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute daily breakdown query: ' . $stmt->error);
    }
    $daily_result = $stmt->get_result();
    if (!$daily_result) {
        throw new Exception('Failed to get daily breakdown result: ' . $conn->error);
    }
    
    $daily_breakdown = [];
    while ($row = $daily_result->fetch_assoc()) {
        $daily_breakdown[] = [
            'date' => $row['date'] ?? date('Y-m-d'),
            'bookings' => (int)($row['bookings'] ?? 0),
            'completed' => (int)($row['completed'] ?? 0),
            'revenue' => (float)($row['revenue'] ?? 0)
        ];
    }
    $stmt->close();
    
    $conn->close();
    
    // Format dates for display
    $start_date_display = date('F j, Y', strtotime($start_date));
    $end_date_display = date('F j, Y', strtotime($end_date));
    $period_display = ucfirst($period);
    if ($start_date === $end_date) {
        $period_display = $start_date_display;
    } else {
        $period_display = $start_date_display . ' - ' . $end_date_display;
    }
    $generated_date = date('F j, Y g:i A');
    
    // Calculate completion rate
    $completion_rate = $metrics['total_bookings'] > 0 
        ? ($metrics['completed_bookings'] / $metrics['total_bookings']) * 100 
        : 0;
    
    // Initialize mPDF
    $mpdf = initMPDF();
    
    if (!$mpdf) {
        throw new Exception('mPDF library not available. Please ensure mPDF is installed in vendor/mpdf/');
    }

    // Set PDF metadata
    $mpdf->SetTitle('Business Analytics Report - ' . $period_display);
    $mpdf->SetAuthor('Lumière Beauty Salon');
    $mpdf->SetSubject('Business Analytics Report');
    
    // Set footer (applies to all pages)
    $mpdf->SetHTMLFooter(generatePDFFooter('Business Analytics Report'));

    // Generate HTML content with modern executive summary template style
    $html = '<style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            color: #2d2d2d;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        h1 {
            font-size: 22pt;
            font-weight: bold;
            color: #1a1a1a;
            text-align: center;
            margin: 15px 0;
        }
        h2 {
            font-size: 14pt;
            font-weight: bold;
            color: #2d2d2d;
            margin-top: 20px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #D4A574;
        }
        h3 {
            font-size: 12pt;
            font-weight: bold;
            color: #2d2d2d;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        .cover-page {
            text-align: center;
            padding: 60px 20px;
            page-break-after: always;
        }
        .cover-title {
            font-size: 24pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 25px;
            line-height: 1.2;
            word-wrap: break-word;
        }
        .cover-subtitle {
            font-size: 12pt;
            color: #666;
            margin-top: 15px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card-header {
            background: #f8f9fa;
            padding: 10px 15px;
            margin: -15px -15px 15px -15px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: bold;
            font-size: 11pt;
            color: #2d2d2d;
        }
        .kpi-grid {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        .kpi-item {
            display: table-cell;
            width: 33.33%;
            padding: 12px;
            text-align: center;
            border: 1px solid #e0e0e0;
            background-color: #fafafa;
        }
        .kpi-value {
            font-size: 18pt;
            font-weight: bold;
            color: #D4A574;
            margin: 8px 0;
        }
        .kpi-label {
            font-size: 9pt;
            color: #666;
            text-transform: uppercase;
        }
        .chart-container {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .chart-title {
            font-size: 12pt;
            font-weight: bold;
            color: #2d2d2d;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        .two-column {
            width: 100%;
            margin: 15px 0;
        }
        .column {
            width: 50%;
            vertical-align: top;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 9pt;
        }
        table th {
            background-color: #f5f5f5;
            color: #2d2d2d;
            font-weight: bold;
            padding: 8px;
            text-align: left;
            border: 1px solid #e0e0e0;
            font-size: 9pt;
        }
        table td {
            padding: 6px 8px;
            border: 1px solid #e0e0e0;
            font-size: 9pt;
        }
        table tr:nth-child(even) {
            background-color: #fafafa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .metric-row {
            margin: 8px 0;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .metric-label {
            font-weight: bold;
            display: inline-block;
            width: 180px;
            font-size: 9pt;
        }
        .metric-value {
            color: #2d2d2d;
            font-size: 9pt;
        }
        .page-break {
            page-break-before: always;
        }
        .section-title {
            font-size: 13pt;
            font-weight: bold;
            color: #1a1a1a;
            margin: 20px 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #D4A574;
        }
    </style>';
    
    // Cover Page (no header on first page) - Add logo
    $company = getCompanyInfo();
    $logo_html = '';
    $logo_path = $company['logo_path'] ?? '';
    
    if (!empty($logo_path) && file_exists($logo_path) && is_readable($logo_path)) {
        try {
            // Convert logo to base64 for embedding in PDF
            $logo_data = file_get_contents($logo_path);
            if ($logo_data !== false && strlen($logo_data) > 0) {
                $logo_base64 = base64_encode($logo_data);
                $logo_mime = 'image/png'; // Default to PNG
                
                // Try to detect MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected_mime = finfo_file($finfo, $logo_path);
                    if ($detected_mime && strpos($detected_mime, 'image/') === 0) {
                        $logo_mime = $detected_mime;
                    }
                    finfo_close($finfo);
                }
                
                $logo_html = '<div style="margin-bottom: 30px; text-align: center;"><img src="data:' . $logo_mime . ';base64,' . $logo_base64 . '" style="max-width: 200px; max-height: 100px; height: auto; display: inline-block;" /></div>';
            }
        } catch (Exception $e) {
            // If logo loading fails, continue without it
            error_log("Cover page logo loading error: " . $e->getMessage());
        }
    }
    
    $cover_html = '<div class="cover-page">
        ' . $logo_html . '
        <div class="cover-title">BUSINESS ANALYTICS REPORT</div>
        <div style="font-size: 16pt; margin: 30px 0; color: #666;">' . htmlspecialchars($period_display) . '</div>
        <div class="cover-subtitle">Generated: ' . htmlspecialchars($generated_date) . '</div>
        <div class="cover-subtitle" style="margin-top: 40px; font-style: italic;">Confidential Business Report</div>
    </div>';
    
    // Write cover page first (without header)
    $mpdf->WriteHTML($cover_html);
    
    // Now set header for subsequent pages
    $mpdf->SetHTMLHeader(generatePDFHeader($mpdf));
    
    // Continue with rest of content
    
    // Executive Summary with card design
    $html .= '<div class="card">';
    $html .= '<div class="card-header">Executive Summary</div>';
    $html .= '<div class="kpi-grid">
        <div class="kpi-item">
            <div class="kpi-label">Total Revenue</div>
            <div class="kpi-value">RM ' . number_format($metrics['total_revenue'], 2) . '</div>
        </div>
        <div class="kpi-item">
            <div class="kpi-label">Commission Paid</div>
            <div class="kpi-value">RM ' . number_format($total_commission, 2) . '</div>
        </div>
        <div class="kpi-item">
            <div class="kpi-label">Booking Volume</div>
            <div class="kpi-value">' . number_format($metrics['total_bookings']) . '</div>
        </div>
    </div>';
    $html .= '</div>';
    
    $html .= '<div class="card">';
    $html .= '<div class="card-header">Period Overview</div>';
    $html .= '<div class="metric-row"><span class="metric-label">Report Period:</span> <span class="metric-value">' . htmlspecialchars($period_display) . '</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Total Bookings:</span> <span class="metric-value">' . number_format($metrics['total_bookings']) . '</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Completion Rate:</span> <span class="metric-value">' . number_format($completion_rate, 1) . '%</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Average Booking Value:</span> <span class="metric-value">RM ' . number_format($average_booking_value, 2) . '</span></div>';
    $html .= '</div>';
    
    // Financial Metrics
    $html .= '<pagebreak />';
    $html .= '<div class="section-title">Financial Metrics</div>';
    
    $html .= '<div class="card">';
    $html .= '<div class="card-header">Revenue Breakdown</div>';
    $html .= '<table>
        <tr>
            <th style="width: 60%;">Metric</th>
            <th style="width: 40%;" class="text-right">Value</th>
        </tr>
        <tr>
            <td>Total Revenue</td>
            <td class="text-right">RM ' . number_format($metrics['total_revenue'], 2) . '</td>
        </tr>
        <tr>
            <td>Commission Paid (10%)</td>
            <td class="text-right">RM ' . number_format($total_commission, 2) . '</td>
        </tr>
        <tr>
            <td>Commission Ratio</td>
            <td class="text-right">' . number_format($commission_ratio, 1) . '%</td>
        </tr>
        <tr>
            <td>Average Booking Value</td>
            <td class="text-right">RM ' . number_format($average_booking_value, 2) . '</td>
        </tr>
    </table>';
    
    $html .= '</div>'; // Close Revenue Breakdown card
    
    $html .= '<div class="card">';
    $html .= '<div class="card-header">Booking Status Summary</div>';
    $html .= '<div class="metric-row"><span class="metric-label">Completed:</span> <span class="metric-value">' . number_format($metrics['completed_bookings']) . ' bookings (' . number_format(($metrics['completed_bookings'] / max($metrics['total_bookings'], 1)) * 100, 1) . '%)</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Confirmed:</span> <span class="metric-value">' . number_format($metrics['total_bookings'] - $metrics['completed_bookings'] - $metrics['cancelled_bookings'] - $metrics['no_show_bookings']) . ' bookings</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Cancelled:</span> <span class="metric-value">' . number_format($metrics['cancelled_bookings']) . ' bookings (' . number_format(($metrics['cancelled_bookings'] / max($metrics['total_bookings'], 1)) * 100, 1) . '%)</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">No-Show:</span> <span class="metric-value">' . number_format($metrics['no_show_bookings']) . ' bookings (' . number_format(($metrics['no_show_bookings'] / max($metrics['total_bookings'], 1)) * 100, 1) . '%)</span></div>';
    $html .= '</div>'; // Close Booking Status card
    
    // Staff Performance Leaderboard
    if (!empty($staff_performance)) {
        $html .= '<pagebreak />';
        $html .= '<div class="section-title">Staff Performance Leaderboard</div>';
        $html .= '<div class="card">';
        $html .= '<table>
            <tr>
                <th style="width: 10%;">Rank</th>
                <th style="width: 35%;">Staff Name</th>
                <th style="width: 20%;" class="text-center">Services</th>
                <th style="width: 25%;" class="text-right">Revenue</th>
                <th style="width: 20%;" class="text-right">Commission</th>
            </tr>';
        
        foreach ($staff_performance as $staff) {
            $html .= '<tr>
                <td class="text-center">#' . $staff['rank'] . '</td>
                <td>' . htmlspecialchars($staff['name']) . '</td>
                <td class="text-center">' . number_format($staff['completed']) . '</td>
                <td class="text-right">RM ' . number_format($staff['revenue'], 2) . '</td>
                <td class="text-right">RM ' . number_format($staff['commission'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>'; // Close Staff Performance card
    }
    
    // Charts Section - Side by Side (Booking Trends Line Chart + Popular Services Bar Chart)
    if ((count($daily_breakdown) <= 30 && !empty($daily_breakdown)) || !empty($popular_services)) {
        $html .= '<pagebreak />';
        $html .= '<div class="section-title">Analytics Charts</div>';
        $html .= '<table class="two-column" style="width: 100%; border-collapse: collapse; margin: 15px 0;"><tr>';
        
        // LEFT COLUMN: Booking Trends Line Chart
        if (count($daily_breakdown) <= 30 && !empty($daily_breakdown)) {
            $html .= '<td class="column" style="width: 50%; vertical-align: top; padding-right: 15px;">';
            $html .= '<div class="chart-container">';
            $html .= '<div class="chart-title">Booking Trends</div>';
            
            // Find max bookings for scaling
            $max_bookings = 0;
            foreach ($daily_breakdown as $day) {
                if ($day['bookings'] > $max_bookings) {
                    $max_bookings = $day['bookings'];
                }
            }
            
            // Chart area
            $chart_height = 180;
            $chart_padding = 15;
            $plot_height = $chart_height - 30;
            $plot_width = 100 - 20;
            
            $html .= '<div style="position: relative; height: ' . $chart_height . 'px; border-left: 2px solid #333; border-bottom: 2px solid #333; padding-left: 5px; padding-right: 5px;">';
            
            // Y-axis labels
            $grid_lines = 5;
            for ($i = 0; $i <= $grid_lines; $i++) {
                $y_pos = ($plot_height / $grid_lines) * ($grid_lines - $i) + 15;
                $value = ($max_bookings / $grid_lines) * $i;
                $html .= '<div style="position: absolute; left: -25px; top: ' . ($y_pos - 6) . 'px; font-size: 8pt; color: #666; text-align: right; width: 20px;">' . round($value) . '</div>';
                if ($i < $grid_lines) {
                    $html .= '<div style="position: absolute; left: 0; top: ' . $y_pos . 'px; right: 0; height: 1px; background: #f5f5f5;"></div>';
                }
            }
            
            // Calculate line points
            $point_count = count($daily_breakdown);
            $points = [];
            foreach ($daily_breakdown as $index => $day) {
                $x_percent = ($index / max($point_count - 1, 1)) * 100;
                $y_percent = $max_bookings > 0 ? (($day['bookings'] / $max_bookings) * 100) : 0;
                $x_pos = ($x_percent / 100) * $plot_width + 5;
                $y_pos = $plot_height - (($y_percent / 100) * $plot_height) + 15;
                $points[] = ['x' => $x_pos, 'y' => $y_pos, 'value' => $day['bookings'], 'date' => $day['date']];
            }
            
            // Draw filled area under line using divs (mPDF compatible)
            if (count($points) > 1) {
                for ($i = 0; $i < count($points) - 1; $i++) {
                    $p1 = $points[$i];
                    $p2 = $points[$i + 1];
                    $width = $p2['x'] - $p1['x'];
                    $height = ($plot_height + 15) - min($p1['y'], $p2['y']);
                    $top = min($p1['y'], $p2['y']);
                    
                    // Create trapezoid shape for filled area
                    $html .= '<div style="position: absolute; left: ' . $p1['x'] . 'px; top: ' . $top . 'px; width: ' . $width . 'px; height: ' . $height . 'px; background: rgba(212, 165, 116, 0.15);"></div>';
                }
            }
            
            // Draw line connecting points using small divs
            if (count($points) > 1) {
                for ($i = 0; $i < count($points) - 1; $i++) {
                    $p1 = $points[$i];
                    $p2 = $points[$i + 1];
                    $dx = $p2['x'] - $p1['x'];
                    $dy = $p2['y'] - $p1['y'];
                    $length = sqrt($dx * $dx + $dy * $dy);
                    $angle = atan2($dy, $dx) * 180 / M_PI;
                    
                    $html .= '<div style="position: absolute; left: ' . $p1['x'] . 'px; top: ' . $p1['y'] . 'px; width: ' . $length . 'px; height: 2.5px; background: #D4A574; transform-origin: 0 50%; transform: rotate(' . $angle . 'deg);"></div>';
                }
            }
            
            // Draw points
            foreach ($points as $p) {
                $html .= '<div style="position: absolute; left: ' . ($p['x'] - 3.5) . 'px; top: ' . ($p['y'] - 3.5) . 'px; width: 7px; height: 7px; background: #D4A574; border: 2px solid #fff; border-radius: 50%;"></div>';
            }
            
            $html .= '</div>'; // Close chart area
            
            // X-axis labels
            $html .= '<div style="display: table; width: 100%; margin-top: 8px; padding-left: 5px; padding-right: 5px;">';
            foreach ($daily_breakdown as $day) {
                $date_label = date('M j', strtotime($day['date']));
                if (strlen($date_label) > 6) {
                    $date_label = date('j M', strtotime($day['date']));
                }
                $html .= '<div style="display: table-cell; text-align: center; font-size: 7pt; color: #666;">' . htmlspecialchars($date_label) . '</div>';
            }
            $html .= '</div>';
            
            $html .= '</div>'; // Close chart-container
            $html .= '</td>'; // Close column
        } else {
            // Empty cell if no booking trends data
            $html .= '<td class="column" style="width: 50%; vertical-align: top; padding-right: 15px;"></td>';
        }
        
        // RIGHT COLUMN: Popular Services Horizontal Bar Chart
        if (!empty($popular_services)) {
            $html .= '<td class="column" style="width: 50%; vertical-align: top; padding-left: 15px;">';
            $html .= '<div class="chart-container">';
            $html .= '<div class="chart-title">Popular Services</div>';
            
            // Find max booking count for scaling bars
            $max_bookings = 0;
            foreach ($popular_services as $service) {
                if ($service['bookings'] > $max_bookings) {
                    $max_bookings = $service['bookings'];
                }
            }
            
            // X-axis scale labels (top)
            $html .= '<div style="display: table; width: 100%; margin-bottom: 8px; font-size: 7pt; color: #666;">';
            $grid_lines = 4;
            for ($i = 0; $i <= $grid_lines; $i++) {
                $value = ($max_bookings / $grid_lines) * $i;
                $html .= '<div style="display: table-cell; text-align: center;">' . round($value) . '</div>';
            }
            $html .= '</div>';
            
            // Chart bars
            $bar_height = 18;
            $bar_spacing = 6;
            $max_services = min(count($popular_services), 8); // Limit to 8 services for better fit
            
            for ($idx = 0; $idx < $max_services; $idx++) {
                $service = $popular_services[$idx];
                $bar_width_percent = $max_bookings > 0 ? ($service['bookings'] / $max_bookings) * 100 : 0;
                
                $html .= '<div style="margin-bottom: ' . $bar_spacing . 'px;">';
                $html .= '<div style="display: table; width: 100%;">';
                
                // Service name (left - 35%)
                $service_name_short = strlen($service['name']) > 15 ? substr($service['name'], 0, 15) . '...' : $service['name'];
                $html .= '<div style="display: table-cell; width: 35%; vertical-align: middle; padding-right: 8px;">';
                $html .= '<span style="font-size: 8pt; color: #2d2d2d; font-weight: 500;">' . htmlspecialchars($service_name_short) . '</span>';
                $html .= '</div>';
                
                // Bar container (middle - 50%)
                $html .= '<div style="display: table-cell; width: 50%; vertical-align: middle; padding-right: 8px;">';
                $html .= '<div style="position: relative; width: 100%; height: ' . $bar_height . 'px; background-color: #f0f0f0; border-radius: 3px; overflow: hidden;">';
                $html .= '<div style="position: absolute; left: 0; top: 0; width: ' . number_format($bar_width_percent, 2) . '%; height: 100%; background: #D4A574; border-radius: 3px;"></div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Count (right - 15%)
                $html .= '<div style="display: table-cell; width: 15%; vertical-align: middle; text-align: right;">';
                $html .= '<span style="font-size: 9pt; color: #2d2d2d; font-weight: bold;">' . number_format($service['bookings']) . '</span>';
                $html .= '</div>';
                
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // Close chart-container
            $html .= '</td>'; // Close column
        } else {
            // Empty cell if no popular services data
            $html .= '<td class="column" style="width: 50%; vertical-align: top; padding-left: 15px;"></td>';
        }
        
        $html .= '</tr></table>'; // Close two-column table
    }
    
    // Write HTML to mPDF
    $mpdf->WriteHTML($html);
    
    // Output PDF
    ob_end_clean();
    $filename = 'Business_Analytics_Report_' . date('Y-m-d', strtotime($start_date)) . '_to_' . date('Y-m-d', strtotime($end_date)) . '.pdf';
    
    // Use the correct namespace for Destination
    if (class_exists('\Mpdf\Output\Destination')) {
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
    } else {
        // Fallback for older mPDF versions
        $mpdf->Output($filename, 'D');
    }
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Business PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
} catch (Error $e) {
    
    ob_end_clean();
    error_log("Business PDF Export Fatal Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>


