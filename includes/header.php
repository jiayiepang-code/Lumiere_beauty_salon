<?php
// Start session if not started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();

    // 1. Calculate Initials for the Avatar (e.g., "Wong Yi" -> "WY")
$avatarInitials = "G"; // Default for Guest
$displayName = "Guest";

if (isset($_SESSION['user_name'])) {
    $displayName = $_SESSION['user_name'];
    // Extract initials
    $words = explode(" ", $displayName);
    if (count($words) >= 2) {
        $avatarInitials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } else {
        $avatarInitials = strtoupper(substr($displayName, 0, 2));
    }
}
} // <-- close session_status check
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lumière Beauty Salon</title>

    <!-- Your CSS -->
    <link rel="stylesheet" href="home.css">
</head>

<body>

<!-- ========== PAGE WRAPPER ========== -->
<div class="page-wrapper">

<!-- ========== HEADER ========== -->
<header class="main-header">

    <!-- LOGO -->
    <div class="logo-area">
        <img src="../images/16.png" alt="Lumière Beauty Salon Logo" class="header-logo">
    </div>

    <!-- NAVIGATION -->
    <nav class="main-nav">
    <a href="../user/index.php" class="nav-link">Home</a> 
    
    <a href="../user/services.php" class="nav-link">Services</a>

        <!-- Dropdown Menu -->
        <div class="nav-dropdown">
           <a href="team.php" class="nav-link dropdown-toggle">Meet The Team ▾</a>

           <div class="dropdown-menu">
        <a href="team.php?cat=hair">Hair Stylists</a>
        <a href="team.php?cat=beauty">Beauticians</a>
        <a href="team.php?cat=massage">Massage Therapists</a>
        <a href="team.php?cat=nail">Nail Technicians</a>
           </div>
        </div>

        <a href="../user/contact.php" class="nav-link">Contact Us</a>
    </nav>

    <!-- BOOK NOW BUTTON -->
    <button class="book-btn" onclick="window.location.href='booking.php'">Book Now</button>

<?php if (isset($_SESSION['user_email'])): ?>
    <button class="profile-btn" id="profileToggle" aria-label="Profile menu">
        <img src="images/50.png" class="header-profile-img" alt="Profile">
    </button>
<?php else: ?>
    <a href="login.php" class="profile-btn">
        <img src="images/50.png" class="header-profile-img" alt="Login">
    </a>
<?php endif; ?>

</header>

<aside class="profile-panel" id="profilePanel">
    <button class="panel-close" id="panelClose">✕</button>

    <?php if (isset($_SESSION['phone'])): ?>
        
        <div class="panel-content">
            <div class="panel-header">
                <div id="dynamicAvatar" class="avatar-circle"><?php echo $avatarInitials; ?></div>
                
                <div class="user-details">
                    <h3 id="panelUserName"><?php echo htmlspecialchars($displayName); ?></h3>
                    <span class="user-role-badge">Customer</span>
                </div>
            </div>

            <ul class="panel-menu">
                <li><a href="user/profile.php">My Profile</a></li>
                <li><a href="user/history.php">My Bookings</a></li>
                <li><a href="user/favourites.php">Favourites Staff</a></li>
                <li><a href="user/settings.php">Settings</a></li>
                <li><a href="about.php">About Us</a></li>
            </ul> 

            <div class="panel-divider"></div>

            <a href="#" class="panel-logout" onclick="handleLogout()">
                <img src="images/51.png" class="logout-img" alt="Logout">
                Logout
            </a>
        </div>

    <?php else: ?>

        <div class="panel-content">
            <div class="panel-header">
                <div class="avatar-circle">?</div>
                <div class="user-details">
                    <h3>Welcome!</h3>
                    <span class="user-role-badge">Guest</span>
                </div>
            </div>
            <p style="font-size:14px; color:#7c6a65; margin:15px 0;">
                Please log in to view your profile.
            </p>
            <ul class="panel-menu">
                <li><a href="login.php" style="color: #4a3b32; font-weight:bold;">Login</a></li>
                <li><a href="register.php" style="color: #c29076;">Register</a></li>
            </ul>
        </div>

    <?php endif; ?>
</aside>

<script>
function handleLogout() {
    if(confirm("Are you sure you want to logout?")) {
        window.location.href = 'logout.php';
    }
}
</script>