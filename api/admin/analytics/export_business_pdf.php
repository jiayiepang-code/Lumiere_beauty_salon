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
    $conn = getDBConnection();
    
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
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $metrics_result = $stmt->get_result();
    $metrics = $metrics_result->fetch_assoc();
    $stmt->close();
    
    // Calculate average booking value
    $average_booking_value = 0;
    if ($metrics['completed_bookings'] > 0) {
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
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $commission_result = $stmt->get_result();
    $commission_data = $commission_result->fetch_assoc();
    $stmt->close();
    
    $total_commission = $commission_data['total_commission'] ?? 0;
    $commission_ratio = $commission_data['commission_ratio'] ?? 0;
    
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
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $staff_result = $stmt->get_result();
    
    $staff_performance = [];
    $rank = 1;
    while ($row = $staff_result->fetch_assoc()) {
        $staff_performance[] = [
            'rank' => $rank++,
            'name' => $row['full_name'],
            'completed' => (int)$row['completed_count'],
            'revenue' => (float)$row['revenue_generated'],
            'commission' => (float)$row['commission_earned']
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
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $services_result = $stmt->get_result();
    
    $popular_services = [];
    while ($row = $services_result->fetch_assoc()) {
        $popular_services[] = [
            'name' => $row['service_name'],
            'bookings' => (int)$row['booking_count'],
            'revenue' => (float)$row['revenue']
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
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $daily_result = $stmt->get_result();
    
    $daily_breakdown = [];
    while ($row = $daily_result->fetch_assoc()) {
        $daily_breakdown[] = [
            'date' => $row['date'],
            'bookings' => (int)$row['bookings'],
            'completed' => (int)$row['completed'],
            'revenue' => (float)$row['revenue']
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
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_1','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:248','message'=>'Before initMPDF','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
    // #endregion

    // Initialize mPDF
    $mpdf = initMPDF();
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_2','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:254','message'=>'After initMPDF','data'=>['mpdfIsObject'=>is_object($mpdf),'mpdfIsFalse'=>$mpdf===false,'mpdfClass'=>is_object($mpdf)?get_class($mpdf):'N/A'],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
    // #endregion
    
    if (!$mpdf) {
        // #region agent log
        file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_3','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:260','message'=>'initMPDF returned false','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
        // #endregion
        throw new Exception('mPDF library not available. Please ensure mPDF is installed in vendor/mpdf/');
    }

    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_4','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:265','message'=>'Before SetTitle/SetAuthor','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion

    // Set PDF metadata
    $mpdf->SetTitle('Business Analytics Report - ' . $period_display);
    $mpdf->SetAuthor('Lumière Beauty Salon');
    $mpdf->SetSubject('Business Analytics Report');
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_5','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:272','message'=>'Before SetHTMLFooter','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion
    
    // Set footer (applies to all pages)
    $mpdf->SetHTMLFooter(generatePDFFooter('Business Analytics Report'));
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_6','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:278','message'=>'After SetHTMLFooter','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion

    // Generate HTML content
    $html = '<style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #2d2d2d;
            line-height: 1.6;
        }
        h1 {
            font-size: 24pt;
            font-weight: bold;
            color: #1a1a1a;
            text-align: center;
            margin: 20px 0;
        }
        h2 {
            font-size: 16pt;
            font-weight: bold;
            color: #2d2d2d;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 2px solid #D4A574;
            padding-bottom: 5px;
        }
        h3 {
            font-size: 14pt;
            font-weight: bold;
            color: #2d2d2d;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .cover-page {
            text-align: center;
            padding: 80px 20px;
            page-break-after: always;
        }
        .cover-title {
            font-size: 26pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 30px;
            line-height: 1.2;
            word-wrap: break-word;
        }
        .cover-subtitle {
            font-size: 14pt;
            color: #666;
            margin-top: 20px;
        }
        .kpi-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        .kpi-item {
            display: table-cell;
            width: 33.33%;
            padding: 15px;
            text-align: center;
            border: 1px solid #e0e0e0;
            background-color: #fafafa;
        }
        .kpi-value {
            font-size: 20pt;
            font-weight: bold;
            color: #D4A574;
            margin: 10px 0;
        }
        .kpi-label {
            font-size: 10pt;
            color: #666;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10pt;
        }
        table th {
            background-color: #f5f5f5;
            color: #2d2d2d;
            font-weight: bold;
            padding: 10px;
            text-align: left;
            border: 1px solid #e0e0e0;
        }
        table td {
            padding: 8px 10px;
            border: 1px solid #e0e0e0;
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
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .metric-label {
            font-weight: bold;
            display: inline-block;
            width: 200px;
        }
        .metric-value {
            color: #2d2d2d;
        }
        .page-break {
            page-break-before: always;
        }
    </style>';
    
    // Cover Page (no header on first page)
    $cover_html = '<div class="cover-page">
        <div class="cover-title">BUSINESS ANALYTICS REPORT</div>
        <div style="font-size: 16pt; margin: 30px 0; color: #666;">' . htmlspecialchars($period_display) . '</div>
        <div class="cover-subtitle">Generated: ' . htmlspecialchars($generated_date) . '</div>
        <div class="cover-subtitle" style="margin-top: 40px; font-style: italic;">Confidential Business Report</div>
    </div>';
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_7','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:380','message'=>'Before WriteHTML cover','data'=>['coverHtmlLength'=>strlen($cover_html)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion
    
    // Write cover page first (without header)
    $mpdf->WriteHTML($cover_html);
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_8','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:385','message'=>'After WriteHTML cover, before SetHTMLHeader','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion
    
    // Now set header for subsequent pages
    $mpdf->SetHTMLHeader(generatePDFHeader($mpdf));
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_9','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:390','message'=>'After SetHTMLHeader','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion
    
    // Continue with rest of content
    
    // Executive Summary
    $html .= '<h2>Executive Summary</h2>';
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
    
    $html .= '<h3>Period Overview</h3>';
    $html .= '<div class="metric-row"><span class="metric-label">Report Period:</span> <span class="metric-value">' . htmlspecialchars($period_display) . '</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Total Bookings:</span> <span class="metric-value">' . number_format($metrics['total_bookings']) . '</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Completion Rate:</span> <span class="metric-value">' . number_format($completion_rate, 1) . '%</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Average Booking Value:</span> <span class="metric-value">RM ' . number_format($average_booking_value, 2) . '</span></div>';
    
    // Financial Metrics
        $html .= '<pagebreak />';
        $html .= '<h2>Financial Metrics</h2>';
    
    $html .= '<h3>Revenue Breakdown</h3>';
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
    
    $html .= '<h3>Booking Status Summary</h3>';
    $html .= '<div class="metric-row"><span class="metric-label">Completed:</span> <span class="metric-value">' . number_format($metrics['completed_bookings']) . ' bookings (' . number_format(($metrics['completed_bookings'] / max($metrics['total_bookings'], 1)) * 100, 1) . '%)</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Confirmed:</span> <span class="metric-value">' . number_format($metrics['total_bookings'] - $metrics['completed_bookings'] - $metrics['cancelled_bookings'] - $metrics['no_show_bookings']) . ' bookings</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Cancelled:</span> <span class="metric-value">' . number_format($metrics['cancelled_bookings']) . ' bookings (' . number_format(($metrics['cancelled_bookings'] / max($metrics['total_bookings'], 1)) * 100, 1) . '%)</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">No-Show:</span> <span class="metric-value">' . number_format($metrics['no_show_bookings']) . ' bookings (' . number_format(($metrics['no_show_bookings'] / max($metrics['total_bookings'], 1)) * 100, 1) . '%)</span></div>';
    
    // Staff Performance Leaderboard
    if (!empty($staff_performance)) {
        $html .= '<pagebreak />';
        $html .= '<h2>Staff Performance Leaderboard</h2>';
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
    }
    
    // Popular Services - Horizontal Bar Chart Design
    if (!empty($popular_services)) {
        $html .= '<pagebreak />';
        $html .= '<h2>Popular Services</h2>';
        
        // Find max booking count for scaling bars
        $max_bookings = 0;
        foreach ($popular_services as $service) {
            if ($service['bookings'] > $max_bookings) {
                $max_bookings = $service['bookings'];
            }
        }
        
        $html .= '<div style="margin-top: 20px;">';
        
        foreach ($popular_services as $service) {
            // Calculate bar width percentage (max 100%)
            $bar_width_percent = $max_bookings > 0 ? ($service['bookings'] / $max_bookings) * 100 : 0;
            
            $html .= '<table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">';
            $html .= '<tr>';
            
            // Service name (left column - 35%)
            $html .= '<td style="width: 35%; vertical-align: middle; padding-right: 15px; padding-bottom: 12px;">';
            $html .= '<span style="font-size: 10pt; color: #2d2d2d;">' . htmlspecialchars($service['name']) . '</span>';
            $html .= '</td>';
            
            // Bar container (middle column - 50%)
            $html .= '<td style="width: 50%; vertical-align: middle; padding-right: 15px; padding-bottom: 12px;">';
            $html .= '<div style="width: 100%; height: 24px; background-color: #f0f0f0; border-radius: 3px; position: relative; overflow: hidden;">';
            $html .= '<div style="width: ' . number_format($bar_width_percent, 2) . '%; height: 100%; background-color: #D4A574; border-radius: 3px;"></div>';
            $html .= '</div>';
            $html .= '</td>';
            
            // Count (right column - 15%)
            $html .= '<td style="width: 15%; vertical-align: middle; text-align: right; padding-bottom: 12px;">';
            $html .= '<span style="font-size: 10pt; color: #2d2d2d; font-weight: bold;">' . number_format($service['bookings']) . '</span>';
            $html .= '</td>';
            
            $html .= '</tr>';
            $html .= '</table>';
        }
        
        $html .= '</div>';
    }
    
    // Daily Breakdown (if not too many days)
    if (count($daily_breakdown) <= 30 && !empty($daily_breakdown)) {
        $html .= '<pagebreak />';
        $html .= '<h2>Daily Booking Trends</h2>';
        $html .= '<table>
            <tr>
                <th style="width: 30%;">Date</th>
                <th style="width: 20%;" class="text-center">Total Bookings</th>
                <th style="width: 20%;" class="text-center">Completed</th>
                <th style="width: 30%;" class="text-right">Revenue</th>
            </tr>';
        
        foreach ($daily_breakdown as $day) {
            $date_display = date('M j, Y', strtotime($day['date']));
            $html .= '<tr>
                <td>' . htmlspecialchars($date_display) . '</td>
                <td class="text-center">' . number_format($day['bookings']) . '</td>
                <td class="text-center">' . number_format($day['completed']) . '</td>
                <td class="text-right">RM ' . number_format($day['revenue'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</table>';
    }
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_10','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:535','message'=>'Before WriteHTML main content','data'=>['htmlLength'=>strlen($html)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion
    
    // Write HTML to mPDF
    $mpdf->WriteHTML($html);
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_11','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:539','message'=>'After WriteHTML, before Output','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H3'])."\n", FILE_APPEND);
    // #endregion
    
    // Output PDF
    ob_end_clean();
    $filename = 'Business_Analytics_Report_' . date('Y-m-d', strtotime($start_date)) . '_to_' . date('Y-m-d', strtotime($end_date)) . '.pdf';
    
    // #region agent log
    $destinationClassExists = class_exists('\Mpdf\Output\Destination');
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_12','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:545','message'=>'Before Output call','data'=>['destinationClassExists'=>$destinationClassExists,'filename'=>$filename],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H2'])."\n", FILE_APPEND);
    // #endregion
    
    // Use the correct namespace for Destination
    if ($destinationClassExists) {
        // #region agent log
        file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_13','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:550','message'=>'Using Destination::DOWNLOAD','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H2'])."\n", FILE_APPEND);
        // #endregion
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
    } else {
        // #region agent log
        file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_14','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:553','message'=>'Using fallback D parameter','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H2'])."\n", FILE_APPEND);
        // #endregion
        // Fallback for older mPDF versions
        $mpdf->Output($filename, 'D');
    }
    
} catch (Exception $e) {
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_15','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:560','message'=>'Exception caught','data'=>['exceptionMessage'=>$e->getMessage(),'exceptionFile'=>$e->getFile(),'exceptionLine'=>$e->getLine(),'exceptionTrace'=>substr($e->getTraceAsString(),0,500)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1,H3,H5'])."\n", FILE_APPEND);
    // #endregion
    
    ob_end_clean();
    error_log("Business PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Error generating report: ' . $e->getMessage());
} catch (Error $e) {
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_16','timestamp'=>time()*1000,'location'=>'export_business_pdf.php:570','message'=>'Fatal Error caught','data'=>['errorMessage'=>$e->getMessage(),'errorFile'=>$e->getFile(),'errorLine'=>$e->getLine(),'errorTrace'=>substr($e->getTraceAsString(),0,500)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H5'])."\n", FILE_APPEND);
    // #endregion
    
    ob_end_clean();
    error_log("Business PDF Export Fatal Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Error generating report: ' . $e->getMessage());
}
?>


