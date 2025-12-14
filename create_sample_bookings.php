<?php
/**
 * Sample Booking Data Creator for jenniekim@gmail.com
 * This script creates sample booking history data based on the provided structure
 * 
 * Usage: Run this script from command line or via browser
 * Command line: php create_sample_bookings.php
 * Browser: Navigate to http://localhost/Lumiere_beauty_salon/create_sample_bookings.php
 */

require_once 'config/database.php';

// Allow running from browser
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

$database = new Database();
$db = $database->getConnection();

// Get customer_email for jenniekim@gmail.com (customer table uses email as primary key, no customer_id)
$customerQuery = "SELECT customer_email FROM customer WHERE customer_email = 'jenniekim@gmail.com' LIMIT 1";
$customerStmt = $db->prepare($customerQuery);
$customerStmt->execute();
$customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die("Error: Customer jenniekim@gmail.com not found in database. Please create the customer first.\n");
}

$customerEmail = $customer['customer_email'];

echo "Creating sample bookings for: $customerEmail\n\n";

// Sample bookings data
$sampleBookings = [
    // Upcoming appointment
    [
        'booking_id' => 'BK001320',
        'booking_date' => '2025-12-18',
        'start_time' => '14:00:00',
        'expected_finish_time' => '15:00:00',
        'status' => 'confirmed',
        'total_price' => 50.00,
        'remarks' => NULL,
        'service_name' => 'Haircut',
        'staff_name' => 'Sarah Johnson',
        'duration_minutes' => 60,
        'service_price' => 50.00
    ],
    
    // Recent appointments
    [
        'booking_id' => 'BK001321',
        'booking_date' => '2025-12-05',
        'start_time' => '10:00:00',
        'expected_finish_time' => '11:00:00',
        'status' => 'completed',
        'total_price' => 80.00,
        'remarks' => NULL,
        'service_name' => 'Manicure',
        'staff_name' => 'Emma Lee',
        'duration_minutes' => 60,
        'service_price' => 80.00
    ],
    [
        'booking_id' => 'BK001322',
        'booking_date' => '2025-11-20',
        'start_time' => '14:00:00',
        'expected_finish_time' => '15:30:00',
        'status' => 'completed',
        'total_price' => 150.00,
        'remarks' => NULL,
        'service_name' => 'Facial',
        'staff_name' => 'Lisa Wang',
        'duration_minutes' => 90,
        'service_price' => 150.00
    ],
    [
        'booking_id' => 'BK001323',
        'booking_date' => '2025-11-08',
        'start_time' => '15:00:00',
        'expected_finish_time' => '16:00:00',
        'status' => 'completed',
        'total_price' => 50.00,
        'remarks' => NULL,
        'service_name' => 'Haircut',
        'staff_name' => 'Sarah Johnson',
        'duration_minutes' => 60,
        'service_price' => 50.00
    ],
    
    // Additional bookings from the sample data structure
    [
        'booking_id' => 'BK001324',
        'booking_date' => '2025-12-19',
        'start_time' => '16:30:00',
        'expected_finish_time' => '18:15:00',
        'status' => 'confirmed',
        'total_price' => 168.00,
        'remarks' => NULL,
        'service_name' => 'Basic Styling',
        'staff_name' => 'Jay Chen',
        'duration_minutes' => 45,
        'service_price' => 30.00
    ],
    [
        'booking_id' => 'BK001325',
        'booking_date' => '2025-12-27',
        'start_time' => '11:00:00',
        'expected_finish_time' => '15:00:00',
        'status' => 'confirmed',
        'total_price' => 427.18,
        'remarks' => NULL,
        'service_name' => 'Men Haircut',
        'staff_name' => 'Mike Lee',
        'duration_minutes' => 45,
        'service_price' => 35.00
    ],
    [
        'booking_id' => 'BK001326',
        'booking_date' => '2025-12-18',
        'start_time' => '10:30:00',
        'expected_finish_time' => '14:15:00',
        'status' => 'cancelled',
        'total_price' => 318.00,
        'remarks' => 'Cancelled by customer',
        'service_name' => 'Basic Styling',
        'staff_name' => 'Sarah Lee',
        'duration_minutes' => 45,
        'service_price' => 30.00
    ]
];

// Check if booking table has customer_id column
$checkColumnQuery = "SHOW COLUMNS FROM `booking` LIKE 'customer_id'";
$checkColumnStmt = $db->prepare($checkColumnQuery);
$checkColumnStmt->execute();
$hasCustomerIdColumn = $checkColumnStmt->rowCount() > 0;

