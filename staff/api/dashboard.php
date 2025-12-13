<?php
require_once '../config.php';

checkAuth();

$staff_email = $_SESSION['staff_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get today's date
        $today = date('Y-m-d');
        
        // 1. Get today's total appointments
        $stmt_total = $pdo->prepare("
            SELECT COUNT(DISTINCT b.booking_id) as total
            FROM `booking_service` bs
            INNER JOIN `booking` b ON bs.booking_id = b.booking_id
            WHERE bs.staff_email = ? AND b.booking_date = ?
        ");
        $stmt_total->execute([$staff_email, $today]);
        $total_result = $stmt_total->fetch();
        $today_total = $total_result['total'] ?? 0;
        
        // 2. Get today's completed appointments
        $stmt_completed = $pdo->prepare("
            SELECT COUNT(DISTINCT b.booking_id) as completed
            FROM `booking_service` bs
            INNER JOIN `booking` b ON bs.booking_id = b.booking_id
            WHERE bs.staff_email = ? AND b.booking_date = ? AND b.status = 'completed'
        ");
        $stmt_completed->execute([$staff_email, $today]);
        $completed_result = $stmt_completed->fetch();
        $today_completed = $completed_result['completed'] ?? 0;
        
        // 3. Get today's remaining appointments (confirmed or in progress)
        $stmt_remaining = $pdo->prepare("
            SELECT COUNT(DISTINCT b.booking_id) as remaining
            FROM `booking_service` bs
            INNER JOIN `booking` b ON bs.booking_id = b.booking_id
            WHERE bs.staff_email = ? AND b.booking_date = ? AND b.status IN ('confirmed')
        ");
        $stmt_remaining->execute([$staff_email, $today]);
        $remaining_result = $stmt_remaining->fetch();
        $today_remaining = $remaining_result['remaining'] ?? 0;
        
        // 4. Get upcoming bookings for notifications (today + next 7 days)
        $future_date = date('Y-m-d', strtotime('+7 days'));
        $stmt_upcoming = $pdo->prepare("
            SELECT 
                b.booking_id,
                b.booking_date,
                b.start_time,
                b.status,
                c.first_name,
                c.last_name,
                GROUP_CONCAT(s.service_name SEPARATOR ', ') as service_names
            FROM `booking_service` bs
            INNER JOIN `booking` b ON bs.booking_id = b.booking_id
            INNER JOIN `customer` c ON b.customer_email = c.customer_email
            INNER JOIN `service` s ON bs.service_id = s.service_id
            WHERE bs.staff_email = ? AND b.booking_date BETWEEN ? AND ? AND b.status = 'confirmed'
            GROUP BY b.booking_id
            ORDER BY b.booking_date ASC, b.start_time ASC
            LIMIT 5
        ");
        $stmt_upcoming->execute([$staff_email, $today, $future_date]);
        $upcoming_bookings = $stmt_upcoming->fetchAll();
        
        // Format upcoming bookings for notifications
        $notifications = [];
        foreach ($upcoming_bookings as $booking) {
            $booking_date = new DateTime($booking['booking_date']);
            $booking_time = new DateTime($booking['start_time']);
            $time_str = $booking_time->format('h:i A');
            $date_str = $booking_date->format('M d');
            $customer_name = trim($booking['first_name'] . ' ' . $booking['last_name']);
            
            $notifications[] = [
                'booking_id' => $booking['booking_id'],
                'message' => "Upcoming booking: {$customer_name} at {$time_str} ({$booking['service_names']})",
                'time' => $date_str,
                'status' => $booking['status']
            ];
        }
        
        jsonResponse([
            'success' => true,
            'stats' => [
                'today_total' => intval($today_total),
                'today_completed' => intval($today_completed),
                'today_remaining' => intval($today_remaining)
            ],
            'notifications' => $notifications
        ]);
        
    } catch(PDOException $e) {
        error_log("Dashboard API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch dashboard data: ' . $e->getMessage()], 500);
    }
}
?>
