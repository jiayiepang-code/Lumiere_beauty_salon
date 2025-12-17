// Service Management JavaScript

// Global variables
let allServices = [];
let currentEditingService = null;
let currentDeleteService = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  loadServices();
  setupEventListeners();
  setDefaultFilters();
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

  // Service form submit
  const serviceForm = document.getElementById("serviceForm");
  if (serviceForm) {
    serviceForm.addEventListener("submit", handleServiceSubmit);
  }

  // Image upload handler
  const serviceImageInput = document.getElementById("serviceImage");
  if (serviceImageInput) {
    serviceImageInput.addEventListener("change", handleImageUpload);
  }

  // Click handler for image upload area
  const imageUploadArea = document.getElementById("imageUploadArea");
  if (imageUploadArea) {
    imageUploadArea.addEventListener("click", function (e) {
      // Don't trigger if clicking the button directly
      if (e.target.closest(".btn")) {
        return;
      }
      serviceImageInput?.click();
    });
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

// Logout handler (SweetAlert2)
async function handleLogout() {
  const result = await Swal.fire({
    title: "Logout?",
    text: "Are you sure you want to logout?",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#c29076" /* Brown Primary */,
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
    const response = await fetch("../../api/admin/services/list.php", {
      credentials: "same-origin", // Include cookies/session for authentication
    });

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
  const statusFilter = document.getElementById("statusFilter")?.value || "";

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

    return matchesSearch && matchesCategory && matchesStatus;
  });

  renderServices(filtered);
}

// Render services table
function renderServices(services) {
  const tableBody = document.getElementById("servicesTableBody");
  const table = document.getElementById("servicesTable");
  const emptyState = document.getElementById("emptyState");
  const loadingState = document.getElementById("loadingState");
  const tableResponsive = document.querySelector(".table-responsive");

  if (loadingState) loadingState.style.display = "none";

  if (services.length === 0) {
    if (table) table.style.display = "none";
    if (tableResponsive) tableResponsive.style.display = "none";
    if (emptyState) emptyState.style.display = "block";
    return;
  }

  if (table) table.style.display = "table";
  if (tableResponsive) tableResponsive.style.display = "block";
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
                    <button class="btn-icon btn-toggle ${
                      service.is_active ? "toggle-active" : "toggle-inactive"
                    }" 
                            onclick="toggleServiceStatus(event, '${escapeHtml(
                              String(service.service_id)
                            )}', ${service.is_active ? "1" : "0"})" 
                            title="${
                              service.is_active ? "Deactivate" : "Activate"
                            }" 
                            aria-label="${
                              service.is_active
                                ? "Deactivate service"
                                : "Activate service"
                            }"
                            type="button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="5" width="22" height="14" rx="7" ry="7"></rect>
                            <circle cx="${
                              service.is_active ? "16" : "8"
                            }" cy="12" r="3"></circle>
                        </svg>
                    </button>
                    <button class="btn-icon btn-edit" onclick="openEditModal('${escapeHtml(
                      String(service.service_id)
                    )}')" title="Edit" aria-label="Edit service">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon btn-delete" onclick="openDeleteModal('${escapeHtml(
                      String(service.service_id)
                    )}')" title="Delete" aria-label="Delete service">
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

// Show loading state
function showLoading() {
  const loadingState = document.getElementById("loadingState");
  const table = document.getElementById("servicesTable");
  const emptyState = document.getElementById("emptyState");
  const tableResponsive = document.querySelector(".table-responsive");

  if (loadingState) loadingState.style.display = "block";
  if (table) table.style.display = "none";
  if (tableResponsive) tableResponsive.style.display = "none";
  if (emptyState) emptyState.style.display = "none";
}

// Show empty state
function showEmptyState() {
  const loadingState = document.getElementById("loadingState");
  const table = document.getElementById("servicesTable");
  const emptyState = document.getElementById("emptyState");
  const tableResponsive = document.querySelector(".table-responsive");

  if (loadingState) loadingState.style.display = "none";
  if (table) table.style.display = "none";
  if (tableResponsive) tableResponsive.style.display = "none";
  if (emptyState) emptyState.style.display = "block";
}

