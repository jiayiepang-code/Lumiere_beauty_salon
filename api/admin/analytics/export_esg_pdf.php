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

    // Generate PDF using TCPDF
    // Check if TCPDF is available, otherwise use FPDF
    $use_tcpdf = false;
    $tcpdf_path = __DIR__ . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
        $use_tcpdf = true;
    } else {
        // Try alternative TCPDF location
        $tcpdf_path2 = __DIR__ . '/../../../vendor/tcpdf/tcpdf.php';
        if (file_exists($tcpdf_path2)) {
            require_once $tcpdf_path2;
            $use_tcpdf = true;
        }
    }

    // Clear output buffer before generating PDF
    ob_end_clean();
    
    if ($use_tcpdf) {
        // Use TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Lumière Beauty Salon');
        $pdf->SetAuthor('Lumière Beauty Salon');
        $pdf->SetTitle('ESG Sustainability Report');
        $pdf->SetSubject('Sustainability Analytics');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        // Add a page
        $pdf->AddPage();
        
        // Logo path
        $logo_path = __DIR__ . '/../../../images/16.png';
        $logo_height = 30;
        
        // Header with logo and company info
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, 15, 15, 0, $logo_height, 'PNG', '', '', false, 300, '', false, false, 0);
        }
        
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetY(20);
        $pdf->Cell(0, 10, 'Lumière Beauty Salon', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'No. 10, Ground Floor Block B, Phase 2, Jln Lintas, Kolam Centre', 0, 1, 'L');
        $pdf->SetX(15);
        $pdf->Cell(0, 5, '88300 Kota Kinabalu, Sabah', 0, 1, 'L');
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'Email: Lumiere@gmail.com | Tel: 012 345 6789 / 088 978 8977', 0, 1, 'L');
        
        // Line separator
        $pdf->Ln(5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(10);
        
        // Report title
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'ESG Sustainability Report', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Month/Year and generated date
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, $current_month_display, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated on: ' . $generated_date, 0, 1, 'C');
        $pdf->Ln(10);
        
        // Key Metrics section
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Key Metrics', 0, 1, 'L');
        $pdf->Ln(3);
        
        $pdf->SetFont('helvetica', '', 11);
        $metrics = [
            'Active Staff' => number_format($total_active_staff, 0),
            'Services Delivered' => number_format($services_delivered, 0),
            'Scheduled Hours' => number_format($total_scheduled_hours, 2) . 'h',
            'Booked Hours' => number_format($total_booked_hours, 2) . 'h',
            'Idle Hours' => number_format($idle_hours, 2) . 'h',
            'Utilization Rate' => number_format($global_utilization_rate, 2) . '%'
        ];
        
        foreach ($metrics as $label => $value) {
            $pdf->SetX(20);
            $pdf->Cell(80, 6, '• ' . $label . ':', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, $value, 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 11);
        }
        
        $pdf->Ln(10);
        
        // Staff Utilization Breakdown
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Staff Utilization Breakdown', 0, 1, 'L');
        $pdf->Ln(3);
        
        if (!empty($staff_breakdown)) {
            // Table header
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(60, 8, 'Staff Member', 1, 0, 'L', true);
            $pdf->Cell(30, 8, 'Scheduled (h)', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Booked (h)', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Idle (h)', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'Utilization', 1, 1, 'C', true);
            
            // Table rows
            $pdf->SetFont('helvetica', '', 9);
            $fill = false;
            foreach ($staff_breakdown as $staff) {
                $pdf->SetFillColor(250, 250, 250);
                $pdf->Cell(60, 7, $staff['name'], 1, 0, 'L', $fill);
                $pdf->Cell(30, 7, number_format($staff['scheduled'], 2), 1, 0, 'C', $fill);
                $pdf->Cell(30, 7, number_format($staff['booked'], 2), 1, 0, 'C', $fill);
                $pdf->Cell(30, 7, number_format($staff['idle'], 2), 1, 0, 'C', $fill);
                $pdf->Cell(35, 7, number_format($staff['utilization'], 2) . '%', 1, 1, 'C', $fill);
                $fill = !$fill;
            }
        } else {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 8, 'No staff data available for selected period.', 0, 1, 'L');
        }
        
        // Footer
        $pdf->SetY(-25);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Page ' . $pdf->getAliasNumPage() . ' / ' . $pdf->getAliasNbPages(), 0, 0, 'C');
        
        // Output PDF
        $filename = 'ESG_Report_' . $selected_year . '_' . $selected_month . '.pdf';
        $pdf->Output($filename, 'D'); // 'D' = download
        
    } else {
        // Fallback: Generate simple text-based report if PDF library not available
        ob_end_clean();
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="ESG_Report_' . $selected_year . '_' . $selected_month . '.txt"');
        
        echo "LUMIÈRE BEAUTY SALON\n";
        echo "ESG Sustainability Report\n";
        echo str_repeat("=", 60) . "\n\n";
        echo "Report Period: " . $current_month_display . "\n";
        echo "Generated on: " . $generated_date . "\n\n";
        echo "Company Information:\n";
        echo "No. 10, Ground Floor Block B, Phase 2, Jln Lintas, Kolam Centre\n";
        echo "88300 Kota Kinabalu, Sabah\n";
        echo "Email: Lumiere@gmail.com\n";
        echo "Tel: 012 345 6789 / 088 978 8977\n\n";
        echo str_repeat("-", 60) . "\n\n";
        echo "KEY METRICS\n";
        echo str_repeat("-", 60) . "\n";
        echo "Active Staff: " . $total_active_staff . "\n";
        echo "Services Delivered: " . $services_delivered . "\n";
        echo "Scheduled Hours: " . number_format($total_scheduled_hours, 2) . "h\n";
        echo "Booked Hours: " . number_format($total_booked_hours, 2) . "h\n";
        echo "Idle Hours: " . number_format($idle_hours, 2) . "h\n";
        echo "Utilization Rate: " . number_format($global_utilization_rate, 2) . "%\n\n";
        echo str_repeat("-", 60) . "\n\n";
        echo "STAFF UTILIZATION BREAKDOWN\n";
        echo str_repeat("-", 60) . "\n";
        printf("%-30s %12s %12s %12s %12s\n", "Staff Member", "Scheduled (h)", "Booked (h)", "Idle (h)", "Utilization");
        echo str_repeat("-", 60) . "\n";
        foreach ($staff_breakdown as $staff) {
            printf("%-30s %12.2f %12.2f %12.2f %11.2f%%\n", 
                $staff['name'], 
                $staff['scheduled'], 
                $staff['booked'], 
                $staff['idle'], 
                $staff['utilization']
            );
        }
    }

} catch (Exception $e) {
    ob_end_clean();
    error_log("PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Error generating report. Please try again.');
}
?>

