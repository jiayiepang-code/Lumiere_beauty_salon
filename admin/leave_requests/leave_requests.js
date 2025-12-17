let isProcessing = false;
let allRequests = [];
let currentMonth = new Date().getMonth() + 1;
let currentYear = 0; // Start with no year filter (0 = unselected)

// Format date helper
function formatDate(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  const options = { year: "numeric", month: "short", day: "numeric" };
  return date.toLocaleDateString("en-US", options);
}

// Format date range
function formatDateRange(startDate, endDate) {
  if (!startDate) return "N/A";
  const start = formatDate(startDate);
  if (!endDate || startDate === endDate) {
    return start;
  }
  const end = formatDate(endDate);
  return `${start} - ${end}`;
}

// Format datetime for submitted
function formatDateTime(dateTimeString) {
  if (!dateTimeString) return "N/A";
  const date = new Date(dateTimeString);
  const options = {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  };
  return date.toLocaleDateString("en-US", options);
}

// Initialize year dropdown
function initializeYearFilter() {
  const yearFilter = document.getElementById("yearFilter");
  if (!yearFilter) return;

  const nowYear = new Date().getFullYear();
  // Generate a wider range to improve flexibility
  const startYear = nowYear - 5;
  const endYear = nowYear + 5;

  yearFilter.innerHTML = "";

  // Add "-unselected-" option first
  const unselectedOption = document.createElement("option");
  unselectedOption.value = "";
  unselectedOption.textContent = "- unselected -";
  unselectedOption.selected = true; // Default to unselected
  yearFilter.appendChild(unselectedOption);

  for (let year = startYear; year <= endYear; year++) {
    const option = document.createElement("option");
    option.value = year;
    option.textContent = year;
    yearFilter.appendChild(option);
  }
}

// Set current month in filter
function setCurrentMonth() {
  const monthFilter = document.getElementById("monthFilter");
  if (monthFilter) {
    const month = String(currentMonth).padStart(2, "0");
    monthFilter.value = month;
  }
}

// Update filter display
function updateFilterDisplay() {
  const filterDisplay = document.getElementById("filterDisplay");
  if (!filterDisplay) return;

  if (currentYear === 0) {
    if (currentMonth === 0) {
      filterDisplay.textContent = `Showing all pending requests (no filters)`;
    } else {
      const monthNames = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
      ];
      const monthName = monthNames[currentMonth - 1];
      filterDisplay.textContent = `Showing statistics for ${monthName} (all years)`;
    }
  } else if (currentMonth === 0) {
    filterDisplay.textContent = `Showing statistics for ${currentYear}`;
  } else {
    const monthNames = [
      "January",
      "February",
      "March",
      "April",
      "May",
      "June",
      "July",
      "August",
      "September",
      "October",
      "November",
      "December",
    ];
    const monthName = monthNames[currentMonth - 1];
    filterDisplay.textContent = `Showing statistics for ${monthName} ${currentYear}`;
  }
}

