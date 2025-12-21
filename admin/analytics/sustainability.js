// Sustainability Analytics Dashboard JavaScript
// CSV Export Functionality

document.addEventListener("DOMContentLoaded", function () {
  // Initialize PDF export button
  const pdfBtn = document.getElementById("export-esg-pdf");
  if (pdfBtn) {
    pdfBtn.addEventListener("click", exportESGReportPdf);
  }

  // Initialize CSV/Excel export button
  const csvBtn = document.getElementById("export-esg-csv");
  if (csvBtn) {
    csvBtn.addEventListener("click", exportESGReportCsv);
  }

  // Auto-update on filter change (remove Apply button)
  const monthSelect = document.getElementById("month-select");
  const yearSelect = document.getElementById("year-select");

  if (monthSelect) {
    monthSelect.addEventListener("change", function () {
      console.log("Month changed to:", this.value);
      updateFilters();
    });
  } else {
    console.error("Month select element not found");
  }

  if (yearSelect) {
    yearSelect.addEventListener("change", function () {
      console.log("Year changed to:", this.value);
      updateFilters();
    });
  } else {
    console.error("Year select element not found");
  }
});

// Update page with new filters
function updateFilters() {
  const monthSelect = document.getElementById("month-select");
  const yearSelect = document.getElementById("year-select");

  if (!monthSelect || !yearSelect) {
    console.error("Filter selects not found");
    return;
  }

  const month = monthSelect.value;
  const year = yearSelect.value;

  if (month && year) {
    try {
      // Construct URL with query parameters
      // Use window.location to get the current URL and update it
      const url = new URL(window.location.href);
      url.searchParams.set("month", month);
      url.searchParams.set("year", year);

      // Reload page with new parameters
      window.location.href = url.toString();
    } catch (e) {
      // Fallback for browsers that don't support URL constructor
      console.warn("URL constructor not supported, using fallback:", e);
      const baseUrl = window.location.pathname;
      const newUrl =
        baseUrl +
        "?month=" +
        encodeURIComponent(month) +
        "&year=" +
        encodeURIComponent(year);
      window.location.href = newUrl;
    }
  } else {
    console.warn("Month or year not selected:", { month, year });
  }
}

/**
 * Export ESG Report as CSV (Excel-compatible)
 */
async function exportESGReportCsv() {
  const month = document.getElementById("month-select").value;
  const year = document.getElementById("year-select").value;

  if (!month || !year) {
    alert("Please select both month and year before exporting.");
    return;
  }

  // Show loading state
  const exportBtn = document.getElementById("export-esg-csv");
  const originalText = exportBtn.innerHTML;
  // Lock width to prevent layout shift
  const btnRect = exportBtn.getBoundingClientRect();
  exportBtn.style.minWidth = `${btnRect.width}px`;
  exportBtn.disabled = true;
  exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

  try {
    const response = await fetch(
      `../../api/admin/analytics/export_esg_csv.php?month=${month}&year=${year}`
    );

    if (response.ok) {
      // Handle CSV download
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `ESG_Report_${year}_${month}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);

      // Wait for user to save, then show success message
      setTimeout(() => {
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "success",
            title: "Excel Downloaded",
            text: "ESG Report has been saved successfully.",
            timer: 2000,
            showConfirmButton: false,
          });
        } else {
          alert("ESG Report exported successfully!");
        }

        // Restore button state
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
        exportBtn.style.minWidth = "";
      }, 2500); // Wait 2.5 seconds for user to save
    } else {
      const errorText = await response.text();
      throw new Error(
        errorText || "Error generating report. Please try again."
      );
    }
  } catch (error) {
    console.error("Export error:", error);

    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Export Failed",
        text: error.message || "Error generating report. Please try again.",
        confirmButtonText: "OK",
      });
    } else {
      alert(
        "Error generating report: " + (error.message || "Please try again.")
      );
    }

    // Restore button state on error (immediately, not delayed)
    exportBtn.disabled = false;
    exportBtn.innerHTML = originalText;
    exportBtn.style.minWidth = "";
  }
}

/**
 * Export ESG Sustainability Analytics as a professional PDF
 * Client-side PDF generation using html2pdf.js
 */
async function exportESGReportPdf() {
  const month = document.getElementById("month-select").value;
  const year = document.getElementById("year-select").value;

  if (!month || !year) {
    alert("Please select both month and year before exporting.");
    return;
  }

  // Show loading state
  const pdfBtn = document.getElementById("export-esg-pdf");
  const originalText = pdfBtn.innerHTML;
  pdfBtn.disabled = true;
  pdfBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';

  // Human-friendly month name for the report title
  const monthNames = {
    "01": "January",
    "02": "February",
    "03": "March",
    "04": "April",
    "05": "May",
    "06": "June",
    "07": "July",
    "08": "August",
    "09": "September",
    10: "October",
    11: "November",
    12: "December",
  };
  const periodLabel = `${monthNames[month] || month} ${year}`;

  try {
    // Build PDF export URL
    const pdfUrl = `../../api/admin/analytics/export_esg_pdf.php?month=${month}&year=${year}`;

    // Fetch PDF directly
    const response = await fetch(pdfUrl);
    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
    }

    // Get PDF blob
    const blob = await response.blob();

    // Check if response is actually a PDF
    if (!blob.type.includes("pdf") && blob.size > 0) {
      // Might be an error message
      const text = await blob.text();
      if (text.includes("Error") || text.includes("error")) {
        throw new Error(text.substring(0, 200));
      }
    }

    // Create download link
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `ESG_Report_${year}_${month}.pdf`;
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
          text: "ESG Report PDF has been saved successfully.",
          timer: 2000,
          showConfirmButton: false,
        });
      }

      // Re-enable button
      pdfBtn.disabled = false;
      pdfBtn.innerHTML = originalText;
    }, 2500); // Wait 2.5 seconds for user to save
  } catch (error) {
    console.error("PDF export error:", error);

    // #region agent log - PDF export failure details (H3)
    fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        sessionId: "debug-session",
        runId: "post-fix1",
        hypothesisId: "H3",
        location: "sustainability.js:exportESGReportPdf:catch",
        message: "PDF export threw error",
        data: {
          name: error && error.name,
          message: error && error.message,
          stack:
            error && error.stack ? String(error.stack).slice(0, 500) : null,
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {});
    // #endregion

    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Export Failed",
        text: error.message || "Error generating PDF. Please try again.",
        confirmButtonText: "OK",
      });
    } else {
      alert("Error generating PDF: " + (error.message || "Please try again."));
    }

    pdfBtn.disabled = false;
    pdfBtn.innerHTML = originalText;
  }
}
