<?php
// Suppress PHP warnings/errors from being displayed in JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Start output buffering to prevent any stray output from corrupting JSON
ob_start();

session_start();
require_once 'config/database.php';
require 'mailer.php';

header('Content-Type: application/json');

// Helper function to safely write debug logs
function safeLogDebug($data) {
    $log_dir = __DIR__ . '/.cursor';
    $log_file = $log_dir . '/debug.log';
    
    // Create directory if it doesn't exist
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    
    // Use @ to suppress any warnings if write fails
    @file_put_contents($log_file, json_encode($data) . "\n", FILE_APPEND);
}

/**
 * Get a random available staff member for a service
 * @param PDO $db Database connection
 * @param string $bookingDate Booking date (Y-m-d)
 * @param string $startTime Service start time (H:i:s)
 * @param string $endTime Service end time (H:i:s)
 * @param int $serviceId Service ID
 * @param string $bookingId Current booking ID (to exclude from availability check)
 * @param array $assignedStaffInBooking Already assigned staff in current booking to avoid conflicts
 * @return string|null Staff email or null if none available
 */
function getRandomAvailableStaff($db, $bookingDate, $startTime, $endTime, $serviceId, $bookingId, $assignedStaffInBooking = []) {
    try {
        // Convert times to minutes for easier comparison
        list($startH, $startM) = explode(':', $startTime);
        $startMinutes = ($startH * 60) + $startM;
        list($endH, $endM) = explode(':', $endTime);
        $endMinutes = ($endH * 60) + $endM;
        
        // Get all active staff who can provide this service
        // First try to get staff from staff_service table, otherwise get all active staff
        // EXCLUDE admin accounts (role = 'admin') - only assign actual staff members
        $staffQuery = "SELECT DISTINCT st.staff_email 
                      FROM staff st
                      LEFT JOIN staff_service ss ON st.staff_email = ss.staff_email AND ss.service_id = ? AND ss.is_active = 1
                      WHERE st.is_active = 1 
                      AND (st.role IS NULL OR LOWER(st.role) != 'admin')
                      AND (ss.service_id IS NOT NULL OR NOT EXISTS (SELECT 1 FROM staff_service WHERE staff_email = st.staff_email))
                      ORDER BY RAND()";
        
        $staffStmt = $db->prepare($staffQuery);
        $staffStmt->execute([$serviceId]);
        $allStaff = $staffStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no staff found from staff_service, get all active staff (excluding admin)
        if (empty($allStaff)) {
            $fallbackQuery = "SELECT staff_email FROM staff WHERE is_active = 1 AND (role IS NULL OR LOWER(role) != 'admin') ORDER BY RAND()";
            $fallbackStmt = $db->prepare($fallbackQuery);
            $fallbackStmt->execute();
            $allStaff = $fallbackStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        if (empty($allStaff)) {
            error_log('No active staff found for service assignment');
            return null;
        }
        
        // Get all existing bookings for this date that might overlap with the requested time
        // Exclude the current booking and cancelled/completed bookings
        // Note: We check the booking's overall time range since services run sequentially
        $bookingsQuery = "SELECT DISTINCT
                            bs.staff_email,
                            b.start_time,
                            b.expected_finish_time
                          FROM booking b
                          JOIN booking_service bs ON b.booking_id = bs.booking_id
                          WHERE b.booking_date = ?
                          AND b.status != 'cancelled'
                          AND b.status != 'completed'
                          AND b.booking_id != ?";
        
        $bookingsStmt = $db->prepare($bookingsQuery);
        $bookingsStmt->execute([$bookingDate, $bookingId]);
        $existingBookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group bookings by staff to check availability more efficiently
        $staffBookings = [];
        foreach ($existingBookings as $booking) {
            if (!isset($staffBookings[$booking['staff_email']])) {
                $staffBookings[$booking['staff_email']] = [];
            }
            $staffBookings[$booking['staff_email']][] = $booking;
        }
        
        // Find available staff (not busy during this time slot)
        $availableStaff = [];
        
        foreach ($allStaff as $staffEmail) {
            $isAvailable = true;
            
            // First, check if this staff is already assigned in the current booking
            foreach ($assignedStaffInBooking as $assigned) {
                if ($assigned['staff_email'] === $staffEmail) {
                    // Convert assigned times to minutes
                    list($assignedStartH, $assignedStartM, $assignedStartS) = array_pad(explode(':', $assigned['start_time']), 3, 0);
                    $assignedStartMinutes = ($assignedStartH * 60) + $assignedStartM;
                    list($assignedEndH, $assignedEndM, $assignedEndS) = array_pad(explode(':', $assigned['end_time']), 3, 0);
                    $assignedEndMinutes = ($assignedEndH * 60) + $assignedEndM;
                    
                    // Check for overlap with already assigned service in this booking
                    if ($startMinutes < $assignedEndMinutes && $endMinutes > $assignedStartMinutes) {
                        $isAvailable = false;
                        break;
                    }
                }
            }
            
            // If still available, check against existing bookings in database
            if ($isAvailable && isset($staffBookings[$staffEmail])) {
                foreach ($staffBookings[$staffEmail] as $booking) {
                    // Convert booking times to minutes
                    list($bookStartH, $bookStartM, $bookStartS) = array_pad(explode(':', $booking['start_time']), 3, 0);
                    $bookStartMinutes = ($bookStartH * 60) + $bookStartM;
                    list($bookEndH, $bookEndM, $bookEndS) = array_pad(explode(':', $booking['expected_finish_time']), 3, 0);
                    $bookEndMinutes = ($bookEndH * 60) + $bookEndM;
                    
                    // Check for overlap: (StartA < EndB) and (EndA > StartB)
                    if ($startMinutes < $bookEndMinutes && $endMinutes > $bookStartMinutes) {
                        $isAvailable = false;
                        break;
                    }
                }
            }
            
            if ($isAvailable) {
                $availableStaff[] = $staffEmail;
            }
        }
        
        // Randomly select from available staff
        if (!empty($availableStaff)) {
            $randomIndex = array_rand($availableStaff);
            $selectedStaff = $availableStaff[$randomIndex];
            error_log("Randomly assigned staff: $selectedStaff for service $serviceId (Date: $bookingDate, Time: $startTime-$endTime)");
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'process_booking.php:staff-assigned', 'message' => 'Staff assigned to service', 'data' => ['staffEmail' => $selectedStaff, 'serviceId' => $serviceId, 'bookingDate' => $bookingDate, 'startTime' => $startTime, 'availableStaffCount' => count($availableStaff)], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'post-fix', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            return $selectedStaff;
        } else {
            // If no staff is available, still assign one (fallback to first staff)
            error_log("No available staff found for service $serviceId, using fallback assignment");
            return $allStaff[0] ?? null;
        }
        
    } catch (Exception $e) {
        error_log('Error in getRandomAvailableStaff: ' . $e->getMessage());
        // Fallback to first active staff (excluding admin)
        $fallbackQuery = "SELECT staff_email FROM staff WHERE is_active = 1 AND (role IS NULL OR LOWER(role) != 'admin') LIMIT 1";
        $fallbackStmt = $db->prepare($fallbackQuery);
        $fallbackStmt->execute();
        $fallbackRow = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        return $fallbackRow['staff_email'] ?? null;
    }
}

// Check if user is logged in
if(!isset($_SESSION['customer_phone']) && !isset($_SESSION['customer_email'])) {
    error_log('Booking attempt without login');
    echo json_encode(['success' => false, 'message' => 'Not logged in. Please log in again.']);
    exit();
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if(!$input) {
    error_log('Invalid JSON input');
    echo json_encode(['success' => false, 'message' => 'Invalid input data. Please refresh and try again.']);
    exit();
}

// Validate required fields
if(empty($input['services']) || !is_array($input['services']) || count($input['services']) === 0) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one service.']);
    exit();
}

if(empty($input['date'])) {
    echo json_encode(['success' => false, 'message' => 'Please select a date for your booking.']);
    exit();
}

if(empty($input['time'])) {
    echo json_encode(['success' => false, 'message' => 'Please select a time for your booking.']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();
    
    // Get customer email from session or database
    $customerEmail = $_SESSION['customer_email'] ?? null;
    $customerPhone = $_SESSION['customer_phone'] ?? null;
    
    // If email not in session, retrieve from database using phone
    if (!$customerEmail && $customerPhone) {
        $emailQuery = "SELECT customer_email, first_name, last_name FROM customer WHERE phone = ? LIMIT 1";
        $emailStmt = $db->prepare($emailQuery);
        $emailStmt->execute([$customerPhone]);
        $customerRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customerRow) {
            $customerEmail = $customerRow['customer_email'];
            // Store in session for future use
            $_SESSION['customer_email'] = $customerEmail;
            $_SESSION['customer_name'] = trim(($customerRow['first_name'] ?? '') . ' ' . ($customerRow['last_name'] ?? ''));
            
            error_log('Customer email retrieved from DB: ' . $customerEmail);
        } else {
            $db->rollBack();
            error_log('Customer not found for phone: ' . $customerPhone);
            echo json_encode(['success' => false, 'message' => 'Customer account not found. Please log out and log in again.']);
            exit();
        }
    }
    
    if (!$customerEmail) {
        $db->rollBack();
        error_log('No customer email found in session or database');
        echo json_encode(['success' => false, 'message' => 'Customer email not found. Please log out and log in again.']);
        exit();
    }
    
    error_log('Processing booking for customer: ' . $customerEmail);
    
    // Verify customer exists in database
    $verifyQuery = "SELECT customer_email FROM customer WHERE customer_email = ? LIMIT 1";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute([$customerEmail]);
    $verifiedCustomer = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verifiedCustomer) {
        $db->rollBack();
        error_log('Customer email not found in database: ' . $customerEmail);
        echo json_encode(['success' => false, 'message' => 'Your account is not properly registered. Please contact support.']);
        exit();
    }
    
    // Calculate Totals from services
    $totalDuration = 0;
    $totalPrice = 0;
    
    $placeholders = implode(',', array_fill(0, count($input['services']), '?'));
    
    // Query matches your service table structure
    $query = "SELECT service_id, 
                     service_name,
                     current_duration_minutes, 
                     current_price 
              FROM service 
              WHERE service_id IN ($placeholders)";
    
    $stmt = $db->prepare($query);
    $stmt->execute($input['services']);
    
    $serviceData = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $serviceData[$row['service_id']] = $row;
        $totalDuration += intval($row['current_duration_minutes']);
        $totalPrice += floatval($row['current_price']);
    }
    
    if(empty($serviceData) || count($serviceData) != count($input['services'])) {
        $db->rollBack();
        error_log('Service data mismatch. Requested: ' . json_encode($input['services']) . ', Found: ' . json_encode(array_keys($serviceData)));
        echo json_encode([
            'success' => false, 
            'message' => 'Some services could not be found. Please refresh and try again.'
        ]);
        exit();
    }
    
    error_log('Services validated. Total duration: ' . $totalDuration . ' mins, Total price: RM ' . $totalPrice);
    
    // Calculate end time
    $startDateTime = new DateTime($input['date'] . ' ' . $input['time']);
    $endDateTime = clone $startDateTime;
    $endDateTime->add(new DateInterval('PT' . $totalDuration . 'M'));
    
    // Generate unique booking ID
    $maxIdQuery = "SELECT booking_id FROM booking WHERE booking_id LIKE 'BK%' ORDER BY CAST(SUBSTRING(booking_id, 3) AS UNSIGNED) DESC LIMIT 1";
    $maxIdStmt = $db->prepare($maxIdQuery);
    $maxIdStmt->execute();
    $maxIdRow = $maxIdStmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNumber = 1;
    if ($maxIdRow && isset($maxIdRow['booking_id'])) {
        if (preg_match('/BK(\d+)/', $maxIdRow['booking_id'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
    }
    
    $bookingId = 'BK' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    
    error_log('Generated booking ID: ' . $bookingId);
    
    // Insert into booking table (matches your schema exactly)
    $insertBookingQuery = "INSERT INTO booking (
        booking_id, 
        customer_email, 
        booking_date, 
        start_time, 
        expected_finish_time, 
        status, 
        remarks, 
        total_price, 
        created_at, 
        updated_at
    ) VALUES (?, ?, ?, ?, ?, 'confirmed', ?, ?, NOW(), NOW())";
    
    $stmt = $db->prepare($insertBookingQuery);
    
    $bookingValues = [
        $bookingId,
        $customerEmail,
        $input['date'],
        $input['time'],
        $endDateTime->format('H:i:s'),
        $input['specialRequests'] ?? '',
        $totalPrice
    ];
    
    error_log('Inserting booking with values: ' . json_encode($bookingValues));
    
    if (!$stmt->execute($bookingValues)) {
        $errorInfo = $stmt->errorInfo();
        error_log('Booking insert failed: ' . json_encode($errorInfo));
        throw new Exception('Failed to create booking: ' . $errorInfo[2]);
    }
    
    error_log('Booking inserted successfully');
    
    // Insert booking services (matches your booking_service table)
    $insertServiceQuery = "INSERT INTO booking_service (
        booking_id, 
        service_id, 
        staff_email, 
        quoted_price, 
        quoted_duration_minutes
    ) VALUES (?, ?, ?, ?, ?)";
    
    $stmtService = $db->prepare($insertServiceQuery);
    
    $serviceAssignments = [];
    
    // Calculate booking start and end time for availability checking
    $bookingDate = $input['date'];
    $bookingStartTime = $input['time'];
    $bookingStartDateTime = new DateTime($bookingDate . ' ' . $bookingStartTime);
    $currentServiceStartTime = clone $bookingStartDateTime;
    
    // Track already assigned staff in this booking to avoid conflicts
    $assignedStaffInBooking = [];
    
    foreach($input['services'] as $serviceId) {
        $staffValue = isset($input['staff'][$serviceId]) ? $input['staff'][$serviceId] : null;
        $staffEmail = ($staffValue && $staffValue != 0 && $staffValue != '0') ? $staffValue : null;
        
        // Validate that manually selected staff is not an admin account
        if ($staffEmail !== null) {
            $validateStaffQuery = "SELECT role FROM staff WHERE staff_email = ? AND is_active = 1 LIMIT 1";
            $validateStmt = $db->prepare($validateStaffQuery);
            $validateStmt->execute([$staffEmail]);
            $staffRow = $validateStmt->fetch(PDO::FETCH_ASSOC);
            
            // If staff not found or is admin, reset to null to trigger auto-assignment
            if (!$staffRow || (isset($staffRow['role']) && strtolower($staffRow['role']) === 'admin')) {
                error_log("Invalid staff assignment attempt: $staffEmail (admin account or not found). Auto-assigning instead.");
                // #region agent log
                @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'process_booking.php:admin-validation', 'message' => 'Admin account blocked from assignment', 'data' => ['staffEmail' => $staffEmail, 'role' => $staffRow['role'] ?? 'not_found', 'serviceId' => $serviceId], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'post-fix', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                $staffEmail = null;
            }
        }
        
        // Get service duration for this specific service
        $serviceDuration = $serviceData[$serviceId]['current_duration_minutes'] ?? 0;
        $serviceEndDateTime = clone $currentServiceStartTime;
        $serviceEndDateTime->add(new DateInterval('PT' . $serviceDuration . 'M'));
        
        // If no staff selected, get a random available staff
        if ($staffEmail === null) {
            $staffEmail = getRandomAvailableStaff($db, $bookingDate, $currentServiceStartTime->format('H:i:s'), $serviceEndDateTime->format('H:i:s'), $serviceId, $bookingId, $assignedStaffInBooking);
        }
        
        // Track this assignment for subsequent services
        if ($staffEmail) {
            $assignedStaffInBooking[] = [
                'staff_email' => $staffEmail,
                'start_time' => $currentServiceStartTime->format('H:i:s'),
                'end_time' => $serviceEndDateTime->format('H:i:s')
            ];
        }
        
        // Update start time for next service
        $currentServiceStartTime = clone $serviceEndDateTime;
        
        // Insert booking service
        $serviceValues = [
            $bookingId,
            $serviceId,
            $staffEmail,
            $serviceData[$serviceId]['current_price'],
            $serviceData[$serviceId]['current_duration_minutes']
        ];
        
        if (!$stmtService->execute($serviceValues)) {
            $errorInfo = $stmtService->errorInfo();
            error_log('Booking service insert failed: ' . json_encode($errorInfo));
            throw new Exception('Failed to add service to booking: ' . $errorInfo[2]);
        }
        
        // Get staff name for response
        $staffName = 'No Preference';
        if ($staffEmail) {
            $staffNameQuery = "SELECT first_name, last_name FROM staff WHERE staff_email = ? LIMIT 1";
            $staffNameStmt = $db->prepare($staffNameQuery);
            $staffNameStmt->execute([$staffEmail]);
            $staffRow = $staffNameStmt->fetch(PDO::FETCH_ASSOC);
            if ($staffRow) {
                $staffName = trim(($staffRow['first_name'] ?? '') . ' ' . ($staffRow['last_name'] ?? ''));
            }
        }
        
        $serviceAssignments[] = [
            'service_id' => $serviceId,
            'service_name' => $serviceData[$serviceId]['service_name'] ?? 'Service',
            'staff_email' => $staffEmail,
            'staff_name' => $staffName,
            'duration' => $serviceData[$serviceId]['current_duration_minutes'],
            'price' => $serviceData[$serviceId]['current_price']
        ];
    }
    
    $db->commit();
    
    error_log('Booking completed successfully: ' . $bookingId);
    
    // Prepare response data first (before email sending)
    $responseData = [
        'success' => true,
        'booking_id' => $bookingId,
        'date' => date('d M Y', strtotime($input['date'])),
        'time' => date('h:i A', strtotime($input['time'])),
        'subtotal' => number_format($totalPrice, 2),
        'services' => $serviceAssignments,
        'customer_email' => $customerEmail
    ];
    
    // Send response immediately to user (don't wait for email)
    ob_end_clean(); // Clear any output buffering
    header('Content-Type: application/json');
    echo json_encode($responseData);
    
    // Flush output to send response to client immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // For FastCGI - closes connection, continues execution
    } else {
        // For non-FastCGI, try to flush and close connection
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
        // Close session to allow other requests
        if (session_id()) {
            session_write_close();
        }
    }
    
    // Now send email in background (user already got response)
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'process_booking.php:' . __LINE__, 'message' => 'Starting email sending process (background)', 'data' => ['bookingId' => $bookingId, 'customerEmail' => $customerEmail], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND);
    // #endregion agent log
    
    try {
        // Get customer details
        $customerQuery = "SELECT first_name, last_name FROM customer WHERE customer_email = ? LIMIT 1";
        $customerStmt = $db->prepare($customerQuery);
        $customerStmt->execute([$customerEmail]);
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
        $customerName = ($customer ? trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) : 'Customer');
        
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'process_booking.php:' . __LINE__, 'message' => 'Customer details retrieved', 'data' => ['customerName' => $customerName, 'customerEmail' => $customerEmail], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND);
        // #endregion agent log
        
        // Build services list for email
        $servicesList = '';
        foreach($serviceAssignments as $idx => $svc) {
            $servicesList .= '<li>' . htmlspecialchars($svc['service_name']) . ' - ' . htmlspecialchars($svc['staff_name']) . ' (RM ' . number_format($svc['price'], 2) . ')</li>';
        }
        
        // Email subject and body
        $subject = 'Booking Confirmation - ' . $bookingId;
        $formattedDate = date('l, d F Y', strtotime($input['date']));
        $startTime = date('g:i A', strtotime($input['time']));
        $endTime = date('g:i A', strtotime($endDateTime->format('Y-m-d H:i:s')));
        
        $emailBody = '<div style="font-family:\'Playfair Display\',\'Georgia\',serif,Arial,sans-serif;background:#f5e9e4;padding:40px 20px;">
            <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;box-shadow:0 4px 20px rgba(194,144,118,0.15);overflow:hidden;">
                <!-- Header -->
                <div style="background:linear-gradient(135deg, #D4A574 0%, #c29076 100%);padding:40px 30px;text-align:center;border-bottom:3px solid #B59267;">
                    <h1 style="color:#ffffff;margin:0;font-size:28px;font-weight:700;font-family:\'Playfair Display\',serif;letter-spacing:0.5px;">✨ Booking Confirmed!</h1>
                    <p style="color:#ffffff;margin:12px 0 0 0;font-size:16px;opacity:0.95;">Thank you for choosing Lumière Beauty Salon</p>
                </div>
                
                <!-- Content -->
                <div style="padding:40px 32px;">
                    <p style="font-size:16px;color:#5c4e4b;margin-bottom:20px;">Dear ' . htmlspecialchars($customerName) . ',</p>
                    <p style="font-size:16px;color:#5c4e4b;line-height:1.6;margin-bottom:32px;">Your booking has been successfully confirmed. We look forward to pampering you!</p>
                    
                    <!-- Booking Details -->
                    <div style="background:#faf5f2;padding:24px;border-radius:12px;margin:24px 0;border-left:4px solid #D4A574;">
                        <h3 style="margin:0 0 20px 0;color:#c29076;font-size:20px;font-weight:600;font-family:\'Playfair Display\',serif;">📋 Booking Details</h3>
                        <div style="margin:12px 0;font-size:15px;color:#5c4e4b;">
                            <span style="font-weight:600;color:#8a766e;display:inline-block;min-width:140px;">Booking ID:</span>
                            <span style="color:#2d2d2d;font-weight:500;">' . htmlspecialchars($bookingId) . '</span>
                        </div>
                        <div style="margin:12px 0;font-size:15px;color:#5c4e4b;">
                            <span style="font-weight:600;color:#8a766e;display:inline-block;min-width:140px;">Date:</span>
                            <span style="color:#2d2d2d;font-weight:500;">' . $formattedDate . '</span>
                        </div>
                        <div style="margin:12px 0;font-size:15px;color:#5c4e4b;">
                            <span style="font-weight:600;color:#8a766e;display:inline-block;min-width:140px;">Time:</span>
                            <span style="color:#2d2d2d;font-weight:500;">' . $startTime . ' - ' . $endTime . '</span>
                        </div>
                        <div style="margin:12px 0;font-size:15px;color:#5c4e4b;">
                            <span style="font-weight:600;color:#8a766e;display:inline-block;min-width:140px;">Services:</span>
                        </div>
                        <ul style="margin:8px 0 12px 20px;padding-left:20px;color:#5c4e4b;">' . $servicesList . '</ul>
                        <div style="margin:12px 0;font-size:15px;color:#5c4e4b;">
                            <span style="font-weight:600;color:#8a766e;display:inline-block;min-width:140px;">Total:</span>
                            <span style="color:#c29076;font-size:18px;font-weight:700;">RM ' . number_format($totalPrice, 2) . '</span>
                        </div>
                    </div>
                    
                    <!-- Important Notes -->
                    <div style="background:#fff9e6;border-left:4px solid #D4A574;padding:20px;border-radius:8px;margin:24px 0;">
                        <strong style="color:#c29076;display:block;margin-bottom:12px;font-size:16px;">💡 Important Notes:</strong>
                        <ul style="margin:0;padding-left:20px;color:#5c4e4b;">
                            <li style="margin:8px 0;">Please arrive 10 minutes early</li>
                            <li style="margin:8px 0;">You will receive a reminder email 24 hours before your appointment</li>
                            <li style="margin:8px 0;">If you need to cancel or reschedule, please contact us at least 24 hours in advance</li>
                            <li style="margin:8px 0;">Payment will be collected at the salon</li>
                        </ul>
                    </div>
                    
                    <!-- Footer -->
                    <div style="text-align:center;margin-top:32px;padding-top:24px;border-top:1px solid #e6d9d2;color:#8a766e;font-size:13px;">
                        <p style="margin:0 0 8px 0;">This is an automated confirmation. Please do not reply to this email.</p>
                        <p style="margin:0;">Thank you for choosing Lumière Beauty Salon!</p>
                        <p style="margin:8px 0 0 0;font-style:italic;">— Lumière Beauty Salon Team</p>
                        <p style="margin:16px 0 0 0;">&copy; ' . date('Y') . ' Lumière Beauty Salon. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>';
        
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'process_booking.php:' . __LINE__, 'message' => 'Calling sendMail function', 'data' => ['to' => $customerEmail, 'subject' => $subject, 'bodyLength' => strlen($emailBody)], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C']) . "\n", FILE_APPEND);
        // #endregion agent log
        
        $emailResult = sendMail($customerEmail, $subject, $emailBody);
        
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'process_booking.php:' . __LINE__, 'message' => 'sendMail result received', 'data' => ['success' => $emailResult === true, 'result' => is_string($emailResult) ? substr($emailResult, 0, 200) : $emailResult], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'D']) . "\n", FILE_APPEND);
        // #endregion agent log
        
        if ($emailResult !== true) {
            error_log('Confirmation email failed for booking ' . $bookingId . ': ' . $emailResult);
            // #region agent log
            file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'process_booking.php:' . __LINE__, 'message' => 'Email sending failed', 'data' => ['bookingId' => $bookingId, 'error' => is_string($emailResult) ? substr($emailResult, 0, 200) : 'Unknown error'], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
            // #endregion agent log
            // Don't fail the booking if email fails
        } else {
            error_log('Confirmation email sent successfully for booking ' . $bookingId);
            // #region agent log
            safeLogDebug(['location' => 'process_booking.php:' . __LINE__, 'message' => 'Email sent successfully', 'data' => ['bookingId' => $bookingId, 'customerEmail' => $customerEmail], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'F']);
            // #endregion agent log
        }
        
        // Schedule reminder email (24 hours before booking)
        try {
            $reminderTime = calculateReminderTime($input['date'], $input['time']);
            
            // Check if email_queue table exists before inserting
            $checkTableQuery = "SHOW TABLES LIKE 'email_queue'";
            $checkStmt = $db->prepare($checkTableQuery);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Table exists, insert reminder
                $reminderStmt = $db->prepare("
                    INSERT INTO email_queue (
                        booking_id, email_type, recipient_email, scheduled_at
                    ) VALUES (?, 'reminder', ?, ?)
                ");
                
                $reminderStmt->execute([
                    $bookingId,
                    $customerEmail,
                    $reminderTime
                ]);
                
                error_log('Reminder email scheduled for booking ' . $bookingId . ' at ' . $reminderTime);
            } else {
                error_log('email_queue table does not exist. Reminder not scheduled for booking ' . $bookingId);
            }
        } catch (Exception $reminderEx) {
            // Don't fail booking if reminder scheduling fails
            error_log('Failed to schedule reminder email for booking ' . $bookingId . ': ' . $reminderEx->getMessage());
        }
    } catch (Exception $emailEx) {
        error_log('Exception sending confirmation email for booking ' . $bookingId . ': ' . $emailEx->getMessage());
        // #region agent log
        safeLogDebug(['location' => 'process_booking.php:' . __LINE__, 'message' => 'Exception in email sending', 'data' => ['bookingId' => $bookingId, 'exception' => $emailEx->getMessage()], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'G']);
        // #endregion agent log
        // Don't fail the booking if email fails
    }
    
    // Response already sent above, exit here
    exit;
    
} catch(Exception $e) {
    if(isset($db)) {
        $db->rollBack();
    }
    
    error_log('Booking exception: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Booking failed: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}

/**
 * Calculate reminder time (24 hours before booking)
 * @param string $bookingDate Booking date (Y-m-d)
 * @param string $startTime Booking start time (H:i:s)
 * @return string Reminder datetime (Y-m-d H:i:s)
 */
function calculateReminderTime($bookingDate, $startTime) {
    // Booking datetime - 24 hours
    $bookingDateTime = new DateTime("$bookingDate $startTime");
    $reminderDateTime = clone $bookingDateTime;
    $reminderDateTime->modify('-24 hours');
    return $reminderDateTime->format('Y-m-d H:i:s');
}

?>