// Service Management JavaScript

// Global variables
let allServices = [];
let currentEditingService = null;
let currentDeleteService = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  loadServices();
  setupEventListeners();
  setupMobileMenu();
});

// Setup event listeners
function setupEventListeners() {
  // Search input
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.addEventListener("input", debounce(filterServices, 300));
  }

  // Category filter
  const categoryFilter = document.getElementById("categoryFilter");
  if (categoryFilter) {
    categoryFilter.addEventListener("change", filterServices);
  }

  // Status filter
  const statusFilter = document.getElementById("statusFilter");
  if (statusFilter) {
    statusFilter.addEventListener("change", filterServices);
  }

  // Active only filter (checkbox)
  const activeOnlyFilter = document.getElementById("activeOnlyFilter");
  if (activeOnlyFilter) {
    activeOnlyFilter.addEventListener("change", filterServices);
  }

  // Service form submit
  const serviceForm = document.getElementById("serviceForm");
  if (serviceForm) {
    serviceForm.addEventListener("submit", handleServiceSubmit);
  }

  // Close modal on outside click
  const serviceModal = document.getElementById("serviceModal");
  if (serviceModal) {
    serviceModal.addEventListener("click", function (e) {
      if (e.target === serviceModal) {
        closeServiceModal();
      }
    });
  }

  const deleteModal = document.getElementById("deleteModal");
  if (deleteModal) {
    deleteModal.addEventListener("click", function (e) {
      if (e.target === deleteModal) {
        closeDeleteModal();
      }
    });
  }
}

// Setup mobile menu toggle
function setupMobileMenu() {
  const hamburgerBtn = document.getElementById("hamburgerBtn");
  const sidebar = document.getElementById("sidebar");

  if (hamburgerBtn && sidebar) {
    hamburgerBtn.addEventListener("click", function () {
      sidebar.classList.toggle("active");
      hamburgerBtn.classList.toggle("active");
    });
  }
}

// Logout handler (SweetAlert2)
async function handleLogout() {
  const result = await Swal.fire({
    title: "Logout?",
    text: "Are you sure you want to logout?",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#D4AF37",
    cancelButtonColor: "#6C757D",
    confirmButtonText: "Yes, logout",
    cancelButtonText: "Cancel",
  });

  if (!result.isConfirmed) return;

  try {
    const response = await fetch("../../api/admin/auth/logout.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
    });
    const data = await response.json();
    if (data.success) {
      window.location.href = "../login.html";
    }
  } catch (error) {
    console.error("Logout error:", error);
    window.location.href = "../login.html";
  }
}

// Debounce function for search
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

// Load services from API
async function loadServices() {
  showLoading();

  try {
    const response = await fetch("../../api/admin/services/list.php");
    const data = await response.json();

    if (data.success) {
      allServices = data.services;
      populateCategoryFilter();
      filterServices();
    } else {
      showToast("Failed to load services", "error");
      showEmptyState();
    }
  } catch (error) {
    console.error("Error loading services:", error);
    showToast("Error loading services", "error");
    showEmptyState();
  }
}

// Populate category filter dropdown
function populateCategoryFilter() {
  const categoryFilter = document.getElementById("categoryFilter");
  if (!categoryFilter) return;

  // Get unique categories
  const categories = [
    ...new Set(allServices.map((s) => s.service_category)),
  ].sort();

  // Keep "All Categories" option and add others
  const currentValue = categoryFilter.value;
  categoryFilter.innerHTML = '<option value="">All Categories</option>';

  categories.forEach((category) => {
    const option = document.createElement("option");
    option.value = category;
    option.textContent = category;
    categoryFilter.appendChild(option);
  });

  categoryFilter.value = currentValue;
}

// Filter services based on search and filters
function filterServices() {
  const searchTerm =
    document.getElementById("searchInput")?.value.toLowerCase() || "";
  const categoryFilter = document.getElementById("categoryFilter")?.value || "";
  const statusFilter = document.getElementById("statusFilter")?.value || "all";
  const activeOnlyFilter =
    document.getElementById("activeOnlyFilter")?.checked || false;

  let filtered = allServices.filter((service) => {
    // Search filter
    const matchesSearch =
      !searchTerm ||
      service.service_name.toLowerCase().includes(searchTerm) ||
      service.description?.toLowerCase().includes(searchTerm) ||
      service.service_category.toLowerCase().includes(searchTerm);

    // Category filter
    const matchesCategory =
      !categoryFilter || service.service_category === categoryFilter;

    // Status filter
    let matchesStatus = true;
    if (statusFilter === "active") {
      matchesStatus = service.is_active === true;
    } else if (statusFilter === "inactive") {
      matchesStatus = service.is_active === false;
    }

    // Active only filter (checkbox)
    const matchesActiveOnly = !activeOnlyFilter || service.is_active === true;

    return (
      matchesSearch && matchesCategory && matchesStatus && matchesActiveOnly
    );
  });

  renderServices(filtered);
}

