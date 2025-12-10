<?php
session_start();

$isLoggedIn = isset($_SESSION['customer_phone']);  // using phone as “ID”

$firstName = $_SESSION['first_name'] ?? '';
$lastName  = $_SESSION['last_name']  ?? '';
$initials  = $_SESSION['initials']   ?? (strlen($firstName) > 1 ? strtoupper(substr($firstName, 0, 2)) : '?');
$role      = $_SESSION['role']       ?? 'Guest';

// for login/register redirect links
$currentUrl        = $_SERVER['REQUEST_URI'];
$currentUrlEncoded = urlencode($currentUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lumière Beauty Salon – Home</title>

    <!-- global + home css -->
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/style.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/home.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/footer.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/header.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
</head>
</head>
<body>

<div class="page-wrapper">

    <!-- ================= HEADER ================= -->
    <header class="main-header">

        <!-- LOGO -->
        <div class="logo-area">
            <img src="../images/16.png"
                 alt="Lumière Beauty Salon Logo"
                 class="header-logo">
        </div>

        <!-- NAVIGATION -->
        <nav class="main-nav">
            <a href="home.php" class="nav-link active">Home</a>
            <a href="services.php" class="nav-link">Services</a>

            <!-- Meet the Team dropdown -->
            <div class="nav-dropdown">
               <button class="nav-link dropdown-toggle">Meet The Team ▾</button>
               <div class="dropdown-menu">
            <a href="team.php?cat=hair">Hair Stylists</a>
            <a href="team.php?cat=beauty">Beauticians</a>
            <a href="team.php?cat=massage">Massage Therapists</a>
            <a href="team.php?cat=nail">Nail Technicians</a>
                </div>
            </div>

            <a href="contact.php" class="nav-link">Contact Us</a>
        </nav>

        <!-- BOOK NOW (forces login if guest) -->
        <?php if ($isLoggedIn): ?>
    <button class="book-btn"
            onclick="window.location.href='booking-service.php'">
        Book Now
    </button>
<?php else: ?>
    <button class="book-btn"
            onclick="window.location.href='../auth/login.php?mode=login&redirect=user/booking-service.php'">
        Book Now
    </button>
<?php endif; ?>

        <!-- PROFILE ICON -->
        <button class="profile-btn" id="profileToggle" aria-label="Profile menu">
            <img src="../images/50.png" class="header-profile-img" alt="Profile">
        </button>
    </header>

    <!-- ================= PROFILE PANEL ================= -->
    <aside class="profile-panel" id="profilePanel">
        <button class="panel-close" id="panelClose">✕</button>

<?php if ($isLoggedIn): ?>
    <div class="panel-content">
        <div class="panel-header">
            <div id="dynamicAvatar" class="avatar-circle">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div class="user-details">
                <h3 id="panelUserName">
                    <?= htmlspecialchars($firstName . ' ' . $lastName) ?>
                </h3>
                <span class="user-role-badge">
                    <?= htmlspecialchars($role) ?>
                </span>
            </div>
        </div>

        <ul class="panel-menu">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="history.php">Booking History</a></li>
            <li><a href="favourites.php">Favourites Staff</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="../about.php">About Us</a></li>
        </ul>

        <div class="panel-divider"></div>

        <a href="../logout.php" class="panel-logout">
            <img src="../images/51.png" class="logout-img" alt="Logout">
            Logout
        </a>
    </div>

<?php else: ?>
            <!-- GUEST VIEW -->
            <div class="panel-content">
                <div class="panel-header">
                    <div class="avatar-circle">?</div>
                    <div class="user-details">
                        <h3>Welcome!</h3>
                        <span class="user-role-badge">Guest</span>
                    </div>
                </div>

                <p style="font-size:14px; color:#7c6a65; margin-bottom:16px;">
                    Please log in to view your profile, bookings, and favourites.
                </p>

                <ul class="panel-menu">
                <li>
                    <!-- OPEN LOGIN TAB -->
                    <a href="../login.php?mode=login&redirect=<?= $currentUrlEncoded ?>">
                    Login
                    </a>
                </li>

                <li>
                    <!-- OPEN REGISTER TAB -->
                    <a href="../register.php?mode=register&redirect=<?= $currentUrlEncoded ?>">
                    Register
                    </a>
                </li>
                </ul>
            </div>
        <?php endif; ?>
    </aside>

    <!-- ================= HERO ================= -->
    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <!-- typing effect goes into #typingText -->
            <h1 class="hero-title">
                <span id="typingText"></span>
            </h1>
            <button class="hero-btn" id="exploreBtn">Explore More</button>
        </div>
    </section>

    <!-- ================= TOP SERVICES ================= -->
    <section class="top-services-section reveal" id="services">
        <h2 class="section-title">Top Services</h2>
        <p class="section-subtitle">Choose from our most popular services</p>

        <div class="service-grid">
            <!-- example cards – keep or replace with your real ones -->
            <div class="service-box stagger" onclick="window.location.href='services.php?category=haircut'">
                <img src="../images/14.png" class="service-img" alt="Haircut">
                <div class="service-overlay">haircut</div>
            </div>

            <div class="service-box stagger" onclick="window.location.href='services.php?category=facial'">
                <img src="../images/11.png" class="service-img" alt="Facial">
                <div class="service-overlay">facial</div>
            </div>

            <div class="service-box stagger" onclick="window.location.href='services.php?category=manicure'">
                <img src="../images/8.png" class="service-img" alt="Manicure">
                <div class="service-overlay">manicure</div>
            </div>

            <div class="service-box stagger" onclick="window.location.href='services.php?category=massage'">
                <img src="../images/5.png" class="service-img" alt="Massage">
                <div class="service-overlay">massage</div>
            </div>
        </div>
    </section>

    <!-- ================= FACILITY SLIDER ================= -->
    <section class="facility-slider-section reveal">
        <h2 class="award-title">Kota Kinabalu’s Award-Winning Beauty Salon</h2>
        <p class="award-subtitle">
            Explore our premium treatment rooms &amp; workstation areas
        </p>

        <div class="facility-slider-container">
            <button class="fac-arrow left" id="facPrev">&#10094;</button>

            <!-- Slide 1 -->
            <div class="facility-slide active">
                <div class="slide-images">
                    <img src="../images/18.png" alt="">
                    <img src="../images/20.png" alt="">
                </div>
                <div class="slide-room-title">Haircut Area</div>
            </div>

            <!-- Slide 2 -->
            <div class="facility-slide">
                <div class="slide-images">
                    <img src="../images/17.png" alt="">
                    <img src="../images/22.png" alt="">
                </div>
                <div class="slide-room-title">Facial Room</div>
            </div>

            <!-- Slide 3 -->
            <div class="facility-slide">
                <div class="slide-images">
                    <img src="../images/24.png" alt="">
                    <img src="../images/25.png" alt="">
                </div>
                <div class="slide-room-title">Massage Area</div>
            </div>

            <!-- Slide 4 -->
            <div class="facility-slide">
                <div class="slide-images">
                    <img src="../images/19.png" alt="">
                    <img src="../images/21.png" alt="">
                </div>
                <div class="slide-room-title">Nail Studio</div>
            </div>

            <button class="fac-arrow right" id="facNext">&#10095;</button>
        </div>
    </section>

    <!-- ================= FEEDBACK, CTA, FOOTER ================= -->
    <!-- ⭐ CUSTOMER FEEDBACK SECTION -->
<section class="feedback-section">
            <h2 class="feedback-title">Customer's Feedback</h2>
            <div class="feedback-wrapper">
                <div class="feedback-card">
                    <div class="stars">★★★★★</div>
                    <div class="feedback-name">Aisha Rahman</div>
                    <p class="feedback-text">
                        "Love my new nails color! The color is perfect, and the attention to detail was amazing. Highly recommend the Sophie nail technician!"
                    </p>
                </div>

                <div class="feedback-card">
                    <div class="stars">★★★★★</div>
                    <div class="feedback-name">Serena Lim</div>
                    <p class="feedback-text">
                        "My haircut looks fantastic! The stylist really understood what I wanted and gave me great advice. Such a professional service!"
                    </p>
                </div>

                <div class="feedback-card">
                    <div class="stars">★★★★★</div>
                    <div class="feedback-name">Priya Sharma</div>
                    <p class="feedback-text">
                        "My skin is glowing after the facial! The skincare specialist was gentle and very knowledgeable. I feel refreshed and pampered. Will be booking again soon!"
                    </p>
                </div>

                <div class="feedback-card">
                    <div class="stars">★★★★★</div>
                    <div class="feedback-name">Chloe Ong</div>
                    <p class="feedback-text">
                        "The massage was incredibly relaxing. I felt all the tension melting away, and the room was so cozy and tranquil. Definitely will come again!"
                    </p>
                </div>
            </div>
        </section>

<!-- ================= BOOKING SECTION ================= -->
<section class="booking-section reveal">
    <div class="booking-box">

        <h2>Ready to Treat Yourself?</h2>

        <p>Book your appointment today and experience the ultimate in beauty and relaxation!</p>

        <?php if ($isLoggedIn): ?>
            <button class="booking-btn" onclick="window.location.href='booking.php'">
                Book Now
            </button>
        <?php else: ?>
            <button class="booking-btn" onclick="window.location.href='login.php?redirect=user/booking-service.php'">
                Book Now
            </button>
        <?php endif; ?>

    </div>
</section>

    <?php include '../includes/footer.php'; ?>

    

</div> <script src="../js/home.js"></script>
</body>
</html>

