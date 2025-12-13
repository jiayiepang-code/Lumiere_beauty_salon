// Master Calendar Logic: drag-and-drop rescheduling, conflict checks, and modal details
// Minimal scaffolding to enable interactions; integrates with future API endpoints.

document.addEventListener('DOMContentLoaded', () => {
  initCalendarInteractions();
});

function initCalendarInteractions() {
  const events = document.querySelectorAll('.calendar-event');
  events.forEach(ev => {
    ev.setAttribute('draggable', 'true');
    ev.addEventListener('dragstart', onDragStart);
  });

  const slots = document.querySelectorAll('.calendar-slot');
  slots.forEach(slot => {
    slot.addEventListener('dragover', (e) => e.preventDefault());
    slot.addEventListener('drop', onDropReschedule);
  });

  // Click to open booking details
  document.querySelectorAll('.calendar-event').forEach(ev => {
    ev.addEventListener('click', () => openBookingDetails(ev.dataset.bookingId));
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
  e.dataTransfer.effectAllowed = 'move';
}

async function onDropReschedule(e) {
  e.preventDefault();
  if (!dragData) return;
  const targetSlot = e.currentTarget.dataset.slot;
  const staffEmail = e.currentTarget.dataset.staffEmail || dragData.staffEmail;

  // Basic client-side conflict check: avoid same slot double-booking
  const occupied = !!document.querySelector(
    `.calendar-event[data-staff-email="${CSS.escape(staffEmail)}"][data-slot="${CSS.escape(targetSlot)}"]`
  );
  if (occupied) {
    toast('Slot already occupied for staff', 'error');
    return;
  }

  try {
    // Call reschedule API (to be implemented server-side)
    const resp = await fetch('../../api/admin/bookings/reschedule.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booking_id: dragData.bookingId,
        staff_email: staffEmail,
        target_slot: targetSlot,
      }),
    });
    const data = await resp.json();
    if (!resp.ok || !data.success) throw new Error(data.error?.message || 'Reschedule failed');
    // Update UI: move element
    const draggedEl = document.querySelector(`.calendar-event[data-booking-id="${CSS.escape(dragData.bookingId)}"]`);
    if (draggedEl) {
      draggedEl.dataset.slot = targetSlot;
      e.currentTarget.appendChild(draggedEl);
    }
    toast('Booking rescheduled', 'success');
  } catch (err) {
    toast(err.message, 'error');
  } finally {
    dragData = null;
  }
}

async function openBookingDetails(bookingId) {
  try {
    const resp = await fetch(`../../api/admin/bookings/details.php?booking_id=${encodeURIComponent(bookingId)}`);
    const data = await resp.json();
    if (!resp.ok || !data.success) throw new Error(data.error?.message || 'Failed to load details');
    renderBookingModal(data.booking);
  } catch (err) {
    toast(err.message, 'error');
  }
}

function renderBookingModal(booking) {
  const modal = document.getElementById('bookingModal');
  const body = modal?.querySelector('.modal-body');
  if (!modal || !body) return;
  body.innerHTML = `
    <div class="detail-grid">
      <div><strong>Customer:</strong> ${escapeHtml(booking.customer_name)} (${escapeHtml(booking.customer_phone)})</div>
      <div><strong>Staff:</strong> ${escapeHtml(booking.staff_name)}</div>
      <div><strong>Service(s):</strong> ${booking.services.map(s => escapeHtml(s.name)).join(', ')}</div>
      <div><strong>Time:</strong> ${escapeHtml(booking.start_time)} - ${escapeHtml(booking.end_time)}</div>
      <div><strong>Status:</strong> ${escapeHtml(booking.status)}</div>
      <div><strong>Price:</strong> RM ${Number(booking.total_price || 0).toFixed(2)}</div>
      <div><strong>Notes:</strong> ${escapeHtml(booking.notes || '-')}</div>
    </div>
  `;
  modal.classList.add('active');
}

function toast(message, kind='success') {
  console.log(`[${kind}]`, message);
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
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
});

