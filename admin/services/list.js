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

// Logout handler
function handleLogout() {
  if (confirm("Are you sure you want to logout?")) {
    fetch("../../api/admin/auth/logout.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.location.href = "../login.html";
        }
      })
      .catch((error) => {
        console.error("Logout error:", error);
        window.location.href = "../login.html";
      });
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
            <td data-label="Image">
                <div class="service-image">
                    ${
                      service.service_image
                        ? `<img src="../../images/${service.service_image}" alt="${service.service_name}" onerror="this.parentElement.innerHTML='No Image'">`
                        : "No Image"
                    }
                </div>
            </td>
            <td data-label="Service">
                <div class="service-name">${escapeHtml(
                  service.service_name
                )}</div>
                ${
                  service.sub_category
                    ? `<div class="service-category">${escapeHtml(
                        service.sub_category
                      )}</div>`
                    : ""
                }
            </td>
            <td data-label="Category">${escapeHtml(
              service.service_category
            )}</td>
            <td data-label="Duration">${
              service.current_duration_minutes
            } min</td>
            <td data-label="Price">RM ${parseFloat(
              service.current_price
            ).toFixed(2)}</td>
            <td data-label="Status">
                <span class="status-badge ${
                  service.is_active ? "active" : "inactive"
                }">
                    <span class="status-dot"></span>
                    ${service.is_active ? "Active" : "Inactive"}
                </span>
            </td>
            <td data-label="Actions">
                <div class="action-buttons">
                    <button class="btn-icon edit" onclick="openEditModal(${
                      service.service_id
                    })" title="Edit" aria-label="Edit service">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon toggle" onclick="toggleServiceStatus(${
                      service.service_id
                    })" title="${
        service.is_active ? "Deactivate" : "Activate"
      }" aria-label="${service.is_active ? "Deactivate" : "Activate"} service">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${
                              service.is_active
                                ? '<path d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                                : '<path d="M8 5v14l11-7z"></path>'
                            }
                        </svg>
                    </button>
                    <button class="btn-icon delete" onclick="openDeleteModal(${
                      service.service_id
                    })" title="Delete" aria-label="Delete service">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
  const url = isEdit
    ? "../../api/admin/services/update.php"
    : "../../api/admin/services/create.php";
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
      showToast(
        result.message ||
          (isEdit
            ? "Service updated successfully"
            : "Service created successfully"),
        "success"
      );
      closeServiceModal();
      loadServices();
    } else {
      if (result.error && result.error.details) {
        displayFormErrors(result.error.details);
      }
      showToast(result.error?.message || "An error occurred", "error");
    }
  } catch (error) {
    console.error("Error submitting form:", error);
    showToast("An error occurred while saving the service", "error");
  } finally {
    setSubmitButtonLoading(false);
  }
}

// Toggle service active status
async function toggleServiceStatus(serviceId) {
  const service = allServices.find((s) => s.service_id === serviceId);
  if (!service) return;

  const action = service.is_active ? "deactivate" : "activate";
  const confirmMsg = `Are you sure you want to ${action} "${service.service_name}"?`;

  if (!confirm(confirmMsg)) return;

  try {
    const response = await fetch("../../api/admin/services/toggle_active.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        service_id: serviceId,
        csrf_token: CSRF_TOKEN,
      }),
    });

    const result = await response.json();

    if (result.success) {
      showToast(result.message, "success");
      loadServices();
    } else {
      showToast(result.error?.message || "An error occurred", "error");
    }
  } catch (error) {
    console.error("Error toggling service status:", error);
    showToast("An error occurred", "error");
  }
}

// Open delete modal
function openDeleteModal(serviceId) {
  const service = allServices.find((s) => s.service_id === serviceId);
  if (!service) return;

  currentDeleteService = service;
  document.getElementById(
    "deleteMessage"
  ).textContent = `Are you sure you want to delete "${service.service_name}"?`;
  document.getElementById("deleteWarning").style.display = "none";
  document.getElementById("deleteModal").classList.add("active");
}

// Close delete modal
function closeDeleteModal() {
  document.getElementById("deleteModal").classList.remove("active");
  currentDeleteService = null;
}

// Confirm delete
async function confirmDelete() {
  if (!currentDeleteService) return;

  setDeleteButtonLoading(true);

  try {
    const response = await fetch("../../api/admin/services/delete.php", {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        service_id: currentDeleteService.service_id,
        action: "delete",
        csrf_token: CSRF_TOKEN,
      }),
    });

    const result = await response.json();

    if (result.success) {
      showToast(result.message || "Service deleted successfully", "success");
      closeDeleteModal();
      loadServices();
    } else {
      if (result.error?.code === "HAS_FUTURE_BOOKINGS") {
        document.getElementById("deleteWarning").textContent =
          result.error.message;
        document.getElementById("deleteWarning").style.display = "block";
      } else {
        showToast(result.error?.message || "An error occurred", "error");
      }
    }
  } catch (error) {
    console.error("Error deleting service:", error);
    showToast("An error occurred while deleting the service", "error");
  } finally {
    setDeleteButtonLoading(false);
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

// Show toast notification
function showToast(message, type = "info") {
  const toast = document.getElementById("toast");
  if (!toast) return;

  toast.textContent = message;
  toast.className = `toast ${type} show`;

  setTimeout(() => {
    toast.classList.remove("show");
  }, 3000);
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}
