<?php
// This is a backup version that uses the simpler appointments table structure
// If your database uses the ERD structure (salon_booking, salon_booking_service), 
// please let me know and I'll update the main performance.php file

require_once '../config.php';

checkAuth();

$staff_email = $_SESSION['staff_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
    
    try {
        // Detect if Staff table uses email or id
        $uses_staff_email = false;
        try {
            $pdo->query("SELECT staff_email FROM Staff LIMIT 1");
            $uses_staff_email = true;
        } catch(PDOException $e) {
            $uses_staff_email = false;
        }
        
        $current_date = date('Y-m-d');
        
        if ($period === 'month') {
            $start_date = date('Y-m-01');
        } elseif ($period === 'week') {
            $start_date = date('Y-m-d', strtotime('monday this week'));
        } else {
            $start_date = date('Y-01-01');
        }
        
        $metrics = [];
        
        // Total completed appointments
        if ($uses_staff_email) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                  FROM appointments a
                                  WHERE a.staff_id = ? AND a.status = 'completed' 
                                  AND a.appointment_date >= ? AND a.appointment_date <= ?");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                  FROM appointments a
                                  INNER JOIN staff st ON a.staff_id = st.id
                                  WHERE st.email = ? AND a.status = 'completed' 
                                  AND a.appointment_date >= ? AND a.appointment_date <= ?");
        }
        $stmt->execute([$staff_email, $start_date, $current_date]);
        $metrics['completed_appointments'] = (int)$stmt->fetchColumn();
        
        // Total revenue
        if ($uses_staff_email) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(a.total_price), 0) as total 
                                  FROM appointments a
                                  WHERE a.staff_id = ? AND a.status = 'completed' 
                                  AND a.appointment_date >= ? AND a.appointment_date <= ?");
        } else {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(a.total_price), 0) as total 
                                  FROM appointments a
                                  INNER JOIN staff st ON a.staff_id = st.id
                                  WHERE st.email = ? AND a.status = 'completed' 
                                  AND a.appointment_date >= ? AND a.appointment_date <= ?");
        }
        $stmt->execute([$staff_email, $start_date, $current_date]);
        $metrics['total_revenue'] = (float)$stmt->fetchColumn();
        
        // Completed appointments for specific date
        if ($uses_staff_email) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                  FROM appointments a
                                  WHERE a.staff_id = ? AND a.status = 'completed' 
                                  AND a.appointment_date = ?");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                  FROM appointments a
                                  INNER JOIN staff st ON a.staff_id = st.id
                                  WHERE st.email = ? AND a.status = 'completed' 
                                  AND a.appointment_date = ?");
        }
        $stmt->execute([$staff_email, $date]);
        $metrics['completed_today'] = (int)$stmt->fetchColumn();
        
        // Unique clients
        if ($uses_staff_email) {
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.customer_id) as total 
                                  FROM appointments a
                                  WHERE a.staff_id = ? AND a.appointment_date >= ? AND a.appointment_date <= ?");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.customer_id) as total 
                                  FROM appointments a
                                  INNER JOIN staff st ON a.staff_id = st.id
                                  WHERE st.email = ? AND a.appointment_date >= ? AND a.appointment_date <= ?");
        }
        $stmt->execute([$staff_email, $start_date, $current_date]);
        $metrics['unique_clients'] = (int)$stmt->fetchColumn();
        
        // Service distribution
        if ($uses_staff_email) {
            $stmt = $pdo->prepare("SELECT s.name as service_name, COUNT(*) as count 
                                  FROM appointments a
                                  INNER JOIN services s ON a.service_id = s.id
                                  WHERE a.staff_id = ? AND a.status = 'completed'
                                  AND a.appointment_date >= ? AND a.appointment_date <= ?
                                  GROUP BY s.id, s.name 
                                  ORDER BY count DESC");
        } else {
            $stmt = $pdo->prepare("SELECT s.name as service_name, COUNT(*) as count 
                                  FROM appointments a
                                  INNER JOIN services s ON a.service_id = s.id
                                  INNER JOIN staff st ON a.staff_id = st.id
                                  WHERE st.email = ? AND a.status = 'completed'
                                  AND a.appointment_date >= ? AND a.appointment_date <= ?
                                  GROUP BY s.id, s.name 
                                  ORDER BY count DESC");
        }
        $stmt->execute([$staff_email, $start_date, $current_date]);
        $service_distribution = $stmt->fetchAll();
        
        // Recent completed sessions
        if ($uses_staff_email) {
            $stmt = $pdo->prepare("SELECT 
                                  a.id,
                                  a.appointment_date,
                                  a.appointment_time,
                                  a.status,
                                  c.first_name,
                                  c.last_name,
                                  s.name as service_name
                                  FROM appointments a
                                  INNER JOIN customers c ON a.customer_id = c.id
                                  INNER JOIN services s ON a.service_id = s.id
                                  WHERE a.staff_id = ? AND a.status = 'completed'
                                  ORDER BY a.appointment_date DESC, a.appointment_time DESC
                                  LIMIT 10");
        } else {
            $stmt = $pdo->prepare("SELECT 
                                  a.id,
                                  a.appointment_date,
                                  a.appointment_time,
                                  a.status,
                                  c.first_name,
                                  c.last_name,
                                  s.name as service_name
                                  FROM appointments a
                                  INNER JOIN customers c ON a.customer_id = c.id
                                  INNER JOIN services s ON a.service_id = s.id
                                  INNER JOIN staff st ON a.staff_id = st.id
                                  WHERE st.email = ? AND a.status = 'completed'
                                  ORDER BY a.appointment_date DESC, a.appointment_time DESC
                                  LIMIT 10");
        }
        $stmt->execute([$staff_email]);
        $recent_appointments = $stmt->fetchAll();
        
        $recent_activity = [];
        foreach ($recent_appointments as $appointment) {
            $customer_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
            $booking_date = new DateTime($appointment['appointment_date']);
            $now = new DateTime();
            $diff = $now->diff($booking_date);
            
            $time_ago = '';
            if ($diff->days == 0) {
                $time_ago = 'Today';
            } elseif ($diff->days == 1) {
                $time_ago = 'Yesterday';
            } elseif ($diff->days < 7) {
                $time_ago = $diff->days . ' days ago';
            } else {
                $time_ago = $booking_date->format('M d, Y');
            }
            
            $recent_activity[] = [
                'type' => 'appointment',
                'customer_name' => $customer_name,
                'service' => $appointment['service_name'],
                'date' => $appointment['appointment_date'],
                'time' => $appointment['appointment_time'],
                'time_ago' => $time_ago
            ];
        }
        
        // Bar chart data
        $bar_chart_data = [];
        $timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'monthly';
        
        if ($timeframe === 'weekly') {
            for ($i = 6; $i >= 0; $i--) {
                $week_start = date('Y-m-d', strtotime("monday -$i weeks"));
                $week_end = date('Y-m-d', strtotime("sunday -$i weeks"));
                
                if ($uses_staff_email) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                          FROM appointments a
                                          WHERE a.staff_id = ? AND a.status = 'completed' 
                                          AND a.appointment_date >= ? AND a.appointment_date <= ?");
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                          FROM appointments a
                                          INNER JOIN staff st ON a.staff_id = st.id
                                          WHERE st.email = ? AND a.status = 'completed' 
                                          AND a.appointment_date >= ? AND a.appointment_date <= ?");
                }
                $stmt->execute([$staff_email, $week_start, $week_end]);
                $count = (int)$stmt->fetchColumn();
                
                $bar_chart_data[] = [
                    'x' => date('M d, Y', strtotime($week_start)),
                    'y' => $count
                ];
            }
        } elseif ($timeframe === 'monthly') {
            for ($i = 6; $i >= 0; $i--) {
                $month_start = date('Y-m-01', strtotime("-$i months"));
                $month_end = date('Y-m-t', strtotime("-$i months"));
                $month_name = date('M Y', strtotime("-$i months"));
                
                if ($uses_staff_email) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                          FROM appointments a
                                          WHERE a.staff_id = ? AND a.status = 'completed' 
                                          AND a.appointment_date >= ? AND a.appointment_date <= ?");
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                                          FROM appointments a
                                          INNER JOIN staff st ON a.staff_id = st.id
                                          WHERE st.email = ? AND a.status = 'completed' 
                                          AND a.appointment_date >= ? AND a.appointment_date <= ?");
                }
                $stmt->execute([$staff_email, $month_start, $month_end]);
                $count = (int)$stmt->fetchColumn();
                
                $bar_chart_data[] = [
                    'x' => $month_name,
                    'y' => $count
                ];
            }
        }
        
        jsonResponse([
            'success' => true,
            'metrics' => $metrics,
            'service_distribution' => $service_distribution,
            'recent_activity' => $recent_activity,
            'bar_chart' => $bar_chart_data
        ]);
        
    } catch(PDOException $e) {
        error_log("Performance API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch performance data: ' . $e->getMessage()], 500);
    }
}
?>

