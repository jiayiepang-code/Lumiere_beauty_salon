<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Please fill in all fields.']);
    exit;
}

// Basic password strength
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']);
    exit;
}

// Normalize phone: remove spaces, ensure +60 prefix if missing
$phone = preg_replace('/\s+/', '', $phone);
if (!preg_match('/^\+60/', $phone)) {
    $phone = ltrim($phone, '0');
    $phone = '+60' . $phone;
}

try {
    // Find staff by phone (try exact, then alt without +60)
    $stmt = $pdo->prepare("SELECT staff_email FROM Staff WHERE phone = ?");
    $stmt->execute([$phone]);
    $staff = $stmt->fetch();

    if (!$staff && strpos($phone, '+60') === 0) {
        $phone_alt = substr($phone, 3);
        $stmt = $pdo->prepare("SELECT staff_email FROM Staff WHERE phone = ?");
        $stmt->execute([$phone_alt]);
        $staff = $stmt->fetch();
    }

    if (!$staff) {
        echo json_encode(['success' => false, 'error' => 'Phone number not found.']);
        exit;
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE Staff SET password = ? WHERE phone = ? OR phone = ?");
    $update->execute([$newHash, $phone, isset($phone_alt) ? $phone_alt : $phone]);

    echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

