<?php
session_start();

// CHECK LOGIN STATUS
$isLoggedIn = isset($_SESSION['customer_phone']);
$firstName  = $_SESSION['first_name'] ?? '';
$lastName   = $_SESSION['last_name'] ?? '';
$role       = $_SESSION['role'] ?? 'Guest';
$initials   = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

// LOAD SHARED HEADER (LOGO + NAV + PROFILE PANEL)
include "../includes/header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services – Lumière Beauty Salon</title>

    <!-- GLOBAL CSS -->
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/style.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/home.css">
    <!-- PAGE-SPECIFIC CSS -->
    <link rel="stylesheet" href="services.css">

    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
</head>

<body>

<!-- ========== SERVICES HERO SECTION ========== -->
<section class="services-header">
    <h1 class="services-title">Our Services</h1>
    <p class="services-subtitle">Explore our full range of beauty treatments</p>
</section>

<!-- CATEGORY BUTTONS -->
<div class="service-category-buttons">
    <button class="service-cat" onclick="selectCategory('haircut')">Haircut</button>
    <button class="service-cat" onclick="selectCategory('facial')">Facial</button>
    <button class="service-cat" onclick="selectCategory('manicure')">Manicure</button>
    <button class="service-cat" onclick="selectCategory('massage')">Massage</button>
</div>

<!-- DYNAMIC SERVICE CONTENT AREA -->
<div id="serviceDetails"></div>

<!-- LOAD SHARED FOOTER -->
<?php include "../includes/footer.php"; ?>

<!-- PAGE SCRIPTS -->
<script src="services.js"></script>

<script>
// AUTO LOAD DEFAULT CATEGORY
const params = new URLSearchParams(window.location.search);
selectCategory(params.get("category") || "haircut");
</script>

</body>
</html>
