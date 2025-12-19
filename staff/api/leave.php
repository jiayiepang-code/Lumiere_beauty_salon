<?php
require_once '../config.php';

checkAuth();

$staff_email = $_SESSION['staff_id']; // stored as email in session

// Utility: respond JSON and stop
function respond($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Ensure leave_requests table exists (lightweight check/create)
function ensureLeaveTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS leave_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_email VARCHAR(100) NOT NULL,
                leave_type VARCHAR(50) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                half_day TINYINT(1) DEFAULT 0,
                reason TEXT,
                status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_staff_email (staff_email),
                INDEX idx_status (status),
                INDEX idx_date (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    
    // ADDED: Check and add half_day_time column if it doesn't exist
    try {
        // Attempt to add the column after 'half_day'
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN half_day_time VARCHAR(50) NULL AFTER half_day");
    } catch (PDOException $e) {
        // If the column already exists (Error 1060), or other failure, we ignore it.
    }
}

// Ensure Staff_Schedule rows exist for a date range; create defaults 10:00-22:00 with status working
function ensureScheduleRows($pdo, $staff_email, $start, $end) {
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

    $insert = $pdo->prepare("INSERT INTO Staff_Schedule (staff_email, work_date, start_time, end_time, status, created_at, updated_at)
                             VALUES (?, ?, '10:00:00', '22:00:00', 'working', NOW(), NOW())");
    $exists = $pdo->prepare("SELECT schedule_id FROM Staff_Schedule WHERE staff_email = ? AND work_date = ?");

    foreach ($period as $day) {
        $dateStr = $day->format('Y-m-d');
        $exists->execute([$staff_email, $dateStr]);
        if (!$exists->fetch()) {
            $insert->execute([$staff_email, $dateStr]);
        }
    }
}

// Approve: mark leave_requests and set Staff_Schedule status=leave
function approveLeave($pdo, $requestId, $status) {
    // Fetch request
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();
    if (!$req) {
        respond(['success' => false, 'error' => 'Leave request not found'], 404);
    }

    // Update request status
    $upd = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
    $upd->execute([$status, $requestId]);

    if ($status === 'approved') {
        // Ensure schedule rows exist
        ensureScheduleRows($pdo, $req['staff_email'], $req['start_date'], $req['end_date']);
        // Set status to leave for the range
        $updSched = $pdo->prepare("UPDATE Staff_Schedule 
                                   SET status = 'leave', updated_at = NOW() 
                                   WHERE staff_email = ? AND work_date BETWEEN ? AND ?");
        $updSched->execute([$req['staff_email'], $req['start_date'], $req['end_date']]);
    }

    respond(['success' => true, 'message' => 'Leave ' . $status . ' successfully']);
}

ensureLeaveTable($pdo);

$action = isset($_GET['action']) ? $_GET['action'] : 'request';

if ($action === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = isset($_POST['leave_type']) ? trim($_POST['leave_type']) : '';
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date   = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $half_day   = isset($_POST['half_day']) && $_POST['half_day'] == '1' ? 1 : 0;
    
    // UPDATED: Capture start and end time specifically
    $start_time = isset($_POST['half_day_start_time']) ? trim($_POST['half_day_start_time']) : NULL;
    $end_time = isset($_POST['half_day_end_time']) ? trim($_POST['half_day_end_time']) : NULL;
    
    // Calculate the value for the half_day_time column: format "HH:MM - HH:MM"
    $half_day_time = ($half_day == 1 && $start_time && $end_time) ? "{$start_time} - {$end_time}" : NULL; // UPDATED

    $reason     = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        respond(['success' => false, 'error' => 'Please fill in all required fields.'], 400);
    }

    if (strtotime($end_date) < strtotime($start_date)) {
        respond(['success' => false, 'error' => 'End date cannot be before start date.'], 400);
    }

    try {
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'staff/api/leave.php:' . __LINE__, 'message' => 'Preparing to insert leave request', 'data' => ['staff_email' => $staff_email, 'leave_type' => $leave_type, 'start_date' => $start_date, 'end_date' => $end_date, 'half_day' => $half_day, 'half_day_time' => $half_day_time, 'reason_length' => strlen($reason)], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'I']) . "\n", FILE_APPEND);
        // #endregion agent log
        
        // INSERT statement remains the same, using the calculated $half_day_time
        $stmt = $pdo->prepare("INSERT INTO leave_requests (staff_email, leave_type, start_date, end_date, half_day, half_day_time, reason)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$staff_email, $leave_type, $start_date, $end_date, $half_day, $half_day_time, $reason]);
        
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'staff/api/leave.php:' . __LINE__, 'message' => 'Leave request inserted successfully', 'data' => ['lastInsertId' => $pdo->lastInsertId(), 'rowCount' => $stmt->rowCount()], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'J']) . "\n", FILE_APPEND);
        // #endregion agent log

        respond(['success' => true, 'message' => 'Leave request submitted. Pending approval.']);
    } catch (PDOException $e) {
        // #region agent log
        $errorMsg = $e->getMessage();
        $errorCode = $e->getCode();
        $logData = array(
            'location' => 'staff/api/leave.php:135',
            'message' => 'Leave request insert failed',
            'data' => array(
                'error' => $errorMsg,
                'code' => $errorCode
            ),
            'timestamp' => round(microtime(true) * 1000),
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'K'
        );
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode($logData) . "\n", FILE_APPEND);
        // #endregion agent log
        respond(array('success' => false, 'error' => 'Database error: ' . $errorMsg), 500);
    }
}

