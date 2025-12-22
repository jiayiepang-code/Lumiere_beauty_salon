<?php
/**
 * API Endpoint: Assign/Update Staff for Booking Service
 * Validates that staff role matches service category before assignment
 */

header('Content-Type: application/json');

require_once '../../../admin/includes/auth_check.php';
require_once '../../../config/db_connect.php';

// Check authentication
if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Authentication required']
    ]);
    exit;
}

// Check session timeout
if (!checkSessionTimeout()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'SESSION_EXPIRED', 'message' => 'Session has expired']
    ]);
    exit;
}

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Only POST requests are allowed']
    ]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'INVALID_JSON', 'message' => 'Invalid JSON data']
        ]);
        exit;
    }
    
    $booking_service_id = isset($input['booking_service_id']) ? trim($input['booking_service_id']) : null;
    $staff_email = isset($input['staff_email']) ? trim($input['staff_email']) : null;
    
    if (!$booking_service_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'booking_service_id is required']
        ]);
        exit;
    }
    
    // If staff_email is empty or null, allow unassignment (set to NULL)
    $allowUnassign = ($staff_email === '' || $staff_email === null);
    
    $conn = getDBConnection();
    
    // Get booking service details with service category
    $serviceQuery = "SELECT 
                        bs.booking_service_id,
                        bs.service_id,
                        bs.staff_email as current_staff_email,
                        s.service_name,
                        s.service_category
                    FROM Booking_Service bs
                    INNER JOIN Service s ON bs.service_id = s.service_id
                    WHERE bs.booking_service_id = ?";
    
    $serviceStmt = $conn->prepare($serviceQuery);
    $serviceStmt->bind_param("i", $booking_service_id);
    $serviceStmt->execute();
    $serviceResult = $serviceStmt->get_result();
    
    if ($serviceResult->num_rows === 0) {
        $serviceStmt->close();
        $conn->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'Booking service not found']
        ]);
        exit;
    }
    
    $bookingService = $serviceResult->fetch_assoc();
    $serviceStmt->close();
    
    $serviceCategory = strtolower(trim($bookingService['service_category'] ?? ''));
    
    // If unassigning, skip role validation
    if (!$allowUnassign) {
        // Get staff details including role
        $staffQuery = "SELECT staff_email, role, first_name, last_name, is_active 
                      FROM Staff 
                      WHERE staff_email = ?";
        $staffStmt = $conn->prepare($staffQuery);
        $staffStmt->bind_param("s", $staff_email);
        $staffStmt->execute();
        $staffResult = $staffStmt->get_result();
        
        if ($staffResult->num_rows === 0) {
            $staffStmt->close();
            $conn->close();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Staff member not found']
            ]);
            exit;
        }
        
        $staff = $staffResult->fetch_assoc();
        $staffStmt->close();
        
        // Check if staff is active
        if (!$staff['is_active']) {
            $conn->close();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'INACTIVE_STAFF', 'message' => 'Cannot assign inactive staff member']
            ]);
            exit;
        }
        
        // Validate staff role matches service category
        // BUT: Allow admin override if service category is empty or doesn't have strict role requirements
        // This allows admins to assign staff when user selected "No Preference"
        $staffRole = strtolower(trim($staff['role'] ?? ''));
        $isValid = validateStaffRoleForService($staffRole, $serviceCategory);
        
        // If validation fails, check if we should allow admin override
        if (!$isValid) {
            // Allow admin override if:
            // 1. Service category is empty/null (user selected "No Preference")
            // 2. Service category doesn't have a strict role mapping
            $hasStrictRoleMapping = hasStrictRoleMapping($serviceCategory);
            
            if (empty($serviceCategory) || !$hasStrictRoleMapping) {
                // Allow admin override - log a warning but proceed
                error_log("Admin override: Assigning staff '{$staff['role']}' to service category '{$bookingService['service_category']}' (booking_service_id: {$booking_service_id})");
                $isValid = true; // Override validation
            } else {
                // Strict role mapping exists and doesn't match - block assignment
                $conn->close();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'ROLE_MISMATCH',
                        'message' => "Staff role '{$staff['role']}' does not match service category '{$bookingService['service_category']}'. " .
                                    "Please assign staff according to their role: " .
                                    getRoleServiceMapping($staffRole)
                    ]
                ]);
                exit;
            }
        }
    }
    
    // Check if staff is available at this booking's time (if assigning, not unassigning)
    if (!$allowUnassign) {
        // Get booking date and time for this service
        $bookingTimeQuery = "SELECT b.booking_id, b.booking_date, b.start_time, b.expected_finish_time
                            FROM Booking b
                            JOIN Booking_Service bs ON b.booking_id = bs.booking_id
                            WHERE bs.booking_service_id = ?";
        $timeStmt = $conn->prepare($bookingTimeQuery);
        $timeStmt->bind_param("i", $booking_service_id);
        $timeStmt->execute();
        $timeResult = $timeStmt->get_result();
        
        if ($timeRow = $timeResult->fetch_assoc()) {
            // Check if this staff is already booked at this time (excluding the current booking)
            $conflictQuery = "SELECT bs.booking_service_id
                             FROM Booking b
                             JOIN Booking_Service bs ON b.booking_id = bs.booking_id
                             WHERE b.booking_date = ?
                             AND bs.staff_email = ?
                             AND b.status NOT IN ('cancelled', 'completed')
                             AND b.booking_id != ?
                             AND (
                                   (b.start_time <= ? AND b.expected_finish_time > ?)  -- new start inside existing
                                OR (b.start_time < ?  AND b.expected_finish_time >= ?) -- new end inside existing
                                OR (b.start_time >= ? AND b.expected_finish_time <= ?)  -- existing fully inside new
                             )
                             LIMIT 1";
            $conflictStmt = $conn->prepare($conflictQuery);
            $conflictStmt->bind_param("ssisssssss", 
                $timeRow['booking_date'],
                $staff_email,
                $timeRow['booking_id'],
                $timeRow['start_time'], $timeRow['start_time'],
                $timeRow['expected_finish_time'], $timeRow['expected_finish_time'],
                $timeRow['start_time'], $timeRow['expected_finish_time']
            );
            $conflictStmt->execute();
            $conflictResult = $conflictStmt->get_result();
            
            if ($conflictResult->num_rows > 0) {
                $conflictStmt->close();
                $timeStmt->close();
                $conn->close();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'STAFF_UNAVAILABLE',
                        'message' => 'This staff member is already booked at this time. Please select another staff member or choose a different time slot.'
                    ]
                ]);
                exit;
            }
            $conflictStmt->close();
        }
        $timeStmt->close();
    }
    
    // Update booking service staff assignment
    if ($allowUnassign) {
        $updateQuery = "UPDATE Booking_Service SET staff_email = NULL WHERE booking_service_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $booking_service_id);
    } else {
        $updateQuery = "UPDATE Booking_Service SET staff_email = ? WHERE booking_service_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $staff_email, $booking_service_id);
    }
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Get updated staff name for response
        $staffName = 'Unassigned';
        if (!$allowUnassign) {
            $nameQuery = "SELECT first_name, last_name FROM Staff WHERE staff_email = ?";
            $nameStmt = $conn->prepare($nameQuery);
            $nameStmt->bind_param("s", $staff_email);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();
            if ($nameRow = $nameResult->fetch_assoc()) {
                $staffName = trim(($nameRow['first_name'] ?? '') . ' ' . ($nameRow['last_name'] ?? ''));
            }
            $nameStmt->close();
        }
        
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $allowUnassign ? 'Staff unassigned successfully' : 'Staff assigned successfully',
            'data' => [
                'booking_service_id' => $booking_service_id,
                'staff_email' => $allowUnassign ? null : $staff_email,
                'staff_name' => $staffName
            ]
        ]);
    } else {
        throw new Exception('Failed to update staff assignment: ' . $updateStmt->error);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    
    error_log('Error in assign_staff.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'An error occurred while updating staff assignment'
        ]
    ]);
}

