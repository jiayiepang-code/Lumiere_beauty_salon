<?php
session_start();
require_once 'config/database.php';

// If NOT logged in, redirect to login page with redirect parameter
$isLoggedIn = isset($_SESSION['customer_phone']) || isset($_SESSION['customer_id']);
if(!$isLoggedIn) {
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header('Location: login.php?redirect=' . $redirect);
    exit();
}

// Get customer phone for database queries
$customerPhone = $_SESSION['customer_phone'] ?? $_SESSION['customer_id'] ?? '';

$database = new Database();
$db = $database->getConnection();

// FETCH SERVICES
$query = "SELECT 
    service_id,
    service_name,
    service_category,
    sub_category,
    description,
    current_duration_minutes as duration_minutes,
    current_price as price
FROM service
WHERE is_active = 1
ORDER BY service_category, service_id";

try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $allServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group services by category
    $servicesGrouped = [];
    $servicesFlat = [];
    $categoryMap = [
        'Haircut' => 'Haircut',
        'Facial' => 'Facial',
        'Manicure' => 'Manicure',
        'Massage' => 'Massage'
    ];
    
    foreach($allServices as $row) {
        $dbCategory = $row['service_category'] ?? 'Other';
        $category = $categoryMap[$dbCategory] ?? $dbCategory;
        $sub = $row['sub_category'] ?? 'General';
        
        $row['category_name'] = $category;
        $row['category_id'] = $category;
        
        $servicesGrouped[$category][$sub][] = $row;
        $servicesFlat[] = $row;
    }
} catch(PDOException $e) {
    $servicesFlat = [];
    $servicesGrouped = [];
    error_log("Booking.php: Failed to fetch services - " . $e->getMessage());
}

// FETCH STAFF with primary services
$query = "SELECT 
    s.staff_email, 
    s.first_name, 
    s.last_name, 
    s.role, 
    s.is_active,
    GROUP_CONCAT(DISTINCT sv.service_name ORDER BY ss.proficiency_level DESC, sv.service_name SEPARATOR ' & ') as primary_services
FROM staff s
LEFT JOIN staff_service ss ON s.staff_email = ss.staff_email AND ss.is_active = 1
LEFT JOIN service sv ON ss.service_id = sv.service_id AND sv.is_active = 1
WHERE s.is_active = 1
GROUP BY s.staff_email
ORDER BY s.role, s.first_name";
try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $staff = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Add staff_id alias for compatibility with JavaScript code
        $row['staff_id'] = $row['staff_email'];
        $staff[$row['role']][] = $row;
    }
} catch(PDOException $e) {
    $staff = [];
    error_log("Booking.php: Failed to fetch staff - " . $e->getMessage());
}

// FETCH STAFF-SERVICE RELATIONSHIPS (for suggested staff)
$staffServiceMap = [];
try {
    $query = "SELECT staff_email, service_id FROM staff_service";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $staffEmail = $row['staff_email'];
        $serviceId = $row['service_id'];
        if(!isset($staffServiceMap[$serviceId])) {
            $staffServiceMap[$serviceId] = [];
        }
        $staffServiceMap[$serviceId][] = $staffEmail;
    }
} catch(PDOException $e) {
    error_log("Booking.php: Failed to fetch staff-service relationships - " . $e->getMessage());
}

