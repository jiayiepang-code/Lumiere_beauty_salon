<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Log received data for debugging
error_log('Booking request received. Raw input length: ' . strlen($rawInput));
error_log('Decoded input: ' . json_encode($input));

if(!$input) {
    error_log('Invalid JSON input. Raw input: ' . substr($rawInput, 0, 500));
    echo json_encode(['success' => false, 'message' => 'Invalid input data. Please refresh and try again.']);
    exit();
}

// Validate required fields
if(empty($input['services']) || !is_array($input['services']) || count($input['services']) === 0) {
    error_log('No services provided in booking request');
    echo json_encode(['success' => false, 'message' => 'Please select at least one service.']);
    exit();
}

if(empty($input['date'])) {
    error_log('No date provided in booking request');
    echo json_encode(['success' => false, 'message' => 'Please select a date for your booking.']);
    exit();
}

if(empty($input['time'])) {
    error_log('No time provided in booking request');
    echo json_encode(['success' => false, 'message' => 'Please select a time for your booking.']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_1','timestamp'=>time()*1000,'location'=>'process_booking.php:45','message'=>'Booking transaction started','data'=>['service_count'=>count($input['services']),'services'=>$input['services']],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
    // #endregion
    
    // 1. Calculate Totals
    $totalDuration = 0;
    $totalPrice = 0;
    
    // Prepare string of question marks for IN clause (e.g., "?,?,?")
    $placeholders = implode(',', array_fill(0, count($input['services']), '?'));
    
    // Try different column name variations for service table
    // Fixed: Use actual column names (service_name, current_duration_minutes, current_price) directly
    $query = "SELECT service_id, 
                     service_name,
                     current_duration_minutes as duration_minutes, 
                     current_price as price 
              FROM service 
              WHERE service_id IN ($placeholders)";
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_2','timestamp'=>time()*1000,'location'=>'process_booking.php:62','message'=>'Query prepared','data'=>['query'=>$query,'service_ids'=>$input['services']],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
    // #endregion
    
    $stmt = $db->prepare($query);
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_3','timestamp'=>time()*1000,'location'=>'process_booking.php:66','message'=>'Before execute','data'=>['prepared'=>true],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
    // #endregion
    
    $stmt->execute($input['services']);
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_4','timestamp'=>time()*1000,'location'=>'process_booking.php:70','message'=>'After execute - query succeeded','data'=>['row_count'=>$stmt->rowCount()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
    // #endregion
    
    $serviceData = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $serviceData[$row['service_id']] = $row;
        $totalDuration += intval($row['duration_minutes']);
        $totalPrice += floatval($row['price']);
        
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_5','timestamp'=>time()*1000,'location'=>'process_booking.php:72','message'=>'Service data fetched','data'=>['service_id'=>$row['service_id'],'duration_minutes'=>$row['duration_minutes'],'price'=>$row['price']],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
        // #endregion
    }
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_6','timestamp'=>time()*1000,'location'=>'process_booking.php:80','message'=>'All services processed','data'=>['total_duration'=>$totalDuration,'total_price'=>$totalPrice,'service_count'=>count($serviceData)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
    // #endregion
    
    // Validate we got service data
    if(empty($serviceData) || count($serviceData) != count($input['services'])) {
        $db->rollBack();
        error_log('Service data mismatch. Requested services: ' . json_encode($input['services']));
        error_log('Found service data: ' . json_encode(array_keys($serviceData)));
        echo json_encode([
            'success' => false, 
            'message' => 'Some services could not be found. Please refresh and try again.',
            'debug' => [
                'requested' => $input['services'],
                'found' => array_keys($serviceData)
            ]
        ]);
        exit();
    }
    
    // 2. Calculate Times
    $startDateTime = new DateTime($input['date'] . ' ' . $input['time']);
    $endDateTime = clone $startDateTime;
    $endDateTime->add(new DateInterval('PT' . $totalDuration . 'M'));
    
    // 3. Generate Booking ID in format BK000123, BK000124, etc. (6-digit running number)
    // Use a retry mechanism to handle race conditions and ensure uniqueness
    $maxAttempts = 10;
    $bookingId = null;
    
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        // Get the highest existing booking_id that starts with 'BK'
        $maxIdQuery = "SELECT booking_id FROM booking WHERE booking_id LIKE 'BK%' ORDER BY CAST(SUBSTRING(booking_id, 3) AS UNSIGNED) DESC, booking_id DESC LIMIT 1";
        $maxIdStmt = $db->prepare($maxIdQuery);
        $maxIdStmt->execute();
        $maxIdRow = $maxIdStmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNumber = 1;
        if ($maxIdRow && isset($maxIdRow['booking_id'])) {
            // Extract number from existing booking_id (e.g., BK000123 -> 123)
            $existingId = $maxIdRow['booking_id'];
            if (preg_match('/BK(\d+)/', $existingId, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }
        }
        
        // Format as BK + 6-digit number with leading zeros
        $candidateId = 'BK' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        
        // Check if this ID already exists (handles race conditions)
        $checkQuery = "SELECT booking_id FROM booking WHERE booking_id = ? LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$candidateId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            $bookingId = $candidateId;
            break;
        }
        
        // If ID exists, increment and try again
        $nextNumber++;
    }
    
    // If we couldn't generate a unique ID after max attempts, use timestamp-based fallback
    if (!$bookingId) {
        $bookingId = 'BK' . date('YmdHis') . rand(100, 999);
    }
    
    // 4. Financials (SST and grand total are calculated but not stored in booking table)
    // The table only has total_price column
    // Note: Duration is calculated from start_time and expected_finish_time, no need to store separately
    
    // 5. Insert Main Booking
    // Based on actual schema: booking_id, customer_email, booking_date, start_time, 
    // expected_finish_time, status, remarks, promo_code, discount_amount, total_price
    
    // #region agent log
    // Query actual table structure to verify column names
    $schemaQuery = "DESCRIBE booking";
    $schemaStmt = $db->prepare($schemaQuery);
    $schemaStmt->execute();
    $tableColumns = $schemaStmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($tableColumns, 'Field');
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_schema','timestamp'=>time()*1000,'location'=>'process_booking.php:131','message'=>'Booking table schema queried','data'=>['columns'=>$columnNames,'all_fields'=>$tableColumns],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
    // #endregion
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_before_insert','timestamp'=>time()*1000,'location'=>'process_booking.php:131','message'=>'Before preparing INSERT query','data'=>['booking_id'=>$bookingId,'total_duration'=>$totalDuration,'total_price'=>$totalPrice,'expected_finish'=>$endDateTime->format('H:i:s')],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'B'])."\n", FILE_APPEND);
    // #endregion
    
    // Insert booking (customer table uses email as primary key, no customer_id)
    $query = "INSERT INTO `booking` (
        booking_id, customer_email, booking_date, start_time, expected_finish_time, 
        total_price, remarks, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')";
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_insert_query','timestamp'=>time()*1000,'location'=>'process_booking.php:141','message'=>'INSERT query prepared','data'=>['query'=>$query,'column_count'=>8],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'])."\n", FILE_APPEND);
    // #endregion
    
    // Get customer email from session or database
    $customerEmail = $_SESSION['customer_email'] ?? $_SESSION['user_email'] ?? null;
    $customerPhone = $_SESSION['customer_phone'] ?? null;
    $customerId = $_SESSION['customer_id'] ?? null;
    
    error_log('Session data - Email: ' . ($customerEmail ?? 'NULL') . ', Phone: ' . ($customerPhone ?? 'NULL') . ', ID: ' . ($customerId ?? 'NULL'));
    
    if (!$customerEmail && $customerPhone) {
        // Get email from database using phone
        $emailQuery = "SELECT customer_email FROM customer WHERE phone = ? LIMIT 1";
        $emailStmt = $db->prepare($emailQuery);
        $emailStmt->execute([$customerPhone]);
        $customerRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
        $customerEmail = $customerRow['customer_email'] ?? '';
        error_log('Email from DB by phone: ' . ($customerEmail ?? 'NOT FOUND'));
    } elseif (!$customerEmail && $customerId) {
        // Fallback: Get email from database using customer_id
        $emailQuery = "SELECT customer_email FROM customer WHERE customer_id = ? LIMIT 1";
        $emailStmt = $db->prepare($emailQuery);
        $emailStmt->execute([$customerId]);
        $customerRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
        $customerEmail = $customerRow['customer_email'] ?? '';
        error_log('Email from DB by ID: ' . ($customerEmail ?? 'NOT FOUND'));
    }
    
    if (empty($customerEmail)) {
        $db->rollBack();
        error_log('BOOKING FAILED: No customer email found. Session data: ' . json_encode($_SESSION));
        echo json_encode(['success' => false, 'message' => 'Customer email not found. Please log out and log in again.']);
        exit();
    }
    
    // Verify the email exists in customer table (case-insensitive)
    $verifyQuery = "SELECT customer_email FROM `customer` WHERE LOWER(TRIM(customer_email)) = LOWER(TRIM(?)) LIMIT 1";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute([$customerEmail]);
    $verifiedRow = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$verifiedRow) {
        $db->rollBack();
        error_log('BOOKING FAILED: Customer email not found in database: ' . $customerEmail);
        echo json_encode(['success' => false, 'message' => 'Your account email is not properly registered. Please contact support.']);
        exit();
    }
    
    // Use the exact email from database to ensure consistency
    $customerEmail = $verifiedRow['customer_email'];
    error_log('Proceeding with booking for email: ' . $customerEmail);
    
    // Store in session for future use
    $_SESSION['customer_email'] = $customerEmail;
    
    $stmt = $db->prepare($query);
    
    // #region agent log
    $insertValues = [
        $bookingId,
        $customerEmail,
        $input['date'],
        $input['time'],
        $endDateTime->format('H:i:s'),
        $totalPrice,
        $input['specialRequests'] ?? ''
    ];
    error_log('Inserting booking with customer_email: ' . $customerEmail);
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_before_execute','timestamp'=>time()*1000,'location'=>'process_booking.php:170','message'=>'Before INSERT execute','data'=>['values_count'=>count($insertValues),'booking_id'=>$bookingId,'total_price'=>$totalPrice,'customer_email'=>$customerEmail],'sessionId'=>'debug-session','runId'=>'post-fix','hypothesisId'=>'D'])."\n", FILE_APPEND);
    // #endregion
    
    // #region agent log
    try {
        $stmt->execute($insertValues);
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_after_execute','timestamp'=>time()*1000,'location'=>'process_booking.php:175','message'=>'INSERT execute succeeded','data'=>['booking_id'=>$bookingId],'sessionId'=>'debug-session','runId'=>'post-fix','hypothesisId'=>'E'])."\n", FILE_APPEND);
    } catch (PDOException $insertEx) {
        // Get error info from statement if available, otherwise use exception info
        $errorInfo = $stmt->errorInfo() ?? null;
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_insert_error','timestamp'=>time()*1000,'location'=>'process_booking.php:177','message'=>'INSERT execute failed','data'=>['error_code'=>$insertEx->getCode(),'error_message'=>$insertEx->getMessage(),'sql_state'=>$errorInfo[0] ?? null],'sessionId'=>'debug-session','runId'=>'post-fix','hypothesisId'=>'F'])."\n", FILE_APPEND);
        throw $insertEx;
    }
    // #endregion
    
    // 6. Insert Booking Items (With Random Auto-Assign and Availability Check)
    // Use staff_email instead of staff_id (staff table uses staff_email as primary key)
    $queryItem = "INSERT INTO booking_service (booking_id, service_id, staff_email, quoted_duration_minutes, quoted_price) VALUES (?, ?, ?, ?, ?)";
    $stmtItem = $db->prepare($queryItem);
    
    // Query to find all capable staff for a service (from staff_service table)
    $allStaffQuery = "SELECT ss.staff_email 
                      FROM staff_service ss 
                      JOIN staff s ON ss.staff_email = s.staff_email 
                      WHERE ss.service_id = ? AND s.is_active = 1";
    $stmtAllStaff = $db->prepare($allStaffQuery);
    
    // Fallback query: Get service category and match by staff role
    $serviceCategoryQuery = "SELECT service_category FROM service WHERE service_id = ?";
    $stmtServiceCategory = $db->prepare($serviceCategoryQuery);
    
    // Fallback: Get staff by role matching service category
    $fallbackStaffQuery = "SELECT staff_email, role 
                          FROM staff 
                          WHERE is_active = 1 
                          AND (role LIKE ? OR role LIKE ? OR role LIKE ?)";
    $stmtFallbackStaff = $db->prepare($fallbackStaffQuery);
    
    // Query to check if a specific staff is available for a time slot
            $availabilityCheckQuery = "SELECT COUNT(*) as count 
                              FROM booking b
                              JOIN booking_service bs ON b.booking_id = bs.booking_id
                              WHERE b.booking_date = ? 
                              AND bs.staff_email = ?
                              AND b.status != 'cancelled'
                              AND b.start_time < ? 
                              AND b.expected_finish_time > ?";
    $stmtAvailability = $db->prepare($availabilityCheckQuery);

    // Track cumulative time for sequential service scheduling
    $currentServiceStart = clone $startDateTime;
    
    // Store service assignments for response
    $serviceAssignments = [];
    
    foreach($input['services'] as $serviceId) {
        // Calculate time slot for this specific service
        $serviceDuration = $serviceData[$serviceId]['duration_minutes'];
        $serviceStartTime = clone $currentServiceStart;
        $serviceEndTime = clone $currentServiceStart;
        $serviceEndTime->add(new DateInterval('PT' . $serviceDuration . 'M'));
        
        // staff[serviceId] contains staff_email (or 0/'0' for no preference)
        $staffValue = isset($input['staff'][$serviceId]) ? $input['staff'][$serviceId] : null;
        $staffEmail = ($staffValue && $staffValue != 0 && $staffValue != '0') ? $staffValue : null;
        
        // --- AUTO-ASSIGN LOGIC (No availability check for "No Preference") ---
        if ($staffEmail === null) {
            // Get all staff who can do this service
            $allCapableStaff = [];
            
            try {
                // First, try to get staff from staff_service table (most accurate)
                $stmtAllStaff->execute([$serviceId]);
                $allCapableStaff = $stmtAllStaff->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Error fetching staff from staff_service table: ' . $e->getMessage());
            }
            
            // If no staff found in staff_service table, try fallback by service category
            if (empty($allCapableStaff)) {
                try {
                    // Get service category
                    $stmtServiceCategory->execute([$serviceId]);
                    $serviceInfo = $stmtServiceCategory->fetch(PDO::FETCH_ASSOC);
                    $serviceCategory = $serviceInfo['service_category'] ?? '';
                    
                    // Map service categories to staff roles
                    $rolePatterns = [];
                    $categoryLower = strtolower($serviceCategory);
                    
                    if (strpos($categoryLower, 'facial') !== false) {
                        $rolePatterns = ['%beautician%', '%beauty%', '%facial%'];
                    } elseif (strpos($categoryLower, 'massage') !== false) {
                        $rolePatterns = ['%massage%', '%therapist%'];
                    } elseif (strpos($categoryLower, 'hair') !== false || strpos($categoryLower, 'styling') !== false) {
                        $rolePatterns = ['%hair%', '%stylist%'];
                    } elseif (strpos($categoryLower, 'nail') !== false || strpos($categoryLower, 'manicure') !== false) {
                        $rolePatterns = ['%nail%', '%technician%'];
                    }
                    
                    // If we have role patterns, try to match staff
                    if (!empty($rolePatterns)) {
                        foreach ($rolePatterns as $pattern) {
                            $stmtFallbackStaff->execute([$pattern, $pattern, $pattern]);
                            $fallbackStaff = $stmtFallbackStaff->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($fallbackStaff)) {
                                $allCapableStaff = array_merge($allCapableStaff, $fallbackStaff);
                            }
                        }
                        // Remove duplicates
                        $uniqueEmails = array_unique(array_column($allCapableStaff, 'staff_email'));
                        $allCapableStaff = array_map(function($email) {
                            return ['staff_email' => $email];
                        }, $uniqueEmails);
                    }
                } catch (Exception $e) {
                    error_log('Error in fallback staff matching: ' . $e->getMessage());
                }
            }
            
            // Last resort: Get all active staff if still no matches
            if (empty($allCapableStaff)) {
                try {
                    $fallbackQuery = "SELECT staff_email FROM staff WHERE is_active = 1";
                    $fallbackStmt = $db->prepare($fallbackQuery);
                    $fallbackStmt->execute();
                    $allCapableStaff = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    error_log('Error fetching all active staff: ' . $e->getMessage());
                }
            }
            
            if (empty($allCapableStaff)) {
                $db->rollBack();
                error_log('No staff found for service: ' . $serviceId);
                echo json_encode(['success' => false, 'message' => 'No staff available for service. Please contact the salon.']);
                exit();
            }
            
            // For "No Preference", find available staff on the selected date
            $serviceStartStr = $serviceStartTime->format('H:i:s');
            $serviceEndStr = $serviceEndTime->format('H:i:s');
            $availableStaff = [];
            
            foreach ($allCapableStaff as $staff) {
                $checkEmail = $staff['staff_email'];
                $stmtAvailability->execute([
                    $input['date'], 
                    $checkEmail, 
                    $serviceEndStr, 
                    $serviceStartStr
                ]);
                $availabilityResult = $stmtAvailability->fetch(PDO::FETCH_ASSOC);
                
                // If count is 0, staff is available
                if (intval($availabilityResult['count']) == 0) {
                    $availableStaff[] = $checkEmail;
                }
            }
            
            // If no available staff found, use all capable staff (fallback)
            if (empty($availableStaff)) {
                $availableStaff = array_column($allCapableStaff, 'staff_email');
            }
            
            // Randomly select from available staff
            $staffEmail = $availableStaff[array_rand($availableStaff)];
        } else {
            // Staff was explicitly selected - verify availability
            $serviceStartStr = $serviceStartTime->format('H:i:s');
            $serviceEndStr = $serviceEndTime->format('H:i:s');
            
            $stmtAvailability->execute([
                $input['date'], 
                $staffEmail, 
                $serviceEndStr, 
                $serviceStartStr
            ]);
            $availabilityResult = $stmtAvailability->fetch(PDO::FETCH_ASSOC);
            
            if(intval($availabilityResult['count']) > 0) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Selected staff is not available for this time slot. Please choose another time or staff.']);
                exit();
            }
        }
        // --- AUTO-ASSIGN LOGIC END ---
        
        $stmtItem->execute([
            $bookingId,
            $serviceId,
            $staffEmail,
            $serviceData[$serviceId]['duration_minutes'],
            $serviceData[$serviceId]['price']
        ]);
        
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
        
        // Store service assignment for response
        $serviceAssignments[] = [
            'service_id' => $serviceId,
            'service_name' => $serviceData[$serviceId]['service_name'] ?? 'Service',
            'staff_email' => $staffEmail,
            'staff_name' => $staffName,
            'duration' => $serviceData[$serviceId]['duration_minutes'],
            'price' => $serviceData[$serviceId]['price']
        ];
        
        // Move to next service start time (sequential scheduling)
        $currentServiceStart = $serviceEndTime;
    }
    
    $db->commit();
    
    // Send email logic (Placeholder)
    // sendConfirmationEmail(...);
    
    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'services' => $serviceAssignments
    ]);
    
} catch(Exception $e) {
    if(isset($db)) $db->rollBack();
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['id'=>'log_'.time().'_error','timestamp'=>time()*1000,'location'=>'process_booking.php:345','message'=>'Exception caught','data'=>['error'=>$e->getMessage(),'code'=>$e->getCode(),'file'=>$e->getFile(),'line'=>$e->getLine()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
    // #endregion
    
    error_log('Booking error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    error_log('Input data: ' . json_encode($input ?? []));
    error_log('Session customer_id: ' . ($_SESSION['customer_id'] ?? 'NOT SET'));
    
    // Provide more detailed error message for debugging
    $errorMessage = 'Booking failed. Please try again or contact support if the problem persists.';
    $errorDetail = $e->getMessage();
    
    // Add specific error messages for common issues
    if(strpos($errorDetail, 'services') !== false || strpos($errorDetail, 'service') !== false) {
        $errorMessage = 'Error with selected services. Please refresh the page and try again.';
    } elseif(strpos($errorDetail, 'staff') !== false) {
        $errorMessage = 'Error with staff selection. Please reselect staff and try again.';
    } elseif(strpos($errorDetail, 'date') !== false || strpos($errorDetail, 'time') !== false) {
        $errorMessage = 'Error with selected date/time. Please select a different time slot.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'error' => $errorDetail // Remove this in production for security
    ]);
}

function sendConfirmationEmail($customerId, $bookingId) {
    global $db; // Use existing connection

    // 1. Get Customer & Booking Info
    $query = "SELECT b.*, c.first_name, c.email FROM booking b 
              JOIN customer c ON b.customer_id = c.customer_id 
              WHERE b.booking_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Get Services
    $querySvc = "SELECT s.name, bs.price, bs.duration_minutes FROM booking_service bs 
                 JOIN service s ON bs.service_id = s.service_id 
                 WHERE bs.booking_id = ?";
    $stmtSvc = $db->prepare($querySvc);
    $stmtSvc->execute([$bookingId]);
    $services = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

    // 3. Build HTML Email
    $total = number_format($booking['grand_total'], 2);
    $date = date('d M Y', strtotime($booking['booking_date']));
    $time = date('h:i A', strtotime($booking['start_time']));

    $serviceRows = "";
    foreach($services as $s) {
        $price = number_format($s['price'], 2);
        $serviceRows .= "<tr>
            <td style='padding:8px; border-bottom:1px solid #ddd;'>{$s['name']} ({$s['duration_minutes']}m)</td>
            <td style='padding:8px; border-bottom:1px solid #ddd; text-align:right;'>RM {$price}</td>
        </tr>";
    }

    $emailBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
        <div style='background: #D4AF37; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
            <h2 style='color: white; margin:0;'>Booking Confirmed!</h2>
        </div>
        <div style='padding: 20px;'>
            <p>Hi <strong>{$booking['first_name']}</strong>,</p>
            <p>Your appointment is confirmed. Here are the details:</p>
            
            <div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Booking ID:</strong> $bookingId</p>
                <p style='margin: 5px 0;'><strong>Date:</strong> $date</p>
                <p style='margin: 5px 0;'><strong>Time:</strong> $time</p>
            </div>

            <table style='width:100%; border-collapse: collapse;'>
                <thead>
                    <tr style='background:#f0f0f0;'>
                        <th style='padding:8px; text-align:left;'>Service</th>
                        <th style='padding:8px; text-align:right;'>Price</th>
                    </tr>
                </thead>
                <tbody>
                    $serviceRows
                </tbody>
                <tfoot>
                    <tr>
                        <td style='padding:10px; text-align:right; font-weight:bold;'>Total (inc. SST):</td>
                        <td style='padding:10px; text-align:right; font-weight:bold; color:#D4AF37;'>RM $total</td>
                    </tr>
                </tfoot>
            </table>
            
            <p style='margin-top: 30px; font-size: 12px; color: #777;'>
                Please arrive 10 minutes early. Payment will be collected at the salon.
            </p>
        </div>
    </div>";

    // 4. Send Email using our Helper
    require_once 'send_mail.php';

    // Get Customer Email
    $stmt = $db->prepare("SELECT email, first_name FROM customer WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch();

    if ($user) {
        sendEmail($user['email'], "Booking Confirmation - $bookingId", $emailBody);
    }
}
?>