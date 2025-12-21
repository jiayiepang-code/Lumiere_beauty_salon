// Business Analytics Dashboard JavaScript

let bookingTrendsChart = null;
let popularServicesChart = null;
let currentDateRange = "thismonth"; // Default to this month
let customStartDate = null;
let customEndDate = null;
let chartsAnimated = false;

// Chart color palette - Yellow/Gold theme
const chartColors = {
  primary: "#D4A574",
  primaryLight: "rgba(212, 165, 116, 0.2)",
  secondary: "#C4956A",
  success: "#22c55e",
  muted: "#888888",
};

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  // #region agent log
  fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      location: "business.js:20",
      message: "DOMContentLoaded - checking Font Awesome icons",
      data: {
        faLoaded: !!document.querySelector('link[href*="font-awesome"]'),
        iconElements: document.querySelectorAll(".summary-icon i").length,
        iconClasses: Array.from(
          document.querySelectorAll(".summary-icon i")
        ).map((el) => el.className),
      },
      timestamp: Date.now(),
      sessionId: "debug-session",
      runId: "run1",
      hypothesisId: "H1",
    }),
  }).catch(() => {});
  // #endregion

  initializeFilters();
  initializeExportButtons();
  loadAnalyticsData();
  setupIntersectionObserver();
});

// Initialize filter dropdowns
function initializeFilters() {
  const dateRangeSelect = document.getElementById("date-range-select");
  const customDateRange = document.getElementById("custom-date-range");
  const customStartDateInput = document.getElementById("custom-start-date");
  const customEndDateInput = document.getElementById("custom-end-date");

  if (dateRangeSelect) {
    dateRangeSelect.addEventListener("change", function () {
      currentDateRange = this.value;

      // Show/hide custom date inputs
      if (this.value === "custom") {
        customDateRange.style.display = "block";
        // Set default dates (last 30 days)
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(endDate.getDate() - 29);
        customStartDateInput.value = startDate.toISOString().split("T")[0];
        customEndDateInput.value = endDate.toISOString().split("T")[0];
        customStartDate = customStartDateInput.value;
        customEndDate = customEndDateInput.value;
      } else {
        customDateRange.style.display = "none";
        customStartDate = null;
        customEndDate = null;
      }

      loadAnalyticsData();
    });
  }

  // Custom date inputs
  if (customStartDateInput) {
    customStartDateInput.addEventListener("change", function () {
      customStartDate = this.value;
      if (customStartDate && customEndDate) {
        loadAnalyticsData();
      }
    });
  }

  if (customEndDateInput) {
    customEndDateInput.addEventListener("change", function () {
      customEndDate = this.value;
      if (customStartDate && customEndDate) {
        loadAnalyticsData();
      }
    });
  }
}

// Initialize export buttons
function initializeExportButtons() {
  // PDF export button
  const pdfBtn = document.getElementById("export-business-pdf");
  if (pdfBtn) {
    pdfBtn.addEventListener("click", exportBusinessReportPdf);
  }

  // CSV/Excel export button
  const csvBtn = document.getElementById("export-business-csv");
  if (csvBtn) {
    csvBtn.addEventListener("click", exportBusinessReportCsv);
  }
}

/**
 * Export Business Analytics Report as CSV (Excel-compatible)
 */
