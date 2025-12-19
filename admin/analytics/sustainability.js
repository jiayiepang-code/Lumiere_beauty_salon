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
    if (!blob.type.includes('pdf') && blob.size > 0) {
      // Might be an error message
      const text = await blob.text();
      if (text.includes('Error') || text.includes('error')) {
        throw new Error(text.substring(0, 200));
      }
    }
    
    // Create download link
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ESG_Report_${year}_${month}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    // Show success message
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "success",
        title: "PDF Generated",
        text: "ESG Report PDF has been downloaded.",
        timer: 2000,
        showConfirmButton: false,
      });
    }
    
    // Re-enable button
    pdfBtn.disabled = false;
    pdfBtn.innerHTML = originalText;
    
  } catch (error) {
    console.error("Error exporting PDF:", error);
    
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
