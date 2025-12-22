<?php
/**
 * List services with staff assignment + proficiency info
 * GET params: staff_email (required)
 */

header('Content-Type: application/json');

require_once '../../../../config/db_connect.php';
require_once '../../../../admin/includes/auth_check.php';
require_once '../../../../admin/includes/error_handler.php';

if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only GET requests are allowed', null, 405);
}

try {
    $staff_email = isset($_GET['staff_email']) ? trim($_GET['staff_email']) : '';
    if ($staff_email === '') {
        ErrorHandler::handleValidationError(['staff_email' => 'staff_email is required']);
    }

    $conn = getDBConnection();

    $sql = "SELECT 
                sv.service_id,
                sv.service_category,
                sv.sub_category,
                sv.service_name,
                COALESCE(ss.is_active, 0) AS assigned,
                ss.proficiency_level
            FROM Service sv
            LEFT JOIN staff_service ss 
              ON ss.service_id = sv.service_id
             AND ss.staff_email = ?
            WHERE sv.is_active = 1
            ORDER BY sv.service_category, sv.sub_category, sv.service_name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $staff_email);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = [
            'service_id' => $row['service_id'],
            'service_category' => $row['service_category'],
            'sub_category' => $row['sub_category'],
            'service_name' => $row['service_name'],
            'assigned' => (bool)$row['assigned'],
            'proficiency_level' => $row['proficiency_level'] ?: null,
        ];
    }

    $stmt->close();
    $conn->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'services' => $services,
    ]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'listing staff-service assignments');
}
