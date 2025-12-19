<?php
// Include authentication check
require_once '../includes/auth_check.php';
requireAdminAuth();

// Set page title and base path
$page_title = 'Leave Requests';
$base_path = '../..';

// Include header
include '../includes/header.php';
?>

<div class="dashboard-header">
    <h1 class="dashboard-title">Staff Leave Requests</h1>
    <p class="dashboard-subtitle">
        Review and manage pending leave requests from your staff members.
    </p>
</div>

<!-- Filters Section -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 200px;">
                <label for="searchInput" style="display: block; margin-bottom: 6px; font-size: 0.875rem; font-weight: 500; color: #374151;">Search</label>
                <div style="position: relative;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; z-index: 1;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchInput" placeholder="Search by staff name or email..." style="width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; transition: all 0.2s;">
                </div>
            </div>
            <div style="min-width: 140px;">
                <label for="monthFilter" style="display: block; margin-bottom: 6px; font-size: 0.875rem; font-weight: 500; color: #374151;">Month</label>
                <select id="monthFilter" style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; background: white; cursor: pointer;">
                    <option value="">- unselected -</option>
                    <option value="01">January</option>
                    <option value="02">February</option>
                    <option value="03">March</option>
                    <option value="04">April</option>
                    <option value="05">May</option>
                    <option value="06">June</option>
                    <option value="07">July</option>
                    <option value="08">August</option>
                    <option value="09">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div style="min-width: 120px;">
                <label for="yearFilter" style="display: block; margin-bottom: 6px; font-size: 0.875rem; font-weight: 500; color: #374151;">Year</label>
                <select id="yearFilter" style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; background: white; cursor: pointer;">
                </select>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button id="applyFiltersBtn" style="padding: 0.6rem 1.25rem; background: #c29076; color: white; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                    Apply Filters
                </button>
            </div>
        </div>
        <div id="filterDisplay" style="margin-top: 12px; font-size: 0.875rem; color: #6b7280; font-weight: 500;"></div>
    </div>
</div>