// FETCH CURRENT CUSTOMER DETAILS (For Step 4 Review)
$query = "SELECT first_name, last_name, customer_email as email, phone FROM customer WHERE phone = ? LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([$customerPhone]);
$customer_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer_info) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment – Lumière Beauty Salon</title>
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/home.css">
    <link rel="stylesheet" href="css/header.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    
    <!-- Bootstrap and Additional CSS for booking page -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    /* Protect header styles from Bootstrap overrides - Use high specificity selectors */
    body.booking-page .main-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        padding: 18px 60px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(10px) !important;
        z-index: 1000 !important;
        box-shadow: 0 4px 18px rgba(0,0,0,0.06) !important;
        font-family: "Snell Roundhand","Playfair Display","Lato","Segoe UI","Didot", cursive, system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
    }
    
    body.booking-page .main-header .main-nav {
        display: flex !important;
        gap: 40px !important;
        font-size: 16px !important;
        justify-content: center !important;
        flex: 1 !important;
        font-family: "Snell Roundhand","Playfair Display","Lato","Segoe UI","Didot", cursive, system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
    }
    
    /* Target header nav links specifically to override Bootstrap */
    body.booking-page .main-header .main-nav a.nav-link,
    body.booking-page .main-header .main-nav .nav-link,
    body.booking-page .main-header .main-nav .dropdown-toggle {
        text-decoration: none !important;
        color: #6b5043 !important;
        position: relative !important;
        padding-bottom: 4px !important;
        font-size: 16px !important;
        font-weight: 500 !important;
        font-family: "Snell Roundhand","Playfair Display","Lato","Segoe UI","Didot", cursive, system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
        background: none !important;
        border: none !important;
        cursor: pointer !important;
        transition: color 0.3s ease !important;
        padding-top: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin: 0 !important;
    }
    
    body.booking-page .main-header .main-nav a.nav-link.active,
    body.booking-page .main-header .main-nav .nav-link.active,
    body.booking-page .main-header .main-nav a.nav-link:hover,
    body.booking-page .main-header .main-nav .nav-link:hover,
    body.booking-page .main-header .main-nav .dropdown-toggle:hover {
        color: #c29076 !important;
        background: none !important;
    }
    
    body.booking-page .main-header .main-nav a.nav-link:not(.dropdown-toggle).active::after,
    body.booking-page .main-header .main-nav .nav-link:not(.dropdown-toggle).active::after,
    body.booking-page .main-header .main-nav a.nav-link:not(.dropdown-toggle):hover::after,
    body.booking-page .main-header .main-nav .nav-link:not(.dropdown-toggle):hover::after {
        content: "" !important;
        position: absolute !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 26px !important;
        height: 2px !important;
        border-radius: 999px !important;
        background: #c29076 !important;
    }
    
    body.booking-page .main-header .main-nav .dropdown-toggle::after {
        content: " ▾" !important;
        display: inline-block !important;
        margin-left: 4px !important;
        font-size: 0.9em !important;
        position: relative !important;
        background: none !important;
        width: auto !important;
        height: auto !important;
        border-radius: 0 !important;
        left: auto !important;
        bottom: auto !important;
        border: none !important;
        margin: 0 0 0 4px !important;
        padding: 0 !important;
    }
    
    body.booking-page .main-header .nav-dropdown {
        position: relative !important;
        display: inline-block !important;
    }
    
    body.booking-page .main-header .nav-dropdown .dropdown-menu {
        position: absolute !important;
        top: 26px !important;
        left: 0 !important;
        background: white !important;
        border: 1px solid #e6d9d2 !important;
        border-radius: 10px !important;
        padding: 12px 0 !important;
        width: 190px !important;
        box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
        display: none !important;
        z-index: 1050 !important;
        min-width: auto !important;
        margin: 0 !important;
    }
    
    body.booking-page .main-header .nav-dropdown:hover .dropdown-menu {
        display: block !important;
    }
    
    body.booking-page .main-header .nav-dropdown .dropdown-menu a {
        display: block !important;
        padding: 10px 16px !important;
        color: #8a766e !important;
        text-decoration: none !important;
        font-size: 15px !important;
        font-family: "Snell Roundhand","Playfair Display","Lato","Segoe UI","Didot", cursive, system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
        transition: background 0.2s ease !important;
        background: transparent !important;
        border: none !important;
        margin: 0 !important;
    }
    
    body.booking-page .main-header .nav-dropdown .dropdown-menu a:hover {
        background: #f5ece8 !important;
        color: #c29076 !important;
    }
    
    body.booking-page .main-header .book-btn {
        padding: 12px 32px !important;
        background: #c29076 !important;
        color: white !important;
        border: none !important;
        border-radius: 30px !important;
        font-size: 16px !important;
        cursor: pointer !important;
        margin-right: 16px !important;
        transition: all 0.25s ease !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08) !important;
    }
    
    body.booking-page .main-header .book-btn:hover {
        background: #ad7c65 !important;
        box-shadow: 0 6px 14px rgba(0,0,0,0.15) !important;
        color: white !important;
    }
    
    body.booking-page .main-header .profile-btn {
        background: none !important;
        border: none !important;
        cursor: pointer !important;
        padding: 5px !important;
        display: flex !important;
        align-items: center !important;
    }
    
    body.booking-page .main-header .header-profile-img {
        width: 32px !important;
        height: 32px !important;
        object-fit: contain !important;
        filter: invert(63%) sepia(11%) saturate(1319%) hue-rotate(338deg) brightness(92%) contrast(86%) !important;
        transition: transform 0.2s ease !important;
    }
    
    body.booking-page .main-header .profile-btn:hover .header-profile-img {
        transform: scale(1.1) !important;
    }
    
    /* Ensure profile panel works on booking page - Override Bootstrap and ensure visibility */
    body.booking-page .profile-panel {
        position: fixed !important;
        top: 90px !important;
        right: 40px !important;
        width: 280px !important;
        z-index: 10001 !important; /* Higher than modals (10000) */
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transform: translateY(-20px) !important;
        transition: all 0.3s ease !important;
    }
    
    body.booking-page .profile-panel.open {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        transform: translateY(0) !important;
    }
    
    /* Ensure profile button hover works */
    body.booking-page .profile-btn:hover + .profile-panel,
    body.booking-page .profile-btn:hover ~ .profile-panel {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        transform: translateY(0) !important;
    }
    
    body.booking-page .profile-panel:hover {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        transform: translateY(0) !important;
    }
    
    body.booking-page .main-header .logo-area {
        display: flex !important;
        align-items: center !important;
    }
    
    body.booking-page .main-header .header-logo {
        height: 60px !important;
        width: auto !important;
        object-fit: contain !important;
    }
    
    /* Reset Bootstrap defaults for header elements */
    body.booking-page .main-header * {
        box-sizing: border-box;
    }
    
    body.booking-page .main-header a {
        text-decoration: none !important;
    }
    
    body.booking-page .main-header button {
        font-family: "Snell Roundhand","Playfair Display","Lato","Segoe UI","Didot", cursive, system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
    }
    
    /* Ensure dropdown menu doesn't get Bootstrap nav styles */
    body.booking-page .main-header .dropdown-menu {
        list-style: none !important;
    }
    
    body.booking-page .main-header .dropdown-menu li {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Book Now button font to match index page */
    body.booking-page .main-header .book-btn {
        font-family: "Snell Roundhand","Playfair Display","Lato","Segoe UI","Didot", cursive, system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
    }
    
    /* Ensure header links have proper line-height */
    body.booking-page .main-header .main-nav a.nav-link,
    body.booking-page .main-header .main-nav .nav-link {
        line-height: 1.5 !important;
        vertical-align: baseline !important;
    }
    
    /* Full page background and font to match index page */
    body.booking-page {
        background: linear-gradient(180deg, #f5e9e4, #faf5f2, #ffffff) !important;
        min-height: 100vh !important;
        padding-top: 90px !important; /* Space for fixed header */
        font-family: "Snell Roundhand","Playfair Display","Lato","Segoe UI","Didot", cursive, system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
        color: #555 !important;
    }
    
    body.booking-page .page-wrapper {
        background: linear-gradient(180deg, #f5e9e4, #faf5f2, #ffffff) !important;
        min-height: 100vh !important;
    }
    
    body.booking-page .container {
        background: transparent !important;
    }
    
    /* Ensure step indicator is visible - High specificity to override other styles */
    .container .step-indicator {
        display: flex !important;
        justify-content: center;
        align-items: center;
        gap: 100px !important;
        margin-bottom: 2rem;
        padding: 20px 0;
        width: 100%;
        flex-wrap: nowrap;
        overflow: visible;
    }
    
    /* Override any conflicting styles from external stylesheets */
    .container .step-indicator > .step + .step {
        margin-left: 0 !important;
    }
    
    .container .step-indicator .step:not(:last-child) {
        margin-right: 0 !important;
    }
    
    .container .step-indicator .step {
        display: flex !important;
        align-items: center !important;
        gap: 18px !important;
        opacity: 0.5;
        transition: opacity 0.3s;
        white-space: nowrap;
        margin-left: 0 !important;
        margin-right: 0 !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        flex-shrink: 0 !important;
        flex-grow: 0 !important;
        flex-basis: auto !important;
        min-width: fit-content !important;
        max-width: none !important;
        position: static !important;
        background: transparent !important;
        border: none !important;
        padding: 0 !important;
        box-shadow: none !important;
        flex-direction: row !important;
        width: auto !important;
        isolation: isolate !important;
    }
    
    /* Override any margin rules that could interfere with gap spacing */
    .container .step-indicator .step:first-child {
        margin-left: 0 !important;
    }
    
    .container .step-indicator .step:last-child {
        margin-right: 0 !important;
    }
    
    /* Force override any external CSS gap rules - ensures equal spacing between ALL steps */
    body .container .step-indicator,
    body .booking-page-bg .container .step-indicator,
    body .container .step-indicator.step-indicator {
        gap: 100px !important; /* Equal gap: Services→Staff, Staff→Date & Time, Date & Time→Review */
    }
    
    body .container .step-indicator .step,
    body .booking-page-bg .container .step-indicator .step {
        gap: 18px !important; /* Internal gap between number circle and text label for each step */
        margin: 0 !important; /* Override any margin rules from external stylesheets */
    }
    
    .container .step-indicator .step span {
        font-size: 16px !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
        display: inline-block !important;
        line-height: 1.2 !important;
        overflow: visible !important;
        text-overflow: clip !important;
        color: inherit;
    }
    
    .container .step-indicator .step.active {
        opacity: 1 !important;
        color: #2f2721 !important;
    }
    
    .container .step-indicator .step.active span {
        color: #2f2721 !important;
        font-weight: 700 !important;
    }
    
    .container .step-indicator .step.completed {
        opacity: 1 !important;
    }
    
    .container .step-indicator .step.completed span {
        color: #7a6c64 !important;
    }
    
    .container .step-indicator .step-number {
        width: 42px !important;
        height: 42px !important;
        min-width: 42px !important;
        min-height: 42px !important;
        max-width: 42px !important;
        max-height: 42px !important;
        border-radius: 50% !important;
        background: #e6d9c8 !important;
        color: #7a6c64 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: 700 !important;
        font-size: 18px !important;
        flex-shrink: 0 !important;
        flex-grow: 0 !important;
        margin-right: 0 !important;
        margin-left: 0 !important;
        position: relative !important;
        border: none !important;
        box-sizing: border-box !important;
        line-height: 1 !important;
        text-align: center !important;
        z-index: 2 !important;
        visibility: visible !important;
        opacity: 1 !important;
        overflow: visible !important;
        text-overflow: clip !important;
        padding: 0 !important;
    }
    
    /* Remove any pseudo-elements from step-number */
    .container .step-indicator .step-number::before,
    .container .step-indicator .step-number::after {
        display: none !important;
        content: none !important;
    }
    
    .container .step-indicator .step.active .step-number {
        background: #c79b19 !important;
        color: #fff !important;
    }
    
    .container .step-indicator .step.completed .step-number {
        background: #c79b19 !important;
        color: #fff !important;
    }
    
    /* Ensure span text is separate from step-number - identical for all steps */
    .container .step-indicator .step span {
        margin-left: 0 !important;
        margin-right: 0 !important;
        display: inline-block !important;
        position: relative !important;
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
        flex-shrink: 0 !important;
        flex-grow: 0 !important;
        z-index: 1 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        width: auto !important;
        min-width: auto !important;
        max-width: none !important;
    }
    
    /* Ensure all steps have identical spacing - critical for equal gaps */
    .container .step-indicator .step,
    #step-1-indicator,
    #step-2-indicator,
    #step-3-indicator,
    #step-4-indicator {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    
    /* Ensure all step spans have identical spacing */
    .container .step-indicator .step span,
    #step-1-indicator span,
    #step-2-indicator span,
    #step-3-indicator span,
    #step-4-indicator span {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        white-space: nowrap !important;
        display: inline-block !important;
    }
    
    /* Ensure step 3 "Date & Time" displays on one line without affecting spacing */
    #step-3-indicator {
        display: flex !important;
        align-items: center !important;
        gap: 18px !important;
        flex-shrink: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    #step-3-indicator span {
        max-width: none !important;
        text-align: left !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
        display: inline-block !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Responsive adjustments for smaller screens */
    @media (max-width: 768px) {
        .container .step-indicator {
            gap: 30px !important;
        }
        
        .container .step-indicator .step {
            gap: 12px !important;
        }
        
        .container .step-indicator .step span {
            font-size: 14px !important;
        }
        
        #step-3-indicator span {
            max-width: none !important;
            white-space: nowrap !important;
            font-size: 12px !important;
        }
    }
    
    /* Ensure booking steps are properly displayed - hidden by default */
    .booking-step {
        display: none !important;
    }
    
    /* JavaScript will add/remove this class to control visibility */
    .booking-step.show-step {
        display: block !important;
    }
    
    /* Make edit pencil icon more clickable */
    .edit-service-btn {
        cursor: pointer !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s ease;
        text-decoration: none !important;
    }
    
    .edit-service-btn:hover {
        background-color: rgba(194, 144, 118, 0.1) !important;
        color: #c29076 !important;
        transform: scale(1.1);
    }
    
    .edit-service-btn i {
        pointer-events: none;
    }
</style>
</head>
<body class="booking-page">

<div class="page-wrapper">

<?php
// Include header (after body tag)
require_once 'includes/header.php';
?>
<script>
</script>

<div class="container mt-4">
    <div class="step-indicator">
        <div class="step active" id="step-1-indicator"><div class="step-number">1</div><span>Services</span></div>
        <div class="step" id="step-2-indicator"><div class="step-number">2</div><span>Staff</span></div>
        <div class="step" id="step-3-indicator"><div class="step-number">3</div><span>Date & Time</span></div>
        <div class="step" id="step-4-indicator"><div class="step-number">4</div><span>Review</span></div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            
            <div id="step-1" class="booking-step" style="display: block;">
                <div class="card">
                    <div class="card-header">
                        <h4>Choose Your Services</h4>
                        <p class="mb-0">Select one or more services for your appointment</p>
                    </div>
                    <div class="card-body">
                        <?php foreach($servicesGrouped as $cat_name => $subcats): ?>
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: var(--warm-brown); border-bottom: 2px solid var(--secondary-beige); padding-bottom: 10px;">
                                    <?php echo htmlspecialchars($cat_name); ?>
                                </h5>
                                <div class="row">
                                    <?php foreach($subcats as $sub_name => $items): ?>
                                        <?php $first = $items[0]; ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card service-card h-100 group-card" data-service-id="<?php echo $first['service_id']; ?>">
                                                <div class="card-body" style="display: flex; flex-direction: column; justify-content: center; padding: 1rem 1rem 0.75rem 1rem;">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title fw-bold mb-0" style="flex: 1; text-align: left;"><?php echo htmlspecialchars($sub_name); ?></h6>
                                                        <span class="badge bg-light text-dark border price-badge">RM <?php echo number_format($first['price'], 0); ?></span>
                                                    </div>
                                                    <p class="card-text small text-muted mb-2" style="text-align: left;"><?php echo htmlspecialchars($first['description'] ?? ''); ?></p>
                                                    <?php if(count($items) > 1): ?>
                                                        <select class="form-select form-select-sm service-variant-select" onclick="event.stopPropagation();" onchange="updateCardPrice(this)">
                                                            <?php foreach($items as $v): ?>
                                                                <option value="<?php echo $v['service_id']; ?>" data-price="<?php echo $v['price']; ?>" data-duration="<?php echo $v['duration_minutes']; ?>">
                                                                    <?php echo htmlspecialchars($v['service_name']); ?> (<?php echo $v['duration_minutes']; ?>m) - RM<?php echo number_format($v['price'], 0); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge bg-secondary"><?php echo $first['duration_minutes']; ?> min</span>
                                                            <span class="fw-bold" style="color: var(--accent-gold);">RM <?php echo number_format($first['price'], 2); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
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
                                    <span class="fw-bold"><?php echo htmlspecialchars(($customer_info['first_name'] ?? '') . ' ' . ($customer_info['last_name'] ?? '')); ?></span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted text-uppercase">Phone</small><br>
                                    <span class="fw-bold"><?php echo htmlspecialchars($customer_info['phone'] ?? ''); ?></span>
                                </div>
                                <div>
                                    <small class="text-muted text-uppercase">Email</small><br>
                                    <span class="fw-bold"><?php echo htmlspecialchars($customer_info['email'] ?? ''); ?></span>
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

<!-- Booking Confirmation Modal -->
<div id="bookingConfirmationModal" class="custom-modal">
    <div class="custom-modal-content confirmation-modal-content">
        <div class="custom-modal-header confirmation-header">
            <h5 class="custom-modal-title" style="color: white;">
                <i class="fas fa-check-circle me-2"></i>Booking Confirmed!
            </h5>
        </div>
        <div class="custom-modal-body confirmation-body">
            <div class="text-center mb-4">
                <div class="success-icon mb-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4 style="color: var(--warm-brown); margin-bottom: 10px;">Your appointment is confirmed!</h4>
                <p class="text-muted mb-4">We've sent a confirmation email with all the details.</p>
            </div>
            
            <div class="booking-details-summary">
                <div class="detail-row">
                    <span class="detail-label">Booking ID:</span>
                    <span class="detail-value" id="confirmationBookingId" style="font-weight: bold; color: var(--accent-gold);"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value" id="confirmationDateTime"></span>
                </div>
            </div>
            
            <div class="booking-details-summary mt-3">
                <h6 class="mb-3" style="color: var(--warm-brown);">Services Booked</h6>
                <div id="confirmationServicesList" style="max-height: 200px; overflow-y: auto;">
                    <!-- Services with staff will be populated here -->
                </div>
            </div>
            
            <div class="booking-details-summary mt-3">
                <div class="detail-row" style="border-top: 2px solid var(--accent-gold); padding-top: 15px; margin-top: 10px;">
                    <span class="detail-label" style="font-weight: bold; font-size: 1.1em;">Total:</span>
                    <span class="detail-value" id="confirmationTotal" style="font-weight: bold; color: var(--accent-gold); font-size: 1.1em;"></span>
                </div>
            </div>
            
            <div class="confirmation-note mt-4 p-3" style="background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--accent-gold);">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-2"></i>
                    Please arrive 10 minutes early. Payment will be collected at the salon.
                </small>
            </div>
        </div>
        <div class="custom-modal-footer confirmation-footer">
            <button type="button" class="btn btn-secondary" onclick="viewBookings()">View Bookings</button>
            <button type="button" class="btn btn-primary" onclick="goToHomepage()">Done</button>
        </div>
    </div>
</div>

<!-- Lumière Brand Error Modal -->
<div id="errorModal" class="custom-modal">
    <div class="custom-modal-content lumiere-error-modal">
        <div class="custom-modal-header lumiere-error-header">
            <h5 class="custom-modal-title">We need a little adjustment</h5>
            <button type="button" class="lumiere-error-close" onclick="closeErrorModal()" aria-label="Close">&times;</button>
        </div>
        <div class="custom-modal-body lumiere-error-body">
            <p id="errorMessage">Something went wrong with your booking. Please try a different time slot.</p>
        </div>
        <div class="custom-modal-footer lumiere-error-footer">
            <button type="button" class="btn-custom-ok" onclick="closeErrorModal()">Got it</button>
        </div>
    </div>
</div>

<style>
/* Base modal backdrop */
.custom-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.2s ease-in;
}

