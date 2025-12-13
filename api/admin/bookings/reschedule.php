<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
header('Content-Type: application/json');

require_once '../../../php/connection.php';
require_once '../../../admin/includes/auth_check.php';

if (!isAdminAuthenticated() || !checkSessionTimeout()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>['code'=>'AUTH','message'=>'Authentication required or session expired']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>['code'=>'METHOD','message'=>'Only POST allowed']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = $input['booking_id'] ?? null;
$staff_email = $input['staff_email'] ?? null;
$target_slot = $input['target_slot'] ?? null; // e.g., 2025-12-12 14:00

if (!$booking_id || !$staff_email || !$target_slot) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>['code'=>'INVALID','message'=>'Missing required fields']]);
    exit;
}

try {
    // Basic conflict check: ensure no other booking overlaps same staff and slot
    $conflictSql = "SELECT COUNT(*) AS cnt FROM Booking WHERE staff_email = ? AND start_time = ? AND status IN ('confirmed','completed') AND booking_id <> ?";
    $stmt = $conn->prepare($conflictSql);
    $stmt->bind_param('ssi', $staff_email, $target_slot, $booking_id);
    $stmt->execute();
    $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    if ($cnt > 0) {
        http_response_code(409);
        echo json_encode(['success'=>false,'error'=>['code'=>'CONFLICT','message'=>'Slot already occupied']]);
        exit;
    }

    // Update booking slot
    $updateSql = "UPDATE Booking SET staff_email = ?, start_time = ? WHERE booking_id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param('ssi', $staff_email, $target_slot, $booking_id);
    $stmt->execute();

    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>['code'=>'SERVER','message'=>$e->getMessage()]]);
}