// Open create modal
function openCreateModal() {
  currentEditingService = null;
  const modalTitle = document.getElementById("modalTitle");
  const submitBtnText = document.getElementById("submitBtnText");
  const serviceForm = document.getElementById("serviceForm");
  const serviceId = document.getElementById("serviceId");
  const serviceModal = document.getElementById("serviceModal");

  if (modalTitle) modalTitle.textContent = "Add New Service";
  if (submitBtnText) submitBtnText.textContent = "Create Service";
  if (serviceForm) serviceForm.reset();
  if (serviceId) serviceId.value = "";

  clearFormErrors();
  clearImagePreview();

  if (serviceModal) {
    serviceModal.classList.add("active");
  }
}

// Open edit modal
function openEditModal(serviceId) {
  // Convert to string for comparison (service_id is VARCHAR(4))
  const serviceIdStr = String(serviceId);
  const service = allServices.find(
    (s) => String(s.service_id) === serviceIdStr
  );
  if (!service) {
    console.error("Service not found:", serviceId);
    showToast("Service not found", "error");
    return;
  }

  currentEditingService = service;

  const modalTitle = document.getElementById("modalTitle");
  const submitBtnText = document.getElementById("submitBtnText");
  const serviceModal = document.getElementById("serviceModal");

  if (modalTitle) modalTitle.textContent = "Edit Service";
  if (submitBtnText) submitBtnText.textContent = "Update Service";

  // Populate form
  const serviceIdEl = document.getElementById("serviceId");
  const serviceCategory = document.getElementById("serviceCategory");
  const subCategory = document.getElementById("subCategory");
  const serviceName = document.getElementById("serviceName");
  const durationMinutes = document.getElementById("durationMinutes");
  const price = document.getElementById("price");
  const cleanupTime = document.getElementById("cleanupTime");
  const description = document.getElementById("description");
  const serviceImage = document.getElementById("serviceImage");

  if (serviceIdEl) serviceIdEl.value = service.service_id;
  if (serviceCategory) serviceCategory.value = service.service_category;
  if (subCategory) subCategory.value = service.sub_category || "";
  if (serviceName) serviceName.value = service.service_name;
  if (durationMinutes) durationMinutes.value = service.current_duration_minutes;
  if (price) price.value = service.current_price;
  if (cleanupTime) cleanupTime.value = service.default_cleanup_minutes || 10;
  if (description) description.value = service.description || "";
  if (serviceImage) serviceImage.value = service.service_image || "";

  // Show image preview if exists
  if (service.service_image) {
    const imageUploadArea = document.getElementById("imageUploadArea");
    if (imageUploadArea) {
      imageUploadArea.style.display = "none";
    }
    showImagePreview(
      `../../images/${service.service_image}`,
      service.service_image
    );
  } else {
    const imageUploadArea = document.getElementById("imageUploadArea");
    const imagePreviewContainer = document.getElementById(
      "imagePreviewContainer"
    );
    if (imageUploadArea) {
      imageUploadArea.style.display = "flex";
    }
    if (imagePreviewContainer) {
      imagePreviewContainer.style.display = "none";
    }
  }

  clearFormErrors();

  if (serviceModal) {
    serviceModal.classList.add("active");
  } else {
    console.error("Service modal element not found");
  }
}

