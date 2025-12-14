// Staff management JavaScript - Client-side filtering and CRUD operations

let staffData = [];
let currentStaffEmail = null;
let staffModal = null;
let deleteModal = null;

/**
 * Format phone number for display
 * Adds +60 prefix if number starts with 60
 */
function formatPhoneForDisplay(phone) {
  if (!phone) return "";

  // If starts with 60, add + prefix
  if (phone.startsWith("60")) {
    return "+" + phone;
  }

  // If starts with 01, return as is (local format)
  return phone;
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  // Extract staff data from table rows
  const rows = document.querySelectorAll("#staffTableBody tr");
  staffData = Array.from(rows).map((row) => {
    // Extract searchable text from name, email, and phone cells
    const nameCell = row.querySelector(".staff-name");
    const contactCells = row.querySelectorAll(".contact-item");
    let searchableText = "";
    if (nameCell) searchableText += nameCell.textContent.toLowerCase() + " ";
    contactCells.forEach((cell) => {
      searchableText += cell.textContent.toLowerCase() + " ";
    });

    return {
      email: row.dataset.email,
      role: row.dataset.role,
      active: row.dataset.active === "1",
      name: row.dataset.name,
      searchableText: searchableText.trim(),
      element: row,
    };
  });

  // Initialize Bootstrap modals if they exist
  const staffModalElement = document.getElementById("staffModal");
  const deleteModalElement = document.getElementById("deleteModal");

  if (staffModalElement) {
    staffModal = new bootstrap.Modal(staffModalElement, { backdrop: "static" });
  }
  if (deleteModalElement) {
    deleteModal = new bootstrap.Modal(deleteModalElement);
  }

  // Set up event listeners
  const searchInput = document.getElementById("searchInput");
  const roleFilter = document.getElementById("roleFilter");

  if (searchInput) {
    searchInput.addEventListener("input", filterStaff);
  }
  if (roleFilter) {
    roleFilter.addEventListener("change", filterStaff);
  }

  // Form submission handler
  const staffForm = document.getElementById("staffForm");
  if (staffForm) {
    staffForm.addEventListener("submit", handleFormSubmit);
  }

  // Image preview handler will be registered in separate DOMContentLoaded below
});

/**
 * Filter staff based on search and role filter
 */
function filterStaff() {
  const searchInput = document.getElementById("searchInput");
  const roleFilter = document.getElementById("roleFilter");

  if (!searchInput || !roleFilter) return;

  const searchTerm = searchInput.value.toLowerCase().trim();
  const roleFilterValue = roleFilter.value;

  staffData.forEach(({ element, email, role, active, searchableText }) => {
    const matchesSearch = !searchTerm || searchableText.includes(searchTerm);
    const matchesRole = !roleFilterValue || role === roleFilterValue;

    element.style.display = matchesSearch && matchesRole ? "" : "none";
  });
}

/**
 * Open create modal function - will be defined after modal initialization
 */
function openCreateModalFunction() {
  if (!staffModal) {
    // If modal not initialized yet, wait a bit and try again
    setTimeout(() => {
      if (staffModal) {
        openCreateModalFunction();
      } else {
        Swal.fire({
          title: "Error",
          text: "Please wait for the page to finish loading",
          icon: "error",
          confirmButtonColor: "#c29076",
        });
      }
    }, 100);
    return;
  }

  document.getElementById("modalTitle").textContent = "Add Staff Member";
  const modalDesc = document.getElementById("modalDescription");
  if (modalDesc) {
    modalDesc.textContent = "Fill in the details to add a new staff member";
  }
  document.getElementById("isEdit").value = "0";
  document.getElementById("staffForm").reset();
  document.getElementById("staffEmail").readOnly = false;
  document.getElementById("password").required = true;
  document.getElementById("passwordRequired").textContent = "*";
  document.getElementById("passwordHint").textContent =
    "Min 8 chars, 1 uppercase, 1 number, 1 special character";

  // Reset image upload area
  const imagePreviewContainer = document.getElementById(
    "imagePreviewContainer"
  );
  const imageUploadArea = document.getElementById("imageUploadArea");
  const staffImageInput = document.getElementById("staffImage");
  const previewFileName = document.getElementById("previewFileName");

  if (imagePreviewContainer) {
    imagePreviewContainer.style.display = "none";
  }
  if (imageUploadArea) {
    imageUploadArea.style.display = "flex";
  }
  if (staffImageInput) {
    staffImageInput.value = "";
  }
  if (previewFileName) {
    previewFileName.textContent = "";
  }

  // Reset active toggle
  const activeToggle = document.getElementById("isActiveToggle");
  if (activeToggle) {
    activeToggle.checked = true;
  }

  // Update submit button text
  const submitButton = document.getElementById("submitButton");
  if (submitButton) {
    submitButton.textContent = "Add Staff Member";
  }

  clearErrors();
  staffModal.show();
}

