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
        return (a.last_name + a.first_name).localeCompare(
          b.last_name + b.first_name
        );
      case "name_desc":
        return (b.last_name + b.first_name).localeCompare(
          a.last_name + a.first_name
        );
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
                ${escapeHtml(customer.phone)}
            </td>
            <td style="color: #666; font-size: 14px;">${dateRegistered}</td>
            <td>
                <div style="display: flex; gap: 8px;">
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
  const customer = allCustomers.find((c) => c.email === email);
  if (!customer) return;

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
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; color: #666; margin: 0 auto 12px;">
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
                          customer.email
                        )}" style="color: var(--primary-color);">${escapeHtml(
    customer.email
  )}</a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #999;">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        <a href="tel:${escapeHtml(
                          customer.phone
                        )}" style="color: var(--primary-color);">${escapeHtml(
    customer.phone
  )}</a>
                    </div>
                </div>
            </div>
            
            <div style="flex: 2; min-width: 300px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">${
                          customer.total_bookings
                        }</div>
                        <div style="font-size: 12px; color: #666;">Total Bookings</div>
                    </div>
                    <div style="background: white; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: #4CAF50;">$${parseFloat(
                          customer.total_spent || 0
                        ).toFixed(2)}</div>
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

  // Show modal with flex for proper centering
  modal.style.display = "flex";

  // Fetch recent bookings for this customer
  fetch(
    `../../api/admin/bookings/list.php?customer_email=${encodeURIComponent(
      email
    )}`
  )
    .then((res) => res.json())
    .then((data) => {
      const bookingsList = document.getElementById("customerBookingsList");
      if (data.success && data.bookings.length > 0) {
        bookingsList.innerHTML = data.bookings
          .slice(0, 5)
          .map(
            (booking) => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0;">
                        <div>
                            <div style="font-weight: 500; color: #333;">${new Date(
                              booking.booking_date
                            ).toLocaleDateString()} at ${booking.start_time.substring(
              0,
              5
            )}</div>
                            <div style="font-size: 13px; color: #666;">$${parseFloat(
                              booking.total_price
                            ).toFixed(2)}</div>
                        </div>
                        <div>
                            <span class="status-badge status-${booking.status.toLowerCase()}">${
              booking.status
            }</span>
                        </div>
                    </div>
                `
          )
          .join("");
      } else {
        bookingsList.innerHTML =
          '<div style="text-align: center; padding: 20px; color: #999;">No bookings found</div>';
      }
    })
    .catch((err) => {
      console.error(err);
      document.getElementById("customerBookingsList").innerHTML =
        '<div style="text-align: center; color: #f44336;">Error loading bookings</div>';
    });
}

function closeCustomerModal() {
  document.getElementById("customerModal").style.display = "none";
}

function openEditModal(email) {
  const customer = allCustomers.find(
    (c) => (c.email || c.customer_email) === email
  );
  if (!customer) {
    showToast("Customer not found", "error");
    return;
  }

  // Populate form
  document.getElementById("edit_customer_email").value =
    customer.email || customer.customer_email;
  document.getElementById("edit_first_name").value = customer.first_name;
  document.getElementById("edit_last_name").value = customer.last_name;
  document.getElementById("edit_phone").value = customer.phone;
  document.getElementById("reset_password_checkbox").checked = false;
  document.getElementById("password_group").style.display = "none";
  document.getElementById("edit_password").value = "";
  document.getElementById("edit_password").removeAttribute("required");

  // Show modal with flex for proper centering
  document.getElementById("editModal").style.display = "flex";
}

function closeEditModal() {
  document.getElementById("editModal").style.display = "none";
  document.getElementById("editForm").reset();
}

function togglePasswordReset() {
  const checkbox = document.getElementById("reset_password_checkbox");
  const passwordGroup = document.getElementById("password_group");
  const passwordInput = document.getElementById("edit_password");

  if (checkbox.checked) {
    passwordGroup.style.display = "block";
    passwordInput.setAttribute("required", "required");
  } else {
    passwordGroup.style.display = "none";
    passwordInput.removeAttribute("required");
    passwordInput.value = "";
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

  // Include password if reset checkbox is checked
  if (document.getElementById("reset_password_checkbox").checked) {
    const password = formData.get("password");
    if (password) {
      data.password = password;
    }
  }

  // Add CSRF token
  data.csrf_token = CSRF_TOKEN;

  // Show loading
  Swal.fire({
    title: "Saving...",
    text: "Please wait",
    allowOutsideClick: false,
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
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        Swal.fire({
          icon: "success",
          title: "Success",
          text: result.message || "Customer updated successfully",
          confirmButtonColor: "#c29076",
        }).then(() => {
          closeEditModal();
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
        text: "An error occurred while updating the customer",
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

function showToast(message, type = "success") {
  const toast = document.getElementById("toast");
  toast.textContent = message;
  toast.className = "toast show " + type;
  setTimeout(() => {
    toast.className = toast.className.replace("show", "");
  }, 3000);
}
