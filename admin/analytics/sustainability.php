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

// ========== MONTH/YEAR FILTER LOGIC ==========
$selected_month = isset($_GET['month']) ? trim($_GET['month']) : '';
$raw_year = isset($_GET['year']) ? trim($_GET['year']) : '';
$selected_year = is_numeric($raw_year) ? (int)$raw_year : null;
$available_years = [];

// Validate month: must be numeric 01-12
if (empty($selected_month) || !is_numeric($selected_month) || $selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m'); // Current month
} else {
    $selected_month = str_pad((int)$selected_month, 2, '0', STR_PAD_LEFT); // Format as 01-12
}

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
$error_message = '';

try {
    $conn = getDBConnection();

    // Build available years from Booking and Staff_Schedule
    $yearQuery = "
        SELECT DISTINCT YEAR(booking_date) AS year FROM Booking WHERE booking_date IS NOT NULL
        UNION
        SELECT DISTINCT YEAR(work_date) AS year FROM Staff_Schedule WHERE work_date IS NOT NULL
        ORDER BY year DESC
    ";
    $stmtYears = $conn->prepare($yearQuery);
    if (!$stmtYears) {
        throw new Exception('Year query failed: ' . $conn->error);
    }
    $stmtYears->execute();
    $yearsResult = $stmtYears->get_result();
    while ($row = $yearsResult->fetch_assoc()) {
        $available_years[] = (int)$row['year'];
    }
    $stmtYears->close();

    if (empty($available_years)) {
        $available_years[] = (int)date('Y');
    }

    $current_year = (int)date('Y');
    $default_year = in_array($current_year, $available_years, true) ? $current_year : $available_years[0];
    if ($selected_year === null || !in_array($selected_year, $available_years, true)) {
        $selected_year = $default_year;
    }

    // ========== CARD 1: TOTAL ACTIVE STAFF ==========
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Staff WHERE is_active = 1 AND role != 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_active_staff = (int)$row['count'];
    }
    $stmt->close();

    // ========== CARD 2: SERVICES DELIVERED ==========
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

    // ========== CARD 3: TOTAL SCHEDULED HOURS (CAPACITY) ==========
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

    // ========== CARD 4: BOOKED HOURS (UTILIZED TIME) ==========
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

    // ========== CARD 5: IDLE HOURS ==========
    $idle_hours = $total_scheduled_hours - $total_booked_hours;
    if ($idle_hours < 0) {
        $idle_hours = 0.00; // Prevent negative values
    }

    // ========== CARD 6: GLOBAL UTILIZATION RATE ==========
    if ($total_scheduled_hours > 0) {
        $global_utilization_rate = ($total_booked_hours / $total_scheduled_hours) * 100;
    } else {
        $global_utilization_rate = 0.00;
    }

    // ========== STAFF BREAKDOWN TABLE ==========
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
            AND b.status = 'completed'
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
            'name' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'),
            'staff_email' => $row['staff_email'],
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

    // ========== OPTIMIZATION INSIGHTS LOGIC ==========
    // Top Performer
    foreach ($staff_breakdown as $staff) {
        if ($staff['scheduled'] > 0) {
            $top_performer_message = $staff['name'] . " maintains " . number_format($staff['utilization'], 2) . "% utilization. Consider prioritizing high-value bookings for them.";
            break;
        }
    }

    // Lowest Performer
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

    // Smart Suggestion
    if ($global_utilization_rate < 50) {
        $smart_suggestion = "Consider reducing shifts or cross-training staff.";
    } elseif ($global_utilization_rate >= 50 && $global_utilization_rate <= 90) {
        $smart_suggestion = "Balanced efficiency. Maintain current scheduling.";
    } else {
        $smart_suggestion = "High utilization. Consider hiring help to prevent burnout.";
    }

    // ========== STAFF SCHEDULE SUMMARY DATA ==========
    $schedule_summary = [];
    foreach ($staff_breakdown as $staff) {
        // Query for schedule details
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT work_date) as days_worked,
                COUNT(DISTINCT CASE WHEN status = 'leave' THEN work_date END) as leave_days
            FROM Staff_Schedule
            WHERE staff_email = ?
            AND MONTH(work_date) = ?
            AND YEAR(work_date) = ?
        ");
        $stmt->bind_param("sii", $staff['staff_email'], $selected_month, $selected_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule_data = $result->fetch_assoc();
        $stmt->close();
        
        $days_worked = (int)($schedule_data['days_worked'] ?? 0);
        $leave_days = (int)($schedule_data['leave_days'] ?? 0);
        $avg_hours = $days_worked > 0 ? $staff['scheduled'] / $days_worked : 0;
        
        $schedule_summary[] = [
            'name' => $staff['name'],
            'email' => $staff['staff_email'],
            'scheduled' => $staff['scheduled'],
            'days_worked' => $days_worked,
            'leave_days' => $leave_days,
            'avg_hours' => $avg_hours
        ];
    }

    $conn->close();

} catch (Exception $e) {
    error_log("Sustainability Analytics Error: " . $e->getMessage());
    $error_message = "An error occurred while loading data. Please try again.";
    if (empty($available_years)) {
        $available_years = [(int)date('Y')];
    }
    if ($conn) {
        $conn->close();
    }
}

