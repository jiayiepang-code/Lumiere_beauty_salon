<?php
session_start();
require_once 'config/database.php';

// If NOT logged in, save the page they wanted (booking.php) and send to Login
if(!isset($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = 'booking.php';
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// 2. FETCH SERVICES
$query = "SELECT 
    sc.category_id,
    sc.name as category_name,
    s.service_id,
    s.name as service_name,
    s.description,
    s.duration_minutes,
    s.price
FROM service_category sc
JOIN service s ON sc.category_id = s.category_id
ORDER BY sc.category_id, s.service_id";

$stmt = $db->prepare($query);
$stmt->execute();

$services = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $services[$row['category_name']][] = $row;
}

// 3. FETCH STAFF
$query = "SELECT staff_id, first_name, last_name, role, is_active FROM staff WHERE is_active = TRUE ORDER BY role, first_name";
$stmt = $db->prepare($query);
$stmt->execute();

$staff = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $staff[$row['role']][] = $row;
}

// 4. FETCH CURRENT CUSTOMER DETAILS (For Step 4 Review)
$query = "SELECT first_name, last_name, email, phone FROM customer WHERE customer_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['customer_id']]);
$customer_info = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="step-indicator">
        <div class="step active" id="step-1-indicator"><div class="step-number">1</div><span>Services</span></div>
        <div class="step" id="step-2-indicator"><div class="step-number">2</div><span>Staff</span></div>
        <div class="step" id="step-3-indicator"><div class="step-number">3</div><span>Date & Time</span></div>
        <div class="step" id="step-4-indicator"><div class="step-number">4</div><span>Review</span></div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            
            <div id="step-1" class="booking-step">
                <div class="card">
                    <div class="card-header">
                        <h4>Choose Your Services</h4>
                        <p class="mb-0">Select one or more services for your appointment</p>
                    </div>
                    <div class="card-body">
                        <?php 
                        // GROUPING LOGIC
                        $defined_groups = [
                            'Haircut Services' => ['Men Haircut', 'Women Haircut', 'Wash + Cut + Blow', 'Wash Only'],
                            'Styling' => ['Basic Styling', 'Rebonding', 'Perming'],
                            'Hair Colouring' => ['Hair Colouring (Short)', 'Hair Colouring (Medium)', 'Hair Colouring (Long)', 'Root Touch Up'],
                            'Hair Treatment' => ['Scalp Treatment', 'Keratin Treatment'],
                            'Facial Treatments' => ['Anti-Aging Facial', 'Deep Cleansing Facial', 'Hydrating Facial', 'Brightening Facial'],
                            'Manicure Options' => ['Classic Basic Manicure', 'Just Colour', 'Nail Care'],
                            'Nail Gelish' => ['Nail Gelish', 'Gel Removal'],
                            'Nail Art' => ['Nail Art (Chrome)', 'Nail Art (Cat Eyes)', 'Nail Art (French)', 'Nail Art (3D)', 'Nail Art (Matte)'],
                            'Extensions' => ['Acrylic Extensions', 'Tip Extensions', 'Infill Extension', 'Extension Removal'],
                            'Add-ons' => ['Hand Scrub + Massage', 'Cuticle Oil Massage'],
                            'Body Massage' => ['Body Massage (60m)', 'Body Massage (90m)', 'Body Massage (120m)'],
                            'Swedish Massage' => ['Swedish Massage (60m)', 'Swedish Massage (120m)'],
                            'Traditional Massage' => ['Borneo Massage (60m)', 'Borneo Massage (120m)'],
                            'Aromatherapy' => ['Aromatherapy (60m)', 'Aromatherapy (120m)'],
                            'Hot Stone' => ['Hot Stone (90m)', 'Hot Stone (120m)']
                        ];

                        $grouped_services = [];
                        $ungrouped_services = [];
                        $service_to_group_map = [];

                        foreach ($defined_groups as $group_title => $service_names) {
                            foreach ($service_names as $name) $service_to_group_map[$name] = $group_title;
                        }

                        foreach ($services as $cat_name => $cat_services) {
                            foreach ($cat_services as $s) {
                                if (isset($service_to_group_map[$s['service_name']])) {
                                    $grouped_services[$cat_name][$service_to_group_map[$s['service_name']]][] = $s;
                                } else {
                                    $ungrouped_services[$cat_name][] = $s;
                                }
                            }
                        }
                        ?>

                        <?php foreach($services as $cat_name => $junk): ?>
                            <?php if(isset($grouped_services[$cat_name]) || isset($ungrouped_services[$cat_name])): ?>
                                <div class="mb-4">
                                    <h5 class="mb-3" style="color: var(--warm-brown); border-bottom: 2px solid var(--secondary-beige); padding-bottom: 10px;">
                                        <?php echo htmlspecialchars($cat_name); ?>
                                    </h5>
                                    <div class="row">
                                        <?php if(isset($grouped_services[$cat_name])): ?>
                                            <?php foreach($grouped_services[$cat_name] as $group_title => $variants): ?>
                                                <?php $first = $variants[0]; ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card service-card h-100 group-card">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="card-title fw-bold mb-0"><?php echo htmlspecialchars($group_title); ?></h6>
                                                                <span class="badge bg-light text-dark border price-badge">RM <?php echo number_format($first['price'], 0); ?></span>
                                                            </div>
                                                            <p class="card-text small text-muted mb-3"><?php echo htmlspecialchars($first['description']); ?></p>
                                                            <select class="form-select form-select-sm service-variant-select" onclick="event.stopPropagation();" onchange="updateCardPrice(this)">
                                                                <?php foreach($variants as $v): ?>
                                                                    <option value="<?php echo $v['service_id']; ?>" data-price="<?php echo $v['price']; ?>" data-duration="<?php echo $v['duration_minutes']; ?>">
                                                                        <?php echo htmlspecialchars($v['service_name']); ?> (<?php echo $v['duration_minutes']; ?>m) - RM<?php echo number_format($v['price'], 0); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if(isset($ungrouped_services[$cat_name])): ?>
                                            <?php foreach($ungrouped_services[$cat_name] as $service): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card service-card h-100" data-service-id="<?php echo $service['service_id']; ?>">
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($service['service_name']); ?></h6>
                                                            <p class="card-text small"><?php echo htmlspecialchars($service['description']); ?></p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-secondary"><?php echo $service['duration_minutes']; ?> min</span>
                                                                <span class="fw-bold" style="color: var(--accent-gold);">RM <?php echo number_format($service['price'], 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="step-2" class="booking-step" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h4>Choose Your Staff</h4>
                        <p class="mb-0">Select a specialist for each service</p>
                    </div>
                    <div class="card-body">
                        <div id="staff-selection" class="row">
                            </div>
                    </div>
                </div>
            </div>

            <div id="step-3" class="booking-step" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h4>Select Date & Time</h4>
                        <p class="mb-0">Choose your preferred slot</p>
                    </div>
                    <div class="card-body">
                        
                        <div class="staff-selector-header" id="staff-display-header">
                            <div class="d-flex align-items-center">
                                <span id="staff-icon-container" class="me-2"></span>
                                <span id="staff-display-name">Loading...</span>
                            </div>
                            <i class="fas fa-chevron-down text-muted"></i>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="calendar-wrapper">
                                    <div class="calendar-header">
                                        <h5 class="mb-0" id="current-month-year" style="color: var(--warm-brown);">Month Year</h5>
                                        <div>
                                            <button class="btn btn-sm btn-light rounded-circle" id="prev-month"><i class="fas fa-chevron-left"></i></button>
                                            <button class="btn btn-sm btn-light rounded-circle" id="next-month"><i class="fas fa-chevron-right"></i></button>
                                        </div>
                                    </div>
                                    <div class="calendar-days">
                                        <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                                    </div>
                                    <div class="calendar-grid" id="calendar-grid"></div>
                                    <input type="hidden" id="booking-date">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Available Time Slots (10:00 AM - 10:00 PM)</label>
                            <div id="time-slots" class="mt-2"></div>
                            <div id="closing-warning" class="alert alert-warning mt-2" style="display:none;">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Some slots are disabled because the total service duration (<span id="total-duration-display">0</span> mins) would exceed our closing time (10:00 PM).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-4" class="booking-step" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h4>Review & Confirm</h4>
                        <p class="mb-0">Please check your details before confirming</p>
                    </div>
                    <div class="card-body">
                        <form id="booking-form">
                            <h5 class="mb-3" style="color: var(--warm-brown);">Customer Details</h5>
                            <div class="bg-light p-3 rounded mb-4 border">
                                <div class="mb-2">
                                    <small class="text-muted text-uppercase">Name</small><br>
                                    <span class="fw-bold"><?php echo htmlspecialchars($customer_info['first_name'] . ' ' . $customer_info['last_name']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted text-uppercase">Phone</small><br>
                                    <span class="fw-bold"><?php echo htmlspecialchars($customer_info['phone']); ?></span>
                                </div>
                                <div>
                                    <small class="text-muted text-uppercase">Email</small><br>
                                    <span class="fw-bold"><?php echo htmlspecialchars($customer_info['email']); ?></span>
                                </div>
                            </div>

                            <h5 class="mb-3" style="color: var(--warm-brown);">Payment Method</h5>
                            <div class="card mb-4 border-warning bg-light">
                                <div class="card-body py-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" checked disabled>
                                        <label class="form-check-label fw-bold">
                                            Pay at Salon (Cash / QR / Credit Card)
                                        </label>
                                    </div>
                                    <input type="hidden" id="payment-method" value="pay_at_salon">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="special-requests" class="form-label h5" style="color: var(--warm-brown);">Special Requests (Optional)</label>
                                <textarea class="form-control" id="special-requests" rows="3" placeholder="e.g. I have sensitive skin..."></textarea>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4 mb-5">
                <button type="button" id="prev-btn" class="btn btn-secondary" style="display: none;">
                    <i class="fas fa-arrow-left me-2"></i>Previous
                </button>
                <button type="button" id="next-btn" class="btn btn-primary">
                    Next<i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="booking-summary">
                <h4 class="mb-3">Booking Summary</h4>
                <div id="summary-content">
                    <p class="text-muted">Select services to see summary</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" style="color: var(--warm-brown);">Choose a Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3">Select your preferred specialist for <span id="modal-service-name" class="fw-bold"></span></p>
                <div id="modal-staff-list" class="d-grid gap-2"></div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    window.staffData = <?php echo json_encode($staff); ?>;
    window.allServicesData = {
        <?php 
        foreach($services as $category => $category_services) {
            foreach($category_services as $service) {
                echo $service['service_id'] . ": {
                    name: '" . addslashes($service['service_name']) . "',
                    duration: " . $service['duration_minutes'] . ",
                    price: " . $service['price'] . ",
                    category: '" . addslashes($service['category_name']) . "' 
                },";
            }
        }
        ?>
    };
</script>

<script src="js/booking.js"></script>
</body>
</html>