// Render services table
function renderServices(services) {
  const tableBody = document.getElementById("servicesTableBody");
  const table = document.getElementById("servicesTable");
  const emptyState = document.getElementById("emptyState");
  const loadingState = document.getElementById("loadingState");

  if (loadingState) loadingState.style.display = "none";

  if (services.length === 0) {
    if (table) table.style.display = "none";
    if (emptyState) emptyState.style.display = "block";
    return;
  }

  if (table) table.style.display = "table";
  if (emptyState) emptyState.style.display = "none";

  if (!tableBody) return;

  tableBody.innerHTML = services
    .map(
      (service) => `
        <tr>
            <td data-label="Category">
                <span class="service-category-badge">${escapeHtml(
                  service.service_category
                )}</span>
            </td>
            <td data-label="Service Name">
                <div class="service-name-cell">${escapeHtml(
                  service.service_name
                )}</div>
                ${
                  service.sub_category
                    ? `<div class="service-subcategory" style="font-size: 12px; color: var(--text-lighter); margin-top: 2px;">${escapeHtml(
                        service.sub_category
                      )}</div>`
                    : ""
                }
            </td>
            <td data-label="Duration">
                <span class="service-duration">${
                  service.current_duration_minutes
                } min</span>
            </td>
            <td data-label="Price">
                <span class="service-price">RM ${parseFloat(
                  service.current_price
                ).toFixed(2)}</span>
            </td>
            <td data-label="Status">
                <span class="status-badge ${
                  service.is_active ? "active" : "inactive"
                }">
                    <span class="status-dot"></span>
                    ${service.is_active ? "Active" : "Inactive"}
                </span>
            </td>
            <td data-label="Created">
                <span class="service-created">${formatDate(
                  service.created_at
                )}</span>
            </td>
            <td data-label="Actions">
                <div class="action-buttons">
                    <button class="btn-icon btn-view" onclick="viewService(${
                      service.service_id
                    })" title="View Details" aria-label="View service details">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                    <button class="btn-icon btn-edit" onclick="openEditModal(${
                      service.service_id
                    })" title="Edit" aria-label="Edit service">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon btn-delete" onclick="openDeleteModal(${
                      service.service_id
                    })" title="Delete" aria-label="Delete service">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `
    )
    .join("");
}

// Format date helper
function formatDate(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  const options = { year: "numeric", month: "short", day: "numeric" };
  return date.toLocaleDateString("en-US", options);
}

// View service details (placeholder function)
function viewService(serviceId) {
  const service = allServices.find((s) => s.service_id === serviceId);
  if (!service) return;

  // For now, just open the edit modal - can be enhanced to show read-only view
  openEditModal(serviceId);
}

// Show loading state
function showLoading() {
  const loadingState = document.getElementById("loadingState");
  const table = document.getElementById("servicesTable");
  const emptyState = document.getElementById("emptyState");

  if (loadingState) loadingState.style.display = "block";
  if (table) table.style.display = "none";
  if (emptyState) emptyState.style.display = "none";
}

// Show empty state
function showEmptyState() {
  const loadingState = document.getElementById("loadingState");
  const table = document.getElementById("servicesTable");
  const emptyState = document.getElementById("emptyState");

  if (loadingState) loadingState.style.display = "none";
  if (table) table.style.display = "none";
  if (emptyState) emptyState.style.display = "block";
}

// Open create modal
function openCreateModal() {
  currentEditingService = null;
  document.getElementById("modalTitle").textContent = "Add New Service";
  document.getElementById("submitBtnText").textContent = "Create Service";
  document.getElementById("serviceForm").reset();
  document.getElementById("serviceId").value = "";
  clearFormErrors();
  clearImagePreview();
  document.getElementById("serviceModal").classList.add("active");
}

// Open edit modal
function openEditModal(serviceId) {
  const service = allServices.find((s) => s.service_id === serviceId);
  if (!service) return;

  currentEditingService = service;
  document.getElementById("modalTitle").textContent = "Edit Service";
  document.getElementById("submitBtnText").textContent = "Update Service";

  // Populate form
  document.getElementById("serviceId").value = service.service_id;
  document.getElementById("serviceCategory").value = service.service_category;
  document.getElementById("subCategory").value = service.sub_category || "";
  document.getElementById("serviceName").value = service.service_name;
  document.getElementById("duration").value = service.current_duration_minutes;
  document.getElementById("price").value = service.current_price;
  document.getElementById("cleanupTime").value =
    service.default_cleanup_minutes;
  document.getElementById("description").value = service.description || "";
  document.getElementById("serviceImage").value = service.service_image || "";
  document.getElementById("isActive").checked = service.is_active
    ? true
    : false;

  // Show image preview if exists
  if (service.service_image) {
    showImagePreview(`../../images/${service.service_image}`);
  }

  clearFormErrors();
  document.getElementById("serviceModal").classList.add("active");
}