async function exportBusinessReportCsv() {
  const exportBtn = document.getElementById("export-business-csv");
  const originalText = exportBtn ? exportBtn.innerHTML : "";

  if (exportBtn) {
    // Lock button width to prevent layout shift while loading
    const btnRect = exportBtn.getBoundingClientRect();
    exportBtn.style.minWidth = `${btnRect.width}px`;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
  }

  try {
    // Build API URL with current filters
    let apiUrl = `../../api/admin/analytics/booking_trends.php`;

    if (currentDateRange === "custom" && customStartDate && customEndDate) {
      apiUrl += `?start_date=${customStartDate}&end_date=${customEndDate}`;
    } else {
      apiUrl += `?preset=${currentDateRange}`;
    }

    const response = await fetch(apiUrl);

    if (!response.ok) {
      throw new Error("Failed to fetch analytics data");
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error?.message || "Failed to load data");
    }

    // Build CSV content
    const csvRows = [];

    // Header aligned with PDF
    csvRows.push(["Lumiere Beauty Salon"]);
    csvRows.push(["Business Analytics Report"]);
    csvRows.push(["Report Period:", `${data.start_date} to ${data.end_date}`]);
    csvRows.push(["Generated on:", new Date().toLocaleString()]);
    csvRows.push(["Company Registration:", "SSM: SA0123456-A"]);
    csvRows.push([]);
    csvRows.push(["Company Information:"]);
    csvRows.push([
      "Address:",
      "No. 10, Ground Floor Block B, Phase 2, Jln Lintas, Kolam Centre",
    ]);
    csvRows.push(["City:", "88300 Kota Kinabalu, Sabah"]);
    csvRows.push(["Email:", "Lumiere@gmail.com"]);
    csvRows.push(["Tel:", "012 345 6789 / 088 978 8977"]);
    csvRows.push([]);

    // Summary Metrics
    csvRows.push(["SUMMARY METRICS"]);
    csvRows.push(["Metric", "Value"]);
    csvRows.push(["Total Bookings", data.metrics.total_bookings]);
    csvRows.push(["Completed Bookings", data.metrics.completed_bookings]);
    csvRows.push(["Cancelled Bookings", data.metrics.cancelled_bookings]);
    csvRows.push(["No-Show Bookings", data.metrics.no_show_bookings]);
    csvRows.push([
      "Total Revenue",
      `RM ${data.metrics.total_revenue.toFixed(2)}`,
    ]);
    csvRows.push([
      "Average Booking Value",
      `RM ${data.metrics.average_booking_value.toFixed(2)}`,
    ]);
    csvRows.push([]);

    // Daily Breakdown
    if (data.daily_breakdown && data.daily_breakdown.length > 0) {
      csvRows.push(["DAILY BREAKDOWN"]);
      csvRows.push(["Date", "Bookings", "Completed", "Cancelled", "Revenue"]);
      data.daily_breakdown.forEach((day) => {
        csvRows.push([
          day.date,
          day.bookings,
          day.completed,
          day.cancelled,
          `RM ${day.revenue.toFixed(2)}`,
        ]);
      });
      csvRows.push([]);
    }

    // Popular Services
    if (data.popular_services && data.popular_services.length > 0) {
      csvRows.push(["POPULAR SERVICES"]);
      csvRows.push(["Service Name", "Booking Count", "Revenue"]);
      data.popular_services.forEach((service) => {
        csvRows.push([
          service.service_name,
          service.booking_count,
          `RM ${service.revenue.toFixed(2)}`,
        ]);
      });
      csvRows.push([]);
    }

    // Staff Performance
    if (data.staff_performance && data.staff_performance.length > 0) {
      csvRows.push(["STAFF PERFORMANCE"]);
      csvRows.push([
        "Staff Name",
        "Completed Sessions",
        "Total Revenue",
        "Commission Earned",
      ]);
      data.staff_performance.forEach((staff) => {
        csvRows.push([
          staff.staff_name,
          staff.completed_sessions,
          `RM ${staff.total_revenue.toFixed(2)}`,
          `RM ${staff.commission_earned.toFixed(2)}`,
        ]);
      });
    }

    // Convert to CSV
    const csv = csvRows
      .map((row) =>
        row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(",")
      )
      .join("\n");

    // Download
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = downloadUrl;
    const dateStr = `${data.start_date}_to_${data.end_date}`;
    a.download = `business-analytics-${dateStr}-${
      new Date().toISOString().split("T")[0]
    }.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(downloadUrl);

    // Wait for user to save, then show success message
    setTimeout(() => {
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "success",
          title: "Excel Downloaded",
          text: "Business Analytics Report has been saved successfully.",
          timer: 2000,
          showConfirmButton: false,
        });
      }

      // Re-enable button
      if (exportBtn) {
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
        exportBtn.style.minWidth = "";
      }
    }, 2500); // Wait 2.5 seconds for user to save
  } catch (error) {
    console.error("Export error:", error);
    alert("Failed to export report: " + error.message);
    // Re-enable button on error
    if (exportBtn) {
      exportBtn.disabled = false;
      exportBtn.innerHTML = originalText;
      exportBtn.style.minWidth = "";
    }
  }
}

/**
 * Export Business Analytics Report as PDF - Server-side approach
 * Uses mPDF via API endpoint for structured, professional PDF with logo
 */
async function exportBusinessReportPdf() {
  const exportBtn = document.getElementById("export-business-pdf");
  if (!exportBtn) return;

  // Disable button and show loading
  const originalText = exportBtn.innerHTML;
  exportBtn.disabled = true;
  exportBtn.innerHTML =
    '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';

  try {
    // Get current filter values - use new filter system
    let startDate, endDate;

    if (currentDateRange === "custom" && customStartDate && customEndDate) {
      startDate = customStartDate;
      endDate = customEndDate;
    } else {
      // Calculate from preset
      const today = new Date();
      switch (currentDateRange) {
        case "today":
          startDate = today.toISOString().split("T")[0];
          endDate = startDate;
          break;
        case "yesterday":
          const yesterday = new Date(today);
          yesterday.setDate(yesterday.getDate() - 1);
          startDate = yesterday.toISOString().split("T")[0];
          endDate = startDate;
          break;
        case "last7days":
          startDate = new Date(today);
          startDate.setDate(startDate.getDate() - 6);
          startDate = startDate.toISOString().split("T")[0];
          endDate = today.toISOString().split("T")[0];
          break;
        case "last30days":
          startDate = new Date(today);
          startDate.setDate(startDate.getDate() - 29);
          startDate = startDate.toISOString().split("T")[0];
          endDate = today.toISOString().split("T")[0];
          break;
        case "thisweek":
          const monday = new Date(today);
          monday.setDate(monday.getDate() - monday.getDay() + 1);
          startDate = monday.toISOString().split("T")[0];
          const sunday = new Date(today);
          sunday.setDate(sunday.getDate() - sunday.getDay() + 7);
          endDate = sunday.toISOString().split("T")[0];
          break;
        case "thismonth":
          startDate = new Date(today.getFullYear(), today.getMonth(), 1)
            .toISOString()
            .split("T")[0];
          endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0)
            .toISOString()
            .split("T")[0];
          break;
        case "lastmonth":
          const lastMonth = new Date(
            today.getFullYear(),
            today.getMonth() - 1,
            1
          );
          startDate = lastMonth.toISOString().split("T")[0];
          endDate = new Date(today.getFullYear(), today.getMonth(), 0)
            .toISOString()
            .split("T")[0];
          break;
        case "thisyear":
          startDate = new Date(today.getFullYear(), 0, 1)
            .toISOString()
            .split("T")[0];
          endDate = new Date(today.getFullYear(), 11, 31)
            .toISOString()
            .split("T")[0];
          break;
        default:
          // Default to this month
          startDate = new Date(today.getFullYear(), today.getMonth(), 1)
            .toISOString()
            .split("T")[0];
          endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0)
            .toISOString()
            .split("T")[0];
      }
    }

    // Build PDF export URL (server-side API)
    const pdfUrl = `../../api/admin/analytics/export_business_pdf.php?start_date=${startDate}&end_date=${endDate}`;

    // Fetch PDF directly from server
    const response = await fetch(pdfUrl);

    // Check content type first
    const contentType = response.headers.get("content-type");

    if (!response.ok) {
      let errorText = "";
      try {
        if (contentType && contentType.includes("application/json")) {
          const errorData = await response.json();
          errorText = errorData.error || errorData.message || "Unknown error";
        } else {
          errorText = await response.text();
        }
      } catch (e) {
        errorText = `HTTP ${response.status}: ${response.statusText}`;
      }
      throw new Error(errorText);
    }

    // Get PDF blob
    const blob = await response.blob();

    // Check if response is actually a PDF
    if (!blob.type.includes("pdf") && blob.size > 0) {
      // Might be an error message
      try {
        const text = await blob.text();
        if (
          text.includes("Error") ||
          text.includes("error") ||
          text.includes("Exception")
        ) {
          throw new Error(text.substring(0, 500));
        }
      } catch (e) {
        if (e.message && !e.message.includes("Error")) {
          throw e;
        }
        throw new Error(
          "Server returned non-PDF response. Please check server logs."
        );
      }
    }

    // Verify blob is not empty
    if (blob.size === 0) {
      throw new Error("PDF file is empty. Please try again.");
    }

    // Create download link
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Business_Analytics_Report_${startDate}_to_${endDate}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    // Wait for user to save, then show success message
    setTimeout(() => {
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "success",
          title: "PDF Downloaded",
          text: "Business Analytics Report PDF has been saved successfully.",
          timer: 2000,
          showConfirmButton: false,
        });
      }

      // Re-enable button
      exportBtn.disabled = false;
      exportBtn.innerHTML = originalText;
    }, 2500); // Wait 2.5 seconds for user to save
  } catch (error) {
    console.error("Error exporting PDF:", error);

    // Show detailed error message
    const errorMessage = error.message || "Unknown error occurred";

    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Export Failed",
        html: `<p>Error generating PDF report:</p><p style="font-size: 12px; color: #666;">${errorMessage}</p>`,
        confirmButtonText: "OK",
      });
    } else {
      alert("Error generating PDF report: " + errorMessage);
    }

    exportBtn.disabled = false;
    exportBtn.innerHTML = originalText;
  }
}

// Setup Intersection Observer for chart animations
function setupIntersectionObserver() {
  const options = {
    root: null,
    rootMargin: "0px",
    threshold: 0.1,
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting && !chartsAnimated) {
        animateCharts();
        chartsAnimated = true;
      }
    });
  }, options);

  const analyticsContent = document.getElementById("analytics-content");
  if (analyticsContent) {
    observer.observe(analyticsContent);
  }
}

// Animate charts when they come into view
function animateCharts() {
  // Add animation classes to KPI cards
  const kpiCards = document.querySelectorAll(".kpi-card");
  kpiCards.forEach((card, index) => {
    setTimeout(() => {
      card.classList.add("animate-in");
    }, index * 100);
  });

  // Add animation classes to chart cards
  const chartCards = document.querySelectorAll(".chart-card");
  chartCards.forEach((card, index) => {
    setTimeout(() => {
      card.classList.add("animate-in");
    }, 400 + index * 200);
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

  // Reset animation state
  chartsAnimated = false;
  document
    .querySelectorAll(".kpi-card, .chart-card")
    .forEach((el) => el.classList.remove("animate-in"));

  try {
    // Build API URL with new filter system
    let url = `../../api/admin/analytics/booking_trends.php`;

    if (currentDateRange === "custom" && customStartDate && customEndDate) {
      url += `?start_date=${customStartDate}&end_date=${customEndDate}`;
    } else {
      url += `?preset=${currentDateRange}`;
    }

    // #region agent log
    fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        location: "business.js:152",
        message: "About to fetch analytics data",
        data: {
          url: url,
          dateRange: currentDateRange,
          customStart: customStartDate,
          customEnd: customEndDate,
        },
        timestamp: Date.now(),
        sessionId: "debug-session",
        runId: "run1",
        hypothesisId: "H1,H4",
      }),
    }).catch(() => {});
    // #endregion

    // Fetch data
    const response = await fetch(url);

    // #region agent log
    fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        location: "business.js:157",
        message: "Analytics fetch response received",
        data: {
          status: response.status,
          statusText: response.statusText,
          contentType: response.headers.get("content-type"),
        },
        timestamp: Date.now(),
        sessionId: "debug-session",
        runId: "run1",
        hypothesisId: "H1,H4",
      }),
    }).catch(() => {});
    // #endregion

    // Check if response is JSON
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      const text = await response.text();
      // #region agent log
      fetch(
        "http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3",
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            location: "business.js:163",
            message: "Non-JSON response detected",
            data: {
              status: response.status,
              contentType: contentType,
              responsePreview: text.substring(0, 200),
            },
            timestamp: Date.now(),
            sessionId: "debug-session",
            runId: "run1",
            hypothesisId: "H1,H4",
          }),
        }
      ).catch(() => {});
      // #endregion
      throw new Error(
        `Server returned non-JSON response (${response.status} ${response.statusText})`
      );
    }

    const data = await response.json();

    // #region agent log
    fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        location: "business.js:171",
        message: "Analytics data parsed successfully",
        data: {
          success: data.success,
          hasError: !!data.error,
          metricsPresent: !!data.metrics,
        },
        timestamp: Date.now(),
        sessionId: "debug-session",
        runId: "run1",
        hypothesisId: "H1,H4",
      }),
    }).catch(() => {});
    // #endregion

    if (!response.ok || !data.success) {
      throw new Error(data.error?.message || "Failed to load analytics data");
    }

    // Hide loading and show content
    loading.style.display = "none";
    analyticsContent.style.display = "block";

    // Show filtered summary section
    const filteredSummarySection = document.getElementById(
      "filtered-summary-section"
    );
    if (filteredSummarySection) {
      filteredSummarySection.style.display = "block";
    }

    // Update date range indicators
    updateDateRangeIndicators(data.start_date, data.end_date, data.preset);

    // Fetch previous period for comparison
    const previousMetrics = await fetchPreviousPeriodData(
      data.start_date,
      data.end_date
    );

    // Update UI with data - with error handling
    try {
      if (data.metrics) {
        updateSummaryCards(data.metrics, previousMetrics);
      }
      if (data.daily_breakdown && data.daily_breakdown.length > 0) {
        updateBookingTrendsChart(data.daily_breakdown);
      }
      if (data.popular_services && data.popular_services.length > 0) {
        updatePopularServicesChart(data.popular_services);
      }
      if (data.staff_performance) {
        updateStaffLeaderboard(data.staff_performance);
      }
    } catch (updateError) {
      console.error("Error updating UI components:", updateError);
    }

    // Trigger animations
    setTimeout(() => {
      animateCharts();
      chartsAnimated = true;
    }, 100);
  } catch (error) {
    console.error("Error loading analytics:", error);
    loading.style.display = "none";
    errorContainer.innerHTML = `
      <div class="error-message" style="background: #fef2f2; color: #dc2626; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
        <strong>Error:</strong> ${error.message}
      </div>
    `;
  }
}

// Update KPI cards with new design
function updateKPICards(metrics) {
  // Total Bookings
  const totalBookingsEl = document.getElementById("total-bookings");
  if (totalBookingsEl) {
    animateNumber(totalBookingsEl, 0, metrics.total_bookings, 800);
  }

  // Completion Rate
  const completionRate =
    metrics.total_bookings > 0
      ? ((metrics.completed_bookings / metrics.total_bookings) * 100).toFixed(0)
      : 0;
  const completionRateEl = document.getElementById("completion-rate");
  if (completionRateEl) {
    animateNumber(completionRateEl, 0, completionRate, 800, "%");
  }

  // Total Revenue
  const totalRevenueEl = document.getElementById("total-revenue");
  if (totalRevenueEl) {
    animateNumber(
      totalRevenueEl,
      0,
      metrics.total_revenue,
      800,
      "",
      "RM ",
      true
    );
  }

  // Average Booking Value
  const avgBookingEl = document.getElementById("avg-booking");
  if (avgBookingEl) {
    animateNumber(
      avgBookingEl,
      0,
      metrics.average_booking_value,
      800,
      "",
      "RM ",
      false
    );
  }

  // Update trend indicators (mock data - you can calculate actual trends)
  updateTrendIndicator("bookings-trend", 12);
  updateTrendIndicator("completion-trend", 3);
  updateTrendIndicator("revenue-trend", 8);
}

// Animate number counting
function animateNumber(
  element,
  start,
  end,
  duration,
  suffix = "",
  prefix = "",
  formatNumber = false
) {
  const startTime = performance.now();
  const startVal = parseFloat(start);
  const endVal = parseFloat(end);

  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Easing function
    const easeOutQuart = 1 - Math.pow(1 - progress, 4);
    const current = startVal + (endVal - startVal) * easeOutQuart;

    if (formatNumber) {
      element.textContent =
        prefix +
        current.toLocaleString("en-MY", { maximumFractionDigits: 0 }) +
        suffix;
    } else {
      element.textContent =
        prefix + Math.round(current).toLocaleString() + suffix;
    }

    if (progress < 1) {
      requestAnimationFrame(update);
    }
  }

  requestAnimationFrame(update);
}

// Update staff leaderboard with filtered data
function updateStaffLeaderboard(staffPerformance) {
  const tbody = document.getElementById("staff-leaderboard-body");
  if (!tbody) return;

  if (!staffPerformance || staffPerformance.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="text-align: center; color: #888; padding: 40px;">
          No staff performance data available for selected period
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = staffPerformance
    .map((staff, index) => {
      const rank = index + 1;
      const rankClass = rank <= 3 ? `rank-${rank}` : "";
      return `
      <tr class="leaderboard-row">
        <td>
          <div class="rank-badge ${rankClass}">
            #${rank}
          </div>
        </td>
        <td>
          <div class="staff-name">${escapeHtml(
            staff.staff_name || staff.full_name || "Unknown"
          )}</div>
        </td>
        <td style="text-align: center;">
          <span class="metric-value">${
            staff.completed_sessions || staff.completed_count || 0
          }</span>
        </td>
        <td style="text-align: right;">
          <span class="metric-value">RM ${(
            staff.total_revenue ||
            staff.revenue_generated ||
            0
          ).toLocaleString("en-MY", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}</span>
        </td>
        <td style="text-align: right;">
          <span class="metric-value commission">RM ${(
            staff.commission_earned || 0
          ).toLocaleString("en-MY", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}</span>
        </td>
      </tr>
    `;
    })
    .join("");
}

// Helper function to escape HTML
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Update trend indicator
function updateTrendIndicator(elementId, value) {
  const element = document.getElementById(elementId);
  if (!element) return;

  const isPositive = value >= 0;
  element.className = `kpi-trend ${isPositive ? "positive" : "negative"}`;
  element.innerHTML = `
    <i class="fas fa-arrow-${isPositive ? "up" : "down"}"></i>
    <span>${Math.abs(value)}%</span>
  `;
}

// Update date range indicators
function updateDateRangeIndicators(startDate, endDate, preset) {
  const dateRangeIndicator = document.getElementById("date-range-indicator");
  const leaderboardIndicator = document.getElementById(
    "leaderboard-date-indicator"
  );
  const chartIndicator = document.getElementById("chart-date-indicator");
  const popularChartIndicator = document.getElementById("popular-chart-date-indicator");

  // Format dates
  const start = new Date(startDate);
  const end = new Date(endDate);
  const startFormatted = start.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
  const endFormatted = end.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });

  // Get preset label
  const presetLabels = {
    today: "Today",
    yesterday: "Yesterday",
    last7days: "Last 7 Days",
    last30days: "Last 30 Days",
    thisweek: "This Week",
    thismonth: "This Month",
    lastmonth: "Last Month",
    thisyear: "This Year",
  };

  let label = presetLabels[preset] || `${startFormatted} - ${endFormatted}`;
  if (preset && presetLabels[preset]) {
    label = `${presetLabels[preset]} (${startFormatted} - ${endFormatted})`;
  }

  if (dateRangeIndicator) {
    dateRangeIndicator.innerHTML = `<i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> ${label}`;
  }

  if (leaderboardIndicator) {
    leaderboardIndicator.innerHTML = `<i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> ${label}`;
  }

  if (chartIndicator) {
    chartIndicator.innerHTML = `<i class="fas fa-chart-line" style="margin-right: 4px;"></i> ${label}`;
  }

  if (popularChartIndicator) {
    popularChartIndicator.innerHTML = `<i class="fas fa-chart-line" style="margin-right: 4px;"></i> ${label}`;
  }
}

// Fetch previous period data for comparison
async function fetchPreviousPeriodData(startDate, endDate) {
  try {
    // Calculate previous period dates
    const start = new Date(startDate);
    const end = new Date(endDate);
    const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

    const prevEnd = new Date(start);
    prevEnd.setDate(prevEnd.getDate() - 1);
    const prevStart = new Date(prevEnd);
    prevStart.setDate(prevStart.getDate() - daysDiff + 1);

    const prevStartStr = prevStart.toISOString().split("T")[0];
    const prevEndStr = prevEnd.toISOString().split("T")[0];

    const url = `../../api/admin/analytics/booking_trends.php?start_date=${prevStartStr}&end_date=${prevEndStr}&group_by=daily`;
    const response = await fetch(url);

    if (response.ok) {
      const data = await response.json();
      if (data.success && data.metrics) {
        // Calculate completion rate for previous period
        const prevCompletionRate =
          data.metrics.total_bookings > 0
            ? (data.metrics.completed_bookings / data.metrics.total_bookings) *
              100
            : 0;

        return {
          ...data.metrics,
          completion_rate: prevCompletionRate,
        };
      }
    }
  } catch (error) {
    console.error("Error fetching previous period data:", error);
  }
  return null;
}

// Update summary cards (replaces old Current Month Summary)
function updateSummaryCards(metrics, previousMetrics = null) {
  const container = document.getElementById("summary-cards-container");
  if (!container) return;

  const completionRate =
    metrics.total_bookings > 0
      ? ((metrics.completed_bookings / metrics.total_bookings) * 100).toFixed(1)
      : 0;

  // Calculate trends if previous metrics available
  const calculateTrend = (current, previous) => {
    if (!previous || previous === 0) return null;
    const change = ((current - previous) / previous) * 100;
    return {
      value: Math.abs(change).toFixed(1),
      isPositive: change >= 0,
    };
  };

  const bookingsTrend = previousMetrics
    ? calculateTrend(metrics.total_bookings, previousMetrics.total_bookings)
    : null;
  const revenueTrend = previousMetrics
    ? calculateTrend(metrics.total_revenue, previousMetrics.total_revenue)
    : null;
  const completionTrend = previousMetrics
    ? calculateTrend(
        parseFloat(completionRate),
        previousMetrics.completion_rate || 0
      )
    : null;
  const avgValueTrend = previousMetrics
    ? calculateTrend(
        metrics.average_booking_value,
        previousMetrics.average_booking_value || 0
      )
    : null;

  const renderTrend = (trend) => {
    if (!trend) return "";
    const color = trend.isPositive ? "#22c55e" : "#ef4444";
    const icon = trend.isPositive ? "fa-arrow-up" : "fa-arrow-down";
    return `
      <div style="display: flex; align-items: center; gap: 4px; margin-top: 8px; font-size: 12px; color: ${color};">
        <i class="fas ${icon}"></i>
        <span>${trend.value}%</span>
      </div>
    `;
  };

  container.innerHTML = `
    <div class="summary-card">
      <div class="summary-icon" style="background-color: rgba(212, 165, 116, 0.1); color: #D4A574;">
        <i class="fas fa-calendar-check" aria-hidden="true"></i>
      </div>
      <div class="summary-info">
        <h3>Total Bookings</h3>
        <p class="summary-value">${metrics.total_bookings.toLocaleString()}</p>
        ${renderTrend(bookingsTrend)}
        <p class="summary-label">All bookings in selected period</p>
      </div>
    </div>
    
    <div class="summary-card">
      <div class="summary-icon" style="background-color: rgba(194, 144, 118, 0.1); color: #c29076;">
        <i class="fas fa-dollar-sign" aria-hidden="true"></i>
      </div>
      <div class="summary-info">
        <h3>Total Revenue</h3>
        <p class="summary-value">RM ${metrics.total_revenue.toLocaleString(
          "en-MY",
          { minimumFractionDigits: 2, maximumFractionDigits: 2 }
        )}</p>
        ${renderTrend(revenueTrend)}
        <p class="summary-label">Completed bookings only</p>
      </div>
    </div>
    
    <div class="summary-card">
      <div class="summary-icon" style="background-color: rgba(76, 175, 80, 0.1); color: #4CAF50;">
        <i class="fas fa-percentage" aria-hidden="true"></i>
      </div>
      <div class="summary-info">
        <h3>Completion Rate</h3>
        <p class="summary-value">${completionRate}%</p>
        ${renderTrend(completionTrend)}
        <p class="summary-label">${metrics.completed_bookings} of ${
    metrics.total_bookings
  } completed</p>
      </div>
    </div>
    
    <div class="summary-card">
      <div class="summary-icon" style="background-color: rgba(33, 150, 243, 0.1); color: #2196F3;">
        <i class="fas fa-chart-line" aria-hidden="true"></i>
      </div>
      <div class="summary-info">
        <h3>Avg Booking Value</h3>
        <p class="summary-value">RM ${metrics.average_booking_value.toLocaleString(
          "en-MY",
          { minimumFractionDigits: 2, maximumFractionDigits: 2 }
        )}</p>
        ${renderTrend(avgValueTrend)}
        <p class="summary-label">Average per completed booking</p>
      </div>
    </div>
  `;
}

// Update booking trends chart - Yellow/Gold line chart
function updateBookingTrendsChart(dailyBreakdown) {
  const ctx = document.getElementById("booking-trends-chart");
  if (!ctx) return;

  // Destroy existing chart if it exists
  if (bookingTrendsChart) {
    bookingTrendsChart.destroy();
  }

  // Prepare data - always use daily format
  const labels = dailyBreakdown.map((day) => {
    const date = new Date(day.date);
    return date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
    });
  });

  const bookingsData = dailyBreakdown.map((day) => day.bookings);

  // Create chart with animation
  bookingTrendsChart = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Bookings",
          data: bookingsData,
          borderColor: chartColors.primary,
          backgroundColor: chartColors.primaryLight,
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: chartColors.primary,
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 5,
          pointHoverRadius: 7,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 1500,
        easing: "easeOutQuart",
      },
      interaction: {
        mode: "index",
        intersect: false,
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          backgroundColor: "#fff",
          titleColor: "#333",
          bodyColor: "#666",
          borderColor: "#e5e5e5",
          borderWidth: 1,
          padding: 12,
          displayColors: false,
          callbacks: {
            title: function (context) {
              return context[0].label;
            },
            label: function (context) {
              return `${context.parsed.y} bookings`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: {
            display: false,
          },
          ticks: {
            color: "#888",
            font: {
              size: 12,
            },
          },
        },
        y: {
          beginAtZero: true,
          grid: {
            color: "#f5f5f5",
          },
          ticks: {
            color: "#888",
            font: {
              size: 12,
            },
            stepSize: 9,
          },
        },
      },
    },
  });
}

// Update popular services chart - Yellow/Gold horizontal bar chart
function updatePopularServicesChart(popularServices) {
  const ctx = document.getElementById("popular-services-chart");
  if (!ctx) return;

  // Destroy existing chart if it exists
  if (popularServicesChart) {
    popularServicesChart.destroy();
  }

  // Prepare data - truncate long names
  const labels = popularServices.map((service) => {
    const name = service.service_name;
    return name.length > 12 ? name.substring(0, 12) + "..." : name;
  });
  const bookingCounts = popularServices.map((service) => service.booking_count);

  // Create horizontal bar chart with animation
  popularServicesChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Bookings",
          data: bookingCounts,
          backgroundColor: chartColors.primary,
          borderRadius: 4,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 1500,
        easing: "easeOutQuart",
        delay: function (context) {
          return context.dataIndex * 100;
        },
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          backgroundColor: "#fff",
          titleColor: "#333",
          bodyColor: "#666",
          borderColor: "#e5e5e5",
          borderWidth: 1,
          padding: 12,
          displayColors: false,
          callbacks: {
            label: function (context) {
              return `${context.parsed.x} bookings`;
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: {
            color: "#f5f5f5",
          },
          ticks: {
            color: "#888",
            font: {
              size: 12,
            },
            stepSize: 15,
          },
        },
        y: {
          grid: {
            display: false,
          },
          ticks: {
            color: "#333",
            font: {
              size: 12,
            },
          },
          categoryPercentage: 0.6, // More space between bars
          barPercentage: 0.7, // Thinner bars with more gap
        },
      },
    },
  });
}

// Update staff performance table with new column
function updateStaffPerformanceTable(staffPerformance) {
  const tbody = document.getElementById("staff-performance-body");
  if (!tbody) return;

  tbody.innerHTML = "";

  if (!staffPerformance || staffPerformance.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="4" style="text-align: center; color: #888; padding: 40px;">No staff performance data available</td></tr>';
    return;
  }

  staffPerformance.forEach((staff, index) => {
    const avgPerSession =
      staff.completed_sessions > 0
        ? staff.total_revenue / staff.completed_sessions
        : 0;

    const row = document.createElement("tr");
    row.style.opacity = "0";
    row.style.transform = "translateY(10px)";
    row.innerHTML = `
      <td style="font-weight: 500;">${staff.staff_name}</td>
      <td>${staff.completed_sessions}</td>
      <td>RM ${staff.total_revenue.toLocaleString("en-MY", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      })}</td>
      <td>RM ${avgPerSession.toLocaleString("en-MY", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      })}</td>
    `;
    tbody.appendChild(row);

    // Animate row appearance
    setTimeout(() => {
      row.style.transition = "opacity 0.3s ease, transform 0.3s ease";
      row.style.opacity = "1";
      row.style.transform = "translateY(0)";
    }, 600 + index * 80);
  });
}
