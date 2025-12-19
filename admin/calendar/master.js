// Master Calendar Logic: drag-and-drop rescheduling, conflict checks, and modal details
// Minimal scaffolding to enable interactions; integrates with future API endpoints.

document.addEventListener("DOMContentLoaded", () => {
  initCalendarInteractions();
});

function initCalendarInteractions() {
  const events = document.querySelectorAll(".calendar-event");
  events.forEach((ev) => {
    ev.setAttribute("draggable", "true");
    ev.addEventListener("dragstart", onDragStart);
  });

  const slots = document.querySelectorAll(".calendar-slot");
  slots.forEach((slot) => {
    slot.addEventListener("dragover", (e) => e.preventDefault());
    slot.addEventListener("drop", onDropReschedule);
  });

  // Click to open booking details
  document.querySelectorAll(".calendar-event").forEach((ev) => {
    ev.addEventListener("click", () =>
      openBookingDetails(ev.dataset.bookingId)
    );
  });
}

let dragData = null;
function onDragStart(e) {
  const el = e.target;
  dragData = {
    bookingId: el.dataset.bookingId,
    staffEmail: el.dataset.staffEmail,
    originalSlot: el.dataset.slot,
  };
  e.dataTransfer.effectAllowed = "move";
}

async function onDropReschedule(e) {
  e.preventDefault();
  if (!dragData) return;
  const targetSlot = e.currentTarget.dataset.slot;
  const staffEmail = e.currentTarget.dataset.staffEmail || dragData.staffEmail;

  // Basic client-side conflict check: avoid same slot double-booking
  const occupied = !!document.querySelector(
    `.calendar-event[data-staff-email="${CSS.escape(
      staffEmail
    )}"][data-slot="${CSS.escape(targetSlot)}"]`
  );
  if (occupied) {
    toast("Slot already occupied for staff", "error");
    return;
  }

  try {
    // Call reschedule API (to be implemented server-side)
    const resp = await fetch("../../api/admin/bookings/reschedule.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        booking_id: dragData.bookingId,
        staff_email: staffEmail,
        target_slot: targetSlot,
      }),
    });
    const data = await resp.json();
    if (!resp.ok || !data.success)
      throw new Error(data.error?.message || "Reschedule failed");
    // Update UI: move element
    const draggedEl = document.querySelector(
      `.calendar-event[data-booking-id="${CSS.escape(dragData.bookingId)}"]`
    );
    if (draggedEl) {
      draggedEl.dataset.slot = targetSlot;
      e.currentTarget.appendChild(draggedEl);
    }
    toast("Booking rescheduled", "success");
  } catch (err) {
    toast(err.message, "error");
  } finally {
    dragData = null;
  }
}

async function openBookingDetails(bookingId) {
  try {
    const resp = await fetch(
      `../../api/admin/bookings/details.php?booking_id=${encodeURIComponent(
        bookingId
      )}`
    );
    const data = await resp.json();
    if (!resp.ok || !data.success)
      throw new Error(data.error?.message || "Failed to load details");
    renderBookingModal(data.booking);
  } catch (err) {
    toast(err.message, "error");
  }
}

function renderBookingModal(booking) {
  const modal = document.getElementById("bookingModal");
  const body = modal?.querySelector(".modal-body");
  if (!modal || !body) return;
  body.innerHTML = `
    <div class="detail-grid">
      <div><strong>Customer:</strong> ${escapeHtml(
        booking.customer_name
      )} (${escapeHtml(booking.customer_phone)})</div>
      <div><strong>Staff:</strong> ${escapeHtml(booking.staff_name)}</div>
      <div><strong>Service(s):</strong> ${booking.services
        .map((s) => escapeHtml(s.name))
        .join(", ")}</div>
      <div><strong>Time:</strong> ${escapeHtml(
        booking.start_time
      )} - ${escapeHtml(booking.end_time)}</div>
      <div><strong>Status:</strong> ${escapeHtml(booking.status)}</div>
      <div><strong>Price:</strong> RM ${Number(
        booking.total_price || 0
      ).toFixed(2)}</div>
      <div><strong>Notes:</strong> ${escapeHtml(booking.notes || "-")}</div>
    </div>
  `;
  modal.classList.add("active");
}