// Close service modal
function closeServiceModal() {
  const serviceModal = document.getElementById("serviceModal");
  const serviceForm = document.getElementById("serviceForm");

  if (serviceModal) serviceModal.classList.remove("active");
  if (serviceForm) serviceForm.reset();

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

  // Remove empty fields (but preserve service_id for edits)
  Object.keys(data).forEach((key) => {
    if (data[key] === "" && key !== "service_id") {
      delete data[key];
    }
  });

  const isEdit = !!data.service_id && data.service_id !== "";
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
      credentials: "same-origin", // Include cookies/session for authentication
      body: JSON.stringify(data),
    });

    // Try to parse JSON response
    let result;
    const responseText = await response.text();
    try {
      result = JSON.parse(responseText);
    } catch (parseError) {
      // If response is not JSON, it's likely a PHP error
      console.error("Failed to parse JSON response:", parseError);
      console.error("Response text:", responseText);
      throw new Error(`Server returned invalid response. Please check the server logs. (Status: ${response.status})`);
    }

    if (!response.ok) {
      // Handle error response
      const errorMessage = result.error?.message || result.message || `Request failed (Status: ${response.status})`;
      
      // Check if there are field-specific validation errors
      if (result.error && result.error.details) {
        displayFormErrors(result.error.details);
        Swal.fire({
          title: "Validation Error",
          text: errorMessage,
          icon: "error",
          confirmButtonColor: "#c29076" /* Brown Primary */,
        });
      } else {
        Swal.fire({
          title: "Error!",
          text: errorMessage,
          icon: "error",
          confirmButtonColor: "#c29076" /* Brown Primary */,
        });
      }
      return;
    }

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
        confirmButtonColor: "#c29076" /* Brown Primary */,
        timer: 2000,
        timerProgressBar: true,
      });

      loadServices();
    } else {
      // Check if there are field-specific validation errors
      if (result.error && result.error.details) {
        displayFormErrors(result.error.details);
        Swal.fire({
          title: "Validation Error",
          text: result.error.message || "Please check the form for errors",
          icon: "error",
          confirmButtonColor: "#c29076" /* Brown Primary */,
        });
      } else {
        Swal.fire({
          title: "Error!",
          text: result.error?.message || result.message || "An error occurred",
          icon: "error",
          confirmButtonColor: "#c29076" /* Brown Primary */,
        });
      }
    }
  } catch (error) {
    console.error("Error submitting form:", error);
    Swal.fire({
      title: "Error!",
      text: error.message || "An error occurred while saving the service. Please try again.",
      icon: "error",
      confirmButtonColor: "#c29076" /* Brown Primary */,
    });
  } finally {
    setSubmitButtonLoading(false);
  }
}

// Toggle service active status
async function toggleServiceStatus(event, serviceId, currentStatus) {
  event.preventDefault();

  const button = event.target.closest("button");
  if (!button) {
    console.error("Button element not found");
    return;
  }

  const serviceIdStr = String(serviceId);
  const service = allServices.find(
    (s) => String(s.service_id) === serviceIdStr
  );
  if (!service) {
    console.error("Service not found:", serviceId);
    showToast("Service not found", "error");
    return;
  }

  const newStatus = currentStatus === 1 ? 0 : 1;
  const actionText = newStatus === 1 ? "activate" : "deactivate";
  const actionPastText = newStatus === 1 ? "activated" : "deactivated";

  try {
    const originalHTML = button.innerHTML;
    button.disabled = true;

    const response = await fetch("../../api/admin/services/toggle_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin", // Include cookies/session for authentication
      body: JSON.stringify({
        service_id: serviceIdStr,
        csrf_token: CSRF_TOKEN,
      }),
    });

    // Try to parse JSON response
    let data;
    try {
      data = await response.json();
    } catch (parseError) {
      // If response is not JSON, create a generic error response
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      throw parseError;
    }

    if (!response.ok) {
      // Handle error response
      const errorMessage = data.error?.message || data.message || `Failed to ${actionText} service (Status: ${response.status})`;
      button.disabled = false;
      button.innerHTML = originalHTML;
      Swal.fire({
        title: "Error",
        text: errorMessage,
        icon: "error",
        confirmButtonColor: "#c29076",
      });
      return;
    }

    if (data.success) {
      // Show success message as toast notification
      Swal.fire({
        text: `Service ${actionPastText}`,
        icon: "success",
        toast: true,
        position: "bottom-end",
        showConfirmButton: false,
        timer: 1000,
        timerProgressBar: false,
      });

      // Reload services after a short delay
      setTimeout(() => {
        loadServices();
      }, 1000);
    } else {
      button.disabled = false;
      button.innerHTML = originalHTML;
      Swal.fire({
        title: "Error",
        text: data.error?.message || data.message || `Failed to ${actionText} service`,
        icon: "error",
        confirmButtonColor: "#c29076",
      });
    }
  } catch (error) {
    console.error("Error toggling service status:", error);
    button.disabled = false;
    button.innerHTML = originalHTML;
    Swal.fire({
      title: "Error",
      text: error.message || `Failed to ${actionText} service. Please try again.`,
      icon: "error",
      confirmButtonColor: "#c29076",
    });
  }
}

