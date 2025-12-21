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

<style>
    :root {
        --primary-color: #c29076;
        --secondary-color: #333333;
        --text-dark: #5c4e4b;
        --text-gray: #6C757D;
        --border-light: #E9ECEF;
        --light-gray: #F8F9FA;
    }

    .filters-section {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-light);
    }

    .filters-section .row {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .filters-section .col-search {
        flex: 1;
        margin: 0;
    }

    .filters-section .col-filter {
        flex: 0 0 180px;
        margin: 0;
    }

    .search-input, .filter-select {
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        height: 43px;
    }

    .search-input {
        padding-left: 2.5rem;
    }

    .search-input:focus, .filter-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(194, 144, 118, 0.1);
        outline: none;
    }

    .btn-toggle {
        transition: color 0.2s ease;
    }

    .btn-toggle.toggle-active {
        color: #2A9D8F;
    }

    .btn-toggle.toggle-active:hover {
        background-color: rgba(42, 157, 143, 0.1);
    }

    .btn-toggle.toggle-inactive {
        color: #9CA3AF;
    }

    .btn-toggle.toggle-inactive:hover {
        background-color: rgba(156, 163, 175, 0.1);
    }

    @media (max-width: 768px) {
        .filters-section .row {
            flex-direction: column;
        }

        .filters-section .col-search,
        .filters-section .col-filter {
            width: 100%;
        }
    }

    /* Image upload area styling */
    .image-upload-wrapper {
        position: relative;
    }
    
    .image-upload-area {
        border: 2px dashed #c29076;
        border-radius: 8px;
        padding: 1.5rem;
        background-color: white;
        transition: all 0.2s ease;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 180px;
        gap: 1rem;
    }
    
    .image-upload-area:hover {
        background-color: #fafafa;
        border-color: var(--primary-color);
    }
    
    .upload-icon-container {
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.6;
    }
    
    .upload-icon-container svg {
        width: 48px;
        height: 48px;
    }
    
    .upload-button-area {
        display: flex;
        align-items: center;
        gap: 1rem;
        justify-content: center;
    }
    
    .upload-button-area .btn {
        border: 1px solid #333;
        color: #333;
        background: white;
        padding: 0.4rem 1rem;
        font-size: 0.875rem;
        border-radius: 4px;
        cursor: pointer;
        pointer-events: auto;
    }
    
    .upload-button-area .btn:hover {
        background-color: #f5f5f5;
    }
    
    .upload-button-area .btn:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(194, 144, 118, 0.3);
    }
    
    .file-name-display {
        color: #999;
        font-size: 0.875rem;
    }
    
    .upload-hint {
        color: #999;
        font-size: 0.875rem;
        margin: 0;
    }
    
    .image-preview-container {
        border: 2px dashed #c29076;
        border-radius: 8px;
        padding: 1.5rem;
        background-color: white;
        text-align: center;
        position: relative;
    }
    
    .preview-wrapper {
        position: relative;
        display: inline-block;
        margin-bottom: 0.75rem;
    }
    
    .preview-image {
        max-width: 200px;
        max-height: 200px;
        width: 100%;
        height: auto;
        border-radius: 8px;
        object-fit: cover;
        border: 2px solid var(--border-light);
        display: block;
    }
    
    .preview-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.6);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        opacity: 0;
        transition: opacity 0.2s ease;
        pointer-events: none;
        flex-wrap: wrap;
        padding: 0.5rem;
    }
    
    .preview-wrapper:hover .preview-overlay {
        opacity: 1;
        pointer-events: auto;
    }
    
    .btn-preview-action {
        padding: 0.4rem 0.75rem;
        border: none;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s ease;
        white-space: nowrap;
        min-width: fit-content;
        flex-shrink: 0;
    }
    
    .btn-preview-action svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }
    
    .btn-replace {
        background-color: white;
        color: #333;
    }
    
    .btn-replace:hover {
        background-color: #f5f5f5;
    }
    
    .btn-remove {
        background-color: #dc2626;
        color: white;
    }
    
    .btn-remove:hover {
        background-color: #b91c1c;
    }
    
    /* Responsive adjustments for small images */
    @media (max-width: 768px) {
        .preview-image {
            max-width: 150px;
            max-height: 150px;
        }
        
        .btn-preview-action {
            padding: 0.35rem 0.6rem;
            font-size: 0.7rem;
            gap: 0.3rem;
        }
        
        .btn-preview-action svg {
            width: 12px;
            height: 12px;
        }
    }
    
    /* Ensure buttons are visible even on very small images */
    .preview-wrapper {
        min-width: 100px;
        min-height: 100px;
        position: relative;
        display: inline-block;
    }
    
    .preview-image {
        min-width: 100px;
        min-height: 100px;
    }
    
    /* Scale buttons down for smaller containers */
    @media (max-width: 480px) {
        .preview-overlay {
            gap: 0.3rem;
            padding: 0.3rem;
        }
        
        .btn-preview-action {
            padding: 0.3rem 0.5rem;
            font-size: 0.65rem;
            gap: 0.25rem;
        }
        
        .btn-preview-action svg {
            width: 11px;
            height: 11px;
        }
    }
    
    .preview-filename {
        color: var(--text-gray);
        font-size: 0.875rem;
        margin: 0;
    }
    
    .text-muted {
        color: #6C757D;
        font-size: 0.875rem;
    }
    
    /* Image size error message */
    .image-size-error {
        background-color: #fff5f5;
        border: 2px solid #dc2626;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #dc2626;
        font-size: 0.875rem;
        font-weight: 600;
        animation: slideInError 0.3s ease-out;
    }
    
    .image-size-error svg {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
    }
    
    @keyframes slideInError {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

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

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="row g-3">
            <div class="col-search">
                <div class="position-relative">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-gray); pointer-events: none; z-index: 1;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" class="form-control search-input" id="searchInput" placeholder="Search services..." aria-label="Search services">
                </div>
            </div>
            <div class="col-filter">
                <select class="form-select filter-select" id="categoryFilter" aria-label="Filter by category">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="col-filter">
                <select class="form-select filter-select" id="statusFilter" aria-label="Filter by status">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
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
                        <input type="number" id="durationMinutes" name="current_duration_minutes" class="form-control" required min="5" max="480" step="5" placeholder="e.g., 30">
                        <span class="error-message" id="error-current_duration_minutes"></span>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label for="price">Price (RM) *</label>
                        <input type="number" id="price" name="current_price" class="form-control" required min="0.01" step="0.01" placeholder="e.g., 50.00">
                        <span class="error-message" id="error-current_price"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="cleanupTime">Cleanup Time (min) *</label>
                    <input type="number" id="cleanupTime" name="default_cleanup_minutes" class="form-control" required min="0" max="60" step="1" placeholder="e.g., 10" value="10">
                    <span class="error-message" id="error-default_cleanup_minutes"></span>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe the service details, benefits, and what the customer can expect..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="serviceImage">Service Image</label>
                    <div id="imageSizeError" class="image-size-error" style="display: none; margin-bottom: 0.75rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>Image size cannot exceed 200MB. Please choose a smaller file.</span>
                    </div>
                    <div class="image-upload-wrapper">
                        <div class="image-upload-area" id="imageUploadArea">
                            <div id="uploadIconContainer" class="upload-icon-container">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                            </div>
                            <div class="upload-button-area">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('serviceImage').click();">Choose File</button>
                                <span class="file-name-display" id="fileNameDisplay">No file chosen</span>
                            </div>
                            <p class="upload-hint">Click to upload</p>
                        </div>
                        <div id="imagePreviewContainer" class="image-preview-container" style="display: none;">
                            <div class="preview-wrapper">
                                <img id="imagePreview" src="" alt="Preview" class="preview-image">
                                <div class="preview-overlay">
                                    <button type="button" class="btn-preview-action btn-replace" onclick="document.getElementById('serviceImage').click();" title="Replace">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" y1="3" x2="12" y2="15"></line>
                                        </svg>
                                        Replace
                                    </button>
                                    <button type="button" class="btn-preview-action btn-remove" onclick="removeImage()" title="Remove">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <p class="preview-filename" id="previewFileName"></p>
                        </div>
                        <input type="file" 
                               id="serviceImage" 
                               name="service_image" 
                               accept="image/jpeg,image/png,image/gif,image/webp" 
                               style="display: none;">
                    </div>
                    <small class="text-muted d-block mt-2">Max 200MB. Allowed: JPEG, PNG, GIF, WebP</small>
                    <span class="error-message" id="error-service_image"></span>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeServiceModal()">Cancel</button>
                    <button type="submit" id="submitBtn" class="btn btn-primary">
                        <span id="submitBtnText">Save Service</span>
                        <span id="submitBtnSpinner" style="display: none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                                <path d="M21 12a9 9 0 11-6.219-8.56"/>
                            </svg>
                        </span>
                    </button>
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
            <p style="color: #6b7280; margin-bottom: 10px;">Are you sure you want to permanently delete <strong id="deleteServiceName"></strong>?</p>
            <p style="color: #E76F51; font-weight: 500; margin-bottom: 10px; font-size: 0.95em;">This action is permanent and can impact reports and linked bookings.</p>
            <p style="color: #6b7280; font-size: 0.9em; margin-bottom: 10px; margin-top: 15px;"><strong>Consequences:</strong></p>
            <ul style="color: #6b7280; font-size: 0.9em; margin: 0 0 15px 20px; line-height: 1.6;">
              <li>Revenue-by-service and trends may lose attribution.</li>
              <li>Past booking lines may show missing service details if fully removed.</li>
              <li>Future bookings (if any) must be cancelled or reassigned.</li>
              <li>Database links that reference this service can break if permanently deleted.</li>
            </ul>
            
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