function toast(message, kind = "success") {
  console.log(`[${kind}]`, message);
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
// Calendar state
let currentView = "day";
let currentDate = new Date();
let bookingsData = [];
let staffSchedulesData = [];
let staffList = [];

// Initialize calendar on page load
document.addEventListener("DOMContentLoaded", function () {
  initializeCalendar();

  // Add event listeners to filter dropdowns
  const staffFilter = document.getElementById("staffFilter");
  const statusFilter = document.getElementById("statusFilter");

  if (staffFilter) {
    staffFilter.addEventListener("change", applyFilters);
  }

  if (statusFilter) {
    statusFilter.addEventListener("change", applyFilters);
  }
});

// Initialize calendar
async function initializeCalendar() {
  // Read URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const startDateParam = urlParams.get("start_date");
  const endDateParam = urlParams.get("end_date");
  const staffEmailParam = urlParams.get("staff_email");
  const statusParam = urlParams.get("status");
  const viewParam = urlParams.get("view");

  // Set initial date from URL or default to today
  if (startDateParam) {
    currentDate = new Date(startDateParam + "T12:00:00");
  } else {
    currentDate = new Date();
  }

  // Set view from URL or default to day
  if (viewParam && ["day", "week", "month"].includes(viewParam)) {
    currentView = viewParam;
  } else {
    currentView = "day";
  }

  // Update view buttons
  document
    .getElementById("viewDay")
    .classList.toggle("active", currentView === "day");
  document
    .getElementById("viewWeek")
    .classList.toggle("active", currentView === "week");
  document
    .getElementById("viewMonth")
    .classList.toggle("active", currentView === "month");

  // Update button styles
  ["viewDay", "viewWeek", "viewMonth"].forEach((id) => {
    const btn = document.getElementById(id);
    if (btn.classList.contains("active")) {
      btn.style.background = "white";
      btn.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
    } else {
      btn.style.background = "transparent";
      btn.style.boxShadow = "none";
    }
  });

  // Load staff list for filter
  await loadStaffList();

  // Set filters from URL
  if (staffEmailParam) {
    const staffFilter = document.getElementById("staffFilter");
    if (staffFilter) {
      staffFilter.value = staffEmailParam;
    }
  }

  if (statusParam) {
    const statusFilter = document.getElementById("statusFilter");
    if (statusFilter) {
      statusFilter.value = statusParam;
    }
  }

  // Load calendar data
  await loadCalendarData();

  // Update current date display
  updateDateDisplay();

  // Scroll to staff schedule section if anchor is present
  if (window.location.hash === "#staff-schedule") {
    setTimeout(() => {
      const section = document.getElementById("staffScheduleSection");
      if (section) {
        section.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }, 500);
  }
}

// Load staff list for filter dropdown
async function loadStaffList() {
  try {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:250',message:'loadStaffList entry',data:{url:'../../api/admin/staff/list.php'},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
    // #endregion
    
    const response = await fetch("../../api/admin/staff/list.php");
    
    // #region agent log
    const status = response.status;
    const contentType = response.headers.get('content-type');
    const responseText = await response.clone().text();
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:257',message:'loadStaffList response received',data:{status,contentType,responsePreview:responseText.substring(0,500),isJson:contentType?.includes('application/json'),startsWithHtml:responseText.trim().startsWith('<')},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A,E'})}).catch(()=>{});
    // #endregion
    
    // Check if response is JSON
    if (!contentType || !contentType.includes('application/json')) {
      // #region agent log
      fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:262',message:'loadStaffList non-JSON response detected',data:{contentType,responsePreview:responseText.substring(0,500)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A,E'})}).catch(()=>{});
      // #endregion
      console.error("Error loading staff list: Server returned non-JSON response", responseText.substring(0, 200));
      return;
    }
    
    const data = await response.json();

    if (data.success) {
      staffList = data.staff;
      const staffFilter = document.getElementById("staffFilter");

      data.staff.forEach((staff) => {
        if (staff.is_active) {
          const option = document.createElement("option");
          option.value = staff.staff_email;
          option.textContent = `${staff.first_name} ${staff.last_name}`;
          staffFilter.appendChild(option);
        }
      });
    } else {
      console.error("Error loading staff list:", data.error);
    }
  } catch (error) {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:278',message:'loadStaffList error caught',data:{errorMessage:error.message,errorStack:error.stack?.substring(0,300)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A,B,C,D,E'})}).catch(()=>{});
    // #endregion
    console.error("Error loading staff list:", error);
  }
}