// Initialize calendar
async function initializeCalendar() {
  // Set initial date filter to today
  const today = new Date().toISOString().split("T")[0];
  document.getElementById("dateFilter").value = today;

  // Load staff list for filter
  await loadStaffList();

  // Load calendar data
  await loadCalendarData();

  // Update current date display
  updateDateDisplay();
}

// Load staff list for filter dropdown
async function loadStaffList() {
  try {
    const response = await fetch("../../api/admin/staff/list.php");
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
    }
  } catch (error) {
    console.error("Error loading staff list:", error);
  }
}

// Load calendar data from API
async function loadCalendarData() {
  const loadingState = document.getElementById("loadingState");
  const emptyState = document.getElementById("emptyState");
  const calendarView = document.getElementById("calendarView");

  loadingState.style.display = "block";
  emptyState.style.display = "none";
  calendarView.style.display = "none";

  try {
    // Build query parameters
    const params = new URLSearchParams();

    const dateFilter = document.getElementById("dateFilter").value;
    const staffFilter = document.getElementById("staffFilter").value;
    const statusFilter = document.getElementById("statusFilter").value;

    if (dateFilter) {
      params.append("date", dateFilter);
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
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error?.message || "Failed to load calendar data");
    }

    if (data.success) {
      bookingsData = data.bookings || [];
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
    loadingState.style.display = "none";
    showNotification("Error loading calendar data", "error");
  }
}

// Render calendar based on current view
function renderCalendar() {
  const calendarView = document.getElementById("calendarView");

  if (currentView === "day") {
    renderDayView();
  } else if (currentView === "week") {
    renderWeekView();
  } else if (currentView === "month") {
    renderMonthView();
  }
}

// Render day view
function renderDayView() {
  const calendarView = document.getElementById("calendarView");

  // Generate time slots from 10:00 AM to 10:00 PM
  const timeSlots = [];
  for (let hour = 10; hour <= 22; hour++) {
    timeSlots.push(`${hour.toString().padStart(2, "0")}:00`);
  }

  let html = '<div class="calendar-day-view">';

  timeSlots.forEach((time) => {
    // Find bookings for this time slot
    const slotBookings = bookingsData.filter((booking) => {
      const bookingTime = booking.start_time.substring(0, 5);
      return bookingTime === time;
    });

    html += `
            <div class="time-slot">
                <div class="time-label">${formatTime(time)}</div>
                <div class="slot-bookings">
        `;

    if (slotBookings.length > 0) {
      slotBookings.forEach((booking) => {
        html += renderBookingCard(booking);
      });
    } else {
      html +=
        '<div style="color: #ccc; font-size: 13px; padding: 8px;">No bookings</div>';
    }

    html += `
                </div>
            </div>
        `;
  });

  html += "</div>";
  calendarView.innerHTML = html;
}

// Render week view (simplified - shows all bookings grouped by day)
function renderWeekView() {
  const calendarView = document.getElementById("calendarView");

  // Group bookings by date
  const bookingsByDate = {};
  bookingsData.forEach((booking) => {
    if (!bookingsByDate[booking.booking_date]) {
      bookingsByDate[booking.booking_date] = [];
    }
    bookingsByDate[booking.booking_date].push(booking);
  });

  let html =
    '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">';

  Object.keys(bookingsByDate)
    .sort()
    .forEach((date) => {
      html += `
            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <h4 style="margin-bottom: 12px; color: #333;">${formatDate(
                  date
                )}</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
        `;

      bookingsByDate[date].forEach((booking) => {
        html += renderBookingCard(booking);
      });

      html += `
                </div>
            </div>
        `;
    });

  html += "</div>";
  calendarView.innerHTML = html;
}

