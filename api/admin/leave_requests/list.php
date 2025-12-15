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

    // Get month/year from query params or use current month/year
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    
    // Validate month (1-12) and year (2020-2030)
    if ($month < 1 || $month > 12) {
        $month = (int)date('m');
    }
    if ($year < 2020 || $year > 2030) {
        $year = (int)date('Y');
    }

    // Pending requests
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
        ORDER BY lr.created_at ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

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

    // Approved/Rejected: Filtered by selected month/year
    $stats = [
        'pending_count' => $pendingCount,
        'approved_this_month' => 0,
        'rejected_this_month' => 0
    ];

    $stmt = $conn->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM leave_requests
        WHERE MONTH(start_date) = ? AND YEAR(start_date) = ?
          AND status IN ('approved', 'rejected')
        GROUP BY status
    ");

    if ($stmt) {
        $stmt->bind_param('ii', $month, $year);
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

    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'stats' => $stats
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


