<?php
// Sidebar Navigation
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="logo-container-oval">
                <img src="<?php echo isset($base_path) ? $base_path : '..'; ?>/images/16.png" alt="Lumière Beauty Salon" class="brand-logo">
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        
        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/index.php" class="nav-item <?php echo ($current_page == 'index.php' && $current_dir == 'admin') ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-section">Management</div>
        
        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/staff/list.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Staff Management</span>
        </a>

        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/leave_requests/index.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/leave_requests/') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <path d="M8 2v4"></path>
                <path d="M16 2v4"></path>
                <polyline points="9 15 11.5 17.5 15 14"></polyline>
            </svg>
            <span>Leave Requests</span>
        </a>

        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/services/list.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/services/') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                <path d="M2 17l10 5 10-5"></path>
                <path d="M2 12l10 5 10-5"></path>
            </svg>
            <span>Service Management</span>
        </a>
        
        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/calendar/master.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/calendar/') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Master Calendar</span>
        </a>

        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/customers/list.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/customers/') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Customers</span>
        </a>
        
        <div class="nav-section">Insights</div>
        
        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/analytics/business.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/analytics/business.php') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span>Business Analytics</span>
        </a>
        
        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/analytics/sustainability.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/analytics/sustainability.php') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
            <span>Sustainability</span>
        </a>

        <div class="nav-section">Configuration</div>
        <a href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/settings/index.php" class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], '/settings/') !== false) ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c0 .69.28 1.32.73 1.77.45.45 1.08.73 1.77.73H21a2 2 0 0 1 0 4h-.09c-.69 0-1.32.28-1.77.73-.45.45-.73 1.08-.73 1.77z"></path>
            </svg>
            <span>Settings</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="#" class="logout-btn" onclick="handleLogout(); return false;">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</aside>