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

<!-- SweetAlert2 for beautiful alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="services-page">
    <div class="services-header">
        <div>
            <h1 class="services-title">Services</h1>
            <p class="services-subtitle">Manage your salon services and pricing</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Service
        </button>
    </div>

    <div class="services-filters">
        <div class="search-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="searchInput" placeholder="Search services..." aria-label="Search services">
        </div>
        <div class="category-filter">
            <select id="categoryFilter" aria-label="Filter by category">
                <option value="">All Categories</option>
            </select>
        </div>
    </div>

    <div class="services-table-card">
        <div id="loadingState" class="loading">Loading services...</div>

        <div id="emptyState" class="empty-state" style="display: none;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p>No services found</p>
            <button class="btn btn-primary" onclick="openCreateModal()" style="margin-top: 12px;">Add your first service</button>
        </div>

        <div class="table-responsive">
            <table id="servicesTable" class="services-table" style="display: none;">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Service Name</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="servicesTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Service Form Modal (Create/Edit) -->
<div id="serviceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Service</h3>
            <button class="modal-close" onclick="closeServiceModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="serviceForm">
                <input type="hidden" id="serviceId" name="service_id">
                
                <div class="form-group">
                    <label for="serviceCategory">Category *</label>
                    <input type="text" id="serviceCategory" name="service_category" class="form-control" required maxlength="50" placeholder="e.g., Hair, Beauty, Massage, Nail">
                    <span class="error-message" id="error-service_category"></span>
                </div>
                
                <div class="form-group">
                    <label for="subCategory">Sub-Category</label>
                    <input type="text" id="subCategory" name="sub_category" class="form-control" maxlength="50" placeholder="e.g., Haircut, Facial, Body Massage">
                </div>
                
                <div class="form-group">
                    <label for="serviceName">Service Name *</label>
                    <input type="text" id="serviceName" name="service_name" class="form-control" required maxlength="100" placeholder="e.g., Classic Haircut with Wash">
                    <span class="error-message" id="error-service_name"></span>
                </div>
                
                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="durationMinutes">Duration (min) *</label>
                        <input type="number" id="durationMinutes" name="duration_minutes" class="form-control" required min="5" step="5" placeholder="e.g., 30">
                        <span class="error-message" id="error-duration_minutes"></span>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label for="price">Price ($) *</label>
                        <input type="number" id="price" name="price" class="form-control" required min="0" step="0.01" placeholder="e.g., 50.00">
                        <span class="error-message" id="error-price"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe the service details, benefits, and what the customer can expect..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="serviceImage">Service Image</label>
                    <input type="file" id="serviceImage" name="service_image" class="form-control" accept="image/*">
                    <div id="imagePreviewContainer" style="margin-top: 10px; display: none;">
                        <img id="imagePreview" src="" alt="Preview" style="max-width: 100px; max-height: 100px; border-radius: 8px; object-fit: cover;">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeServiceModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-body" style="padding: 30px 20px;">
            <div style="width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                </svg>
            </div>
            <h3 style="margin-bottom: 10px; color: #1f2937;">Delete Service?</h3>
            <p style="color: #6b7280; margin-bottom: 25px;">Are you sure you want to delete <strong id="deleteServiceName"></strong>? This action cannot be undone.</p>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDelete()">Delete Service</button>
            </div>
        </div>
    </div>
</div>

<script>
// CSRF Token from PHP
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token ?? ''); ?>';
</script>
<script src="list.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>