if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real system, verify role (manager/admin). For now, allow.
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'approved';
    if (!$request_id || !in_array($status, ['approved', 'rejected'])) {
        respond(['success' => false, 'error' => 'Invalid request or status'], 400);
    }
    approveLeave($pdo, $request_id, $status);
}

if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    if (!$request_id) {
        respond(['success' => false, 'error' => 'Invalid request ID'], 400);
    }

    // Verify ownership and current status
    $stmt = $pdo->prepare("SELECT staff_email, status FROM leave_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        respond(['success' => false, 'error' => 'Leave request not found'], 404);
    }

    if ($req['staff_email'] !== $staff_email) {
        respond(['success' => false, 'error' => 'Unauthorized'], 403);
    }

    if ($req['status'] !== 'pending') {
        respond(['success' => false, 'error' => 'Only pending requests can be cancelled'], 400);
    }

    $cancel = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $cancel->execute([$request_id]);

    respond(['success' => true, 'message' => 'Leave request cancelled']);
}

if ($action === 'history' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch leave history for the logged-in staff (including cancelled requests)
    try {
        // SELECT statement remains the same, retrieving half_day_time
        $stmt = $pdo->prepare("SELECT id, leave_type, start_date, end_date, half_day, half_day_time, reason, status, created_at, updated_at 
                              FROM leave_requests 
                              WHERE staff_email = ?
                              ORDER BY created_at DESC");
        $stmt->execute([$staff_email]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // #region agent log
        $logData = [
            'location' => 'staff/api/leave.php:' . __LINE__,
            'message' => 'Returning leave history',
            'data' => [
                'requestCount' => count($requests),
                'requests' => array_map(function($r) {
                    return ['id' => $r['id'], 'status' => $r['status'], 'leave_type' => $r['leave_type']];
                }, $requests)
            ],
            'timestamp' => round(microtime(true) * 1000),
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'B'
        ];
        file_put_contents(__DIR__ . '/../../.cursor/debug.log', json_encode($logData) . "\n", FILE_APPEND);
        // #endregion agent log
        
        // Ensure status values are properly set (handle null/empty)
        foreach ($requests as &$request) {
            if (empty($request['status'])) {
                $request['status'] = 'pending'; // Default to pending if status is missing
            }
            // Normalize status to lowercase
            $request['status'] = strtolower(trim($request['status']));
        }
        unset($request); // Break reference
        
        respond(['success' => true, 'requests' => $requests]);
    } catch (PDOException $e) {
        respond(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

respond(['success' => false, 'error' => 'Invalid action'], 400);
?>
