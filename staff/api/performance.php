<?php
require_once '../config.php';

checkAuth();

$staff_email = $_SESSION['staff_id']; // staff_id is actually staff_email from login

// Database uses table names with spaces (wrapped in backticks)

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle target commission requests
    if (isset($_GET['action']) && $_GET['action'] === 'get_target') {
        $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'staff/api/performance.php:' . __LINE__, 'message' => 'get_target action called', 'data' => ['staff_email' => $staff_email, 'month' => $month], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run2', 'hypothesisId' => 'D']) . "\n", FILE_APPEND);
        // #endregion agent log
        
        try {
            // Calculate current commission for the month
            $month_start = $month . '-01';
            $month_end = date('Y-m-t', strtotime($month_start));
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(bs.quoted_price * bs.quantity * 0.1), 0) as commission 
                                  FROM `booking_service` bs
                                  INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                  WHERE bs.staff_email = ? AND b.status = 'completed' 
                                  AND b.booking_date >= ? AND b.booking_date <= ?");
            $stmt->execute([$staff_email, $month_start, $month_end]);
            $current_commission = (float)$stmt->fetchColumn();
            
            // Get target commission (check if table exists, if not return 0)
            $target = 0;
            try {
                // Check if staff_target_commission table exists
                $check_table = $pdo->query("SHOW TABLES LIKE 'staff_target_commission'");
                if ($check_table->rowCount() > 0) {
                    $stmt = $pdo->prepare("SELECT target_amount FROM staff_target_commission 
                                          WHERE staff_email = ? AND target_month = ?");
                    $stmt->execute([$staff_email, $month]);
                    $result = $stmt->fetch();
                    if ($result) {
                        $target = (float)$result['target_amount'];
                    }
                }
            } catch(PDOException $e) {
                // Table doesn't exist, target is 0
                $target = 0;
            }
            
            // #region agent log
            file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'staff/api/performance.php:' . __LINE__, 'message' => 'Returning target and current commission', 'data' => ['target' => $target, 'current_commission' => $current_commission, 'month' => $month], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run2', 'hypothesisId' => 'D']) . "\n", FILE_APPEND);
            // #endregion agent log
            
            jsonResponse([
                'success' => true,
                'target' => $target,
                'current_commission' => $current_commission,
                'month' => $month
            ]);
            
        } catch(PDOException $e) {
            error_log("Get Target Commission Error: " . $e->getMessage());
            jsonResponse(['error' => 'Failed to get target commission: ' . $e->getMessage()], 500);
        }
        exit;
    }
    
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
    
    try {
        // Get current date and period start date
        $current_date = date('Y-m-d');
        
        if ($period === 'month') {
            $start_date = date('Y-m-01');
        } elseif ($period === 'week') {
            $start_date = date('Y-m-d', strtotime('monday this week'));
        } else {
            $start_date = date('Y-01-01'); // year
        }
        
        // Get performance metrics
        $metrics = [];
        
        // Total completed appointments (based on booking status)
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.booking_id) as total 
                              FROM `booking_service` bs
                              INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                              WHERE bs.staff_email = ? AND b.status = 'completed' 
                              AND b.booking_date >= ? AND b.booking_date <= ?");
        try {
            $stmt->execute([$staff_email, $start_date, $current_date]);
            $metrics['completed_appointments'] = (int)$stmt->fetchColumn();
        } catch(PDOException $e) {
            throw $e;
        }
        
        // Total revenue (sum of quoted_price from booking_service)
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(bs.quoted_price * bs.quantity), 0) as total 
                                  FROM `booking_service` bs
                                  INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                  WHERE bs.staff_email = ? AND b.status = 'completed' 
                                  AND b.booking_date >= ? AND b.booking_date <= ?");
            $stmt->execute([$staff_email, $start_date, $current_date]);
            $metrics['total_revenue'] = (float)$stmt->fetchColumn();
        } catch(PDOException $e) {
            throw $e;
        }
        
        // Completed appointments for a specific date
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.booking_id) as total 
                              FROM `booking_service` bs
                              INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                              WHERE bs.staff_email = ? AND b.status = 'completed' 
                              AND b.booking_date = ?");
        $stmt->execute([$staff_email, $date]);
        $metrics['completed_today'] = (int)$stmt->fetchColumn();
        
        // Unique clients
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.customer_email) as total 
                              FROM `booking_service` bs
                              INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                              WHERE bs.staff_email = ? AND b.booking_date >= ? AND b.booking_date <= ?");
        $stmt->execute([$staff_email, $start_date, $current_date]);
        $metrics['unique_clients'] = (int)$stmt->fetchColumn();
        
        // Monthly revenue trend (last 12 months)
        $revenue_trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            $month_name = date('M Y', strtotime("-$i months"));
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(bs.quoted_price * bs.quantity), 0) as total 
                                  FROM `booking_service` bs
                                  INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                  WHERE bs.staff_email = ? AND b.status = 'completed' 
                                  AND b.booking_date >= ? AND b.booking_date <= ?");
            $stmt->execute([$staff_email, $month_start, $month_end]);
            $revenue = (float)$stmt->fetchColumn();
            
            $revenue_trend[] = [
                'month' => $month_name,
                'revenue' => $revenue
            ];
        }
        
        // Service distribution - filtered by timeframe or default to all records from 2024
        $service_distribution = [];
        try {
            $where_conditions = "bs.staff_email = ? AND b.status = 'completed'";
            $query_params = [$staff_email];
            
            // Check if filtering by specific timeframe and date/month/year
            if (isset($_GET['timeframe'])) {
                $timeframe = $_GET['timeframe'];
                
                if ($timeframe === 'weekly' && isset($_GET['date'])) {
                    // Filter by week containing the specified date
                    $specified_date = $_GET['date']; // Expected format: YYYY-MM-DD
                    $week_start = date('Y-m-d', strtotime("monday", strtotime($specified_date)));
                    $week_end = date('Y-m-d', strtotime("sunday", strtotime($specified_date)));
                    
                    $where_conditions .= " AND b.booking_date >= ? AND b.booking_date <= ?";
                    $query_params[] = $week_start;
                    $query_params[] = $week_end;
                } elseif ($timeframe === 'monthly' && isset($_GET['month'])) {
                    // Filter by specific month (format: YYYY-MM)
                    $month_str = $_GET['month'];
                    $month_start = $month_str . '-01';
                    $month_end = date('Y-m-t', strtotime($month_start));
                    
                    $where_conditions .= " AND b.booking_date >= ? AND b.booking_date <= ?";
                    $query_params[] = $month_start;
                    $query_params[] = $month_end;
                } elseif ($timeframe === 'yearly' && isset($_GET['year'])) {
                    // Filter by specific year
                    $year = $_GET['year'];
                    $year_start = $year . '-01-01';
                    $year_end = $year . '-12-31';
                    
                    $where_conditions .= " AND b.booking_date >= ? AND b.booking_date <= ?";
                    $query_params[] = $year_start;
                    $query_params[] = $year_end;
                } else {
                    // If timeframe is set but no valid date/month/year, default to 2024 onwards
                    $distribution_start_date = '2024-01-01';
                    $where_conditions .= " AND b.booking_date >= ?";
                    $query_params[] = $distribution_start_date;
                }
            } else {
                // No timeframe specified, default to 2024 onwards
                $distribution_start_date = '2024-01-01';
                $where_conditions .= " AND b.booking_date >= ?";
                $query_params[] = $distribution_start_date;
            }
            
            $stmt = $pdo->prepare("SELECT s.service_name, COUNT(*) as count 
                                  FROM `booking_service` bs
                                  INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                  INNER JOIN `service` s ON bs.service_id = s.service_id
                                  WHERE $where_conditions
                                  GROUP BY s.service_id, s.service_name 
                                  ORDER BY count DESC");
            $stmt->execute($query_params);
            $service_distribution = $stmt->fetchAll();
        } catch(PDOException $e) {
            throw $e;
        }
        
        // Recent completed sessions - filter by month if provided
        $recent_activity = [];
        $where_clause = "bs.staff_email = ? AND b.status = 'completed'";
        $query_params = [$staff_email];
        
        // If month parameter is provided, filter by that month
        if (isset($_GET['month']) && !empty($_GET['month'])) {
            $month_str = $_GET['month'];
            if (preg_match('/^\d{4}-\d{2}$/', $month_str)) {
                $month_start = $month_str . '-01';
                $month_end = date('Y-m-t', strtotime($month_start));
                $where_clause .= " AND b.booking_date >= ? AND b.booking_date <= ?";
                $query_params[] = $month_start;
                $query_params[] = $month_end;
            }
        }
        
        $stmt = $pdo->prepare("SELECT 
                              b.booking_id,
                              b.booking_date,
                              b.start_time,
                              b.status,
                              c.first_name,
                              c.last_name,
                              s.service_name
                              FROM `booking_service` bs
                              INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                              INNER JOIN `service` s ON bs.service_id = s.service_id
                              INNER JOIN `customer` c ON b.customer_email = c.customer_email
                              WHERE {$where_clause}
                              ORDER BY b.booking_date DESC, b.start_time DESC
                              LIMIT 100");
        $stmt->execute($query_params);
        $recent_appointments = $stmt->fetchAll();
        
        foreach ($recent_appointments as $appointment) {
            $customer_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
            $booking_date = new DateTime($appointment['booking_date']);
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
                $time_ago = $booking_date->format('d/m/Y');
            }
            
            $recent_activity[] = [
                'type' => 'appointment',
                'customer_name' => $customer_name,
                'service' => $appointment['service_name'],
                'date' => $appointment['booking_date'],
                'time' => $appointment['start_time'],
                'time_ago' => $time_ago
            ];
        }
        
        // Staff ranking for a specific month
        $ranking_data = null;
        if (isset($_GET['month'])) {
            $month_parts = explode('-', $month);
            $month_start = $month . '-01';
            $month_end = date('Y-m-t', strtotime($month_start));
            
            // Get all staff completed appointments for the month
            $stmt = $pdo->prepare("SELECT 
                                  bs.staff_email,
                                  COUNT(DISTINCT b.booking_id) as completed_count
                                  FROM `booking_service` bs
                                  INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                  WHERE b.status = 'completed' 
                                  AND b.booking_date >= ? AND b.booking_date <= ?
                                  GROUP BY bs.staff_email
                                  ORDER BY completed_count DESC");
            $stmt->execute([$month_start, $month_end]);
            $all_staff_ranks = $stmt->fetchAll();
            
            // Find current staff's rank
            $rank = 1;
            $total_staff = count($all_staff_ranks);
            $staff_completed = 0;
            
            foreach ($all_staff_ranks as $index => $staff_rank) {
                if ($staff_rank['staff_email'] === $staff_email) {
                    $rank = $index + 1;
                    $staff_completed = (int)$staff_rank['completed_count'];
                    break;
                }
            }
            
            // Calculate total bookings for the month
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.booking_id) as total 
                                  FROM `booking` b
                                  WHERE b.status = 'completed' 
                                  AND b.booking_date >= ? AND b.booking_date <= ?");
            $stmt->execute([$month_start, $month_end]);
            $total_bookings = (int)$stmt->fetchColumn();
            
            $ranking_data = [
                'rank' => $rank,
                'total_staff' => $total_staff,
                'completed' => $staff_completed,
                'total_bookings' => $total_bookings
            ];
        }
        
        // Weekly/Monthly/Yearly completed appointments for bar chart
        $bar_chart_data = [];
        $timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'monthly';
        
        if ($timeframe === 'weekly') {
            // Last 12 weeks - calculate from current week's Monday backwards
            // Get Monday of current week first
            $current_monday = strtotime('monday this week');
            
            for ($i = 11; $i >= 0; $i--) {
                // Calculate Monday that is $i weeks ago from current Monday
                $week_start_timestamp = strtotime("-$i weeks", $current_monday);
                $week_start = date('Y-m-d', $week_start_timestamp);
                $week_end = date('Y-m-d', strtotime('+6 days', $week_start_timestamp));
                
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.booking_id) as total 
                                      FROM `booking_service` bs
                                      INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                      WHERE bs.staff_email = ? AND b.status = 'completed' 
                                      AND b.booking_date >= ? AND b.booking_date <= ?");
                $stmt->execute([$staff_email, $week_start, $week_end]);
                $count = (int)$stmt->fetchColumn();
                
                $bar_chart_data[] = [
                    'x' => date('Y-m-d', $week_start_timestamp), // Use raw date for JS processing
                    'y' => $count
                ];
            }
        } elseif ($timeframe === 'monthly') {
            // Last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $month_start = date('Y-m-01', strtotime("-$i months"));
                $month_end = date('Y-m-t', strtotime("-$i months"));
                
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.booking_id) as total 
                                      FROM `booking_service` bs
                                      INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                      WHERE bs.staff_email = ? AND b.status = 'completed' 
                                      AND b.booking_date >= ? AND b.booking_date <= ?");
                $stmt->execute([$staff_email, $month_start, $month_end]);
                $count = (int)$stmt->fetchColumn();
                
                $bar_chart_data[] = [
                    'x' => date('Y-m-01', strtotime("-$i months")), // Use raw date for JS processing
                    'y' => $count
                ];
            }
        } else { // yearly
            // Last 4 years
            $current_year = (int)date('Y');
            for ($i = 3; $i >= 0; $i--) {
                $year = $current_year - $i;
                $year_start = "$year-01-01";
                $year_end = "$year-12-31";
                
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.booking_id) as total 
                                      FROM `booking_service` bs
                                      INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                      WHERE bs.staff_email = ? AND b.status = 'completed' 
                                      AND b.booking_date >= ? AND b.booking_date <= ?");
                $stmt->execute([$staff_email, $year_start, $year_end]);
                $count = (int)$stmt->fetchColumn();
                
                $bar_chart_data[] = [
                    'x' => (string)$year, // Use raw year for JS processing
                    'y' => $count
                ];
            }
        }
        
        // Commission data - 10% of booking price for completed bookings (Monthly only)
        $commission_data = [];
        if (isset($_GET['commission']) && $_GET['commission'] === 'true') {
            // Last 12 months - used for scrolling chart
            for ($i = 11; $i >= 0; $i--) {
                $month_start = date('Y-m-01', strtotime("-$i months"));
                $month_end = date('Y-m-t', strtotime("-$i months"));
                $month_name = date('M Y', strtotime("-$i months"));
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(bs.quoted_price * bs.quantity * 0.1), 0) as commission 
                                      FROM `booking_service` bs
                                      INNER JOIN `booking` b ON bs.booking_id = b.booking_id
                                      WHERE bs.staff_email = ? AND b.status = 'completed' 
                                      AND b.booking_date >= ? AND b.booking_date <= ?");
                $stmt->execute([$staff_email, $month_start, $month_end]);
                $commission = (float)$stmt->fetchColumn();
                
                $commission_data[] = [
                    'period' => $month_name, // M Y format for display
                    'commission' => $commission
                ];
            }
        }
        
        $response = [
            'success' => true,
            'metrics' => $metrics,
            'revenue_trend' => $revenue_trend,
            'service_distribution' => $service_distribution,
            'recent_activity' => $recent_activity,
            'ranking' => $ranking_data,
            'bar_chart' => $bar_chart_data
        ];
        
        // Add commission data if requested
        if (isset($_GET['commission']) && $_GET['commission'] === 'true') {
            $response['commission_data'] = $commission_data;
        }
        
        jsonResponse($response);
        
    } catch(PDOException $e) {
        error_log("Performance API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch performance data: ' . $e->getMessage()], 500);
    } catch(Exception $e) {
        error_log("Performance API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch performance data: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'set_target') {
        $month = isset($data['month']) ? $data['month'] : '';
        $target_amount = isset($data['target_amount']) ? (float)$data['target_amount'] : 0;
        
        if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            jsonResponse(['error' => 'Invalid month format. Use YYYY-MM'], 400);
        }
        
        if ($target_amount < 0) {
            jsonResponse(['error' => 'Target amount must be positive'], 400);
        }
        
        try {
            // Create table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS staff_target_commission (
                id INT PRIMARY KEY AUTO_INCREMENT,
                staff_email VARCHAR(100) NOT NULL,
                target_month VARCHAR(7) NOT NULL,
                target_amount DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_staff_month (staff_email, target_month)
            )");
            
            // #region agent log
            file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'staff/api/performance.php:' . __LINE__, 'message' => 'Saving target commission', 'data' => ['staff_email' => $staff_email, 'month' => $month, 'target_amount' => $target_amount], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'L']) . "\n", FILE_APPEND);
            // #endregion agent log
            
            // Insert or update target
            $stmt = $pdo->prepare("INSERT INTO staff_target_commission (staff_email, target_month, target_amount) 
                                  VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE target_amount = ?, updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$staff_email, $month, $target_amount, $target_amount]);
            
            // #region agent log
            file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'staff/api/performance.php:' . __LINE__, 'message' => 'Target commission saved', 'data' => ['rowCount' => $stmt->rowCount(), 'target_amount' => $target_amount, 'month' => $month], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'M']) . "\n", FILE_APPEND);
            // #endregion agent log
            
            jsonResponse([
                'success' => true,
                'message' => 'Target commission saved successfully',
                'target' => $target_amount,
                'month' => $month
            ]);
            
        } catch(PDOException $e) {
            error_log("Set Target Commission Error: " . $e->getMessage());
            jsonResponse(['error' => 'Failed to save target commission: ' . $e->getMessage()], 500);
        }
        exit;
    }
    
    jsonResponse(['error' => 'Invalid action'], 400);
}
?>