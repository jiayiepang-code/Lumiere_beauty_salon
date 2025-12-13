// Staff management JavaScript - Client-side filtering and CRUD operations

let staffData = [];
let currentStaffEmail = null;
let staffModal = null;
let deleteModal = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
    // Extract staff data from table rows
    const rows = document.querySelectorAll('#staffTableBody tr');
    staffData = Array.from(rows).map(row => ({
        email: row.dataset.email,
        role: row.dataset.role,
        active: row.dataset.active === '1',
        name: row.dataset.name,
        element: row
    }));

    // Initialize Bootstrap modals if they exist
    const staffModalElement = document.getElementById("staffModal");
    const deleteModalElement = document.getElementById("deleteModal");
    
    if (staffModalElement) {
        staffModal = new bootstrap.Modal(staffModalElement, { backdrop: 'static' });
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

    // Image preview handler
    const imageInput = document.getElementById("staffImage");
    if (imageInput) {
        imageInput.addEventListener("change", handleImagePreview);
    }
});

/**
 * Filter staff based on search and role filter
 */
function filterStaff() {
    const searchInput = document.getElementById("searchInput");
    const roleFilter = document.getElementById("roleFilter");
    
    if (!searchInput || !roleFilter) return;

    const searchTerm = searchInput.value.toLowerCase();
    const roleFilterValue = roleFilter.value;

    staffData.forEach(({ element, email, role, active }) => {
        const text = element.textContent.toLowerCase();
        const matchesSearch = !searchTerm || text.includes(searchTerm);
        const matchesRole = !roleFilterValue || role === roleFilterValue;

        element.style.display = (matchesSearch && matchesRole) ? '' : 'none';
    });
}

/**
 * Open create modal
 */
function openCreateModal() {
    if (!staffModal) {
        Swal.fire({
            title: 'Add Staff',
            text: 'Create functionality will be implemented next',
            icon: 'info',
            confirmButtonColor: '#c29076'
        });
        return;
    }

    document.getElementById("modalTitle").textContent = "Add Staff";
    document.getElementById("isEdit").value = "0";
    document.getElementById("staffForm").reset();
    document.getElementById("staffEmail").readOnly = false;
    document.getElementById("password").required = true;
    document.getElementById("passwordRequired").textContent = "*";
    document.getElementById("passwordHint").textContent = "Min 8 chars, 1 uppercase, 1 number, 1 special character";
    document.getElementById("imagePreviewContainer").style.display = "none";
    clearErrors();
    staffModal.show();
}

/**
 * Open edit modal
 */
