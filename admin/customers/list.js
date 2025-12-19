// Customer Management JavaScript

let allCustomers = [];
let currentPage = 1;
const itemsPerPage = 10;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  loadCustomers();
  setupEventListeners();
  checkSessionStatus();
});

function checkSessionStatus() {
  const statusElement = document.querySelector("[data-session-status]");
  if (statusElement) {
    const type = statusElement.dataset.type;
    const message = statusElement.dataset.message;

    Swal.fire({
      icon: type,
      title: type === "success" ? "Success" : "Error",
      text: message,
      confirmButtonColor: "#c29076",
    });
  }
}

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

  const editModal = document.getElementById("editModal");
  if (editModal) {
    editModal.addEventListener("click", function (e) {
      if (e.target === editModal) {
        closeEditModal();
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

  console.log("Fetching customers from API...");

  fetch("../../api/admin/customers/list.php", { credentials: "same-origin" })
    .then((response) => {
      console.log("Response status:", response.status);
      console.log("Response ok:", response.ok);

      // Clone response to read it twice (once for logging, once for JSON parsing)
      return response
        .clone()
        .text()
        .then((text) => {
          console.log("Raw response:", text);
          try {
            const data = JSON.parse(text);
            console.log("Parsed response:", data);
            return { ok: response.ok, status: response.status, data: data };
          } catch (e) {
            console.error("JSON parse error:", e);
            throw new Error("Invalid JSON response");
          }
        });
    })
    .then((result) => {
      if (!result.ok) {
        console.error("API returned error status:", result.status);
        if (result.data && result.data.error) {
          console.error("Error details:", result.data.error);
          throw new Error(result.data.error.message || "API request failed");
        }
        throw new Error("Network response was not ok");
      }

      if (result.data.success) {
        console.log("Successfully loaded", result.data.count, "customers");
        allCustomers = result.data.customers;
        filterCustomers();
      } else {
        console.error("API returned success=false:", result.data.error);
        showToast(result.data.error || "Failed to load customers", "error");
      }
    })
    .catch((error) => {
      console.error("Error loading customers:", error);
      showToast("Error loading customers: " + error.message, "error");
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

  let filtered = allCustomers.filter((customer) => {
    const email = customer.email || customer.customer_email || "";
    const searchString =
      `${customer.first_name} ${customer.last_name} ${email} ${customer.phone}`.toLowerCase();
    return searchString.includes(searchTerm);
  });

  // Sort
  filtered.sort((a, b) => {
    switch (sortValue) {
      case "name_asc":
        // Sort by first name ascending (A-Z)
        const nameA = ((a.first_name || "") + " " + (a.last_name || "")).trim().toLowerCase();
        const nameB = ((b.first_name || "") + " " + (b.last_name || "")).trim().toLowerCase();
        return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
      case "name_desc":
        // Sort by first name descending (Z-A)
        const nameA_desc = ((a.first_name || "") + " " + (a.last_name || "")).trim().toLowerCase();
        const nameB_desc = ((b.first_name || "") + " " + (b.last_name || "")).trim().toLowerCase();
        return nameB_desc.localeCompare(nameA_desc, undefined, { sensitivity: 'base' });
      case "bookings_desc":
        return (b.total_bookings || 0) - (a.total_bookings || 0);
      case "recent_desc":
        const dateA = a.created_at ? new Date(a.created_at) : new Date(0);
        const dateB = b.created_at ? new Date(b.created_at) : new Date(0);
        return dateB - dateA;
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

  pageItems.forEach((customer) => {
    const tr = document.createElement("tr");

    const dateRegistered = customer.created_at
      ? new Date(customer.created_at).toLocaleDateString("en-US", {
          year: "numeric",
          month: "short",
          day: "numeric",
        })
      : "N/A";

    tr.innerHTML = `
            <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #c29076, #b18776); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white;">
                        ${customer.first_name.charAt(
                          0
                        )}${customer.last_name.charAt(0)}
                    </div>
                    <div>
                        <div style="font-weight: 500; color: #333;">${escapeHtml(
                          customer.first_name
                        )} ${escapeHtml(customer.last_name)}</div>
                    </div>
                </div>
            </td>
            <td style="color: #666;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px; color: #999;">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                ${escapeHtml(customer.email || customer.customer_email)}
            </td>
            <td style="color: #666;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px; color: #999;">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                ${escapeHtml(formatPhoneNumber(customer.phone))}
            </td>
            <td style="color: #666; font-size: 14px;">${dateRegistered}</td>
            <td>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-sm btn-info" onclick="viewCustomer('${escapeHtml(
                      customer.email || customer.customer_email
                    )}')" title="View Customer Details">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="openEditModal('${escapeHtml(
                      customer.email || customer.customer_email
                    )}')" title="Edit Customer">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCustomer('${escapeHtml(
                      customer.email || customer.customer_email
                    )}', '${escapeHtml(customer.first_name)} ${escapeHtml(
      customer.last_name
    )}')" title="Delete Customer">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
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
    if (
      i === 1 ||
      i === totalPages ||
      (i >= currentPage - 1 && i <= currentPage + 1)
    ) {
      const btn = document.createElement("button");
      btn.className = `btn btn-sm ${
        i === currentPage ? "btn-primary" : "btn-secondary"
      }`;
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
  const customer = allCustomers.find(
    (c) => (c.email || c.customer_email) === email
  );
  if (!customer) {
    showToast("Customer not found", "error");
    return;
  }

  const modal = document.getElementById("customerModal");
  const content = document.getElementById("customerDetailsContent");

  // Format dates
  const lastVisit = customer.last_visit
    ? new Date(customer.last_visit).toLocaleDateString("en-US", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      })
    : "Never";

  const joinedDate = customer.created_at
    ? new Date(customer.created_at).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
      })
    : "Unknown";

  content.innerHTML = `
        <div style="display: flex; gap: 24px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #c29076, #b18776); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; color: white; margin: 0 auto 12px;">
                        ${customer.first_name.charAt(
                          0
                        )}${customer.last_name.charAt(0)}
                    </div>
                    <h2 style="margin: 0; font-size: 20px; color: #333;">${escapeHtml(
                      customer.first_name
                    )} ${escapeHtml(customer.last_name)}</h2>
                    <p style="color: #666; margin: 4px 0;">Customer since ${joinedDate}</p>
                </div>
                
                <div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0; margin-bottom: 12px; font-size: 14px; text-transform: uppercase; color: #666;">Contact Info</h4>
                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #999;">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <a href="mailto:${escapeHtml(
                          customer.email || customer.customer_email
                        )}" style="color: var(--primary-color);">${escapeHtml(
    customer.email || customer.customer_email
  )}</a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #999;">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        <a href="tel:${escapeHtml(
                          customer.phone.replace(/\s+/g, "")
                        )}" style="color: var(--primary-color);">${escapeHtml(
    formatPhoneNumber(customer.phone)
  )}</a>
                    </div>
                </div>
            </div>
            
            <div style="flex: 2; min-width: 300px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">${
                          customer.total_bookings || 0
                        }</div>
                        <div style="font-size: 12px; color: #666;">Total Bookings</div>
                    </div>
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: #4CAF50;">RM ${parseFloat(
                          customer.total_spent || 0
                        ).toFixed(2)}</div>
                        <div style="font-size: 12px; color: #666;">Total Spent</div>
                    </div>
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 14px; font-weight: 600; color: #333; margin-top: 4px;">${lastVisit}</div>
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">Last Visit</div>
                    </div>
                </div>
                
                <h4 style="margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Booking History</h4>
                <div id="customerBookingsList" style="max-height: 400px; overflow-y: auto;">
                    <div style="text-align: center; padding: 20px; color: #999;">Loading bookings...</div>
                </div>
            </div>
        </div>
    `;

  // Show modal with flex for proper centering
  modal.style.display = "flex";
  modal.classList.add("active");

  // Fetch ALL bookings for this customer (not just 5)
  fetch(
    `../../api/admin/bookings/list.php?customer_email=${encodeURIComponent(
      email
    )}`
  )
    .then((res) => res.json())
    .then((data) => {
      const bookingsList = document.getElementById("customerBookingsList");
      if (data.success && data.bookings && data.bookings.length > 0) {
        // Show all bookings in a table format
        bookingsList.innerHTML = `
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #f9fafb; border-bottom: 2px solid #eee;">
                <th style="padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #666; font-weight: 600;">Date & Time</th>
                <th style="padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #666; font-weight: 600;">Services</th>
                <th style="padding: 12px; text-align: right; font-size: 12px; text-transform: uppercase; color: #666; font-weight: 600;">Amount</th>
                <th style="padding: 12px; text-align: center; font-size: 12px; text-transform: uppercase; color: #666; font-weight: 600;">Status</th>
              </tr>
            </thead>
            <tbody>
              ${data.bookings
                .map(
                  (booking) => `
                <tr style="border-bottom: 1px solid #f0f0f0;">
                  <td style="padding: 12px;">
                    <div style="font-weight: 500; color: #333;">${new Date(
                      booking.booking_date
                    ).toLocaleDateString("en-US", {
                      year: "numeric",
                      month: "short",
                      day: "numeric",
                    })}</div>
                    <div style="font-size: 13px; color: #666;">${booking.start_time.substring(
                      0,
                      5
                    )} - ${booking.expected_finish_time || booking.end_time || "N/A"}</div>
                  </td>
                  <td style="padding: 12px; color: #666; font-size: 14px;">
                    ${booking.services && booking.services.length > 0
                      ? booking.services.map((s) => escapeHtml(s.name || s.service_name)).join(", ")
                      : "N/A"}
                  </td>
                  <td style="padding: 12px; text-align: right; font-weight: 600; color: #333;">
                    RM ${parseFloat(booking.total_price || 0).toFixed(2)}
                  </td>
                  <td style="padding: 12px; text-align: center;">
                    <span class="status-badge status-${booking.status.toLowerCase()}" style="padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                      ${escapeHtml(booking.status)}
                    </span>
                  </td>
                </tr>
              `
                )
                .join("")}
            </tbody>
          </table>
        `;
      } else {
        bookingsList.innerHTML =
          '<div style="text-align: center; padding: 40px; color: #999;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 12px; opacity: 0.3;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><line x1="7" y1="3" x2="7" y2="8" x3="17" y3="8"></line></svg><p>No bookings found</p></div>';
      }
    })
    .catch((err) => {
      console.error(err);
      document.getElementById("customerBookingsList").innerHTML =
        '<div style="text-align: center; padding: 20px; color: #f44336;">Error loading bookings</div>';
    });
}

function closeCustomerModal() {
  const customerModal = document.getElementById("customerModal");
  if (customerModal) {
    customerModal.style.display = "none";
    customerModal.classList.remove("active");
  }
}

function openEditModal(email) {
  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:503',message:'openEditModal called',data:{email:email,allCustomersLength:allCustomers.length},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
  // #endregion
  
  const customer = allCustomers.find(
    (c) => (c.email || c.customer_email) === email
  );
  
  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:510',message:'Customer lookup result',data:{customerFound:!!customer,customerEmail:customer?(customer.email||customer.customer_email):null},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
  // #endregion
  
  if (!customer) {
    showToast("Customer not found", "error");
    return;
  }

  // Get form elements
  const editEmailInput = document.getElementById("edit_customer_email");
  const editFirstNameInput = document.getElementById("edit_first_name");
  const editLastNameInput = document.getElementById("edit_last_name");
  const editPhoneInput = document.getElementById("edit_phone");
  const editModal = document.getElementById("editModal");

  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:525',message:'Element lookup results',data:{editEmailInput:!!editEmailInput,editFirstNameInput:!!editFirstNameInput,editLastNameInput:!!editLastNameInput,editPhoneInput:!!editPhoneInput,editModal:!!editModal},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'D'})}).catch(()=>{});
  // #endregion

  // Check if elements exist
  if (!editEmailInput || !editFirstNameInput || !editLastNameInput || !editPhoneInput || !editModal) {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:530',message:'Elements missing error',data:{missingElements:{email:!editEmailInput,firstName:!editFirstNameInput,lastName:!editLastNameInput,phone:!editPhoneInput,modal:!editModal}},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'D'})}).catch(()=>{});
    // #endregion
    console.error("Edit modal elements not found");
    showToast("Error: Edit form elements not found", "error");
    return;
  }

  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:536',message:'Before setting values',data:{customerData:{email:customer.email||customer.customer_email,firstName:customer.first_name,lastName:customer.last_name,phone:customer.phone}},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
  // #endregion

  // Populate form
  try {
    editEmailInput.value = customer.email || customer.customer_email;
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:542',message:'After setting email',data:{success:true},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion
  } catch(e) {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:545',message:'Error setting email',data:{error:e.message,stack:e.stack},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion
    throw e;
  }
  
  try {
    editFirstNameInput.value = customer.first_name || "";
    editLastNameInput.value = customer.last_name || "";
    editPhoneInput.value = customer.phone || "";
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:554',message:'After setting all input values',data:{success:true},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion
  } catch(e) {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:557',message:'Error setting input values',data:{error:e.message,stack:e.stack},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion
    throw e;
  }

  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:562',message:'Before showing modal',data:{editModalExists:!!editModal},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
  // #endregion

  // Show modal with flex for proper centering
  try {
    editModal.style.display = "flex";
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:567',message:'After setting display flex',data:{success:true},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion
    
    // Also add active class for CSS compatibility
    editModal.classList.add("active");
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:571',message:'After adding active class',data:{success:true},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion
  } catch(e) {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:574',message:'Error showing modal',data:{error:e.message,stack:e.stack},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
    // #endregion
    throw e;
  }
  
  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'list.js:579',message:'openEditModal completed successfully',data:{success:true},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
  // #endregion
}

function closeEditModal() {
  const editModal = document.getElementById("editModal");
  const editForm = document.getElementById("editForm");
  
  if (editModal) {
    editModal.style.display = "none";
    editModal.classList.remove("active");
  }
  
  if (editForm) {
    editForm.reset();
  }
}


function saveCustomer(event) {
  event.preventDefault();

  const formData = new FormData(event.target);
  const data = {
    customer_email: formData.get("customer_email"),
    first_name: formData.get("first_name"),
    last_name: formData.get("last_name"),
    phone: formData.get("phone"),
  };

  // Add CSRF token
  data.csrf_token = CSRF_TOKEN;

  // Close the edit modal immediately before showing loading
  closeEditModal();

  // Show loading
  Swal.fire({
    title: "Saving...",
    text: "Please wait",
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  fetch("../../api/admin/customers/update.php", {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
    },
    credentials: "same-origin",
    body: JSON.stringify(data),
  })
    .then((response) => {
      if (!response.ok) {
        return response.json().then(err => {
          throw new Error(err.error?.message || `HTTP error! status: ${response.status}`);
        });
      }
      return response.json();
    })
    .then((result) => {
      if (result.success) {
        Swal.fire({
          icon: "success",
          title: "Success",
          text: result.message || "Customer updated successfully",
          confirmButtonColor: "#c29076",
          timer: 2000,
          timerProgressBar: true,
        }).then(() => {
          loadCustomers(); // Reload the table
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: result.error?.message || "Failed to update customer",
          confirmButtonColor: "#c29076",
        });
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      Swal.fire({
        icon: "error",
        title: "Error",
        text: error.message || "An error occurred while updating the customer",
        confirmButtonColor: "#c29076",
      });
    });
}

function deleteCustomer(email, fullName) {
  Swal.fire({
    title: "Delete Customer?",
    html: `Are you sure you want to permanently delete <strong>${fullName}</strong>?<br><br>This action cannot be undone.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Yes, delete",
    cancelButtonText: "Cancel",
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading
      Swal.fire({
        title: "Deleting...",
        text: "Please wait",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      fetch("../../api/admin/customers/delete.php", {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          customer_email: email,
          csrf_token: CSRF_TOKEN,
        }),
      })
        .then((response) => response.json())
        .then((result) => {
          if (result.success) {
            Swal.fire({
              icon: "success",
              title: "Deleted",
              text: result.message || "Customer deleted successfully",
              confirmButtonColor: "#c29076",
            }).then(() => {
              loadCustomers(); // Reload the table
            });
          } else {
            // Check if error is due to existing bookings
            if (result.error?.code === "HAS_BOOKINGS") {
              Swal.fire({
                icon: "info",
                title: "Cannot Delete",
                text: result.error.message,
                confirmButtonColor: "#c29076",
              });
            } else {
              Swal.fire({
                icon: "error",
                title: "Error",
                text: result.error?.message || "Failed to delete customer",
                confirmButtonColor: "#c29076",
              });
            }
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "An error occurred while deleting the customer",
            confirmButtonColor: "#c29076",
          });
        });
    }
  });
}

