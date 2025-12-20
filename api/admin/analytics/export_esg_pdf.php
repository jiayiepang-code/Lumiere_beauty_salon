<?php
/**
 * ESG Sustainability PDF Export
 * Generates professional PDF report for ESG sustainability analytics
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
$selected_month = isset($_GET['month']) ? trim($_GET['month']) : date('m');
$raw_year = isset($_GET['year']) ? trim($_GET['year']) : '';
$selected_year = is_numeric($raw_year) ? (int)$raw_year : (int)date('Y');

// Validate month: must be numeric 01-12
if (empty($selected_month) || !is_numeric($selected_month) || $selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m');
} else {
    $selected_month = str_pad((int)$selected_month, 2, '0', STR_PAD_LEFT);
}

// Validate year: must be between 2020-2030
if ($selected_year < 2020 || $selected_year > 2030) {
    $selected_year = (int)date('Y');
}

// Month names for display
$month_names = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

$current_month_display = $month_names[$selected_month] . ' ' . $selected_year;
$generated_date = date('F j, Y g:i A');

try {
    $conn = getDBConnection();

    // Initialize variables
    $total_active_staff = 0;
    $services_delivered = 0;
    $total_scheduled_hours = 0.00;
    $total_booked_hours = 0.00;
    $idle_hours = 0.00;
    $global_utilization_rate = 0.00;
    $staff_breakdown = [];
    $top_performer_message = '';
    $lowest_performer_message = '';
    $smart_suggestion = '';

    // Card 1: Total Active Staff
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Staff WHERE is_active = 1 AND role != 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_active_staff = (int)$row['count'];
    }
    $stmt->close();

    // Card 2: Services Delivered
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM Booking 
        WHERE status = 'completed' 
        AND MONTH(booking_date) = ? 
        AND YEAR(booking_date) = ?
    ");
    $stmt->bind_param("si", $selected_month, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $services_delivered = (int)$row['count'];
    }
    $stmt->close();

    // Card 3: Total Scheduled Hours
    $stmt = $conn->prepare("
        SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))) / 3600 as total_hours
        FROM Staff_Schedule 
        WHERE MONTH(work_date) = ? 
        AND YEAR(work_date) = ?
    ");
    $stmt->bind_param("si", $selected_month, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_scheduled_hours = (float)($row['total_hours'] ?? 0.00);
    }
    $stmt->close();

    // Card 4: Booked Hours
    $stmt = $conn->prepare("
        SELECT SUM((quoted_duration_minutes + quoted_cleanup_minutes)) / 60 as total_hours
        FROM Booking_Service bs
        JOIN Booking b ON bs.booking_id = b.booking_id
        WHERE b.status IN ('completed', 'confirmed')
        AND MONTH(b.booking_date) = ? 
        AND YEAR(b.booking_date) = ?
    ");
    $stmt->bind_param("si", $selected_month, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_booked_hours = (float)($row['total_hours'] ?? 0.00);
    }
    $stmt->close();

    // Card 5: Idle Hours
    $idle_hours = $total_scheduled_hours - $total_booked_hours;
    if ($idle_hours < 0) {
        $idle_hours = 0.00;
    }

    // Card 6: Global Utilization Rate
    if ($total_scheduled_hours > 0) {
        $global_utilization_rate = ($total_booked_hours / $total_scheduled_hours) * 100;
    } else {
        $global_utilization_rate = 0.00;
    }

    // Staff Breakdown Table
    $stmt = $conn->prepare("
        SELECT 
            st.staff_email,
            st.first_name,
            st.last_name,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, ss.start_time, ss.end_time) / 60), 0) as scheduled_hours,
            COALESCE(SUM((bs.quoted_duration_minutes + bs.quoted_cleanup_minutes) / 60), 0) as booked_hours
        FROM Staff st
        LEFT JOIN Staff_Schedule ss ON st.staff_email = ss.staff_email 
            AND MONTH(ss.work_date) = ? 
            AND YEAR(ss.work_date) = ?
        LEFT JOIN Booking_Service bs ON st.staff_email = bs.staff_email
        LEFT JOIN Booking b ON bs.booking_id = b.booking_id 
            AND MONTH(b.booking_date) = ? 
            AND YEAR(b.booking_date) = ?
            AND b.status IN ('completed', 'confirmed')
        WHERE st.is_active = 1 AND st.role != 'admin'
        GROUP BY st.staff_email, st.first_name, st.last_name
    ");
    $stmt->bind_param("sisi", $selected_month, $selected_year, $selected_month, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $scheduled = (float)$row['scheduled_hours'];
        $booked = (float)$row['booked_hours'];
        $idle = $scheduled - $booked;
        if ($idle < 0) {
            $idle = 0.00;
        }
        $utilization = ($scheduled > 0) ? ($booked / $scheduled) * 100 : 0;
        
        $staff_breakdown[] = [
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'scheduled' => $scheduled,
            'booked' => $booked,
            'idle' => $idle,
            'utilization' => $utilization
        ];
    }
    $stmt->close();

    // Sort by utilization descending
    usort($staff_breakdown, function($a, $b) {
        return $b['utilization'] <=> $a['utilization'];
    });

    // Optimization Insights
    foreach ($staff_breakdown as $staff) {
        if ($staff['scheduled'] > 0) {
            $top_performer_message = $staff['name'] . " maintains " . number_format($staff['utilization'], 2) . "% utilization. Consider prioritizing high-value bookings for them.";
            break;
        }
    }

    $lowest_performer = null;
    for ($i = count($staff_breakdown) - 1; $i >= 0; $i--) {
        if ($staff_breakdown[$i]['scheduled'] > 0) {
            $lowest_performer = $staff_breakdown[$i];
            break;
        }
    }
    if ($lowest_performer) {
        $lowest_performer_message = $lowest_performer['name'] . " has " . number_format($lowest_performer['idle'], 2) . " idle hours. Consider adjusting their roster or running a promo for their specialty.";
    }

    if ($global_utilization_rate < 50) {
        $smart_suggestion = "Consider reducing shifts or cross-training staff.";
    } elseif ($global_utilization_rate >= 50 && $global_utilization_rate <= 90) {
        $smart_suggestion = "Balanced efficiency. Maintain current scheduling.";
    } else {
        $smart_suggestion = "High utilization. Consider hiring help to prevent burnout.";
    }

    // Staff Schedule Summary
    $schedule_summary = [];
    foreach ($staff_breakdown as $staff) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT work_date) as days_worked,
                COUNT(DISTINCT CASE WHEN status = 'leave' THEN work_date END) as leave_days
            FROM Staff_Schedule
            WHERE staff_email = (
                SELECT staff_email FROM Staff 
                WHERE CONCAT(first_name, ' ', last_name) = ?
                LIMIT 1
            )
            AND MONTH(work_date) = ?
            AND YEAR(work_date) = ?
        ");
        $stmt->bind_param("sii", $staff['name'], $selected_month, $selected_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule_data = $result->fetch_assoc();
        $stmt->close();
        
        $days_worked = (int)($schedule_data['days_worked'] ?? 0);
        $leave_days = (int)($schedule_data['leave_days'] ?? 0);
        $avg_hours = $days_worked > 0 ? $staff['scheduled'] / $days_worked : 0;
        
        $schedule_summary[] = [
            'name' => $staff['name'],
            'scheduled' => $staff['scheduled'],
            'days_worked' => $days_worked,
            'leave_days' => $leave_days,
            'avg_hours' => $avg_hours
        ];
    }

    $conn->close();

    // Initialize mPDF
    $mpdf = initMPDF();
    
    if (!$mpdf) {
        throw new Exception('mPDF library not available. Please ensure mPDF is installed in vendor/mpdf/');
    }

    // Set PDF metadata
    $mpdf->SetTitle('ESG Sustainability Report - ' . $current_month_display);
    $mpdf->SetAuthor('Lumière Beauty Salon');
    $mpdf->SetSubject('ESG Sustainability Report');
    
    // Set footer (applies to all pages)
    $mpdf->SetHTMLFooter(generatePDFFooter('ESG Sustainability Report'));

    // Generate HTML content
    $html = '<style>
        body {
            font-family: dejavusans, sans-serif;
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
            border-bottom: 2px solid #4CAF50;
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
            padding: 100px 0;
        }
        .cover-title {
            font-size: 28pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 30px;
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
            color: #4CAF50;
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
        .insight-box {
            margin: 15px 0;
            padding: 15px;
            border-left: 4px solid #4CAF50;
            background-color: #f9f9f9;
        }
        .insight-title {
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        .page-break {
            page-break-before: always;
        }
        .utilization-bar {
            width: 100%;
            height: 20px;
            background-color: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin: 5px 0;
        }
        .utilization-fill {
            height: 100%;
            background-color: #4CAF50;
        }
    </style>';
    
    // Cover Page (no header on first page)
    $cover_html = '<div class="cover-page">
        <div class="cover-title">ESG SUSTAINABILITY REPORT</div>
        <div style="font-size: 16pt; margin: 30px 0; color: #666;">' . htmlspecialchars($current_month_display) . '</div>
        <div class="cover-subtitle">Operational Efficiency & Staff Utilization Analysis</div>
        <div class="cover-subtitle" style="margin-top: 20px;">Generated: ' . htmlspecialchars($generated_date) . '</div>
    </div>';
    
    // Write cover page first (without header)
    $mpdf->WriteHTML($cover_html);
    
    // Now set header for subsequent pages
    $mpdf->SetHTMLHeader(generatePDFHeader($mpdf));
    
    // Continue with rest of content
    
    // Executive Summary
    $html .= '<h2>Executive Summary</h2>';
    $html .= '<div class="kpi-grid">
        <div class="kpi-item">
            <div class="kpi-label">Active Staff</div>
            <div class="kpi-value">' . number_format($total_active_staff) . '</div>
        </div>
        <div class="kpi-item">
            <div class="kpi-label">Services Delivered</div>
            <div class="kpi-value">' . number_format($services_delivered) . '</div>
        </div>
        <div class="kpi-item">
            <div class="kpi-label">Utilization Rate</div>
            <div class="kpi-value">' . number_format($global_utilization_rate, 1) . '%</div>
        </div>
    </div>';
    
    $html .= '<h3>Operational Overview</h3>';
    $html .= '<div class="metric-row"><span class="metric-label">Total Scheduled Hours:</span> <span class="metric-value">' . number_format($total_scheduled_hours, 2) . 'h</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Booked Hours:</span> <span class="metric-value">' . number_format($total_booked_hours, 2) . 'h</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Idle Hours:</span> <span class="metric-value">' . number_format($idle_hours, 2) . 'h</span></div>';
    $html .= '<div class="metric-row"><span class="metric-label">Global Utilization Rate:</span> <span class="metric-value">' . number_format($global_utilization_rate, 2) . '%</span></div>';
    
    // Operational Efficiency Metrics
    $html .= '<pagebreak />';
    $html .= '<h2>Operational Efficiency Metrics</h2>';
    
    $html .= '<table>
        <tr>
            <th style="width: 60%;">Metric</th>
            <th style="width: 40%;" class="text-right">Value</th>
        </tr>
        <tr>
            <td>Active Staff</td>
            <td class="text-right">' . number_format($total_active_staff) . '</td>
        </tr>
        <tr>
            <td>Services Delivered</td>
            <td class="text-right">' . number_format($services_delivered) . '</td>
        </tr>
        <tr>
            <td>Scheduled Hours</td>
            <td class="text-right">' . number_format($total_scheduled_hours, 2) . 'h</td>
        </tr>
        <tr>
            <td>Booked Hours</td>
            <td class="text-right">' . number_format($total_booked_hours, 2) . 'h</td>
        </tr>
        <tr>
            <td>Idle Hours</td>
            <td class="text-right">' . number_format($idle_hours, 2) . 'h</td>
        </tr>
        <tr>
            <td>Utilization Rate</td>
            <td class="text-right">' . number_format($global_utilization_rate, 2) . '%</td>
        </tr>
    </table>';
    
    $html .= '<h3>Efficiency Analysis</h3>';
    if ($global_utilization_rate >= 75) {
        $html .= '<div class="insight-box">
            <div class="insight-title">✓ Optimal Efficiency</div>
            <p>Utilization above 75% indicates optimal efficiency. Idle hours are within acceptable range. Staff scheduling is well-aligned with demand.</p>
        </div>';
    } elseif ($global_utilization_rate >= 50) {
        $html .= '<div class="insight-box">
            <div class="insight-title">✓ Balanced Efficiency</div>
            <p>Utilization between 50-75% shows balanced operations. Current scheduling appears appropriate for demand levels.</p>
        </div>';
    } else {
        $html .= '<div class="insight-box">
            <div class="insight-title">⚠ Optimization Opportunity</div>
            <p>Utilization below 50% suggests potential for optimization. Consider adjusting staff schedules or implementing demand generation strategies.</p>
        </div>';
    }
        
    // Staff Utilization Breakdown
    if (!empty($staff_breakdown)) {
        $html .= '<pagebreak />';
        $html .= '<h2>Staff Utilization Breakdown</h2>';
        $html .= '<table>
            <tr>
                <th style="width: 30%;">Staff Member</th>
                <th style="width: 20%;" class="text-right">Scheduled (h)</th>
                <th style="width: 20%;" class="text-right">Booked (h)</th>
                <th style="width: 15%;" class="text-right">Idle (h)</th>
                <th style="width: 15%;" class="text-right">Utilization</th>
            </tr>';
        
            foreach ($staff_breakdown as $staff) {
            $util_color = '#4CAF50';
            if ($staff['utilization'] < 60) {
                $util_color = '#FF9800';
            } elseif ($staff['utilization'] > 80) {
                $util_color = '#2196F3';
            }
            
            $html .= '<tr>
                <td>' . htmlspecialchars($staff['name']) . '</td>
                <td class="text-right">' . number_format($staff['scheduled'], 2) . '</td>
                <td class="text-right">' . number_format($staff['booked'], 2) . '</td>
                <td class="text-right">' . number_format($staff['idle'], 2) . '</td>
                <td class="text-right">' . number_format($staff['utilization'], 2) . '%</td>
            </tr>';
        }
        
        $html .= '</table>';
    }
    
    // Optimization Insights
    if ($top_performer_message || $lowest_performer_message || $smart_suggestion) {
        $html .= '<pagebreak />';
        $html .= '<h2>Optimization Insights</h2>';
        
        if ($top_performer_message) {
            $html .= '<div class="insight-box" style="border-left-color: #4CAF50;">
                <div class="insight-title" style="color: #4CAF50;">✓ Efficiency Win</div>
                <p>' . htmlspecialchars($top_performer_message) . '</p>
            </div>';
        }
        
        if ($lowest_performer_message) {
            $html .= '<div class="insight-box" style="border-left-color: #FF9800;">
                <div class="insight-title" style="color: #FF9800;">⚠ Optimization Opportunity</div>
                <p>' . htmlspecialchars($lowest_performer_message) . '</p>
            </div>';
        }
        
        if ($smart_suggestion) {
            $html .= '<div class="insight-box" style="border-left-color: #2196F3;">
                <div class="insight-title" style="color: #2196F3;">💡 Recommendation</div>
                <p>' . htmlspecialchars($smart_suggestion) . '</p>
            </div>';
        }
    }
    
    // Staff Work Schedule Summary
    if (!empty($schedule_summary)) {
        $html .= '<pagebreak />';
        $html .= '<h2>Staff Work Schedule Summary</h2>';
        $html .= '<table>
            <tr>
                <th style="width: 30%;">Staff Member</th>
                <th style="width: 20%;" class="text-right">Total Hours</th>
                <th style="width: 15%;" class="text-center">Days Worked</th>
                <th style="width: 15%;" class="text-center">Leave Days</th>
                <th style="width: 20%;" class="text-right">Avg Hours/Day</th>
            </tr>';
        
        foreach ($schedule_summary as $summary) {
            $html .= '<tr>
                <td>' . htmlspecialchars($summary['name']) . '</td>
                <td class="text-right">' . number_format($summary['scheduled'], 2) . 'h</td>
                <td class="text-center">' . number_format($summary['days_worked']) . '</td>
                <td class="text-center">' . number_format($summary['leave_days']) . '</td>
                <td class="text-right">' . number_format($summary['avg_hours'], 2) . 'h</td>
            </tr>';
        }
        
        $html .= '</table>';
    }
    
    // Write HTML to mPDF
    $mpdf->WriteHTML($html);
    
    // Output PDF
    ob_end_clean();
    $filename = 'ESG_Report_' . $selected_year . '_' . $selected_month . '.pdf';
    
    // Use the correct namespace for Destination
    if (class_exists('\Mpdf\Output\Destination')) {
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
    } else {
        // Fallback for older mPDF versions
        $mpdf->Output($filename, 'D');
    }
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("ESG PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Error generating report: ' . $e->getMessage());
} catch (Error $e) {
    
    ob_end_clean();
    error_log("ESG PDF Export Fatal Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Error generating report: ' . $e->getMessage());
}
?>
