// Sustainability Analytics Dashboard JavaScript
// PDF Export Functionality

document.addEventListener("DOMContentLoaded", function () {
  // Initialize export button
  const exportBtn = document.getElementById("export-esg-pdf");
  if (exportBtn) {
    exportBtn.addEventListener("click", exportESGReport);
  }
});

/**
 * Export ESG Report as PDF
 */
async function exportESGReport() {
  const month = document.getElementById("month-select").value;
  const year = document.getElementById("year-select").value;

  if (!month || !year) {
    alert("Please select both month and year before exporting.");
    return;
  }

  // Show loading state
  const exportBtn = document.getElementById("export-esg-pdf");
  const originalText = exportBtn.innerHTML;
  exportBtn.disabled = true;
  exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

  try {
    const response = await fetch(
      `../../api/admin/analytics/export_esg_pdf.php?month=${month}&year=${year}`
    );

    if (response.ok) {
      // Check if response is PDF
      const contentType = response.headers.get("content-type");

      if (contentType && contentType.includes("application/pdf")) {
        // Handle PDF download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
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
            title: "Export Successful",
            text: "ESG Report has been downloaded successfully.",
            timer: 2000,
            showConfirmButton: false,
          });
        } else {
          alert("ESG Report exported successfully!");
        }
      } else if (contentType && contentType.includes("text/plain")) {
        // Handle text file fallback
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `ESG_Report_${year}_${month}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        alert(
          "ESG Report exported as text file. PDF library not installed. Please install TCPDF for PDF export."
        );
      } else {
        // Handle error response
        const text = await response.text();
        throw new Error(text || "Unknown error occurred");
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
