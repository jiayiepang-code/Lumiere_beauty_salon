// Business Analytics Dashboard JavaScript

let bookingTrendsChart = null;
let popularServicesChart = null;
let currentPeriod = "weekly";
let currentStartDate = null;
let currentEndDate = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  initializePeriodButtons();
  initializeDateRangePicker();
  loadAnalyticsData();
});

// Initialize period selector buttons
function initializePeriodButtons() {
  const periodButtons = document.querySelectorAll(".period-btn");

  periodButtons.forEach((button) => {
    button.addEventListener("click", function () {
      // Remove active class from all buttons
      periodButtons.forEach((btn) => btn.classList.remove("active"));

      // Add active class to clicked button
      this.classList.add("active");

      // Update current period
      currentPeriod = this.dataset.period;

      // Clear custom date range
      currentStartDate = null;
      currentEndDate = null;
      document.getElementById("start-date").value = "";
      document.getElementById("end-date").value = "";

      // Load data for selected period
      loadAnalyticsData();
    });
  });
}

// Initialize date range picker
function initializeDateRangePicker() {
  const applyButton = document.getElementById("apply-range");

  applyButton.addEventListener("click", function () {
    const startDate = document.getElementById("start-date").value;
    const endDate = document.getElementById("end-date").value;

    if (!startDate || !endDate) {
      alert("Please select both start and end dates");
      return;
    }

    if (new Date(startDate) > new Date(endDate)) {
      alert("Start date must be before end date");
      return;
    }

    // Update current date range
    currentStartDate = startDate;
    currentEndDate = endDate;

    // Remove active class from period buttons
    document
      .querySelectorAll(".period-btn")
      .forEach((btn) => btn.classList.remove("active"));

    // Load data for custom date range
    loadAnalyticsData();
  });
}

// Load analytics data from API
async function loadAnalyticsData() {
  const loading = document.getElementById("loading");
  const errorContainer = document.getElementById("error-container");
  const analyticsContent = document.getElementById("analytics-content");

  // Show loading
  loading.style.display = "block";
  errorContainer.innerHTML = "";
  analyticsContent.style.display = "none";

  try {
    // Build API URL
    let url = "../../api/admin/analytics/booking_trends.php?period=" + currentPeriod;

    if (currentStartDate && currentEndDate) {
      url += "&start_date=" + currentStartDate + "&end_date=" + currentEndDate;
    }

    // Fetch data
    const response = await fetch(url);
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.error?.message || "Failed to load analytics data");
    }

    // Hide loading and show content
    loading.style.display = "none";
    analyticsContent.style.display = "block";

    // Update UI with data
    updateKPICards(data.metrics);
    updateBookingTrendsChart(data.daily_breakdown);
    updatePopularServicesChart(data.popular_services);
    updateStaffPerformanceTable(data.staff_performance);
  } catch (error) {
    console.error("Error loading analytics:", error);
    loading.style.display = "none";
    errorContainer.innerHTML = `
            <div class="error-message">
                <strong>Error:</strong> ${error.message}
            </div>
        `;
  }
}

// Update KPI cards
function updateKPICards(metrics) {
  document.getElementById("total-bookings").textContent =
    metrics.total_bookings;

  // Calculate completion rate
  const completionRate =
    metrics.total_bookings > 0
      ? ((metrics.completed_bookings / metrics.total_bookings) * 100).toFixed(1)
      : 0;
  document.getElementById("completion-rate").textContent = completionRate + "%";

  document.getElementById("total-revenue").textContent =
    "RM " + metrics.total_revenue.toFixed(2);
  document.getElementById("avg-booking").textContent =
    "RM " + metrics.average_booking_value.toFixed(2);
}

