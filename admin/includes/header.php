<?php
// Ensure authentication is checked before including header
if (!function_exists('requireAdminAuth')) {
    require_once __DIR__ . '/auth_check.php';
}

requireAdminAuth();

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
    <link rel="stylesheet" href="<?php echo isset($base_path) ? $base_path : '..'; ?>/admin/css/responsive-mobile.css">
    <!-- SweetAlert2 for beautiful alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="admin-layout">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <!-- Hamburger Menu - Left -->
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <!-- User Info - Center -->
            <div class="mobile-user-center">
                <span class="user-name-mobile"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></span>
                <span class="user-role-mobile"><?php echo htmlspecialchars(ucfirst($admin['role'] ?? 'admin')); ?></span>
            </div>
            
            <!-- User Avatar - Right -->
            <div class="user-avatar-mobile">
                <?php
                $base_path = isset($base_path) ? $base_path : '..';
                $initials = isset($admin['first_name']) ? strtoupper(substr($admin['first_name'], 0, 1)) . strtoupper(substr($admin['last_name'], 0, 1)) : 'US';
                $imagePath = resolveStaffImagePath($admin['staff_image'] ?? null, $base_path);
                ?>
                <?php if (!empty($imagePath)): ?>
                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($admin['first_name']); ?>" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width: 100%; height: 100%; border-radius: 50%; background: #f5e9e2; display: flex; align-items: center; justify-content: center; color: #8b5e3c; font-weight: 600; font-size: 14px;\'><?php echo htmlspecialchars($initials); ?></div>';" />
                <?php else: ?>
                    <div style="width: 100%; height: 100%; border-radius: 50%; background: #f5e9e2; display: flex; align-items: center; justify-content: center; color: #8b5e3c; font-weight: 600; font-size: 14px;">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <!-- Optional: Breadcrumbs could go here -->
                </div>
                <div class="header-actions">
                    <div class="user-profile-header">
                        <div class="user-info-text">
                            <span class="user-name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars(ucfirst($admin['role'] ?? 'admin')); ?></span>
                        </div>
                        <div class="user-avatar-header">
                            <?php
                            $base_path = isset($base_path) ? $base_path : '..';
                            $initials = isset($admin['first_name']) ? strtoupper(substr($admin['first_name'], 0, 1)) . strtoupper(substr($admin['last_name'], 0, 1)) : 'MS';
                            $imagePath = resolveStaffImagePath($admin['staff_image'] ?? null, $base_path);
                            ?>
                            <?php if (!empty($imagePath)): ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($admin['first_name']); ?>" 
                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width: 100%; height: 100%; border-radius: 50%; background: #f5e9e2; display: flex; align-items: center; justify-content: center; color: #8b5e3c; font-weight: 600; font-size: 14px;\'><?php echo htmlspecialchars($initials); ?></div>';"
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; border-radius: 50%; background: #f5e9e2; display: flex; align-items: center; justify-content: center; color: #8b5e3c; font-weight: 600; font-size: 14px;">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>
            <div class="content-body">