// Load calendar data from API
async function loadCalendarData() {
  const loadingState = document.getElementById("loadingState");
  const emptyState = document.getElementById("emptyState");
  const calendarView = document.getElementById("calendarView");

  if (!loadingState || !emptyState || !calendarView) {
    console.error("Calendar elements not found in DOM");
    return;
  }

  loadingState.style.display = "block";
  emptyState.style.display = "none";
  calendarView.style.display = "none";

  try {
    // Build query parameters
    const params = new URLSearchParams();

    const staffFilter = document.getElementById("staffFilter").value;
    const statusFilter = document.getElementById("statusFilter").value;

    // Helper function to format date in local timezone (YYYY-MM-DD)
    const formatLocalDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    };

    // Format date based on current view (using local timezone to avoid UTC conversion issues)
    if (currentView === "day") {
      params.append("date", formatLocalDate(currentDate));
    } else if (currentView === "week") {
      const startOfWeek = new Date(currentDate);
      startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
      const endOfWeek = new Date(startOfWeek);
      endOfWeek.setDate(startOfWeek.getDate() + 6);
      params.append("start_date", formatLocalDate(startOfWeek));
      params.append("end_date", formatLocalDate(endOfWeek));
    } else if (currentView === "month") {
      const startOfMonth = new Date(
        currentDate.getFullYear(),
        currentDate.getMonth(),
        1
      );
      const endOfMonth = new Date(
        currentDate.getFullYear(),
        currentDate.getMonth() + 1,
        0
      );
      params.append("start_date", formatLocalDate(startOfMonth));
      params.append("end_date", formatLocalDate(endOfMonth));
    }

    if (staffFilter) {
      params.append("staff_email", staffFilter);
    }

    if (statusFilter) {
      params.append("status", statusFilter);
    }

    const response = await fetch(
      `../../api/admin/bookings/list.php?${params.toString()}`
    );

    // Check if response is JSON
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      const text = await response.text();
      console.error("Non-JSON response received:", text);
      throw new Error(
        "Server returned an invalid response. Please check the console for details."
      );
    }

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error?.message || "Failed to load calendar data");
    }

    if (data.success) {
      // Normalize customer names
      bookingsData = (data.bookings || []).map((booking) => {
        if (!booking.customer_name && booking.customer_first_name) {
          booking.customer_name = `${booking.customer_first_name} ${
            booking.customer_last_name || ""
          }`;
        }
        return booking;
      });
      staffSchedulesData = data.staff_schedules || [];

      loadingState.style.display = "none";

      if (bookingsData.length === 0) {
        emptyState.style.display = "block";
      } else {
        calendarView.style.display = "block";
        renderCalendar();
      }

      // Render staff schedules
      renderStaffSchedules();
    }
  } catch (error) {
    console.error("Error loading calendar data:", error);
    if (loadingState) loadingState.style.display = "none";
    if (emptyState) {
      emptyState.style.display = "block";
      emptyState.innerHTML = `<p style="color: #F44336;">Error loading calendar: ${error.message}</p>`;
    }
  }
}

// Render calendar based on current view
function renderCalendar() {
  const calendarView = document.getElementById("calendarView");

  if (!calendarView) {
    console.error("Calendar view element not found");
    return;
  }

  if (currentView === "day") {
    renderDayView();
  } else if (currentView === "week") {
    renderWeekView();
  } else if (currentView === "month") {
    renderMonthView();
  }
}

// Render day view - Vertical timeline 10 AM - 10 PM
function renderDayView() {
  const calendarView = document.getElementById("calendarView");

  if (!calendarView) {
    console.error("Calendar view element not found");
    return;
  }

  // Generate time slots from 10:00 AM to 10:00 PM (22:00)
  const timeSlots = [];
  for (let hour = 10; hour <= 22; hour++) {
    timeSlots.push(`${hour.toString().padStart(2, "0")}:00`);
  }

  let html = '<div class="calendar-day-timeline" id="dayTimelineContainer">';

  timeSlots.forEach((time) => {
    // Find all bookings that overlap with this hour
    const slotBookings = bookingsData.filter((booking) => {
      const bookingHour = parseInt(booking.start_time.substring(0, 2));
      const timeHour = parseInt(time.substring(0, 2));
      const endHour = parseInt(booking.expected_finish_time.substring(0, 2));
      return bookingHour <= timeHour && endHour > timeHour;
    });

    html += `
      <div class="timeline-row">
        <div class="timeline-time">${formatTime(time)}</div>
        <div class="timeline-slots">`;

    if (slotBookings.length > 0) {
      slotBookings.forEach((booking) => {
        const statusClass = getStatusClass(booking.status);
        const customer = escapeHtml(
          booking.customer_name ||
            booking.customer_first_name + " " + booking.customer_last_name
        );
        const services = booking.services || [];
        const serviceName =
          services.length > 0
            ? escapeHtml(services[0].service_name)
            : "Service";
        const durationMinutes = calculateDurationMinutes(
          booking.start_time,
          booking.expected_finish_time
        );
        const durationDisplay =
          durationMinutes !== "" ? `${durationMinutes} min` : "-";
        const statusLabel = escapeHtml(
          (booking.status || "available").replace(/-/g, " ").toUpperCase()
        );

        html += `
          <div class="admin-calendar-event ${statusClass}" role="button" tabindex="0" onclick="viewBookingDetails('${
          booking.booking_id
        }')">
            <div class="event-main">
              <div class="event-header">
                <div class="event-datetime">${formatTime(
                  booking.start_time
                )} - ${formatDate(booking.booking_date)}</div>
                <span class="status-pill ${statusClass}">${statusLabel}</span>
              </div>
              <div class="event-fields">
                <div class="event-field">
                  <span class="event-label">Customer</span>
                  <span class="event-value">${customer}</span>
                </div>
                <div class="event-field">
                  <span class="event-label">Service</span>
                  <span class="event-value">${serviceName}</span>
                </div>
                <div class="event-field">
                  <span class="event-label">Duration</span>
                  <span class="event-value">${durationDisplay}</span>
                </div>
              </div>
            </div>
            <div class="event-action-cue" aria-hidden="true" title="Click to view details">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
            </div>
          </div>`;
      });
    }

    html += `
        </div>
      </div>`;
  });

  html += "</div>";
  calendarView.innerHTML = html;

  // Add real-time timeline indicator after rendering
  addRealTimeIndicator();
}

