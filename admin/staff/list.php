<?php
// Include authentication check
require_once '../includes/auth_check.php';
requireAdminAuth();

// Set page title
$page_title = 'Staff Management';
$base_path = '../..';

// Include database connection
require_once '../../config/db_connect.php';

// Fetch all staff records using prepared statement
$conn = getDBConnection();
$staff = [];
$error = null;

try {
    // Use prepared statement even though no user input yet (best practice)
    $sql = "SELECT staff_email, phone, first_name, last_name, bio, role, 
                   staff_image, is_active, created_at
            FROM staff
            ORDER BY first_name, last_name";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Error fetching staff: " . $error);
} finally {
    $conn->close();
}

// Get CSRF token
$csrf_token = getCSRFToken();

// Include header
include '../includes/header.php';
?>

<style>
    :root {
        --primary-color: #c29076;
        --secondary-color: #333333;
        --text-dark: #5c4e4b;
        --text-gray: #6C757D;
        --border-light: #E9ECEF;
        --light-gray: #F8F9FA;
    }

    .staff-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2.5rem;
        padding: 0;
    }

    .staff-header h1 {
        font-size: 3rem;
        font-weight: 700;
        color: var(--text-dark);
        margin: 0;
    }

    .staff-header .subtitle {
        color: var(--text-gray);
        font-size: 1.1rem;
        margin-top: 0.75rem;
    }

    .btn-add {
        background: linear-gradient(135deg, var(--primary-color), #9e7364);
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 1.05rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(194, 144, 118, 0.2);
        cursor: pointer;
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(194, 144, 118, 0.3);
        color: white;
    }

    .filters-section {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-light);
    }

    .search-input, .filter-select {
        border: 1px solid var(--border-light);
        border-radius: 10px;
        padding: 1rem 1.25rem;
        font-size: 1rem;
        transition: all 0.2s ease;
    }

    .search-input:focus, .filter-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(194, 144, 118, 0.1);
        outline: none;
    }

    .table-wrapper {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .table thead {
        background-color: #F8F9FA;
        border-bottom: 2px solid var(--border-light);
    }

    .table thead th {
        color: var(--text-gray);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.8px;
        padding: 1.5rem;
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
        padding: 1.5rem;
        vertical-align: middle;
        color: var(--text-dark);
        font-size: 1rem;
    }

    .staff-cell {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .staff-avatar {
        width: 64px;
        height: 64px;
        min-width: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.25rem;
        overflow: hidden;
        flex-shrink: 0;
    }

    .staff-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .staff-info {
        flex: 1;
        min-width: 0;
    }

    .staff-name {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .staff-bio {
        font-size: 0.95rem;
        color: var(--text-gray);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 400px;
    }

    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--text-gray);
        font-size: 0.95rem;
    }

    .contact-item svg {
        width: 18px;
        height: 18px;
        color: var(--border-light);
        flex-shrink: 0;
    }

    .role-badge {
        display: inline-block;
        padding: 0.5rem 1.25rem;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .role-staff {
        background-color: #FDF6E3;
        color: var(--primary-color);
    }

    .role-admin {
        background-color: #F5E6E0;
        color: #E76F51;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 1.25rem;
        border-radius: 25px;
        font-size: 0.9rem;
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
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    .status-badge.active .status-dot {
        background-color: #2A9D8F;
    }

    .status-badge.inactive .status-dot {
        background-color: #9CA3AF;
    }

    .action-buttons {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }

    .btn-icon {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
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
        width: 20px;
        height: 20px;
    }

    .btn-view {
        color: var(--primary-color);
    }

    .btn-view:hover {
        background-color: rgba(194, 144, 118, 0.1);
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
        padding: 4rem 1rem;
        color: var(--text-gray);
    }

    .empty-state svg {
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }

    .error-state {
        text-align: center;
        padding: 2rem;
        color: #E76F51;
        background: #FFF0EB;
        border-radius: 10px;
        margin: 2rem 0;
    }

    @media (max-width: 768px) {
        .staff-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .staff-header h1 {
            font-size: 2rem;
        }

        .staff-bio {
            display: none;
        }

        .table {
            font-size: 0.9rem;
        }

        .table thead th,
        .table tbody td {
            padding: 1rem 0.75rem;
        }

        .action-buttons {
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
        }
    }
</style>

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
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-gray); pointer-events: none; z-index: 1;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" class="form-control search-input ps-5" id="searchInput" placeholder="Search staff by name or email...">
                </div>
            </div>
            <div class="col-md-6">
                <select class="form-select filter-select" id="roleFilter">
                    <option value="">All Roles</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <?php if ($error): ?>
    <div class="error-state">
        <strong>Error loading staff:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Table Section -->
    <div class="table-wrapper">
        <?php if (empty($staff) && !$error): ?>
        <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p style="font-size: 1.1rem;">No staff members found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
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
                <tbody id="staffTableBody">
                    <?php foreach ($staff as $member): 
                        $initials = strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1));
                        $joinDate = date('Y-m-d', strtotime($member['created_at']));
                        $roleClass = $member['role'] === 'admin' ? 'role-admin' : 'role-staff';
                        $imagePath = !empty($member['staff_image']) ? htmlspecialchars($member['staff_image']) : '';
                    ?>
                    <tr data-email="<?php echo htmlspecialchars($member['staff_email']); ?>"
                        data-role="<?php echo htmlspecialchars($member['role']); ?>"
                        data-active="<?php echo $member['is_active'] ? '1' : '0'; ?>"
                        data-name="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>">
                        <td>
                            <div class="staff-cell">
                                <div class="staff-avatar">
                                    <?php if (!empty($imagePath)): ?>
                                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($member['first_name']); ?>" onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo htmlspecialchars($initials); ?>';">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($initials); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="staff-info">
                                    <div class="staff-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                    <div class="staff-bio"><?php echo htmlspecialchars($member['bio'] ?? ''); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="contact-info">
                                <div class="contact-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <?php echo htmlspecialchars($member['staff_email']); ?>
                                </div>
                                <div class="contact-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <?php echo htmlspecialchars($member['phone']); ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge <?php echo $roleClass; ?>"><?php echo htmlspecialchars(ucfirst($member['role'])); ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $member['is_active'] ? 'active' : 'inactive'; ?>">
                                <span class="status-dot"></span>
                                <?php echo $member['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($joinDate); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon btn-view" onclick="viewStaff('<?php echo htmlspecialchars($member['staff_email'], ENT_QUOTES); ?>')" title="View" type="button">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <button class="btn-icon btn-edit" onclick="openEditModal('<?php echo htmlspecialchars($member['staff_email'], ENT_QUOTES); ?>')" title="Edit" type="button">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <button class="btn-icon btn-delete" onclick="openDeleteModal('<?php echo htmlspecialchars($member['staff_email'], ENT_QUOTES); ?>')" title="Delete" type="button">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Staff Form Modal (Create/Edit) -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), #9e7364); color: white; border: none;">
                <h5 class="modal-title" id="modalTitle">Add Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="closeStaffModal()"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" id="isEdit" name="is_edit" value="0">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="staffEmail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="staffEmail" name="staff_email" required maxlength="100">
                            <div class="text-danger small mt-1" id="error-staff_email" style="display: none;"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" required placeholder="e.g., 0123456789">
                            <div class="text-danger small mt-1" id="error-phone" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required maxlength="50" placeholder="e.g., John">
                            <div class="text-danger small mt-1" id="error-first_name" style="display: none;"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required maxlength="50" placeholder="e.g., Doe">
                            <div class="text-danger small mt-1" id="error-last_name" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span id="passwordRequired" class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" placeholder="Enter strong password">
                            <small class="text-muted d-block mt-1" id="passwordHint">Min 8 chars, 1 uppercase, 1 number, 1 special character</small>
                            <div class="text-danger small mt-1" id="error-password" style="display: none;"></div>
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
                        <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Brief introduction about the staff member..." maxlength="500"></textarea>
                        <div class="text-danger small mt-1" id="error-bio" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="staffImage" class="form-label">Profile Image</label>
                        <input type="file" class="form-control" id="staffImage" name="staff_image" accept="image/*">
                        <small class="text-muted d-block mt-1">Max 2MB. Allowed: JPEG, PNG, GIF, WebP</small>
                        <div id="imagePreviewContainer" style="margin-top: 10px; display: none;">
                            <img id="imagePreview" src="" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-light);">
                        </div>
                        <div class="text-danger small mt-1" id="error-staff_image" style="display: none;"></div>
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// CSRF Token from PHP
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token); ?>';
</script>
<script src="list.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>