// Open delete modal (SweetAlert2)
function openDeleteModal(serviceId) {
  // Convert to string for comparison (service_id is VARCHAR(4))
  const serviceIdStr = String(serviceId);
  const service = allServices.find(
    (s) => String(s.service_id) === serviceIdStr
  );
  if (!service) {
    console.error("Service not found:", serviceId);
    showToast("Service not found", "error");
    return;
  }

  currentDeleteService = service;

  Swal.fire({
    title: "Delete Service?",
    html: `<div style="text-align: left;">
      <p style="margin-bottom: 15px;">Are you sure you want to permanently delete <strong>"${escapeHtml(
        service.service_name
      )}"</strong>?</p>
      <p style="margin-bottom: 15px; color: #E76F51; font-weight: 500;">This action is permanent and can impact reports and linked bookings.</p>
      <p style="font-size: 0.9em; color: #666; margin-bottom: 10px;"><strong>Consequences:</strong></p>
      <ul style="margin: 0; padding-left: 20px; font-size: 0.9em; color: #666; line-height: 1.6;">
        <li>Revenue-by-service and trends may lose attribution.</li>
        <li>Past booking lines may show missing service details if fully removed.</li>
        <li>Future bookings (if any) must be cancelled or reassigned.</li>
        <li>Database links that reference this service can break if permanently deleted.</li>
      </ul>
    </div>`,
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

// Close delete modal
function closeDeleteModal() {
  currentDeleteService = null;
}

// Confirm delete
async function confirmDelete() {
  if (!currentDeleteService) return;

  // Step 1: Prompt for password
  const { value: password } = await Swal.fire({
    title: "Confirm Your Password",
    input: "password",
    inputLabel: "For security, please enter your admin password",
    inputPlaceholder: "Enter your password",
    inputAttributes: { autocapitalize: "off", autocorrect: "off" },
    showCancelButton: true,
    confirmButtonText: "Verify & Continue",
    cancelButtonText: "Cancel",
    confirmButtonColor: "#c29076",
    cancelButtonColor: "#6C757D",
    inputValidator: (value) => {
      if (!value) {
        return "Password is required";
      }
    },
  });

  if (!password) return; // User cancelled

  // Step 2: Call re-auth endpoint
  try {
    const reauthRes = await fetch("../../api/admin/security/reauth.php", {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        csrf_token: CSRF_TOKEN,
        password: password,
      }),
    });

    const reauth = await reauthRes.json();

    if (!reauth.success) {
      Swal.fire({
        title: "Authorization Failed",
        text: reauth.error?.message || "Incorrect password. Please try again.",
        icon: "error",
        confirmButtonColor: "#c29076",
      });
      return;
    }

    // Step 3: Proceed with delete - Show loading
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
      const response = await fetch("../../api/admin/services/crud.php", {
        method: "DELETE",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          service_id: currentDeleteService.service_id,
          csrf_token: CSRF_TOKEN,
        }),
      });

      const result = await response.json();

      if (result.success) {
        Swal.fire({
          title: "Deleted!",
          text: result.message || "Service has been deleted successfully.",
          icon: "success",
          confirmButtonColor: "#c29076" /* Brown Primary */,
        });
        closeDeleteModal();
        loadServices();
      } else {
        if (result.has_bookings) {
          Swal.fire({
            title: "Cannot Delete",
            text: result.message,
            icon: "error",
            confirmButtonColor: "#c29076" /* Brown Primary */,
          });
        } else {
          Swal.fire({
            title: "Error!",
            text: result.message || "An error occurred",
            icon: "error",
            confirmButtonColor: "#c29076" /* Brown Primary */,
          });
        }
      }
    } catch (error) {
      console.error("Error deleting service:", error);
      Swal.fire({
        title: "Error!",
        text: "An error occurred while deleting the service",
        icon: "error",
        confirmButtonColor: "#c29076" /* Brown Primary */,
      });
    }
  } catch (error) {
    console.error("Error verifying password:", error);
    Swal.fire({
      title: "Error",
      text: "Failed to verify password. Please try again.",
      icon: "error",
      confirmButtonColor: "#c29076",
    });
  }
}