// Render month view (simplified - shows booking count per day)
function renderMonthView() {
  const calendarView = document.getElementById("calendarView");

  // Group bookings by date and count
  const bookingsByDate = {};
  bookingsData.forEach((booking) => {
    if (!bookingsByDate[booking.booking_date]) {
      bookingsByDate[booking.booking_date] = {
        count: 0,
        confirmed: 0,
        completed: 0,
        cancelled: 0,
      };
    }
    bookingsByDate[booking.booking_date].count++;

    if (booking.status === "confirmed")
      bookingsByDate[booking.booking_date].confirmed++;
    if (booking.status === "completed")
      bookingsByDate[booking.booking_date].completed++;
    if (booking.status === "cancelled" || booking.status === "no-show") {
      bookingsByDate[booking.booking_date].cancelled++;
    }
  });

  let html =
    '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px;">';

  Object.keys(bookingsByDate)
    .sort()
    .forEach((date) => {
      const stats = bookingsByDate[date];
      html += `
            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; cursor: pointer;" 
                 onclick="filterByDate('${date}')">
                <div style="font-weight: 600; margin-bottom: 8px; color: #333;">${formatDate(
                  date
                )}</div>
                <div style="font-size: 24px; font-weight: 700; color: #667eea; margin-bottom: 8px;">
                    ${stats.count}
                </div>
                <div style="font-size: 12px; color: #666;">
                    <div>✓ ${stats.confirmed} confirmed</div>
                    <div>✓ ${stats.completed} completed</div>
                    ${
                      stats.cancelled > 0
                        ? `<div>✗ ${stats.cancelled} cancelled</div>`
                        : ""
                    }
                </div>
            </div>
        `;
    });

  html += "</div>";
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
  const section = document.getElementById("staffScheduleSection");
  const grid = document.getElementById("staffScheduleGrid");

  if (staffSchedulesData.length === 0) {
    section.style.display = "none";
    return;
  }

  section.style.display = "block";

  let html = "";
  staffSchedulesData.forEach((schedule) => {
    html += `
            <div class="staff-schedule-card">
                <div class="staff-name">${escapeHtml(schedule.staff_name)}</div>
                <div class="schedule-time">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    ${formatTime(schedule.start_time)} - ${formatTime(
      schedule.end_time
    )}
                </div>
                <span class="schedule-status ${schedule.status}">${
      schedule.status
    }</span>
            </div>
        `;
  });

  grid.innerHTML = html;
}

// View booking details
async function viewBookingDetails(bookingId) {
  const modal = document.getElementById("bookingModal");
  const content = document.getElementById("bookingDetailsContent");

  content.innerHTML = '<div class="loading">Loading booking details...</div>';
  modal.style.display = "flex";

  try {
    const response = await fetch(
      `../../api/admin/bookings/details.php?booking_id=${bookingId}`
    );
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error?.message || "Failed to load booking details");
    }

    if (data.success) {
      renderBookingDetails(data.booking);
    }
  } catch (error) {
    console.error("Error loading booking details:", error);
    content.innerHTML = `<div style="color: #F44336; padding: 20px;">Error loading booking details</div>`;
  }
}

// Render booking details in modal
function renderBookingDetails(booking) {
  const content = document.getElementById("bookingDetailsContent");

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

  // Update active button
  document.querySelectorAll(".view-btn").forEach((btn) => {
    btn.classList.remove("active");
    if (btn.dataset.view === view) {
      btn.classList.add("active");
    }
  });

  // Re-render calendar
  if (bookingsData.length > 0) {
    renderCalendar();
  }
}

// Navigate date
function navigateDate(direction) {
  const dateFilter = document.getElementById("dateFilter");
  let date = dateFilter.value ? new Date(dateFilter.value) : new Date();

  if (direction === "prev") {
    date.setDate(date.getDate() - 1);
  } else if (direction === "next") {
    date.setDate(date.getDate() + 1);
  } else if (direction === "today") {
    date = new Date();
  }

  dateFilter.value = date.toISOString().split("T")[0];
  currentDate = date;
  updateDateDisplay();
  applyFilters();
}

// Update date display
function updateDateDisplay() {
  const dateFilter = document.getElementById("dateFilter").value;
  const date = dateFilter ? new Date(dateFilter) : new Date();

  const options = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  };
  document.getElementById("currentDate").textContent = date.toLocaleDateString(
    "en-US",
    options
  );
}

// Apply filters
function applyFilters() {
  updateDateDisplay();
  loadCalendarData();
}

// Filter by specific date (from month view)
function filterByDate(date) {
  document.getElementById("dateFilter").value = date;
  switchView("day");
  applyFilters();
}

// Utility functions
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
