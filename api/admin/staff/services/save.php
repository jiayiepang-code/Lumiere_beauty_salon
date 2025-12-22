<?php
/**
 * Save staff-service assignments + proficiency
 * POST JSON: { staff_email: string, assignments: [{service_id, assigned, proficiency_level}] }
 */

header('Content-Type: application/json');

require_once '../../../../config/db_connect.php';
require_once '../../../../admin/includes/auth_check.php';
require_once '../../../../admin/includes/error_handler.php';
require_once '../../includes/csrf_validation.php';

if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only POST requests are allowed', null, 405);
}

// Validate CSRF token
if (!validateCSRFToken()) {
    ErrorHandler::sendError(ErrorHandler::INVALID_CSRF_TOKEN, 'Invalid CSRF token', null, 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        ErrorHandler::sendError(ErrorHandler::INVALID_JSON, 'Invalid JSON body');
    }

    $staff_email = isset($input['staff_email']) ? trim($input['staff_email']) : '';
    $assignments = $input['assignments'] ?? null;

    if ($staff_email === '') {
        ErrorHandler::handleValidationError(['staff_email' => 'staff_email is required']);
    }
    if (!is_array($assignments)) {
        ErrorHandler::handleValidationError(['assignments' => 'assignments must be an array']);
    }

    $allowed_levels = ['junior', 'senior', 'expert'];

    $conn = getDBConnection();
    $conn->begin_transaction();

    $selectStmt = $conn->prepare("SELECT staff_service_id FROM staff_service WHERE staff_email = ? AND service_id = ?");
    $insertStmt = $conn->prepare("INSERT INTO staff_service (staff_email, service_id, proficiency_level, is_active) VALUES (?, ?, ?, 1)");
    $updateStmt = $conn->prepare("UPDATE staff_service SET proficiency_level = ?, is_active = 1 WHERE staff_email = ? AND service_id = ?");
    $deactivateStmt = $conn->prepare("UPDATE staff_service SET is_active = 0 WHERE staff_email = ? AND service_id = ?");

    foreach ($assignments as $item) {
        $service_id = isset($item['service_id']) ? trim($item['service_id']) : '';
        $assigned = isset($item['assigned']) ? (bool)$item['assigned'] : false;
        $level = isset($item['proficiency_level']) ? strtolower(trim($item['proficiency_level'])) : null;

        if ($service_id === '') {
            $conn->rollback();
            ErrorHandler::handleValidationError(['service_id' => 'service_id cannot be empty']);
        }

        if ($assigned) {
            if ($level === null || !in_array($level, $allowed_levels, true)) {
                $conn->rollback();
                ErrorHandler::handleValidationError(['proficiency_level' => 'Invalid or missing proficiency_level']);
            }

            // Exists?
            $selectStmt->bind_param('ss', $staff_email, $service_id);
            $selectStmt->execute();
            $res = $selectStmt->get_result();

            if ($res->num_rows > 0) {
                $updateStmt->bind_param('sss', $level, $staff_email, $service_id);
                $updateStmt->execute();
            } else {
                $insertStmt->bind_param('sss', $staff_email, $service_id, $level);
                $insertStmt->execute();
            }
        } else {
            // Deactivate if exists
            $deactivateStmt->bind_param('ss', $staff_email, $service_id);
            $deactivateStmt->execute();
        }
    }

    $conn->commit();

    $selectStmt->close();
    $insertStmt->close();
    $updateStmt->close();
    $deactivateStmt->close();
    $conn->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Skills updated',
    ]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'saving staff-service assignments');
}
