// Staff management JavaScript

let staffData = [];
let currentStaffEmail = null;
let csrfToken = null;
let staffModal = null;
let deleteModal = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  // Get CSRF token from session
  csrfToken = getCSRFToken();

  // Initialize Bootstrap modals
  staffModal = new bootstrap.Modal(document.getElementById("staffModal"), { backdrop: 'static' });
  deleteModal = new bootstrap.Modal(document.getElementById("deleteModal"));

  // Load staff data
  loadStaff();

  // Set up event listeners
  document.getElementById("searchInput").addEventListener("input", filterStaff);
  document.getElementById("roleFilter").addEventListener("change", filterStaff);
  document
    .getElementById("staffForm")
    .addEventListener("submit", handleFormSubmit);
});

/**
 * Get CSRF token from meta tag or generate one
 */
function getCSRFToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute("content") : "";
}

/**
 * Load staff data from API
 */
async function loadStaff() {
  try {
    const response = await fetch("../../api/admin/staff/list.php");
    const data = await response.json();

    if (data.success) {
      staffData = data.staff;
      displayStaff(staffData);
    } else {
      showError(
        "Failed to load staff: " + (data.error?.message || "Unknown error")
      );
    }
  } catch (error) {
    console.error("Error loading staff:", error);
    showError("Failed to load staff. Please try again.");
  }
}

/**
 * Display staff in table
 */
