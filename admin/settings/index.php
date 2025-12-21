<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Set page title
$page_title = 'Settings';
$base_path = '../..';

// Include header
include '../includes/header.php';
?>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="settings-page">
    <div class="settings-header">
        <div>
            <h1 class="settings-title">Settings</h1>
            <p class="settings-subtitle">Manage branding and localization settings</p>
        </div>
    </div>

    <div class="grid-2-col">
            <div class="card">
                <div class="card-header">
                    <h2>Branding</h2>
                </div>
                <div class="card-body">
                    <form id="branding-form">
                        <div class="form-group">
                            <label for="brand-name">Brand Name</label>
                            <input type="text" id="brand-name" class="form-control" placeholder="Lumière Beauty Salon">
                        </div>
                        <div class="form-group">
                            <label for="primary-color">Primary Color</label>
                            <input type="color" id="primary-color" class="form-control" value="#8B4789">
                        </div>
                        <div class="form-group">
                            <label for="accent-color">Accent Color</label>
                            <input type="color" id="accent-color" class="form-control" value="#D8C3A5">
                        </div>
                        <button type="button" class="btn btn-primary" id="save-branding">Save</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Localization</h2>
                </div>
                <div class="card-body">
                    <form id="localization-form">
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" class="form-control">
                                <option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur (GMT+8)</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="operating-hours">Operating Hours</label>
                            <input type="text" id="operating-hours" class="form-control" placeholder="10:00 - 22:00">
                        </div>
                        <button type="button" class="btn btn-primary" id="save-localization">Save</button>
                    </form>
                </div>
            </div>
    </div>
</div>

<script>
// Basic client-side handlers (stubbed; backend APIs can be added)
document.getElementById('save-branding')?.addEventListener('click', () => {
    alert('Branding saved (stub)');
});

document.getElementById('save-localization')?.addEventListener('click', () => {
    alert('Localization saved (stub)');
});
</script>

<?php require_once '../includes/footer.php'; ?>
