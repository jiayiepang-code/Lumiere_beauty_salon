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
$selected_year = isset($_GET['year']) ? trim($_GET['year']) : '';

// Validate month: must be numeric 01-12
if (empty($selected_month) || !is_numeric($selected_month) || $selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m'); // Current month
} else {
    $selected_month = str_pad((int)$selected_month, 2, '0', STR_PAD_LEFT); // Format as 01-12
}

// Validate year: must be numeric 2020-2030
if (empty($selected_year) || !is_numeric($selected_year) || $selected_year < 2020 || $selected_year > 2030) {
    $selected_year = (int)date('Y'); // Current year
} else {
    $selected_year = (int)$selected_year;
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

    // ========== CARD 1: TOTAL ACTIVE STAFF ==========
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Staff WHERE is_active = 1");
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
            'name' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'),
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

    $conn->close();

} catch (Exception $e) {
    error_log("Sustainability Analytics Error: " . $e->getMessage());
    $error_message = "An error occurred while loading data. Please try again.";
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
        <form method="GET" action="" class="date-filter-form">
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
            <select name="year" id="year-select" class="form-control">
                <?php for ($y = 2020; $y <= 2030; $y++): 
                    $selected = ($y == $selected_year) ? 'selected' : '';
                ?>
                    <option value="<?php echo $y; ?>" <?php echo $selected; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>
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
                <div class="card-info-btn" data-tooltip="Total number of active staff members available for bookings">
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
                <div class="card-info-btn" data-tooltip="Count of confirmed and completed services during the period">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $services_delivered; ?></div>
            <div class="metric-label">Services Delivered</div>
            <div class="metric-description">Completed & confirmed bookings</div>
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
                <div class="card-info-btn" data-tooltip="Total hours actually used = Sum of service duration + cleanup time">
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 22h14"></path>
                        <path d="M5 2h14"></path>
                        <path d="M17 22V6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v16"></path>
                        <path d="M7 16h10"></path>
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
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.date-filter-form .form-control {
    padding: 10px 16px;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    background: white;
    color: #333;
    font-size: 14px;
    cursor: pointer;
    min-width: 120px;
}

.date-filter-form .btn-primary {
    padding: 10px 20px;
    background: #D4A574;
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
}

.date-filter-form .btn-primary:hover {
    background: #C4956A;
}

/* ========== METRICS GRID ========== */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 40px;
}

.metric-card {
    background: white;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #f0f0f0;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    overflow: hidden;
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
    width: 64px;
    height: 64px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    transition: all 0.3s ease;
}

.metric-icon svg {
    width: 32px;
    height: 32px;
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
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(212, 165, 116, 0.1);
    color: #D4A574;
    font-size: 14px;
    cursor: help;
    transition: all 0.2s ease;
    border: 1px solid rgba(212, 165, 116, 0.2);
    z-index: 5;
}

.card-info-btn svg {
    width: 16px;
    height: 16px;
}

.card-info-btn:hover {
    background: rgba(212, 165, 116, 0.2);
    transform: scale(1.1);
}

.card-info-btn::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 12px);
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 200px;
    text-align: center;
}

.card-info-btn::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 4px);
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #333;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
    z-index: 1001;
}

.card-info-btn:hover::after,
.card-info-btn:hover::before {
    opacity: 1;
}

.metric-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1;
    letter-spacing: -0.5px;
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
    margin-top: 4px;
    padding-top: 0;
}

.utilization-bar {
    width: 100%;
    height: 8px;
    background-color: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 12px;
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

.alert {
    padding: 16px 18px;
    border-radius: 12px;
    border: 1.5px solid;
    background: white;
    transition: all 0.2s ease;
}

.alert-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.alert-header i {
    font-size: 16px;
    flex-shrink: 0;
}

.alert h4 {
    font-size: 13px;
    font-weight: 600;
    margin: 0;
}

.alert p {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
}

.alert-success {
    border-color: #4CAF50;
    background-color: #f1f8f4;
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
    background-color: #fff8e1;
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
    background-color: #e3f2fd;
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
    background-color: #ffebee;
    color: #c62828;
    margin-bottom: 20px;
}

/* ========== RESPONSIVE DESIGN ========== */
@media (max-width: 1024px) {
    .metrics-grid {
        grid-template-columns: repeat(2, 1fr);
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
    
    .date-filter-form {
        width: 100%;
    }
    
    .date-filter-form .form-control {
        flex: 1;
        min-width: 0;
    }
    
    .metrics-grid {
        grid-template-columns: 1fr;
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
}
</style>

<?php require_once '../includes/footer.php'; ?>