async function fetchLeaveRequests() {
  const tbody = document.getElementById("leaveRequestsTableBody");
  const emptyState = document.getElementById("leaveRequestsEmpty");
  const tableWrapper = document.getElementById("leaveRequestsTableWrapper");

  if (!tbody) return;

  tbody.innerHTML =
    '<tr><td colspan="8" style="text-align:center; padding: 24px; color:#999;">Loading leave requests...</td></tr>';

  try {
    // Construct URL with query parameters
    const monthParam = currentMonth === 0 ? "" : currentMonth;
    const yearParam = currentYear === 0 ? "" : currentYear;

    let apiUrl = `${LEAVE_REQUESTS_API_BASE}/list.php`;
    const params = [];

    if (monthParam !== "") {
      params.push(`month=${monthParam}`);
    }
    if (yearParam !== "") {
      params.push(`year=${yearParam}`);
    }

    if (params.length > 0) {
      apiUrl += "?" + params.join("&");
    }

    const response = await fetch(apiUrl, {
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error("Failed to load leave requests");
    }

    const data = await response.json();

    allRequests = data.requests || [];
    const stats = data.stats || {};

    // Always ensure year dropdown has "-unselected-" option and proper years
    const yearFilter = document.getElementById("yearFilter");
    if (yearFilter) {
      // Preserve the current selection, handling empty string for "-unselected-"
      const prevSelectedValue = yearFilter.value || "";
      const prevSelected =
        prevSelectedValue === "" ? 0 : parseInt(prevSelectedValue, 10);
      const nowYear = new Date().getFullYear();

      // Create a default range (current year ± 5 years) to ensure future years are always available
      const defaultStartYear = nowYear - 5;
      const defaultEndYear = nowYear + 5;
      const defaultYears = [];
      for (let y = defaultStartYear; y <= defaultEndYear; y++) {
        defaultYears.push(y);
      }

      // Merge API years with default years if available, removing duplicates and sorting
      let mergedYears = defaultYears;
      if (
        Array.isArray(data.available_years) &&
        data.available_years.length > 0
      ) {
        mergedYears = [
          ...new Set([...defaultYears, ...data.available_years]),
        ].sort((a, b) => a - b);
      }

      yearFilter.innerHTML = "";

      // Always add "-unselected-" option first
      const unselectedOption = document.createElement("option");
      unselectedOption.value = "";
      unselectedOption.textContent = "- unselected -";
      yearFilter.appendChild(unselectedOption);

      mergedYears.forEach((y) => {
        const option = document.createElement("option");
        option.value = y;
        option.textContent = y;
        yearFilter.appendChild(option);
      });

      // Prefer keeping prevSelected if present; otherwise use currentYear
      if (prevSelected === 0 || prevSelected === null || isNaN(prevSelected)) {
        yearFilter.value = "";
        currentYear = 0;
      } else if (mergedYears.includes(prevSelected)) {
        yearFilter.value = String(prevSelected);
        currentYear = prevSelected;
      } else if (mergedYears.includes(currentYear) && currentYear !== 0) {
        yearFilter.value = String(currentYear);
      } else {
        // Fallback to current year or last available, or unselected if currentYear is 0
        if (currentYear === 0 || isNaN(currentYear)) {
          yearFilter.value = "";
          currentYear = 0;
        } else {
          const fallback = mergedYears.includes(nowYear)
            ? nowYear
            : mergedYears[mergedYears.length - 1];
          yearFilter.value = String(fallback);
          currentYear = fallback;
        }
      }
    }

    // Update stats based on current filter
    updateStatsForMonth(stats);

    // Filter and render requests
    filterAndRenderRequests();

    // Update filter display
    updateFilterDisplay();
  } catch (err) {
    console.error(err);
    tbody.innerHTML =
      '<tr><td colspan="8" style="text-align:center; padding: 24px; color:#b91c1c;">Failed to load leave requests. Please refresh the page.</td></tr>';
  }
}

function updateStatsForMonth(stats) {
  // Use stats from API which are already filtered by month/year
  document.getElementById("pendingCount").textContent =
    stats.pending_count ?? 0;
  document.getElementById("approvedThisMonth").textContent =
    stats.approved_this_month ?? 0;
  document.getElementById("rejectedThisMonth").textContent =
    stats.rejected_this_month ?? 0;
}

function filterAndRenderRequests() {
  const tbody = document.getElementById("leaveRequestsTableBody");
  const emptyState = document.getElementById("leaveRequestsEmpty");
  const tableWrapper = document.getElementById("leaveRequestsTableWrapper");
  const searchInput = document.getElementById("searchInput");

  if (!tbody) return;

  // Get search term
  const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : "";

  // Filter requests - show all pending requests, filter by search term only
  let filteredRequests = allRequests.filter((req) => {
    // Show only pending requests
    if (req.status !== "pending") return false;

    // Filter by search term
    if (searchTerm) {
      const searchableText = `${req.staff_name || ""} ${
        req.staff_email || ""
      } ${req.leave_type || ""} ${req.reason || ""}`.toLowerCase();
      if (!searchableText.includes(searchTerm)) {
        return false;
      }
    }

    return true;
  });

  if (filteredRequests.length === 0) {
    tbody.innerHTML = "";
    emptyState.style.display = "block";
    tableWrapper.style.display = "none";
    return;
  }

  emptyState.style.display = "none";
  tableWrapper.style.display = "block";
  tbody.innerHTML = "";

  filteredRequests.forEach((req) => {
    const tr = document.createElement("tr");
    tr.setAttribute(
      "data-searchable",
      `${req.staff_name || ""} ${req.staff_email || ""} ${
        req.leave_type || ""
      } ${req.reason || ""}`.toLowerCase()
    );
    const reasonShort =
      (req.reason || "").length > 60
        ? (req.reason || "").substring(0, 57) + "..."
        : req.reason || "";

    tr.innerHTML = `
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:32px;height:32px;border-radius:50%;background:#f5e9e2;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:600;color:#8b5e3c;">
                            ${getInitials(req.staff_name)}
                        </div>
                        <div>
                            <div style="font-weight:500;">${escapeHtml(
                              req.staff_name || ""
                            )}</div>
                            <div style="font-size:0.8rem;color:#6b7280;">${escapeHtml(
                              req.staff_email || ""
                            )}</div>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(req.leave_type || "")}</td>
                <td class="date-range-cell">${escapeHtml(
                  formatDateRange(req.start_date, req.end_date)
                )}</td>
                <td>${escapeHtml(req.duration_label || "")}</td>
                <td>
                    <span class="reason-text" title="${escapeHtml(
                      req.reason || ""
                    )}">
                        ${escapeHtml(reasonShort)}
                    </span>
                </td>
                <td class="submitted-cell">${escapeHtml(
                  formatDateTime(req.submitted_at || req.created_at_raw)
                )}</td>
                <td>
                    <span class="badge-status badge-pending">Pending</span>
                </td>
                <td>
                    <div class="action-buttons" style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                        <button class="btn-approve" data-id="${req.id}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Approve
                        </button>
                        <button class="btn-reject" data-id="${req.id}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Reject
                        </button>
                    </div>
                </td>
            `;

    tbody.appendChild(tr);
  });

  attachActionHandlers();
}

function attachActionHandlers() {
  const approveButtons = document.querySelectorAll(".btn-approve");
  const rejectButtons = document.querySelectorAll(".btn-reject");

  approveButtons.forEach((btn) => {
    btn.addEventListener("click", () => handleAction(btn, "approve"));
  });

  rejectButtons.forEach((btn) => {
    btn.addEventListener("click", () => handleAction(btn, "reject"));
  });
}

async function handleAction(button, action) {
  if (isProcessing) return;

  const requestId = button.getAttribute("data-id");
  if (!requestId) return;

  // For approve action, check for conflicts first
  if (action === "approve") {
    try {
      const conflictResponse = await fetch(
        `${LEAVE_REQUESTS_API_BASE}/check_conflicts.php?request_id=${requestId}`,
        {
          credentials: "same-origin",
        }
      );

      if (conflictResponse.ok) {
        const conflictData = await conflictResponse.json();

        if (
          conflictData.has_conflicts &&
          conflictData.conflicting_bookings.length > 0
        ) {
          // Build booking list HTML
          const bookingList = conflictData.conflicting_bookings
            .map(
              (b) =>
                `<div style="padding: 8px; margin: 4px 0; background: #f8f9fa; border-radius: 4px;">
                  <strong>${escapeHtml(b.customer_name)}</strong><br>
                  <small>${formatDate(b.booking_date)} at ${
                  b.start_time
                }</small><br>
                  <small style="color: #6c757d;">${escapeHtml(
                    b.services
                  )}</small>
                </div>`
            )
            .join("");

          const conflictResult = await Swal.fire({
            title: "⚠️ Conflicting Bookings Detected",
            html: `
              <p style="text-align: left; margin-bottom: 15px;">
                <strong>${conflictData.conflict_count} booking(s)</strong> will be affected by this leave approval:
              </p>
              <div style="max-height: 300px; overflow-y: auto; text-align: left; margin: 15px 0;">
                ${bookingList}
              </div>
              <p style="color: #f5576c; font-weight: bold; margin-top: 15px;">
                ⚠️ Customers will be notified via email to reschedule/reassign their appointments.
              </p>
              <p style="margin-top: 10px; font-size: 0.9em; color: #6c757d;">
                Do you want to proceed with approval?
              </p>
            `,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#f5576c",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, Approve & Notify Customers",
            cancelButtonText: "Cancel",
            width: "600px",
          });

          if (!conflictResult.isConfirmed) {
            return; // User cancelled
          }
        }
      }
    } catch (err) {
      console.error("Error checking conflicts:", err);
      // Continue with normal approval flow if conflict check fails
    }
  }

  // For reject action, show confirmation dialog
  if (action === "reject") {
    const result = await Swal.fire({
      title: "Reject Leave Request?",
      text: "Are you sure you want to reject this leave request?",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#ef4444",
      cancelButtonColor: "#6b7280",
      confirmButtonText: "Yes, Reject",
      cancelButtonText: "Cancel",
    });

    if (!result.isConfirmed) {
      return;
    }
  }

  const row = button.closest("tr");
  const approveBtn = row.querySelector(".btn-approve");
  const rejectBtn = row.querySelector(".btn-reject");

  approveBtn.disabled = true;
  rejectBtn.disabled = true;
  isProcessing = true;

  // Show loading
  Swal.fire({
    title: "Processing...",
    text: "Please wait",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const response = await fetch(`${LEAVE_REQUESTS_API_BASE}/update.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
      body: JSON.stringify({
        request_id: requestId,
        action,
      }),
    });

    // Check if response is JSON
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      const textResponse = await response.text();
      console.error("Non-JSON response:", textResponse);
      throw new Error("Server returned an invalid response. Please check the error log.");
    }

    const data = await response.json();

    if (!response.ok || data.error) {
      throw new Error(data.error || "Action failed");
    }

    // Remove row from table after successful processing
    row.remove();

    // Refresh data
    await fetchLeaveRequests();

    // Show success message
    if (action === "approve") {
      let successMessage = "The staff schedule has been successfully updated.";
      
      if (data.conflict_count > 0) {
        successMessage += `\n\n${data.conflict_count} customer(s) have been notified via email.`;
        if (data.emails_failed > 0) {
          successMessage += `\n⚠️ ${data.emails_failed} email(s) failed to send.`;
        }
      }

      Swal.fire({
        title: "Leave Request Approved",
        text: successMessage,
        icon: "success",
        confirmButtonColor: "#22c55e",
      });
    } else {
      Swal.fire({
        title: "Success!",
        text: "Leave request has been rejected.",
        icon: "success",
        confirmButtonColor: "#c29076",
      });
    }
  } catch (err) {
    console.error(err);
    Swal.fire({
      title: "Error",
      text: err.message || "Something went wrong. Please try again.",
      icon: "error",
      confirmButtonColor: "#c29076",
    });
    approveBtn.disabled = false;
    rejectBtn.disabled = false;
  } finally {
    isProcessing = false;
  }
}

function getInitials(name) {
  if (!name) return "";
  const parts = name.trim().split(" ");
  if (parts.length === 1) {
    return parts[0].substring(0, 2).toUpperCase();
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function escapeHtml(value) {
  if (value === null || value === undefined) return "";
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  // Set month first, year will be populated from API if available
  setCurrentMonth();
  initializeYearFilter();
  fetchLeaveRequests();

  // Search input handler
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        filterAndRenderRequests();
      }, 300);
    });
  }

  // Filter button handler
  const applyFiltersBtn = document.getElementById("applyFiltersBtn");
  if (applyFiltersBtn) {
    applyFiltersBtn.addEventListener("click", function () {
      const monthFilter = document.getElementById("monthFilter");
      const yearFilter = document.getElementById("yearFilter");

      if (monthFilter) {
        currentMonth =
          monthFilter.value === "" ? 0 : parseInt(monthFilter.value, 10);
      }
      if (yearFilter) {
        currentYear =
          yearFilter.value === "" ? 0 : parseInt(yearFilter.value, 10);
      }

      fetchLeaveRequests();
    });
  }
});
