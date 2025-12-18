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
    $sql = "SELECT staff_email, phone, first_name, last_name, role, 
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

/**
 * Format phone number for display
 * Adds +60 prefix if number starts with 60
 */
function formatPhoneForDisplay($phone) {
    if (empty($phone)) return '';
    
    // If starts with 60, add + prefix
    if (preg_match('/^60/', $phone)) {
        return '+' . $phone;
    }
    
    // If starts with 01, return as is (local format)
    return $phone;
}

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

    /* Constrain container width for better readability */
    .container-fluid {
        max-width: 100%;
        margin: 0;
        padding: 0;
        }

        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 0;
        }

        .staff-header h1 {
        font-size: 2rem;
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
        background: linear-gradient(135deg, var(--primary-color), #9e7364);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
        font-size: 0.95rem;
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

    .filters-section .col-md-6 {
        flex: 1;
        margin: 0;
    }

    .search-input, .filter-select {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        height: 43px; /* Fixed height to match exactly */
    }

    .search-input {
        padding-left: 2.5rem; /* Space for search icon */
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
            font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 1rem;
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
        padding: 1rem;
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
            font-size: 1rem;
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
        font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .staff-bio {
        font-size: 0.875rem;
            color: var(--text-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        max-width: 300px;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
        gap: 0.75rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-gray);
        font-size: 0.875rem;
        }

        .contact-item svg {
            width: 16px;
            height: 16px;
        color: #6C757D; /* Dark grey for better visibility on hover */
        flex-shrink: 0;
        }

        .role-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
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
        width: 18px;
        height: 18px;
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
        .container-fluid {
            padding-left: 1rem;
            padding-right: 1rem;
        }

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
            font-size: 0.875rem;
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

        .btn-icon svg {
            width: 16px;
            height: 16px;
        }
    }

    /* Additional responsive adjustments for better accessibility */
    @media (min-width: 1400px) {
        .container-fluid {
            max-width: 1200px; /* Prevent content from becoming too wide on large screens */
        }
    }

    /* Modal Styling - Clean and Simple */
    #staffModal.modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    #staffModal.modal.active {
        display: flex;
    }

    #staffModal .modal-content {
        background: white;
        border-radius: 12px;
        width: 100%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #staffModal .modal-header {
        padding: 24px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    #staffModal .modal-header h3 {
        font-size: 20px;
        color: var(--text-dark);
        margin: 0;
        font-weight: 600;
    }

    #staffModal .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        color: #999;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    #staffModal .modal-close:hover {
        background: #f0f0f0;
        color: var(--text-dark);
    }

    #staffModal .modal-body {
        padding: 24px;
        max-height: calc(100vh - 220px);
        overflow-y: auto;
    }
    
    /* Optimize form spacing to fit all fields */
    #staffModal .modal-body .mb-3 {
        margin-bottom: 1rem !important;
    }
    
    #staffModal .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.4rem;
    }
    
    #staffModal .form-control,
    #staffModal .form-select {
        padding: 0.65rem 0.9rem;
        font-size: 0.9rem;
        height: auto;
    }
    
    #staffModal .form-control:focus,
    #staffModal .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(194, 144, 118, 0.1);
    }
    
    #staffModal textarea.form-control {
        min-height: 60px;
    }
    
    #staffModal .text-muted {
        font-size: 0.8rem;
    }

    #staffModal .modal-footer {
        padding: 20px 24px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    /* Form control styling */
    #staffModal .form-control, 
    #staffModal .form-select {
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    #staffModal .form-control:focus, 
    #staffModal .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(194, 144, 118, 0.1);
        outline: none;
    }

    #staffModal .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    /* Image upload area styling - Clean design */
    .image-upload-wrapper {
        position: relative;
    }
    
    .image-upload-area {
        border: 2px dashed #c29076;
        border-radius: 8px;
        padding: 1.5rem;
        background-color: #fafafa;
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
        background-color: #f5f5f5;
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
        background-color: #fafafa;
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
        color: #666;
        font-size: 0.875rem;
        margin: 0;
        word-break: break-all;
        text-align: center;
    }

    /* Toggle switch styling */
    #staffModal .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    #staffModal .form-check-input {
        width: 3rem;
        height: 1.5rem;
        cursor: pointer;
    }

    /* Error message styling with animation */
    .error-message {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.5rem;
        padding: 0.5rem 0.75rem;
        background-color: #fff5f5;
        border-left: 3px solid #dc3545;
        border-radius: 4px;
        display: none;
        animation: slideInError 0.3s ease-out;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.1);
    }

    .error-message.show {
        display: block;
        animation: slideInError 0.3s ease-out, shake 0.5s ease-in-out;
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

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    /* Add red border to input fields with errors */
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    /* Make modal responsive */
    @media (max-width: 768px) {
        #staffModal.modal {
            padding: 1rem;
        }

        #staffModal .modal-content {
            max-width: 100%;
            max-height: calc(100vh - 2rem);
        }

        #staffModal .modal-header,
        #staffModal .modal-body,
        #staffModal .modal-footer {
            padding: 1.5rem;
        }
    }
    </style>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="staff-header mb-4">
        <div>
            <h1>Staff</h1>
            <p class="subtitle">Manage your staff members and roles</p>
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
                    <input type="text" class="form-control search-input" id="searchInput" placeholder="Search staff...">
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
                        
                        // #region agent log
                        $log_data = [
                            'sessionId' => 'debug-session',
                            'runId' => 'run2',
                            'hypothesisId' => 'A',
                            'location' => 'list.php:905',
                            'message' => 'Raw database image path',
                            'data' => [
                                'staff_email' => $member['staff_email'],
                                'raw_staff_image' => $member['staff_image'],
                                'imagePath_after_htmlspecialchars' => $imagePath
                            ],
                            'timestamp' => time() * 1000
                        ];
                        file_put_contents(__DIR__ . '/../../.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
                        // #endregion
                        
                        // Smart image path resolution - handles old paths, missing extensions, and malformed paths
                        if (!empty($imagePath)) {
                            $originalPath = $imagePath;
                            
                            // Extract filename from various path formats
                            // Handle: /images/70.png, /images/42, staff/uploads/staff/file.png, etc.
                            $filename = basename($imagePath);
                            
                            // If filename has no extension, try common image extensions
                            $extensions = ['', '.png', '.jpg', '.jpeg', '.gif', '.webp'];
                            $foundFile = false;
                            $baseDir = __DIR__ . '/../../images/';
                            
                            // #region agent log
                            $log_data = [
                                'sessionId' => 'debug-session',
                                'runId' => 'run2',
                                'hypothesisId' => 'B',
                                'location' => 'list.php:930',
                                'message' => 'File resolution attempt',
                                'data' => [
                                    'staff_email' => $member['staff_email'],
                                    'original_path' => $originalPath,
                                    'extracted_filename' => $filename,
                                    'base_dir' => $baseDir
                                ],
                                'timestamp' => time() * 1000
                            ];
                            file_put_contents(__DIR__ . '/../../.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
                            // #endregion
                            
                            // Try to find file in root images directory first (where old files are)
                            foreach ($extensions as $ext) {
                                $testFilename = $filename . $ext;
                                $testPathRoot = $baseDir . $testFilename;
                                
                                if (file_exists($testPathRoot)) {
                                    // Use relative path with base_path to match other admin pages
                                    $imagePath = $base_path . '/images/' . $testFilename;
                                    $foundFile = true;
                                    
                                    // #region agent log
                                    $log_data = [
                                        'sessionId' => 'debug-session',
                                        'runId' => 'run2',
                                        'hypothesisId' => 'C',
                                        'location' => 'list.php:950',
                                        'message' => 'File found in root directory',
                                        'data' => [
                                            'staff_email' => $member['staff_email'],
                                            'found_filename' => $testFilename,
                                            'final_path' => $imagePath,
                                            'physical_path' => $testPathRoot
                                        ],
                                        'timestamp' => time() * 1000
                                    ];
                                    file_put_contents(__DIR__ . '/../../.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
                                    // #endregion
                                    
                                    break;
                                }
                            }
                            
                            // If not found in root, try staff directory
                            if (!$foundFile) {
                                foreach ($extensions as $ext) {
                                    $testFilename = $filename . $ext;
                                    $testPathStaff = $baseDir . 'staff/' . $testFilename;
                                    
                                    if (file_exists($testPathStaff)) {
                                        // Use relative path with base_path to match other admin pages
                                        $imagePath = $base_path . '/images/staff/' . $testFilename;
                                        $foundFile = true;
                                        
                                        // #region agent log
                                        $log_data = [
                                            'sessionId' => 'debug-session',
                                            'runId' => 'run2',
                                            'hypothesisId' => 'C',
                                            'location' => 'list.php:970',
                                            'message' => 'File found in staff directory',
                                            'data' => [
                                                'staff_email' => $member['staff_email'],
                                                'found_filename' => $testFilename,
                                                'final_path' => $imagePath,
                                                'physical_path' => $testPathStaff
                                            ],
                                            'timestamp' => time() * 1000
                                        ];
                                        file_put_contents(__DIR__ . '/../../.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
                                        // #endregion
                                        
                                        break;
                                    }
                                }
                            }
                            
                            // If still not found, set to empty so it shows initials instead of broken image
                            if (!$foundFile) {
                                $imagePath = '';
                                
                                // #region agent log
                                $log_data = [
                                    'sessionId' => 'debug-session',
                                    'runId' => 'run2',
                                    'hypothesisId' => 'D',
                                    'location' => 'list.php:985',
                                    'message' => 'File not found, setting to empty to show initials',
                                    'data' => [
                                        'staff_email' => $member['staff_email'],
                                        'original_path' => $originalPath,
                                        'final_path' => $imagePath
                                    ],
                                    'timestamp' => time() * 1000
                                ];
                                file_put_contents(__DIR__ . '/../../.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
                                // #endregion
                            }
                        }
                    ?>
                    <tr data-email="<?php echo htmlspecialchars($member['staff_email']); ?>"
                        data-role="<?php echo htmlspecialchars($member['role']); ?>"
                        data-active="<?php echo $member['is_active'] ? '1' : '0'; ?>"
                        data-name="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>">
                        <td>
                            <div class="staff-cell">
                                <div class="staff-avatar">
                                    <?php if (!empty($imagePath)): ?>
                                        <?php 
                                        // #region agent log
                                        $log_data = [
                                            'sessionId' => 'debug-session',
                                            'runId' => 'run3',
                                            'hypothesisId' => 'E',
                                            'location' => 'list.php:1005',
                                            'message' => 'HTML output - image src attribute',
                                            'data' => [
                                                'staff_email' => $member['staff_email'],
                                                'imagePath_used_in_html' => $imagePath,
                                                'base_path' => $base_path ?? 'N/A',
                                                'full_url_would_be' => ($base_path ?? '') . $imagePath
                                            ],
                                            'timestamp' => time() * 1000
                                        ];
                                        file_put_contents(__DIR__ . '/../../.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
                                        // #endregion
                                        ?>
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
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6C757D" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <?php echo htmlspecialchars($member['staff_email']); ?>
                                </div>
                                <div class="contact-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6C757D" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <?php echo htmlspecialchars(formatPhoneForDisplay($member['phone'])); ?>
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
                                <button class="btn-icon btn-toggle <?php echo $member['is_active'] ? 'toggle-active' : 'toggle-inactive'; ?>" 
                                        onclick="toggleStaffStatus('<?php echo htmlspecialchars($member['staff_email'], ENT_QUOTES); ?>', <?php echo $member['is_active'] ? '1' : '0'; ?>)" 
                                        title="<?php echo $member['is_active'] ? 'Deactivate' : 'Activate'; ?>" 
                                        type="button">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="5" width="22" height="14" rx="7" ry="7"></rect>
                                        <circle cx="<?php echo $member['is_active'] ? '16' : '8'; ?>" cy="12" r="3"></circle>
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
<div id="staffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Staff Member</h3>
            <button class="modal-close" onclick="closeStaffModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="staffForm" enctype="multipart/form-data">
                    <input type="hidden" id="isEdit" name="is_edit" value="0">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required maxlength="50" placeholder="First name">
                            <div class="error-message" id="error-first_name" style="display: none;"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required maxlength="50" placeholder="Last name">
                            <div class="error-message" id="error-last_name" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="staffEmail" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="staffEmail" name="staff_email" required maxlength="100" placeholder="email@lumiere.com" autocomplete="off">
                       <div class="error-message" id="error-staff_email" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" required placeholder="+60 12 345 6789">
                        <div class="error-message" id="error-phone" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span id="passwordRequired" class="text-danger">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-control password-input" id="password" name="password" minlength="8" placeholder="Enter your password">
                            <button type="button" class="password-toggle-btn" id="togglePassword" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                                <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg id="eyeOffIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1" id="passwordHint">Min 8 chars, 1 uppercase, 1 number, 1 special character</small>
                        <div class="error-message" id="error-password" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select role</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                        <div class="error-message" id="error-role" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="2" placeholder="Brief description..." maxlength="500" style="resize: vertical;"></textarea>
                        <small class="text-muted">Brief introduction about the staff member</small>
                        <div class="error-message" id="error-bio" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Profile Photo</label>
                        <div id="imageSizeError" class="image-size-error" style="display: none; margin-bottom: 0.75rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>Image size cannot exceed 2MB. Please choose a smaller file.</span>
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
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('staffImage').click();">Choose File</button>
                                    <span class="file-name-display" id="fileNameDisplay">No file chosen</span>
                                </div>
                                <p class="upload-hint">Click to upload</p>
                            </div>
                            <div id="imagePreviewContainer" class="image-preview-container" style="display: none;">
                                <div class="preview-wrapper">
                                    <img id="imagePreview" src="" alt="Preview" class="preview-image">
                                    <div class="preview-overlay">
                                        <button type="button" class="btn-preview-action btn-replace" onclick="document.getElementById('staffImage').click();" title="Replace">
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
                                   id="staffImage" 
                                   name="staff_image" 
                                   accept="image/jpeg,image/png,image/gif,image/webp" 
                                   style="display: none;">
                        </div>
                        <small class="text-muted d-block mt-2">Max 2MB. Allowed: JPEG, PNG, GIF, WebP</small>
                        <div class="error-message" id="error-staff_image" style="display: none;"></div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                        <label class="form-label mb-0" for="isActiveToggle">Active</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="isActiveToggle" checked>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitButton">
                            <span id="submitBtnText">Add Staff Member</span>
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

<style>
    /* Image upload area hover effect */
    .image-upload-area:hover {
        background-color: #f5f5f5 !important;
        border-color: var(--primary-color) !important;
    }

    /* Form control styling */
    .form-control, .form-select {
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(194, 144, 118, 0.1);
        outline: none;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    /* Password input with eye toggle button */
    .password-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-input-wrapper .password-input {
        padding-right: 45px;
        width: 100%;
    }

    .password-toggle-btn {
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        background: transparent;
        border: none;
        padding: 0 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9CA3AF;
        transition: color 0.2s ease;
        z-index: 5;
    }

    .password-toggle-btn:hover {
        color: #6B7280;
    }

    .password-toggle-btn:focus {
        outline: none;
    }

    .password-toggle-btn svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }

    /* Toggle switch styling */
    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    /* Button styling to match services modal */
    #staffModal .btn-primary {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    #staffModal .btn-primary:hover {
        background: #9e7364;
        color: white;
    }

    #staffModal .btn-secondary {
        background: #6C757D;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    #staffModal .btn-secondary:hover {
        background: #5a6268;
        color: white;
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