// Format month name for display
$month_names = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', 
                '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', 
                '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
$current_month_display = $month_names[$selected_month] . ' ' . $selected_year;

// Determine progress bar color for utilization
$progress_bar_color = 'blue';
if ($global_utilization_rate > 75) {
    $progress_bar_color = 'green';
} elseif ($global_utilization_rate < 60) {
    $progress_bar_color = 'orange';
}

// Flag for red display of idle hours
$idle_hours_red = ($idle_hours > ($total_scheduled_hours * 0.5));

// Include header
include '../includes/header.php';
?>

<!-- Font Awesome v6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="analytics-page">
    <div class="analytics-header">
        <div>
            <h1 class="analytics-title">Sustainability Analytics</h1>
            <p class="analytics-subtitle">Monitor operational efficiency and staff utilization for ESG reporting (Monthly only)</p>
        </div>
        <div class="analytics-header-actions">
            <div class="date-filter-form">
                <div class="filter-group">
                    <label for="month-select" style="font-size: 12px; color: #666; margin-bottom: 4px; display: block;">
                        <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> Month
                    </label>
                    <select name="month" id="month-select" class="form-control">
                        <?php for ($m = 1; $m <= 12; $m++): 
                            $month_val = str_pad($m, 2, '0', STR_PAD_LEFT);
                            $selected = ($month_val == $selected_month) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $month_val; ?>" <?php echo $selected; ?>>
                                <?php echo $month_names[$month_val]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="year-select" style="font-size: 12px; color: #666; margin-bottom: 4px; display: block;">
                        <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> Year
                    </label>
                    <select name="year" id="year-select" class="form-control">
                        <?php foreach ($available_years as $y): 
                            $selected = ($y == $selected_year) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo $selected; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- PDF export button (jsPDF + html2canvas) -->
            <button type="button" id="export-esg-pdf" class="btn btn-export-pdf">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </button>
            <!-- Existing Excel/CSV export button -->
            <button type="button" id="export-esg-csv" class="btn btn-secondary">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <p><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($staff_breakdown) && $total_scheduled_hours == 0): ?>
        <div class="alert alert-info">
            <p>No data available for selected period.</p>
        </div>
    <?php else: ?>

    <!-- ========== TOP GRID - 6 METRICS CARDS ========== -->
    <div class="metrics-grid">
        <!-- Card 1: Total Active Staff -->
        <div class="metric-card">
            <div class="card-header">
                <div class="metric-icon blue-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="card-info-btn" data-tooltip="Total number of active staff members (excluding admin) available for bookings">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $total_active_staff; ?></div>
            <div class="metric-label">Active Staff</div>
            <div class="metric-description">Total staff available for bookings</div>
        </div>

        <!-- Card 2: Services Delivered -->
        <div class="metric-card">
            <div class="card-header">
                <div class="metric-icon green-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div class="card-info-btn" data-tooltip="Count of completed services during the period">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $services_delivered; ?></div>
            <div class="metric-label">Services Delivered</div>
            <div class="metric-description">Completed bookings only</div>
        </div>

        <!-- Card 3: Total Scheduled Hours -->
        <div class="metric-card">
            <div class="card-header">
                <div class="metric-icon amber-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <div class="card-info-btn" data-tooltip="Total capacity hours = Sum of all staff working hours assigned">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($total_scheduled_hours, 2); ?>h</div>
            <div class="metric-label">Scheduled Hours</div>
            <div class="metric-description">Total staff capacity planned</div>
        </div>

        <!-- Card 4: Booked Hours -->
        <div class="metric-card">
            <div class="card-header">
                <div class="metric-icon lime-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="card-info-btn" data-tooltip="Total hours actually used: service duration + cleanup time">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($total_booked_hours, 2); ?>h</div>
            <div class="metric-label">Booked Hours</div>
            <div class="metric-description">Staff hours actually used</div>
        </div>

        <!-- Card 5: Idle Hours -->
        <div class="metric-card">
            <div class="card-header">
                <div class="metric-icon orange-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Idle hours">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="card-info-btn" data-tooltip="Unused capacity = Scheduled Hours - Booked Hours">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value" style="<?php echo $idle_hours_red ? 'color: #F44336;' : ''; ?>">
                <?php echo number_format($idle_hours, 2); ?>h
            </div>
            <div class="metric-label">Idle Hours</div>
            <div class="metric-description">Wasted capacity potential</div>
        </div>

        <!-- Card 6: Utilization Rate -->
        <div class="metric-card">
            <div class="card-header">
                <div class="metric-icon brown-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                        <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                    </svg>
                </div>
                <div class="card-info-btn" data-tooltip="Efficiency % = (Booked Hours / Scheduled Hours) × 100">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($global_utilization_rate, 2); ?>%</div>
            <div class="metric-label">Utilization Rate</div>
            <div class="metric-description">Staff productivity efficiency</div>
            <div class="utilization-bar">
                <div class="utilization-fill utilization-<?php echo $progress_bar_color; ?>" 
                     style="width: <?php echo min($global_utilization_rate, 100); ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- ========== OPTIMIZATION INSIGHTS SECTION ========== -->
    <div class="insights-section">
        <div class="insights-grid">
            <?php if ($top_performer_message): ?>
                <div class="alert alert-success">
                    <div class="alert-header">
                        <i class="fas fa-star"></i>
                        <h4>Efficiency Win</h4>
                    </div>
                    <p><?php echo htmlspecialchars($top_performer_message, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($lowest_performer_message): ?>
                <div class="alert alert-warning">
                    <div class="alert-header">
                        <i class="fas fa-lightbulb"></i>
                        <h4>Optimization Opportunity</h4>
                    </div>
                    <p><?php echo htmlspecialchars($lowest_performer_message, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($smart_suggestion): ?>
                <div class="alert alert-info">
                    <div class="alert-header">
                        <i class="fas fa-chart-line"></i>
                        <h4>Efficiency Analysis</h4>
                    </div>
                    <p><?php echo htmlspecialchars($smart_suggestion, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== MIDDLE SECTION - STAFF BREAKDOWN TABLE ========== -->
    <div class="staff-breakdown-section">
        <h2 class="section-title">Staff Utilization Breakdown</h2>
        <div class="table-responsive">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Scheduled (h)</th>
                        <th>Booked (h)</th>
                        <th>Idle (h)</th>
                        <th>Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff_breakdown)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">No staff data available for selected period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staff_breakdown as $staff): 
                            $idle_red = ($staff['idle'] > 4);
                            $util_color = 'blue';
                            if ($staff['utilization'] > 80) {
                                $util_color = 'green';
                            } elseif ($staff['utilization'] < 60) {
                                $util_color = 'orange';
                            }
                        ?>
                            <tr>
                                <td><?php echo $staff['name']; ?></td>
                                <td><?php echo number_format($staff['scheduled'], 2); ?></td>
                                <td><?php echo number_format($staff['booked'], 2); ?></td>
                                <td style="<?php echo $idle_red ? 'color: #F44336; font-weight: 600;' : ''; ?>">
                                    <?php echo number_format($staff['idle'], 2); ?>
                                </td>
                                <td>
                                    <div class="utilization-cell">
                                        <div class="utilization-bar-small">
                                            <div class="utilization-fill-small utilization-<?php echo $util_color; ?>" 
                                                 style="width: <?php echo min($staff['utilization'], 100); ?>%;"></div>
                                        </div>
                                        <span class="utilization-text"><?php echo number_format($staff['utilization'], 2); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========== STAFF WORK SCHEDULE SECTION ========== -->
    <div class="staff-schedule-section" id="staff-work-schedule">
        <div class="section-header">
            <h2 class="section-title">Staff Work Schedule - <?php echo $current_month_display; ?></h2>
            <?php
            $last_day = date('t', strtotime($selected_year . '-' . $selected_month . '-01'));
            $start_date = $selected_year . '-' . $selected_month . '-01';
            $end_date = $selected_year . '-' . $selected_month . '-' . str_pad($last_day, 2, '0', STR_PAD_LEFT);
            ?>
            <a href="../calendar/master.php?view=month&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>#staffRosterSection" 
               class="btn btn-secondary">
                <i class="fas fa-calendar"></i> View Full Schedule in Master Calendar
            </a>
        </div>
        
        <div class="schedule-summary-table-wrapper">
            <table class="schedule-summary-table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Total Scheduled Hours</th>
                        <th>Days Worked</th>
                        <th>Leave Days</th>
                        <th>Average Hours/Day</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedule_summary)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">No schedule data available for selected period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schedule_summary as $summary): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($summary['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format($summary['scheduled'], 2); ?>h</td>
                            <td><?php echo $summary['days_worked']; ?></td>
                            <td><?php echo $summary['leave_days']; ?></td>
                            <td><?php echo number_format($summary['avg_hours'], 2); ?>h</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

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

.analytics-header-actions {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
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

.date-filter-form {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
}

.date-filter-form .filter-group {
    display: flex;
    flex-direction: column;
}

.date-filter-form .form-control {
    padding: 10px 16px;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    background: white;
    color: #333;
    font-size: 14px;
    cursor: pointer;
    min-width: 140px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.date-filter-form .form-control:hover {
    border-color: #D4A574;
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

/* ========== METRICS GRID ========== */

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
    /* Ensure tooltips inside cards are never clipped by the grid */
    overflow: visible;
}

.metric-card {
    background: white;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #f0f0f0;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
    /* Allow tooltips to extend outside the card without being clipped */
    overflow: visible;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #D4A574, #D4A574);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.metric-card:hover::before {
    transform: scaleX(1);
}

.metric-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
    border-color: #e0e0e0;
}

.card-header {
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    margin-bottom: 8px;
}

.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    transition: all 0.3s ease;
}

.metric-icon svg {
    width: 24px;
    height: 24px;
}

.metric-card:hover .metric-icon {
    transform: scale(1.08) rotate(3deg);
}

.metric-icon.blue-icon {
    background: linear-gradient(135deg, rgba(33, 150, 243, 0.12), rgba(33, 150, 243, 0.04));
    color: #2196F3;
    border: 1.5px solid rgba(33, 150, 243, 0.2);
}

.metric-icon.green-icon {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.12), rgba(76, 175, 80, 0.04));
    color: #4CAF50;
    border: 1.5px solid rgba(76, 175, 80, 0.2);
}