function exportCustomers() {
  // Simple CSV export
  let csvContent = "data:text/csv;charset=utf-8,";
  csvContent += "First Name,Last Name,Email,Phone,Date Registered\n";

  allCustomers.forEach((c) => {
    const row = [
      c.first_name,
      c.last_name,
      c.email || c.customer_email,
      c.phone,
      c.created_at || "",
    ]
      .map((item) => `"${item}"`)
      .join(",");
    csvContent += row + "\n";
  });

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute(
    "download",
    `customers_export_${new Date().toISOString().split("T")[0]}.csv`
  );
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showToast("Customer list exported successfully", "success");
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
  if (!text) return "";
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatPhoneNumber(phone) {
  if (!phone) return "";
  
  // Remove all spaces and keep only digits and +
  let cleaned = phone.replace(/\s+/g, "");
  
  // Format Malaysian phone numbers (+60)
  if (cleaned.startsWith("+60")) {
    // Remove +60 to get the number part
    let numberPart = cleaned.substring(3);
    
    // Format as: +60 XX XXX XXXX
    if (numberPart.length >= 9) {
      // Take first 2 digits, next 3 digits, and remaining digits
      let part1 = numberPart.substring(0, 2);
      let part2 = numberPart.substring(2, 5);
      let part3 = numberPart.substring(5);
      
      return `+60 ${part1} ${part2} ${part3}`;
    } else if (numberPart.length >= 6) {
      // For shorter numbers: +60 XX XXX
      let part1 = numberPart.substring(0, 2);
      let part2 = numberPart.substring(2);
      return `+60 ${part1} ${part2}`;
    } else {
      // For very short numbers: +60 XX
      return `+60 ${numberPart}`;
    }
  }
  
  // If it doesn't start with +60, return as is (or format if it's a local number)
  // Handle local numbers that might start with 0
  if (cleaned.startsWith("0")) {
    let numberPart = cleaned.substring(1);
    if (numberPart.length >= 9) {
      let part1 = numberPart.substring(0, 2);
      let part2 = numberPart.substring(2, 5);
      let part3 = numberPart.substring(5);
      return `+60 ${part1} ${part2} ${part3}`;
    }
  }
  
  // Return original if we can't format it
  return phone;
}

function showToast(message, type = "success") {
  const toast = document.getElementById("toast");
  toast.textContent = message;
  toast.className = "toast show " + type;
  setTimeout(() => {
    toast.className = toast.className.replace("show", "");
  }, 3000);
}
