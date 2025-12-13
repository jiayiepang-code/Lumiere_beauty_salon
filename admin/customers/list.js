// Customer Management JavaScript

let allCustomers = [];
let currentPage = 1;
const itemsPerPage = 10;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
    loadCustomers();
    setupEventListeners();
});

function setupEventListeners() {
    // Search input
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("input", debounce(filterCustomers, 300));
    }

    // Sort filter
    const sortFilter = document.getElementById("sortFilter");
    if (sortFilter) {
        sortFilter.addEventListener("change", filterCustomers);
    }
    
    // Close modal on outside click
    const customerModal = document.getElementById("customerModal");
    if (customerModal) {
        customerModal.addEventListener("click", function (e) {
            if (e.target === customerModal) {
                closeCustomerModal();
            }
        });
    }
}

function loadCustomers() {
    const loadingState = document.getElementById("loadingState");
    const emptyState = document.getElementById("emptyState");
    const table = document.getElementById("customersTable");
    
    loadingState.style.display = "block";
    emptyState.style.display = "none";
    table.style.display = "none";
    
    fetch('../../api/admin/customers/list.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                allCustomers = data.customers;
                filterCustomers();
            } else {
                showToast(data.error || 'Failed to load customers', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading customers', 'error');
            loadingState.style.display = "none";
            emptyState.style.display = "block";
        })
        .finally(() => {
            loadingState.style.display = "none";
        });
}

function filterCustomers() {
    const searchTerm = document.getElementById("searchInput").value.toLowerCase();
    const sortValue = document.getElementById("sortFilter").value;
    
    let filtered = allCustomers.filter(customer => {
        const searchString = `${customer.first_name} ${customer.last_name} ${customer.email} ${customer.phone}`.toLowerCase();
        return searchString.includes(searchTerm);
    });
    
    // Sort
    filtered.sort((a, b) => {
        switch (sortValue) {
            case 'name_asc':
                return (a.first_name + a.last_name).localeCompare(b.first_name + b.last_name);
            case 'name_desc':
                return (b.first_name + b.last_name).localeCompare(a.first_name + a.last_name);
            case 'bookings_desc':
                return b.total_bookings - a.total_bookings;
            case 'recent_desc':
                // Handle null dates
                if (!a.last_visit) return 1;
                if (!b.last_visit) return -1;
                return new Date(b.last_visit) - new Date(a.last_visit);
            default:
                return 0;
        }
    });
    
    renderTable(filtered);
}

function renderTable(customers) {
    const tbody = document.getElementById("customersTableBody");
    const table = document.getElementById("customersTable");
    const emptyState = document.getElementById("emptyState");
    const pagination = document.getElementById("pagination");
    
    tbody.innerHTML = "";
    
    if (customers.length === 0) {
        table.style.display = "none";
        emptyState.style.display = "block";
        pagination.innerHTML = "";
        return;
    }
    
    table.style.display = "table";
    emptyState.style.display = "none";
    
    // Pagination logic
    const totalPages = Math.ceil(customers.length / itemsPerPage);
    if (currentPage > totalPages) currentPage = 1;
    
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageItems = customers.slice(start, end);
    
    pageItems.forEach(customer => {
        const tr = document.createElement("tr");
        
        const lastVisit = customer.last_visit 
            ? new Date(customer.last_visit).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) 
            : '<span style="color: #999;">Never</span>';
            
        const totalSpent = parseFloat(customer.total_spent || 0).toFixed(2);
        
        tr.innerHTML = `
            <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #666;">
                        ${customer.first_name.charAt(0)}${customer.last_name.charAt(0)}
                    </div>
                    <div>
                        <div style="font-weight: 500; color: #333;">${escapeHtml(customer.first_name)} ${escapeHtml(customer.last_name)}</div>
                    </div>
                </div>
            </td>
            <td>
                <div style="font-size: 13px;">
                    <div style="margin-bottom: 4px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px; color: #999;">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        ${escapeHtml(customer.email)}
                    </div>
                    <div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px; color: #999;">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        ${escapeHtml(customer.phone)}
                    </div>
                </div>
            </td>
            <td>
                <span style="display: inline-block; padding: 4px 12px; background: #f0f9ff; color: #0369a1; border-radius: 12px; font-size: 13px; font-weight: 500;">
                    ${customer.total_bookings} bookings
                </span>
            </td>
            <td style="font-weight: 500;">$${totalSpent}</td>
            <td style="color: #666; font-size: 14px;">${lastVisit}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="viewCustomer('${escapeHtml(customer.email)}')" title="View Details">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    renderPagination(totalPages);
}

function renderPagination(totalPages) {
    const pagination = document.getElementById("pagination");
    pagination.innerHTML = "";
    
    if (totalPages <= 1) return;
    
    // Previous
    const prevBtn = document.createElement("button");
    prevBtn.className = "btn btn-sm btn-secondary";
    prevBtn.innerHTML = "&laquo;";
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => {
        if (currentPage > 1) {
            currentPage--;
            filterCustomers();
        }
    };
    pagination.appendChild(prevBtn);
    
    // Page numbers (simplified)
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            const btn = document.createElement("button");
            btn.className = `btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-secondary'}`;
            btn.textContent = i;
            btn.onclick = () => {
                currentPage = i;
                filterCustomers();
            };
            pagination.appendChild(btn);
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            const span = document.createElement("span");
            span.textContent = "...";
            span.style.padding = "5px";
            pagination.appendChild(span);
        }
    }
    
    // Next
    const nextBtn = document.createElement("button");
    nextBtn.className = "btn btn-sm btn-secondary";
    nextBtn.innerHTML = "&raquo;";
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => {
        if (currentPage < totalPages) {
            currentPage++;
            filterCustomers();
        }
    };
    pagination.appendChild(nextBtn);
}