.metric-icon.amber-icon {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.12), rgba(255, 193, 7, 0.04));
    color: #FFC107;
    border: 1.5px solid rgba(255, 193, 7, 0.2);
}

.metric-icon.lime-icon {
    background: linear-gradient(135deg, rgba(139, 195, 74, 0.12), rgba(139, 195, 74, 0.04));
    color: #8BC34A;
    border: 1.5px solid rgba(139, 195, 74, 0.2);
}

.metric-icon.orange-icon {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.12), rgba(255, 152, 0, 0.04));
    color: #FF9800;
    border: 1.5px solid rgba(255, 152, 0, 0.2);
}

.metric-icon.brown-icon {
    background: linear-gradient(135deg, rgba(212, 165, 116, 0.12), rgba(212, 165, 116, 0.04));
    color: #D4A574;
    border: 1.5px solid rgba(212, 165, 116, 0.2);
}

.card-info-btn {
    position: absolute;
    right: 0;
    top: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(212, 165, 116, 0.1);
    color: #D4A574;
    font-size: 13px;
    cursor: help;
    transition: all 0.2s ease;
    border: 1px solid rgba(212, 165, 116, 0.2);
    /* Keep the trigger above card content */
    z-index: 10;
}

.card-info-btn svg {
    width: 14px;
    height: 14px;
}