.custom-modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.custom-modal-content {
    background-color: #fffaf5;
    color: #5b4734;
    padding: 0;
    border: none;
    border-radius: 18px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
    animation: slideIn 0.2s ease-out;
}

.custom-modal-header {
    padding: 16px 20px 10px;
    border-bottom: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.custom-modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #6b3d23;
}

.custom-modal-body {
    padding: 8px 20px 18px;
    font-size: 15px;
    color: #5b4734;
}

.custom-modal-body p {
    margin: 0;
    color: inherit;
}

.custom-modal-footer {
    padding: 10px 20px 18px;
    text-align: right;
    border-top: none;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-custom-ok {
    padding: 8px 22px;
    border: none;
    border-radius: 999px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background-color 0.2s;
    background: linear-gradient(to right, #d7bb91, #b59267);
    color: #ffffff;
}

.btn-custom-ok:hover {
    background: linear-gradient(to right, #b59267, #d7bb91);
}

/* Brand-specific error modal tweaks */
.lumiere-error-modal {
    border: 1px solid #d3b58c;
}

.lumiere-error-header {
    background: transparent;
}

.lumiere-error-close {
    border: none;
    background: transparent;
    font-size: 22px;
    line-height: 1;
    color: #b08a5a;
    cursor: pointer;
    padding: 0;
}

.lumiere-error-footer {
    justify-content: flex-end;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Confirmation Modal Styles */
.confirmation-modal-content {
    max-width: 500px;
    background: white;
    color: #333;
}

.confirmation-header {
    background: linear-gradient(135deg, var(--accent-gold), #c79b19);
    color: white;
    border-bottom: none;
}

.confirmation-header .custom-modal-title {
    color: white;
    font-size: 18px;
    font-weight: 600;
}

.confirmation-body {
    color: #333;
    max-height: 70vh;
    overflow-y: auto;
}

.success-icon {
    font-size: 60px;
    color: #28a745;
    animation: scaleIn 0.3s ease-out;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.booking-details-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #dee2e6;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #6c757d;
}

.detail-value {
    color: #333;
    text-align: right;
}

.confirmation-note {
    font-size: 13px;
}

.confirmation-footer {
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    gap: 10px;
}

.confirmation-footer .btn {
    padding: 10px 24px;
    border-radius: 6px;
    font-weight: 500;
}
</style>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Error Modal Functions
    function showErrorModal(message) {
        document.getElementById('errorMessage').textContent = message;
        const modal = document.getElementById('errorModal');
        modal.classList.add('show');
    }

    function closeErrorModal() {
        const modal = document.getElementById('errorModal');
        modal.classList.remove('show');
    }

    // Booking Confirmation Modal Functions
    function showBookingConfirmation(bookingData) {
        // Populate confirmation details
        document.getElementById('confirmationBookingId').textContent = bookingData.booking_id || 'N/A';
        document.getElementById('confirmationDateTime').textContent = 
            (bookingData.date || '') + ' at ' + (bookingData.time || '');
        document.getElementById('confirmationTotal').textContent = 
            bookingData.subtotal ? 'RM ' + parseFloat(bookingData.subtotal).toFixed(2) : 'RM 0.00';
        
        // Populate services list with staff assignments
        const servicesListContainer = document.getElementById('confirmationServicesList');
        if (bookingData.services && Array.isArray(bookingData.services) && bookingData.services.length > 0) {
            let servicesHtml = '<table class="table table-sm" style="margin-bottom: 0;">';
            servicesHtml += '<thead><tr><th>Service</th><th>Duration</th><th>Staff</th><th>Price</th></tr></thead><tbody>';
            
            bookingData.services.forEach(function(service) {
                servicesHtml += '<tr>';
                servicesHtml += '<td>' + (service.service_name || 'Service') + '</td>';
                servicesHtml += '<td>' + (service.duration || 0) + ' min</td>';
                servicesHtml += '<td>' + (service.staff_name || 'No Preference') + '</td>';
                servicesHtml += '<td>RM ' + parseFloat(service.price || 0).toFixed(2) + '</td>';
                servicesHtml += '</tr>';
            });
            
            servicesHtml += '</tbody></table>';
            servicesListContainer.innerHTML = servicesHtml;
        } else {
            // Fallback if services array is not available
            servicesListContainer.innerHTML = '<p class="text-muted">' + (bookingData.services || 'Multiple services') + '</p>';
        }
        
        // Show modal
        const modal = document.getElementById('bookingConfirmationModal');
        modal.classList.add('show');
    }

    function closeBookingConfirmation() {
        const modal = document.getElementById('bookingConfirmationModal');
        modal.classList.remove('show');
    }

    function viewBookings() {
        closeBookingConfirmation();
        // Redirect to user dashboard bookings section
        window.location.href = 'user/dashboard.php?section=bookings&t=' + Date.now();
    }

    function goToHomepage() {
        closeBookingConfirmation();
        // Redirect to homepage
        window.location.href = 'user/index.php';
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const errorModal = document.getElementById('errorModal');
        const confirmationModal = document.getElementById('bookingConfirmationModal');
        
        if (event.target == errorModal) {
            closeErrorModal();
        }
        if (event.target == confirmationModal) {
            closeBookingConfirmation();
        }
    });

    window.staffData = <?php echo json_encode($staff); ?>;
    window.staffServiceMap = <?php echo json_encode($staffServiceMap); ?>;
    
    <?php 
    $jsServices = [];
    if (isset($servicesFlat) && is_array($servicesFlat)) {
        foreach($servicesFlat as $s) {
            $jsServices[$s['service_id']] = [
                'name' => $s['service_name'],
                'duration' => (int)$s['duration_minutes'],
                'price' => (float)$s['price'],
                'category' => $s['category_name'] ?? '',
                'sub_category' => $s['sub_category'] ?? ''
            ];
        }
    }
    ?>
    
    window.allServicesData = <?php echo json_encode($jsServices); ?>;
    
    // Pass server's current time to JavaScript (in milliseconds timestamp)
    // This ensures time slots are disabled based on server time, not client time
    window.serverTime = <?php echo time() * 1000; ?>;
</script>

<script src="js/booking.js"></script>
</div> <!-- Close container -->

</div> <!-- Close page-wrapper -->

</body>
</html>