<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card" id="card-pending-count">
        <div class="stat-info">
            <h3>Overall Pending Requests</h3>
            <div class="stat-value" id="pendingCount">0</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
    </div>

    <div class="stat-card" id="card-approved-month">
        <div class="stat-info">
            <h3>Approved This Month</h3>
            <div class="stat-value" id="approvedThisMonth">0</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
    </div>

    <div class="stat-card" id="card-rejected-month">
        <div class="stat-info">
            <h3>Rejected This Month</h3>
            <div class="stat-value" id="rejectedThisMonth">0</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="section-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 12px; flex-wrap: wrap;">
            <div style="display: flex; align-items: flex-start; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top: 2px; flex-shrink: 0;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <div>
                    <h2 class="section-title" style="margin: 0;">Pending Leave Requests</h2>
                    <p class="section-subtitle" style="margin: 0; font-size: 0.9rem; color: #6c757d;">
                        Review each request and take action to approve or reject.
                    </p>
                </div>
            </div>
        </div>

        <div id="leaveRequestsEmpty" style="display:none; text-align:center; padding:40px 16px; color:#999;">
            <p style="margin:0;">No pending leave requests.</p>
        </div>

        <div class="table-responsive" id="leaveRequestsTableWrapper">
            <table class="table" id="leaveRequestsTable">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Leave Type</th>
                        <th>Date Range</th>
                        <th>Duration</th>
                        <th>Reason</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="leaveRequestsTableBody">
                    <!-- Rows will be populated by JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    #leaveRequestsTable th {
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        color: #6c757d;
        white-space: nowrap;
    }

    #leaveRequestsTable td {
        vertical-align: middle;
        font-size: 0.9rem;
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .badge-pending {
        background-color: #fff7e6;
        color: #d48b18;
    }

    .btn-approve, .btn-reject {
        border-radius: 999px;
        padding: 0.35rem 0.9rem;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        border: none;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
        min-width: 90px;
        width: 90px;
    }

    .btn-approve {
        background-color: #22c55e;
        color: #fff;
    }

    .btn-approve:hover:enabled {
        background-color: #16a34a;
    }

    .btn-reject {
        background-color: #f3f4f6;
        color: #4b5563;
    }

    .btn-reject:hover:enabled {
        background-color: #e5e7eb;
    }

    .btn-approve:disabled,
    .btn-reject:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .reason-text {
        max-width: 260px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
        gap: 12px;
        flex-wrap: wrap;
    }

    .section-header svg {
        margin-top: 2px;
        flex-shrink: 0;
    }

    .section-subtitle {
        margin: 0;
        font-size: 0.9rem;
        color: #6c757d;
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #searchInput:focus,
    #monthFilter:focus,
    #yearFilter:focus {
        outline: none;
        border-color: #c29076;
        box-shadow: 0 0 0 3px rgba(194, 144, 118, 0.1);
    }

    #applyFiltersBtn:hover {
        background: #9e7364;
    }

    #applyFiltersBtn:active {
        transform: translateY(1px);
    }

        .date-range-cell,
    .submitted-cell {
        white-space: nowrap;
        font-size: 0.875rem;
    }

    /* Filter section responsive */
    @media (max-width: 768px) {
        .card-body > div:first-child {
            flex-direction: column;
            align-items: stretch !important;
        }

        .card-body > div:first-child > div {
            min-width: 100% !important;
        }

        #applyFiltersBtn {
            width: 100%;
        }
    }

    /* Tablet styles (768px - 1024px) */
    @media (max-width: 1024px) {
        .dashboard-header h1 {
            font-size: 1.75rem;
        }

        .dashboard-header p {
            font-size: 0.95rem;
        }

        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .stat-card {
            padding: 20px;
        }

        .stat-value {
            font-size: 1.75rem;
        }

        #leaveRequestsTable th,
        #leaveRequestsTable td {
            padding: 0.75rem 0.5rem;
            font-size: 0.85rem;
        }

        .reason-text {
            max-width: 200px;
        }
    }

    /* Mobile styles (below 768px) */
    @media (max-width: 768px) {
        .dashboard-header {
            margin-bottom: 20px;
        }

        .dashboard-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 0.875rem;
        }

        .stats-grid {
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            padding: 16px;
        }

        .stat-info h3 {
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
        }

        .stat-icon svg {
            width: 20px;
            height: 20px;
        }

        .card {
            margin-bottom: 16px;
        }

        .card-body {
            padding: 16px;
        }

        .section-header {
            margin-bottom: 12px;
            align-items: flex-start;
        }

        .section-header svg {
            margin-top: 2px;
        }

        .section-title {
            font-size: 1.125rem;
        }

        .section-subtitle {
            font-size: 0.8rem;
        }

        .table-responsive {
            margin: 0 -16px;
            padding: 0 16px;
        }

        .action-buttons {
            gap: 0.4rem;
        }

        #leaveRequestsTable {
            font-size: 0.8rem;
            min-width: 800px; /* Force horizontal scroll */
        }

        #leaveRequestsTable th,
        #leaveRequestsTable td {
            padding: 0.6rem 0.4rem;
        }

        /* Hide less critical columns on mobile */
        #leaveRequestsTable th:nth-child(5), /* Reason */
        #leaveRequestsTable td:nth-child(5),
        #leaveRequestsTable th:nth-child(6), /* Submitted */
        #leaveRequestsTable td:nth-child(6) {
            display: none;
        }

        .btn-approve,
        .btn-reject {
            padding: 0.3rem 0.7rem;
            font-size: 0.75rem;
            gap: 0.25rem;
        }

        .btn-approve svg,
        .btn-reject svg {
            width: 14px;
            height: 14px;
        }

        .badge-status {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }

        #leaveRequestsEmpty {
            padding: 32px 16px;
        }

        /* SVG removed from empty state */
    }

    /* Small mobile (below 480px) */
    @media (max-width: 480px) {
        .dashboard-header h1 {
            font-size: 1.25rem;
        }

        .stats-grid {
            gap: 10px;
        }

        .stat-card {
            padding: 14px;
        }

        .stat-value {
            font-size: 1.25rem;
        }

        .card-body {
            padding: 12px;
        }

        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .section-header svg {
            margin-top: 2px;
        }

        .section-subtitle {
            font-size: 0.75rem;
        }

        #leaveRequestsTable {
            min-width: 700px;
            font-size: 0.75rem;
        }

        #leaveRequestsTable th,
        #leaveRequestsTable td {
            padding: 0.5rem 0.3rem;
        }

        /* Hide Duration column on very small screens */
        #leaveRequestsTable th:nth-child(4),
        #leaveRequestsTable td:nth-child(4) {
            display: none;
        }

        .btn-approve,
        .btn-reject {
            padding: 0.25rem 0.6rem;
            font-size: 0.7rem;
        }

        /* Stack action buttons vertically on very small screens */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            min-width: 100px;
        }

        .action-buttons .btn-approve,
        .action-buttons .btn-reject {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<!-- Modal for viewing approved/rejected requests -->
<div id="requestsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Leave Requests</h2>
            <button class="modal-close" id="modalCloseBtn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div id="modalLoading" style="text-align: center; padding: 40px; color: #999;">
                Loading requests...
            </div>
            <div id="modalEmpty" style="display: none; text-align: center; padding: 40px; color: #999;">
                <p>No requests found.</p>
            </div>
            <div id="modalTableWrapper" style="display: none;">
                <div class="table-responsive">
                    <table class="table" id="modalTable">
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Leave Type</th>
                                <th>Date Range</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th>Submitted</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="modalTableBody">
                            <!-- Rows will be populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
        animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 100%;
        max-width: 1200px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: #111827;
    }

    .modal-close {
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        border-radius: 6px;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: #f3f4f6;
        color: #111827;
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
    }

    #modalTable {
        width: 100%;
        border-collapse: collapse;
    }

    #modalTable th {
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        color: #6c757d;
        white-space: nowrap;
        padding: 12px;
        text-align: left;
        border-bottom: 2px solid #e5e7eb;
        background: #f9fafb;
    }

    #modalTable td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
        font-size: 0.9rem;
    }

    #modalTable tbody tr:hover {
        background: #f9fafb;
    }

    .badge-approved {
        background-color: #d1fae5;
        color: #065f46;
    }

    .badge-rejected {
        background-color: #fee2e2;
        color: #991b1b;
    }

    /* Make stat cards clickable */
    #card-approved-month,
    #card-rejected-month {
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    #card-approved-month:hover,
    #card-rejected-month:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        .modal-content {
            max-width: 100%;
            max-height: 95vh;
            margin: 10px;
        }

        .modal-header {
            padding: 16px;
        }

        .modal-header h2 {
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 16px;
        }

        #modalTable {
            font-size: 0.8rem;
            min-width: 700px;
        }

        #modalTable th,
        #modalTable td {
            padding: 8px 6px;
        }
    }
</style>

<script>
    const LEAVE_REQUESTS_API_BASE = '<?php echo $base_path; ?>/api/admin/leave_requests';
</script>
<script src="leave_requests.js"></script>

<?php
// Include footer
include '../includes/footer.php';
?>