// Handle image upload
function handleImageUpload(event) {
  const file = event.target.files[0];
  if (!file) {
    // Reset if no file selected
    const imageUploadArea = document.getElementById("imageUploadArea");
    const imagePreviewContainer = document.getElementById(
      "imagePreviewContainer"
    );
    const fileNameDisplay = document.getElementById("fileNameDisplay");

    if (imagePreviewContainer) imagePreviewContainer.style.display = "none";
    if (imageUploadArea) imageUploadArea.style.display = "flex";
    if (fileNameDisplay) fileNameDisplay.textContent = "No file chosen";
    return;
  }

  // Get error element reference
  const errorElement = document.getElementById("imageSizeError");

  // Hide error initially
  if (errorElement) {
    errorElement.style.display = "none";
  }

  // Validate file type
  const validTypes = [
    "image/jpeg",
    "image/jpg",
    "image/png",
    "image/gif",
    "image/webp",
  ];
  if (!validTypes.includes(file.type)) {
    showToast(
      "Please select a valid image file (JPEG, PNG, GIF, or WebP)",
      "error"
    );
    event.target.value = "";
    return;
  }

  // Validate file size (2MB)
  if (file.size > 2 * 1024 * 1024) {
    if (errorElement) {
      errorElement.style.display = "flex";
    }
    event.target.value = "";
    return;
  }

  // Hide error if file is valid
  if (errorElement) {
    errorElement.style.display = "none";
  }

  // Update file name display
  const fileNameDisplay = document.getElementById("fileNameDisplay");
  if (fileNameDisplay) {
    fileNameDisplay.textContent = file.name;
  }

  // Show preview
  const reader = new FileReader();
  reader.onload = function (e) {
    showImagePreview(e.target.result, file.name);
  };
  reader.readAsDataURL(file);
}

// Show image preview
function showImagePreview(src, fileName) {
  const previewContainer = document.getElementById("imagePreviewContainer");
  const imageUploadArea = document.getElementById("imageUploadArea");
  const img = document.getElementById("imagePreview");
  const previewFileName = document.getElementById("previewFileName");

  if (img) {
    img.src = src;
  }
  if (previewFileName && fileName) {
    previewFileName.textContent = fileName;
  }
  if (previewContainer) {
    previewContainer.style.display = "block";
  }
  if (imageUploadArea) {
    imageUploadArea.style.display = "none";
  }
}

// Clear image preview
function clearImagePreview() {
  const previewContainer = document.getElementById("imagePreviewContainer");
  const imageUploadArea = document.getElementById("imageUploadArea");
  const img = document.getElementById("imagePreview");
  const fileInput = document.getElementById("serviceImage");
  const fileNameDisplay = document.getElementById("fileNameDisplay");
  const previewFileName = document.getElementById("previewFileName");
  const errorElement = document.getElementById("imageSizeError");

  if (previewContainer) previewContainer.style.display = "none";
  if (imageUploadArea) imageUploadArea.style.display = "flex";
  if (img) img.src = "";
  if (fileInput) fileInput.value = "";
  if (fileNameDisplay) fileNameDisplay.textContent = "No file chosen";
  if (previewFileName) previewFileName.textContent = "";
  if (errorElement) errorElement.style.display = "none";
}

// Remove image
function removeImage() {
  clearImagePreview();
  // Also clear the file input
  const fileInput = document.getElementById("serviceImage");
  if (fileInput) {
    fileInput.value = "";
  }
}

// Reset filters
function resetFilters() {
  document.getElementById("searchInput").value = "";
  document.getElementById("categoryFilter").value = "";
  const statusFilter = document.getElementById("statusFilter");
  if (statusFilter) statusFilter.value = "";
  filterServices();
}

// Set default month and year filters based on current system time
function setDefaultFilters() {
  const currentDate = new Date();
  const currentMonth = currentDate.getMonth() + 1; // Months are 0-indexed
  const currentYear = currentDate.getFullYear();

  const monthFilter = document.getElementById("monthFilter");
  const yearFilter = document.getElementById("yearFilter");

  if (monthFilter) {
    monthFilter.value = currentMonth;
  }

  if (yearFilter) {
    yearFilter.value = currentYear;
  }
}

// Display form errors
function displayFormErrors(errors) {
  // Map field names to element IDs
  const fieldToElementId = {
    service_category: "serviceCategory",
    sub_category: "subCategory",
    service_name: "serviceName",
    current_duration_minutes: "durationMinutes",
    current_price: "price",
    default_cleanup_minutes: "cleanupTime",
    description: "description",
    service_image: "serviceImage",
    service_id: "serviceId",
  };

  Object.keys(errors).forEach((field) => {
    const errorElement = document.getElementById(`error-${field}`);
    const elementId = fieldToElementId[field] || field;
    const inputElement = document.getElementById(elementId);

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