// Add real-time timeline indicator
function addRealTimeIndicator() {
  // Only show on today's date
  const today = new Date();
  const viewingDate = new Date(currentDate);
  const isToday = today.toDateString() === viewingDate.toDateString();

  if (!isToday) return;

  const now = new Date();
  const currentHour = now.getHours();
  const currentMinute = now.getMinutes();
  const currentTimeInMinutes = currentHour * 60 + currentMinute;

  // Check if current time is within viewing hours (10 AM - 10 PM)
  const startHour = 10;
  const endHour = 22;
  const startTimeInMinutes = startHour * 60;
  const endTimeInMinutes = endHour * 60;

  if (
    currentTimeInMinutes < startTimeInMinutes ||
    currentTimeInMinutes > endTimeInMinutes
  ) {
    return; // Current time is outside viewing hours
  }

  // Find the timeline container
  const timelineContainer = document.getElementById("dayTimelineContainer");
  if (!timelineContainer) return;

  // Make timeline container relative positioned
  timelineContainer.style.position = "relative";

  // Find all timeline rows to calculate position
  const timelineRows = timelineContainer.querySelectorAll(".timeline-row");
  if (timelineRows.length === 0) return;

  // Calculate which row the current time falls into and the position within that row
  const totalSlots = 13; // 10 AM to 10 PM = 13 hours
  const slotIndex = Math.floor(
    (currentTimeInMinutes - startTimeInMinutes) / 60
  );
  const minutesIntoSlot = (currentTimeInMinutes - startTimeInMinutes) % 60;
  const slotPercent = (minutesIntoSlot / 60) * 100;

  // Get the row element
  if (slotIndex >= timelineRows.length) return;
  const targetRow = timelineRows[slotIndex];
  const rowRect = targetRow.getBoundingClientRect();
  const containerRect = timelineContainer.getBoundingClientRect();

  // Calculate absolute position within the container
  const rowTop = targetRow.offsetTop;
  const rowHeight = rowRect.height;
  const absoluteTop = rowTop + (rowHeight * slotPercent) / 100;
  const totalHeight = timelineContainer.scrollHeight;
  const positionPercent = (absoluteTop / totalHeight) * 100;

  // Create indicator element
  const indicator = document.createElement("div");
  indicator.className = "real-time-indicator";
  indicator.style.cssText = `
    position: absolute;
    left: 0;
    right: 0;
    top: ${positionPercent}%;
    height: 2px;
    background: #c29076;
    z-index: 100;
    pointer-events: none;
  `;

  // Create dot
  const dot = document.createElement("div");
  dot.style.cssText = `
    position: absolute;
    left: 78px;
    top: -4px;
    width: 10px;
    height: 10px;
    background: #c29076;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #c29076;
  `;
  indicator.appendChild(dot);

  timelineContainer.appendChild(indicator);
}

