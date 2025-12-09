<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Set page title
$page_title = 'Services';
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

.services-table {
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

.service-image {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
}

.service-name {
    font-weight: 500;
    color: #333;
}

.service-category {
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
        min-width: 800px;
    }
}
</style>

<div class="page-actions">
    <div class="search-filter-group">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search services...">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
        </div>
        <select id="categoryFilter" class="filter-select">
            <option value="">All Categories</option>
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
        Add Service
    </button>
</div>

<div class="services-table">
    <div class="table-container">
        <div id="loadingState" class="loading">
            Loading services...
        </div>
        <div id="emptyState" class="empty-state" style="display: none;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p>No services found</p>
        </div>
        <table id="servicesTable" style="display: none;">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Service Name</th>
                    <th>Category</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="servicesTableBody">
            </tbody>
        </table>
    </div>
</div>

<!-- Service Form Modal (Create/Edit) -->
<div id="serviceModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add Service</h3>
            <button class="modal-close" onclick="closeServiceModal()">&times;</button>
        </div>
        <form id="serviceForm">
            <input type="hidden" id="serviceId" name="service_id">
            
            <div class="form-group">
                <label for="serviceCategory">Category *</label>
                <input type="text" id="serviceCategory" name="service_category" required maxlength="50">
                <span class="error-message" id="error-service_category"></span>
            </div>
            
            <div class="form-group">
                <label for="subCategory">Sub-Category</label>
                <input type="text" id="subCategory" name="sub_category" maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="serviceName">Service Name *</label>
                <input type="text" id="serviceName" name="service_name" required maxlength="100">
                <span class="error-message" id="error-service_name"></span>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="duration">Duration (minutes) *</label>
                    <input type="number" id="duration" name="current_duration_minutes" required min="15" max="480">
                    <span class="error-message" id="error-current_duration_minutes"></span>
                </div>
                
                <div class="form-group">
                    <label for="cleanupTime">Cleanup Time (minutes) *</label>
                    <input type="number" id="cleanupTime" name="default_cleanup_minutes" required min="0" max="60">
                    <span class="error-message" id="error-default_cleanup_minutes"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="price">Price (RM) *</label>
                <input type="number" id="price" name="current_price" required min="0.01" step="0.01">
                <span class="error-message" id="error-current_price"></span>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            
            <div class="form-group">
                <label for="serviceImage">Image URL</label>
                <input type="text" id="serviceImage" name="service_image" maxlength="255">
                <small style="color: #666;">Enter image filename or URL</small>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeServiceModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="submitBtn">Save Service</button>
            </div>
        </form>
    </div>
</div>

<script src="list.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>