function viewCustomer(email) {
    const customer = allCustomers.find(c => c.email === email);
    if (!customer) return;
    
    const modal = document.getElementById("customerModal");
    const content = document.getElementById("customerDetailsContent");
    
    // Format dates
    const lastVisit = customer.last_visit 
        ? new Date(customer.last_visit).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) 
        : 'Never';
        
    const joinedDate = customer.created_at 
        ? new Date(customer.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) 
        : 'Unknown';

    content.innerHTML = `
        <div style="display: flex; gap: 24px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; color: #666; margin: 0 auto 12px;">
                        ${customer.first_name.charAt(0)}${customer.last_name.charAt(0)}
                    </div>
                    <h2 style="margin: 0; font-size: 20px; color: #333;">${escapeHtml(customer.first_name)} ${escapeHtml(customer.last_name)}</h2>
                    <p style="color: #666; margin: 4px 0;">Customer since ${joinedDate}</p>
                </div>
                
                <div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0; margin-bottom: 12px; font-size: 14px; text-transform: uppercase; color: #666;">Contact Info</h4>
                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #999;">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <a href="mailto:${escapeHtml(customer.email)}" style="color: var(--primary-color);">${escapeHtml(customer.email)}</a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #999;">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        <a href="tel:${escapeHtml(customer.phone)}" style="color: var(--primary-color);">${escapeHtml(customer.phone)}</a>
                    </div>
                </div>
            </div>
            
            <div style="flex: 2; min-width: 300px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">${customer.total_bookings}</div>
                        <div style="font-size: 12px; color: #666;">Total Bookings</div>
                    </div>
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: #4CAF50;">$${parseFloat(customer.total_spent || 0).toFixed(2)}</div>
                        <div style="font-size: 12px; color: #666;">Total Spent</div>
                    </div>
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 14px; font-weight: 600; color: #333; margin-top: 4px;">${lastVisit}</div>
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">Last Visit</div>
                    </div>
                </div>
                
                <h4 style="margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Recent Bookings</h4>
                <div id="customerBookingsList">
                    <div style="text-align: center; padding: 20px; color: #999;">Loading bookings...</div>
                </div>
            </div>
        </div>
    `;
    
    modal.style.display = "block";
    
    // Fetch recent bookings for this customer
    fetch(`../../api/admin/bookings/list.php?customer_email=${encodeURIComponent(email)}`)
        .then(res => res.json())
        .then(data => {
            const bookingsList = document.getElementById("customerBookingsList");
            if (data.success && data.bookings.length > 0) {
                bookingsList.innerHTML = data.bookings.slice(0, 5).map(booking => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0;">
                        <div>
                            <div style="font-weight: 500; color: #333;">${new Date(booking.booking_date).toLocaleDateString()} at ${booking.start_time.substring(0, 5)}</div>
                            <div style="font-size: 13px; color: #666;">$${parseFloat(booking.total_price).toFixed(2)}</div>
                        </div>
                        <div>
                            <span class="status-badge status-${booking.status.toLowerCase()}">${booking.status}</span>
                        </div>
                    </div>
                `).join('');
            } else {
                bookingsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">No bookings found</div>';
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById("customerBookingsList").innerHTML = '<div style="text-align: center; color: #f44336;">Error loading bookings</div>';
        });
}

function closeCustomerModal() {
    document.getElementById("customerModal").style.display = "none";
}

function exportCustomers() {
    // Simple CSV export
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "First Name,Last Name,Email,Phone,Total Bookings,Total Spent,Last Visit\n";
    
    allCustomers.forEach(c => {
        const row = [
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.total_bookings,
            c.total_spent,
            c.last_visit || ''
        ].map(item => `"${item}"`).join(",");
        csvContent += row + "\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "customers_export.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast show ' + type;
    setTimeout(() => {
        toast.className = toast.className.replace('show', '');
    }, 3000);
}