// Render week view - Horizontal table with days as columns, time as rows
function renderWeekView() {
  const calendarView = document.getElementById("calendarView");

  if (!calendarView) {
    console.error("Calendar view element not found");
    return;
  }

  // Get week start (Sunday) and generate 7 days
  const weekStart = new Date(currentDate);
  weekStart.setDate(currentDate.getDate() - currentDate.getDay());

  const weekDays = [];
  for (let i = 0; i < 7; i++) {
    const day = new Date(weekStart);
    day.setDate(weekStart.getDate() + i);
    weekDays.push(day);
  }

  // Group bookings by date and time
  const bookingsByDateTime = {};
  bookingsData.forEach((booking) => {
    const hour = booking.start_time.substring(0, 2);
    const key = `${booking.booking_date}_${hour}`;
    if (!bookingsByDateTime[key]) {
      bookingsByDateTime[key] = [];
    }
    bookingsByDateTime[key].push(booking);
  });

  // Generate time slots (10 AM - 10 PM)
  const timeSlots = [];
  for (let hour = 10; hour <= 22; hour++) {
    timeSlots.push(hour);
  }

  let html = '<div class="calendar-week-grid"><table class="week-table">';

  // Header row with day names and dates
  html += '<thead><tr><th class="time-col">Time</th>';
  weekDays.forEach((day) => {
    const dayName = day.toLocaleDateString("en-US", { weekday: "short" });
    const dayDate = day.getDate();
    const isToday = day.toDateString() === new Date().toDateString();
    html += `<th class="day-col ${isToday ? "today" : ""}">
      <div>${dayName}</div>
      <div class="day-date">${dayDate}</div>
    </th>`;
  });
  html += "</tr></thead><tbody>";

  // Time rows
  timeSlots.forEach((hour) => {
    html += `<tr><td class="time-cell">${formatTime(
      hour.toString().padStart(2, "0") + ":00"
    )}</td>`;

    weekDays.forEach((day) => {
      // Format date string using local timezone to avoid UTC conversion issues
      const dateStr = `${day.getFullYear()}-${String(
        day.getMonth() + 1
      ).padStart(2, "0")}-${String(day.getDate()).padStart(2, "0")}`;
      const key = `${dateStr}_${hour.toString().padStart(2, "0")}`;
      const bookings = bookingsByDateTime[key] || [];

      html += '<td class="booking-cell">';
      bookings.forEach((booking) => {
        const statusColor = getStatusColor(booking.status);
        const customer = escapeHtml(
          booking.customer_name ||
            booking.customer_first_name + " " + booking.customer_last_name
        );
        html += `
          <div class="week-booking-pill" 
               style="background: ${statusColor};"
               onclick="viewBookingDetails('${booking.booking_id}')">
            ${customer}
          </div>`;
      });
      html += "</td>";
    });

    html += "</tr>";
  });

  html += "</tbody></table></div>";
  calendarView.innerHTML = html;
}

// Render month view - Calendar grid with booking pills
function renderMonthView() {
  const calendarView = document.getElementById("calendarView");

  if (!calendarView) {
    console.error("Calendar view element not found");
    return;
  }

  // Get first and last day of the month
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const daysInMonth = lastDay.getDate();
  const startDay = firstDay.getDay(); // 0 = Sunday

  // Group bookings by date
  const bookingsByDate = {};
  bookingsData.forEach((booking) => {
    if (!bookingsByDate[booking.booking_date]) {
      bookingsByDate[booking.booking_date] = [];
    }
    bookingsByDate[booking.booking_date].push(booking);
  });

  let html = '<div class="calendar-month-grid">';

  // Day headers
  html += '<div class="month-header">';
  ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].forEach((day) => {
    html += `<div class="month-day-name">${day}</div>`;
  });
  html += "</div>";

  html += '<div class="month-days">';

  // Empty cells for days before month starts
  for (let i = 0; i < startDay; i++) {
    html += '<div class="month-day empty"></div>';
  }

  // Days of the month
  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month, day);
    // Format date string using local timezone to avoid UTC conversion issues
    const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(
      day
    ).padStart(2, "0")}`;
    const bookings = bookingsByDate[dateStr] || [];
    const isToday = date.toDateString() === new Date().toDateString();

    html += `<div class="month-day ${
      isToday ? "today" : ""
    }" onclick="filterByDate('${dateStr}')">`;
    html += `<div class="month-day-number">${day}</div>`;
    html += '<div class="month-day-bookings">';

    // Show up to 3 booking pills
    bookings.slice(0, 3).forEach((booking) => {
      const statusColor = getStatusColor(booking.status);
      const customer = escapeHtml(
        booking.customer_name ||
          booking.customer_first_name + " " + booking.customer_last_name
      );
      const time = formatTime(booking.start_time);
      html += `
        <div class="month-booking-pill" 
             style="background: ${statusColor};"
             onclick="event.stopPropagation(); viewBookingDetails('${booking.booking_id}')">
          <span class="pill-time">${time}</span> ${customer}
        </div>`;
    });

    // Show "+ more" if there are more bookings
    if (bookings.length > 3) {
      html += `<div class="month-booking-more">+${
        bookings.length - 3
      } more</div>`;
    }

    html += "</div></div>";
  }

  html += "</div></div>";
  calendarView.innerHTML = html;
}

