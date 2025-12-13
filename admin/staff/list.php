<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Set page title
$page_title = 'Staff Management';
$base_path = '../..';

// Include header
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../admin/css/admin-style.css">
    <style>
        :root {
            --primary-color: #D4AF37;
            --secondary-color: #333333;
            --dark-bg: #1a1a2e;
            --light-gray: #F8F9FA;
            --text-dark: #333333;
            --text-gray: #6C757D;
            --border-light: #E9ECEF;
        }

        body {
            background-color: #F8F9FA;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 0;
        }

        .staff-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .staff-header .subtitle {
            color: var(--text-gray);
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }

        .btn-add {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(212, 175, 55, 0.3);
            color: white;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-light);
        }

        .search-input {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(178, 58, 72, 0.1);
            outline: none;
        }

        .filter-select {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
            outline: none;
        }

        .table-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background-color: #F8F9FA;
            border-bottom: 2px solid var(--border-light);
        }

        .table thead th {
            color: var(--text-gray);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
            padding: 1.25rem;
            border: none;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--border-light);
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover {
            background-color: #FDF6E3;
        }

        .table tbody td {
            padding: 1.25rem;
            vertical-align: middle;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .staff-cell {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .staff-avatar {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }

        .staff-info {
            flex: 1;
        }

        .staff-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .staff-bio {
            font-size: 0.85rem;
            color: var(--text-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .contact-item svg {
            width: 16px;
            height: 16px;
            color: var(--border-light);
        }

        .role-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .role-stylist {
            background-color: #FDF6E3;
            color: #D4AF37;
        }

        .role-receptionist {
            background-color: #E6F4F1;
            color: #2A9D8F;
        }

        .role-manager {
            background-color: #EBF5FB;
            color: #264653;
        }

        .role-admin {
            background-color: #F5E6E0;
            color: #E76F51;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.active {
            background-color: #E6F4F1;
            color: #2A9D8F;
        }

        .status-badge.inactive {
            background-color: #FFF0EB;
            color: #6C757D;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-badge.active .status-dot {
            background-color: #2A9D8F;
        }

        .status-badge.inactive .status-dot {
            background-color: #9CA3AF;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-gray);
        }

        .btn-icon:hover {
            background-color: var(--light-gray);
        }

        .btn-icon svg {
            width: 16px;
            height: 16px;
        }

        .btn-view {
            color: var(--primary-color);
        }

        .btn-view:hover {
            background-color: rgba(212, 175, 55, 0.1);
        }

        .btn-edit {
            color: #264653;
        }

        .btn-edit:hover {
            background-color: rgba(38, 70, 83, 0.1);
        }

        .btn-delete {
            color: #E76F51;
        }

        .btn-delete:hover {
            background-color: rgba(231, 111, 81, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-gray);
        }

        .empty-state svg {
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-gray);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .form-control, .form-select {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .staff-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .staff-header h1 {
                font-size: 1.75rem;
            }

            .staff-bio {
                display: none;
            }

            .table {
                font-size: 0.85rem;
            }

            .table thead th,
            .table tbody td {
                padding: 0.75rem 0.5rem;
            }

            .action-buttons {
                gap: 0.25rem;
            }

            .btn-icon {
                width: 32px;
                height: 32px;
            }
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 1rem 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        .toast.show {
            display: block;
        }

        .toast.success {
            border-left: 4px solid #b23a48;
            color: #66101f;
        }

        .toast.error {
            border-left: 4px solid #a02d3a;
            color: #4a1a26;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="staff-header mb-4">
        <div>
            <h1>Staff</h1>
            <p class="subtitle">Manage your team members and roles</p>
        </div>
        <button class="btn btn-add" onclick="openCreateModal()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 8px;">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Staff
        </button>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="position-relative">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-gray); pointer-events: none;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" class="form-control search-input ps-5" id="searchInput" placeholder="Search staff by name or email...">
                </div>
            </div>
            <div class="col-md-6">
                <select class="form-select filter-select" id="roleFilter">
                    <option value="">All Roles</option>
                    <option value="stylist">Stylist</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="table-wrapper">
        <div id="loadingState" class="loading">
            <div class="spinner-border" role="status" style="color: var(--primary-color);">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading staff...</p>
        </div>

        <div id="emptyState" class="empty-state" style="display: none;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p>No staff members found</p>
        </div>

        <div class="table-responsive" id="tableContainer" style="display: none;">
            <table class="table" id="staffTable">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Staff Form Modal (Create/Edit) -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="closeStaffModal()"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" id="isEdit" name="is_edit" value="0">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="staffEmail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="staffEmail" name="staff_email" required maxlength="100">
                            <div class="text-danger small mt-1" id="error-staff_email"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" required placeholder="e.g., 0123456789">
                            <div class="text-danger small mt-1" id="error-phone"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required maxlength="50" placeholder="e.g., John">
                            <div class="text-danger small mt-1" id="error-first_name"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required maxlength="50" placeholder="e.g., Doe">
                            <div class="text-danger small mt-1" id="error-last_name"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span id="passwordRequired" class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" placeholder="Enter strong password">
                            <small class="text-muted d-block mt-1" id="passwordHint">Min 8 chars, 1 uppercase, 1 number, 1 special character</small>
                            <div class="text-danger small mt-1" id="error-password"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Brief introduction about the staff member..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="staffImage" class="form-label">Profile Image</label>
                        <input type="file" class="form-control" id="staffImage" name="staff_image" accept="image/*">
                        <div id="imagePreviewContainer" style="margin-top: 10px; display: none;">
                            <img id="imagePreview" src="" alt="Preview" style="max-width: 100px; max-height: 100px; border-radius: 50%; object-fit: cover;">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeStaffModal()">Cancel</button>
                        <button type="submit" class="btn btn-add">Save Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center pt-4">
                <div style="width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                    </svg>
                </div>
                <h5 class="mb-2">Delete Staff Member?</h5>
                <p class="text-muted mb-4">Are you sure you want to delete <strong id="deleteStaffName"></strong>? This action cannot be undone.</p>
                
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeDeleteModal()">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Staff</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// CSRF Token from PHP
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token ?? ''); ?>';
</script>
<script src="list.js"></script>

</body>
</html>

<?php
// Include footer
include '../includes/footer.php';
?>

    <div class="staff-table-card">
        <div id="loadingState" class="loading">Loading staff...</div>

        <div id="emptyState" class="empty-state" style="display: none;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p>No staff members found</p>
        </div>

        <div class="table-responsive">
            <table id="staffTable" class="staff-table" style="display: none;">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