// Make globally accessible immediately
window.openCreateModal = openCreateModalFunction;

// Also keep as regular function for backwards compatibility
function openCreateModal() {
  openCreateModalFunction();
}

/**
 * Open edit modal
 */
async function openEditModal(staffEmail) {
  if (!staffModal) {
    Swal.fire({
      title: "Edit Staff",
      text: "Edit functionality will be implemented next",
      icon: "info",
      confirmButtonColor: "#c29076",
    });
    return;
  }

  try {
    // Fetch staff details
    const response = await fetch(
      `../../api/admin/staff/details.php?email=${encodeURIComponent(
        staffEmail
      )}`,
      {
        method: "GET",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
        },
      }
    );

    // Check if response is ok before parsing JSON
    if (!response.ok) {
      if (response.status === 401) {
        Swal.fire({
          title: "Authentication Required",
          text: "Your session has expired. Please login again.",
          icon: "error",
          confirmButtonColor: "#c29076",
        }).then(() => {
          window.location.href = "/Lumiere-beauty-salon/admin/login.html";
        });
        return;
      }
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success && data.staff) {
      const member = data.staff;

      document.getElementById("modalTitle").textContent = "Edit Staff Member";
      const modalDesc = document.getElementById("modalDescription");
      if (modalDesc) {
        modalDesc.textContent = "Update the staff member details";
      }
      document.getElementById("isEdit").value = "1";
      document.getElementById("staffEmail").value = member.staff_email;
      document.getElementById("staffEmail").readOnly = true;
      document.getElementById("phone").value = formatPhoneForDisplay(
        member.phone
      );
      document.getElementById("firstName").value = member.first_name;
      document.getElementById("lastName").value = member.last_name;
      document.getElementById("role").value = member.role;
      document.getElementById("bio").value = member.bio || "";

      // Make password optional for edit
      document.getElementById("password").required = false;
      document.getElementById("password").value = "";
      document.getElementById("password").placeholder =
        "Leave blank to keep current password";
      document.getElementById("passwordRequired").textContent = "";
      document.getElementById("passwordHint").textContent =
        "Leave blank to keep current password, or enter new password (min 8 chars)";

      // Show existing image if available
      const imagePreviewContainer = document.getElementById(
        "imagePreviewContainer"
      );
      const imageUploadArea = document.getElementById("imageUploadArea");
      const previewFileName = document.getElementById("previewFileName");
      if (member.staff_image) {
        const imagePreview = document.getElementById("imagePreview");
        if (imagePreview) {
          imagePreview.src = member.staff_image;
        }
        if (previewFileName) {
          // Extract filename from path
          const fileName =
            member.staff_image.split("/").pop() || "Image selected";
          previewFileName.textContent = fileName;
        }
        if (imagePreviewContainer) {
          imagePreviewContainer.style.display = "block";
        }
        if (imageUploadArea) {
          imageUploadArea.style.display = "none";
        }
      } else {
        if (imagePreviewContainer) {
          imagePreviewContainer.style.display = "none";
        }
        if (imageUploadArea) {
          imageUploadArea.style.display = "flex";
        }
        if (previewFileName) {
          previewFileName.textContent = "";
        }
      }

      // Set active toggle
      const activeToggle = document.getElementById("isActiveToggle");
      if (activeToggle) {
        activeToggle.checked =
          member.is_active === true || member.is_active === 1;
      }

      // Update submit button text
      const submitButton = document.getElementById("submitButton");
      if (submitButton) {
        submitButton.textContent = "Update Staff Member";
      }

      clearErrors();
      staffModal.show();
    } else {
      Swal.fire({
        title: "Error",
        text: data.error?.message || "Failed to load staff details",
        icon: "error",
        confirmButtonColor: "#c29076",
      });
    }
  } catch (error) {
    console.error("Error loading staff:", error);
    Swal.fire({
      title: "Error",
      text: "Failed to load staff details",
      icon: "error",
      confirmButtonColor: "#c29076",
    });
  }
}

/**
 * Toggle staff active/inactive status
 */