// Render booking card
function renderBookingCard(booking) {
  const statusClass = booking.status.replace("-", "_");
  const services = booking.services || [];
  const serviceNames = services.map((s) => s.service_name).join(", ");
  const staffNames = [...new Set(services.map((s) => s.staff_name))].join(", ");

  return `
        <div class="booking-card status-${statusClass}" onclick="viewBookingDetails('${
    booking.booking_id
  }')">
            <div class="booking-header">
                <div>
                    <div class="booking-customer">${escapeHtml(
                      booking.customer_name
                    )}</div>
                    <div class="booking-time">${formatTime(
                      booking.start_time
                    )} - ${formatTime(booking.expected_finish_time)}</div>
                </div>
                <span class="status-badge ${booking.status}">${
    booking.status
  }</span>
            </div>
            <div class="booking-services">${escapeHtml(serviceNames)}</div>
            <div class="booking-staff">Staff: ${escapeHtml(staffNames)}</div>
        </div>
    `;
}

// Render staff schedules
function renderStaffSchedules() {
  const staffScheduleSection = document.getElementById("staffScheduleSection");
  const staffScheduleGrid = document.querySelector(".staff-schedule-grid");

  if (!staffScheduleSection || !staffScheduleGrid) {
    console.warn("Staff schedule elements not found");
    return;
  }

  // Show or hide section based on data
  if (staffSchedulesData.length === 0) {
    staffScheduleSection.style.display = "none";
    return;
  }

  let html = "";

  staffSchedulesData.forEach((schedule) => {
    const cardHtml = `
            <div class="staff-schedule-card" style="background: white; border-radius: 8px; padding: 16px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; align-items: center;">
                <h4 style="margin: 0; font-size: 16px; color: #333;">${escapeHtml(
                  schedule.staff_name
                )}</h4>
                <p style="margin: 8px 0; font-size: 14px; color: #666;">${escapeHtml(
                  schedule.working_time
                )}</p>
                <span style="padding: 4px 8px; border-radius: 4px; background: ${
                  schedule.status === "Working" ? "#4CAF50" : "#9E9E9E"
                }; color: white; font-size: 12px;">${escapeHtml(
      schedule.status
    )}</span>
            </div>
        `;
    html += cardHtml;
  });

  staffScheduleGrid.innerHTML = html;
  staffScheduleSection.style.display = "block";
}

// View booking details
async function viewBookingDetails(bookingId) {
  const modal = document.getElementById("bookingModal");
  const content = document.getElementById("bookingDetailsContent");

  if (!modal || !content) {
    console.error("Modal elements not found");
    return;
  }

  content.innerHTML = '<div class="loading">Loading booking details...</div>';
  modal.style.display = "flex";

  try {
    // #region agent log
    const url = `../../api/admin/bookings/details.php?booking_id=${bookingId}`;
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:873',message:'viewBookingDetails entry',data:{bookingId,url},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
    // #endregion
    
    const response = await fetch(url);

    // #region agent log
    const status = response.status;
    const contentType = response.headers.get("content-type");
    const responseText = await response.clone().text();
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:878',message:'viewBookingDetails response received',data:{status,contentType,responsePreview:responseText.substring(0,500),isJson:contentType?.includes('application/json'),startsWithHtml:responseText.trim().startsWith('<')},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A,E'})}).catch(()=>{});
    // #endregion

    // Check if response is JSON
    if (!contentType || !contentType.includes("application/json")) {
      // #region agent log
      fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:882',message:'viewBookingDetails non-JSON response detected',data:{contentType,responsePreview:responseText.substring(0,500)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A,E'})}).catch(()=>{});
      // #endregion
      console.error("Non-JSON response received:", responseText);
      throw new Error(
        "Server returned an invalid response. Please check the console for details."
      );
    }

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error?.message || "Failed to load booking details");
    }

    if (data.success) {
      renderBookingDetails(data.booking);
    }
  } catch (error) {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'master.js:897',message:'viewBookingDetails error caught',data:{errorMessage:error.message,errorStack:error.stack?.substring(0,300)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A,B,C,D,E'})}).catch(()=>{});
    // #endregion
    console.error("Error loading booking details:", error);
    if (content) {
      content.innerHTML = `<div style="color: #F44336; padding: 20px;">
        <strong>Error loading booking details:</strong><br>
        ${escapeHtml(error.message)}
      </div>`;
    }
  }
}