// Get service IDs and staff emails
$serviceMap = [];
$staffMap = [];

// Get services
$serviceQuery = "SELECT service_id, service_name FROM service WHERE is_active = 1";
$serviceStmt = $db->prepare($serviceQuery);
$serviceStmt->execute();
while ($row = $serviceStmt->fetch(PDO::FETCH_ASSOC)) {
    $serviceMap[strtolower($row['service_name'])] = $row['service_id'];
}

// Get staff
$staffQuery = "SELECT staff_email, CONCAT(first_name, ' ', last_name) as full_name FROM staff WHERE is_active = 1";
$staffStmt = $db->prepare($staffQuery);
$staffStmt->execute();
while ($row = $staffStmt->fetch(PDO::FETCH_ASSOC)) {
    $staffMap[$row['full_name']] = $row['staff_email'];
}

$db->beginTransaction();

try {
    $insertedCount = 0;
    
    foreach ($sampleBookings as $booking) {
        // Check if booking already exists
        $checkQuery = "SELECT booking_id FROM booking WHERE booking_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$booking['booking_id']]);
        
        if ($checkStmt->fetch()) {
            echo "Skipping {$booking['booking_id']} - already exists\n";
            continue;
        }
        
        // Insert booking (customer table uses email as primary key, no customer_id)
        $insertQuery = "INSERT INTO `booking` (
            booking_id, customer_email, booking_date, start_time, 
            expected_finish_time, status, total_price, remarks, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $insertValues = [
            $booking['booking_id'],
            $customerEmail,
            $booking['booking_date'],
            $booking['start_time'],
            $booking['expected_finish_time'],
            $booking['status'],
            $booking['total_price'],
            $booking['remarks']
        ];
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute($insertValues);
        
        // Get service_id
        $serviceName = strtolower($booking['service_name']);
        $serviceId = null;
        
        // Try exact match first
        if (isset($serviceMap[$serviceName])) {
            $serviceId = $serviceMap[$serviceName];
        } else {
            // Try partial match
            foreach ($serviceMap as $name => $id) {
                if (stripos($name, $serviceName) !== false || stripos($serviceName, $name) !== false) {
                    $serviceId = $id;
                    break;
                }
            }
        }
        
        // If still not found, use first available service
        if (!$serviceId && !empty($serviceMap)) {
            $serviceId = reset($serviceMap);
            echo "Warning: Service '{$booking['service_name']}' not found, using first available service\n";
        }
        
        // Get staff_email
        $staffEmail = null;
        if (isset($staffMap[$booking['staff_name']])) {
            $staffEmail = $staffMap[$booking['staff_name']];
        } else {
            // Try to find any active staff
            $anyStaffQuery = "SELECT staff_email FROM staff WHERE is_active = 1 LIMIT 1";
            $anyStaffStmt = $db->prepare($anyStaffQuery);
            $anyStaffStmt->execute();
            $anyStaff = $anyStaffStmt->fetch(PDO::FETCH_ASSOC);
            $staffEmail = $anyStaff['staff_email'] ?? null;
            echo "Warning: Staff '{$booking['staff_name']}' not found, using first available staff\n";
        }
        
        if ($serviceId && $staffEmail) {
            // Insert booking_service
            $bookingServiceQuery = "INSERT INTO `booking_service` (
                booking_id, service_id, staff_email, quoted_duration_minutes, quoted_price
            ) VALUES (?, ?, ?, ?, ?)";
            
            $bookingServiceStmt = $db->prepare($bookingServiceQuery);
            $bookingServiceStmt->execute([
                $booking['booking_id'],
                $serviceId,
                $staffEmail,
                $booking['duration_minutes'],
                $booking['service_price']
            ]);
            
            echo "Created booking: {$booking['booking_id']} - {$booking['service_name']} on {$booking['booking_date']}\n";
            $insertedCount++;
        } else {
            echo "Error: Could not create booking_service for {$booking['booking_id']} - missing service or staff\n";
        }
    }
    
    $db->commit();
    echo "\nSuccessfully created $insertedCount sample bookings for $customerEmail\n";
    
    if (php_sapi_name() !== 'cli') {
        echo "\n\n<a href='user/dashboard.php?section=bookings'>View Bookings in Dashboard</a>\n";
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    if (php_sapi_name() !== 'cli') {
        echo "</pre>";
    }
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}