.card-info-btn:hover {
    background: rgba(212, 165, 116, 0.2);
    transform: scale(1.08);
}

.card-info-btn::after {
    content: attr(data-tooltip);
    position: absolute;
    /* Positioned to the left side of the info icon */
    top: 50%;
    right: 100%;
    margin-right: 8px;
    left: auto;
    transform: translateY(-50%);
    /* Tailwind-like: w-64, bg-gray-900, text-white, shadow-xl */
    width: 256px;
    background: #111827;
    color: #ffffff;
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 0.85rem;
    line-height: 1.5;
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease, transform 0.25s ease;
    /* Ensure tooltip appears above all surrounding content */
    z-index: 9999;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25), 0 10px 10px -5px rgba(0,0,0,0.2);
    text-align: left;
}

.card-info-btn::before {
    content: '';
    position: absolute;
    /* Arrow on the right-center of the tooltip, pointing towards the icon */
    top: 50%;
    right: calc(100% - 1px);
    left: auto;
    transform: translateY(-50%);
    border: 6px solid transparent;
    border-left-color: #111827;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
    z-index: 9999;
}

.card-info-btn:hover::after,
.card-info-btn:hover::before {
    opacity: 1;
}

.metric-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1;
}

.metric-label {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    text-transform: capitalize;
    letter-spacing: 0;
    margin-top: 4px;
}

