<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

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

if (!$customerEmail) {
    header('Location: login.php');
    exit();
}

// Get customer's booking history using customer_email
$query = "SELECT 
    b.*,
    COUNT(bs.booking_service_id) as service_count
FROM booking b
LEFT JOIN booking_service bs ON b.booking_id = bs.booking_id
WHERE b.customer_email = ?
GROUP BY b.booking_id
ORDER BY b.booking_date DESC, b.start_time DESC";

$stmt = $db->prepare($query);
$stmt->execute([$customerEmail]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking services for each booking
$bookingServices = [];
foreach($bookings as $booking) {
    $query = "SELECT 
        bs.*,
        COALESCE(s.service_name, s.name) as service_name,
        st.first_name as staff_first_name,
        st.last_name as staff_last_name
    FROM booking_service bs
    JOIN service s ON bs.service_id = s.service_id
    LEFT JOIN staff st ON bs.staff_email = st.staff_email
    WHERE bs.booking_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$booking['booking_id']]);
    $bookingServices[$booking['booking_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/header.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="hero-title">My Booking History</h1>
                <p class="hero-subtitle">View and manage your appointments</p>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if(empty($bookings)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt fa-3x mb-3" style="color: var(--accent-gold);"></i>
                        <h4>No bookings yet</h4>
                        <p class="mb-4">You haven't made any bookings yet. Start by booking your first appointment!</p>
                        <a href="booking.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Book Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Your Bookings</h3>
                </div>
            </div>
        </div>

        <div class="row">
            <?php foreach($bookings as $booking): 
                // Check if booking date has passed and mark as expired if status is still confirmed
                $bookingDate = $booking['booking_date'];
                $bookingDateTime = $bookingDate . ' ' . $booking['expected_finish_time'];
                $currentDateTime = date('Y-m-d H:i:s');
                $displayStatus = $booking['status'];
                
                // If booking date/time has passed and status is still confirmed, mark as expired
                if (strtotime($bookingDateTime) < strtotime($currentDateTime) && $booking['status'] === 'confirmed') {
                    $displayStatus = 'expired';
                }
            ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><?php echo htmlspecialchars($booking['booking_id']); ?></span>
                            <span class="badge 
                                <?php 
                                switch($displayStatus) {
                                    case 'confirmed': echo 'bg-success'; break;
                                    case 'completed': echo 'bg-primary'; break;
                                    case 'cancelled': echo 'bg-danger'; break;
                                    case 'expired': echo 'bg-warning'; break;
                                    default: echo 'bg-warning';
                                }
                                ?>">
                                <?php echo ucfirst($displayStatus); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong>Date & Time:</strong>
                                </div>
                                <div><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></div>
                                <div><?php echo date('h:i A', strtotime($booking['start_time'])); ?> - <?php echo date('h:i A', strtotime($booking['expected_finish_time'])); ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong>Services:</strong>
                                    <span>(<?php echo $booking['service_count']; ?>)</span>
                                </div>
                                <div class="small">
                                    <?php 
                                    $serviceNames = array_map(function($service) {
                                        return $service['service_name'];
                                    }, $bookingServices[$booking['booking_id']]);
                                    echo htmlspecialchars(implode(', ', $serviceNames));
                                    ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <span class="fw-bold" style="color: var(--accent-gold);">
                                        RM <?php echo number_format($booking['total_price'], 2); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if($booking['remarks']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <strong>Remarks:</strong><br>
                                        <?php echo htmlspecialchars($booking['remarks']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-fill" 
                                        onclick="viewBookingDetails(<?php echo $booking['booking_id']; ?>)" 
                                        data-bs-toggle="modal" data-bs-target="#bookingModal">
                                    View Details
                                </button>
                                <?php 
                                // Use displayStatus to check if cancel button should be shown
                                $canCancel = ($displayStatus === 'confirmed' && strtotime($bookingDateTime) >= strtotime($currentDateTime));
                                if($canCancel): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger flex-fill" 
                                            onclick="cancelBooking('<?php echo htmlspecialchars($booking['booking_id'], ENT_QUOTES); ?>')">
                                        Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Booking Details Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bookingDetailsContent">
                <!-- Content will be loaded by JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
function viewBookingDetails(bookingId) {
    $.ajax({
        url: 'get_booking_details.php',
        method: 'GET',
        data: { booking_id: bookingId },
        success: function(response) {
            $('#bookingDetailsContent').html(response);
        },
        error: function() {
            $('#bookingDetailsContent').html('<p class="text-danger">Failed to load booking details.</p>');
        }
    });
}

function cancelBooking(bookingId) {
    if(confirm('Do you want to cancel this booking?')) {
        $.ajax({
            url: 'cancel_booking.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ booking_id: bookingId }),
            success: function(response) {
                if(response.success) {
                    alert('Booking cancelled successfully');
                    location.reload();
                } else {
                    alert('Failed to cancel booking: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                let errorMsg = 'An error occurred. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                alert(errorMsg);
            }
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>