async function openEditModal(staffEmail) {
    if (!staffModal) {
        Swal.fire({
            title: 'Edit Staff',
            text: 'Edit functionality will be implemented next',
            icon: 'info',
            confirmButtonColor: '#c29076'
        });
        return;
    }

    try {
        // Fetch staff details
        const response = await fetch(`../../api/admin/staff/details.php?email=${encodeURIComponent(staffEmail)}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success && data.staff) {
            const member = data.staff;
            
            document.getElementById("modalTitle").textContent = "Edit Staff";
            document.getElementById("isEdit").value = "1";
            document.getElementById("staffEmail").value = member.staff_email;
            document.getElementById("staffEmail").readOnly = true;
            document.getElementById("phone").value = member.phone;
            document.getElementById("firstName").value = member.first_name;
            document.getElementById("lastName").value = member.last_name;
            document.getElementById("role").value = member.role;
            document.getElementById("bio").value = member.bio || "";

            // Make password optional for edit
            document.getElementById("password").required = false;
            document.getElementById("password").value = "";
            document.getElementById("password").placeholder = "Leave blank to keep current password";
            document.getElementById("passwordRequired").textContent = "";
            document.getElementById("passwordHint").textContent = "Leave blank to keep current password, or enter new password (min 8 chars)";

            // Show existing image if available
            if (member.staff_image) {
                document.getElementById("imagePreview").src = member.staff_image;
                document.getElementById("imagePreviewContainer").style.display = "block";
            } else {
                document.getElementById("imagePreviewContainer").style.display = "none";
            }

            clearErrors();
            staffModal.show();
        } else {
            Swal.fire({
                title: 'Error',
                text: data.error?.message || 'Failed to load staff details',
                icon: 'error',
                confirmButtonColor: '#c29076'
            });
        }
    } catch (error) {
        console.error("Error loading staff:", error);
        Swal.fire({
            title: 'Error',
            text: 'Failed to load staff details',
            icon: 'error',
            confirmButtonColor: '#c29076'
        });
    }
}

/**
 * View staff details
 */
function viewStaff(staffEmail) {
    openEditModal(staffEmail);
}

/**
 * Close staff modal
 */
function closeStaffModal() {
    if (staffModal) {
        staffModal.hide();
    }
    document.getElementById("staffForm").reset();
    document.getElementById("imagePreviewContainer").style.display = "none";
    clearErrors();
}

/**
 * Handle form submission
 */
async function handleFormSubmit(e) {
    e.preventDefault();
    clearErrors();

    const isEdit = document.getElementById("isEdit").value === "1";
    const formData = new FormData();
    
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('staff_email', document.getElementById("staffEmail").value.trim());
    formData.append('phone', document.getElementById("phone").value.trim());
    formData.append('first_name', document.getElementById("firstName").value.trim());
    formData.append('last_name', document.getElementById("lastName").value.trim());
    formData.append('role', document.getElementById("role").value);
    formData.append('bio', document.getElementById("bio").value.trim());

    // Add password if provided
    const password = document.getElementById("password").value;
    if (password) {
        formData.append('password', password);
    }

    // Add image if selected
    const imageFile = document.getElementById("staffImage").files[0];
    if (imageFile) {
        formData.append('staff_image', imageFile);
    }

    const url = isEdit
        ? "../../api/admin/staff/update.php"
        : "../../api/admin/staff/create.php";

    try {
        const response = await fetch(url, {
            method: isEdit ? "PUT" : "POST",
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message || 'Staff saved successfully',
                icon: 'success',
                confirmButtonColor: '#c29076'
            }).then(() => {
                closeStaffModal();
                location.reload(); // Reload to show updated data
            });
        } else {
            if (data.error?.details) {
                displayErrors(data.error.details);
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.error?.message || "Failed to save staff",
                    icon: 'error',
                    confirmButtonColor: '#c29076'
                });
            }
        }
    } catch (error) {
        console.error("Error saving staff:", error);
        Swal.fire({
            title: 'Error',
            text: 'Failed to save staff. Please try again.',
            icon: 'error',
            confirmButtonColor: '#c29076'
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
        reader.onload = function(event) {
            document.getElementById("imagePreview").src = event.target.result;
            document.getElementById("imagePreviewContainer").style.display = "block";
        };
        reader.readAsDataURL(file);
    }
}

/**
 * Open delete confirmation modal
 */
function openDeleteModal(staffEmail) {
    const member = staffData.find(s => s.email === staffEmail);
    
    if (!member) {
        Swal.fire({
            title: 'Error',
            text: 'Staff member not found',
            icon: 'error',
            confirmButtonColor: '#c29076'
        });
        return;
    }

    currentStaffEmail = staffEmail;

    Swal.fire({
        title: 'Delete Staff Member?',
        html: `Are you sure you want to delete <strong>${escapeHtml(member.name)}</strong>?<br><br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E76F51',
        cancelButtonColor: '#6C757D',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
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
                title: 'Deleted!',
                text: data.message || 'Staff member has been deleted.',
                icon: 'success',
                confirmButtonColor: '#c29076'
            }).then(() => {
                location.reload(); // Reload to show updated data
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.error?.message || "Failed to delete staff",
                icon: 'error',
                confirmButtonColor: '#c29076'
            });
        }
    } catch (error) {
        console.error("Error deleting staff:", error);
        Swal.fire({
            title: 'Error',
            text: 'Failed to delete staff. Please try again.',
            icon: 'error',
            confirmButtonColor: '#c29076'
        });
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
    const errorElements = document.querySelectorAll("[id^='error-']");
    errorElements.forEach((el) => {
        el.textContent = "";
        el.style.display = "none";
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