// Close service modal
function closeServiceModal() {
  document.getElementById("serviceModal").classList.remove("active");
  document.getElementById("serviceForm").reset();
  clearFormErrors();
  clearImagePreview();
  currentEditingService = null;
}

// Handle service form submit
async function handleServiceSubmit(event) {
  event.preventDefault();

  clearFormErrors();

  const formData = new FormData(event.target);
  const data = Object.fromEntries(formData.entries());

  // Add CSRF token
  data.csrf_token = CSRF_TOKEN;

  // Remove empty fields
  Object.keys(data).forEach((key) => {
    if (data[key] === "") {
      delete data[key];
    }
  });

  const isEdit = !!data.service_id;
  const url = "../../api/admin/services/crud.php";
  const method = isEdit ? "PUT" : "POST";

  // Show loading state
  setSubmitButtonLoading(true);

  try {
    const response = await fetch(url, {
      method: method,
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });

    const result = await response.json();

    if (result.success) {
      closeServiceModal();

      // Show success with SweetAlert2
      Swal.fire({
        title: isEdit ? "Updated!" : "Created!",
        text:
          result.message ||
          (isEdit
            ? "Service updated successfully!"
            : "Service created successfully!"),
        icon: "success",
        confirmButtonColor: "#D4AF37",
        timer: 2000,
        timerProgressBar: true,
      });

      loadServices();
    } else {
      Swal.fire({
        title: "Error!",
        text: result.message || "An error occurred",
        icon: "error",
        confirmButtonColor: "#D4AF37",
      });
    }
  } catch (error) {
    console.error("Error submitting form:", error);
    Swal.fire({
      title: "Error!",
      text: "An error occurred while saving the service",
      icon: "error",
      confirmButtonColor: "#D4AF37",
    });
  } finally {
    setSubmitButtonLoading(false);
  }
}

// Toggle service active status (SweetAlert2)
async function toggleServiceStatus(serviceId) {
  const service = allServices.find((s) => s.service_id === serviceId);
  if (!service) return;

  const action = service.is_active ? "deactivate" : "activate";
  const actionColor = service.is_active ? "#E76F51" : "#2A9D8F";

  const result = await Swal.fire({
    title: `${action.charAt(0).toUpperCase() + action.slice(1)} Service?`,
    html: `Are you sure you want to ${action} <strong>"${escapeHtml(
      service.service_name
    )}"</strong>?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: actionColor,
    cancelButtonColor: "#6C757D",
    confirmButtonText: `Yes, ${action} it!`,
    cancelButtonText: "Cancel",
  });

  if (!result.isConfirmed) return;

  try {
    const response = await fetch("../../api/admin/services/toggle_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        service_id: serviceId,
        csrf_token: CSRF_TOKEN,
      }),
    });

    const data = await response.json();

    if (data.success) {
      showToast(data.message || "Status updated successfully", "success");
      loadServices();
    } else {
      showToast(data.message || "An error occurred", "error");
    }
  } catch (error) {
    console.error("Error toggling service status:", error);
    showToast("An error occurred", "error");
  }
}

// Open delete modal (SweetAlert2)
function openDeleteModal(serviceId) {
  const service = allServices.find((s) => s.service_id === serviceId);
  if (!service) return;

  currentDeleteService = service;

  Swal.fire({
    title: "Delete Service?",
    html: `Are you sure you want to permanently delete <strong>"${escapeHtml(
      service.service_name
    )}"</strong>?<br><br><span style="color:#E76F51;">This action cannot be undone.</span>`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#E76F51",
    cancelButtonColor: "#6C757D",
    confirmButtonText: "Yes, delete it!",
    cancelButtonText: "Cancel",
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      confirmDelete();
    }
  });
}
  }

  document.getElementById("deleteModal").classList.add("active");
}

// Close delete modal
function closeDeleteModal() {
  currentDeleteService = null;
}