async function toggleStaffStatus(staffEmail, currentStatus) {
  const newStatus = currentStatus === 1 ? 0 : 1;
  const actionText = newStatus === 1 ? "activate" : "deactivate";
  const actionPastText = newStatus === 1 ? "activated" : "deactivated";

  try {
    // Show loading state
    const button = event.target.closest("button");
    const originalHTML = button.innerHTML;
    button.disabled = true;

    const response = await fetch("../../api/admin/staff/update.php", {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        csrf_token: CSRF_TOKEN,
        staff_email: staffEmail,
        is_active: newStatus.toString(),
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      // Show success message as toast notification
      Swal.fire({
        text: `Staff member ${actionPastText}`,
        icon: "success",
        toast: true,
        position: "bottom-end",
        showConfirmButton: false,
        timer: 1000,
        timerProgressBar: false,
        didOpen: (toast) => {
          toast.style.boxShadow = "0 2px 8px rgba(0, 0, 0, 0.1)";
          toast.style.fontSize = "0.875rem";
          toast.style.padding = "0.75rem 1rem";
        },
      });

      // Reload page after a short delay
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      button.disabled = false;
      Swal.fire({
        title: "Error",
        text: data.error?.message || `Failed to ${actionText} staff member`,
        icon: "error",
        confirmButtonColor: "#c29076",
      });
    }
  } catch (error) {
    console.error("Error toggling staff status:", error);
    Swal.fire({
      title: "Error",
      text: `Failed to ${actionText} staff member. Please try again.`,
      icon: "error",
      confirmButtonColor: "#c29076",
    });
  }
}

/**
 * Close staff modal
 */
function closeStaffModal() {
  if (staffModal) {
    staffModal.hide();
  }
  document.getElementById("staffForm").reset();

  // Reset image upload area
  const imagePreviewContainer = document.getElementById(
    "imagePreviewContainer"
  );
  const imageUploadArea = document.getElementById("imageUploadArea");
  const staffImageInput = document.getElementById("staffImage");
  const previewFileName = document.getElementById("previewFileName");

  if (imagePreviewContainer) {
    imagePreviewContainer.style.display = "none";
  }
  if (imageUploadArea) {
    imageUploadArea.style.display = "flex";
  }
  if (staffImageInput) {
    staffImageInput.value = "";
  }
  if (previewFileName) {
    previewFileName.textContent = "";
  }

  clearErrors();
}

/**
 * Handle form submission
 */
async function handleFormSubmit(e) {
  e.preventDefault();
  clearErrors();

  const isEdit = document.getElementById("isEdit").value === "1";

  // Client-side validation
  const staffEmail = document.getElementById("staffEmail").value.trim();
  const phone = document.getElementById("phone").value.trim();
  const firstName = document.getElementById("firstName").value.trim();
  const lastName = document.getElementById("lastName").value.trim();
  const role = document.getElementById("role").value;
  const password = document.getElementById("password").value;
  const passwordRequired =
    document.getElementById("passwordRequired").textContent === "*";

  // Validate required fields
  if (!staffEmail) {
    displayErrors({ staff_email: "Email is required" });
    return;
  }
  if (!phone) {
    displayErrors({ phone: "Phone number is required" });
    return;
  }
  if (!firstName) {
    displayErrors({ first_name: "First name is required" });
    return;
  }
  if (!lastName) {
    displayErrors({ last_name: "Last name is required" });
    return;
  }
  if (!role) {
    displayErrors({ role: "Role is required" });
    return;
  }
  if (passwordRequired && !password) {
    displayErrors({ password: "Password is required" });
    return;
  }

  const formData = new FormData();

  formData.append("csrf_token", CSRF_TOKEN);
  formData.append("staff_email", staffEmail);
  formData.append("phone", phone);
  formData.append("first_name", firstName);
  formData.append("last_name", lastName);
  formData.append("role", role);
  formData.append("bio", document.getElementById("bio").value.trim());

  // Add password - required for create, optional for edit
  // Always append password field (even if empty, server will validate)
  formData.append("password", password || "");

  // Add is_active status
  const activeToggle = document.getElementById("isActiveToggle");
  if (activeToggle) {
    formData.append("is_active", activeToggle.checked ? "1" : "0");
  }

  // Add image if selected
  const imageFile = document.getElementById("staffImage").files[0];
  if (imageFile) {
    formData.append("staff_image", imageFile);
  }

  const url = isEdit
    ? "../../api/admin/staff/update.php"
    : "../../api/admin/staff/create.php";

  // Debug: Log form data being sent
  console.log("Submitting staff form:", {
    isEdit,
    url,
    staffEmail,
    firstName,
    lastName,
    phone,
    role,
    passwordLength: password ? password.length : 0,
    hasImage: !!imageFile,
    isActive: activeToggle ? activeToggle.checked : false,
  });

  try {
    // Use POST for both create and update to properly handle FormData with file uploads
    // PHP's $_POST and $_FILES are only populated for POST requests
    const response = await fetch(url, {
      method: "POST",
      credentials: "include",
      body: formData,
    });

    // Check if response is ok
    if (!response.ok) {
      const errorText = await response.text();
      console.error("API Error Response:", errorText);
      try {
        const errorData = JSON.parse(errorText);
        if (errorData.error?.details) {
          displayErrors(errorData.error.details);
        } else {
          Swal.fire({
            title: "Error",
            text:
              errorData.error?.message || `Server error: ${response.status}`,
            icon: "error",
            confirmButtonColor: "#c29076",
          });
        }
      } catch (e) {
        Swal.fire({
          title: "Error",
          text: `Failed to save staff. Server returned: ${response.status}`,
          icon: "error",
          confirmButtonColor: "#c29076",
        });
      }
      return;
    }

    // Try to parse JSON response
    let data;
    try {
      const responseText = await response.text();
      console.log("API Response:", responseText);
      data = JSON.parse(responseText);
    } catch (parseError) {
      console.error("Failed to parse JSON response:", parseError);
      Swal.fire({
        title: "Error",
        text: "Invalid response from server. Please check the console for details.",
        icon: "error",
        confirmButtonColor: "#c29076",
      });
      return;
    }

    if (data.success) {
      // Close modal immediately
      closeStaffModal();

      // Show success message
      Swal.fire({
        title: "Success!",
        text:
          data.message ||
          (isEdit
            ? "Staff updated successfully"
            : "Staff created successfully"),
        icon: "success",
        confirmButtonColor: "#c29076",
        timer: 2000,
        showConfirmButton: true,
      }).then(() => {
        location.reload(); // Reload to show updated data
      });
    } else {
      if (data.error?.details) {
        displayErrors(data.error.details);
        // Also show a general error message
        Swal.fire({
          title: "Validation Error",
          text: data.error?.message || "Please check the form for errors",
          icon: "error",
          confirmButtonColor: "#c29076",
        });
      } else {
        Swal.fire({
          title: "Error",
          text: data.error?.message || "Failed to save staff",
          icon: "error",
          confirmButtonColor: "#c29076",
        });
      }
    }
  } catch (error) {
    console.error("Error saving staff:", error);
    Swal.fire({
      title: "Error",
      text: "Failed to save staff. Please check the console for details.",
      icon: "error",
      confirmButtonColor: "#c29076",
    });
  }
}

/**
 * Handle image preview
 */
function handleImagePreview(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function (event) {
      const imagePreviewContainer = document.getElementById(
        "imagePreviewContainer"
      );
      const imageUploadArea = document.getElementById("imageUploadArea");
      const imagePreview = document.getElementById("imagePreview");
      const previewFileName = document.getElementById("previewFileName");

      if (imagePreview) {
        imagePreview.src = event.target.result;
      }
      if (previewFileName) {
        previewFileName.textContent = file.name;
      }
      if (imagePreviewContainer) {
        imagePreviewContainer.style.display = "block";
      }
      if (imageUploadArea) {
        imageUploadArea.style.display = "none";
      }
    };
    reader.readAsDataURL(file);
  } else {
    const imagePreviewContainer = document.getElementById(
      "imagePreviewContainer"
    );
    const imageUploadArea = document.getElementById("imageUploadArea");
    if (imagePreviewContainer) {
      imagePreviewContainer.style.display = "none";
    }
    if (imageUploadArea) {
      imageUploadArea.style.display = "flex";
    }
  }
}

