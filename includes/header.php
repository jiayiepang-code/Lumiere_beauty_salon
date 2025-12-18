<?php
/**
 * User Header - Shared header for user pages
 * Include this file in user pages to get the header and profile panel
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login status & user data
$isLoggedIn = isset($_SESSION['customer_phone']);  // using phone as "ID"

$firstName = $_SESSION['first_name'] ?? '';
$lastName  = $_SESSION['last_name']  ?? '';
$initials  = $_SESSION['initials']   ?? (strlen($firstName) > 1 ? strtoupper(substr($firstName, 0, 2)) : '?');
$role      = $_SESSION['role']       ?? 'Guest';

// for login/register redirect links
$currentUrl        = $_SERVER['REQUEST_URI'];
$currentUrlEncoded = urlencode($currentUrl);

// Determine base path for navigation links based on where header is included from
$currentScript = $_SERVER['PHP_SELF'] ?? '';

// Are we currently in the /user/ folder?
$inUserFolder = strpos($currentScript, '/user/') !== false;

// Used for user-facing nav links (Home, Services, Team, Contact)
// - From booking.php (root) → go into user/ (e.g. user/index.php)
// - From user/*.php       → stay in current folder (e.g. index.php)
$basePath = strpos($currentScript, '/booking.php') !== false ? 'user/' : '';

// Prefix needed to reach root-level scripts like login.php, register.php, logout.php, booking.php
// - From /user/*.php      → '../'
// - From root pages       → ''
$rootPrefix = $inUserFolder ? '../' : '';

// Image paths
$imagePath = $inUserFolder ? '../images/' : 'images/';
// #region agent log
file_put_contents('c:\xampp\htdocs\Lumiere_beauty_salon\.cursor\debug.log', json_encode(['location' => 'includes/header.php:' . __LINE__, 'message' => 'Header path resolution', 'data' => ['currentScript' => $currentScript, 'basePath' => $basePath, 'imagePath' => $imagePath, 'isLoggedIn' => $isLoggedIn], 'timestamp' => round(microtime(true) * 1000), 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND);
// #endregion agent log
?>

<!-- ================= HEADER ================= -->
<header class="main-header">

    <!-- LOGO -->
    <div class="logo-area">
        <a href="<?php echo $basePath . 'index.php'; ?>" style="text-decoration: none; display: block;">
            <img src="<?php echo $imagePath; ?>16.png"
                 alt="Lumière Beauty Salon Logo"
                 class="header-logo">
        </a>
    </div>

    <!-- NAVIGATION -->
    <nav class="main-nav">
        <a href="<?php echo $basePath . 'index.php'; ?>" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'home.php') ? 'active' : ''; ?>">Home</a>
        <a href="<?php echo $basePath . 'services.php'; ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">Services</a>

        <!-- Meet the Team dropdown -->
        <div class="nav-dropdown">
           <a href="<?php echo $basePath . 'team.php'; ?>" class="nav-link dropdown-toggle <?php echo basename($_SERVER['PHP_SELF']) == 'team.php' ? 'active' : ''; ?>">Meet The Team</a>
           <div class="dropdown-menu">
        <a href="<?php echo $basePath . 'team.php?cat=hair'; ?>">Hair Stylists</a>
        <a href="<?php echo $basePath . 'team.php?cat=beauty'; ?>">Beauticians</a>
        <a href="<?php echo $basePath . 'team.php?cat=massage'; ?>">Massage Therapists</a>
        <a href="<?php echo $basePath . 'team.php?cat=nail'; ?>">Nail Technicians</a>
            </div>
        </div>

        <a href="<?php echo $basePath . 'contact.php'; ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">Contact Us</a>
    </nav>

    <!-- BOOK NOW (forces login if guest) -->
    <?php if ($isLoggedIn): ?>
<button class="book-btn"
        onclick="window.location.href='<?php echo $rootPrefix; ?>booking.php'">
    Book Now
</button>
<?php else: ?>
<button class="book-btn"
        onclick="window.location.href='<?php echo $rootPrefix; ?>login.php?redirect=' + encodeURIComponent('booking.php')">
    Book Now
</button>
<?php endif; ?>

    <!-- PROFILE ICON - Toggle profile panel -->
    <button class="profile-btn" id="profileToggle" aria-label="Profile menu">
        <img src="<?php echo $imagePath; ?>50.png" class="header-profile-img" alt="Profile">
    </button>
</header>

<!-- ================= PROFILE PANEL ================= -->
<aside class="profile-panel" id="profilePanel">
    <button class="panel-close" id="panelClose">✕</button>

<?php if ($isLoggedIn): ?>
<div class="panel-content">
    <div class="panel-header">
        <a href="<?php echo $basePath; ?>dashboard.php" style="text-decoration: none; display: block;">
            <div id="dynamicAvatar" class="avatar-circle" style="cursor: pointer;">
                <?= htmlspecialchars($initials) ?>
            </div>
        </a>
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
        <li><a href="<?php echo $basePath; ?>dashboard.php?section=overview">Overview</a></li>
        <li><a href="<?php echo $basePath; ?>dashboard.php?section=bookings">My Bookings</a></li>
        <li><a href="<?php echo $basePath; ?>dashboard.php?section=profile">My Profile</a></li>
        <li><a href="<?php echo $basePath; ?>dashboard.php?section=favourites">Favourites Staff</a></li>
        <li><a href="<?php echo $basePath; ?>dashboard.php?section=help">Help & Support</a></li>
    </ul>

    <div class="panel-divider"></div>

    <a href="<?php echo $rootPrefix; ?>logout.php" class="panel-logout">
        <img src="<?php echo $imagePath; ?>51.png" class="logout-img" alt="Logout">
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
                <a href="<?php echo $rootPrefix; ?>login.php?mode=login&redirect=<?= $currentUrlEncoded ?>">
                Login
                </a>
            </li>

            <li>
                <!-- OPEN REGISTER TAB -->
                <a href="<?php echo $rootPrefix; ?>register.php?mode=register&redirect=<?= $currentUrlEncoded ?>">
                Register
                </a>
            </li>
            </ul>
        </div>
    <?php endif; ?>
</aside>

<script>
// Profile Panel Hover Functionality
// This script enables hover to show/hide the profile panel on all pages that include header.php
document.addEventListener("DOMContentLoaded", function() {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'includes/header.php:166',message:'Profile panel script DOMContentLoaded fired',data:{currentUrl:window.location.href},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion agent log
    
    const toggle = document.getElementById("profileToggle");
    const panel = document.getElementById("profilePanel");
    const closeBtn = document.getElementById("panelClose");

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'includes/header.php:171',message:'Profile panel elements lookup',data:{toggleExists:!!toggle,panelExists:!!panel,closeBtnExists:!!closeBtn},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
    // #endregion agent log

    if (toggle && panel) {
        // #region agent log
        const initialStyle = window.getComputedStyle(panel);
        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'includes/header.php:176',message:'Profile panel initial CSS state',data:{opacity:initialStyle.opacity,visibility:initialStyle.visibility,zIndex:initialStyle.zIndex,pointerEvents:initialStyle.pointerEvents,display:initialStyle.display,position:initialStyle.position},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'C'})}).catch(()=>{});
        // #endregion agent log
        
        let hoverTimeout;
        let isHovering = false;

        // Show panel on hover over profile button
        toggle.addEventListener("mouseenter", () => {
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'includes/header.php:182',message:'Profile toggle mouseenter event fired',data:{hasOpenClass:panel.classList.contains('open')},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'D'})}).catch(()=>{});
            // #endregion agent log
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => {
                panel.classList.add("open");
                isHovering = true;
                // #region agent log
                const afterStyle = window.getComputedStyle(panel);
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'includes/header.php:186',message:'Profile panel open class added',data:{hasOpenClass:panel.classList.contains('open'),opacity:afterStyle.opacity,visibility:afterStyle.visibility,zIndex:afterStyle.zIndex,pointerEvents:afterStyle.pointerEvents},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'E'})}).catch(()=>{});
                // #endregion agent log
            }, 150); // Small delay to prevent accidental triggers
        });

        // Keep panel open when hovering over it
        panel.addEventListener("mouseenter", () => {
            clearTimeout(hoverTimeout);
            panel.classList.add("open");
            isHovering = true;
        });

        // Hide panel when mouse leaves profile button
        toggle.addEventListener("mouseleave", () => {
            hoverTimeout = setTimeout(() => {
                if (!isHovering) {
                    panel.classList.remove("open");
                }
            }, 200);
        });

        // Hide panel when mouse leaves panel
        panel.addEventListener("mouseleave", () => {
            hoverTimeout = setTimeout(() => {
                panel.classList.remove("open");
                isHovering = false;
            }, 200);
        });

        // Also support click for mobile/touch devices
        toggle.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            panel.classList.toggle("open");
        });

        // Close button functionality
        if (closeBtn) {
            closeBtn.addEventListener("click", () => {
                panel.classList.remove("open");
                isHovering = false;
            });
        }

        // Click outside to close (but allow clicks on links to navigate)
        document.addEventListener("click", (e) => {
            // If clicking on a dashboard link, don't close the panel - let it navigate
            if (e.target.closest('a[href*="dashboard.php"]')) {
                return; // Allow navigation
            }
            
            if (panel.classList.contains("open") && 
                !panel.contains(e.target) && 
                !toggle.contains(e.target)) {
                panel.classList.remove("open");
                isHovering = false;
            }
        });
        
        // Log dashboard link clicks for debugging
        const dashboardLinks = panel.querySelectorAll('a[href*="dashboard.php"]');
        dashboardLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'includes/header.php:dashboard-link-click',message:'Dashboard link clicked',data:{href:this.href,basePath:'<?php echo $basePath; ?>',currentUrl:window.location.href},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'F'})}).catch(()=>{});
                // #endregion agent log
            });
        });
    }
});
</script>
