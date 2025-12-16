<?php
// Staff Header Component
// This file provides the header section for all staff pages
// It includes the logo, navigation, and user profile menu
?>

<header class="header">
    <div class="container">
        <div class="header-content">
            <div class="brand">
                <img src="../images/16.png" alt="Lumière logo" class="logo-img">
                <div class="logo">Lumière</div>
            </div>
            <nav class="nav">
                <a href="dashboard.html">Home</a>
                <a href="schedule.html">Schedule</a>
                <a href="performance.php">Performance</a>
                <a href="apply-leave.html">Leave</a>
            </nav>
            <div class="profile-section">
                <div class="notification-wrapper" onclick="toggleNotifications(event)">
                    <i class="fas fa-bell notification-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display:none;">0</span>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <span id="notificationCountText">0</span>
                        </div>
                        <ul class="notification-list" id="notificationList"></ul>
                        <div class="notification-empty" id="notificationEmpty">No new notifications</div>
                    </div>
                </div>
                <div class="profile-icon" onclick="toggleProfileDropdown()">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <div class="avatar" id="dropdownAvatar">
                            <img id="dropdownAvatarImg" alt="Staff profile">
                            <span class="avatar-initials" id="dropdownAvatarPlaceholder">ST</span>
                        </div>
                        <div class="dropdown-names">
                            <span class="profile-name" id="dropdownName">Staff</span>
                            <span class="profile-role">Staff</span>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="window.location.href='profile.html'">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </div>
                    <div class="dropdown-item" onclick="window.location.href='apply-leave.html'">
                        <i class="fas fa-calendar-minus"></i>
                        <span>Apply Leave</span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item logout-item" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
