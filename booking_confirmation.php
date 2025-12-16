<?php
session_start();
require_once 'config/database.php';

if(!isset($_GET['booking_id']) && !isset($_GET['reference'])) {
    header('Location: index.php');
    exit();
}

$bookingId = $_GET['booking_id'] ?? $_GET['reference'] ?? null;

$database = new Database();
$db = $database->getConnection();

// Get customer email from session
$customerEmail = $_SESSION['customer_email'] ?? $_SESSION['user_email'] ?? null;
if (!$customerEmail && isset($_SESSION['customer_phone'])) {
    $emailQuery = "SELECT customer_email FROM customer WHERE phone = ? LIMIT 1";
    $emailStmt = $db->prepare($emailQuery);
    $emailStmt->execute([$_SESSION['customer_phone']]);
    $emailRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
    $customerEmail = $emailRow['customer_email'] ?? null;
}

// Get booking details using booking_id and customer_email
$query = "SELECT 
    b.*,
    c.first_name,
    c.last_name,
    c.email
FROM booking b
JOIN customer c ON b.customer_email = c.customer_email
WHERE b.booking_id = ? AND b.customer_email = ?";

$stmt = $db->prepare($query);
$stmt->execute([$bookingId, $customerEmail]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$booking) {
    header('Location: index.php');
    exit();
}

// Get booking services
$query = "SELECT 
    bs.*,
    bs.quoted_duration_minutes,
    bs.quoted_price,
    COALESCE(s.service_name, s.name) as service_name,
    COALESCE(s.duration_minutes, s.current_duration_minutes) as duration_minutes,
    COALESCE(s.price, s.current_price) as price,
    st.first_name as staff_first_name,
    st.last_name as staff_last_name
FROM booking_service bs
JOIN service s ON bs.service_id = s.service_id
LEFT JOIN staff st ON bs.staff_email = st.staff_email
WHERE bs.booking_id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$booking['booking_id']]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lumière Beauty Salon</title>

     <!-- BOOTSTRAP CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    
    <link href="css/style.css" rel="stylesheet"> 
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-spa me-2"></i>Lumière Beauty Salon
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="booking.php">Book Now</a></li>
                    <li class="nav-item"><a class="nav-link" href="booking_history.php">My Bookings</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card text-center">
                <div class="card-body">
                    <div class="confirmation-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    
                    <h2 class="mb-3" style="font-family: 'Playfair Display', serif; color: var(--warm-brown);">
                        Booking Confirmed!
                    </h2>
                    
                    <p class="lead mb-4">Your appointment has been successfully booked.</p>
                    
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-start">
                                    <h5 class="card-title mb-3">Booking Details</h5>
                                    
                                    <div class="mb-2">
                                        <strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['booking_id']); ?>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Date:</strong> <?php echo date('d M Y', strtotime($booking['booking_date'])); ?>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Time:</strong> <?php echo date('h:i A', strtotime($booking['start_time'])); ?>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Duration:</strong> <?php echo $booking['total_duration_minutes']; ?> minutes
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Customer:</strong> <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-8">
                            <h5 class="mb-3">Services Booked</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Duration</th>
                                            <th>Staff</th>
                                            <th>Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($services as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                            <td><?php echo $service['quoted_duration_minutes'] ?? $service['duration_minutes'] ?? 'N/A'; ?> min</td>
                                            <td>
                                                <?php 
                                                if($service['staff_first_name']) {
                                                    echo htmlspecialchars($service['staff_first_name'] . ' ' . $service['staff_last_name']);
                                                } else {
                                                    echo 'No preference';
                                                }
                                                ?>
                                            </td>
                                            <td>RM <?php echo number_format($service['quoted_price'] ?? $service['price'] ?? 0, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-end">
                                <div class="mb-1"><strong>Total:</strong> RM <?php echo number_format($booking['total_price'], 2); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-envelope me-2"></i>
                        A confirmation email has been sent to <?php echo htmlspecialchars($booking['email']); ?>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="booking_history.php" class="btn btn-primary">
                            <i class="fas fa-history me-2"></i>View Booking History
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Lumière Beauty Salon</h5>
                    <p>Where beauty meets elegance. Experience luxury treatments in a serene environment.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 Lumière Beauty Salon. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>