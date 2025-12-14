<?php
session_start();
require_once 'config/database.php';

if(!isset($_GET['booking_id'])) {
    echo 'Invalid request';
    exit();
}

$bookingId = $_GET['booking_id'];

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

// Verify the booking belongs to the customer using customer_email
$query = "SELECT 
    b.*,
    c.first_name,
    c.last_name,
    c.email,
    c.phone
FROM booking b
JOIN customer c ON b.customer_email = c.customer_email
WHERE b.booking_id = ? AND b.customer_email = ?";

$stmt = $db->prepare($query);
$stmt->execute([$bookingId, $customerEmail]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$booking) {
    echo 'Booking not found';
    exit();
}

// Get booking services
$query = "SELECT 
    bs.*,
    COALESCE(s.service_name, s.name) as service_name,
    s.description,
    st.first_name as staff_first_name,
    st.last_name as staff_last_name
FROM booking_service bs
JOIN service s ON bs.service_id = s.service_id
LEFT JOIN staff st ON bs.staff_email = st.staff_email
WHERE bs.booking_id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$bookingId]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-6">
        <h6>Booking Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Booking ID:</strong></td>
                <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
            </tr>
            <tr>
                <td><strong>Customer:</strong></td>
                <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?php echo htmlspecialchars($booking['email']); ?></td>
            </tr>
            <tr>
                <td><strong>Phone:</strong></td>
                <td><?php echo htmlspecialchars($booking['phone']); ?></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <span class="badge 
                        <?php 
                        switch($booking['status']) {
                            case 'confirmed': echo 'bg-success'; break;
                            case 'completed': echo 'bg-primary'; break;
                            case 'cancelled': echo 'bg-danger'; break;
                            default: echo 'bg-warning';
                        }
                        ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6>Appointment Details</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Date:</strong></td>
                <td><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></td>
            </tr>
            <tr>
                <td><strong>Time:</strong></td>
                <td><?php echo date('h:i A', strtotime($booking['start_time'])); ?> - <?php echo date('h:i A', strtotime($booking['expected_finish_time'])); ?></td>
            </tr>
            <?php if($booking['remarks']): ?>
            <tr>
                <td><strong>Remarks:</strong></td>
                <td><?php echo htmlspecialchars($booking['remarks']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <h6>Services</h6>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Description</th>
                    <th>Duration</th>
                    <th>Staff</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($services as $service): ?>
                <tr>
                    <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                    <td><?php echo htmlspecialchars($service['description']); ?></td>
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
</div>

<div class="row">
    <div class="col-md-6 offset-md-6">
        <table class="table table-sm text-end">
            <tr class="table-primary">
                <td><strong>Total:</strong></td>
                <td><strong>RM <?php echo number_format($booking['total_price'], 2); ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12 text-center">
        <p class="text-muted">
            <small>Booking created on <?php echo date('d M Y h:i A', strtotime($booking['created_at'])); ?></small>
        </p>
        <?php if($booking['updated_at'] !== $booking['created_at']): ?>
        <p class="text-muted">
            <small>Last updated on <?php echo date('d M Y h:i A', strtotime($booking['updated_at'])); ?></small>
        </p>
        <?php endif; ?>
    </div>
</div>