// Render booking details in modal
function renderBookingDetails(booking) {
  const content = document.getElementById("bookingDetailsContent");

  if (!content) {
    console.error("Booking details content element not found");
    return;
  }

  let html = `
        <div style="display: grid; gap: 24px;">
            <div>
                <h4 style="margin-bottom: 12px; color: #333;">Customer Information</h4>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                    <div style="margin-bottom: 8px;"><strong>Name:</strong> ${escapeHtml(
                      booking.customer_name
                    )}</div>
                    <div style="margin-bottom: 8px;"><strong>Email:</strong> ${escapeHtml(
                      booking.customer_email
                    )}</div>
                    <div><strong>Phone:</strong> ${escapeHtml(
                      booking.customer_phone
                    )}</div>
                </div>
            </div>
            
            <div>
                <h4 style="margin-bottom: 12px; color: #333;">Booking Information</h4>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                    <div style="margin-bottom: 8px;"><strong>Booking ID:</strong> ${escapeHtml(
                      booking.booking_id
                    )}</div>
                    <div style="margin-bottom: 8px;"><strong>Date:</strong> ${formatDate(
                      booking.booking_date
                    )}</div>
                    <div style="margin-bottom: 8px;"><strong>Time:</strong> ${formatTime(
                      booking.start_time
                    )} - ${formatTime(booking.expected_finish_time)}</div>
                    <div style="margin-bottom: 8px;"><strong>Status:</strong> <span class="status-badge ${
                      booking.status
                    }">${booking.status}</span></div>
                    <div style="margin-bottom: 8px;"><strong>Total Duration:</strong> ${
                      booking.total_duration_minutes
                    } minutes</div>
                    ${
                      booking.remarks
                        ? `<div style="margin-bottom: 8px;"><strong>Remarks:</strong> ${escapeHtml(
                            booking.remarks
                          )}</div>`
                        : ""
                    }
                </div>
            </div>
            
            <div>
                <h4 style="margin-bottom: 12px; color: #333;">Services</h4>
                <div style="display: flex; flex-direction: column; gap: 12px;">
    `;

  booking.services.forEach((service, index) => {
    html += `
            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; margin-bottom: 8px;">${
                  index + 1
                }. ${escapeHtml(service.service_name)}</div>
                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Category: ${escapeHtml(
                  service.service_category
                )}</div>
                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Staff: ${escapeHtml(
                  service.staff_name
                )}</div>
                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Duration: ${
                  service.quoted_duration_minutes
                } min + ${service.quoted_cleanup_minutes} min cleanup</div>
                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Price: RM ${service.quoted_price.toFixed(
                  2
                )}</div>
                ${
                  service.special_request
                    ? `<div style="font-size: 13px; color: #666; margin-top: 8px;"><strong>Special Request:</strong> ${escapeHtml(
                        service.special_request
                      )}</div>`
                    : ""
                }
            </div>
        `;
  });

  html += `
                </div>
            </div>
            
            <div>
                <h4 style="margin-bottom: 12px; color: #333;">Pricing</h4>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                    ${
                      booking.promo_code
                        ? `<div style="margin-bottom: 8px;"><strong>Promo Code:</strong> ${escapeHtml(
                            booking.promo_code
                          )}</div>`
                        : ""
                    }
                    ${
                      booking.discount_amount > 0
                        ? `<div style="margin-bottom: 8px;"><strong>Discount:</strong> -RM ${booking.discount_amount.toFixed(
                            2
                          )}</div>`
                        : ""
                    }
                    <div style="font-size: 18px; font-weight: 600; color: #667eea;"><strong>Total:</strong> RM ${booking.total_price.toFixed(
                      2
                    )}</div>
                </div>
            </div>
    `;

  if (booking.booking_history && booking.booking_history.length > 0) {
    html += `
            <div>
                <h4 style="margin-bottom: 12px; color: #333;">Customer Booking History</h4>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; max-height: 200px; overflow-y: auto;">
        `;

    booking.booking_history.forEach((history) => {
      html += `
                <div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
                    <div style="font-size: 13px;">
                        <strong>${formatDate(
                          history.booking_date
                        )}</strong> at ${formatTime(history.start_time)}
                        - ${
                          history.service_count
                        } service(s) - RM ${history.total_price.toFixed(2)}
                        <span class="status-badge ${
                          history.status
                        }" style="margin-left: 8px;">${history.status}</span>
                    </div>
                </div>
            `;
    });

    html += `
                </div>
            </div>
        `;
  }

  html += "</div>";

  content.innerHTML = html;
}