function displayStaff(staff) {
  const loadingState = document.getElementById("loadingState");
  const emptyState = document.getElementById("emptyState");
  const tableContainer = document.getElementById("tableContainer");
  const tbody = document.getElementById("staffTableBody");

  loadingState.style.display = "none";

  if (staff.length === 0) {
    emptyState.style.display = "block";
    tableContainer.style.display = "none";
    return;
  }

  emptyState.style.display = "none";
  tableContainer.style.display = "block";

  tbody.innerHTML = staff
    .map((member) => {
      const initials = (
        member.first_name.charAt(0) + member.last_name.charAt(0)
      ).toUpperCase();
      
      // Format join date
      const joinDate = new Date(member.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
      }).split('/').join('-');
      const [month, day, year] = joinDate.split('-');
      const formattedDate = `${year}-${month}-${day}`;

      return `
            <tr>
                <td>
                    <div class="staff-cell">
                        <div class="staff-avatar">${initials}</div>
                        <div class="staff-info">
                            <div class="staff-name">${escapeHtml(member.first_name)} ${escapeHtml(member.last_name)}</div>
                            <div class="staff-bio">${escapeHtml(member.bio || '')}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="contact-info">
                        <div class="contact-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            ${escapeHtml(member.staff_email)}
                        </div>
                        <div class="contact-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            ${escapeHtml(member.phone)}
                        </div>
                    </div>
                </td>
                <td>
                    <span class="role-badge role-${member.role.toLowerCase()}">${escapeHtml(capitalizeFirst(member.role))}</span>
                </td>
                <td>
                    <span class="status-badge ${member.is_active ? 'active' : 'inactive'}">
                        <span class="status-dot"></span>
                        ${member.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>${formattedDate}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon btn-view" onclick="viewStaff('${escapeHtml(member.staff_email)}')" title="View" type="button">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                        <button class="btn-icon btn-edit" onclick="openEditModal('${escapeHtml(member.staff_email)}')" title="Edit" type="button">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button class="btn-icon btn-delete" onclick="openDeleteModal('${escapeHtml(member.staff_email)}')" title="Delete" type="button">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    })
    .join("");
}

/**
 * Filter staff based on search and filters
 */
function filterStaff() {
  const searchTerm = document.getElementById("searchInput").value.toLowerCase();
  const roleFilter = document.getElementById("roleFilter").value;
  const activeOnly = document.getElementById("activeOnlyFilter").checked;

  const filtered = staffData.filter((member) => {
    const matchesSearch =
      member.first_name.toLowerCase().includes(searchTerm) ||
      member.last_name.toLowerCase().includes(searchTerm) ||
      member.staff_email.toLowerCase().includes(searchTerm) ||
      member.phone.includes(searchTerm);

    const matchesRole = !roleFilter || member.role === roleFilter;
    const matchesActive = !activeOnly || member.is_active;

    return matchesSearch && matchesRole && matchesActive;
  });

  displayStaff(filtered);
}

/**
 * Open create modal
 */
function openCreateModal() {
  document.getElementById("modalTitle").textContent = "Add Staff";
  document.getElementById("isEdit").value = "0";
  document.getElementById("staffForm").reset();
  document.getElementById("staffEmail").readOnly = false;
  document.getElementById("password").required = true;
  document.getElementById("password").placeholder = "Enter strong password";
  document.getElementById("passwordRequired").textContent = "*";
  document.getElementById("passwordHint").textContent = "Min 8 chars, 1 uppercase, 1 number, 1 special character";
  clearErrors();
  staffModal.show();
}

/**
 * Open edit modal
 */
function openEditModal(staffEmail) {
  const member = staffData.find((s) => s.staff_email === staffEmail);
  if (!member) return;

  document.getElementById("modalTitle").textContent = "Edit Staff";
  document.getElementById("isEdit").value = "1";
  document.getElementById("staffEmail").value = member.staff_email;
  document.getElementById("staffEmail").readOnly = true;
  document.getElementById("phone").value = member.phone;
  document.getElementById("firstName").value = member.first_name;
  document.getElementById("lastName").value = member.last_name;
  document.getElementById("role").value = member.role;
  document.getElementById("bio").value = member.bio || "";
  document.getElementById("staffImage").value = member.staff_image || "";

  // Make password optional for edit
  document.getElementById("password").required = false;
  document.getElementById("password").value = "";
  document.getElementById("password").placeholder = "Leave blank to keep current password";
  document.getElementById("passwordRequired").textContent = "";
  document.getElementById("passwordHint").textContent = "Leave blank to keep current password, or enter new password (min 8 chars)";

  clearErrors();
  staffModal.show();
}

/**
 * Close staff modal
 */
function closeStaffModal() {
  staffModal.hide();
  document.getElementById("staffForm").reset();
  clearErrors();
}

/**
 * Handle form submission
 */
async function handleFormSubmit(e) {
  e.preventDefault();
  clearErrors();

  const isEdit = document.getElementById("isEdit").value === "1";
  const formData = {
    csrf_token: csrfToken,
    staff_email: document.getElementById("staffEmail").value.trim(),
    phone: document.getElementById("phone").value.trim(),
    first_name: document.getElementById("firstName").value.trim(),
    last_name: document.getElementById("lastName").value.trim(),
    role: document.getElementById("role").value,
    bio: document.getElementById("bio").value.trim(),
    staff_image: document.getElementById("staffImage").value.trim(),
  };

  // Add password if provided
  const password = document.getElementById("password").value;
  if (password) {
    formData.password = password;
  }

  const url = isEdit
    ? "../../api/admin/staff/update.php"
    : "../../api/admin/staff/create.php";

  const method = isEdit ? "PUT" : "POST";

  try {
    const response = await fetch(url, {
      method: method,
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (data.success) {
      showSuccess(data.message);
      closeStaffModal();
      loadStaff();
    } else {
      if (data.error?.details) {
        displayErrors(data.error.details);
      } else {
        showError(data.error?.message || "Failed to save staff");
      }
    }
  } catch (error) {
    console.error("Error saving staff:", error);
    showError("Failed to save staff. Please try again.");
  }
}

/**
 * Toggle staff active status
 */
async function toggleActive(staffEmail, isActive) {
  try {
    const response = await fetch("../../api/admin/staff/toggle_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        csrf_token: csrfToken,
        staff_email: staffEmail,
      }),
    });

    const data = await response.json();

    if (data.success) {
      showSuccess(data.message || 'Status updated successfully');
      loadStaff();
    } else {
      showError(data.message || "Failed to toggle status");
      // Reload to reset toggle
      loadStaff();
    }
  } catch (error) {
    console.error("Error toggling status:", error);
    showError("Failed to toggle status. Please try again.");
    loadStaff();
  }
}
  } catch (error) {
    console.error("Error toggling status:", error);
    showError("Failed to toggle status. Please try again.");
    loadStaff();
  }
}

/**
 * Open delete confirmation modal
 */
function openDeleteModal(staffEmail) {
  currentStaffEmail = staffEmail;
  const member = staffData.find((s) => s.staff_email === staffEmail);

  if (member) {
    document.getElementById("deleteStaffName").textContent = `${member.first_name} ${member.last_name}`;
  }

  document.getElementById("deleteModal").style.display = "flex";
}

/**
 * View staff details (placeholder function)
 */
function viewStaff(staffEmail) {
  const member = staffData.find((s) => s.staff_email === staffEmail);
  if (member) {
    // For now, just open edit modal
    // You can create a separate view modal later
    openEditModal(staffEmail);
  }
}

/**
 * Capitalize first letter of a string
 */
function capitalizeFirst(str) {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

/**
 * Close delete modal
 */
function closeDeleteModal() {
  deleteModal.hide();
  currentStaffEmail = null;
}

/**
 * Open delete modal
 */
function openDeleteModal(staffEmail) {
  const member = staffData.find((s) => s.staff_email === staffEmail);
  if (!member) return;

  currentStaffEmail = staffEmail;
  document.getElementById("deleteStaffName").textContent = 
    `${escapeHtml(member.first_name)} ${escapeHtml(member.last_name)}`;
  deleteModal.show();
}

/**
 * Confirm delete
 */
async function confirmDelete() {
  if (!currentStaffEmail) return;

  try {
    const response = await fetch("../../api/admin/staff/delete.php", {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        csrf_token: csrfToken,
        staff_email: currentStaffEmail,
      }),
    });

    const data = await response.json();

    if (data.success) {
      showSuccess(data.message);
      closeDeleteModal();
      loadStaff();
    } else {
      showError(data.error?.message || "Failed to delete staff");
      closeDeleteModal();
      }
    }
  } catch (error) {
    console.error("Error deleting staff:", error);
    showError("Failed to delete staff. Please try again.");
    closeDeleteModal();
  }
}

/**
 * Display validation errors
 */
function displayErrors(errors) {
  for (const [field, message] of Object.entries(errors)) {
    const errorElement = document.getElementById(`error-${field}`);
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.display = "block";
    }
  }
}

/**
 * Clear all error messages
 */
function clearErrors() {
  const errorElements = document.querySelectorAll(".error-message");
  errorElements.forEach((el) => {
    el.textContent = "";
    el.style.display = "none";
  });
}

/**
 * Show success message
 */
function showSuccess(message) {
  const toast = document.getElementById("toast");
  if (!toast) return;
  
  toast.textContent = message;
  toast.className = "toast show success";
  setTimeout(() => {
    toast.classList.remove("show");
  }, 3000);
}

/**
 * Show error message
 */
function showError(message) {
  const toast = document.getElementById("toast");
  if (!toast) return;
  
  toast.textContent = message;
  toast.className = "toast show error";
  setTimeout(() => {
    toast.classList.remove("show");
  }, 4000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Toggle password visibility
 */
function togglePasswordVisibility() {
  const passwordInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eyeIcon');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    eyeIcon.innerHTML = `
      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
      <line x1="1" y1="1" x2="23" y2="23"></line>
    `;
  } else {
    passwordInput.type = 'password';
    eyeIcon.innerHTML = `
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
      <circle cx="12" cy="12" r="3"></circle>
    `;
  }
}