/**
 * Remove selected image and show upload area again
 */
function removeImage() {
  const imagePreviewContainer = document.getElementById(
    "imagePreviewContainer"
  );
  const imageUploadArea = document.getElementById("imageUploadArea");
  const staffImageInput = document.getElementById("staffImage");
  const previewFileName = document.getElementById("previewFileName");

  if (imagePreviewContainer) {
    imagePreviewContainer.style.display = "none";
  }
  if (imageUploadArea) {
    imageUploadArea.style.display = "flex";
  }
  if (staffImageInput) {
    staffImageInput.value = "";
  }
  if (previewFileName) {
    previewFileName.textContent = "";
  }
}

// Image input change handler and upload area click handler
document.addEventListener("DOMContentLoaded", function () {
  const imageInput = document.getElementById("staffImage");
  if (imageInput) {
    imageInput.addEventListener("change", handleImagePreview);
  }

  // Click handler for image upload area
  const imageUploadArea = document.getElementById("imageUploadArea");
  if (imageUploadArea) {
    imageUploadArea.addEventListener("click", function (e) {
      // Don't trigger if clicking the button directly
      if (e.target.closest(".btn")) {
        return;
      }
      const fileInput = document.getElementById("staffImage");
      if (fileInput) {
        fileInput.click();
      }
    });
  }
});

