// Staff management JavaScript

let staffData = [];
let currentStaffEmail = null;
let csrfToken = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  // Get CSRF token from session
  csrfToken = getCSRFToken();

  // Load staff data
  loadStaff();

  // Set up event listeners
  document.getElementById("searchInput").addEventListener("input", filterStaff);
  document.getElementById("roleFilter").addEventListener("change", filterStaff);
  document
    .getElementById("activeOnlyFilter")
    .addEventListener("change", filterStaff);
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
  const table = document.getElementById("staffTable");
  const tbody = document.getElementById("staffTableBody");

  loadingState.style.display = "none";

  if (staff.length === 0) {
    emptyState.style.display = "block";
    table.style.display = "none";
    return;
  }

  emptyState.style.display = "none";
  table.style.display = "table";

  tbody.innerHTML = staff
    .map((member) => {
      const initials = (
        member.first_name.charAt(0) + member.last_name.charAt(0)
      ).toUpperCase();
      const imageHtml = member.staff_image
        ? `<img src="../../images/${member.staff_image}" alt="${member.first_name}" class="staff-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
               <div class="staff-image-placeholder" style="display: none;">${initials}</div>`
        : `<div class="staff-image-placeholder">${initials}</div>`;

      return `
            <tr>
                <td data-label="Staff Member">
                    <div class="staff-profile">
                        ${imageHtml}
                        <div class="staff-info">
                            <div class="staff-name">${escapeHtml(
                              member.first_name
                            )} ${escapeHtml(member.last_name)}</div>
                            <div class="staff-email">${escapeHtml(
                              member.staff_email
                            )}</div>
                        </div>
                    </div>
                </td>
                <td data-label="Phone">${escapeHtml(member.phone)}</td>
                <td data-label="Role"><span class="role-badge">${escapeHtml(
                  member.role
                )}</span></td>
                <td data-label="Status">
                    <span class="status-badge ${
                      member.is_active ? "status-active" : "status-inactive"
                    }">
                        ${member.is_active ? "Active" : "Inactive"}
                    </span>
                </td>
                <td data-label="Active">
                    <label class="toggle-switch">
                        <input type="checkbox" ${
                          member.is_active ? "checked" : ""
                        } 
                               onchange="toggleActive('${escapeHtml(
                                 member.staff_email
                               )}', this.checked)"
                               aria-label="Toggle staff active status">
                        <span class="toggle-slider"></span>
                    </label>
                </td>
                <td data-label="Actions">
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="openEditModal('${escapeHtml(
                          member.staff_email
                        )}')" title="Edit" aria-label="Edit staff member">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button class="btn-icon" onclick="openDeleteModal('${escapeHtml(
                          member.staff_email
                        )}')" title="Delete" aria-label="Delete staff member">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d32f2f" stroke-width="2">
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
  document.getElementById("passwordGroup").style.display = "block";
  document.getElementById("password").required = true;
  clearErrors();
  document.getElementById("staffModal").style.display = "flex";
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
  document.getElementById("passwordGroup").style.display = "block";
  document.getElementById("password").required = false;
  document.getElementById("password").value = "";
  const passwordLabel = document.querySelector('label[for="password"]');
  passwordLabel.textContent = "Password (leave blank to keep current)";

  clearErrors();
  document.getElementById("staffModal").style.display = "flex";
}

/**
 * Close staff modal
 */
function closeStaffModal() {
  document.getElementById("staffModal").style.display = "none";
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
    const response = await fetch("../../api/admin/staff/toggle_active.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        csrf_token: csrfToken,
        staff_email: staffEmail,
        is_active: isActive,
      }),
    });

    const data = await response.json();

    if (data.success) {
      showSuccess(data.message);
      loadStaff();
    } else {
      if (data.warning && data.error?.code === "HAS_FUTURE_BOOKINGS") {
        showError(
          data.error.message +
            " (" +
            data.error.details.future_bookings +
            " bookings)"
        );
      } else {
        showError(data.error?.message || "Failed to toggle status");
      }
      // Reload to reset toggle
      loadStaff();
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
    document.getElementById(
      "deleteMessage"
    ).textContent = `Are you sure you want to delete ${member.first_name} ${member.last_name}?`;
  }

  document.getElementById("deleteWarning").style.display = "none";
  document.getElementById("deleteModal").style.display = "flex";
}

/**
 * Close delete modal
 */
function closeDeleteModal() {
  document.getElementById("deleteModal").style.display = "none";
  currentStaffEmail = null;
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
      if (data.warning && data.error?.code === "HAS_FUTURE_BOOKINGS") {
        document.getElementById("deleteWarning").style.display = "block";
        document.getElementById(
          "deleteWarning"
        ).innerHTML = `<strong>Warning:</strong> This staff member has ${data.error.details.future_bookings} future booking(s).`;
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
  // Use existing notification system if available, otherwise alert
  if (typeof showNotification === "function") {
    showNotification(message, "success");
  } else {
    alert(message);
  }
}

/**
 * Show error message
 */
function showError(message) {
  // Use existing notification system if available, otherwise alert
  if (typeof showNotification === "function") {
    showNotification(message, "error");
  } else {
    alert(message);
  }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}
