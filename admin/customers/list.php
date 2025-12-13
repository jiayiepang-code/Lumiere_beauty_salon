<?php
// Include authentication check
require_once '../includes/auth_check.php';

// Require admin authentication
requireAdminAuth();

// Set page title
$page_title = 'Customer Management';
$base_path = '../..';

// Include header
include '../includes/header.php';
?>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Customer Management</h2>
        <!-- Export button could go here -->
        <button class="btn btn-secondary" onclick="exportCustomers()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export CSV
        </button>
    </div>
    
    <div class="card-body">
        <!-- Filters -->
        <div class="filters-bar" style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <div class="search-box" style="flex: 1; min-width: 250px; position: relative;">
                <input type="text" id="searchInput" class="form-control" placeholder="Search customers (name, email, phone)..." style="padding-left: 40px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999;">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </div>
            
            <select id="sortFilter" class="form-control" style="width: auto; min-width: 150px;">
                <option value="name_asc">Name (A-Z)</option>
                <option value="name_desc">Name (Z-A)</option>
                <option value="bookings_desc">Most Bookings</option>
                <option value="recent_desc">Recently Active</option>
            </select>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <div id="loadingState" class="loading" style="text-align: center; padding: 40px; color: #999;">
                Loading customers...
            </div>
            
            <div id="emptyState" class="empty-state" style="display: none; text-align: center; padding: 60px 20px; color: #999;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 16px; opacity: 0.5; display: block;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <p>No customers found</p>
            </div>
            
            <table id="customersTable" class="table" style="display: none;">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Contact Info</th>
                        <th>Total Bookings</th>
                        <th>Total Spent</th>
                        <th>Last Visit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <!-- Populated by JS -->
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div id="pagination" style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
                <!-- Pagination buttons will be generated here -->
            </div>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div id="customerModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 id="modalTitle">Customer Details</h3>
            <button class="modal-close" onclick="closeCustomerModal()">&times;</button>
        </div>
        <div class="modal-body" id="customerDetailsContent">
            <!-- Content loaded via JS -->
            <div style="text-align: center; padding: 20px;">Loading details...</div>
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
