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
            <div class="sidebar-brand" style="font-size: 20px;">
                <span>Lumière</span> Admin
            </div>
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
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
                            <span class="user-role">Manager</span>
                        </div>
                        <div class="user-avatar-header">
                            <?php
                            $base_path = isset($base_path) ? $base_path : '..';
                            $imagePath = '';
                            $initials = isset($admin['first_name']) ? strtoupper(substr($admin['first_name'], 0, 1)) . strtoupper(substr($admin['last_name'], 0, 1)) : 'MS';
                            
                            // Try to resolve staff image path
                            if (!empty($admin['staff_image'])) {
                                $originalPath = $admin['staff_image'];
                                $filename = basename($originalPath);
                                $extensions = ['', '.png', '.jpg', '.jpeg', '.gif', '.webp'];
                                $baseDir = __DIR__ . '/../../images/';
                                $foundFile = false;
                                
                                // Try root images directory first
                                foreach ($extensions as $ext) {
                                    $testFilename = $filename . $ext;
                                    $testPathRoot = $baseDir . $testFilename;
                                    
                                    if (file_exists($testPathRoot)) {
                                        $imagePath = $base_path . '/images/' . $testFilename;
                                        $foundFile = true;
                                        break;
                                    }
                                }
                                
                                // If not found in root, try staff directory
                                if (!$foundFile) {
                                    foreach ($extensions as $ext) {
                                        $testFilename = $filename . $ext;
                                        $testPathStaff = $baseDir . 'staff/' . $testFilename;
                                        
                                        if (file_exists($testPathStaff)) {
                                            $imagePath = $base_path . '/images/staff/' . $testFilename;
                                            $foundFile = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            if (!empty($imagePath)): ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($admin['first_name']); ?>" 
                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo htmlspecialchars($initials); ?>';"
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>
            <div class="content-body">
