<?php
// Ensure authentication is checked before including header
if (!function_exists('isAdminAuthenticated')) {
    require_once __DIR__ . '/auth_check.php';
}

if (!isAdminAuthenticated()) {
    header('Location: ../admin/login.html');
    exit;
}

$admin = getCurrentAdmin();
$csrf_token = getCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Lumière Admin</title>
    <link rel="stylesheet" href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/css/admin-style.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <h1>Lumière Admin</h1>
            <div class="mobile-user">
                <span><?php echo htmlspecialchars(substr($admin['first_name'], 0, 1)); ?></span>
            </div>
        </div>

        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Lumière</h2>
                <p>Admin Portal</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/index.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Dashboard</span>
                </a>
                
                <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/services/list.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/services/') !== false) ? 'active' : ''; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7h-9M14 17H5M17 12H3"></path>
                        <circle cx="17" cy="7" r="3"></circle>
                        <circle cx="7" cy="17" r="3"></circle>
                        <circle cx="20" cy="12" r="3"></circle>
                    </svg>
                    <span>Services</span>
                </a>
                
                <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/staff/list.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? 'active' : ''; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Staff</span>
                </a>
                
                <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/calendar/master.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/calendar/') !== false) ? 'active' : ''; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Calendar</span>
                </a>
                
                <div class="nav-section">Analytics</div>
                
                <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/analytics/business.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'business.php') ? 'active' : ''; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="20" x2="12" y2="10"></line>
                        <line x1="18" y1="20" x2="18" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="16"></line>
                    </svg>
                    <span>Business</span>
                </a>
                
                <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/analytics/sustainability.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'sustainability.php') ? 'active' : ''; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                    <span>Sustainability</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo htmlspecialchars(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <button class="logout-btn" onclick="handleLogout()" title="Logout">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="content-header">
                <h1><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h1>
                <div class="header-actions">
                    <div class="user-info-desktop">
                        <span class="user-name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></span>
                        <span class="user-role">Admin</span>
                    </div>
                </div>
            </div>
            
            <div class="content-body">
