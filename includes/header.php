<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================
// LOGIN STATUS & USER DATA
// ==========================
$isLoggedIn = isset($_SESSION['customer_phone']) || isset($_SESSION['customer_email']);

$firstName = $_SESSION['first_name'] ?? '';
$lastName  = $_SESSION['last_name']  ?? '';
// Only show first 2 characters of first name (e.g., "Jisoo" -> "JI")
$initials  = $_SESSION['initials']   ?? ($isLoggedIn && strlen($firstName) > 0 
    ? strtoupper(substr($firstName, 0, 2))
    : '?');
$role      = $_SESSION['role']       ?? 'Guest';

$displayName = $isLoggedIn
    ? ($firstName . " " . $lastName)
    : "Guest";

// Avatar initials for header button - only first 2 characters of first name
if ($isLoggedIn) {
    $avatarInitials = strtoupper(substr($firstName, 0, 2));
} else {
    $avatarInitials = "G";
}

// for login/register redirect links
$currentUrl        = $_SERVER['REQUEST_URI'];
$currentUrlEncoded = urlencode($currentUrl);

// Asset base adjusts paths for root pages (e.g., booking.php) vs /user pages
$assetBase = (strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false) ? '../' : '';

// Detect booking page (used for body class and conditional assets)
$isBookingPage = basename($_SERVER['SCRIPT_NAME']) === 'booking.php';
if (!isset($bodyClass)) {
    $bodyClass = $isBookingPage ? 'booking-page' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lumière Beauty Salon</title>

    <link rel="stylesheet" href="<?php echo $assetBase; ?>css/style.css">
    <link rel="stylesheet" href="<?php echo $assetBase; ?>css/home.css">
    <?php if ($isBookingPage): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <?php endif; ?>
</head>

<body class="<?php echo htmlspecialchars(trim($bodyClass)); ?>">

<div class="page-wrapper">

<header class="main-header">

    <div class="logo-area">
        <a href="<?php echo $assetBase; ?>user/index.php" style="text-decoration: none; display: block;">
            <img src="<?php echo $assetBase; ?>images/16.png" class="header-logo">
        </a>
    </div>

    <nav class="main-nav">
        <a href="<?php echo $assetBase; ?>user/index.php" class="nav-link">Home</a>
        <a href="<?php echo $assetBase; ?>user/services.php" class="nav-link">Services</a>

        <div class="nav-dropdown">
            <a href="<?php echo $assetBase; ?>user/team.php" class="nav-link dropdown-toggle">Meet The Team</a>

            <div class="dropdown-menu">
                <a href="<?php echo $assetBase; ?>user/team.php?cat=hair">Hair Stylists</a>
                <a href="<?php echo $assetBase; ?>user/team.php?cat=beauty">Beauticians</a>
                <a href="<?php echo $assetBase; ?>user/team.php?cat=massage">Massage Therapists</a>
                <a href="<?php echo $assetBase; ?>user/team.php?cat=nail">Nail Technicians</a>
            </div>
        </div>

        <a href="<?php echo $assetBase; ?>user/contact.php" class="nav-link">Contact Us</a>
    </nav>

    <!-- Book Now Button -->
    <?php 
    $isLoggedInHeader = isset($_SESSION['customer_phone']) || isset($_SESSION['customer_email']);
    if ($isLoggedInHeader): ?>
        <button class="book-btn" onclick="location.href='<?php echo $assetBase; ?>booking.php'">Book Now</button>
    <?php else: ?>
        <button class="book-btn" onclick="location.href='<?php echo $assetBase; ?>login.php?redirect=booking.php'">Book Now</button>
    <?php endif; ?>

    <!-- Profile Button -->
    <?php if ($isLoggedIn): ?>
        <a href="<?php echo $assetBase; ?>user/dashboard.php?section=profile" id="profileToggle" class="profile-btn" aria-label="Profile menu" style="text-decoration: none; display: inline-block;">
            <img src="<?php echo $assetBase; ?>images/50.png" class="header-profile-img" alt="Profile">
        </a>
    <?php else: ?>
        <button id="profileToggle" class="profile-btn" aria-label="Profile menu">
            <img src="<?php echo $assetBase; ?>images/50.png" class="header-profile-img" alt="Profile">
        </button>
    <?php endif; ?>

</header>

<aside class="profile-panel" id="profilePanel">
    <button class="panel-close" id="panelClose">✕</button>

    <?php if ($isLoggedIn): ?>
        <div class="panel-content">
            <div class="panel-header">
                <a href="<?php echo $assetBase; ?>user/dashboard.php?section=profile" style="text-decoration: none; display: block;">
                    <div id="dynamicAvatar" class="avatar-circle" style="cursor: pointer;">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                </a>
                <div class="user-details">
                    <h3 id="panelUserName">
                        <?= htmlspecialchars(trim($firstName . ' ' . $lastName)) ?>
                    </h3>
                    <span class="user-role-badge">
                        <?= htmlspecialchars($role) ?>
                    </span>
                </div>
            </div>

            <ul class="panel-menu">
                <li><a href="<?php echo $assetBase; ?>user/dashboard.php?section=overview">Overview</a></li>
                <li><a href="<?php echo $assetBase; ?>user/dashboard.php?section=bookings">My Bookings</a></li>
                <li><a href="<?php echo $assetBase; ?>user/dashboard.php?section=profile">My Profile</a></li>
                <li><a href="<?php echo $assetBase; ?>user/dashboard.php?section=favourites">Favourites Staff</a></li>
                <li><a href="<?php echo $assetBase; ?>user/dashboard.php?section=help">Help & Support</a></li>
            </ul>

            <div class="panel-divider"></div>

            <a href="<?php echo $assetBase; ?>logout.php" class="panel-logout">
                <img src="<?php echo $assetBase; ?>images/51.png" class="logout-img" alt="Logout">
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
                    <a href="<?php echo $assetBase; ?>login.php?mode=login&redirect=<?= $currentUrlEncoded ?>">
                    Login
                    </a>
                </li>

                <li>
                    <!-- OPEN REGISTER TAB -->
                    <a href="<?php echo $assetBase; ?>register.php?mode=register&redirect=<?= $currentUrlEncoded ?>">
                    Register
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</aside>

<script>
// Profile Panel Toggle Script (Works on all pages that include header.php)
document.addEventListener("DOMContentLoaded", function() {
    const toggle = document.getElementById("profileToggle");
    const panel = document.getElementById("profilePanel");
    const closeBtn = document.getElementById("panelClose");

    if (toggle && panel) {
        let hoverTimeout;
        let isHovering = false;

        // Show panel on hover
        toggle.addEventListener("mouseenter", () => {
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => {
                panel.classList.add("open");
                isHovering = true;
            }, 150); // Small delay to prevent accidental triggers
        });

        // Keep panel open when hovering over it
        panel.addEventListener("mouseenter", () => {
            clearTimeout(hoverTimeout);
            panel.classList.add("open");
            isHovering = true;
        });

        // Hide panel when mouse leaves both button and panel
        toggle.addEventListener("mouseleave", () => {
            hoverTimeout = setTimeout(() => {
                if (!isHovering) {
                    panel.classList.remove("open");
                }
            }, 200);
        });

        panel.addEventListener("mouseleave", () => {
            hoverTimeout = setTimeout(() => {
                panel.classList.remove("open");
                isHovering = false;
            }, 200);
        });

        // Also support click for mobile/touch devices
        if (toggle.tagName === 'BUTTON') {
            toggle.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                panel.classList.toggle("open");
            });
        } else if (toggle.tagName === 'A') {
            // For logged-in users, prevent navigation on click, show panel instead
            toggle.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                panel.classList.toggle("open");
            });
        }

        // Close button
        if (closeBtn) {
            closeBtn.addEventListener("click", () => {
                panel.classList.remove("open");
                isHovering = false;
            });
        }

        // Click outside to close
        document.addEventListener("click", (e) => {
            if (panel.classList.contains("open") && 
                !panel.contains(e.target) && 
                !toggle.contains(e.target)) {
                panel.classList.remove("open");
                isHovering = false;
            }
        });

        // Verify panel stays fixed when scrolling
        let scrollCheckCount = 0;
        window.addEventListener("scroll", () => {
            if (panel && panel.classList.contains("open") && scrollCheckCount < 3) {
                scrollCheckCount++;
                setTimeout(() => {
                    // Panel scroll position check (debugging code removed)
                }, 100);
            }
        });
    }
});
</script>