// Confirm delete
async function confirmDelete() {
  if (!currentDeleteService) return;

  // Show loading
  Swal.fire({
    title: "Deleting...",
    text: "Please wait while we delete the service.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const response = await fetch(
      `../../api/admin/services/crud.php?id=${currentDeleteService.service_id}`,
      {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
        },
      }
    );

    const result = await response.json();

    if (result.success) {
      Swal.fire({
        title: "Deleted!",
        text: result.message || "Service has been deleted successfully.",
        icon: "success",
        confirmButtonColor: "#D4AF37",
      });
      closeDeleteModal();
      loadServices();
    } else {
      if (result.has_bookings) {
        Swal.fire({
          title: "Cannot Delete",
          text: result.message,
          icon: "error",
          confirmButtonColor: "#D4AF37",
        });
      } else {
        Swal.fire({
          title: "Error!",
          text: result.message || "An error occurred",
          icon: "error",
          confirmButtonColor: "#D4AF37",
        });
      }
    }
  } catch (error) {
    console.error("Error deleting service:", error);
    Swal.fire({
      title: "Error!",
      text: "An error occurred while deleting the service",
      icon: "error",
      confirmButtonColor: "#D4AF37",
    });
  }
}

// Handle image upload
function handleImageUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  // Validate file type
  const validTypes = ["image/jpeg", "image/jpg", "image/png"];
  if (!validTypes.includes(file.type)) {
    showToast("Please select a valid image file (JPG or PNG)", "error");
    event.target.value = "";
    return;
  }

  // Validate file size (2MB)
  if (file.size > 2 * 1024 * 1024) {
    showToast("Image size must be less than 2MB", "error");
    event.target.value = "";
    return;
  }

  // Show preview
  const reader = new FileReader();
  reader.onload = function (e) {
    showImagePreview(e.target.result);
    // Store filename in hidden input
    document.getElementById("serviceImage").value = file.name;
  };
  reader.readAsDataURL(file);
}

// Show image preview
function showImagePreview(src) {
  const preview = document.getElementById("imagePreview");
  const img = document.getElementById("previewImg");

  if (preview && img) {
    img.src = src;
    preview.style.display = "block";
  }
}

// Clear image preview
function clearImagePreview() {
  const preview = document.getElementById("imagePreview");
  const img = document.getElementById("previewImg");
  const fileInput = document.getElementById("serviceImageFile");

  if (preview) preview.style.display = "none";
  if (img) img.src = "";
  if (fileInput) fileInput.value = "";
  document.getElementById("serviceImage").value = "";
}

// Remove image
function removeImage() {
  clearImagePreview();
}

// Reset filters
function resetFilters() {
  document.getElementById("searchInput").value = "";
  document.getElementById("categoryFilter").value = "";
  const statusFilter = document.getElementById("statusFilter");
  if (statusFilter) statusFilter.value = "all";
  const activeOnlyFilter = document.getElementById("activeOnlyFilter");
  if (activeOnlyFilter) activeOnlyFilter.checked = false;
  filterServices();
}

// Display form errors
function displayFormErrors(errors) {
  Object.keys(errors).forEach((field) => {
    const errorElement = document.getElementById(`error-${field}`);
    const inputElement = document.getElementById(field.replace("_", ""));

    if (errorElement) {
      errorElement.textContent = errors[field];
    }
    if (inputElement) {
      inputElement.classList.add("error");
    }
  });
}

// Clear form errors
function clearFormErrors() {
  document
    .querySelectorAll(".error-message")
    .forEach((el) => (el.textContent = ""));
  document
    .querySelectorAll(".form-input, .form-select, .form-textarea")
    .forEach((el) => {
      el.classList.remove("error");
    });
}

// Set submit button loading state
function setSubmitButtonLoading(loading) {
  const submitBtn = document.getElementById("submitBtn");
  const submitBtnText = document.getElementById("submitBtnText");
  const submitBtnSpinner = document.getElementById("submitBtnSpinner");

  if (submitBtn) submitBtn.disabled = loading;
  if (submitBtnText) submitBtnText.style.display = loading ? "none" : "inline";
  if (submitBtnSpinner)
    submitBtnSpinner.style.display = loading ? "inline-block" : "none";
}

// Set delete button loading state
function setDeleteButtonLoading(loading) {
  const deleteBtn = document.getElementById("confirmDeleteBtn");
  const deleteBtnText = document.getElementById("deleteBtnText");
  const deleteBtnSpinner = document.getElementById("deleteBtnSpinner");

  if (deleteBtn) deleteBtn.disabled = loading;
  if (deleteBtnText) deleteBtnText.style.display = loading ? "none" : "inline";
  if (deleteBtnSpinner)
    deleteBtnSpinner.style.display = loading ? "inline-block" : "none";
}

// Show toast notification (SweetAlert2)
function showToast(message, type = "info") {
  // Map type to SweetAlert2 icon
  const iconMap = {
    success: "success",
    error: "error",
    warning: "warning",
    info: "info",
  };

  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.onmouseenter = Swal.stopTimer;
      toast.onmouseleave = Swal.resumeTimer;
    },
  });

  Toast.fire({
    icon: iconMap[type] || "info",
    title: message,
  });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}
