// Business Analytics Dashboard JavaScript

let bookingTrendsChart = null;
let popularServicesChart = null;
let currentPeriod = "weekly";
let currentDays = 7;
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
  fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'business.js:20', message: 'DOMContentLoaded - checking Font Awesome icons', data: {faLoaded: !!document.querySelector('link[href*="font-awesome"]'), iconElements: document.querySelectorAll('.summary-icon i').length, iconClasses: Array.from(document.querySelectorAll('.summary-icon i')).map(el => el.className)}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H1'})}).catch(() => {});
  // #endregion
  
  initializeFilters();
  initializeExportButton();
  loadAnalyticsData();
  setupIntersectionObserver();
});

// Initialize filter dropdowns
function initializeFilters() {
  const periodSelect = document.getElementById("period-select");
  const rangeSelect = document.getElementById("range-select");

  if (periodSelect) {
    periodSelect.addEventListener("change", function () {
      currentPeriod = this.value;
      loadAnalyticsData();
    });
  }

  if (rangeSelect) {
    rangeSelect.addEventListener("change", function () {
      currentDays = parseInt(this.value);
      loadAnalyticsData();
    });
  }
}

// Initialize export button
function initializeExportButton() {
  const exportBtn = document.getElementById("export-report");
  if (exportBtn) {
    exportBtn.addEventListener("click", function () {
      exportReport();
    });
  }
}

// Export report functionality
function exportReport() {
  // Create a simple CSV export
  const data = [];
  data.push(["Business Analytics Report"]);
  data.push(["Generated:", new Date().toLocaleDateString()]);
  data.push([]);

  // Get KPI values
  const totalBookings =
    document.getElementById("total-bookings")?.textContent || "0";
  const completionRate =
    document.getElementById("completion-rate")?.textContent || "0%";
  const totalRevenue =
    document.getElementById("total-revenue")?.textContent || "RM 0";
  const avgBooking =
    document.getElementById("avg-booking")?.textContent || "RM 0";

  data.push(["Metric", "Value"]);
  data.push(["Total Bookings", totalBookings]);
  data.push(["Completion Rate", completionRate]);
  data.push(["Total Revenue", totalRevenue]);
  data.push(["Avg Booking Value", avgBooking]);

  // Convert to CSV
  const csv = data.map((row) => row.join(",")).join("\n");
  const blob = new Blob([csv], { type: "text/csv" });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `analytics-report-${new Date().toISOString().split("T")[0]}.csv`;
  a.click();
  window.URL.revokeObjectURL(url);
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
    // Build API URL
    let url = `../../api/admin/analytics/booking_trends.php?period=${currentPeriod}&days=${currentDays}`;

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'business.js:152', message: 'About to fetch analytics data', data: {url: url, period: currentPeriod, days: currentDays}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H1,H4'})}).catch(() => {});
    // #endregion

    // Fetch data
    const response = await fetch(url);
    
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'business.js:157', message: 'Analytics fetch response received', data: {status: response.status, statusText: response.statusText, contentType: response.headers.get('content-type')}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H1,H4'})}).catch(() => {});
    // #endregion
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      // #region agent log
      fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'business.js:163', message: 'Non-JSON response detected', data: {status: response.status, contentType: contentType, responsePreview: text.substring(0, 200)}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H1,H4'})}).catch(() => {});
      // #endregion
      throw new Error(`Server returned non-JSON response (${response.status} ${response.statusText})`);
    }
    
    const data = await response.json();
    
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({location: 'business.js:171', message: 'Analytics data parsed successfully', data: {success: data.success, hasError: !!data.error, metricsPresent: !!data.metrics}, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'H1,H4'})}).catch(() => {});
    // #endregion

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

// Update booking trends chart - Yellow/Gold line chart
function updateBookingTrendsChart(dailyBreakdown) {
  const ctx = document.getElementById("booking-trends-chart");
  if (!ctx) return;

  // Destroy existing chart if it exists
  if (bookingTrendsChart) {
    bookingTrendsChart.destroy();
  }

  // Prepare data - use day names for weekly view
  const labels = dailyBreakdown.map((day) => {
    const date = new Date(day.date);
    if (currentPeriod === "weekly" || currentDays <= 7) {
      return date.toLocaleDateString("en-US", { weekday: "short" });
    }
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
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
          barThickness: 24,
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
