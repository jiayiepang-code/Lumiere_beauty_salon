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
    c.customer_email as email,
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
    s.service_name,
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

// Calculate subtotal from services
$subtotal = 0;
foreach($services as $service) {
    $subtotal += floatval($service['quoted_price'] ?? $service['price'] ?? 0);
}

// Calculate duration
$duration = 0;
foreach($services as $service) {
    $duration += intval($service['quoted_duration_minutes'] ?? $service['duration_minutes'] ?? 0);
}

// Calculate grand total (no SST)
$grandTotal = $subtotal;

// Format Reference ID (using booking_id)
$referenceId = $booking['booking_id'];
// If booking_id doesn't start with 'LB', use as is, otherwise format it
if (strpos($referenceId, 'LB') === 0 || strpos($referenceId, 'BK') === 0) {
    $referenceId = $booking['booking_id'];
} else {
    $referenceId = 'LB' . date('YmdHis') . str_pad($booking['booking_id'], 5, '0', STR_PAD_LEFT);
}

// Determine payment method (default to Pay_at_salon if not set)
$paymentMethod = $booking['payment_method'] ?? 'Pay_at_salon';
?>

<style>
.booking-details-modal {
    font-family: "Segoe UI", sans-serif;
}
.booking-details-section {
    margin-bottom: 1.5rem;
}
.booking-details-section h6 {
    font-weight: 600;
    margin-bottom: 1rem;
    color: #333;
    font-family: "Segoe UI", sans-serif;
}
.booking-info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}
.booking-info-label {
    font-weight: 600;
    color: #555;
    font-family: "Segoe UI", sans-serif;
}
.booking-info-value {
    color: #333;
    font-family: "Segoe UI", sans-serif;
}
.status-badge-modal {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}
.status-confirmed {
    background-color: #28a745;
    color: white;
}
.status-cancelled {
    background-color: #dc3545;
    color: white;
}
.services-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}
.services-table th {
    background-color: #f8f9fa;
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #555;
    border-bottom: 2px solid #dee2e6;
    font-family: "Segoe UI", sans-serif;
}
.services-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    font-family: "Segoe UI", sans-serif;
}
.total-summary {
    margin-top: 1.5rem;
    text-align: right;
}
.total-row {
    display: flex;
    justify-content: flex-end;
    padding: 0.5rem 0;
}
.total-row-label {
    min-width: 150px;
    text-align: right;
    padding-right: 1rem;
}
.total-row-value {
    min-width: 120px;
    text-align: right;
}
.grand-total {
    background-color: #4A90E2;
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 4px;
    margin-top: 0.5rem;
    font-weight: 700;
    font-family: "Segoe UI", sans-serif;
}
.booking-timestamp {
    text-align: center;
    color: #6c757d;
    font-size: 0.875rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #dee2e6;
}
</style>

<div class="booking-details-modal">
    <div class="row">
        <div class="col-md-6 booking-details-section">
            <h6><strong>Customer Details</strong></h6>
            <div class="booking-info-row">
                <span class="booking-info-label">Booking ID:</span>
                <span class="booking-info-value"><?php echo htmlspecialchars($referenceId); ?></span>
            </div>
            <div class="booking-info-row">
                <span class="booking-info-label">Customer:</span>
                <span class="booking-info-value"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
            </div>
            <div class="booking-info-row">
                <span class="booking-info-label">Email:</span>
                <span class="booking-info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
            </div>
            <div class="booking-info-row">
                <span class="booking-info-label">Phone:</span>
                <span class="booking-info-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
            </div>
            <div class="booking-info-row">
                <span class="booking-info-label">Status:</span>
                <span class="booking-info-value">
                    <span class="status-badge-modal status-<?php echo strtolower($booking['status']); ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </span>
                </span>
            </div>
        </div>
        
        <div class="col-md-6 booking-details-section">
            <h6><strong>Booking Details</strong></h6>
            <div class="booking-info-row">
                <span class="booking-info-label">Date:</span>
                <span class="booking-info-value"><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></span>
            </div>
            <div class="booking-info-row">
                <span class="booking-info-label">Time:</span>
                <span class="booking-info-value"><?php echo date('h:i A', strtotime($booking['start_time'])); ?> - <?php echo date('h:i A', strtotime($booking['expected_finish_time'])); ?></span>
            </div>
            <div class="booking-info-row">
                <span class="booking-info-label">Duration:</span>
                <span class="booking-info-value"><?php echo $duration; ?> minutes</span>
            </div>
            <div class="booking-info-row">
                <span class="booking-info-label">Payment Method:</span>
                <span class="booking-info-value"><?php echo htmlspecialchars($paymentMethod); ?></span>
            </div>
        </div>
    </div>

    <div class="booking-details-section">
        <h6><strong>Services</strong></h6>
        <table class="services-table">
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
                    <td><?php echo htmlspecialchars($service['description'] ?? 'N/A'); ?></td>
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

    <div class="total-summary">
        <div class="total-row grand-total">
            <span class="total-row-label"><strong>Total:</strong></span>
            <span class="total-row-value"><strong>RM <?php echo number_format($grandTotal, 2); ?></strong></span>
        </div>
    </div>

    <div class="booking-timestamp">
        <p><small>Booking created on <?php echo date('d M Y h:i A', strtotime($booking['created_at'])); ?></small></p>
        <?php if($booking['updated_at'] && $booking['updated_at'] !== $booking['created_at']): ?>
        <p><small>Last updated on <?php echo date('d M Y h:i A', strtotime($booking['updated_at'])); ?></small></p>
        <?php endif; ?>
    </div>
</div>