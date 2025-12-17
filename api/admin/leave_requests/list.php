<?php
require_once '../../../config/config.php';
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';

header('Content-Type: application/json');

if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]);
    exit;
}

try {
    $conn = getDBConnection();

    // Get month/year from query params
    $month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
    $year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
    
    // Validate month (1-12) if provided
    if ($month !== null && ($month < 1 || $month > 12)) {
        $month = null;
    }
    // Validate year if provided, allow a wide range of years to keep filters flexible
    if ($year !== null && ($year < 1970 || $year > 2100)) {
        $year = null;
    }

    // Pending requests filtered by selected month/year
    // Build dynamic query based on whether month/year is provided
    // Check if the leave request date range overlaps with the selected month/year
    if ($month !== null && $year !== null) {
        // Calculate the first and last day of the selected month/year
        $firstDay = sprintf('%04d-%02d-01', $year, $month);
        $lastDay = date('Y-m-t', strtotime($firstDay)); // Last day of the month
        
    $sql = "
        SELECT 
            lr.id,
            lr.staff_email,
            s.first_name,
            s.last_name,
            lr.leave_type,
            lr.start_date,
            lr.end_date,
            lr.half_day,
            lr.reason,
            lr.status,
            lr.created_at
        FROM leave_requests lr
        JOIN staff s ON lr.staff_email = s.staff_email
        WHERE lr.status = 'pending'
              AND lr.start_date <= ?
              AND lr.end_date >= ?
            ORDER BY lr.created_at ASC";
    } else if ($year !== null) {
        // Filter by year only - check if date range overlaps with the year
        $firstDay = sprintf('%04d-01-01', $year);
        $lastDay = sprintf('%04d-12-31', $year);
        
        $sql = "
            SELECT 
                lr.id,
                lr.staff_email,
                s.first_name,
                s.last_name,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.half_day,
                lr.reason,
                lr.status,
                lr.created_at
            FROM leave_requests lr
            JOIN staff s ON lr.staff_email = s.staff_email
            WHERE lr.status = 'pending'
              AND lr.start_date <= ?
              AND lr.end_date >= ?
            ORDER BY lr.created_at ASC";
    } else if ($month !== null) {
        // Filter by month only (all years) - check if date range overlaps with the month in any year
        // This handles: month in start_date, month in end_date, or range spans the month
        $sql = "
            SELECT 
                lr.id,
                lr.staff_email,
                s.first_name,
                s.last_name,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.half_day,
                lr.reason,
                lr.status,
                lr.created_at
            FROM leave_requests lr
            JOIN staff s ON lr.staff_email = s.staff_email
            WHERE lr.status = 'pending'
              AND (
                MONTH(lr.start_date) = ?
                OR MONTH(lr.end_date) = ?
                OR (MONTH(lr.start_date) < ? AND MONTH(lr.end_date) > ?)
                OR (MONTH(lr.start_date) > MONTH(lr.end_date) AND (MONTH(lr.start_date) <= ? OR MONTH(lr.end_date) >= ?))
              )
            ORDER BY lr.created_at ASC";
    } else {
        // No filters - show all pending requests
        $sql = "
            SELECT 
                lr.id,
                lr.staff_email,
                s.first_name,
                s.last_name,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.half_day,
                lr.reason,
                lr.status,
                lr.created_at
            FROM leave_requests lr
            JOIN staff s ON lr.staff_email = s.staff_email
            WHERE lr.status = 'pending'
            ORDER BY lr.created_at ASC";
    }
    
    $stmtPendingList = $conn->prepare($sql);
    if (!$stmtPendingList) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    // Bind parameters based on which filters are provided
    if ($month !== null && $year !== null) {
        // Month and year filter
        $stmtPendingList->bind_param('ss', $lastDay, $firstDay);
    } else if ($year !== null) {
        // Year filter only
        $stmtPendingList->bind_param('ss', $lastDay, $firstDay);
    } else if ($month !== null) {
        // Month filter only - bind month parameter 6 times for all conditions
        $stmtPendingList->bind_param('iiiiii', $month, $month, $month, $month, $month, $month);
    }
    // No parameters needed if no filters
    
    $stmtPendingList->execute();
    $result = $stmtPendingList->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $start = $row['start_date'];
        $end = $row['end_date'];
        $dateRange = ($start === $end) ? $start : ($start . ' - ' . $end);

        // Map half_day (0/1) to duration type/label
        $isHalfDay = !empty($row['half_day']);
        $durationType = $isHalfDay ? 'half' : 'full';
        $durationLabel = $isHalfDay ? 'Half Day' : 'Full Day';

        $submittedAt = $row['created_at'];

        $requests[] = [
            'id' => (int)$row['id'],
            'staff_email' => $row['staff_email'],
            'staff_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'leave_type' => $row['leave_type'],
            'start_date' => $start,
            'end_date' => $end,
            'date_range' => $dateRange,
            'duration_type' => $durationType,
            'duration_label' => $durationLabel,
            'reason' => $row['reason'],
            'status' => $row['status'],
            'created_at_raw' => $submittedAt,
            'submitted_at' => $submittedAt
        ];
    }

    // Summary stats
    // Pending count: ALL pending requests (not filtered by month)
    $pendingStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM leave_requests WHERE status = 'pending'");
    $pendingCount = 0;
    if ($pendingStmt) {
        $pendingStmt->execute();
        $res = $pendingStmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $pendingCount = (int)$row['cnt'];
        }
        $pendingStmt->close();
    }

    // Approved/Rejected: Filtered by selected month/year (or all if no filters)
    // Use date range overlap logic for consistency
    $stats = [
        'pending_count' => $pendingCount,
        'approved_this_month' => 0,
        'rejected_this_month' => 0
    ];

    if ($month !== null && $year !== null) {
        // Month and year filter
        $statsSql = "
            SELECT status, COUNT(*) AS cnt
            FROM leave_requests
            WHERE start_date <= ?
              AND end_date >= ?
              AND status IN ('approved', 'rejected')
            GROUP BY status";
        $stmt = $conn->prepare($statsSql);
        if ($stmt) {
            $stmt->bind_param('ss', $lastDay, $firstDay);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ($row['status'] === 'approved') {
                    $stats['approved_this_month'] = (int)$row['cnt'];
                } elseif ($row['status'] === 'rejected') {
                    $stats['rejected_this_month'] = (int)$row['cnt'];
                }
            }
            $stmt->close();
        }
    } else if ($year !== null) {
        // Year filter only
    $statsSql = "
        SELECT status, COUNT(*) AS cnt
        FROM leave_requests
            WHERE start_date <= ?
              AND end_date >= ?
              AND status IN ('approved', 'rejected')
            GROUP BY status";
        $stmt = $conn->prepare($statsSql);
        if ($stmt) {
            $stmt->bind_param('ss', $lastDay, $firstDay);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ($row['status'] === 'approved') {
                    $stats['approved_this_month'] = (int)$row['cnt'];
                } elseif ($row['status'] === 'rejected') {
                    $stats['rejected_this_month'] = (int)$row['cnt'];
                }
            }
            $stmt->close();
        }
    } else if ($month !== null) {
        // Month filter only (all years) - same logic as pending requests
        $statsSql = "
            SELECT status, COUNT(*) AS cnt
            FROM leave_requests
            WHERE (
                MONTH(start_date) = ?
                OR MONTH(end_date) = ?
                OR (MONTH(start_date) < ? AND MONTH(end_date) > ?)
                OR (MONTH(start_date) > MONTH(end_date) AND (MONTH(start_date) <= ? OR MONTH(end_date) >= ?))
              )
              AND status IN ('approved', 'rejected')
        GROUP BY status";
    $stmt = $conn->prepare($statsSql);
    if ($stmt) {
            $stmt->bind_param('iiiiii', $month, $month, $month, $month, $month, $month);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ($row['status'] === 'approved') {
                    $stats['approved_this_month'] = (int)$row['cnt'];
                } elseif ($row['status'] === 'rejected') {
                    $stats['rejected_this_month'] = (int)$row['cnt'];
                }
            }
            $stmt->close();
        }
    } else {
        // No filters - show all approved/rejected
        $statsSql = "
            SELECT status, COUNT(*) AS cnt
            FROM leave_requests
            WHERE status IN ('approved', 'rejected')
            GROUP BY status";
        $stmt = $conn->prepare($statsSql);
        if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if ($row['status'] === 'approved') {
                $stats['approved_this_month'] = (int)$row['cnt'];
            } elseif ($row['status'] === 'rejected') {
                $stats['rejected_this_month'] = (int)$row['cnt'];
            }
        }
        $stmt->close();
        }
    }

    // Build available years from data for a flexible year dropdown
    $availableYears = [];
    $yearsRes = $conn->query("SELECT MIN(YEAR(start_date)) AS min_year, MAX(YEAR(start_date)) AS max_year FROM leave_requests");
    if ($yearsRes && $yearsRow = $yearsRes->fetch_assoc()) {
        $minYear = (int)$yearsRow['min_year'];
        $maxYear = (int)$yearsRow['max_year'];
        if ($minYear > 0 && $maxYear > 0 && $minYear <= $maxYear) {
            for ($y = $minYear; $y <= $maxYear; $y++) {
                $availableYears[] = $y;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'stats' => $stats,
        'available_years' => $availableYears
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}