.metric-description {
    font-size: 0.85rem;
    color: #999;
    line-height: 1.4;
    margin-top: 2px;
}

.utilization-bar {
    width: 100%;
    height: 6px;
    background-color: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 8px;
}

.utilization-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 1s ease-out;
}

.utilization-fill.utilization-green {
    background: linear-gradient(90deg, #4CAF50, #66BB6A);
}

.utilization-fill.utilization-orange {
    background: linear-gradient(90deg, #FF9800, #FFB74D);
}

.utilization-fill.utilization-blue {
    background: linear-gradient(90deg, #2196F3, #64B5F6);
}

/* ========== STAFF BREAKDOWN SECTION ========== */
.staff-breakdown-section {
    margin-bottom: 32px;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d2d2d;
    margin-bottom: 20px;
}

.table-responsive {
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #f0f0f0;
}

.staff-table {
    width: 100%;
    border-collapse: collapse;
}

.staff-table thead {
    background: #fafafa;
}

.staff-table th {
    padding: 14px 24px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #f0f0f0;
}

.staff-table td {
    padding: 16px 24px;
    font-size: 14px;
    color: #333;
    border-bottom: 1px solid #f5f5f5;
}

.staff-table tbody tr:hover {
    background: #fafafa;
}

.staff-table tbody tr:last-child td {
    border-bottom: none;
}

.utilization-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.utilization-bar-small {
    flex: 1;
    height: 6px;
    background-color: #e0e0e0;
    border-radius: 3px;
    overflow: hidden;
}

.utilization-fill-small {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease-out;
}

.utilization-fill-small.utilization-green {
    background: #4CAF50;
}

.utilization-fill-small.utilization-orange {
    background: #FF9800;
}

.utilization-fill-small.utilization-blue {
    background: #2196F3;
}

.utilization-text {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    min-width: 50px;
    text-align: right;
}

/* ========== INSIGHTS SECTION ========== */
.insights-section {
    margin-bottom: 32px;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

@keyframes slideInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert {
    padding: 18px 20px;
    border-radius: 12px;
    border: 1.5px solid;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    animation: slideInUp 0.35s ease-out;
}

.alert-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.alert-header i {
    font-size: 16px;
    flex-shrink: 0;
}

.alert h4 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: inherit;
}

.alert p {
    margin: 0;
    font-size: 0.85rem;
    line-height: 1.4;
    color: inherit;
}

.alert-success {
    border-color: #4CAF50;
    background: #fff;
    color: #2e7d32;
}

.alert-success .alert-header i {
    color: #1b5e20;
}

.alert-success h4 {
    color: #1b5e20;
}

.alert-warning {
    border-color: #FF9800;
    background: #fff;
    color: #e65100;
}

.alert-warning .alert-header i {
    color: #bf360c;
}

.alert-warning h4 {
    color: #bf360c;
}

.alert-info {
    border-color: #2196F3;
    background: #fff;
    color: #1565c0;
}

.alert-info .alert-header i {
    color: #0d47a1;
}

.alert-info h4 {
    color: #0d47a1;
}

.alert-danger {
    border-color: #F44336;
    background: #fff;
    color: #c62828;
    margin-bottom: 20px;
}

/* ========== STAFF WORK SCHEDULE SECTION ========== */
.staff-schedule-section {
    margin-top: 40px;
    margin-bottom: 32px;
}

.staff-schedule-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.staff-schedule-section .btn-secondary {
    padding: 10px 20px;
    background: #D4A574;
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.staff-schedule-section .btn-secondary:hover {
    background: #C4956A;
    color: white;
}

.schedule-summary-table-wrapper {
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #f0f0f0;
}

.schedule-summary-table {
    width: 100%;
    border-collapse: collapse;
}

.schedule-summary-table th {
    padding: 14px 24px;
    text-align: left;
    background: #fafafa;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
    border-bottom: 1px solid #f0f0f0;
}

.schedule-summary-table td {
    padding: 16px 24px;
    border-bottom: 1px solid #f5f5f5;
    font-size: 14px;
    color: #333;
}

.schedule-summary-table tbody tr:hover {
    background: #fafafa;
}

.schedule-summary-table tbody tr:last-child td {
    border-bottom: none;
}

.schedule-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #D4A574;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    transition: background 0.2s ease;
}

.schedule-link-btn:hover {
    background: #C4956A;
    color: white;
}

/* ========== RESPONSIVE DESIGN ========== */
@media (max-width: 1024px) {
    .metrics-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }
    
    .insights-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .analytics-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .analytics-header-actions {
        align-items: flex-start;
        width: 100%;
        flex-direction: column;
    }
    
    .date-filter-form {
        width: 100%;
    }
    
    .date-filter-form .form-control {
        flex: 1;
        min-width: 0;
    }
    
    .metrics-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .insights-grid {
        grid-template-columns: 1fr;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .staff-table {
        font-size: 13px;
    }
    
    .staff-table th,
    .staff-table td {
        padding: 12px 16px;
    }
    
    .staff-schedule-section .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .staff-schedule-section .btn-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .schedule-summary-table th,
    .schedule-summary-table td {
        padding: 12px 16px;
        font-size: 13px;
    }
    
    .schedule-link-btn {
        padding: 5px 10px;
        font-size: 12px;
    }
}
</style>

<!-- html2pdf.js for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="sustainability.js"></script>

<?php require_once '../includes/footer.php'; ?>