// Close booking modal
function closeBookingModal() {
  document.getElementById("bookingModal").style.display = "none";
}

// Switch calendar view
function switchView(view) {
  currentView = view;

  // Update button styles
  document.getElementById("viewDay").classList.toggle("active", view === "day");
  document
    .getElementById("viewWeek")
    .classList.toggle("active", view === "week");
  document
    .getElementById("viewMonth")
    .classList.toggle("active", view === "month");

  // Update buttons background
  ["viewDay", "viewWeek", "viewMonth"].forEach((id) => {
    const btn = document.getElementById(id);
    if (btn.classList.contains("active")) {
      btn.style.background = "white";
      btn.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
    } else {
      btn.style.background = "transparent";
      btn.style.boxShadow = "none";
    }
  });

  // Reload calendar with new view
  loadCalendarData();
  updateDateDisplay();
}

// Navigate date
function changeDate(direction) {
  if (currentView === "day") {
    currentDate.setDate(currentDate.getDate() + direction);
  } else if (currentView === "week") {
    currentDate.setDate(currentDate.getDate() + direction * 7);
  } else if (currentView === "month") {
    currentDate.setMonth(currentDate.getMonth() + direction);
  }

  loadCalendarData();
  updateDateDisplay();
}

// Go to today
function goToToday() {
  currentDate = new Date();
  loadCalendarData();
  updateDateDisplay();
}

// Update date display
function updateDateDisplay() {
  const display = document.getElementById("currentDateDisplay");
  const options = { year: "numeric", month: "long", day: "numeric" };

  if (currentView === "day") {
    display.textContent = currentDate.toLocaleDateString("en-US", options);
  } else if (currentView === "week") {
    const startOfWeek = new Date(currentDate);
    startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    display.textContent = `${startOfWeek.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
    })} - ${endOfWeek.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    })}`;
  } else if (currentView === "month") {
    display.textContent = currentDate.toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
    });
  }
}

// Apply filters
function applyFilters() {
  loadCalendarData();
}

// Filter by specific date (from month view)
function filterByDate(date) {
  currentDate = new Date(date + "T12:00:00"); // Add time to avoid timezone issues
  currentView = "day";

  // Update view buttons
  document.getElementById("viewDay").classList.add("active");
  document.getElementById("viewWeek").classList.remove("active");
  document.getElementById("viewMonth").classList.remove("active");

  // Update button styles
  ["viewDay", "viewWeek", "viewMonth"].forEach((id) => {
    const btn = document.getElementById(id);
    if (btn.classList.contains("active")) {
      btn.style.background = "white";
      btn.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
    } else {
      btn.style.background = "transparent";
      btn.style.boxShadow = "none";
    }
  });

  updateDateDisplay();
  loadCalendarData();
}

// Utility functions
function calculateDurationMinutes(startTime, endTime) {
  if (!startTime || !endTime) return "";
  const [startHour, startMinute] = startTime.split(":").map(Number);
  const [endHour, endMinute] = endTime.split(":").map(Number);
  if (
    Number.isNaN(startHour) ||
    Number.isNaN(startMinute) ||
    Number.isNaN(endHour) ||
    Number.isNaN(endMinute)
  ) {
    return "";
  }
  return Math.max(0, endHour * 60 + endMinute - (startHour * 60 + startMinute));
}

function formatTime(time) {
  if (!time) return "";
  const [hours, minutes] = time.split(":");
  const hour = parseInt(hours);
  const ampm = hour >= 12 ? "PM" : "AM";
  const displayHour = hour % 12 || 12;
  return `${displayHour}:${minutes} ${ampm}`;
}

function formatDate(dateStr) {
  const date = new Date(dateStr + "T00:00:00");
  const options = { year: "numeric", month: "short", day: "numeric" };
  return date.toLocaleDateString("en-US", options);
}

function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function getStatusClass(status) {
  if (!status) return "status-available";
  return `status-${String(status).toLowerCase().replace(/\s+/g, "-")}`;
}

function getStatusColor(status) {
  const colors = {
    confirmed: "#69B578",
    completed: "#4A90E2",
    cancelled: "#D0021B", // Red for cancelled
    "no-show": "#9E9E9E", // Grey for no-show
    available: "#A0A0A0",
  };
  return colors[status] || colors.available;
}

function showNotification(message, type = "info") {
  // Simple notification (can be enhanced with a toast library)
  alert(message);
}

// Close modal when clicking outside
window.onclick = function (event) {
  const modal = document.getElementById("bookingModal");
  if (event.target === modal) {
    closeBookingModal();
  }
};
