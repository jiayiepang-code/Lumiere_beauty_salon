// Sustainability Analytics Dashboard JavaScript

let idleHoursChart = null;
let currentPeriod = "monthly";
let currentStartDate = null;
let currentEndDate = null;
let currentData = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  initializePeriodButtons();
  initializeDateRangePicker();
  initializeExportButton();
  loadSustainabilityData();
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
      loadSustainabilityData();
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
    loadSustainabilityData();
  });
}

// Initialize export button
function initializeExportButton() {
  const exportButton = document.getElementById("export-pdf");

  exportButton.addEventListener("click", function () {
    if (!currentData) {
      alert("No data available to export");
      return;
    }

    exportESGReport();
  });
}

// Load sustainability data from API
async function loadSustainabilityData() {
  const loading = document.getElementById("loading");
  const errorContainer = document.getElementById("error-container");
  const analyticsContent = document.getElementById("analytics-content");

  // Show loading
  loading.style.display = "block";
  errorContainer.innerHTML = "";
  analyticsContent.style.display = "none";

  try {
    // Build API URL
    let url = "../../api/admin/analytics/idle_hours.php?period=" + currentPeriod;

    if (currentStartDate && currentEndDate) {
      url += "&start_date=" + currentStartDate + "&end_date=" + currentEndDate;
    }

    // Fetch data
    const response = await fetch(url);
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(
        data.error?.message || "Failed to load sustainability data"
      );
    }

    // Store current data for export
    currentData = data;

    // Hide loading and show content
    loading.style.display = "none";
    analyticsContent.style.display = "block";

    // Update UI with data
    updateKPICards(data.salon_metrics);
    updateIdleHoursChart(data.daily_idle_pattern);
    updateStaffBreakdownTable(data.staff_breakdown);
  } catch (error) {
    console.error("Error loading sustainability data:", error);
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
  document.getElementById("scheduled-hours").textContent =
    metrics.total_scheduled_hours.toFixed(1) + " hrs";
  document.getElementById("booked-hours").textContent =
    metrics.total_booked_hours.toFixed(1) + " hrs";
  document.getElementById("idle-hours").textContent =
    metrics.total_idle_hours.toFixed(1) + " hrs";
  document.getElementById("utilization-rate").textContent =
    metrics.utilization_rate.toFixed(1) + "%";
}

// Update idle hours chart
function updateIdleHoursChart(dailyPattern) {
  const ctx = document.getElementById("idle-hours-chart");

  // Destroy existing chart if it exists
  if (idleHoursChart) {
    idleHoursChart.destroy();
  }

  // Prepare data
  const labels = dailyPattern.map((day) => {
    const date = new Date(day.date);
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  });

  const scheduledData = dailyPattern.map((day) => day.scheduled_hours);
  const bookedData = dailyPattern.map((day) => day.booked_hours);
  const idleData = dailyPattern.map((day) => day.idle_hours);

  // Create chart
  idleHoursChart = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Scheduled Hours",
          data: scheduledData,
          borderColor: "#2196F3",
          backgroundColor: "rgba(33, 150, 243, 0.1)",
          tension: 0.4,
          fill: true,
        },
        {
          label: "Booked Hours",
          data: bookedData,
          borderColor: "#4CAF50",
          backgroundColor: "rgba(76, 175, 80, 0.1)",
          tension: 0.4,
          fill: true,
        },
        {
          label: "Idle Hours",
          data: idleData,
          borderColor: "#FF9800",
          backgroundColor: "rgba(255, 152, 0, 0.2)",
          tension: 0.4,
          fill: true,
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
              label += context.parsed.y.toFixed(1) + " hrs";
              return label;
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: "Hours",
          },
          ticks: {
            callback: function (value) {
              return value.toFixed(0) + " hrs";
            },
          },
        },
      },
    },
  });
}

// Update staff breakdown table
function updateStaffBreakdownTable(staffBreakdown) {
  const tbody = document.getElementById("staff-breakdown-body");
  tbody.innerHTML = "";

  if (staffBreakdown.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="5" style="text-align: center; color: #666;">No staff data available</td></tr>';
    return;
  }

  staffBreakdown.forEach((staff) => {
    const utilizationClass =
      staff.utilization_rate >= 75
        ? "utilization-high"
        : staff.utilization_rate >= 50
        ? "utilization-medium"
        : "utilization-low";

    const row = document.createElement("tr");
    row.innerHTML = `
            <td>${staff.staff_name}</td>
            <td>${staff.scheduled_hours.toFixed(1)} hrs</td>
            <td>${staff.booked_hours.toFixed(1)} hrs</td>
            <td>${staff.idle_hours.toFixed(1)} hrs</td>
            <td>
                <span class="utilization-badge ${utilizationClass}">
                    ${staff.utilization_rate.toFixed(1)}%
                </span>
            </td>
        `;
    tbody.appendChild(row);
  });
}

// Export ESG report
function exportESGReport() {
  // Create a simple text-based report
  let reportContent = "LUMIÈRE BEAUTY SALON\n";
  reportContent += "ESG Sustainability Report\n";
  reportContent += "=".repeat(50) + "\n\n";
  reportContent += `Period: ${currentData.period}\n`;
  reportContent += `Date Range: ${currentData.start_date} to ${currentData.end_date}\n\n`;

  reportContent += "SALON METRICS\n";
  reportContent += "-".repeat(50) + "\n";
  reportContent += `Total Scheduled Hours: ${currentData.salon_metrics.total_scheduled_hours.toFixed(
    2
  )} hrs\n`;
  reportContent += `Total Booked Hours: ${currentData.salon_metrics.total_booked_hours.toFixed(
    2
  )} hrs\n`;
  reportContent += `Total Idle Hours: ${currentData.salon_metrics.total_idle_hours.toFixed(
    2
  )} hrs\n`;
  reportContent += `Utilization Rate: ${currentData.salon_metrics.utilization_rate.toFixed(
    1
  )}%\n\n`;

  reportContent += "STAFF BREAKDOWN\n";
  reportContent += "-".repeat(50) + "\n";
  reportContent +=
    "Staff Name".padEnd(25) +
    "Scheduled".padEnd(12) +
    "Booked".padEnd(12) +
    "Idle".padEnd(12) +
    "Util%\n";
  reportContent += "-".repeat(50) + "\n";

  currentData.staff_breakdown.forEach((staff) => {
    reportContent +=
      staff.staff_name.padEnd(25) +
      `${staff.scheduled_hours.toFixed(1)} hrs`.padEnd(12) +
      `${staff.booked_hours.toFixed(1)} hrs`.padEnd(12) +
      `${staff.idle_hours.toFixed(1)} hrs`.padEnd(12) +
      `${staff.utilization_rate.toFixed(1)}%\n`;
  });

  reportContent += "\n" + "=".repeat(50) + "\n";
  reportContent += `Generated on: ${new Date().toLocaleString()}\n`;

  // Create a blob and download
  const blob = new Blob([reportContent], { type: "text/plain" });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `ESG_Report_${currentData.start_date}_to_${currentData.end_date}.txt`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);

  alert("ESG Report exported successfully!");
}