/**
 * Open delete confirmation modal
 */
function openDeleteModal(staffEmail) {
  const member = staffData.find((s) => s.email === staffEmail);

  if (!member) {
    Swal.fire({
      title: "Error",
      text: "Staff member not found",
      icon: "error",
      confirmButtonColor: "#c29076",
    });
    return;
  }

  currentStaffEmail = staffEmail;

  Swal.fire({
    title: "Delete Staff Member?",
    html: `Are you sure you want to delete <strong>${escapeHtml(
      member.name
    )}</strong>?<br><br>This action cannot be undone.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#E76F51",
    cancelButtonColor: "#6C757D",
    confirmButtonText: "Yes, delete it!",
    cancelButtonText: "Cancel",
  }).then((result) => {
    if (result.isConfirmed) {
      confirmDelete();
    }
  });
}

/**
 * Confirm delete
 */
async function confirmDelete() {
  if (!currentStaffEmail) return;

  try {
    const response = await fetch("../../api/admin/staff/delete.php", {
      method: "DELETE",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        csrf_token: CSRF_TOKEN,
        staff_email: currentStaffEmail,
      }),
    });

    const data = await response.json();

    if (data.success) {
      Swal.fire({
        title: "Deleted!",
        text: data.message || "Staff member has been deleted.",
        icon: "success",
        confirmButtonColor: "#c29076",
      }).then(() => {
        location.reload(); // Reload to show updated data
      });
    } else {
      Swal.fire({
        title: "Error",
        text: data.error?.message || "Failed to delete staff",
        icon: "error",
        confirmButtonColor: "#c29076",
      });
    }
  } catch (error) {
    console.error("Error deleting staff:", error);
    Swal.fire({
      title: "Error",
      text: "Failed to delete staff. Please try again.",
      icon: "error",
      confirmButtonColor: "#c29076",
    });
  }
}

/**
 * Display validation errors with animation
 */
function displayErrors(errors) {
  // Clear all previous errors first
  clearErrors();

  for (const [field, message] of Object.entries(errors)) {
    const errorElement = document.getElementById(`error-${field}`);
    const inputElement =
      document.getElementById(field) ||
      document.getElementById(field.replace("_", "")) ||
      document.querySelector(`[name="${field}"]`);

    if (errorElement) {
      errorElement.textContent = message;
      errorElement.classList.add("show");
      errorElement.style.display = "block";

      // Trigger animation by removing and re-adding class
      setTimeout(() => {
        errorElement.classList.remove("show");
        setTimeout(() => {
          errorElement.classList.add("show");
        }, 10);
      }, 10);
    }

    // Add invalid class to input field
    if (inputElement) {
      inputElement.classList.add("is-invalid");

      // Remove invalid class when user starts typing
      inputElement.addEventListener(
        "input",
        function removeInvalid() {
          inputElement.classList.remove("is-invalid");
          const errorEl = document.getElementById(`error-${field}`);
          if (errorEl) {
            errorEl.classList.remove("show");
            errorEl.style.display = "none";
          }
          inputElement.removeEventListener("input", removeInvalid);
        },
        { once: true }
      );
    }
  }

  // Scroll to first error
  const firstError = document.querySelector(".error-message.show");
  if (firstError) {
    firstError.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }
}

/**
 * Clear all error messages
 */
function clearErrors() {
  const errorElements = document.querySelectorAll(".error-message");
  errorElements.forEach((el) => {
    el.textContent = "";
    el.classList.remove("show");
    el.style.display = "none";
  });

  // Remove invalid class from all inputs
  const invalidInputs = document.querySelectorAll(".is-invalid");
  invalidInputs.forEach((input) => {
    input.classList.remove("is-invalid");
  });
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
  const passwordInput = document.getElementById("password");
  const eyeIcon = document.getElementById("eyeIcon");
  const eyeOffIcon = document.getElementById("eyeOffIcon");

  if (!passwordInput || !eyeIcon || !eyeOffIcon) {
    console.error("Password toggle elements not found");
    return;
  }

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    eyeIcon.style.display = "none";
    eyeOffIcon.style.display = "inline-block";
  } else {
    passwordInput.type = "password";
    eyeIcon.style.display = "inline-block";
    eyeOffIcon.style.display = "none";
  }
}