// Update booking trends chart
function updateBookingTrendsChart(dailyBreakdown) {
  const ctx = document.getElementById("booking-trends-chart");

  // Destroy existing chart if it exists
  if (bookingTrendsChart) {
    bookingTrendsChart.destroy();
  }

  // Prepare data
  const labels = dailyBreakdown.map((day) => {
    const date = new Date(day.date);
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  });

  const bookingsData = dailyBreakdown.map((day) => day.bookings);
  const completedData = dailyBreakdown.map((day) => day.completed);
  const revenueData = dailyBreakdown.map((day) => day.revenue);

  // Create chart
  bookingTrendsChart = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Total Bookings",
          data: bookingsData,
          borderColor: "#8B4789",
          backgroundColor: "rgba(139, 71, 137, 0.1)",
          tension: 0.4,
          yAxisID: "y",
        },
        {
          label: "Completed",
          data: completedData,
          borderColor: "#4CAF50",
          backgroundColor: "rgba(76, 175, 80, 0.1)",
          tension: 0.4,
          yAxisID: "y",
        },
        {
          label: "Revenue (RM)",
          data: revenueData,
          borderColor: "#2196F3",
          backgroundColor: "rgba(33, 150, 243, 0.1)",
          tension: 0.4,
          yAxisID: "y1",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: "index",
        intersect: false,
      },
      plugins: {
        legend: {
          position: "top",
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              let label = context.dataset.label || "";
              if (label) {
                label += ": ";
              }
              if (context.dataset.yAxisID === "y1") {
                label += "RM " + context.parsed.y.toFixed(2);
              } else {
                label += context.parsed.y;
              }
              return label;
            },
          },
        },
      },
      scales: {
        y: {
          type: "linear",
          display: true,
          position: "left",
          title: {
            display: true,
            text: "Number of Bookings",
          },
          ticks: {
            stepSize: 1,
          },
        },
        y1: {
          type: "linear",
          display: true,
          position: "right",
          title: {
            display: true,
            text: "Revenue (RM)",
          },
          grid: {
            drawOnChartArea: false,
          },
        },
      },
    },
  });
}

// Update popular services chart
function updatePopularServicesChart(popularServices) {
  const ctx = document.getElementById("popular-services-chart");

  // Destroy existing chart if it exists
  if (popularServicesChart) {
    popularServicesChart.destroy();
  }

  // Prepare data
  const labels = popularServices.map((service) => service.service_name);
  const bookingCounts = popularServices.map((service) => service.booking_count);
  const revenues = popularServices.map((service) => service.revenue);

  // Create chart
  popularServicesChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Booking Count",
          data: bookingCounts,
          backgroundColor: "rgba(139, 71, 137, 0.8)",
          yAxisID: "y",
        },
        {
          label: "Revenue (RM)",
          data: revenues,
          backgroundColor: "rgba(76, 175, 80, 0.8)",
          yAxisID: "y1",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "top",
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              let label = context.dataset.label || "";
              if (label) {
                label += ": ";
              }
              if (context.dataset.yAxisID === "y1") {
                label += "RM " + context.parsed.y.toFixed(2);
              } else {
                label += context.parsed.y;
              }
              return label;
            },
          },
        },
      },
      scales: {
        y: {
          type: "linear",
          display: true,
          position: "left",
          title: {
            display: true,
            text: "Booking Count",
          },
          ticks: {
            stepSize: 1,
          },
        },
        y1: {
          type: "linear",
          display: true,
          position: "right",
          title: {
            display: true,
            text: "Revenue (RM)",
          },
          grid: {
            drawOnChartArea: false,
          },
        },
      },
    },
  });
}

// Update staff performance table
function updateStaffPerformanceTable(staffPerformance) {
  const tbody = document.getElementById("staff-performance-body");
  tbody.innerHTML = "";

  if (staffPerformance.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="3" style="text-align: center; color: #666;">No staff performance data available</td></tr>';
    return;
  }

  staffPerformance.forEach((staff) => {
    const row = document.createElement("tr");
    row.innerHTML = `
            <td>${staff.staff_name}</td>
            <td>${staff.completed_sessions}</td>
            <td>RM ${staff.total_revenue.toFixed(2)}</td>
        `;
    tbody.appendChild(row);
  });
}
