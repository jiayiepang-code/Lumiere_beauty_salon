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

<style>
.page-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
}

.search-filter-group {
    display: flex;
    gap: 12px;
    flex: 1;
    min-width: 300px;
}

.search-box {
    flex: 1;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 10px 40px 10px 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}

.search-box svg {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.filter-select {
    padding: 10px 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.staff-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #f8f9fa;
}

th {
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #333;
    font-size: 14px;
    border-bottom: 2px solid #e9ecef;
}

td {
    padding: 16px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}

tr:hover {
    background: #f8f9fa;
}

.staff-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.staff-image {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.staff-image-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.staff-info {
    flex: 1;
}

.staff-name {
    font-weight: 500;
    color: #333;
}

.staff-email {
    color: #666;
    font-size: 13px;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    background: #e7f3ff;
    color: #0066cc;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-icon {
    padding: 6px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.2s;
}

.btn-icon:hover {
    background: #f0f0f0;
}

.btn-icon svg {
    display: block;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #4CAF50;
}

input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state svg {
    margin: 0 auto 16px;
    opacity: 0.5;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #999;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .page-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-filter-group {
        flex-direction: column;
        min-width: 100%;
    }
    
    .table-container {
        overflow-x: scroll;
    }
    
    table {
        min-width: 900px;
    }
}
</style>

<div class="page-actions">
    <div class="search-filter-group">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search staff...">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
        </div>
        <select id="roleFilter" class="filter-select">
            <option value="">All Roles</option>
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
        </select>
        <label style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
            <input type="checkbox" id="activeOnlyFilter">
            <span>Active Only</span>
        </label>
    </div>
    <button class="btn-primary" onclick="openCreateModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Add Staff
    </button>
</div>

<div class="staff-table">
    <div class="table-container">
        <div id="loadingState" class="loading">
            Loading staff...
        </div>
        <div id="emptyState" class="empty-state" style="display: none;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p>No staff members found</p>
        </div>
        <table id="staffTable" style="display: none;">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="staffTableBody">
            </tbody>
        </table>
    </div>
</div>

<!-- Staff Form Modal (Create/Edit) -->
<div id="staffModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add Staff</h3>
            <button class="modal-close" onclick="closeStaffModal()">&times;</button>
        </div>
        <form id="staffForm">
            <input type="hidden" id="isEdit" name="is_edit" value="0">
            
            <div class="form-group">
                <label for="staffEmail">Email *</label>
                <input type="email" id="staffEmail" name="staff_email" required maxlength="100">
                <span class="error-message" id="error-staff_email"></span>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone *</label>
                <input type="tel" id="phone" name="phone" required placeholder="01X-XXXXXXX or 60XXXXXXXXX">
                <span class="error-message" id="error-phone"></span>
            </div>
            
            <div class="form-group" id="passwordGroup">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" minlength="8">
                <small style="color: #666;">Min 8 chars, 1 uppercase, 1 number, 1 special character</small>
                <span class="error-message" id="error-password"></span>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name *</label>
                    <input type="text" id="firstName" name="first_name" required maxlength="50">
                    <span class="error-message" id="error-first_name"></span>
                </div>
                
                <div class="form-group">
                    <label for="lastName">Last Name *</label>
                    <input type="text" id="lastName" name="last_name" required maxlength="50">
                    <span class="error-message" id="error-last_name"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="role">Role *</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
                <span class="error-message" id="error-role"></span>
            </div>
            
            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" rows="4" maxlength="500"></textarea>
                <span class="error-message" id="error-bio"></span>
            </div>
            
            <div class="form-group">
                <label for="staffImage">Image URL</label>
                <input type="text" id="staffImage" name="staff_image" maxlength="255">
                <small style="color: #666;">Enter image filename or URL</small>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeStaffModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="submitBtn">Save Staff</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div style="padding: 20px;">
            <p id="deleteMessage">Are you sure you want to delete this staff member?</p>
            <p id="deleteWarning" style="color: #d32f2f; margin-top: 12px; display: none;">
                <strong>Warning:</strong> This staff member has future bookings.
            </p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()">Delete</button>
        </div>
    </div>
</div>

<script src="list.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>