/**
 * Validate if staff role matches service category
 * 
 * @param string $staffRole Staff role (e.g., 'beautician', 'hair_stylist')
 * @param string $serviceCategory Service category (e.g., 'Facial', 'Haircut')
 * @return bool True if role matches category, false otherwise
 */
function validateStaffRoleForService($staffRole, $serviceCategory) {
    // Role to service category mapping
    $roleServiceMap = [
        'beautician' => ['facial'],
        'hair_stylist' => ['haircut', 'hair'],
        'nail_technician' => ['manicure', 'nail'],
        'massage_therapist' => ['massage']
    ];
    
    // Normalize inputs
    $staffRole = strtolower(trim($staffRole));
    $serviceCategory = strtolower(trim($serviceCategory));
    
    // Check if role exists in mapping
    if (!isset($roleServiceMap[$staffRole])) {
        // Unknown role - allow assignment (for backward compatibility)
        return true;
    }
    
    // Check if service category matches any allowed category for this role
    $allowedCategories = $roleServiceMap[$staffRole];
    return in_array($serviceCategory, $allowedCategories);
}

/**
 * Get human-readable service mapping for a role (for error messages)
 * 
 * @param string $staffRole Staff role
 * @return string Description of allowed services
 */
function getRoleServiceMapping($staffRole) {
    $mapping = [
        'beautician' => 'Beauticians can only handle Facial services',
        'hair_stylist' => 'Hair Stylists can only handle Haircut/Hair services',
        'nail_technician' => 'Nail Technicians can only handle Manicure/Nail services',
        'massage_therapist' => 'Massage Therapists can only handle Massage services'
    ];
    
    $staffRole = strtolower(trim($staffRole));
    return $mapping[$staffRole] ?? 'Unknown role';
}

/**
 * Check if a service category has a strict role mapping requirement
 * 
 * @param string $serviceCategory Service category
 * @return bool True if category has strict role requirements
 */
function hasStrictRoleMapping($serviceCategory) {
    $serviceCategory = strtolower(trim($serviceCategory));
    
    // Categories with strict role requirements
    $strictCategories = ['facial', 'haircut', 'hair', 'manicure', 'nail', 'massage'];
    
    return in_array($serviceCategory, $strictCategories);
}
?>

