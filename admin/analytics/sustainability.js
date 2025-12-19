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
});

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

      // Show success message
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: "success",
          title: "Export Successful",
          text: "ESG Report has been downloaded successfully.",
          timer: 2000,
          showConfirmButton: false,
        });
      } else {
        alert("ESG Report exported successfully!");
      }
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
  } finally {
    // Restore button state
    exportBtn.disabled = false;
    exportBtn.innerHTML = originalText;
  }
}

/**
 * Export ESG Sustainability Analytics as a professional PDF
 * using jsPDF + html2canvas (landscape, with header + page breaks)
 */
async function exportESGReportPdf() {
  const month = document.getElementById("month-select").value;
  const year = document.getElementById("year-select").value;

  if (!month || !year) {
    alert("Please select both month and year before exporting.");
    return;
  }

  // #region agent log - debug PDF libs presence (H1)
  fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      sessionId: "debug-session",
      runId: "initial",
      hypothesisId: "H1",
      location: "sustainability.js:exportESGReportPdf:libs-check",
      message: "Checking html2canvas & jsPDF availability",
      data: {
        month,
        year,
        html2canvasType: typeof html2canvas,
        hasWindowJsPdf: !!(window && window.jspdf),
      },
      timestamp: Date.now(),
    }),
  }).catch(() => {});
  // #endregion

  // Defensive checks for external libraries
  if (typeof html2canvas === "undefined" || !window.jspdf) {
    // #region agent log - libs missing branch (H1)
    fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        sessionId: "debug-session",
        runId: "initial",
        hypothesisId: "H1",
        location: "sustainability.js:exportESGReportPdf:libs-missing",
        message: "PDF export aborted - libraries missing",
        data: {
          html2canvasType: typeof html2canvas,
          hasWindowJsPdf: !!(window && window.jspdf),
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {});
    // #endregion

    alert(
      "PDF export libraries are not loaded. Please refresh the page and try again."
    );
    return;
  }

  const { jsPDF } = window.jspdf;

  const pdfBtn = document.getElementById("export-esg-pdf");
  const originalText = pdfBtn.innerHTML;
  pdfBtn.disabled = true;
  pdfBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing PDF...';

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
    "10": "October",
    "11": "November",
    "12": "December",
  };
  const periodLabel = `${monthNames[month] || month} ${year}`;

  try {
    // Capture only the main analytics content so the PDF is clean
    const analyticsPage = document.querySelector(".analytics-page");
    if (!analyticsPage) {
      throw new Error("Analytics content not found on the page.");
    }

    // We temporarily add a subtle "PDF" styling hook if needed in future;
    // for now, we just ensure scrollbars are not cut off.
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "visible";

    const canvas = await html2canvas(analyticsPage, {
      // Use scale 1 to avoid extremely large canvases that can exhaust memory
      scale: 1,
      useCORS: true,
      scrollY: -window.scrollY,
      backgroundColor: "#ffffff",
    });

    document.body.style.overflow = previousOverflow;

    // #region agent log - canvas dimensions after render (H2)
    fetch("http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        sessionId: "debug-session",
        runId: "post-fix1",
        hypothesisId: "H2",
        location: "sustainability.js:exportESGReportPdf:canvas-created",
        message: "Canvas created for PDF export",
        data: {
          canvasWidth: canvas.width,
          canvasHeight: canvas.height,
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {});
    // #endregion

    // Use JPEG with moderate quality to further reduce memory footprint
    const imgData = canvas.toDataURL("image/jpeg", 0.85);

    // Create a landscape A4 PDF
    const pdf = new jsPDF("landscape", "pt", "a4");
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();

    // --- Header: logo placeholder, title, and selected period ---
    let cursorY = 40;

    // Placeholder for company logo (user can replace later)
    pdf.setDrawColor(180);
    pdf.setLineWidth(0.5);
    const logoX = 40;
    const logoY = cursorY;
    const logoWidth = 140;
    const logoHeight = 40;
    pdf.rect(logoX, logoY, logoWidth, logoHeight);
    pdf.setFont("helvetica", "normal");
    pdf.setFontSize(10);
    pdf.text("Company Logo", logoX + 20, logoY + 24);

    // Report title
    pdf.setFont("helvetica", "bold");
    pdf.setFontSize(18);
    pdf.text("Sustainability Analytics Report", pageWidth / 2, cursorY + 18, {
      align: "center",
    });

    // Selected period
    pdf.setFont("helvetica", "normal");
    pdf.setFontSize(12);
    pdf.text(`Period: ${periodLabel}`, pageWidth / 2, cursorY + 38, {
      align: "center",
    });

    // Generated timestamp (small, right-aligned)
    const generatedAt = new Date().toLocaleString();
    pdf.setFontSize(9);
    pdf.text(`Generated on: ${generatedAt}`, pageWidth - 40, logoY + 12, {
      align: "right",
    });

    // Slight separator under header
    const contentStartY = logoY + logoHeight + 20;
    pdf.setDrawColor(220);
    pdf.line(40, contentStartY - 10, pageWidth - 40, contentStartY - 10);

    // --- Body: captured analytics content with automatic page breaks ---
    const imgWidth = pageWidth - 80; // 40pt margin on each side
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    let heightLeft = imgHeight;
    let position = contentStartY;

    pdf.addImage(imgData, "PNG", 40, position, imgWidth, imgHeight);
    heightLeft -= pageHeight - contentStartY;

    while (heightLeft > 0) {
      pdf.addPage("landscape");

      // Optional: footer page number for multi-page reports
      pdf.setFont("helvetica", "italic");
      pdf.setFontSize(9);
      const pageLabel = `Page ${pdf.internal.getNumberOfPages()}`;
      pdf.text(pageLabel, pageWidth - 40, pageHeight - 20, {
        align: "right",
      });

      position = contentStartY - heightLeft;
      pdf.addImage(imgData, "PNG", 40, position, imgWidth, imgHeight);
      heightLeft -= pageHeight;
    }

    // Save with a descriptive filename
    pdf.save(`Sustainability_Analytics_Report_${year}_${month}.pdf`);
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
          stack: error && error.stack ? String(error.stack).slice(0, 500) : null,
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {});
    // #endregion

    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "PDF Export Failed",
        text:
          error.message ||
          "An error occurred while generating the PDF report. Please try again.",
        confirmButtonText: "OK",
      });
    } else {
      alert(
        "Error generating PDF report: " +
          (error.message || "Please try again later.")
      );
    }
  } finally {
    pdfBtn.disabled = false;
    pdfBtn.innerHTML = originalText;
  }
}
