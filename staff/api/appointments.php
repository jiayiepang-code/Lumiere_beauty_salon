<?php
require_once '../config.php';

checkAuth();

$staff_email = $_SESSION['staff_id']; // staff_id is actually staff_email from login

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get appointments for a specific date or month
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    $month = isset($_GET['month']) ? $_GET['month'] : null;
    
    try {
        // Determine the SQL WHERE clause based on date or month
        $where_clause = 'bs.staff_email = ?';
        $params = [$staff_email];
        
        if ($month) {
            // Format: YYYY-MM
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                jsonResponse(['error' => 'Invalid month format. Use YYYY-MM'], 400);
            }
            // Calculate start and end dates for the month (database uses yyyy-mm-dd format)
            $month_start = $month . '-01'; // First day of month
            $month_end = date('Y-m-t', strtotime($month_start)); // Last day of month
            $where_clause .= ' AND b.booking_date >= ? AND b.booking_date <= ?';
            $params[] = $month_start;
            $params[] = $month_end;
        } elseif ($date) {
            // Specific date format: YYYY-MM-DD
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                jsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD'], 400);
            }
            $where_clause .= ' AND b.booking_date = ?';
            $params[] = $date;
        } else {
            // Default to today
            $where_clause .= ' AND b.booking_date = ?';
            $params[] = date('Y-m-d');
        }
        
        // Use ERD structure with spaces in table names (wrapped in backticks)
        $stmt = $pdo->prepare("SELECT 
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.expected_finish_time,
            b.status,
            b.total_price,
            b.remarks,
            bs.booking_service_id,
            bs.quoted_price,
            bs.quoted_duration_minutes,
            bs.quoted_cleanup_minutes,
            bs.quantity,
            bs.sequence_order,
            bs.service_status,
            bs.special_request,
            s.service_id,
            s.service_name,
            s.service_category,
            s.sub_category,
            s.current_price,
            s.current_duration_minutes,
            c.customer_email,
            c.first_name,
            c.last_name,
            c.phone
            FROM `booking_service` bs
            INNER JOIN `booking` b ON bs.booking_id = b.booking_id
            INNER JOIN `service` s ON bs.service_id = s.service_id
            INNER JOIN `customer` c ON b.customer_email = c.customer_email
            WHERE {$where_clause}
            ORDER BY b.booking_date, b.start_time, bs.sequence_order");
        
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        // Process results into appointments format
        $appointments = [];
        foreach ($results as $row) {
            $booking_id = $row['booking_id'];
            
            if (!isset($appointments[$booking_id])) {
                $appointments[$booking_id] = [
                    'booking_id' => $booking_id,
                    'date' => $row['booking_date'],
                    'date_formatted' => date('d/m/Y', strtotime($row['booking_date'])),
                    'time' => $row['start_time'],
                    'time_formatted' => date('h:i A', strtotime($row['start_time'])),
                    'expected_finish_time' => isset($row['expected_finish_time']) ? $row['expected_finish_time'] : null,
                    'status' => $row['status'],
                    'total_price' => $row['total_price'],
                    'remarks' => $row['remarks'] ?? null,
                    'customer_email' => $row['customer_email'] ?? null,
                    'customer_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'customer_phone' => $row['phone'] ?? null,
                    'services' => []
                ];
            }
            
            // Add service details
            $appointments[$booking_id]['services'][] = [
                'booking_service_id' => $row['booking_service_id'] ?? $row['service_id'],
                'service_id' => $row['service_id'],
                'service_name' => $row['service_name'],
                'service_category' => $row['service_category'] ?? '',
                'sub_category' => $row['sub_category'] ?? '',
                'quoted_price' => $row['quoted_price'] ?? $row['current_price'],
                'quoted_duration_minutes' => $row['quoted_duration_minutes'] ?? $row['current_duration_minutes'],
                'quoted_cleanup_minutes' => $row['quoted_cleanup_minutes'] ?? 0,
                'quantity' => $row['quantity'] ?? 1,
                'sequence_order' => $row['sequence_order'] ?? 1,
                'service_status' => $row['service_status'] ?? $row['status'],
                'special_request' => $row['special_request'] ?? null
            ];
        }
        
        // Convert to indexed array and calculate total duration
        $appointments_list = [];
        foreach ($appointments as $booking_id => $appt) {
            $total_duration = 0;
            $service_names = [];
            foreach ($appt['services'] as $service) {
                $total_duration += ($service['quoted_duration_minutes'] + $service['quoted_cleanup_minutes']) * $service['quantity'];
                $service_names[] = $service['service_name'];
            }
            
            $appt['duration_minutes'] = $total_duration;
            $appt['duration_formatted'] = $total_duration . ' min';
            $appt['service_names'] = implode(', ', $service_names);
            // Use first service's special request if available
            $appt['special_request'] = !empty($appt['services'][0]['special_request']) 
                ? $appt['services'][0]['special_request'] 
                : null;
            
            $appointments_list[] = $appt;
        }
        
        jsonResponse(['success' => true, 'appointments' => $appointments_list]);
        
    } catch(PDOException $e) {
        // #region agent log
        $log_data = ['message' => 'Appointments API Exception', 'error' => $e->getMessage(), 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'hypothesisId' => 'C'];
        file_put_contents('c:\\xampp\\htdocs\\Lumiere-beauty-salon\\.cursor\\debug.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion
        error_log("Appointments API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch appointments: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update booking status
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['booking_id']) || !isset($data['status'])) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }
    
    $booking_id = $data['booking_id'];
    $status = $data['status'];
    
    // Validate status
    $valid_statuses = ['confirmed', 'completed', 'cancelled', 'no_show'];
    if (!in_array($status, $valid_statuses)) {
        jsonResponse(['error' => 'Invalid status'], 400);
    }
    
    try {
        // Verify staff is assigned to this booking
        $check_stmt = $pdo->prepare("SELECT bs.booking_service_id 
                                     FROM `booking_service` bs
                                     INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                     WHERE b.booking_id = ? AND bs.staff_email = ?");
        $check_stmt->execute([$booking_id, $staff_email]);
        
        if ($check_stmt->rowCount() == 0) {
            jsonResponse(['error' => 'Booking not found or not authorized'], 404);
        }
        
        // Update booking status
        $stmt = $pdo->prepare("UPDATE `booking` SET status = ?, updated_at = NOW() WHERE booking_id = ?");
        $stmt->execute([$status, $booking_id]);
        
        // Also update service status if needed
        if ($status === 'completed') {
            $update_service = $pdo->prepare("UPDATE `booking_service` 
                                           SET service_status = 'completed' 
                                           WHERE booking_id = ? AND staff_email = ?");
            $update_service->execute([$booking_id, $staff_email]);
        }
        
        jsonResponse(['success' => true, 'message' => 'Booking status updated successfully']);
        
    } catch(PDOException $e) {
        // #region agent log
        $log_data = ['message' => 'Update Appointment Exception', 'error' => $e->getMessage(), 'code' => $e->getCode(), 'hypothesisId' => 'C'];
        file_put_contents('c:\\xampp\\htdocs\\Lumiere-beauty-salon\\.cursor\\debug.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion
        error_log("Update Appointment Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update booking status: ' . $e->getMessage()], 500);
    }
}
?>
