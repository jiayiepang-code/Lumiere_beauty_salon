<?php
// check_availability.php
// Suppress all output and errors to ensure clean JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // Start output buffering to catch any unwanted output

require_once 'config/database.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$mode = $input['mode'] ?? ''; // 'slots' or 'staff'
$date = $input['date'] ?? '';
$services = $input['services'] ?? []; // Array of service IDs (for Step 3)
$startTime = $input['startTime'] ?? ''; // "10:00" (for Step 3)

// CONFIG
$BUFFER_PER_SERVICE = 15; // minutes

if ($mode === 'slots') {
    // STEP 1: Check which time slots (10:00 - 22:00) have AT LEAST ONE staff free
    // Note: Since we don't know duration yet, we usually just check if start time is free
    
    try {
        // 1. Get all staff
        $stmt = $db->query("SELECT staff_email FROM staff WHERE is_active = 1");
        if ($stmt === false) {
            throw new Exception('Failed to query staff');
        }
        $allStaff = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $totalStaffCount = count($allStaff);

        // 2. Get all appointments for this date
        // We look for any appointment that COVERS the specific 30-min slot start times
        $query = "SELECT start_time, end_time, staff_id FROM appointment WHERE date = :date AND status != 'cancelled'";
        $stmt = $db->prepare($query);
        if ($stmt === false) {
            throw new Exception('Failed to prepare appointment query');
        }
        $stmt->execute([':date' => $date]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $disabledSlots = [];
        $start = 10 * 60; // 10:00 in minutes
        $end = 22 * 60;   // 22:00 in minutes

        for ($time = $start; $time < $end; $time += 30) {
            $busyStaffCount = 0;
            
            foreach ($appointments as $appt) {
                $apptStart = timeToMinutes($appt['start_time']);
                $apptEnd = timeToMinutes($appt['end_time']); // Should already include buffer from DB
                
                // If the appointment covers this specific slot start time
                if ($time >= $apptStart && $time < $apptEnd) {
                    $busyStaffCount++;
                }
            }

            // If ALL staff are busy at this time, disable the slot
            if ($busyStaffCount >= $totalStaffCount) {
                $h = floor($time / 60);
                $m = $time % 60;
                $slotStr = sprintf("%02d:%02d", $h, $m);
                $disabledSlots[] = $slotStr;
            }
        }

        ob_end_clean();
        echo json_encode(['disabled_slots' => $disabledSlots]);
        exit;
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['error' => 'Error checking slots: ' . $e->getMessage()]);
        exit;
    }

} elseif ($mode === 'staff') {
    // STEP 3: Filter staff based on Total Duration + Buffer
    
    try {
        // 1. Calculate Duration & Buffer
        $totalDuration = 0;
        $serviceCount = count($services);
        
        // Fetch duration for selected services
        if(!empty($services)) {
            $placeholders = implode(',', array_fill(0, count($services), '?'));
            $stmt = $db->prepare("SELECT current_duration_minutes FROM service WHERE service_id IN ($placeholders)");
            if ($stmt === false) {
                throw new Exception('Failed to prepare service query');
            }
            $stmt->execute($services);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $totalDuration += $row['current_duration_minutes'];
            }
        }

        $totalBuffer = $serviceCount * $BUFFER_PER_SERVICE;
        $totalBlockTime = $totalDuration + $totalBuffer;

        $reqStart = timeToMinutes($startTime);
        $reqEnd = $reqStart + $totalBlockTime;

        // 2. Check each staff for overlaps
        $query = "SELECT staff_email, start_time, end_time 
                  FROM appointment 
                  WHERE date = :date AND status != 'cancelled'
                  AND staff_id IN (SELECT staff_email FROM staff WHERE is_active = 1)";
        
        $stmt = $db->prepare($query);
        if ($stmt === false) {
            throw new Exception('Failed to prepare appointment query');
        }
        $stmt->execute([':date' => $date]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get list of all active staff first
        $stmtStaff = $db->query("SELECT staff_email FROM staff WHERE is_active = 1");
        if ($stmtStaff === false) {
            throw new Exception('Failed to query active staff');
        }
        $availableStaff = $stmtStaff->fetchAll(PDO::FETCH_COLUMN);

        $busyStaff = [];

        foreach ($appointments as $appt) {
            $apptStart = timeToMinutes($appt['start_time']);
            $apptEnd = timeToMinutes($appt['end_time']); // Includes their stored buffer

            // Check Overlap: (StartA < EndB) and (EndA > StartB)
            if ($reqStart < $apptEnd && $reqEnd > $apptStart) {
                if (!in_array($appt['staff_email'], $busyStaff)) {
                    $busyStaff[] = $appt['staff_email']; // Mark this staff as busy
                }
            }
        }

        // Remove busy staff from available list
        $finalAvailable = array_values(array_diff($availableStaff, $busyStaff));

        ob_end_clean();
        echo json_encode([
            'available_staff' => $finalAvailable,
            'debug_info' => [
                'total_duration' => $totalDuration,
                'buffer' => $totalBuffer,
                'block_minutes' => $totalBlockTime
            ]
        ]);
        exit;
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['error' => 'Error checking staff: ' . $e->getMessage()]);
        exit;
    }
} else {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid mode']);
    exit;
}

function timeToMinutes($timeStr) {
    list($h, $m) = explode(':', $timeStr);
    return ($h * 60) + $m;
}
?>