<?php
require_once '../../config/config.php';
require_once '../includes/auth_check.php';
requireAdminAuth();

$pageTitle = "Settings";
require_once '../includes/header.php';
?>

<div class="admin-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Settings</h1>
                <p class="subtitle">Manage branding, timezone, and access control</p>
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

        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h2>Role-Based Access Control (RBAC)</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Description</th>
                                <th>Permissions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Administrator</td>
                                <td>Full access to admin portal.</td>
                                <td>Manage services, staff, bookings, analytics, settings</td>
                            </tr>
                            <tr>
                                <td>Manager</td>
                                <td>Operational oversight with limited settings.</td>
                                <td>Manage services, staff, bookings, view analytics</td>
                            </tr>
                            <tr>
                                <td>Analyst</td>
                                <td>Read-only analytics access.</td>
                                <td>View analytics and export reports</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline" id="configure-rbac">Configure</button>
            </div>
        </div>
    </main>
</div>

<script>
// Basic client-side handlers (stubbed; backend APIs can be added)
document.getElementById('save-branding')?.addEventListener('click', () => {
    alert('Branding saved (stub)');
});

document.getElementById('save-localization')?.addEventListener('click', () => {
    alert('Localization saved (stub)');
});

document.getElementById('configure-rbac')?.addEventListener('click', () => {
    alert('RBAC configuration (stub)');
});
</script>

<?php require_once '../includes/footer.php'; ?>
