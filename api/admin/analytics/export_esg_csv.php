<?php
// Start output buffering to catch any accidental output
ob_start();

// Disable error display (errors will be logged, not printed)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);

// Use admin-specific session name to match auth_check.php
session_name('admin_session');
session_start();

// Include database connection and authentication
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';

// Clear any output that might have been generated
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

    // Card 1: Total Active Staff
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Staff WHERE is_active = 1");
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
        WHERE status IN ('confirmed', 'completed') 
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

    $conn->close();

    // Clear output buffer before generating CSV
    ob_end_clean();
    
    // Set headers for CSV download (Excel-compatible)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ESG_Report_' . $selected_year . '_' . $selected_month . '.csv"');
    
    // Add BOM for UTF-8 (helps Excel display special characters correctly)
    echo "\xEF\xBB\xBF";
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Company Information Section (aligned with PDF)
    fputcsv($output, ['Lumiere Beauty Salon']);
    fputcsv($output, ['ESG Sustainability Report']);
    fputcsv($output, ['Report Period:', $current_month_display]);
    fputcsv($output, ['Generated on:', $generated_date]);
    fputcsv($output, ['Company Registration:', 'SSM: SA0123456-A']);
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Company Information:']);
    fputcsv($output, ['Address:', 'No. 10, Ground Floor Block B, Phase 2, Jln Lintas, Kolam Centre']);
    fputcsv($output, ['City:', '88300 Kota Kinabalu, Sabah']);
    fputcsv($output, ['Email:', 'lumierebeautysalon2022@gmail.com']);
    fputcsv($output, ['Tel:', '012 345 6789 / 088 978 8977']);
    fputcsv($output, []); // Empty row
    
    // Key Metrics Section
    fputcsv($output, ['KEY METRICS']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Active Staff', number_format($total_active_staff, 0)]);
    fputcsv($output, ['Services Delivered', number_format($services_delivered, 0)]);
    fputcsv($output, ['Scheduled Hours', number_format($total_scheduled_hours, 2) . 'h']);
    fputcsv($output, ['Booked Hours', number_format($total_booked_hours, 2) . 'h']);
    fputcsv($output, ['Idle Hours', number_format($idle_hours, 2) . 'h']);
    fputcsv($output, ['Utilization Rate', number_format($global_utilization_rate, 2) . '%']);
    fputcsv($output, []); // Empty row
    
    // Staff Utilization Breakdown
    fputcsv($output, ['STAFF UTILIZATION BREAKDOWN']);
    fputcsv($output, ['Staff Member', 'Scheduled (h)', 'Booked (h)', 'Idle (h)', 'Utilization (%)']);
    
    foreach ($staff_breakdown as $staff) {
        fputcsv($output, [
            $staff['name'],
            number_format($staff['scheduled'], 2),
            number_format($staff['booked'], 2),
            number_format($staff['idle'], 2),
            number_format($staff['utilization'], 2)
        ]);
    }
    
    fclose($output);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    error_log("CSV Export Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Error generating report. Please try again.');
}
?>









